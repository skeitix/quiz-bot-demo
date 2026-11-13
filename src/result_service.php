<?php
function getResultByScore($score)
{
    if ($score >= 14 && $score <= 20) {

        return "Результат 1";
    }

    elseif ($score >= 21 && $score <= 30) {

        return "Результат 2";
    }

    elseif ($score >= 31 && $score <= 42) {

        return "Результат 3";
    }

    elseif ($score >= 43 && $score <= 56) {

        return "Результат 4";
    }

    else {

        return "Результат 4";
    }
}


// Сохранение результата

function saveResult($db, $userId, $user, $answers) {

    // Создаём запись результата

    $stmt = $db->prepare(" 
        INSERT INTO results (telegram_id,username,first_name,score) 
        VALUES (?, ?, ?, ?)  
    ");    // вставляет новую строку в таблицу резулт, в какие столбцы собираемся записать результат параметры которые будут записаны позже создание sql запроса

    $stmt->execute([$userId, $user['username'], $user['first_name'], $user['score']]); // Безопасно передаем значения

    // Получаем ID результата

    $resultId = $db->lastInsertId();

    // Сохраняем ответы

    $stmt = $db->prepare("
        INSERT INTO answers (result_id,question,answer,points)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($answers as $answer) {

        $stmt->execute([$resultId, $answer['question'], $answer['answer'], $answer['points']]);
    }
}

//Все результаты по юзернейму

function getResultsByUsername($config, $username) {
    $db = getDatabase($config);

    $username = ltrim(trim($username), '@');

    $stmt = $db->prepare("
        SELECT *
        FROM results
        WHERE username = ?
        ORDER BY created_at ASC
    "); // выбираем все столбцы из таблицы резултс где юзернейм совпадает с переданным значением юзернейма сортируя от старого к новому

    $stmt->execute([$username]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC); // возвращает массив всех найденных строк
}

//Пользователи, которые прошли тест (Один пользователь = одна строка)

function getUsersWithResults($config)
{
    $db = getDatabase($config);

    $stmt = $db->query("
        SELECT
            telegram_id,
            username,
            first_name,
            MAX(created_at) AS last_result
        FROM results
        GROUP BY telegram_id
        ORDER BY last_result DESC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Все прохождения конкретного пользователя

function getUserResults($config, $telegramId) {
    $db = getDatabase($config);

    $stmt = $db->prepare("
        SELECT *
        FROM results
        WHERE telegram_id = ?
        ORDER BY created_at ASC
    ");

    $stmt->execute([$telegramId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Ответы конкретного прохождения

function getResultAnswers($config, $resultId) {
    $db = getDatabase($config);

    $stmt = $db->prepare("
        SELECT *
        FROM answers
        WHERE result_id = ?
        ORDER BY id ASC
    ");

    $stmt->execute([$resultId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}