# Espor Takip — GitHub Pages

LoLEsports, Leaguepedia ve Oracle's Elixir verilerini periyodik olarak çekip
statik bir sitede gösteren basit bir proje.

## Nasıl çalışır?

```
GitHub Actions (her 30 dakikada bir + manuel tetikleme)
  ├─ scripts/fetch_lolesports.py    → data/schedule.json
  ├─ scripts/fetch_leaguepedia.py   → data/results.json
  └─ scripts/fetch_oracles_elixir.py → data/stats.json
        ↓ (git commit + push)
GitHub Pages (index.html + style.css + app.js)
  → bu JSON dosyalarını fetch() ile okuyup gösterir
```

Tarayıcıdan bu API'lere direkt istek atmak CORS hatası verdiği / API key
istediği için, veriler önce Actions üzerinde Python ile çekiliyor ve repo'ya
JSON olarak commit'leniyor. Site sadece kendi repo'sundaki statik dosyaları
okuyor — bu yüzden CORS sorunu yaşanmıyor.

## Kurulum

1. Bu klasördeki tüm dosyaları GitHub repo'na yükle (kök dizine).
2. **Settings → Pages** üzerinden GitHub Pages'i `main` branch / kök dizin
   olarak aç.
3. **Settings → Actions → General** altında "Workflow permissions"ı
   **"Read and write permissions"** yap (Actions'ın `data/*.json`
   dosylarını commit edebilmesi için gerekli).
4. (Opsiyonel ama önerilir) Oracle's Elixir istatistiklerinin çalışması için:
   - https://oracleselixir.com/tools/downloads adresinden güncel CSV
     indirme linkini al (genelde bir Google Sheets/Drive linki).
   - **Settings → Secrets and variables → Actions → Variables** sekmesinden
     `OE_CSV_URL` adında bir repository variable oluştur ve linki yapıştır.
   - Bu değişken tanımlı değilse `data/stats.json` boş kalır, site bunu
     belirtir, diğer sekmeler normal çalışır.
5. **Actions** sekmesinden "Update Esports Data" workflow'unu bir kere
   manuel çalıştır (`Run workflow`) — ilk JSON dosyaları böylece üretilir.

## Takip edilen ligler

`scripts/fetch_lolesports.py` içindeki `WANTED_LEAGUES` setini düzenleyerek
hangi liglerin (LCK, LEC, LPL, MSI, Worlds, vb.) takvime dahil olacağını
değiştirebilirsin. Tam liga adları için `get_leagues()` fonksiyonunun
döndürdüğü listeye bakabilirsin (örnek bir script ile loglayıp kontrol
edebilirsin).

## Sınırlamalar / notlar

- LoLEsports API'si **resmi değildir**, herkesin kullandığı sabit bir
  public API key ile çalışır. Anthropic/Riot bunu değiştirirse
  `scripts/fetch_lolesports.py` güncellenmesi gerekebilir.
- Leaguepedia Cargo API'sine yapılan isteklerde nazik bir `User-Agent`
  ve makul bir `limit` kullanılıyor; çok sık/ağır sorgu göndermekten
  kaçın (Fandom kuralları).
- Oracle's Elixir CSV linki periyodik olarak değişir; site bozulursa
  ilk kontrol edilecek yer `OE_CSV_URL` değişkenidir.
- Cron zamanlamaları GitHub Actions'ta garanti değildir, yoğun saatlerde
  gecikme olabilir.

## Dosya yapısı

```
.
├── .github/workflows/update-data.yml   # Otomatik veri güncelleme job'ı
├── scripts/
│   ├── fetch_lolesports.py
│   ├── fetch_leaguepedia.py
│   └── fetch_oracles_elixir.py
├── data/
│   ├── schedule.json
│   ├── results.json
│   └── stats.json
├── index.html
├── style.css
└── app.js
```
