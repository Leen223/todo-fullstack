<?php
declare(strict_types=1);


function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string|false
{
    $b64 = strtr($data, '-_', '+/');
    $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
    return base64_decode($b64, true);
}

function jwtSecret(): string
{
    $secret = env('JWT_SECRET');
    if (strlen($secret) < 32) {
        throw new RuntimeException('JWT_SECRET is missing or shorter than 32 characters');
    }
    return $secret;
}

function jwtEncode(array $payload): string
{
    $segments = [
        base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
        base64UrlEncode(json_encode($payload)),
    ];
    $signature = hash_hmac('sha256', implode('.', $segments), jwtSecret(), true);
    $segments[] = base64UrlEncode($signature);

    return implode('.', $segments);
}

function jwtDecode(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$header64, $payload64, $signature64] = $parts;

    $header = json_decode((string) base64UrlDecode($header64), true);
    if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
        return null;
    }

    $expected = base64UrlEncode(hash_hmac('sha256', $header64 . '.' . $payload64, jwtSecret(), true));
    if (!hash_equals($expected, $signature64)) {
        return null;
    }

    $payload = json_decode((string) base64UrlDecode($payload64), true);
    if (!is_array($payload) || !isset($payload['exp']) || !is_int($payload['exp']) || $payload['exp'] < time()) {
        return null;
    }

    return $payload;
}
