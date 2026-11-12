<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config.php';
require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/helpers.php';
require __DIR__ . '/../../src/jwt.php';
require __DIR__ . '/../../src/middleware.php';
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/tasks.php';
require __DIR__ . '/../../src/bulk.php';
require __DIR__ . '/../../src/admin.php';

loadEnv(__DIR__ . '/../../.env');

// CORS (needed only if the frontend runs on a different origin)
header('Access-Control-Allow-Origin: ' . env('CORS_ORIGIN', '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

try {
    // ---- /api/auth/register, /api/auth/login, /api/auth/me ----
    if (preg_match('#/api/auth/([a-z]+)/?$#', $path, $a)) {
        routeAuth($a[1], $method);
    }

    // ---- /api/admin/users, /api/admin/tasks (admin only) ----
    if (preg_match('#/api/admin/(users|tasks)(?:/([^/]+))?/?$#', $path, $ad)) {
        routeAdmin($ad[1], $ad[2] ?? null, $method);
    }

    // ---- /api/tasks ... (works from any folder) ----
    if (!preg_match('#/api/tasks(?:/([^/]+))?/?$#', $path, $m)) {
        jsonError(404, 'Endpoint not found');
    }
    $id = $m[1] ?? null;

    // Every task endpoint needs a valid token (401 otherwise)
    $user   = requireAuth();
    $userId = $user['id'];

    if ($id === null) {
        // /api/tasks
        switch ($method) {
            case 'GET':
                listTasks($userId);
                break;
            case 'POST':
                createTask($userId);
                break;
            default:
                jsonError(405, 'Method not allowed');
        }
    } else {
        // /api/tasks/bulk
        if ($id === 'bulk') {
            if ($method !== 'POST') {
                jsonError(405, 'Method not allowed');
            }
            bulkCreateTasks($userId);
        }

        // ---- single task ----
        if (!ctype_digit($id) || (int) $id < 1) {
            jsonError(400, 'Invalid task id');
        }
        $id = (int) $id;

        switch ($method) {
            case 'GET':
                getTask($id, $userId);
                break;
            case 'PUT':
                updateTask($id, $userId);
                break;
            case 'DELETE':
                deleteTask($id, $userId);
                break;
            default:
                jsonError(405, 'Method not allowed');
        }
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonError(500, 'Internal server error');
}
