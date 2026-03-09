<?php
// config/db.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'typing_app');
define('DB_USER', 'root');       // ← změň na svého MySQL uživatele
define('DB_PASS', '');           // ← změň na své MySQL heslo
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="color:red;padding:20px">Chyba připojení k databázi: ' . $e->getMessage() . '</div>');
        }
    }
    return $pdo;
}
