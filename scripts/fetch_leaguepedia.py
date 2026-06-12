"""
Leaguepedia'nin Cargo API'sinden son mac sonuclarini, takim bazli objective
istatistiklerini (ejder/kule/baron, ejder tipleri, banlar) ve oyuncu bazli
detaylari (sampiyon, item, KDA) ceker; data/results.json olarak kaydeder.

Leaguepedia, MediaWiki + Cargo tabanlidir. Iki tablo kullanilir:
  - ScoreboardGames   -> mac/takim seviyesinde veriler
  - ScoreboardPlayers -> oyuncu seviyesinde veriler (sampiyon, itemler, KDA)

Tam alan adlari wiki surumune gore degisebildigi icin, bu script "akilli"
bir sorgu fonksiyonu kullanir: Cargo API "unknown field" hatasi donduren
alanlari otomatik olarak sorgudan cikarip tekrar dener. Boylece script,
hangi alanlarin gercekten var oldugunu kendisi kesfeder.

cikan data/results.json formati:
{
  "games": [
    {
      "gameId": "...",
      "date": "2026-06-01T12:00:00Z",
      "tournament": "...",
      "patch": "14.10",
      "team1": "T1", "team2": "T2",
      "score": [1, 0], "winner": "1",
      "objectives": {
        "team1": {"dragons": 2, "towers": 5, "barons": 1, "heralds": 0},
        "team2": {"dragons": 2, "towers": 3, "barons": 0, "heralds": 1},
        "dragonTypes": ["Cloud", "Infernal", "Ocean", "Mountain"]
      },
      "bans": {"team1": [...], "team2": [...]},
      "players": [
        {"team": "1", "player": "Faker", "champion": "Azir",
         "role": "Mid", "kills": 4, "deaths": 1, "assists": 6,
         "items": ["Infinity Edge", "Berserker's Greaves", ...]}
      ]
    }
  ]
}
"""

import json
import os
import re
import time
import requests

API_URL = "https://lol.fandom.com/api.php"
HEADERS = {"User-Agent": "esports-tracker-bot/1.0 (github actions)"}

RETRY_DELAYS = [15, 30, 60]  # saniye - rate limit'e takilirsa bu siralamayla tekrar dener

GAMES_LIMIT = 20  # kac mac icin detayli veri cekilecek (her mac ~10 oyuncu sorgusu da getirir)


# ---------------------------------------------------------------------------
# Genel amacli, kendi kendini duzelten Cargo sorgu fonksiyonu
# ---------------------------------------------------------------------------

def _extract_bad_field(error_info):
    """Cargo'nun hata mesajindan gecersiz alan adini cikarmaya calisir."""
    patterns = [
        r'Unrecognized field ["\']?([A-Za-z0-9_ ]+)["\']?',
        r'field ["\']?([A-Za-z0-9_ ]+)["\']? (?:was not found|does not exist|is not a valid)',
        r'["\']([A-Za-z0-9_ ]+)["\'] is not a (?:field|valid field)',
    ]
    for pat in patterns:
        m = re.search(pat, error_info, re.IGNORECASE)
        if m:
            return m.group(1).strip()
    return None


def cargo_query(tables, fields, where=None, order_by=None, limit=50, join_on=None):
    """
    Cargo API'sine sorgu atar. 'fields' bir liste olarak verilir.
    Eger API bir alanin gecersiz oldugunu soylerse, o alani listeden cikarip
    tekrar dener. Rate limit'e takilirsa bekleyip tekrar dener.

    Donus: (rows, kullanilan_fields) -- rows None ise rate limit nedeniyle
    tamamen vazgecildi demektir.
    """
    fields = list(fields)

    while True:
        params = {
            "action": "cargoquery",
            "tables": tables,
            "fields": ",".join(fields),
            "format": "json",
            "limit": str(limit),
        }
        if where:
            params["where"] = where
        if order_by:
            params["order_by"] = order_by
        if join_on:
            params["join_on"] = join_on

        rate_limited_out = False
        field_pruned = False

        for attempt, delay in enumerate([0] + RETRY_DELAYS):
            if delay:
                print(f"  Rate limit - {delay}s bekleyip tekrar deneniyor "
                      f"(deneme {attempt}/{len(RETRY_DELAYS)})...")
                time.sleep(delay)

            resp = requests.get(API_URL, params=params, headers=HEADERS, timeout=30)
            resp.raise_for_status()
            data = resp.json()

            if "error" in data:
                code = data["error"].get("code", "")
                info = data["error"].get("info", "")

                if code == "ratelimited":
                    if attempt == len(RETRY_DELAYS):
                        rate_limited_out = True
                    continue

                bad_field = _extract_bad_field(info)
                if bad_field:
                    new_fields = [f for f in fields if f.split("=")[0].strip() != bad_field]
                    if len(new_fields) == len(fields):
                        raise RuntimeError(f"Cargo hatasi (alan cikarilamadi): {data['error']}")
                    print(f"  Gecersiz alan '{bad_field}' sorgudan cikarildi, tekrar deneniyor...")
                    fields = new_fields
                    field_pruned = True
                    break

                raise RuntimeError(f"Cargo hatasi: {data['error']}")

            return data.get("cargoquery", []), fields

        if field_pruned:
            continue  # yeni fields ile baştan dene

        if rate_limited_out:
            print("  Tum denemeler rate limit'e takildi.")
            return None, fields


