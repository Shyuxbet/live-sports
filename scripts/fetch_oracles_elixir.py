"""
Oracle's Elixir CSV'sini indirir ve oyuncu basina ozet istatistikler
uretip data/stats.json olarak kaydeder.

Oracle's Elixir verileri Google Sheets/Drive uzerinden paylasilir ve link
her split'te degisebilir (https://oracleselixir.com/tools/downloads).
Bu yuzden URL'i hardcode etmek yerine, repo'da bir "Repository variable"
olarak OE_CSV_URL tanimlamani bekliyoruz:

  Settings -> Secrets and variables -> Actions -> Variables -> New repository variable
  Name:  OE_CSV_URL
  Value: <Oracle's Elixir indirme linki - "dosya olarak indir" CSV linki>

Eger bu degisken tanimli degilse script sessizce atlar ve eski
data/stats.json dosyasi (varsa) oldugu gibi kalir.
"""

import io
import json
import os
import requests
import pandas as pd

CSV_URL = os.environ.get("OE_CSV_URL", "").strip()


def main():
    if not CSV_URL:
        print("OE_CSV_URL tanimli degil, bu adim atlaniyor.")
        return

    resp = requests.get(CSV_URL, timeout=60)
    resp.raise_for_status()

    df = pd.read_csv(io.StringIO(resp.text), low_memory=False)

    # Sadece oyuncu satirlari (takim toplam satirlarini disarida birak)
    if "position" in df.columns:
        df = df[df["position"] != "team"]

    needed = {"playername", "gameid", "kills", "deaths", "assists"}
    if not needed.issubset(df.columns):
        print("Beklenen kolonlar bulunamadi, CSV formatini kontrol et.")
        return

    summary = (
        df.groupby("playername")
        .agg(
            games=("gameid", "nunique"),
            avg_kills=("kills", "mean"),
            avg_deaths=("deaths", "mean"),
            avg_assists=("assists", "mean"),
        )
        .reset_index()
    )
    summary["kda"] = (summary["avg_kills"] + summary["avg_assists"]) / summary[
        "avg_deaths"
    ].replace(0, 1)

    summary = summary.sort_values("games", ascending=False).head(100)
    summary = summary.round(2)

    os.makedirs("data", exist_ok=True)
    summary.to_json("data/stats.json", orient="records", indent=2)

    print(f"{len(summary)} oyuncu yazildi -> data/stats.json")


if __name__ == "__main__":
    main()
