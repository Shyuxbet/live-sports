<?php
/**
 * Genel ayarlar / Global configuration
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Istanbul');

define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', BASE_DIR . '/data');
define('CACHE_DIR', DATA_DIR . '/cache');
define('SETTINGS_FILE', DATA_DIR . '/settings.json');

if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0775, true);
}

if (!is_dir(DATA_DIR)) {
    @mkdir(DATA_DIR, 0775, true);
}
