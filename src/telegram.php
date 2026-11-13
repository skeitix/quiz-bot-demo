<?php

function TG_sendMessage($chatId, $text, $keyboard, $config) {

    $url = 'https://api.telegram.org/bot' . $config['telegram_token'] . '/sendMessage';
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'html'
    ];
    if ($keyboard !== null) {
        $data['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    // Проверяем SSL-сертификат Telegram.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    // Не позволяем запросу зависнуть надолго.
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    curl_setopt($ch, CURLOPT_HEADER, false);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log('Telegram API error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('Telegram API HTTP error: ' . $httpCode);

        return false;
    }

    return $response;
}

function removeKeyboard($chatId, $text, $config)
{
    $keyboard = [
        'remove_keyboard' => true
    ];
    return TG_sendMessage($chatId, $text, $keyboard, $config);
}

