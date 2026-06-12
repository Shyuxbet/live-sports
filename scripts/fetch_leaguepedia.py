"""
Leaguepedia'nin Cargo API'sinden son mac sonuclarini ceker ve
data/results.json olarak kaydeder.

Leaguepedia, MediaWiki tabanlidir ve "ScoreboardGames" tablosu uzerinden
sorgulanabilir. Detayli alan listesi icin:
https://lol.fandom.com/wiki/Special:CargoTables/ScoreboardGames
"""

import json
import os
import requests

API_URL = "https://lol.fandom.com/api.php"
HEADERS = {"User-Agent": "esports-tracker-bot/1.0 (github actions)"}

FIELDS = [
    "Team1",
    "Team2",
    "Team1Score",
    "Team2Score",
    "Winner",
    "DateTime_UTC",
    "Tournament",
    "Patch",
    "Gamelength",
]


def get_recent_games(limit=50):
    params = {
        "action": "cargoquery",
        "tables": "ScoreboardGames",
        "fields": ",".join(FIELDS),
        "order_by": "DateTime_UTC DESC",
        "limit": str(limit),
        "format": "json",
    }
    resp = requests.get(API_URL, params=params, headers=HEADERS, timeout=30)
    resp.raise_for_status()
    data = resp.json()

    if "error" in data:
        code = data["error"].get("code")
        if code == "ratelimited":
            # Gecici bir durum: bu calistirmada atla, eski results.json kalsin.
            print("Leaguepedia rate limit'e takildi, bu calistirma atlaniyor.")
            return None
        raise RuntimeError(f"Leaguepedia API hatasi: {data['error']}")

    rows = data.get("cargoquery", [])

    if not rows:
        # Bos donduyse, ham yaniti gorelim ki sebebi anlayalim.
        print("Leaguepedia bos sonuc dondurdu. Ham yanit (ilk 1000 karakter):")
        print(json.dumps(data, ensure_ascii=False)[:1000])

    return [row["title"] for row in rows]


def main():
    games = get_recent_games()

    if games is None:
        # Rate limit nedeniyle bu calistirma atlandi, mevcut dosyaya dokunma.
        return

    os.makedirs("data", exist_ok=True)
    with open("data/results.json", "w", encoding="utf-8") as f:
        json.dump(games, f, ensure_ascii=False, indent=2)

    print(f"{len(games)} sonuc yazildi -> data/results.json")


if __name__ == "__main__":
    main()
