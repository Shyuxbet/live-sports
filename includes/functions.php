<?php
/**
 * functions.php
 * Ortak yardımcı fonksiyonlar: ayarlar, admin oturumu, ikonlar vb.
 */

require_once __DIR__ . '/config.php';

/**
 * data/settings.json dosyasını okur. Yoksa varsayılan ayarları oluşturur.
 */
function load_settings()
{
    if (!file_exists(SETTINGS_FILE)) {
        $default = default_settings();
        save_settings($default);
        return $default;
    }

    $json = @file_get_contents(SETTINGS_FILE);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        $data = default_settings();
        save_settings($data);
    }

    // Eksik alanları varsayılanlarla tamamla (güvenli güncelleme için)
    $defaults = default_settings();
    $data = array_replace_recursive($defaults, $data);

    return $data;
}

function default_settings()
{
    return [
        'admin_password' => '1234',
        'site_title' => 'Shyuxbet',
        'social_links' => [
            'discord'   => ['url' => '', 'enabled' => true],
            'facebook'  => ['url' => '', 'enabled' => true],
            'twitter'   => ['url' => '', 'enabled' => true],
            'instagram' => ['url' => '', 'enabled' => true],
            'youtube'   => ['url' => '', 'enabled' => true],
            'twitch'    => ['url' => '', 'enabled' => true],
        ],
        // önemli maç anahtarları (event_key() ile üretilir)
        'important_matches' => [],
    ];
}

/**
 * Ayarları data/settings.json dosyasına kaydeder.
 */
