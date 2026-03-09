<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'TypeMaster') ?> — TypeMaster</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=IBM+Plex+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-brand">
        <span class="nav-logo">⌨</span>
        <span class="nav-title">TypeMaster</span>
    </div>
    <div class="nav-links">
        <a href="<?= BASE_URL ?>/dashboard.php"   class="nav-link <?= $currentPage==='dashboard'   ?'active':'' ?>">🏠 Rozcestník</a>
        <a href="<?= BASE_URL ?>/stats.php"        class="nav-link <?= $currentPage==='stats'        ?'active':'' ?>">📊 Statistiky</a>
        <a href="<?= BASE_URL ?>/leaderboard.php"  class="nav-link <?= $currentPage==='leaderboard'  ?'active':'' ?>">🏆 Žebříček</a>
        <?php if ($currentUser['is_admin']): ?>
        <a href="<?= BASE_URL ?>/admin/index.php"  class="nav-link nav-link-admin <?= str_starts_with($currentPage,'admin')||dirname($_SERVER['PHP_SELF'])==='/admin'?'active':'' ?>">⚙️ Admin</a>
        <?php endif; ?>
    </div>
    <div class="nav-user">
        <span class="nav-username">
            <?= htmlspecialchars($currentUser['display_name']) ?>
            <?php if ($currentUser['is_admin']): ?><span class="badge-admin" title="Administrátor">A</span><?php endif; ?>
        </span>
        <a href="<?= BASE_URL ?>/change-password.php" class="btn-passwd">🔑 Heslo</a>
        <a href="<?= BASE_URL ?>/logout.php" class="btn-logout">Odhlásit</a>
    </div>
</nav>
<main class="main-content">
