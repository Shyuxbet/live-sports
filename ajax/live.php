<?php
/**
 * ajax/live.php
 * index.php sayfasındaki "Canlı Maçlar" bölümünü periyodik olarak
 * yenilemek için kullanılan JSON/HTML endpoint.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api.php';

header('Content-Type: application/json; charset=utf-8');

$settings = load_settings();
$schedule = LoLEsportsAPI::getSchedule();

$liveEvents = [];
if (!empty($schedule['data']['schedule']['events'])) {
    foreach ($schedule['data']['schedule']['events'] as $event) {
        if (($event['type'] ?? '') !== 'match') continue;
        if (($event['state'] ?? '') === 'inProgress') {
            $liveEvents[] = $event;
        }
    }
}

$live = LoLEsportsAPI::getLive();
$liveFromGetLive = $live['data']['schedule']['events'] ?? null;
if (is_array($liveFromGetLive) && !empty($liveFromGetLive)) {
    $liveEvents = array_filter($liveFromGetLive, function ($event) {
        return ($event['type'] ?? '') === 'match';
    });
} elseif (is_array($liveFromGetLive)) {
    // getLive() yanıt verdi ama aktif maç yok -> liste boş
    $liveEvents = [];
}

ob_start();
include __DIR__ . '/../includes/partial_live.php';
$html = ob_get_clean();

echo json_encode([
    'count' => count($liveEvents),
    'html'  => $html,
]);