# ---------------------------------------------------------------------------
# Veri cekme
# ---------------------------------------------------------------------------

GAME_FIELDS = [
    "GameId",
    "Team1",
    "Team2",
    "Team1Score",
    "Team2Score",
    "Winner",
    "DateTime_UTC",
    "Tournament",
    "Patch",
    "Gamelength",
    "Team1Dragons",
    "Team2Dragons",
    "Team1Towers",
    "Team2Towers",
    "Team1Barons",
    "Team2Barons",
    "Team1Heralds",
    "Team2Heralds",
    "Team1Bans",
    "Team2Bans",
    "DragonType",
]

PLAYER_FIELDS = [
    "GameId",
    "Team",
    "Link",
    "Champion",
    "Role",
    "Kills",
    "Deaths",
    "Assists",
    "Items",
]


def to_int(v, default=0):
    try:
        return int(float(v))
    except (TypeError, ValueError):
        return default


def split_list(v):
    if not v:
        return []
    parts = re.split(r"\s*[,;]\s*", str(v))
    return [p.strip() for p in parts if p.strip()]


def fetch_games():
    print("ScoreboardGames sorgulaniyor...")
    rows, used_fields = cargo_query(
        tables="ScoreboardGames",
        fields=GAME_FIELDS,
        order_by="DateTime_UTC DESC",
        limit=GAMES_LIMIT,
    )
    if rows is None:
        return None
    print(f"  Kullanilan alanlar: {used_fields}")
    print(f"  {len(rows)} mac bulundu.")
    return [row["title"] for row in rows]


def fetch_players_for_games(game_ids):
    if not game_ids:
        return {}

    quoted = ",".join(f'"{gid}"' for gid in game_ids)
    print("ScoreboardPlayers sorgulaniyor...")
    rows, used_fields = cargo_query(
        tables="ScoreboardPlayers",
        fields=PLAYER_FIELDS,
        where=f"GameId IN ({quoted})",
        limit=len(game_ids) * 12,
    )
    if rows is None:
        return None
    print(f"  Kullanilan alanlar: {used_fields}")
    print(f"  {len(rows)} oyuncu satiri bulundu.")

    by_game = {}
    for row in rows:
        r = row["title"]
        gid = r.get("GameId")
        if not gid:
            continue
        by_game.setdefault(gid, []).append({
            "team": r.get("Team"),
            "player": r.get("Link"),
            "champion": r.get("Champion"),
            "role": r.get("Role"),
            "kills": to_int(r.get("Kills")),
            "deaths": to_int(r.get("Deaths")),
            "assists": to_int(r.get("Assists")),
            "items": split_list(r.get("Items")),
        })
    return by_game


def build_games(game_rows, players_by_game):
    games = []
    for row in game_rows:
        gid = row.get("GameId")

        objectives = {
            "team1": {
                "dragons": to_int(row.get("Team1Dragons")),
                "towers": to_int(row.get("Team1Towers")),
                "barons": to_int(row.get("Team1Barons")),
                "heralds": to_int(row.get("Team1Heralds")),
            },
            "team2": {
                "dragons": to_int(row.get("Team2Dragons")),
                "towers": to_int(row.get("Team2Towers")),
                "barons": to_int(row.get("Team2Barons")),
                "heralds": to_int(row.get("Team2Heralds")),
            },
            "dragonTypes": split_list(row.get("DragonType")),
        }

        bans = {
            "team1": split_list(row.get("Team1Bans")),
            "team2": split_list(row.get("Team2Bans")),
        }

        games.append({
            "gameId": gid,
            "date": row.get("DateTime_UTC"),
            "tournament": row.get("Tournament"),
            "patch": row.get("Patch"),
            "gamelength": row.get("Gamelength"),
            "team1": row.get("Team1"),
            "team2": row.get("Team2"),
            "score": [to_int(row.get("Team1Score")), to_int(row.get("Team2Score"))],
            "winner": row.get("Winner"),
            "objectives": objectives,
            "bans": bans,
            "players": (players_by_game or {}).get(gid, []),
        })
    return games


def main():
    game_rows = fetch_games()

    if game_rows is None:
        print("Rate limit nedeniyle bu calistirma atlandi, mevcut dosyaya dokunulmuyor.")
        return

    game_ids = [g.get("GameId") for g in game_rows if g.get("GameId")]
    players_by_game = fetch_players_for_games(game_ids)

    if players_by_game is None:
        print("Oyuncu verileri rate limit nedeniyle alinamadi, sadece mac ozetleri yaziliyor.")
        players_by_game = {}

    games = build_games(game_rows, players_by_game)

    os.makedirs("data", exist_ok=True)
    with open("data/results.json", "w", encoding="utf-8") as f:
        json.dump({"games": games}, f, ensure_ascii=False, indent=2)

    print(f"{len(games)} mac yazildi -> data/results.json")


if __name__ == "__main__":
    main()
