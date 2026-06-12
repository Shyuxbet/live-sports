"""
Oracle's Elixir CSV'sini indirir ve oyuncu basina ozet istatistikler
uretip data/stats.json olarak kaydeder.

Oracle's Elixir verileri Google Sheets/Drive uzerinden paylasilir ve link
her split'te degisebilir (https://oracleselixir.com/tools/downloads).
Bu yuzden URL'i hardcode etmek yerine, repo'da bir "Repository variable"
olarak OE_CSV_URL tanimlamani bekliyoruz:

  Settings -> Secrets and variables -> Actions -> Variables -> New repository variable
  Name:  OE_CSV_URL
  Value: <Oracle's Elixir Google Drive paylasim linki, oldugu gibi yapistir>
         orn: https://drive.google.com/file/d/XXXXXXXX/view?usp=sharing

Script bu linki otomatik olarak direkt indirme linkine cevirir ve Drive'in
buyuk dosyalarda gosterdigi "virus taramasi yapilamadi" onay sayfasini da
asar. Drive paylasim ayarinin "Anyone with the link" (baglantiya sahip
herkes) olmasi gerekir.

Eger OE_CSV_URL tanimli degilse script sessizce atlar ve eski
data/stats.json dosyasi (varsa) oldugu gibi kalir.
"""

import io
import os
import re
import requests
import pandas as pd

CSV_URL = os.environ.get("OE_CSV_URL", "").strip()


def to_direct_download_url(url):
    """
    Google Drive 'view' linklerini (https://drive.google.com/file/d/<ID>/view...)
    direkt indirme linkine cevirir. Diger URL'leri oldugu gibi dondurur.
    """
    m = re.search(r"drive\.google\.com/file/d/([^/]+)", url)
    if m:
        file_id = m.group(1)
        return f"https://drive.google.com/uc?export=download&id={file_id}"

    m = re.search(r"drive\.google\.com/.*[?&]id=([^&]+)", url)
    if m:
        file_id = m.group(1)
        return f"https://drive.google.com/uc?export=download&id={file_id}"

    return url


def download_csv_text(url, session):
    resp = session.get(url, timeout=120)
    resp.raise_for_status()

    content_type = resp.headers.get("Content-Type", "")

    # Buyuk Drive dosyalarinda "virus taramasi yapilamadi, devam et?" sayfasi gelir.
    if "text/html" in content_type:
        # Onay token'ini sayfadan ayikla
        m = re.search(r'confirm=([0-9A-Za-z_-]+)', resp.text)
        file_id_m = re.search(r"id=([^&]+)", url)
        if m and file_id_m:
            confirm = m.group(1)
            file_id = file_id_m.group(1)
            confirm_url = (
                f"https://drive.google.com/uc?export=download"
                f"&confirm={confirm}&id={file_id}"
            )
            resp = session.get(confirm_url, timeout=120)
            resp.raise_for_status()
            content_type = resp.headers.get("Content-Type", "")

        if "text/html" in content_type:
            raise ValueError(
                "Google Drive CSV degil HTML sayfasi dondurdu. "
                "Dosya cok buyuk olabilir veya paylasim ayarlari yanlis olabilir "
                "('Anyone with the link' olmali)."
            )

    return resp.text


def main():
    if not CSV_URL:
        print("OE_CSV_URL tanimli degil, bu adim atlaniyor.")
        return

    download_url = to_direct_download_url(CSV_URL)

    session = requests.Session()
    csv_text = download_csv_text(download_url, session)

    df = pd.read_csv(io.StringIO(csv_text), low_memory=False)

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
