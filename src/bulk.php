<?php
declare(strict_types=1);

const MAX_BULK_ITEMS = 50;

// POST /api/tasks/bulk   body: {"titles": ["Task 1", "Task 2"]}
// Saves all tasks in ONE transaction: if one fails, none are saved.
function bulkCreateTasks(): void
{
    $data   = readJson();
    $titles = $data['titles'] ?? null;

    if (!is_array($titles) || !array_is_list($titles) || count($titles) === 0) {
        jsonError(400, 'titles must be a non-empty array');
    }
    if (count($titles) > MAX_BULK_ITEMS) {
        jsonError(400, 'You can add at most ' . MAX_BULK_ITEMS . ' tasks at once');
    }

    $clean = [];
    foreach ($titles as $i => $raw) {
        $clean[] = validTitle($raw, 'Task ' . ($i + 1) . ': ');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $insert = $pdo->prepare('INSERT INTO tasks (title) VALUES (?)');
        $select = $pdo->prepare(
            'SELECT id, title, is_completed, created_at, updated_at FROM tasks WHERE id = ?'
        );

        // Insert from the last line to the first, so the first line gets the highest id
        // and shows on top of the list (newest first).
        $created = array_fill(0, count($clean), null);
        for ($i = count($clean) - 1; $i >= 0; $i--) {
            $insert->execute([$clean[$i]]);
            $select->execute([(int) $pdo->lastInsertId()]);
            $created[$i] = formatTask($select->fetch());
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    jsonResponse(201, $created);
}
