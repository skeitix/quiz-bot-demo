<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(
    __DIR__ . '/..'
);

$dotenv->load();

require_once __DIR__ . '/database.php';

$config = require __DIR__ . '/../config/config.php';

$db = getDatabase($config); // получаем бд

// Результаты храним 30 дней

$days = 30;

$limitDate = date('Y-m-d H:i:s', strtotime("-{$days} days")); // вычисляем дату

// Получаем старые результаты

$stmt = $db->prepare("
    SELECT id
    FROM results
    WHERE created_at < ?
");

$stmt->execute([$limitDate]);

$oldResults = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Удаляем ответы старых результатов

$deleteAnswers = $db->prepare("
    DELETE FROM answers
    WHERE result_id = ?
");

// Удаляем сами результаты

$deleteResults = $db->prepare("
    DELETE FROM results
    WHERE id = ?
");

foreach ($oldResults as $resultId) {

    $deleteAnswers->execute([$resultId]);

    $deleteResults->execute([$resultId]);
}

echo 'Old results deleted: ' . count($oldResults);

$stmt = $db->prepare("
    DELETE FROM current_answers
    WHERE telegram_id IN (
        SELECT telegram_id
        FROM users
        WHERE created_at < ?
    )
");

$limitDate = date('Y-m-d H:i:s', strtotime('-1 day'));

$stmt->execute([$limitDate]);

$stmt = $db->prepare("
    DELETE FROM users
    WHERE created_at < ?
");

$stmt->execute([$limitDate]);

// Удаляем старые обработанные Telegram updates

$limitDate = date('Y-m-d H:i:s', strtotime('-30 days'));

$stmt = $db->prepare("
    DELETE FROM processed_updates
    WHERE processed_at < ?
");

$stmt->execute([$limitDate]);

echo PHP_EOL . 'Old processed updates deleted: ' . $stmt->rowCount();