<?php
declare(strict_types=1);


function jsonResponse(int $status, mixed $data = null): never
{
    http_response_code($status);
    if ($status !== 204) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

function jsonError(int $status, string $message): never
{
    jsonResponse($status, ['error' => $message]);
}


function readJson(): array
{
    $data = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($data)) {
        jsonError(400, 'Request body must be valid JSON');
    }
    return $data;
}


function validTitle(mixed $value, string $prefix = ''): string
{
    if (!is_string($value)) {
        jsonError(400, $prefix . 'Title is required and must be text');
    }
    $title = trim($value);
    if ($title === '') {
        jsonError(400, $prefix . 'Title is required and cannot be empty');
    }
    if (mb_strlen($title) > 255) {
        jsonError(400, $prefix . 'Title must be 255 characters or fewer');
    }
    return $title;
}


function formatTask(array $row): array
{
    return [
        'id'           => (int) $row['id'],
        'title'        => $row['title'],
        'is_completed' => (bool) $row['is_completed'],
        'created_at'   => $row['created_at'],
        'updated_at'   => $row['updated_at'],
    ];
}
