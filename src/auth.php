<?php
declare(strict_types=1);

const MIN_PASSWORD_LENGTH = 8;
const MAX_PASSWORD_BYTES  = 72; 

const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

function stringField(array $data, string $key): string
{
    $value = $data[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function publicUser(array $row): array
{
    return [
        'id'         => (int) $row['id'],
        'name'       => $row['name'],
        'email'      => $row['email'],
        'role'       => $row['role'],
        'created_at' => $row['created_at'],
    ];
}

function findUserById(int $id): ?array
{
    $stmt = db()->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}


function routeAuth(string $action, string $method): void
{
    switch ($action) {
        case 'register':
            $allowed = 'POST';
            $handler = 'registerUser';
            break;
        case 'login':
            $allowed = 'POST';
            $handler = 'loginUser';
            break;
        case 'me':
            $allowed = 'GET';
            $handler = 'getProfile';
            break;
        default:
            jsonError(404, 'Endpoint not found');
    }

    if ($method !== $allowed) {
        jsonError(405, 'Method not allowed');
    }
    $handler();
}

function registerUser(): void
{
    $data  = readJson();
    $name  = stringField($data, 'name');
    $email = mb_strtolower(stringField($data, 'email'));
    $password = is_string($data['password'] ?? null) ? $data['password'] : '';

    if ($name === '') {
        jsonError(400, 'Name is required');
    }
    if (mb_strlen($name) > 100) {
        jsonError(400, 'Name must be 100 characters or fewer');
    }
    if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        jsonError(400, 'A valid email address is required');
    }
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        jsonError(400, 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters');
    }
    if (strlen($password) > MAX_PASSWORD_BYTES) {
        jsonError(400, 'Password must be at most ' . MAX_PASSWORD_BYTES . ' characters');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, $hash]);
    } catch (PDOException $e) {
        // 1062 = duplicate entry (email is UNIQUE)
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            jsonError(409, 'This email is already registered');
        }
        throw $e;
    }

    $user = findUserById((int) db()->lastInsertId());
    jsonResponse(201, publicUser($user));
}


function loginUser(): void
{
    $data     = readJson();
    $email    = mb_strtolower(stringField($data, 'email'));
    $password = is_string($data['password'] ?? null) ? $data['password'] : '';

    if ($email === '' || $password === '') {
        jsonError(400, 'Email and password are required');
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, password_hash, role, created_at FROM users WHERE email = ?'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();


    $hash = is_array($user) ? $user['password_hash'] : DUMMY_HASH;
    $ok   = password_verify($password, $hash);

    if (!is_array($user) || !$ok) {
        jsonError(401, 'Invalid email or password');
    }

    $lifetime = (int) env('JWT_EXPIRES_IN', '3600');
    if ($lifetime < 60) {
        $lifetime = 3600;
    }
    $now = time();

    $token = jwtEncode([
        'id'    => (int) $user['id'],
        'email' => $user['email'],
        'role'  => $user['role'],
        'iat'   => $now,
        'exp'   => $now + $lifetime,
    ]);

    jsonResponse(200, [
        'token'      => $token,
        'token_type' => 'Bearer',
        'expires_in' => $lifetime,
        'user'       => publicUser($user),
    ]);
}

function getProfile(): void
{
    $auth = requireAuth();
    $user = findUserById($auth['id']);

    if ($user === null) {
        jsonError(401, 'Unauthorized: this account no longer exists');
    }
    jsonResponse(200, publicUser($user));
}
