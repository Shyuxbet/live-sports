/**
 * script.js
 * - Anasayfada "Canlı Maçlar" bölümünü periyodik olarak yeniler.
 * - Takvim sayfasında admin için ★ (önemli maç) butonunu yönetir.
 */

(function () {
    var liveContainer = document.getElementById('live-matches');

    if (liveContainer) {
        var REFRESH_INTERVAL = 30000; // 30 saniye

        function refreshLiveMatches() {
            // ajax/live.php her zaman site kökünden çağrılır
            var base = window.location.pathname.includes('/admin/') ? '../' : '';
            fetch(base + 'ajax/live.php', { cache: 'no-store' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && typeof data.html === 'string') {
                        liveContainer.innerHTML = data.html;
                    }
                })
                .catch(function (err) {
                    console.error('Canlı maç verisi alınamadı:', err);
                });
        }

        setInterval(refreshLiveMatches, REFRESH_INTERVAL);
    }

    // Takvimdeki ★ önemli maç işaretleme (admin)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.star-toggle');
        if (!btn) return;

        var key = btn.getAttribute('data-key');
        if (!key) return;

        btn.disabled = true;

        fetch('ajax/toggle_important.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'key=' + encodeURIComponent(key)
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (!data || !data.success) {
                    alert((data && data.error) ? data.error : 'Bir hata oluştu.');
                    return;
                }
                var eventEl = btn.closest('.cal-event');
                if (data.important) {
                    btn.classList.add('active');
                    if (eventEl) eventEl.classList.add('important');
                } else {
                    btn.classList.remove('active');
                    if (eventEl) eventEl.classList.remove('important');
                }
            })
            .catch(function (err) {
                btn.disabled = false;
                console.error('İşaretleme başarısız:', err);
            });
    });
})();
