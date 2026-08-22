<?php
// Připojení k databázi.
// 1) Docker / TrueNAS: env proměnné DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
// 2) Klasický hosting: config/db.local.php zapsaný instalátorem (install.php)
function getDB(): PDO {
    static $pdo;
    if ($pdo) return $pdo;

    if (getenv('DB_HOST') === false && file_exists(__DIR__ . '/db.local.php')) {
        require __DIR__ . '/db.local.php';
        $pdo = getLocalDB();
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'typemaster';
    $user = getenv('DB_USER') ?: 'typemaster';
    $pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}
