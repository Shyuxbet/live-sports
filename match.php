<?php
/**
 * match.php
 * Tek bir oyunun (game) detaylı istatistiklerini gösterir:
 * - Takım skorları, altın farkı
 * - Baron / Ejder / Kule / Engel sayıları
 * - Şampiyon seçimleri (picks)
 * - Oyuncu bazlı KDA, CS, hasar, item ve rünler
 *
 * Not: LoL Esports public API üzerinden "ban" (yasaklanan şampiyon)
 * verisi sağlanmamaktadır; bu yüzden bans bölümü bilgilendirme
 * mesajı gösterir.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/api.php';

$settings = load_settings();

$gameId   = $_GET['gameId'] ?? '';
$eventId  = $_GET['eventId'] ?? '';
$blueName = $_GET['blue'] ?? 'Mavi Takım';
$redName  = $_GET['red'] ?? 'Kırmızı Takım';
$leagueName = $_GET['league'] ?? '';

$BASE_PATH = '';

$eventDetailsForDebug = null;

// gameId boşsa, eventId üzerinden getEventDetails ile bulmayı dene
if (!$gameId && $eventId) {
    $eventDetailsResponse = LoLEsportsAPI::getEventDetails($eventId);
    $eventDetailsForDebug = $eventDetailsResponse;
    $eventData = $eventDetailsResponse['data']['event'] ?? null;

    if ($eventData) {
        $games = $eventData['match']['games'] ?? [];
        $state = $eventData['state'] ?? '';

        $gameId = get_game_id_from_games($games, $state === 'inProgress' ? 'inProgress' : 'completed');

        // İsim/lig bilgisi URL'den gelmediyse buradan tamamla
        $teams = $eventData['match']['teams'] ?? [];
        if (empty($_GET['blue']) && !empty($teams[0])) {
            $blueName = $teams[0]['code'] ?? ($teams[0]['name'] ?? $blueName);
        }
        if (empty($_GET['red']) && !empty($teams[1])) {
            $redName = $teams[1]['code'] ?? ($teams[1]['name'] ?? $redName);
        }
        if (empty($_GET['league']) && !empty($eventData['league']['name'])) {
            $leagueName = $eventData['league']['name'];
        }
    }
}

if (!$gameId) {
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="page-section">
        <div class="empty-note">
            Bu maç için geçerli bir oyun (game) kimliği bulunamadı.
            Bu durum, maçın henüz başlamamış olmasından veya veri
            sağlayıcının bu maç için henüz bir oyun kimliği yayınlamamış
            olmasından kaynaklanabilir.
            <br><br>
            <a href="index.php">← Anasayfaya dön</a> &nbsp;|&nbsp;
            <a href="schedule.php">Takvime git</a>
        </div>

        <?php if (is_admin_logged_in()): ?>
            <div class="empty-note small" style="margin-top:14px;">
                <strong>Admin hata ayıklama bilgisi</strong><br>
                gameId: <code><?= h($_GET['gameId'] ?? '(boş)') ?></code><br>
                eventId: <code><?= h($eventId ?: '(boş)') ?></code><br>
                <?php if ($eventDetailsForDebug !== null): ?>
                    getEventDetails yanıtı (ilk 2000 karakter):
                    <pre style="white-space:pre-wrap; overflow-x:auto;"><?= h(substr(json_encode($eventDetailsForDebug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0, 2000)) ?></pre>
                <?php elseif ($eventId): ?>
                    getEventDetails çağrısı boş/null sonuç döndürdü (API'den yanıt alınamadı olabilir).
                <?php else: ?>
                    URL'de eventId bulunmadığı için getEventDetails denenmedi.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$window  = LoLEsportsAPI::getWindow($gameId);
$details = LoLEsportsAPI::getDetails($gameId);

$gameMeta = $window['gameMetadata'] ?? null;
$frames   = $window['frames'] ?? [];
$lastFrame = !empty($frames) ? $frames[count($frames) - 1] : null;

$detailFrames    = $details['frames'] ?? [];
$lastDetailFrame = !empty($detailFrames) ? $detailFrames[count($detailFrames) - 1] : null;

$dataAvailable = $lastFrame !== null;

$gameState  = $lastFrame['gameState'] ?? null; // "in_game" | "paused" | "finished" | null
$frameCount = count($frames);

$patch = $gameMeta['patchVersion'] ?? LoLEsportsAPI::getLatestPatch();
$runeIconMap = LoLEsportsAPI::getRuneIconMap($patch);

/**
 * Oyuncuları topla: gameMetadata + son window frame + son details frame
 * participantId 1-5 mavi takım, 6-10 kırmızı takım.
 */
