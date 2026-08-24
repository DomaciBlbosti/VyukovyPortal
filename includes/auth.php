<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/app.php';

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return !empty($_SESSION['is_admin']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function getCurrentUser(): array {
    return [
        'id'           => $_SESSION['user_id']      ?? 0,
        'username'     => $_SESSION['username']     ?? '',
        'display_name' => $_SESSION['display_name'] ?? '',
        'is_admin'     => $_SESSION['is_admin']     ?? false,
        'grade'        => $_SESSION['grade']        ?? 0,
    ];
}

/**
 * Ročník přihlášeného žáka (1–9, 0 = neuvedeno).
 * Čte se ze session; po změně v adminu se projeví po dalším přihlášení,
 * proto se u vlastního profilu bere rovnou z databáze.
 */
function getUserGrade(): int {
    return (int)($_SESSION['grade'] ?? 0);
}

function login(string $username, string $password): bool|string {
    require_once __DIR__ . '/../config/db.php';
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, username, password_hash, display_name, is_admin, is_active, grade FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) return false;
    if (!$user['is_active']) return 'disabled';
    if (!password_verify($password, $user['password_hash'])) return false;

    $_SESSION['user_id']      = $user['id'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['display_name'] = $user['display_name'];
    $_SESSION['is_admin']     = (bool)$user['is_admin'];
    $_SESSION['grade']        = (int)($user['grade'] ?? 0);

    $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
    return true;
}

function logout(): void {
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function saveGameSession(array $data): int {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/levels.php';
    $db   = getDB();
    $user = getCurrentUser();
    $stmt = $db->prepare('
        INSERT INTO game_sessions (user_id, game_type, wpm, accuracy, duration_seconds, chars_typed, errors, text_snippet, points)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $user['id'],
        $data['game_type']        ?? 'classic',
        $data['wpm']              ?? 0,
        $data['accuracy']         ?? 0,
        $data['duration_seconds'] ?? 0,
        $data['chars_typed']      ?? 0,
        $data['errors']           ?? 0,
        $data['text_snippet']     ?? '',
        calculatePoints($data),
    ]);
    return (int)$db->lastInsertId();
}

/**
 * Uloží výsledek hry a vrátí odpověď pro frontend — včetně získaných bodů
 * a informace, jestli hráč postoupil na nový level.
 */
function saveGameResult(array $data): array {
    require_once __DIR__ . '/levels.php';
    require_once __DIR__ . '/achievements.php';
    require_once __DIR__ . '/mistakes.php';
    $user   = getCurrentUser();
    $before = getUserLevel($user['id']);
    $id     = saveGameSession($data);
    $after  = getUserLevel($user['id']);

    // Chybovník: hry, které umí označit jednotlivé úlohy, pošlou jejich
    // výsledky v 'answers'. Ostatní (psaní, zeměpis) klíč vůbec nepošlou.
    if (!empty($data['answers'])) {
        recordAnswers((int)$user['id'], $data['game_type'] ?? '', $data['answers'],
                      (string)($data['topic'] ?? ''), (string)($data['topic_label'] ?? ''));
    }

    return [
        'ok'           => true,
        'id'           => $id,
        'points'       => calculatePoints($data),
        'level'        => $after,
        'levelup'      => $after['level'] > $before['level'],
        'achievements' => checkAchievements((int)$user['id'], $data),
    ];
}
