<?php
require_once __DIR__ . '/database.php';

function getUser($userId, $config)
{
    $db = getDatabase($config);

    $stmt = $db->prepare("
        SELECT *
        FROM users
        WHERE telegram_id = ?
    ");

    $stmt->execute([$userId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function saveUser($db, $userId, $userData)
{

    $stmt = $db->prepare("
        INSERT INTO users (
            telegram_id,
            username,
            first_name,
            state,
            test_id,
            question,
            score,
            admin_target_id
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)

        ON CONFLICT(telegram_id) /* Если пользователь с таким айди существует не создавай нового, а обнови существующего*/
        DO UPDATE SET
            username = excluded.username,
            first_name = excluded.first_name,
            state = excluded.state,
            test_id = excluded.test_id,
            question = excluded.question,
            score = excluded.score,
            admin_target_id = excluded.admin_target_id
    ");

    $stmt->execute([$userId, $userData['username'] ?? null, $userData['first_name'] ?? null, $userData['state'] ?? null, $userData['test_id'] ?? null, $userData['question'] ?? null, $userData['score'] ?? 0, $userData['admin_target_id'] ?? null]);
}

function deleteUser($db, $userId)
{
    $stmt = $db->prepare("
        DELETE FROM users
        WHERE telegram_id = ?
    ");

    $stmt->execute([$userId]);
}

function updateUserState($config, $telegramId, $state)
{
    $db = getDatabase($config);

    // Проверяем, существует ли пользователь

    $user = getUser($telegramId, $config);

    // Если пользователя нет — создаём его

    if ($user === null) {

        $stmt = $db->prepare("
            INSERT INTO users (
                telegram_id,
                state,
                score
            )
            VALUES (?, ?, 0)
        ");

        $stmt->execute([$telegramId, $state]);

        return;
    }

    // Если пользователь существует — просто меняем состояние

    $stmt = $db->prepare("
        UPDATE users
        SET state = ?
        WHERE telegram_id = ?
    ");

    $stmt->execute([$state, $telegramId]);
}

// Сохранить выбранного пользователя для админа. Когда админ пишет username: мы запоминаем telegram_id этого пользователя.

function updateAdminTarget($config, $telegramId, $targetTelegramId) {
    $db = getDatabase($config);

    // Проверяем, существует ли запись админа

    $user = getUser($telegramId, $config);

    // Если записи нет — создаём её

    if ($user === null) {

        $stmt = $db->prepare("
            INSERT INTO users (
                telegram_id,
                state,
                admin_target_id,
                score
            )
            VALUES (?, ?, ?, 0)
        ");

        $stmt->execute([$telegramId, 'admin_waiting_test', $targetTelegramId]);

        return;
    }

    // Если запись существует — обновляем выбранного пользователя

    $stmt = $db->prepare("
        UPDATE users
        SET admin_target_id = ?
        WHERE telegram_id = ?
    ");

    $stmt->execute([$targetTelegramId, $telegramId]);
}

//Получить выбранного пользователя админом

function getAdminTarget($config, $telegramId) {
    $db = getDatabase($config);

    $stmt = $db->prepare("
        SELECT admin_target_id
        FROM users
        WHERE telegram_id = ?
    ");

    $stmt->execute([$telegramId]);

    return $stmt->fetchColumn();
}