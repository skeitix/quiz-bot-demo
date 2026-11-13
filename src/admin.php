<?php


require_once __DIR__ . '/result_service.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/user_storage.php';

// Экранирование текста для телеграмм хтмл

function escapeTelegramHtml($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Проверка на админа

function isAdmin($userId, $config)
{
    $admins = explode(',', $config['admin_id']); //превращает строку из енв в массив разделяет по запятым

    $admins = array_map('trim', $admins);

    return in_array((string)$userId, $admins, true); // проверяем есть ли айди юзера в айди админов
}

// Админ-панель

function showAdminPanel($userId, $config) :void
{
    $keyboard = [
        'keyboard' => [
            [
                ['text' => '👥 Посмотреть результаты']
            ],
            [
                ['text' => '📝 Начать тест']
            ]
        ],
        'resize_keyboard' => true
    ];

    TG_sendMessage($userId, "🔐 <b>Админ-панель</b>\n\nУправление результатами тестирования:\n\n👥 <b>Просмотр результатов</b>\nПосмотреть пользователей и результаты их прохождений.\n\n📝 <b>Пройти тест</b>\nЗапустить тестирование от своего аккаунта. ", $keyboard, $config);
}

// Список пользователей

function showAdminUsers($chatId, $config) //вызывается когда админ нажал посмотреть результат
{
    $users = getUsersWithResults($config); // дает массив пользователей с результатами

    if (!$users)
    {
        TG_sendMessage($chatId, 'Пока никто не прошёл тест.', null, $config);

        return;
    }

    $text = "👥 <b>Пользователи:</b>\n\n";

    foreach ($users as $user) {

        if (!empty($user['username']))
        {
            $username = escapeTelegramHtml($user['username']);
            $text .= '@' . $username . "\n";
        }
    }

    $text .= "\n✏️ Введите username пользователя:";

    TG_sendMessage($chatId, $text, null, $config); // отправка текста с пользователями

    updateUserState($config, $chatId, 'admin_waiting_username'); // записывает в бд состояние админа
}

// Админ выбрал юзернейм пользователя

function handleAdminUsername($chatId, $username, $config) {
    $username = ltrim(trim($username), '@'); // убираем собаку и пробелы

    $results = getResultsByUsername($config, $username); // получаем все результаты пользователя

    if (!$results) {
        $safeUsername = escapeTelegramHtml($username);
        TG_sendMessage($chatId, "Пользователь @{$safeUsername} не найден.", null, $config);

        return;
    }

    // Получаем Telegram ID пользователя

    $targetTelegramId = $results[0]['telegram_id'];

    // Запоминаем, какого пользователя сейчас смотрит админ

    updateAdminTarget($config, $chatId, $targetTelegramId);

    // Показываем все прохождения
    $safeUsername = escapeTelegramHtml($username);
    $text = "👤 @{$safeUsername}\n\n";

    $text .= "📊Всего прохождений: " . count($results) . "\n\n";

    foreach ($results as $index => $result) { // перебираем все прохождения, индекс номер элемента массива

        $testNumber = $index + 1; // 0+1 = 1 тест

        $date = date('d.m.Y H:i', strtotime($result['created_at']));

        $text .= "<b>Тест {$testNumber}</b>\n";

        $text .= "Дата: " . escapeTelegramHtml($date) . "\n";

        $text .= "<b>Баллы: </b>" . escapeTelegramHtml($result['score']) . "\n\n";
    }

    $text .= "📋<b>Введите номер теста:</b>";

    TG_sendMessage($chatId, $text, null, $config);

    // Ожидаем номер теста

    updateUserState($config, $chatId, 'admin_waiting_test'); // меняем состояние админа
}

// Админ выбрал номер теста

function handleAdminTestSelection($chatId, $text, $config) {
    $text = trim($text);

    //Проверяем, что введено число

    if (!ctype_digit($text)) {

        TG_sendMessage($chatId, 'Введите номер теста, например: 1', null, $config);

        return;
    }

    $testNumber = (int)$text;

    //Получаем выбранного пользователя

    $targetTelegramId = getAdminTarget($config, $chatId);

    if (!$targetTelegramId) {

        TG_sendMessage($chatId, 'Пользователь не выбран.', null, $config);

        return;
    }
    //Получаем все его тесты

    $results = getUserResults($config, $targetTelegramId);

    // Преобразуем:Тест 1 → $results[0] Тест 2 → $results[1] Тест 3 → $results[2]

    $index = $testNumber - 1;

    if (!isset($results[$index])) {

        TG_sendMessage($chatId, 'Такого теста нет.', null, $config);

        return;
    }

    $result = $results[$index];

    // Получаем ответы именно этого прохождения

    $answers = getResultAnswers($config, $result['id']);

    //Формируем результат
    $safeUsername = escapeTelegramHtml($result['username']);
    $safeFirstName = escapeTelegramHtml($result['first_name']);
    $safeScore = escapeTelegramHtml($result['score']);
    $safeDate = escapeTelegramHtml(date('d.m.Y H:i', strtotime($result['created_at'])));

    $text = "📋 Тест {$testNumber}\n\n";

    $text .= "👤 @{$safeUsername}\n";

    $text .= "Имя: {$safeFirstName}\n";

    $text .= "Баллы: {$safeScore}\n";

    $text .= "Дата: {$safeDate}\n\n";

    $text .= "Ответы:\n\n";

    foreach ($answers as $index => $answer) {

        $number = $index + 1;

        $safeQuestion = escapeTelegramHtml($answer['question']);
        $safeAnswer = escapeTelegramHtml($answer['answer']);

        $safePoints = escapeTelegramHtml($answer['points']);

        $text .= "{$number}. {$safeQuestion}\n";

        $text .= "Ответ: {$safeAnswer}\n";

        $text .= "Баллы: {$safePoints}\n\n";
    }

    TG_sendMessage($chatId, $text, null, $config);

    // После просмотра снова ждём username

    updateUserState($config, $chatId, 'admin_waiting_username');
}