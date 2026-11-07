<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config.php';
require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/helpers.php';
require __DIR__ . '/../../src/tasks.php';

loadEnv(__DIR__ . '/../../.env');

header('Access-Control-Allow-Origin: ' . env('CORS_ORIGIN', '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!preg_match('#/api/tasks(?:/([^/]+))?/?$#', (string) $path, $m)) {
    jsonError(404, 'Endpoint not found');
}
$id = $m[1] ?? null;

try {
    if ($id === null) {
        switch ($method) {
            case 'GET':
                listTasks();
                break;
            case 'POST':
                createTask();
                break;
            default:
                jsonError(405, 'Method not allowed');
        }
    } else {
        if (!ctype_digit($id) || (int) $id < 1) {
            jsonError(400, 'Invalid task id');
        }
        $id = (int) $id;

        switch ($method) {
            case 'GET':
                getTask($id);
                break;
            case 'PUT':
                updateTask($id);
                break;
            case 'DELETE':
                deleteTask($id);
                break;
            default:
                jsonError(405, 'Method not allowed');
        }
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonError(500, 'Internal server error');
}
