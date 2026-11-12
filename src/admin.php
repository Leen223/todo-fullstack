<?php
declare(strict_types=1);


function routeAdmin(string $resource, ?string $id, string $method): void
{
    $admin = requireRole('admin');

    if ($resource === 'users' && $id === null) {
        if ($method !== 'GET') {
            jsonError(405, 'Method not allowed');
        }
        listUsers();
    }

    if ($resource === 'tasks' && $id === null) {
        if ($method !== 'GET') {
            jsonError(405, 'Method not allowed');
        }
        listAllTasks();
    }

    if ($resource === 'users' && $id !== null) {
        if ($method !== 'DELETE') {
            jsonError(405, 'Method not allowed');
        }
        if (!ctype_digit($id) || (int) $id < 1) {
            jsonError(400, 'Invalid user id');
        }
        deleteUser((int) $id, $admin['id']);
    }

    jsonError(404, 'Endpoint not found');
}

function listUsers(): void
{
    $rows = db()->query(
        'SELECT id, name, email, role, created_at FROM users ORDER BY id'
    )->fetchAll();

    jsonResponse(200, array_map('publicUser', $rows));
}

function listAllTasks(): void
{
    $rows = db()->query(
        'SELECT t.id, t.title, t.is_completed, t.created_at, t.updated_at, t.user_id, u.name AS owner_name
         FROM tasks t
         LEFT JOIN users u ON u.id = t.user_id
         ORDER BY t.created_at DESC, t.id DESC'
    )->fetchAll();

    $tasks = array_map(function (array $row): array {
        $task               = formatTask($row);
        $task['user_id']    = $row['user_id'] === null ? null : (int) $row['user_id'];
        $task['owner_name'] = $row['owner_name'];
        return $task;
    }, $rows);

    jsonResponse(200, $tasks);
}

function deleteUser(int $id, int $adminId): void
{
    if ($id === $adminId) {
        jsonError(400, 'You cannot delete your own account');
    }

    $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        jsonError(404, 'User not found');
    }
    jsonResponse(204);
}