$players = [];

if ($gameMeta) {
    foreach (($gameMeta['blueTeamMetadata']['participantMetadata'] ?? []) as $p) {
        $players[$p['participantId']] = [
            'team'         => 'blue',
            'championId'   => $p['championId'] ?? '',
            'summonerName' => $p['summonerName'] ?? '',
            'role'         => $p['role'] ?? '',
        ];
    }
    foreach (($gameMeta['redTeamMetadata']['participantMetadata'] ?? []) as $p) {
        $players[$p['participantId']] = [
            'team'         => 'red',
            'championId'   => $p['championId'] ?? '',
            'summonerName' => $p['summonerName'] ?? '',
            'role'         => $p['role'] ?? '',
        ];
    }
}

if ($lastFrame) {
    foreach (['blueTeam', 'redTeam'] as $side) {
        foreach (($lastFrame[$side]['participants'] ?? []) as $p) {
            $pid = $p['participantId'] ?? null;
            if ($pid === null) continue;
            if (!isset($players[$pid])) $players[$pid] = [];
            $players[$pid]['gold']     = $p['totalGold'] ?? 0;
            $players[$pid]['level']    = $p['level'] ?? 0;
            $players[$pid]['kills']    = $p['kills'] ?? 0;
            $players[$pid]['deaths']   = $p['deaths'] ?? 0;
            $players[$pid]['assists']  = $p['assists'] ?? 0;
            $players[$pid]['goldDiff'] = $p['totalGoldDiff'] ?? null;
        }
    }
}

if ($lastDetailFrame) {
    foreach (($lastDetailFrame['participants'] ?? []) as $p) {
        $pid = $p['participantId'] ?? null;
        if ($pid === null) continue;
        if (!isset($players[$pid])) $players[$pid] = [];
        $players[$pid]['items']    = $p['items'] ?? [];
        $players[$pid]['cs']       = $p['creepScore'] ?? ($players[$pid]['cs'] ?? 0);

        // API'de mutlak hasar değeri "totalDamageDealtToChampions" olarak
        // gelebilir; bazı yanıtlarda bunun yerine sadece takım hasarına
        // oranı ("championDamageShare", 0-1 aralığında) bulunur.
        if (isset($p['totalDamageDealtToChampions'])) {
            $players[$pid]['damage'] = $p['totalDamageDealtToChampions'];
            $players[$pid]['damageIsShare'] = false;
        } elseif (isset($p['championDamageShare'])) {
            $players[$pid]['damage'] = $p['championDamageShare'];
            $players[$pid]['damageIsShare'] = true;
        } else {
            $players[$pid]['damage'] = 0;
            $players[$pid]['damageIsShare'] = false;
        }

        $players[$pid]['killParticipation'] = $p['killParticipation'] ?? null;
        $players[$pid]['wardsPlaced']   = $p['wardsPlaced'] ?? null;
        $players[$pid]['wardsDestroyed'] = $p['wardsDestroyed'] ?? null;
        $players[$pid]['perks']    = $p['perkMetadata']['perks'] ?? [];
        $players[$pid]['styleId']  = $p['perkMetadata']['styleId'] ?? null;
        $players[$pid]['subStyleId'] = $p['perkMetadata']['subStyleId'] ?? null;
    }
}

