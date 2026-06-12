<?php
/**
 * admin/save_settings.php
 * Admin panelindeki formlardan gelen POST verilerini işler:
 * - Şifre değiştirme
 * - Sosyal medya linkleri / açma-kapama
 * - Site başlığı
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin('login.php');

$settings = load_settings();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new1 = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password_confirm'] ?? '';
        $storedPassword = $settings['admin_password'] ?? '1234';

        if ($current !== $storedPassword) {
            $message = 'Mevcut şifre yanlış.';
            $messageType = 'error';
        } elseif ($new1 === '' || strlen($new1) < 4) {
            $message = 'Yeni şifre en az 4 karakter olmalıdır.';
            $messageType = 'error';
        } elseif ($new1 !== $new2) {
            $message = 'Yeni şifreler birbiriyle eşleşmiyor.';
            $messageType = 'error';
        } else {
            $settings['admin_password'] = $new1;
            save_settings($settings);
            $message = 'Şifre başarıyla güncellendi.';
        }
    } elseif ($action === 'save_social') {
        $platforms = array_keys($settings['social_links'] ?? default_settings()['social_links']);
        foreach ($platforms as $platform) {
            $url = trim($_POST['social_url_' . $platform] ?? '');
            $enabled = isset($_POST['social_enabled_' . $platform]);
            $settings['social_links'][$platform] = [
                'url' => $url,
                'enabled' => $enabled,
            ];
        }
        save_settings($settings);
        $message = 'Sosyal medya ayarları kaydedildi.';
    } elseif ($action === 'save_general') {
        $title = trim($_POST['site_title'] ?? '');
        if ($title !== '') {
            $settings['site_title'] = $title;
        }
        save_settings($settings);
        $message = 'Genel ayarlar kaydedildi.';
    }
}

$_SESSION['admin_message'] = $message;
$_SESSION['admin_message_type'] = $messageType;

header('Location: index.php');
exit;
