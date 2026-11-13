<?php

$telegramToken = $_ENV['TELEGRAM_TOKEN'] ?? '';
$adminId = $_ENV['ADMIN_ID'] ?? '';

if ($telegramToken === '') {
    throw new RuntimeException(
        'TELEGRAM_TOKEN is not configured'
    );
}

if ($adminId === '') {
    throw new RuntimeException(
        'ADMIN_ID is not configured'
    );
}

$dataPath = __DIR__ . '/../data/';

if (!is_dir($dataPath)) {
    throw new RuntimeException(
        'Data directory does not exist: ' . $dataPath
    );
}

return [
    'telegram_token' => $telegramToken,
    'admin_id' => $adminId,
    'data_path' => $dataPath
];
