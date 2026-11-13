<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/user_storage.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/test_service.php';
require_once __DIR__ . '/admin.php';


function handleUpdate($update, $config)
{
    if (!isset($update['update_id'])) {
        return;
    }

    $pdo = getDatabase($config);

    $stmt = $pdo->prepare(
        'INSERT OR IGNORE INTO processed_updates (update_id) VALUES (?)'
    );

    $stmt->execute([
        $update['update_id']
    ]);

    if ($stmt->rowCount() === 0) {
        return;
    }

    if (!isset($update['message'])) {
        return;
    }

    $message = $update['message'];

    $userId = $message['chat']['id'] ?? null;

    $text = trim($message['text'] ?? '');

    $username = $message['from']['username'] ?? null;

    $firstName = $message['from']['first_name'] ?? null;

    handleMessage($userId, $text, $username, $firstName, $config);
}

function handleMessage($userId, $text, $username, $firstName, $config) {

    // START

    if ($text === '/start') {

        $keyboard = [
            'keyboard' => [
                [
                    ['text' => '🚀 Начать тест']
                ]
            ],
            'resize_keyboard' => true
        ];

        if (isAdmin($userId, $config)) {

            $keyboard['keyboard'][] = [
                ['text' => '🔐 Админ-панель']
            ];
        }

        TG_sendMessage($userId,
            "<b>👋 Добро пожаловать!</b>\n\n"
            . "Примерный текст для описания и начала теста\n\n"
            . "<b>Готовы начать?</b>",
            $keyboard, $config);

        return;
    }

    // Админ-панель

    if ($text === '🔐 Админ-панель') {

        if (!isAdmin($userId, $config)) {
            return;
        }

        showAdminPanel($userId, $config);

        return;
    }

    //Посмотреть результаты

    if ($text === '👥 Посмотреть результаты' || $text === 'Посмотреть результаты') {

        if (!isAdmin($userId, $config)) {
            return;
        }

        showAdminUsers($userId, $config);

        return;
    }

    //Начать тест

    if ($text === '📝 Начать тест' || $text === 'Начать тест' || $text === '🚀 Начать тест') {

        startTest($userId, $username, $firstName, $config);

        return;
    }

    //Получаем состояние пользователя

    $user = getUser($userId, $config);

    $state = $user['state'] ?? null;

    // Возврат к предыдущему вопросу

    if ($user !== null && $state === 'testing' && $text === '⬅️ Назад') {

        goToPreviousQuestion($userId, $config);

        return;
    }

    // Админ ожидает юзернейм

    if (isAdmin($userId, $config) && $state === 'admin_waiting_username'){

        handleAdminUsername($userId, $text, $config);

        return;
    }

    //Админ ожидает номер теста

    if (isAdmin($userId, $config) && $state === 'admin_waiting_test'){

        handleAdminTestSelection($userId, $text, $config);

        return;
    }

    //Пользователь проходит тест

    if ($user !== null && $state === 'testing') {

        processAnswer($userId, $text, $config);

        return;
    }

    // Непонятное сообщение

    TG_sendMessage($userId, 'Я вас не понимаю 🤔', null, $config);
}