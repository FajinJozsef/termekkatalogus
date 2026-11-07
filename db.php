<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dsn = 'mysql:host=' . $_ENV['DB_HOST']
     . ';port=' . $_ENV['DB_PORT']
     . ';dbname=' . $_ENV['DB_DATABASE']
     . ';charset=utf8mb4';

try {
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo 'Nem sikerült kapcsolódni az adatbázishoz. Ellenőrizd, hogy fut-e a MySQL, és jók-e a .env beállítások.';
    exit;
}