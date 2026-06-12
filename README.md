# Shyuxbet - LoL Esports Canlı Skor &amp; Takvim

PHP tabanlı, [LoL Esports API](https://esports-api.lolesports.com) verilerini kullanan
canlı maç takip, maç istatistikleri ve takvim sitesi.

## Özellikler

- **Canlı ve oynanmış maçlar** anasayfada listelenir, canlı bölüm her 30 saniyede
  otomatik yenilenir.
- **Maç detay sayfası**: takım bazlı toplam altın / altın farkı, Baron Nashor ve
  Ejder sayıları, kule/engel sayıları, oyuncu bazlı KDA, CS, hasar, item ve
  rün ikonları, şampiyon seçimleri (pick).
  > Not: LoL Esports'un herkese açık (public) API'si **draft/ban verisi sunmamaktadır**,
  > bu nedenle "yasaklı şampiyon" bölümü gösterilemez. Bu, API'nin bir kısıtıdır.
- **Takvim**: aylık takvim görünümü. Admin girişi yapıldığında her maçın yanında
  ★ butonu çıkar; tıklanınca maç "önemli" olarak işaretlenir ve yeşil renkte
  vurgulanır (anasayfada ve takvimde).
- **Header**: ortalanmış sosyal medya ikonları (Discord, Facebook, Twitter/X,
  Instagram, YouTube, Twitch) — her biri admin panelinden tek tek açılıp
  kapatılabilir, link adresleri girilebilir.
- **Admin paneli**: header'ın sol üst köşesinde küçük bir "Ayarlar" (dişli)
  butonu ile erişilir. Varsayılan şifre **1234**'tür, panelden değiştirilebilir.
- **Footer**: telif hakkı (copyright) yazısı + kaynak bilgisi.

## Klasör Yapısı

```
shyuxbet/
├── index.php              # Canlı + oynanmış maçlar
├── match.php               # Maç detay / istatistik sayfası
├── schedule.php             # Aylık takvim
├── includes/
│   ├── config.php          # Genel ayarlar / oturum
│   ├── api.php              # LoL Esports API PHP istemcisi
│   ├── functions.php        # Yardımcı fonksiyonlar, ayarlar okuma/yazma
│   ├── header.php / footer.php
│   └── partial_live.php     # Canlı maç kartları (AJAX ile tekrar kullanılır)
├── ajax/
│   ├── live.php             # Canlı maçları yeniden çeker (JSON)
│   └── toggle_important.php # Önemli maç işaretleme (admin)
├── admin/
│   ├── login.php
│   ├── index.php            # Admin paneli
│   ├── save_settings.php
│   └── logout.php
├── assets/
│   ├── css/style.css
│   └── js/script.js
└── data/
    ├── settings.json        # Şifre, sosyal medya, önemli maçlar
    └── cache/                # API cache dosyaları (otomatik oluşur)
```

## Gereksinimler

- PHP **7.4+** (PHP 8.x önerilir)
- `curl` PHP eklentisi (çoğu hosting'de varsayılan olarak açıktır)
- Yazma izni: `data/` ve `data/cache/` klasörleri PHP tarafından
  yazılabilir olmalıdır (ayarları ve cache'i saklamak için).

## Kurulum

1. Bu klasörün tüm içeriğini bir PHP destekli sunucuya (Apache/Nginx + PHP-FPM
   veya paylaşımlı hosting) yükleyin. `public_html` veya `www` kök dizinine
   koymanız yeterlidir.
2. `data/` klasörüne yazma izni verin:
   ```bash
   chmod -R 775 data
   ```
3. Siteyi tarayıcıda açın. `data/settings.json` dosyası otomatik oluşturulacaktır
   (eğer yoksa).
4. Header'ın sol üstündeki **"Ayarlar"** ikonuna tıklayıp `1234` şifresiyle
   giriş yapın. Şifreyi, sosyal medya bağlantılarını ve site başlığını
   buradan yönetebilirsiniz.

## GitHub'a Yükleme

```bash
cd shyuxbet
git init
git add .
git commit -m "Shyuxbet LoL Esports sitesi"
git branch -M main
git remote add origin https://github.com/KULLANICI_ADIN/REPO_ADI.git
git push -u origin main
```

> `data/cache/*` dosyaları `.gitignore` ile hariç tutulmuştur; `data/settings.json`
> deponuza dahil edilir (varsayılan şifre `1234` ile). Canlıya almadan önce
> şifrenizi değiştirmeniz önerilir.

## API Hakkında

`includes/api.php`, sağladığınız `LoLEsportsAPI.ts` dosyasındaki tüm
endpoint'lerin PHP karşılığıdır:

| TS Fonksiyonu              | PHP Karşılığı                          | Açıklama |
|----------------------------|------------------------------------------|----------|
| `getScheduleResponse`       | `LoLEsportsAPI::getSchedule()`            | Maç takvimi |
| `getWindowResponse`         | `LoLEsportsAPI::getWindow($gameId)`       | Canlı takım istatistikleri (altın, baron, ejder, kule...) |
| `getGameDetailsResponse`    | `LoLEsportsAPI::getDetails($gameId)`      | Oyuncu bazlı detaylar (item, hasar, rün, CS...) |
| `getEventDetailsResponse`   | `LoLEsportsAPI::getEventDetails($id)`     | Maç/etkinlik bilgisi |
| `getStandingsResponse`      | `LoLEsportsAPI::getStandings($id)`        | Turnuva puan durumu |
| `getDataDragonResponse`     | `LoLEsportsAPI::getDataDragonJSON(...)`   | Item/rün statik verisi |
| `getFormattedPatchVersion`   | `LoLEsportsAPI::formatPatchVersion(...)`  | Patch sürüm biçimleme |

Tüm istekler basit dosya tabanlı cache ile yapılır (`data/cache/`), bu sayede
hem API limitlerine yaklaşılmaz hem de sayfa yüklemeleri hızlanır. Canlı
istatistik endpoint'leri (`/window`, `/details`) cache'lenmez; her zaman
güncel veriyi gösterirler.

## Sık Sorulan Sorular

**Takvimde / anasayfada hiç maç görünmüyor?**
LoL Esports public API'si genelde sadece yaklaşık ±1-2 haftalık veri döner.
Şu anda aktif bir turnuva yoksa liste boş olabilir.

**Baron/Ejder sayıları veya altın farkı görünmüyor?**
Bu veriler sadece maç **canlı yayınlandığı veya oynandığı** sırada/sonrasında
`livestats` API'sinden gelir. Maç henüz başlamadıysa veya veri sağlayıcı
tarafında gecikme varsa boş gelebilir.

**Favicon / logo'yu nasıl değiştiririm?**
`includes/header.php` içindeki `<link rel="icon" ...>` ve `.logo-icon`
`<img>` etiketlerindeki URL'yi kendi logonuzla (örn. `assets/img/logo.png`)
değiştirin.