function save_settings($data)
{
    return @file_put_contents(
        SETTINGS_FILE,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

/**
 * Admin oturumu açık mı?
 */
function is_admin_logged_in()
{
    return !empty($_SESSION['is_admin']);
}

/**
 * Admin değilse login sayfasına yönlendirir.
 * $basePath: admin klasöründen index'e göre yol (örn: "" admin içinden çağrılırken)
 */
function require_admin($redirectUrl = 'login.php')
{
    if (!is_admin_logged_in()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * XSS koruması için kısaltma.
 */
function h($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Bazı takım istatistik alanları (örn. "dragons") API'den bir sayı
 * olarak değil, alınan objelerin/öğelerin LİSTESİ olarak gelebilir.
 * Bu fonksiyon hem sayı hem dizi durumunu güvenle "adet"e çevirir.
 */
function stat_count($value)
{
    if (is_array($value)) {
        return count($value);
    }
    if ($value === null) {
        return 0;
    }
    return $value;
}

/**
 * LoL Esports live stats API "dragons" alanını biçimlendirir.
 * Bu alan genelde [] (sayı) değil, alınan ejderhaların TÜRLERİNİ
 * içeren bir dizi olarak gelir (örn. ["infernal","mountain"]).
 * Adet + (varsa) türleri birlikte döndürür.
 */
function format_dragons($value)
{
    if (!is_array($value)) {
        return (string)($value ?? 0);
    }

    $count = count($value);
    if ($count === 0) {
        return '0';
    }

    $names = [
        'infernal' => 'Ateş',
        'ocean'    => 'Okyanus',
        'mountain' => 'Dağ',
        'cloud'    => 'Bulut',
        'hextech'  => 'Hextech',
        'chemtech' => 'Kimyatek',
        'elder'    => 'Yaşlı',
    ];

    $labels = [];
    foreach ($value as $d) {
        $type = is_array($d) ? ($d['type'] ?? $d['name'] ?? '') : $d;
        $type = strtolower((string)$type);
        $labels[] = $names[$type] ?? ($type !== '' ? ucfirst($type) : '?');
    }

    return $count . ' (' . implode(', ', $labels) . ')';
}

/**
 * Bir maç (event) için kararlı/benzersiz bir anahtar üretir.
 * "önemli maç" işaretlemesi bu anahtarla saklanır.
 */
function event_key($event)
{
    if (!empty($event['match']['id'])) {
        return 'm_' . $event['match']['id'];
    }
    $teams = $event['match']['teams'] ?? [];
    $codes = array_map(function ($t) {
        return $t['code'] ?? ($t['name'] ?? '');
    }, $teams);
    $raw = implode('-', $codes) . '_' . ($event['startTime'] ?? '');
    return 'h_' . md5($raw);
}

/**
 * Bir maç (event) için, görüntülenebilecek bir "game id" bulur.
 * Tercihen $preferState durumundaki oyunu, yoksa son oyunu döndürür.
 */
function get_event_game_id($event, $preferState = null)
{
    $games = $event['match']['games'] ?? [];
    if (empty($games)) return null;

    $hasId = function ($game) {
        return isset($game['id']) && $game['id'] !== '' && $game['id'] !== null && $game['id'] !== '0';
    };

    if ($preferState) {
        foreach ($games as $game) {
            if (($game['state'] ?? '') === $preferState && $hasId($game)) {
                return $game['id'];
            }
        }
    }

    // En son oynanan / oynanmakta olan oyunu bul
    $lastValid = null;
    foreach ($games as $game) {
        if ($hasId($game) && ($game['state'] ?? '') !== 'unstarted') {
            $lastValid = $game['id'];
        }
    }
    if ($lastValid) return $lastValid;

    foreach ($games as $game) {
        if ($hasId($game)) return $game['id'];
    }
    return null;
}

/**
 * Bir maç (event) için, getEventDetails çağrısında kullanılabilecek
 * bir kimlik döndürür (önce match.id, sonra event.id).
 */
function get_event_id($event)
{
    return $event['match']['id'] ?? $event['id'] ?? null;
}

/**
 * Bir getEventDetails (event) yanıtının "games" listesinden
 * görüntülenebilecek bir game id bulur. get_event_game_id ile aynı
 * mantığı kullanır ama "match" sarmalayıcısı olmadan, doğrudan
 * games dizisi üzerinde çalışır.
 */
function get_game_id_from_games($games, $preferState = null)
{
    return get_event_game_id(['match' => ['games' => $games]], $preferState);
}

/**
 * ISO tarihi yerel saat dilimine göre biçimlendirir.
 */
function format_datetime($iso, $format = 'd.m.Y H:i')
{
    if (!$iso) return '';
    try {
        $dt = new DateTime($iso);
        $dt->setTimezone(new DateTimeZone('Europe/Istanbul'));
        return $dt->format($format);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Sosyal medya ikonlarını (inline SVG) döndürür.
 */
function social_icon_svg($key)
{
    $icons = [
        'discord' => '<svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M20.317 4.369A19.79 19.79 0 0 0 16.558 3c-.211.375-.444.879-.608 1.276a18.27 18.27 0 0 0-3.9 0A12.6 12.6 0 0 0 11.442 3 19.74 19.74 0 0 0 7.68 4.37 20.07 20.07 0 0 0 3.677 15.6a19.9 19.9 0 0 0 6.07 3.06c.246-.336.46-.693.645-1.066a12.6 12.6 0 0 1-1.016-.49c.085-.063.169-.129.249-.196 2.952 1.36 6.146 1.36 9.067 0 .083.07.166.133.249.196-.323.19-.665.354-1.017.49.186.374.4.731.646 1.067a19.86 19.86 0 0 0 6.072-3.06 19.93 19.93 0 0 0-4.325-11.232ZM9.555 13.8c-.886 0-1.612-.81-1.612-1.81 0-.998.71-1.81 1.612-1.81.91 0 1.628.82 1.612 1.81 0 1-.71 1.81-1.612 1.81Zm4.9 0c-.886 0-1.61-.81-1.61-1.81 0-.998.71-1.81 1.61-1.81.91 0 1.629.82 1.612 1.81 0 1-.702 1.81-1.612 1.81Z"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.16 8.44 9.94v-7.03H7.9v-2.91h2.54V9.79c0-2.5 1.49-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.91h-2.34V22c4.78-.78 8.43-4.94 8.43-9.94Z"/></svg>',
        'twitter' => '<svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.45-6.231Zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77Z"/></svg>',
        'instagram' => '<svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.97.24 2.43.4.61.24 1.05.52 1.5.97.45.45.74.9.97 1.5.16.46.35 1.26.4 2.43.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.24 1.97-.4 2.43-.24.61-.52 1.05-.97 1.5-.45.45-.9.74-1.5.97-.46.16-1.26.35-2.43.4-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.97-.24-2.43-.4a4.02 4.02 0 0 1-1.5-.97 4.02 4.02 0 0 1-.97-1.5c-.16-.46-.35-1.26-.4-2.43C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.24-1.97.4-2.43.24-.61.52-1.05.97-1.5.45-.45.9-.74 1.5-.97.46-.16 1.26-.35 2.43-.4C8.42 2.17 8.8 2.16 12 2.16Zm0 1.62c-3.14 0-3.5.01-4.74.07-.96.04-1.48.21-1.82.34-.46.18-.78.39-1.12.73-.34.34-.55.66-.73 1.12-.13.34-.3.86-.34 1.82-.06 1.24-.07 1.6-.07 4.74s.01 3.5.07 4.74c.04.96.21 1.48.34 1.82.18.46.39.78.73 1.12.34.34.66.55 1.12.73.34.13.86.3 1.82.34 1.24.06 1.6.07 4.74.07s3.5-.01 4.74-.07c.96-.04 1.48-.21 1.82-.34.46-.18.78-.39 1.12-.73.34-.34.55-.66.73-1.12.13-.34.3-.86.34-1.82.06-1.24.07-1.6.07-4.74s-.01-3.5-.07-4.74c-.04-.96-.21-1.48-.34-1.82a2.4 2.4 0 0 0-.73-1.12 2.4 2.4 0 0 0-1.12-.73c-.34-.13-.86-.3-1.82-.34-1.24-.06-1.6-.07-4.74-.07Zm0 4.13a4.09 4.09 0 1 1 0 8.18 4.09 4.09 0 0 1 0-8.18Zm0 1.62a2.47 2.47 0 1 0 0 4.94 2.47 2.47 0 0 0 0-4.94Zm5.21-2.84a.98.98 0 1 1-1.96 0 .98.98 0 0 1 1.96 0Z"/></svg>',
        'youtube' => '<svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.38.55A3.02 3.02 0 0 0 .5 6.19 31.6 31.6 0 0 0 0 12a31.6 31.6 0 0 0 .5 5.81 3.02 3.02 0 0 0 2.12 2.14C4.5 20.5 12 20.5 12 20.5s7.5 0 9.38-.55a3.02 3.02 0 0 0 2.12-2.14A31.6 31.6 0 0 0 24 12a31.6 31.6 0 0 0-.5-5.81ZM9.6 15.5v-7l6.2 3.5-6.2 3.5Z"/></svg>',
        'twitch' => '<svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M4.3 2 2.2 6.7v12.6h4.5V22l3.1-2.7h2.6L18.8 14V2H4.3Zm12.4 11.1-2.6 2.6h-2.6l-2.3 2.3v-2.3H6.6V3.6h10.1v9.5ZM14.5 6.4h-1.6v4.5h1.6V6.4Zm-4.2 0H8.7v4.5h1.6V6.4Z"/></svg>',
    ];

    return $icons[$key] ?? '<svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><circle cx="12" cy="12" r="10"/></svg>';
}

/**
 * Maç durumuna göre rozet (badge) HTML üretir.
 */
function state_badge($state)
{
    switch ($state) {
        case 'inProgress':
            return '<span class="badge badge-live">CANLI</span>';
        case 'completed':
            return '<span class="badge badge-done">Bitti</span>';
        case 'unstarted':
            return '<span class="badge badge-soon">Yakında</span>';
        default:
            return '<span class="badge">' . h($state) . '</span>';
    }
}
