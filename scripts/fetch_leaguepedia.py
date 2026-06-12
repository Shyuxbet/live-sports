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
    return [row["title"] for row in data.get("cargoquery", [])]


def main():
    games = get_recent_games()

    os.makedirs("data", exist_ok=True)
    with open("data/results.json", "w", encoding="utf-8") as f:
        json.dump(games, f, ensure_ascii=False, indent=2)

    print(f"{len(games)} sonuc yazildi -> data/results.json")


if __name__ == "__main__":
    main()
