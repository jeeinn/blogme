<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap/app.php';

$app = bootstrap_app();

$command = $argv[1] ?? '';
if (!in_array($command, ['reset-password', 'migrate'], true)) {
    echo "Usage:\n";
    echo "  php cli.php migrate\n";
    echo "  php cli.php reset-password <email>\n";
    exit(1);
}

if ($command === 'migrate') {
    $app->db()->migrate();
    echo "Database migration completed.\n";
    exit(0);
}

$app->db()->migrate();

$email = $argv[2] ?? '';
if ($email === '') {
    echo "Email is required.\n";
    exit(1);
}

$user = $app->users()->byEmail($email);
if ($user === null) {
    echo "User not found: {$email}\n";
    exit(1);
}

$random = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
$app->users()->updatePassword((string) $user['ID'], password_hash($random, PASSWORD_BCRYPT));
echo "Password for {$email} has been reset to: \"{$random}\"\n";
