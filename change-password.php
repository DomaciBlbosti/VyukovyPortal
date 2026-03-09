<?php
// change-password.php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/config/db.php';

$user    = getCurrentUser();
$db      = getDB();
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']       ?? '';
    $confirm  = $_POST['confirm_password']   ?? '';

    // Ověř aktuální heslo
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!password_verify($current, $row['password_hash'])) {
        $error = 'Aktuální heslo není správné.';
    } elseif (mb_strlen($new) < 6) {
        $error = 'Nové heslo musí mít alespoň 6 znaků.';
    } elseif ($new !== $confirm) {
        $error = 'Nová hesla se neshodují.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);
        $success = 'Heslo bylo úspěšně změněno.';
    }
}

$pageTitle = 'Změna hesla';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>🔑 Změna <span class="accent">hesla</span></h1>
    <p class="page-subtitle"><?= htmlspecialchars($user['display_name']) ?></p>
</div>

<div class="form-card">
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="current_password">Aktuální heslo</label>
            <input type="password" id="current_password" name="current_password"
                   autocomplete="current-password" placeholder="zadej aktuální heslo...">
        </div>
        <div class="form-group">
            <label for="new_password">Nové heslo</label>
            <input type="password" id="new_password" name="new_password"
                   autocomplete="new-password" placeholder="minimálně 6 znaků...">
        </div>
        <div class="form-group">
            <label for="confirm_password">Potvrzení nového hesla</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   autocomplete="new-password" placeholder="zopakuj nové heslo...">
        </div>
        <div style="display:flex; gap:1rem; margin-top:1.5rem">
            <button type="submit" class="btn-primary">Uložit heslo</button>
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn-secondary">Zrušit</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
