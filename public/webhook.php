<?php

require_once __DIR__ . '/../vendor/autoload.php'; // подключение композера

use Dotenv\Dotenv; // используем чтобы читало енв

$dotenv = Dotenv::createImmutable(__DIR__ . '/..'); //используем чтобы читало енв
$dotenv->load(); //используем чтобы читало енв тупо можно скопировать

$config = require __DIR__ . '/../config/config.php'; // тут массив с датой, админом и токеном

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Проверяем секретный токен Telegram.
// Telegram передаёт его в заголовке X-Telegram-Bot-Api-Secret-Token.
$receivedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if (empty($_ENV['WEBHOOK_SECRET']) || !hash_equals($_ENV['WEBHOOK_SECRET'], $receivedSecret))
{
    http_response_code(403);
    exit('Forbidden');
}

$data = file_get_contents('php://input'); // отлов всех пост запросов
$update = json_decode($data, true); // преобразование json в массив

if (!is_array($update)) {
    http_response_code(400);
    exit('Invalid JSON');
}

require_once __DIR__ . '/../src/update_handler.php';

handleUpdate($update, $config);

http_response_code(200);
echo 'OK';
