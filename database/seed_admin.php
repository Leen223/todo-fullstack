<?php
declare(strict_types=1);

// Creates the first admin (or promotes an existing account to admin).
//
// Usage:
//   php database/seed_admin.php "Admin Name" admin@example.com "StrongPassword123"
//
// If the email already exists, that account is promoted and its password is NOT changed.

if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';

loadEnv(__DIR__ . '/../.env');

[, $name, $email, $password] = array_pad($argv, 4, null);

if (!$name || !$email || !$password) {
    exit("Usage: php database/seed_admin.php \"Name\" email@example.com \"Password\"\n");
}

$email = strtolower(trim($email));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    exit("Invalid email address.\n");
}
if (strlen($password) < 8 || strlen($password) > 72) {
    exit("The password must be between 8 and 72 characters.\n");
}

$stmt = db()->prepare(
    "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')
     ON DUPLICATE KEY UPDATE role = 'admin'"
);
$stmt->execute([trim($name), $email, password_hash($password, PASSWORD_BCRYPT)]);

echo "Admin ready: $email\n";
