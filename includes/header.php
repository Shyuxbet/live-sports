<?php
/**
 * header.php
 * $BASE_PATH: bu dosyayı include eden sayfanın site köküne göre yolu.
 *  - Kök dizindeki sayfalar (index.php, match.php, schedule.php) için ""
 *  - admin/ klasöründeki sayfalar için "../"
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!isset($BASE_PATH)) {
    $BASE_PATH = '';
}

if (!isset($settings)) {
    $settings = load_settings();
}

$siteTitle = $settings['site_title'] ?? 'Shyuxbet';
$adminLink = $BASE_PATH . 'admin/' . (is_admin_logged_in() ? 'index.php' : 'login.php');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($siteTitle) ?> | LoL Esports Canlı Skor &amp; Takvim</title>
    <link rel="icon" type="image/png" href="https://ddragon.leagueoflegends.com/cdn/14.23.1/img/profileicon/29.png">
    <link rel="stylesheet" href="<?= $BASE_PATH ?>assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="admin-gear" href="<?= h($adminLink) ?>" title="Admin Paneli" aria-label="Admin Paneli">
        <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
            <path d="M19.43 12.98c.04-.32.07-.64.07-.98 0-.34-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65A.488.488 0 0 0 14 2h-4c-.25 0-.46.18-.5.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1a.566.566 0 0 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98 0 .33.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.04.24.25.42.5.42h4c.25 0 .46-.18.5-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.22.08.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65ZM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7Z"/>
        </svg>
        <span class="admin-gear-text">Ayarlar</span>
    </a>

    <div class="header-content">
        <a class="logo" href="<?= $BASE_PATH ?>index.php">
            <img class="logo-icon" src="https://ddragon.leagueoflegends.com/cdn/14.23.1/img/profileicon/29.png" alt="League of Legends">
            <span class="logo-text">Shyux<span class="accent">bet</span></span>
        </a>

        <nav class="main-nav">
            <a href="<?= $BASE_PATH ?>index.php">Anasayfa</a>
            <a href="<?= $BASE_PATH ?>schedule.php">Takvim</a>
        </nav>

        <div class="social-links">
            <?php foreach (($settings['social_links'] ?? []) as $key => $social):
                if (empty($social['enabled'])) continue;
                $url = !empty($social['url']) ? $social['url'] : '#';
            ?>
                <a class="social-icon social-<?= h($key) ?>"
                   href="<?= h($url) ?>"
                   target="_blank" rel="noopener noreferrer"
                   title="<?= h(ucfirst($key)) ?>">
                    <?= social_icon_svg($key) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</header>
<main class="container">
