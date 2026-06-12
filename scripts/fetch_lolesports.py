"""
LoLEsports unofficial API'sinden maç takvimini ceker ve data/schedule.json olarak kaydeder.

API herkesin kullandigi sabit bir public key ile calisir (resmi degil ama yillardir
ayni sekilde acik). CORS nedeniyle taraycidan direkt cagrilamaz, bu yuzden bu script
GitHub Actions uzerinde calisip statik bir JSON uretir.
"""

import json
import os
import requests

API_KEY = "0TvQnueqKa5mxJntVWt0w4LpLfEkrV1Ta8rQBb9Z"
BASE_URL = "https://esports-api.lolesports.com/persisted/gw"
HEADERS = {"x-api-key": API_KEY}

# Takip etmek istedigin ligler. Tam liste icin get_leagues() ciktisina bakabilirsin.
WANTED_LEAGUES = {
    "LCK",
    "LEC",
    "LTA North",
    "LTA South",
    "LPL",
    "LCP",
    "MSI",
    "Worlds",
}


def get_leagues():
    resp = requests.get(f"{BASE_URL}/getLeagues", headers=HEADERS, params={"hl": "en-GB"})
    resp.raise_for_status()
    return resp.json()["data"]["leagues"]


def get_schedule(league_ids):
    params = {"hl": "en-GB", "leagueId": ",".join(league_ids)}
    resp = requests.get(f"{BASE_URL}/getSchedule", headers=HEADERS, params=params)
    resp.raise_for_status()
    return resp.json()["data"]["schedule"]


def simplify_event(event):
    match = event.get("match") or {}
    teams = match.get("teams") or []
    return {
        "startTime": event.get("startTime"),
        "state": event.get("state"),          # "unstarted" | "inProgress" | "completed"
        "blockName": event.get("blockName"),
        "league": (event.get("league") or {}).get("name"),
        "leagueIcon": (event.get("league") or {}).get("image"),
        "teams": [
            {
                "name": t.get("name"),
                "code": t.get("code"),
                "image": t.get("image"),
                "result": t.get("result"),    # {"outcome": "win"/"loss", "gameWins": N}
            }
            for t in teams
        ],
        "strategy": (match.get("strategy") or {}),  # ornek: {"type": "bestOf", "count": 5}
    }


def main():
    leagues = get_leagues()
    selected = [lg for lg in leagues if lg["name"] in WANTED_LEAGUES]

    if not selected:
        # Filtre tutmadiysa varsayilan (parametresiz) takvimi cek
        schedule = get_schedule([])
    else:
        league_ids = [lg["id"] for lg in selected]
        schedule = get_schedule(league_ids)

    events = [simplify_event(e) for e in schedule.get("events", [])]

    os.makedirs("data", exist_ok=True)
    with open("data/schedule.json", "w", encoding="utf-8") as f:
        json.dump(
            {"updated": schedule.get("pages", {}), "events": events},
            f,
            ensure_ascii=False,
            indent=2,
        )

    print(f"{len(events)} mac yazildi -> data/schedule.json")


if __name__ == "__main__":
    main()
