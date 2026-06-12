<?php
/**
 * admin/login.php
 * Şifre ile admin girişi. Varsayılan şifre: 1234
 * (data/settings.json içinden admin panelinde değiştirilebilir)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$settings = load_settings();
$error = '';

if (is_admin_logged_in()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $storedPassword = $settings['admin_password'] ?? '1234';

    if ($password !== '' && $password === $storedPassword) {
        $_SESSION['is_admin'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Şifre yanlış. Lütfen tekrar deneyin.';
    }
}

$BASE_PATH = '../';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-section narrow">
    <div class="admin-login-box">
        <h2>Admin Girişi</h2>
        <p class="muted">Sadece site yöneticisi için.</p>

        <?php if ($error): ?>
            <div class="form-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="admin-form">
            <label for="password">Şifre</label>
            <input type="password" id="password" name="password" required autofocus>
            <button type="submit" class="btn btn-primary">Giriş Yap</button>
        </form>

        <p class="muted small">
            Varsayılan şifre <strong>1234</strong>'tür. Giriş yaptıktan sonra
            admin panelinden değiştirebilirsiniz.
        </p>

        <a class="back-link" href="../index.php">← Anasayfaya dön</a>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