ksort($players);

$bluePlayers = array_filter($players, fn($p) => ($p['team'] ?? '') === 'blue');
$redPlayers  = array_filter($players, fn($p) => ($p['team'] ?? '') === 'red');

// Takım toplamları
$blueTeamStats = $lastFrame['blueTeam'] ?? [];
$redTeamStats  = $lastFrame['redTeam'] ?? [];

$blueGold = $blueTeamStats['totalGold'] ?? 0;
$redGold  = $redTeamStats['totalGold'] ?? 0;
$goldDiff = $blueGold - $redGold;

// Oyun henüz başlamamış / veri akışı henüz oluşmamışsa (örn. şampiyon
// seçimi bitti ama maç henüz yüklenmiyor) tüm sayısal değerler 0 görünür.
// Bu durumu kullanıcıya açıklamak için bir bayrak tutuyoruz.
$isPreGame = $dataAvailable
    && $gameState !== 'finished'
    && (int)$blueGold === 0
    && (int)$redGold === 0;

// Maç hâlâ canlıysa (bitmediyse) sayfayı periyodik olarak otomatik yenile.
$shouldAutoRefresh = $gameState !== 'finished';

include __DIR__ . '/includes/header.php';
?>

<?php if ($shouldAutoRefresh): ?>
<script>
    // Bu maç bitmediği sürece sayfayı 20 saniyede bir otomatik yeniler.
    setTimeout(function () { window.location.reload(); }, 20000);
</script>
<?php endif; ?>

<section class="page-section">
    <div class="match-header">
        <div class="match-header-team">
            <span class="team-name"><?= h($blueName) ?></span>
            <span class="team-side blue">MAVİ TAKIM</span>
        </div>
        <div class="match-header-center">
            <div class="match-header-league"><?= h($leagueName) ?></div>
            <div class="match-header-gold">
                <?= number_format($blueGold) ?>
                <span class="gold-diff <?= $goldDiff >= 0 ? 'pos' : 'neg' ?>">
                    (<?= $goldDiff >= 0 ? '+' : '' ?><?= number_format($goldDiff) ?>)
                </span>
                <?= number_format($redGold) ?>
            </div>
            <div class="match-header-sub">Toplam Altın</div>
        </div>
        <div class="match-header-team right">
            <span class="team-name"><?= h($redName) ?></span>
            <span class="team-side red">KIRMIZI TAKIM</span>
        </div>
    </div>

    <?php if (!$dataAvailable): ?>
        <div class="empty-note">
            Bu maç için canlı istatistik verisi henüz mevcut değil. Maç başlamadıysa
            veya veri sağlayıcı tarafında gecikme varsa bu durum normaldir. Lütfen
            sayfayı daha sonra tekrar yükleyin.

            <?php if (is_admin_logged_in()): ?>
                <br><br>
                <strong>Admin hata ayıklama bilgisi</strong><br>
                gameId: <code><?= h($gameId) ?></code><br>
                window null mu: <code><?= $window === null ? 'evet (istek başarısız)' : 'hayır' ?></code><br>
                gameMetadata var mı: <code><?= $gameMeta ? 'evet' : 'hayır' ?></code><br>
                frame sayısı: <code><?= h($frameCount) ?></code><br>
                details null mu: <code><?= $details === null ? 'evet (istek başarısız)' : 'hayır' ?></code><br>
                <br>
                Son API istekleri:
                <pre style="white-space:pre-wrap; overflow-x:auto; font-size:11px;"><?php foreach (LoLEsportsAPI::$debugLog as $entry): ?>
URL: <?= h($entry['url']) ?>
HTTP: <?= h($entry['httpCode']) ?> | cURL hatası: <?= h($entry['error'] ?: '-') ?>
Yanıt (ilk 500 karakter): <?= h($entry['bodySnippet'] ?? '(yok)') ?>

