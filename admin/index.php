<?php
/**
 * admin/index.php
 * Admin paneli ana sayfası:
 * - Şifre değiştirme
 * - Sosyal medya linklerini düzenleme / açma-kapama
 * - Site başlığı
 * - Önemli maçlar listesi (kaldırma)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api.php';

require_admin('login.php');

$settings = load_settings();

$message = $_SESSION['admin_message'] ?? '';
$messageType = $_SESSION['admin_message_type'] ?? 'success';
unset($_SESSION['admin_message'], $_SESSION['admin_message_type']);

$BASE_PATH = '../';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="section-head">
        <h2>Admin Paneli</h2>
        <a class="btn btn-secondary" href="logout.php">Çıkış Yap</a>
    </div>

    <?php if ($message): ?>
        <div class="form-message <?= $messageType === 'error' ? 'form-error' : 'form-success' ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="admin-grid">

        <!-- Genel Ayarlar -->
        <div class="admin-card">
            <h3>Genel Ayarlar</h3>
            <form method="post" action="save_settings.php" class="admin-form">
                <input type="hidden" name="action" value="save_general">
                <label for="site_title">Site Başlığı</label>
                <input type="text" id="site_title" name="site_title" value="<?= h($settings['site_title'] ?? 'Shyuxbet') ?>">
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </form>
        </div>

        <!-- Şifre Değiştirme -->
        <div class="admin-card">
            <h3>Admin Şifresini Değiştir</h3>
            <form method="post" action="save_settings.php" class="admin-form">
                <input type="hidden" name="action" value="change_password">
                <label for="current_password">Mevcut Şifre</label>
                <input type="password" id="current_password" name="current_password" required>

                <label for="new_password">Yeni Şifre</label>
                <input type="password" id="new_password" name="new_password" required minlength="4">

                <label for="new_password_confirm">Yeni Şifre (Tekrar)</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="4">

                <button type="submit" class="btn btn-primary">Şifreyi Güncelle</button>
            </form>
        </div>

        <!-- Sosyal Medya -->
        <div class="admin-card wide">
            <h3>Sosyal Medya Bağlantıları</h3>
            <p class="muted">Bağlantı adreslerini girin ve sitenin başlığında görünmesini istediklerinizi açık (etkin) bırakın.</p>
            <form method="post" action="save_settings.php" class="admin-form">
                <input type="hidden" name="action" value="save_social">

                <?php foreach (($settings['social_links'] ?? []) as $key => $social): ?>
                    <div class="social-form-row">
                        <span class="social-form-icon"><?= social_icon_svg($key) ?></span>
                        <label class="social-form-label"><?= h(ucfirst($key)) ?></label>
                        <input type="url" name="social_url_<?= h($key) ?>" placeholder="https://..." value="<?= h($social['url'] ?? '') ?>">
                        <label class="switch">
                            <input type="checkbox" name="social_enabled_<?= h($key) ?>" <?= !empty($social['enabled']) ? 'checked' : '' ?>>
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary">Sosyal Medyayı Kaydet</button>
            </form>
        </div>

        <!-- Önemli Maçlar -->
        <div class="admin-card wide">
            <h3>İşaretlenen Önemli Maçlar</h3>
            <p class="muted">
                Maç takviminde (<a href="../schedule.php">Takvim</a> sayfasında) her maçın
                yanındaki ★ ikonuna tıklayarak önemli maç işaretlemesi yapabilirsiniz.
                Aşağıda şu anda kayıtlı olan işaretli maç anahtarları listelenmiştir.
            </p>
            <?php if (empty($settings['important_matches'])): ?>
                <p class="muted">Henüz önemli olarak işaretlenmiş bir maç yok.</p>
            <?php else: ?>
                <ul class="important-list">
                    <?php foreach ($settings['important_matches'] as $key): ?>
                        <li><?= h($key) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
