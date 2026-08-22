<?php
/**
 * Inicializace databáze pro Docker/TrueNAS.
 * - počká, až DB naběhne (max ~2 minuty)
 * - založí tabulky ze schema.sql (idempotentní — CREATE TABLE IF NOT EXISTS)
 * - když je tabulka users prázdná, založí admin účet z ADMIN_USERNAME/ADMIN_PASSWORD
 */

$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'typemaster';
$user = getenv('DB_USER') ?: 'typemaster';
$pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

$pdo = null;
for ($i = 1; $i <= 60; $i++) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        break;
    } catch (PDOException $e) {
        echo "[db] pokus $i/60: " . $e->getMessage() . "\n";
        sleep(2);
    }
}
if (!$pdo) {
    fwrite(STDERR, "[db] databáze nenaběhla\n");
    exit(1);
}

$schema = file_get_contents(__DIR__ . '/../schema.sql');
foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $schema))) as $sql) {
    if ($sql !== '' && !str_starts_with($sql, '--')) {
        $pdo->exec($sql);
    }
}
echo "[db] schéma připraveno\n";

$count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count === 0) {
    $adminUser = getenv('ADMIN_USERNAME') ?: 'admin';
    $adminPass = getenv('ADMIN_PASSWORD') ?: 'admin123';
    $adminName = getenv('ADMIN_DISPLAY_NAME') ?: $adminUser;
    $pdo->prepare('INSERT INTO users (username, password_hash, display_name, is_admin) VALUES (?,?,?,1)')
        ->execute([$adminUser, password_hash($adminPass, PASSWORD_BCRYPT), $adminName]);
    echo "[db] admin účet '$adminUser' vytvořen";
    if (getenv('ADMIN_PASSWORD') === false || getenv('ADMIN_PASSWORD') === '') {
        echo " s VÝCHOZÍM heslem 'admin123' — ZMĚŇ HO hned po přihlášení!";
    }
    echo "\n";
} else {
    echo "[db] users: $count účtů, admin se nezakládá\n";
}
