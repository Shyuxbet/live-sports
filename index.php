<?php
/**
 * index.php
 * Canlı maçları ve son oynanan maçları listeler.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/api.php';

$settings = load_settings();
$schedule = LoLEsportsAPI::getSchedule();

$liveEvents = [];
$upcomingEvents = [];
$completedEvents = [];

if (!empty($schedule['data']['schedule']['events'])) {
    foreach ($schedule['data']['schedule']['events'] as $event) {
        if (($event['type'] ?? '') !== 'match') continue;

        $state = $event['state'] ?? '';
        if ($state === 'inProgress') {
            $liveEvents[] = $event;
        } elseif ($state === 'completed') {
            $completedEvents[] = $event;
        } elseif ($state === 'unstarted') {
            $upcomingEvents[] = $event;
        }
    }
}

// getLive(), şu anda CANLI olan maçlar için daha güvenilir bir kaynaktır
// (şema: "null if no match is taking place"). Varsa, canlı listesini
// bununla değiştiriyoruz; boş/null ise getSchedule'dan bulduğumuz
// inProgress maçlarla devam ediyoruz.
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

// En son tamamlanan maçlar üstte görünsün
$completedEvents = array_reverse($completedEvents);
$completedEvents = array_slice($completedEvents, 0, 16);

// Sıradaki birkaç maç
$upcomingEvents = array_slice($upcomingEvents, 0, 8);

$BASE_PATH = '';
include __DIR__ . '/includes/header.php';
?>

<section class="page-section">
    <div class="section-head">
        <h2><span class="live-dot"></span> Canlı Maçlar</h2>
        <span class="auto-refresh-note">Bu bölüm otomatik olarak güncellenir</span>
    </div>

    <div id="live-matches" class="match-grid">
        <?php include __DIR__ . '/includes/partial_live.php'; ?>
    </div>
</section>

<?php if (!empty($upcomingEvents)): ?>
<section class="page-section">
    <div class="section-head">
        <h2>Sıradaki Maçlar</h2>
        <a class="see-all" href="schedule.php">Tüm takvim →</a>
    </div>
    <div class="match-grid">
        <?php foreach ($upcomingEvents as $event):
            $match  = $event['match'] ?? [];
            $teams  = $match['teams'] ?? [];
            $league = $event['league'] ?? [];
            $teamA  = $teams[0] ?? [];
            $teamB  = $teams[1] ?? [];
            $important = in_array(event_key($event), $settings['important_matches'] ?? []);
        ?>
            <div class="match-card upcoming <?= $important ? 'important' : '' ?>">
                <div class="match-card-league">
                    <?php if (!empty($league['image'])): ?>
                        <img src="<?= h($league['image']) ?>" alt="" class="league-icon">
                    <?php endif; ?>
                    <span><?= h($league['name'] ?? '') ?></span>
                    <?php if ($important): ?><span class="badge badge-important">★ Önemli</span><?php endif; ?>
                </div>
                <div class="match-card-teams">
                    <div class="team">
                        <?php if (!empty($teamA['image'])): ?><img src="<?= h($teamA['image']) ?>" alt=""><?php endif; ?>
                        <span class="team-code"><?= h($teamA['code'] ?? $teamA['name'] ?? '?') ?></span>
                    </div>
                    <div class="vs"><?= h(format_datetime($event['startTime'] ?? '', 'd.m H:i')) ?></div>
                    <div class="team">
                        <span class="team-code"><?= h($teamB['code'] ?? $teamB['name'] ?? '?') ?></span>
                        <?php if (!empty($teamB['image'])): ?><img src="<?= h($teamB['image']) ?>" alt=""><?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="page-section">
    <div class="section-head">
        <h2>Son Oynanan Maçlar</h2>
        <a class="see-all" href="schedule.php">Tüm takvim →</a>
    </div>

    <div class="match-grid">
        <?php if (empty($completedEvents)): ?>
            <p class="empty-note">Henüz tamamlanmış maç verisi yok.</p>
        <?php else: ?>
            <?php foreach ($completedEvents as $event):
                $match  = $event['match'] ?? [];
                $teams  = $match['teams'] ?? [];
                $league = $event['league'] ?? [];
                $gameId = get_event_game_id($event, 'completed');
                $teamA  = $teams[0] ?? [];
                $teamB  = $teams[1] ?? [];
                $winnerA = ($teamA['result']['outcome'] ?? '') === 'win';
                $winnerB = ($teamB['result']['outcome'] ?? '') === 'win';
            ?>
                <a class="match-card done"
                   href="match.php?gameId=<?= h($gameId) ?>&eventId=<?= h(get_event_id($event)) ?>&blue=<?= h($teamA['code'] ?? $teamA['name'] ?? '') ?>&red=<?= h($teamB['code'] ?? $teamB['name'] ?? '') ?>&league=<?= h($league['name'] ?? '') ?>">
                    <div class="match-card-league">
                        <?php if (!empty($league['image'])): ?>
                            <img src="<?= h($league['image']) ?>" alt="" class="league-icon">
                        <?php endif; ?>
                        <span><?= h($league['name'] ?? '') ?></span>
                        <span class="match-date"><?= h(format_datetime($event['startTime'] ?? '', 'd.m.Y H:i')) ?></span>
                    </div>
                    <div class="match-card-teams">
                        <div class="team <?= $winnerA ? 'winner' : '' ?>">
                            <?php if (!empty($teamA['image'])): ?><img src="<?= h($teamA['image']) ?>" alt=""><?php endif; ?>
                            <span class="team-code"><?= h($teamA['code'] ?? $teamA['name'] ?? '?') ?></span>
                            <span class="team-score"><?= h($teamA['result']['gameWins'] ?? 0) ?></span>
                        </div>
                        <div class="vs">-</div>
                        <div class="team <?= $winnerB ? 'winner' : '' ?>">
                            <span class="team-score"><?= h($teamB['result']['gameWins'] ?? 0) ?></span>
                            <span class="team-code"><?= h($teamB['code'] ?? $teamB['name'] ?? '?') ?></span>
                            <?php if (!empty($teamB['image'])): ?><img src="<?= h($teamB['image']) ?>" alt=""><?php endif; ?>
                        </div>
                    </div>
                    <div class="match-card-foot">Maç istatistiklerini gör →</div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
