<?php

require_once __DIR__ . '/user_storage.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/result_service.php';

function getTests($config) //Получает тест из базы данных, в этом случае джесон
{
    $file = $config['data_path'] . 'tests.json'; // путь к тесту

    $data = file_get_contents($file); // получение самого теста

    return json_decode($data, true); // джесон в массив
}

// Запуск теста

function startTest($userId, $username, $firstName, $config) {

    $db = getDatabase($config); // получение бд

    //Удаляем старые временные ответы

    $stmt = $db->prepare("
        DELETE FROM current_answers
        WHERE telegram_id = ?
    ");

    $stmt->execute([$userId]);

    //Создаём состояние пользователя

    $user = [
        'username' => $username,
        'first_name' => $firstName,
        'state' => 'testing',
        'test_id' => 'test_1',
        'question' => 1,
        'score' => 0
    ];

    saveUser($db, $userId, $user);

    sendQuestion($userId, $user, $config);
}

function sendQuestion($userId, $user, $config) {

    $tests = getTests($config); // получаем все тесты в джесон

    $test = $tests[$user['test_id']]; // получаем конкретный тест

    $questionNumber = $user['question']; // получаем номер текущего вопроса

    $question = $test['questions'][$questionNumber - 1]; // уменьшаем его на 1, чтобы вывести корректный текущий вопрос

    // Добавляем варианты ответа

    foreach ($question['answers'] as $answer) {

        $keyboard['keyboard'][] = [
            [
                'text' => $answer['text']
            ]
        ];
    }

    // Если это не первый вопрос, добавляем кнопку возврата

    if ($questionNumber > 1) {

        $keyboard['keyboard'][] = [
            [
                'text' => '⬅️ Назад'
            ]
        ];
    }

    TG_sendMessage($userId, $question['text'], $keyboard, $config);
}

function goToPreviousQuestion($userId, $config)
{
    $db = getDatabase($config);
    $user = getUser($userId, $config);

    if ($user === null) {
        return;
    }

    // На первом вопросе назад идти нельзя

    if ($user['question'] <= 1) {
        sendQuestion($userId, $user, $config);
        return;
    }

    // Переходим к предыдущему вопросу

    $user['question']--;

    saveUser($db, $userId, $user);

    sendQuestion($userId, $user, $config);
}

function processAnswer($userId, $answerText, $config) {

    $user = getUser($userId, $config); // получаем состояние пользователя

    if ($user === null) {
        return;
    }

    $tests = getTests($config); // получаем все тесты

    $test = $tests[$user['test_id']];

    $questionNumber = $user['question'];

    $question = $test['questions'][$questionNumber - 1];

    /* Ищем ответ*/

    $points = null;

    foreach ($question['answers'] as $answer) {

        if ($answer['text'] === $answerText) {

            $points = $answer['points'];

            break;
        }
    } // сравниваем ответ пользователя с ответом из джесона

    if ($points === null) {

        TG_sendMessage($userId, 'Пожалуйста, выберите один из вариантов ответа.', null, $config);

        return;
    }

    // Получаем базу данных

    $db = getDatabase($config);

    // Проверяем, отвечал ли пользователь на этот вопрос раньше

    $stmt = $db->prepare("
        SELECT id
        FROM current_answers
        WHERE telegram_id = ?
          AND question = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$userId, $question['text']]);

    $existingAnswer = $stmt->fetch(PDO::FETCH_ASSOC);

    // Если ответ уже существует — заменяем его

    if ($existingAnswer !== false) {

        $stmt = $db->prepare("
            UPDATE current_answers
            SET answer = ?, points = ?
            WHERE id = ?
        ");

        $stmt->execute([$answerText, $points, $existingAnswer['id']]);

    } else {

        // Если ответа ещё нет — создаём новую запись

        $stmt = $db->prepare("
            INSERT INTO current_answers (
                telegram_id,
                question,
                answer,
                points
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([$userId, $question['text'], $answerText, $points]);
    }

    // Пересчитываем общий результат

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(points), 0)
        FROM current_answers
        WHERE telegram_id = ?
    ");

    $stmt->execute([$userId]);
    $scoreRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $user['score'] = (int)(array_values($scoreRow)[0] ?? 0);


    // Последний вопрос?

    $lastQuestion = count($test['questions']);

    if ($questionNumber >= $lastQuestion) {

        try {

            //Начинаем транзакцию до сохранения итогового результата

            $db->beginTransaction();

            // Получаем все ответы пользователя

            $stmt = $db->prepare("
                SELECT question, answer, points
                FROM current_answers
                WHERE telegram_id = ?
                ORDER BY id ASC
            ");

            $stmt->execute([$userId]);

            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            //Сохраняем результат

            saveResult($db, $userId, $user, $answers);

            // Удаляем временные ответы

            $stmt = $db->prepare("
                DELETE FROM current_answers
                WHERE telegram_id = ?
            ");

            $stmt->execute([$userId]);

            //Удаляем пользователяиз незавершённых тестов

            deleteUser($db, $userId);

            $db->commit();

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Error saving test result for user ' . $userId . ': ' . $e->getMessage());

            TG_sendMessage($userId, 'Произошла ошибка при сохранении результата. Пожалуйста, попробуйте ещё раз.', null, $config);

            return;
        }

        //Формируем итоговый результат

        $result = getResultByScore($user['score']);

        removeKeyboard($userId, "✅ <b>Тестирование завершено</b>\n\n" . "📊Ваш результат:\n\n" . $result, $config);

        return;
    }

    //Переходим к следующему вопросу

    $user['question']++;

    saveUser($db, $userId, $user);

    sendQuestion($userId, $user, $config);
}
