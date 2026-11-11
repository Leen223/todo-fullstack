<?php
declare(strict_types=1);


function bearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }

    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

function requireAuth(): array
{
    $token   = bearerToken();
    $payload = $token === null ? null : jwtDecode($token);

    if ($payload === null || !isset($payload['id'], $payload['email'], $payload['role'])) {
        jsonError(401, 'Unauthorized: missing, invalid or expired token');
    }

    return [
        'id'    => (int) $payload['id'],
        'email' => (string) $payload['email'],
        'role'  => (string) $payload['role'],
    ];
}
