<?php
declare(strict_types=1);

// Find the project folder that contains src/ and .env. Two layouts are supported:
//   1) src/ and .env next to public/ (local, or above the web root on a host)
//   2) src/ and .env inside the web root (hosts where you cannot go above it)
$root = null;
foreach ([__DIR__ . '/../..', __DIR__ . '/..'] as $candidate) {
    if (is_file($candidate . '/src/config.php')) {
        $root = $candidate;
        break;
    }
}
if ($root === null) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    exit('{"error":"Server is not configured correctly"}');
}

require $root . '/src/config.php';
require $root . '/src/db.php';
require $root . '/src/helpers.php';
require $root . '/src/jwt.php';
require $root . '/src/middleware.php';
require $root . '/src/auth.php';
require $root . '/src/tasks.php';
require $root . '/src/bulk.php';
require $root . '/src/admin.php';

loadEnv($root . '/.env');

// CORS: only the frontend domain from .env is allowed, never "*".
// If the frontend and the API are on the same domain, CORS_ORIGIN can stay empty.
$allowedOrigin = env('CORS_ORIGIN');
if ($allowedOrigin !== '' && $allowedOrigin !== '*') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

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