<?php

function getDatabase($config) // используется для подключения к бд
{
    $databasePath = $config['data_path'] . 'database.sqlite'; // путь к бд

    $pdo = new PDO('sqlite:' . $databasePath); // создание объекта для подключения к бд, выбираем sqlite и путь

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // настройка отработки ошибок

    return $pdo;
}
