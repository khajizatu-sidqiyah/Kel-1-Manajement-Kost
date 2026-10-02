// Halaman Denah & Status Kamar. Membutuhkan rooms-api.js (dimuat lebih dulu).
let rooms = [];
let filter = "all";

// Komponen kartu kamar: nomor + label status + warna
function roomCard(r) {
  const free = String(r.status).toLowerCase() !== "terisi";
  const body = free
    ? `<div class="room-box"><div>Harga Sewa <b>${rp(r.harga)}</b></div><span class="room-ok">${icon("check")}Siap Huni Bersih</span></div>
       <button type="button" class="room-btn">${icon("key")}Check-in / Isi Kamar</button>`
    : `<div class="room-box"><div>Penghuni <b>${esc(r.penghuni)}</b></div>
       <div>Jatuh Tempo <b class="${r.mendesak ? "late" : ""}">${esc(r.jatuh_tempo)}${r.catatan ? " " + esc(r.catatan) : ""}</b></div></div>
       <button type="button" class="room-btn">${icon("clip")}Detail Sewa</button>`;
  return `<article class="card room room--${free ? "kosong" : "terisi"}">
    <div class="room-top"><span><i class="dot"></i>${free ? "KOSONG" : "TERISI"}</span><span>Kamar ${esc(r.no_kamar)}</span></div>
    <div class="room-body">
      <div class="room-title"><b>Kamar ${esc(r.no_kamar)}</b><span class="room-lt">Lt. ${esc(r.lantai)}</span></div>
      <p class="room-type">${esc(r.tipe_kamar)} • ${esc(r.fasilitas)}</p>${body}
    </div></article>`;
}

function renderTabs() {
  const floors = [...new Set(rooms.map((r) => r.lantai))].sort();
  const tab = (key, text) => `<button type="button" class="tab" role="tab" data-f="${key}" aria-selected="${String(filter) === String(key)}">${text}</button>`;
  set("tabs", tab("all", `Semua Kamar (${rooms.length})`) + floors.map((f) => tab(f, `Lantai ${f} (${rooms.filter((r) => r.lantai === f).length} Kamar)`)).join(""));
}

function renderFloors() {
  const floors = [...new Set(rooms.map((r) => r.lantai))].sort().filter((f) => filter === "all" || String(f) === String(filter));
  set("floors", floors.map((f) => {
    const list = rooms.filter((r) => r.lantai === f), busy = list.filter((r) => r.status === "terisi").length;
    return `<section class="floor"><div class="floor-head"><h2>Lantai ${f} <span>— ${list.length} Kamar${FLOOR_INFO[f] ? " (" + FLOOR_INFO[f] + ")" : ""}</span></h2>
      <span class="floor-count">${list.length - busy} Kosong • ${busy} Terisi</span></div>
      <div class="rooms">${list.map(roomCard).join("")}</div></section>`;
  }).join(""));
}

function showMessage(html) { set("stats", ""); set("tabs", ""); set("floors", `<div class="card state">${html}</div>`); }

async function init() {
  set("floors", `<div class="card state">Memuat data kamar…</div>`);
  try {
    rooms = await loadRooms();
  } catch (e) {
    showMessage(`<b>Data kamar gagal dimuat</b>Periksa koneksi Anda lalu coba lagi.<br><br><button type="button" class="btn-main" id="retry">Coba lagi</button>`);
    $("retry").addEventListener("click", init);
    return;
  }
  if (!rooms.length) {
    showMessage(`<b>Belum ada data kamar</b><button type="button" class="btn-main" id="add-room">Tambah kamar</button>`);
    return;
  }
  renderStats(rooms); renderTabs(); renderFloors();
}

$("tabs")?.addEventListener("click", (e) => {
  const b = e.target.closest(".tab");
  if (!b) return;
  filter = b.dataset.f; renderTabs(); renderFloors();
});

init();
