<?php
/**
 * schedule.php
 * Maç takvimini aylık takvim görünümünde gösterir.
 * Admin oturumu açıkken her maçın yanında ★ butonu çıkar;
 * tıklanınca o maç "önemli" olarak işaretlenir/kaldırılır ve
 * anasayfa + takvimde yeşil olarak vurgulanır.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/api.php';

$settings = load_settings();
$important = $settings['important_matches'] ?? [];

// Görüntülenecek ay/yıl
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstOfMonth = new DateTime("$year-$month-01", new DateTimeZone('Europe/Istanbul'));
$daysInMonth  = (int)$firstOfMonth->format('t');

// Haftanın ilk günü Pazartesi olacak şekilde offset hesapla (ISO-8601, 1=Pzt..7=Paz)
$startWeekday = (int)$firstOfMonth->format('N');
$leadingBlanks = $startWeekday - 1;

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Takvim verisini al ve günlere göre grupla
$schedule = LoLEsportsAPI::getSchedule();
$eventsByDay = []; // 'Y-m-d' => [event, event, ...]

if (!empty($schedule['data']['schedule']['events'])) {
    foreach ($schedule['data']['schedule']['events'] as $event) {
        if (($event['type'] ?? '') !== 'match') continue;
        if (empty($event['startTime'])) continue;

        try {
            $dt = new DateTime($event['startTime']);
            $dt->setTimezone(new DateTimeZone('Europe/Istanbul'));
        } catch (Exception $e) {
            continue;
        }

        $key = $dt->format('Y-m-d');
        $eventsByDay[$key][] = $event;
    }
}

$monthNamesTr = [1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık'];
$dayNamesTr = ['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'];

$todayKey = (new DateTime('now', new DateTimeZone('Europe/Istanbul')))->format('Y-m-d');

$BASE_PATH = '';
include __DIR__ . '/includes/header.php';
?>

<section class="page-section">
    <div class="section-head calendar-head">
        <h2>Maç Takvimi</h2>
        <div class="calendar-nav">
            <a class="cal-nav-btn" href="schedule.php?y=<?= $prevYear ?>&m=<?= $prevMonth ?>">← Önceki Ay</a>
            <span class="cal-current"><?= $monthNamesTr[$month] ?> <?= $year ?></span>
            <a class="cal-nav-btn" href="schedule.php?y=<?= $nextYear ?>&m=<?= $nextMonth ?>">Sonraki Ay →</a>
        </div>
    </div>

    <?php if (is_admin_logged_in()): ?>
        <div class="admin-note">
            Admin modu aktif: maçların yanındaki ★ ikonuna tıklayarak maçı "önemli" olarak işaretleyebilir / kaldırabilirsiniz. Önemli maçlar yeşil renkte gösterilir.
        </div>
    <?php endif; ?>

    <div class="calendar-grid">
        <?php foreach ($dayNamesTr as $dn): ?>
            <div class="calendar-dayname"><?= $dn ?></div>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < $leadingBlanks; $i++): ?>
            <div class="calendar-cell empty"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dayEvents = $eventsByDay[$dateKey] ?? [];
            $isToday = ($dateKey === $todayKey);
        ?>
            <div class="calendar-cell <?= $isToday ? 'today' : '' ?> <?= empty($dayEvents) ? '' : 'has-events' ?>">
                <div class="calendar-date"><?= $day ?></div>
                <div class="calendar-events">
                    <?php foreach ($dayEvents as $event):
                        $match  = $event['match'] ?? [];
                        $teams  = $match['teams'] ?? [];
                        $teamA  = $teams[0] ?? [];
                        $teamB  = $teams[1] ?? [];
                        $league = $event['league'] ?? [];
                        $state  = $event['state'] ?? '';
                        $gameId = get_event_game_id($event, $state === 'inProgress' ? 'inProgress' : 'completed');
                        $key    = event_key($event);
                        $isImportant = in_array($key, $important);
                        $time = format_datetime($event['startTime'] ?? '', 'H:i');
                    ?>
                        <div class="cal-event <?= $isImportant ? 'important' : '' ?> state-<?= h($state) ?>">
                            <?php if (is_admin_logged_in()): ?>
                                <button type="button"
                                        class="star-toggle <?= $isImportant ? 'active' : '' ?>"
                                        data-key="<?= h($key) ?>"
                                        title="Önemli olarak işaretle / kaldır">★</button>
                            <?php endif; ?>

                            <span class="cal-event-time"><?= h($time) ?></span>

                            <?php if ($state !== 'unstarted' && (!empty($gameId) || !empty(get_event_id($event)))): ?>
                                <a class="cal-event-teams"
                                   href="match.php?gameId=<?= h($gameId) ?>&eventId=<?= h(get_event_id($event)) ?>&blue=<?= h($teamA['code'] ?? $teamA['name'] ?? '') ?>&red=<?= h($teamB['code'] ?? $teamB['name'] ?? '') ?>&league=<?= h($league['name'] ?? '') ?>">
                                    <?= h($teamA['code'] ?? $teamA['name'] ?? '?') ?>
                                    <span class="cal-vs">vs</span>
                                    <?= h($teamB['code'] ?? $teamB['name'] ?? '?') ?>
                                </a>
                            <?php else: ?>
                                <span class="cal-event-teams">
                                    <?= h($teamA['code'] ?? $teamA['name'] ?? '?') ?>
                                    <span class="cal-vs">vs</span>
                                    <?= h($teamB['code'] ?? $teamB['name'] ?? '?') ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($state === 'inProgress'): ?>
                                <span class="badge badge-live small">CANLI</span>
                            <?php endif; ?>

                            <div class="cal-event-league"><?= h($league['name'] ?? '') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <p class="empty-note small">
        Not: Riot LoL Esports API genellikle yaklaşık ±2 haftalık takvim verisi sağlar.
        Görünen aydaki boş günler için veri kaynağında henüz maç bulunmuyor olabilir.
    </p>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
