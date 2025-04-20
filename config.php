<?php
// Файл конфигурации для подключения к базе данных

// Параметры подключения
$host = 'localhost';
$dbname = 'online_education_platform';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

// Настройка DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// Опции для PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Выбрасывать исключения при ошибках
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Возвращать ассоциативные массивы
    PDO::ATTR_EMULATE_PREPARES   => false,                    // Использовать нативные подготовленные запросы
];

// Создание подключения
try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // В реальном проекте стоит использовать более безопасный способ обработки ошибок
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}
?> 