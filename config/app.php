<?php
// Pokud nebyl spuštěn instalační průvodce, přesměruj
if (!defined('INSTALLING') && !file_exists(__DIR__ . '/installed.lock')) {
    $installUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
                . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/install.php';
    header('Location: ' . $installUrl);
    exit;
}

// Výchozí hodnota — přepíše ji install.php
define('BASE_URL', '/games');
define('APP_NAME', 'TypeMaster');
define('APP_VERSION', '2.0');
