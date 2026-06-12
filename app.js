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

function buildMatchCard(ev) {
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
  return card;
}

function fillGrid(gridId, events, emptyMessage) {
  const grid = document.getElementById(gridId);

  if (!events || events.length === 0) {
    grid.innerHTML = `<p class="empty">${emptyMessage}</p>`;
    return;
  }

  grid.innerHTML = "";
  for (const ev of events) {
    grid.appendChild(buildMatchCard(ev));
  }
}

function renderSchedule(events) {
  if (!events || events.length === 0) {
    fillGrid("grid-live", [], "Veri yükleniyor… (data/schedule.json henüz boş olabilir)");
    fillGrid("grid-upcoming", [], "Veri yükleniyor…");
    fillGrid("grid-completed", [], "Veri yükleniyor…");
    return;
  }

  const live = events.filter((e) => e.state === "inProgress");
  const upcoming = events.filter((e) => e.state === "unstarted");
  const completed = events
    .filter((e) => e.state === "completed")
    // en yakın zamanda biten en üstte olsun
    .slice()
    .reverse();

  fillGrid("grid-live", live, "Şu an canlı maç yok.");
  fillGrid("grid-upcoming", upcoming, "Yaklaşan maç bulunamadı.");
  fillGrid("grid-completed", completed, "Tamamlanan maç bulunamadı.");
}

// ---------- Results (Leaguepedia) ----------

function dragonChip(type) {
  const safe = (type || "").trim();
  const cls = `dragon-${safe.replace(/[^A-Za-z]/g, "")}`;
  return `<span class="dragon-chip ${cls}">${safe || "?"}</span>`;
}

function objectivesBlock(game) {
  const o = game.objectives || {};
  const t1 = o.team1 || {};
  const t2 = o.team2 || {};
  const types = o.dragonTypes || [];

  const hasAnyStat = [t1, t2].some(
    (t) => (t.dragons || t.towers || t.barons || t.heralds)
  );

  if (!hasAnyStat && types.length === 0) {
    return "";
  }

  const col = (name, t) => `
    <div class="objectives-col">
      <span class="obj-team">${name}</span>
      <span class="obj-stat">Ejder <strong>${t.dragons ?? 0}</strong></span>
      <span class="obj-stat">Kule <strong>${t.towers ?? 0}</strong></span>
      <span class="obj-stat">Baron <strong>${t.barons ?? 0}</strong></span>
      <span class="obj-stat">Vadi Ruhu <strong>${t.heralds ?? 0}</strong></span>
    </div>
  `;

  const dragonsHtml = types.length
    ? `<div class="objectives-col">
         <span class="obj-team">Alınan Ejderler</span>
         <div class="dragon-types">${types.map(dragonChip).join("")}</div>
       </div>`
    : "";

  return `
    <div class="objectives-row">
      ${col(game.team1 || "Takım 1", t1)}
      ${col(game.team2 || "Takım 2", t2)}
      ${dragonsHtml}
    </div>
  `;
}

function bansBlock(game) {
  const b = game.bans || {};
  const t1 = b.team1 || [];
  const t2 = b.team2 || [];
  if (t1.length === 0 && t2.length === 0) return "";

  return `
    <div class="bans-row">
      <strong>${game.team1 || "Takım 1"} ban:</strong> ${t1.join(", ") || "—"}
      &nbsp;·&nbsp;
      <strong>${game.team2 || "Takım 2"} ban:</strong> ${t2.join(", ") || "—"}
    </div>
  `;
}

function playersTable(game) {
  const players = game.players || [];
  if (players.length === 0) {
    return `<p class="empty">Oyuncu bazlı veri bulunamadı.</p>`;
  }

  const team1Players = players.filter((p) => String(p.team) === "1");
  const team2Players = players.filter((p) => String(p.team) === "2");
  const others = players.filter((p) => !["1", "2"].includes(String(p.team)));

  const row = (p) => `
    <tr>
      <td class="champion-name">${p.champion || "—"}</td>
      <td>${p.player || "—"}</td>
      <td>${p.role || "—"}</td>
      <td class="kda-cell">${p.kills ?? 0} / ${p.deaths ?? 0} / ${p.assists ?? 0}</td>
      <td>
        <div class="items-cell">
          ${(p.items || []).map((it) => `<span class="item-chip">${it}</span>`).join("") || "—"}
        </div>
      </td>
    </tr>
  `;

  const groupRow = (label) => `
    <tr class="team-divider">
      <td colspan="5"><strong>${label}</strong></td>
    </tr>
  `;

  let bodyHtml = "";
  if (team1Players.length || team2Players.length) {
    if (team1Players.length) {
      bodyHtml += groupRow(game.team1 || "Takım 1");
      bodyHtml += team1Players.map(row).join("");
    }
    if (team2Players.length) {
      bodyHtml += groupRow(game.team2 || "Takım 2");
      bodyHtml += team2Players.map(row).join("");
    }
    bodyHtml += others.map(row).join("");
  } else {
    bodyHtml = players.map(row).join("");
  }

  return `
    <div class="players-table-wrap">
      <table class="players-table">
        <thead>
          <tr>
            <th>Şampiyon</th>
            <th>Oyuncu</th>
            <th>Rol</th>
            <th>K/D/A</th>
            <th>Itemler</th>
          </tr>
        </thead>
        <tbody>${bodyHtml}</tbody>
      </table>
    </div>
  `;
}

function renderResults(data) {
  const list = document.getElementById("results-list");
  const games = (data && data.games) || [];

  if (games.length === 0) {
    list.innerHTML = `<p class="empty">Sonuç bulunamadı. data/results.json henüz boş olabilir.</p>`;
    return;
  }

  list.innerHTML = "";

  for (const game of games) {
    const winner = String(game.winner);
    const t1Class = winner === "1" ? "winner" : "";
    const t2Class = winner === "2" ? "winner" : "";

    const card = document.createElement("div");
    card.className = "result-card";

    card.innerHTML = `
      <div class="result-summary">
        <div class="result-meta">
          <span>${fmtDateTime(game.date)}</span>
          <span class="tournament">${game.tournament || "—"}</span>
        </div>
        <div class="team-col ${t1Class}">${game.team1 || "—"}</div>
        <div class="result-score">${game.score?.[0] ?? "-"} : ${game.score?.[1] ?? "-"}</div>
        <div class="team-col ${t2Class}" style="text-align:right">${game.team2 || "—"}</div>
        <div class="chevron">▶</div>
      </div>
      <div class="result-detail">
        ${objectivesBlock(game)}
        ${playersTable(game)}
        ${bansBlock(game)}
      </div>
    `;

    card.querySelector(".result-summary").addEventListener("click", () => {
      card.classList.toggle("open");
    });

    list.appendChild(card);
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
    const msg = `<p class="empty">Hata: ${e.message}</p>`;
    document.getElementById("grid-live").innerHTML = msg;
    document.getElementById("grid-upcoming").innerHTML = msg;
    document.getElementById("grid-completed").innerHTML = "";
  }

  try {
    const results = await loadJSON("data/results.json");
    renderResults(results);
  } catch (e) {
    document.getElementById("results-list").innerHTML =
      `<p class="empty">Hata: ${e.message}</p>`;
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
