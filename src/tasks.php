<?php
declare(strict_types=1);



function findTask(int $id, int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT id, title, is_completed, created_at, updated_at FROM tasks WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();

    return $row ? formatTask($row) : null;
}


function listTasks(int $userId): void
{
    $stmt = db()->prepare(
        'SELECT id, title, is_completed, created_at, updated_at FROM tasks WHERE user_id = ? ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute([$userId]);

    jsonResponse(200, array_map('formatTask', $stmt->fetchAll()));
}


function getTask(int $id, int $userId): void
{
    $task = findTask($id, $userId);
    if ($task === null) {
        jsonError(404, 'Task not found');
    }
    jsonResponse(200, $task);
}


function createTask(int $userId): void
{
    $data  = readJson();
    $title = validTitle($data['title'] ?? null);

    $stmt = db()->prepare('INSERT INTO tasks (user_id, title) VALUES (?, ?)');
    $stmt->execute([$userId, $title]);

    jsonResponse(201, findTask((int) db()->lastInsertId(), $userId));
}


function updateTask(int $id, int $userId): void
{
    $data     = readJson();
    $hasTitle = array_key_exists('title', $data);
    $hasDone  = array_key_exists('is_completed', $data);

    if (!$hasTitle && !$hasDone) {
        jsonError(400, 'Send a title and/or is_completed');
    }

    $title = $hasTitle ? validTitle($data['title']) : null;

    if ($hasDone && !is_bool($data['is_completed'])) {
        jsonError(400, 'is_completed must be true or false');
    }
    $done = $hasDone ? (int) $data['is_completed'] : null;

    
    $stmt = db()->prepare(
        'UPDATE tasks SET title = COALESCE(?, title), is_completed = COALESCE(?, is_completed) WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$title, $done, $id, $userId]);

    $task = findTask($id, $userId);
    if ($task === null) {
        jsonError(404, 'Task not found');
    }
    jsonResponse(200, $task);
}


function deleteTask(int $id, int $userId): void
{
    $stmt = db()->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);

    if ($stmt->rowCount() === 0) {
        jsonError(404, 'Task not found');
    }
    jsonResponse(204);
}
