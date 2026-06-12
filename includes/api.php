<?php
/**
 * LoLEsportsAPI.php
 *
 * PHP istemcisi - "Unofficial Lolesports API" (esports-api.lolesports.com /
 * feed.lolesports.com) için. Endpoint ve alan adları, projeye yüklenen
 * openapi.yaml (vickz84259/lolesports-api-docs) şemasına göre yazılmıştır.
 *
 * Kullanılan API key, Riot'un LoL Esports web sitesinin kendisinin de
 * kullandığı, herkese açık/bilinen bir public key'dir.
 */

require_once __DIR__ . '/config.php';

class LoLEsportsAPI
{
    // --- Endpoint kökleri ---
    const API_URL_PERSISTED = "https://esports-api.lolesports.com/persisted/gw";
    const API_URL_LIVE      = "https://feed.lolesports.com/livestats/v1";
    const API_KEY           = "0TvQnueqKa5mxJntVWt0w4LpLfEkrV1Ta8rQBb9Z";
    const DEFAULT_LOCALE    = "en-US";

    // --- Data Dragon (Riot statik veri) URL şablonları ---
    const ITEMS_URL        = "https://ddragon.leagueoflegends.com/cdn/PATCH_VERSION/img/item/";
    const CHAMPIONS_URL    = "https://ddragon.leagueoflegends.com/cdn/PATCH_VERSION/img/champion/";
    const RUNES_JSON_URL   = "https://ddragon.leagueoflegends.com/cdn/PATCH_VERSION/data/en_US/runesReforged.json";
    const ITEMS_JSON_URL   = "https://ddragon.leagueoflegends.com/cdn/PATCH_VERSION/data/en_US/item.json";
    const RUNE_ICON_BASE   = "https://ddragon.leagueoflegends.com/cdn/img/";
    const PROFILE_ICON_URL = "https://ddragon.leagueoflegends.com/cdn/PATCH_VERSION/img/profileicon/";

    /**
     * En son yapılan isteklerin hata ayıklama bilgisi (admin paneli için).
     * Her giriş: ['url' => ..., 'httpCode' => ..., 'error' => ..., 'bodySnippet' => ...]
     */
    public static $debugLog = [];

    // ==========================================================
    // Düşük seviye HTTP yardımcıları
    // ==========================================================

