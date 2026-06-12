<?php
/**
 * ajax/toggle_important.php
 * Admin oturumu açıkken, schedule.php üzerindeki ★ butonuna
 * tıklandığında çağrılır. Belirtilen maç anahtarını
 * "important_matches" listesine ekler/çıkarır.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_admin_logged_in()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim. Lütfen admin girişi yapın.']);
    exit;
}

$key = trim($_POST['key'] ?? '');

if ($key === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Geçersiz istek.']);
    exit;
}

$settings = load_settings();
$important = $settings['important_matches'] ?? [];

$index = array_search($key, $important, true);
$isNowImportant = false;

if ($index !== false) {
    // Kaldır
    array_splice($important, $index, 1);
    $isNowImportant = false;
} else {
    // Ekle
    $important[] = $key;
    $isNowImportant = true;
}

$settings['important_matches'] = $important;
save_settings($settings);

echo json_encode([
    'success' => true,
    'important' => $isNowImportant,
]);
