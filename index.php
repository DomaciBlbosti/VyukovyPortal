<?php
require_once __DIR__ . '/includes/auth.php';

// Blokuj přístup pro přihlášené
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($result === true) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    } elseif ($result === 'disabled') {
        $error = 'Tento účet byl deaktivován. Kontaktuj administrátora.';
    } else {
        $error = 'Nesprávné přihlašovací jméno nebo heslo.';
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TypeMaster — Přihlášení</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=IBM+Plex+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
</head>
<body class="login-page">
<div class="login-container">
    <div class="login-logo">
        <span class="login-icon">⌨</span>
        <h1 class="login-title">TypeMaster</h1>
        <p class="login-sub">Psaní všemi deseti</p>
    </div>

    <?php if ($error): ?>
    <div class="login-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form class="login-form" method="post">
        <div class="login-field">
            <label for="username">Přihlašovací jméno</label>
            <input type="text" id="username" name="username" required autofocus
                   autocomplete="username" placeholder="username">
        </div>
        <div class="login-field">
            <label for="password">Heslo</label>
            <input type="password" id="password" name="password" required
                   autocomplete="current-password" placeholder="••••••••">
        </div>
        <button type="submit" class="login-btn">Přihlásit se →</button>
    </form>
</div>
</body>
</html>