    /**
     * Sorgu parametrelerinden (dizi değerler dahil) bir URL kuyruğu (query
     * string) üretir. Diziler OpenAPI "form / explode=true" stiline göre
     * tekrarlanan anahtarlar olarak kodlanır: ?id=1&id=2
     */
    private static function buildQuery(array $params)
    {
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null) continue;

            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === null || $item === '') continue;
                    $parts[] = urlencode($key) . '=' . urlencode((string)$item);
                }
            } else {
                if ($value === '') continue;
                $parts[] = urlencode($key) . '=' . urlencode((string)$value);
            }
        }
        return implode('&', $parts);
    }

    /**
     * esports-api.lolesports.com / prod-relapi.ewp.gg uçlarına istek atar.
     * `x-api-key` header'ı ve `hl` (locale) parametresi otomatik eklenir.
     */
    private static function esportsRequest($path, array $params = [], $cacheTtl = 0)
    {
        $params = array_merge(['hl' => self::DEFAULT_LOCALE], $params);
        $query  = self::buildQuery($params);
        $url    = self::API_URL_PERSISTED . $path . '?' . $query;

        return self::request(
            $url,
            ["x-api-key: " . self::API_KEY],
            $cacheTtl > 0 ? ($path . '?' . $query) : null,
            $cacheTtl
        );
    }

    /**
     * Basit dosya tabanlı cache + cURL isteği.
     *
     * @param string $url
     * @param array  $headers
     * @param string|null $cacheKey
     * @param int    $cacheTtl saniye, 0 = cache yok
     * @return array|null decoded JSON ya da hata durumunda null
     */
    private static function request($url, $headers = [], $cacheKey = null, $cacheTtl = 0)
    {
        $cacheFile = null;
        if ($cacheKey && $cacheTtl > 0) {
            $cacheFile = CACHE_DIR . '/' . md5($cacheKey) . '.json';
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
                $cached = @file_get_contents($cacheFile);
                if ($cached !== false) {
                    $decoded = json_decode($cached, true);
                    if ($decoded !== null) {
                        return $decoded;
                    }
                }
            }
        }

        // feed.lolesports.com / esports-api.lolesports.com bazı durumlarda
        // tarayıcı benzeri istekler bekleyebiliyor (User-Agent / Referer /
        // Origin). Bu yüzden gerçek bir tarayıcı gibi davranıyoruz.
        $defaultHeaders = [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Origin: https://lolesports.com',
            'Referer: https://lolesports.com/',
        ];
        $headers = array_merge($defaultHeaders, $headers);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // gzip/deflate destekle
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        self::$debugLog[] = [
            'url'         => $url,
            'httpCode'    => $httpCode,
            'error'       => $err,
            'bodySnippet' => $response !== false ? substr((string)$response, 0, 500) : null,
        ];

        if ($err || $response === false) {
            error_log("LoLEsportsAPI cURL error for $url : $err");

            // İstek başarısız olduysa ve eski bir cache varsa onu döndür
            if ($cacheFile && file_exists($cacheFile)) {
                $cached = @file_get_contents($cacheFile);
                $decoded = json_decode($cached, true);
                if ($decoded !== null) return $decoded;
            }
            return null;
        }

        if ($httpCode >= 400) {
            error_log("LoLEsportsAPI HTTP $httpCode for $url : $response");
            if ($cacheFile && file_exists($cacheFile)) {
                $cached = @file_get_contents($cacheFile);
                $decoded = json_decode($cached, true);
                if ($decoded !== null) return $decoded;
            }
            return null;
        }

        $decoded = json_decode($response, true);

        if ($decoded !== null && $cacheFile) {
            @file_put_contents($cacheFile, $response);
        }

        return $decoded;
    }

    // ==========================================================
    // leagues
    // ==========================================================

    /**
     * Tüm ligleri (LCK, LEC, Worlds, vb.) getirir.
     * GET /getLeagues
     */
    public static function getLeagues()
    {
        return self::esportsRequest('/getLeagues', [], 6 * 3600);
    }

    /**
     * Bir lige ait turnuvaları getirir.
     * GET /getTournamentsForLeague?leagueId=...
     */
    public static function getTournamentsForLeague($leagueId)
    {
        return self::esportsRequest('/getTournamentsForLeague', [
            'leagueId' => $leagueId,
        ], 3600);
    }

    /**
     * Bir veya birden fazla turnuva için puan durumunu getirir.
     * GET /getStandings?tournamentId=...
     *
     * @param int|string|array $tournamentIds
     */
    public static function getStandings($tournamentIds)
    {
        $ids = is_array($tournamentIds) ? $tournamentIds : [$tournamentIds];
        $cacheKey = '/getStandings?tournamentId=' . implode(',', $ids);
        return self::request(
            self::API_URL_PERSISTED . '/getStandings?' . self::buildQuery([
                'hl' => self::DEFAULT_LOCALE,
                'tournamentId' => $ids,
            ]),
            ["x-api-key: " . self::API_KEY],
            $cacheKey,
            300
        );
    }

    // ==========================================================
    // events
    // ==========================================================

    /**
     * Maç takvimini (geçmiş + canlı + gelecek) getirir.
     *
     * @param array|int|null $leagueIds  Belirli lig(ler)e göre filtrelemek için
     * @param string|null    $pageToken  Sayfalama için (data.schedule.pages.older/newer)
     */
    public static function getSchedule($leagueIds = null, $pageToken = null)
    {
        $params = [];
        if ($leagueIds !== null) {
            $params['leagueId'] = is_array($leagueIds) ? $leagueIds : [$leagueIds];
        }
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        // Canlı maç takibi için kısa cache süresi (30 sn)
        return self::esportsRequest('/getSchedule', $params, 30);
    }

    /**
     * Şu anda CANLI olan maçları getirir (varsa). getSchedule'dan farklı
     * olarak sadece şu anda oynanan maçları döndürür ve lig bilgisi
     * (extendedLeague: image dahil) daha zengindir.
     * GET /getLive
     */
    public static function getLive()
    {
        return self::esportsRequest('/getLive', [], 15);
    }

    /**
     * Bir veya birden fazla turnuva için tamamlanmış maçları getirir.
     * GET /getCompletedEvents?tournamentId=...
     */
    public static function getCompletedEvents($tournamentIds)
    {
        $ids = is_array($tournamentIds) ? $tournamentIds : [$tournamentIds];
        return self::esportsRequest('/getCompletedEvents', [
            'tournamentId' => $ids,
        ], 60);
    }

    /**
     * Bir maç/etkinlik için detaylı bilgi getirir (takımlar, oyunlar,
     * stream'ler, strateji vb.)
     * GET /getEventDetails?id=...
     *
     * @param string|int $id Etkinlik (event) id'si - data.schedule.events[].id
     *                        veya data.schedule.events[].match.id
     */
    public static function getEventDetails($id)
    {
        return self::esportsRequest('/getEventDetails', [
            'id' => $id,
        ], 60);
    }

    /**
     * Belirli oyun (game) id'lerine ait bilgileri getirir (vods, state, number).
     * GET /getGames?id=...
     *
     * @param array|string|int $gameIds
     */
    public static function getGames($gameIds)
    {
        $ids = is_array($gameIds) ? $gameIds : [$gameIds];
        return self::esportsRequest('/getGames', [
            'id' => $ids,
        ], 60);
    }

    // ==========================================================
    // teams
    // ==========================================================

    /**
     * Takım(lar)ın detaylarını (kadro, logo, vb.) getirir.
     * GET /getTeams?id=...
     *
     * @param array|string|null $teamSlugs verilmezse tüm takımları getirir
     */
    public static function getTeams($teamSlugs = null)
    {
        $params = [];
        if ($teamSlugs !== null) {
            $params['id'] = is_array($teamSlugs) ? $teamSlugs : [$teamSlugs];
        }
        return self::esportsRequest('/getTeams', $params, 6 * 3600);
    }

    // ==========================================================
    // match details (feed.lolesports.com/livestats/v1)
    // ==========================================================

    /**
     * "Şimdi"yi, 10 saniyenin katına yuvarlanmış RFC3339 (UTC) formatında
     * döndürür. window/details endpoint'lerine startingTime olarak
     * verilmesi gerekir:
     *  - Maç CANLI ise: "şimdi"ye en yakın frame'i (güncel veriyi) döndürür.
     *  - Maç BİTMİŞ ise: oyunun SON frame'lerini (final istatistikleri) döndürür.
     * startingTime gönderilmezse API genelde oyunun BAŞLANGIÇ (0. dakika,
     * her şey 0) frame'ini döner — bu yüzden bu parametre zorunludur.
     *
     * @param int $delaySeconds "Şimdi"den kaç saniye geriye gidileceği (canlı
     *                          yayın gecikmesini telafi etmek için, opsiyonel).
     */
    public static function getCurrentRoundedISOTime($delaySeconds = 0)
    {
        $timestamp = time() - $delaySeconds;
        $timestamp -= ($timestamp % 10); // en yakın 10 saniyeye yuvarla
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    /**
     * Belirli bir oyunun (game) canlı istatistik penceresini getirir.
     * Takım bazlı altın, baron, ejder, kule, kill vb. veriler burada.
     * GET /window/{gameId}?startingTime=...
     */
    public static function getWindow($gameId, $startingTime = null)
    {
        if ($startingTime === null) {
            $startingTime = self::getCurrentRoundedISOTime();
        }
        $url = self::API_URL_LIVE . '/window/' . rawurlencode((string)$gameId);
        if ($startingTime) {
            $url .= '?startingTime=' . urlencode($startingTime);
        }
        return self::request($url, [], null, 0);
    }

    /**
     * Belirli bir oyunun detaylı istatistiklerini getirir.
     * Item, hasar, CS, rün vb. oyuncu bazlı veriler burada.
     * GET /details/{gameId}?startingTime=...&participantIds=...
     *
     * @param string|null $participantIds örn. "1_2_3_4_5" (alt çizgiyle ayrılmış)
     */
    public static function getDetails($gameId, $startingTime = null, $participantIds = null)
    {
        if ($startingTime === null) {
            $startingTime = self::getCurrentRoundedISOTime();
        }
        $url = self::API_URL_LIVE . '/details/' . rawurlencode((string)$gameId);

        $query = [];
        if ($startingTime) $query['startingTime'] = $startingTime;
        if ($participantIds) $query['participantIds'] = $participantIds;
        if ($query) $url .= '?' . self::buildQuery($query);

        return self::request($url, [], null, 0);
    }

    // ==========================================================
    // Data Dragon (statik oyun verisi: item, şampiyon, rün ikonları)
    // ==========================================================

    /**
     * Data Dragon statik JSON dosyalarını (item, rün vb.) getirir.
     */
    public static function getDataDragonJSON($jsonUrlTemplate, $patchVersion)
    {
        $formatted = self::formatPatchVersion($patchVersion);
        $url = str_replace('PATCH_VERSION', $formatted, $jsonUrlTemplate);
        return self::request($url, [], "ddragon_" . md5($url), 6 * 3600);
    }

    /**
     * Data Dragon'da bulunan en güncel oyun sürümünü (patch) getirir.
     */
    public static function getLatestPatch()
    {
        $versions = self::request(
            "https://ddragon.leagueoflegends.com/api/versions.json",
            [],
            "ddragon_versions",
            6 * 3600
        );
        if (is_array($versions) && count($versions) > 0) {
            return $versions[0];
        }
        return "14.1.1";
    }

    /**
     * "14.3.123456" -> "14.3.1" gibi Data Dragon klasör adına çevirir.
     */
    public static function formatPatchVersion($patchVersion)
    {
        $parts = explode('.', (string)$patchVersion);
        if (count($parts) < 2) {
            return $patchVersion . '.1';
        }
        return $parts[0] . '.' . $parts[1] . '.1';
    }

    /**
     * Bir şampiyonun kare ikonunun tam URL'sini döndürür.
     */
    public static function championIconUrl($championId, $patchVersion)
    {
        if (!$championId) return null;
        $formatted = self::formatPatchVersion($patchVersion);
        $clean = str_replace([' ', "'", '.'], '', $championId);
        return str_replace('PATCH_VERSION', $formatted, self::CHAMPIONS_URL) . $clean . '.png';
    }

    /**
     * Bir itemin ikonunun tam URL'sini döndürür.
     */
    public static function itemIconUrl($itemId, $patchVersion)
    {
        if (!$itemId || (int)$itemId === 0) return null;
        $formatted = self::formatPatchVersion($patchVersion);
        return str_replace('PATCH_VERSION', $formatted, self::ITEMS_URL) . $itemId . '.png';
    }

    /**
     * Verilen patch için rün id -> ikon yolu eşlemesini döndürür.
     * Dönen array: [perkId => "perk-images/..../Icon.png", ...]
     */
    public static function getRuneIconMap($patchVersion)
    {
        $data = self::getDataDragonJSON(self::RUNES_JSON_URL, $patchVersion);
        $map = [];
        if (!is_array($data)) return $map;

        foreach ($data as $style) {
            if (isset($style['id'], $style['icon'])) {
                $map[$style['id']] = $style['icon'];
            }
            if (!empty($style['slots']) && is_array($style['slots'])) {
                foreach ($style['slots'] as $slot) {
                    if (empty($slot['runes'])) continue;
                    foreach ($slot['runes'] as $rune) {
                        if (isset($rune['id'], $rune['icon'])) {
                            $map[$rune['id']] = $rune['icon'];
                        }
                    }
                }
            }
        }
        return $map;
    }

    /**
     * Bir rün id'sinin tam ikon URL'sini döndürür.
     */
    public static function runeIconUrl($perkId, $runeIconMap)
    {
        if (!$perkId || empty($runeIconMap[$perkId])) return null;
        return self::RUNE_ICON_BASE . $runeIconMap[$perkId];
    }
}
