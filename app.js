// ---------- Tab switching ----------

const tabs = document.querySelectorAll(".tab");
const panels = document.querySelectorAll(".panel");

tabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    tabs.forEach((t) => {
      t.classList.remove("active");
      t.setAttribute("aria-selected", "false");
    });
    panels.forEach((p) => p.classList.remove("active"));

    tab.classList.add("active");
    tab.setAttribute("aria-selected", "true");
    document.getElementById(`panel-${tab.dataset.tab}`).classList.add("active");
  });
});

// ---------- Helpers ----------

function fmtDateTime(iso) {
  if (!iso) return "—";
  const d = new Date(iso);
  if (isNaN(d.getTime())) return iso;
  return d.toLocaleString("tr-TR", {
    day: "2-digit",
    month: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

async function loadJSON(path) {
  const res = await fetch(path, { cache: "no-store" });
  if (!res.ok) throw new Error(`${path} yuklenemedi (${res.status})`);
  return res.json();
}

function setUpdatedAt() {
  const el = document.getElementById("updated-at");
  const now = new Date();
  el.textContent = `Sayfa açıldı: ${now.toLocaleTimeString("tr-TR")}`;
}

// ---------- Schedule (LoLEsports) ----------

function renderSchedule(events) {
  const grid = document.getElementById("schedule-grid");

  if (!events || events.length === 0) {
    grid.innerHTML = `<p class="empty">Yaklaşan veya canlı maç bulunamadı. data/schedule.json henüz boş olabilir — GitHub Actions ilk çalışmayı tamamlayınca dolacak.</p>`;
    return;
  }

  grid.innerHTML = "";

  for (const ev of events) {
    const teams = ev.teams || [];
    const t1 = teams[0] || {};
    const t2 = teams[1] || {};
    const isLive = ev.state === "inProgress";
    const isDone = ev.state === "completed";

    const t1Outcome = (t1.result || {}).outcome;
    const t2Outcome = (t2.result || {}).outcome;

    const t1Class = t1Outcome === "win" ? "winner" : t1Outcome === "loss" ? "loser" : "";
    const t2Class = t2Outcome === "win" ? "winner" : t2Outcome === "loss" ? "loser" : "";

    let centerHtml;
    if (isDone || isLive) {
      const s1 = (t1.result || {}).gameWins ?? "-";
      const s2 = (t2.result || {}).gameWins ?? "-";
      centerHtml = `<div class="score">${s1} : ${s2}</div>`;
    } else {
      centerHtml = `<div class="score">VS</div>`;
    }

    const bo = ev.strategy && ev.strategy.count ? `BO${ev.strategy.count}` : "";

    const card = document.createElement("article");
    card.className = `match-card${isLive ? " live" : ""}`;
    card.innerHTML = `
      <div class="match-meta">
        <span class="league">
          ${ev.leagueIcon ? `<img src="${ev.leagueIcon}" alt="" loading="lazy">` : ""}
          ${ev.league || ""}${ev.blockName ? " · " + ev.blockName : ""}
        </span>
        ${
          isLive
            ? `<span class="live-badge"><span class="live-dot"></span>CANLI</span>`
            : `<span class="match-time">${fmtDateTime(ev.startTime)}</span>`
        }
      </div>
      <div class="match-teams">
        <div class="team ${t1Class}">
          ${t1.image ? `<img src="${t1.image}" alt="" loading="lazy">` : ""}
          <span class="team-name">${t1.code || t1.name || "TBD"}</span>
        </div>
        <div class="vs-divider">
          ${centerHtml}
          ${bo ? `<span class="bo-label">${bo}</span>` : ""}
        </div>
        <div class="team team-right ${t2Class}">
          ${t2.image ? `<img src="${t2.image}" alt="" loading="lazy">` : ""}
          <span class="team-name">${t2.code || t2.name || "TBD"}</span>
        </div>
      </div>
    `;
    grid.appendChild(card);
  }
}

// ---------- Results (Leaguepedia) ----------

function renderResults(games) {
  const tbody = document.querySelector("#results-table tbody");

  if (!games || games.length === 0) {
    tbody.innerHTML = `<tr><td colspan="5" class="empty">Sonuç bulunamadı. data/results.json henüz boş olabilir.</td></tr>`;
    return;
  }

  tbody.innerHTML = "";

  for (const g of games) {
    const winner = String(g.Winner);
    const t1Class = winner === "1" ? "winner" : "";
    const t2Class = winner === "2" ? "winner" : "";

    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${fmtDateTime(g.DateTime_UTC)}</td>
      <td>${g.Tournament || "—"}</td>
      <td class="team-col ${t1Class}">${g.Team1 || "—"}</td>
      <td class="score-col">${g.Team1Score ?? "-"} : ${g.Team2Score ?? "-"}</td>
      <td class="team-col ${t2Class}">${g.Team2 || "—"}</td>
    `;
    tbody.appendChild(tr);
  }
}

// ---------- Stats (Oracle's Elixir) ----------

function renderStats(players) {
  const tbody = document.querySelector("#stats-table tbody");

  if (!players || players.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" class="empty">İstatistik bulunamadı. OE_CSV_URL repo değişkeni ayarlanmamış olabilir — README'ye bak.</td></tr>`;
    return;
  }

  tbody.innerHTML = "";

  for (const p of players) {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td class="team-col">${p.playername || "—"}</td>
      <td>${p.games ?? "-"}</td>
      <td>${p.avg_kills ?? "-"}</td>
      <td>${p.avg_deaths ?? "-"}</td>
      <td>${p.avg_assists ?? "-"}</td>
      <td class="score-col">${p.kda ?? "-"}</td>
    `;
    tbody.appendChild(tr);
  }
}

// ---------- Init ----------

async function init() {
  setUpdatedAt();

  try {
    const schedule = await loadJSON("data/schedule.json");
    renderSchedule(schedule.events);
  } catch (e) {
    document.getElementById("schedule-grid").innerHTML = `<p class="empty">Hata: ${e.message}</p>`;
  }

  try {
    const results = await loadJSON("data/results.json");
    renderResults(results);
  } catch (e) {
    document.querySelector("#results-table tbody").innerHTML =
      `<tr><td colspan="5" class="empty">Hata: ${e.message}</td></tr>`;
  }

  try {
    const stats = await loadJSON("data/stats.json");
    renderStats(stats);
  } catch (e) {
    document.querySelector("#stats-table tbody").innerHTML =
      `<tr><td colspan="6" class="empty">Hata: ${e.message}</td></tr>`;
  }
}

init();