<?php endforeach; ?></pre>
            <?php endif; ?>
        </div>
    <?php elseif ($isPreGame): ?>
        <div class="empty-note">
            Şampiyon seçimi tamamlanmış görünüyor ancak maçın canlı
            istatistik verisi (altın, hasar, CS vb.) henüz akmaya başlamadı —
            bu genellikle yükleme ekranı / ilk saniyeler sırasında normaldir.
            Bu sayfa <strong>20 saniyede bir otomatik olarak yenilenecek</strong>.
            <?php if (is_admin_logged_in()): ?>
                <br><br>
                <span class="muted small">
                    Admin bilgisi — gameId: <code><?= h($gameId) ?></code>,
                    gameState: <code><?= h($gameState ?? 'null') ?></code>,
                    frame sayısı: <code><?= h($frameCount) ?></code>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($dataAvailable): ?>

    <!-- Takım istatistikleri: Baron / Ejder / Kule / Engel / Kill -->
    <div class="team-stats-grid">
        <div class="team-stats-col">
            <h3 class="team-stats-title blue"><?= h($blueName) ?></h3>
            <ul class="team-stats-list">
                <li><span>Toplam Kill</span><strong><?= h(stat_count($blueTeamStats['totalKills'] ?? 0)) ?></strong></li>
                <li><span>🐲 Ejderha</span><strong><?= h(format_dragons($blueTeamStats['dragons'] ?? 0)) ?></strong></li>
                <li><span>🐗 Baron Nashor</span><strong><?= h(stat_count($blueTeamStats['barons'] ?? 0)) ?></strong></li>
                <li><span>🗼 Kule</span><strong><?= h(stat_count($blueTeamStats['towers'] ?? 0)) ?></strong></li>
                <li><span>🛡️ Engel (Inhibitor)</span><strong><?= h(stat_count($blueTeamStats['inhibitors'] ?? 0)) ?></strong></li>
            </ul>
        </div>
        <div class="team-stats-col right">
            <h3 class="team-stats-title red"><?= h($redName) ?></h3>
            <ul class="team-stats-list">
                <li><span>Toplam Kill</span><strong><?= h(stat_count($redTeamStats['totalKills'] ?? 0)) ?></strong></li>
                <li><span>🐲 Ejderha</span><strong><?= h(format_dragons($redTeamStats['dragons'] ?? 0)) ?></strong></li>
                <li><span>🐗 Baron Nashor</span><strong><?= h(stat_count($redTeamStats['barons'] ?? 0)) ?></strong></li>
                <li><span>🗼 Kule</span><strong><?= h(stat_count($redTeamStats['towers'] ?? 0)) ?></strong></li>
                <li><span>🛡️ Engel (Inhibitor)</span><strong><?= h(stat_count($redTeamStats['inhibitors'] ?? 0)) ?></strong></li>
            </ul>
        </div>
    </div>

    <!-- Yasaklı şampiyonlar -->
    <div class="bans-note">
        <strong>Yasaklı (ban) şampiyonlar:</strong>
        LoL Esports genel API'si draft/ban verisini sunmamaktadır, bu nedenle
        bu bölüm gösterilemiyor. Sadece seçilen (pick) şampiyonlar aşağıda yer almaktadır.
    </div>

    <!-- Oyuncu tabloları -->
    <?php
    $renderTeamTable = function ($teamPlayers, $teamName, $sideClass, $patch, $runeIconMap) {
        ?>
        <div class="player-table-wrap">
            <h3 class="player-table-title <?= h($sideClass) ?>"><?= h($teamName) ?> - Oyuncu İstatistikleri</h3>
            <div class="table-scroll">
            <table class="player-table">
                <thead>
                    <tr>
                        <th>Şampiyon</th>
                        <th>Oyuncu</th>
                        <th>Rün</th>
                        <th>K/D/A</th>
                        <th>KK%</th>
                        <th>CS</th>
                        <th>Altın</th>
                        <th>Hasar</th>
                        <th>Ward (V/Y)</th>
                        <th>Itemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($teamPlayers as $p): ?>
                    <tr>
                        <td class="champ-cell">
                            <?php $champIcon = LoLEsportsAPI::championIconUrl($p['championId'] ?? '', $patch); ?>
                            <?php if ($champIcon): ?>
                                <img class="champ-icon" src="<?= h($champIcon) ?>" alt="<?= h($p['championId'] ?? '') ?>" title="<?= h($p['championId'] ?? '') ?>">
                            <?php endif; ?>
                            <span class="champ-name"><?= h($p['championId'] ?? '?') ?></span>
                        </td>
                        <td>
                            <div class="player-name"><?= h($p['summonerName'] ?? '?') ?></div>
                            <div class="player-role"><?= h(strtoupper($p['role'] ?? '')) ?></div>
                        </td>
                        <td>
                            <div class="rune-icons">
                                <?php
                                $perks = $p['perks'] ?? [];
                                $keystone = $perks[0] ?? null;
                                $subStyle = $p['subStyleId'] ?? null;
                                $keystoneUrl = LoLEsportsAPI::runeIconUrl($keystone, $runeIconMap);
                                $subStyleUrl = LoLEsportsAPI::runeIconUrl($subStyle, $runeIconMap);
                                ?>
                                <?php if ($keystoneUrl): ?>
                                    <img class="rune-icon main" src="<?= h($keystoneUrl) ?>" alt="Keystone">
                                <?php endif; ?>
                                <?php if ($subStyleUrl): ?>
                                    <img class="rune-icon sub" src="<?= h($subStyleUrl) ?>" alt="Secondary Tree">
                                <?php endif; ?>
                                <?php if (!$keystoneUrl && !$subStyleUrl): ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="kda-cell">
                            <?= h($p['kills'] ?? 0) ?>/<?= h($p['deaths'] ?? 0) ?>/<?= h($p['assists'] ?? 0) ?>
                        </td>
                        <td>
                            <?php if (isset($p['killParticipation'])): ?>
                                <?= h(round($p['killParticipation'] * 100)) ?>%
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= h($p['cs'] ?? '-') ?></td>
                        <td><?= isset($p['gold']) ? number_format($p['gold']) : '-' ?></td>
                        <td>
                            <?php if (!isset($p['damage'])): ?>
                                -
                            <?php elseif (!empty($p['damageIsShare'])): ?>
                                <?= h(round($p['damage'] * 100)) ?>%
                                <span class="muted small">(takım payı)</span>
                            <?php else: ?>
                                <?= number_format($p['damage']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($p['wardsPlaced']) || isset($p['wardsDestroyed'])): ?>
                                <?= h($p['wardsPlaced'] ?? 0) ?> / <?= h($p['wardsDestroyed'] ?? 0) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="item-icons">
                                <?php
                                $items = $p['items'] ?? [];
                                if (empty($items)) {
                                    echo '<span class="muted">-</span>';
                                }
                                foreach ($items as $itemId):
                                    $itemUrl = LoLEsportsAPI::itemIconUrl($itemId, $patch);
                                    if (!$itemUrl) continue;
                                ?>
                                    <img class="item-icon" src="<?= h($itemUrl) ?>" alt="Item <?= h($itemId) ?>" title="Item ID: <?= h($itemId) ?>">
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php
    };

    $renderTeamTable($bluePlayers, $blueName, 'blue', $patch, $runeIconMap);
    $renderTeamTable($redPlayers, $redName, 'red', $patch, $runeIconMap);
    ?>

    <?php endif; ?>

    <div class="match-footer-links">
        <a href="index.php">← Anasayfaya dön</a>
        <a href="schedule.php">Takvimi gör</a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
