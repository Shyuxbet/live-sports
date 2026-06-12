<?php
/**
 * partial_live.php
 * Sadece canlı maçlar grid'inin İÇERİĞİNİ üretir.
 * $liveEvents dizisi parent script tarafından sağlanmalıdır.
 */
if (empty($liveEvents)): ?>
    <p class="empty-note">Şu anda yayınlanan bir maç bulunmuyor.</p>
<?php else: ?>
    <?php foreach ($liveEvents as $event):
        $match  = $event['match'] ?? [];
        $teams  = $match['teams'] ?? [];
        $league = $event['league'] ?? [];
        $gameId = get_event_game_id($event, 'inProgress');
        $teamA  = $teams[0] ?? [];
        $teamB  = $teams[1] ?? [];
    ?>
        <a class="match-card live"
           href="match.php?gameId=<?= h($gameId) ?>&eventId=<?= h(get_event_id($event)) ?>&blue=<?= h($teamA['code'] ?? $teamA['name'] ?? '') ?>&red=<?= h($teamB['code'] ?? $teamB['name'] ?? '') ?>&league=<?= h($league['name'] ?? '') ?>">
            <div class="match-card-league">
                <?php if (!empty($league['image'])): ?>
                    <img src="<?= h($league['image']) ?>" alt="" class="league-icon">
                <?php endif; ?>
                <span><?= h($league['name'] ?? '') ?></span>
                <?= state_badge($event['state'] ?? '') ?>
            </div>
            <div class="match-card-teams">
                <div class="team">
                    <?php if (!empty($teamA['image'])): ?><img src="<?= h($teamA['image']) ?>" alt=""><?php endif; ?>
                    <span class="team-code"><?= h($teamA['code'] ?? $teamA['name'] ?? '?') ?></span>
                    <span class="team-score"><?= h($teamA['result']['gameWins'] ?? 0) ?></span>
                </div>
                <div class="vs">VS</div>
                <div class="team">
                    <span class="team-score"><?= h($teamB['result']['gameWins'] ?? 0) ?></span>
                    <span class="team-code"><?= h($teamB['code'] ?? $teamB['name'] ?? '?') ?></span>
                    <?php if (!empty($teamB['image'])): ?><img src="<?= h($teamB['image']) ?>" alt=""><?php endif; ?>
                </div>
            </div>
            <div class="match-card-foot">Canlı izle / detayları gör →</div>
        </a>
    <?php endforeach; ?>
<?php endif; ?>
