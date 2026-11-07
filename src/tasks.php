<?php
declare(strict_types=1);

function findTask(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, title, is_completed, created_at, updated_at FROM tasks WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ? formatTask($row) : null;
}


function listTasks(): void
{
    $rows = db()->query(
        'SELECT id, title, is_completed, created_at, updated_at FROM tasks ORDER BY created_at DESC, id DESC'
    )->fetchAll();

    jsonResponse(200, array_map('formatTask', $rows));
}


function getTask(int $id): void
{
    $task = findTask($id);
    if ($task === null) {
        jsonError(404, 'Task not found');
    }
    jsonResponse(200, $task);
}


function createTask(): void
{
    $data  = readJson();
    $title = validTitle($data['title'] ?? null);

    $stmt = db()->prepare('INSERT INTO tasks (title) VALUES (?)');
    $stmt->execute([$title]);

    jsonResponse(201, findTask((int) db()->lastInsertId()));
}


function updateTask(int $id): void
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
        'UPDATE tasks SET title = COALESCE(?, title), is_completed = COALESCE(?, is_completed) WHERE id = ?'
    );
    $stmt->execute([$title, $done, $id]);

    $task = findTask($id);
    if ($task === null) {
        jsonError(404, 'Task not found');
    }
    jsonResponse(200, $task);
}


function deleteTask(int $id): void
{
    $stmt = db()->prepare('DELETE FROM tasks WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        jsonError(404, 'Task not found');
    }
    jsonResponse(204);
}
