// Halaman Denah & Status Kamar.
// Data masih TIRUAN (mock). Saat endpoint backend (card 3.1) siap:
// ubah USE_MOCK = false dan sesuaikan URL + nama field di loadRooms().
const USE_MOCK = true;
const API_URL = "/api/kamar";

// [no, lantai, tipe, fasilitas, harga, status, penghuni, jatuhTempo, mendesak, catatan]
const MOCK = [
  ["01",1,"Standard AC","KM Luar",1600000,"kosong"],
  ["02",1,"Deluxe AC","KM Dalam",1600000,"terisi","Dimas Aditya","05 Nov 2024",1],
  ["03",1,"Standard AC","Kasur King",1600000,"kosong"],
  ["04",1,"Deluxe AC","KM Dalam",1600000,"terisi","Siti Rahma","18 Nov 2024",1,"(Lunas)"],
  ["05",1,"Standard AC","KM Luar",1600000,"terisi","Andi Wijaya","20 Nov 2024",1],
  ["06",1,"Deluxe AC","KM Dalam",1700000,"terisi","Rian Fathur","12 Des 2024"],
  ["07",1,"Standard AC","Kasur King",1600000,"kosong"],
  ["08",1,"Standard AC","Kasur Single",1600000,"terisi","Bayu Nugroho","22 Des 2024"],
  ["09",1,"Standard AC","Kasur Queen",1650000,"kosong"],
  ["21",1,"Deluxe Plus","Balkon Depan",1700000,"kosong"],
  ["10",2,"Standard AC","Kasur King",1650000,"kosong"],
  ["11",2,"Standard AC","Kasur Single",1650000,"kosong"],
  ["12",2,"Deluxe Plus","KM Dalam",1700000,"terisi","Taufik Hidayat","15 Des 2024"],
  ["13",2,"Deluxe AC","KM Dalam",1700000,"terisi","Nadia Putri","27 Des 2024"],
  ["14",2,"Standard AC","Kasur King",1650000,"kosong"],
  ["15",2,"Deluxe AC","Kasur Queen",1700000,"kosong"],
  ["16",2,"Deluxe AC","KM Dalam",1700000,"kosong"],
  ["17",2,"Deluxe AC","View Rooftop",1700000,"kosong"],
  ["18",2,"Standard AC","Kasur Queen",1700000,"kosong"],
  ["19",2,"Deluxe AC","KM Dalam",1750000,"kosong"],
  ["20",2,"Deluxe AC","Ventilasi Luas",1750000,"kosong"],
  ["22",2,"Standard AC","KM Luar",1700000,"terisi","Hendro Subroto","10 Des 2024",1],
  ["23",2,"Standard AC","Kasur Queen",1700000,"terisi","Anisa Rahmawati","08 Des 2024",1],
];
const FLOOR_INFO = { 1: "Akses Parkir & Area Depan", 2: "Area Balkon & Rooftop" };

// Satu-satunya tempat yang memanggil server -> mudah diganti ke endpoint asli.
async function loadRooms() {
  if (USE_MOCK) {
    const mode = new URLSearchParams(location.search).get("mock"); // ?mock=empty | ?mock=error
    if (mode === "error") throw new Error("mock error");
    if (mode === "empty") return [];
    return MOCK.map(([no, lantai, tipe, fas, harga, status, penghuni, tempo, urgent, note]) =>
      ({ no_kamar: no, lantai, tipe_kamar: tipe, fasilitas: fas, harga, status, penghuni, jatuh_tempo: tempo, mendesak: !!urgent, catatan: note }));
  }
  const res = await fetch(API_URL, { headers: { Accept: "application/json" } });
  if (!res.ok) throw new Error("HTTP " + res.status);
  return (await res.json()).data;
}

const $ = (id) => document.getElementById(id);
const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const rp = (n) => "Rp " + Number(n).toLocaleString("id-ID") + "/bln";
const icon = (id, cls = "i i-sm") => `<svg class="${cls}"><use href="#i-${id}"/></svg>`;

let rooms = [];
let filter = "all";

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

function renderStats() {
  const total = rooms.length, busy = rooms.filter((r) => r.status === "terisi").length, free = total - busy;
  const pct = total ? Math.round((busy / total) * 100) : 0, floors = new Set(rooms.map((r) => r.lantai)).size;
  const C = 2 * Math.PI * 20;
  $("stats").innerHTML = `
    <div class="card stat"><div><small>Total Kapasitas</small><div class="stat-num"><b>${total}</b><span>Unit Kamar</span></div><p>${floors} Lantai Bangunan</p></div><span class="stat-ico">${icon("building", "i")}</span></div>
    <div class="card stat stat--red"><div><small>Kamar Terisi</small><div class="stat-num"><b>${busy}</b><small class="tag tag--red">TERISI</small></div><p>Penghuni aktif terdaftar</p></div><span class="stat-ico">${icon("door", "i")}</span></div>
    <div class="card stat stat--green"><div><small>Kamar Kosong</small><div class="stat-num"><b>${free}</b><small class="tag tag--green">KOSONG</small></div><p>Siap huni / Disewa</p></div><span class="stat-ico">${icon("door", "i")}</span></div>
    <div class="card stat"><div style="flex:1"><small>Tingkat Okupansi</small><div class="stat-num"><b>${pct}%</b><span>${busy} / ${total} Kamar</span></div><div class="bar"><i style="width:${pct}%"></i></div></div>
      <div class="ring"><svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="none" stroke="#e2e8f0" stroke-width="5"/><circle cx="24" cy="24" r="20" fill="none" stroke="#047857" stroke-width="5" stroke-linecap="round" stroke-dasharray="${(C * pct) / 100} ${C}"/></svg><span>${busy}/${total}</span></div></div>`;
  $("chip-km").lastChild.textContent = `KM Dalam (${rooms.filter((r) => !/KM Luar/i.test(r.fasilitas)).length})`;
}

function renderTabs() {
  const floors = [...new Set(rooms.map((r) => r.lantai))].sort();
  const tab = (key, text) => `<button type="button" class="tab" role="tab" data-f="${key}" aria-selected="${String(filter) === String(key)}">${text}</button>`;
  $("tabs").innerHTML = tab("all", `Semua Kamar (${rooms.length})`) + floors.map((f) => tab(f, `Lantai ${f} (${rooms.filter((r) => r.lantai === f).length} Kamar)`)).join("");
}

function renderFloors() {
  const floors = [...new Set(rooms.map((r) => r.lantai))].sort().filter((f) => filter === "all" || String(f) === String(filter));
  $("floors").innerHTML = floors.map((f) => {
    const list = rooms.filter((r) => r.lantai === f), busy = list.filter((r) => r.status === "terisi").length;
    return `<section class="floor"><div class="floor-head"><h2>Lantai ${f} <span>— ${list.length} Kamar${FLOOR_INFO[f] ? " (" + FLOOR_INFO[f] + ")" : ""}</span></h2>
      <span class="floor-count">${list.length - busy} Kosong • ${busy} Terisi</span></div>
      <div class="rooms">${list.map(roomCard).join("")}</div></section>`;
  }).join("");
}

function showMessage(html) { $("stats").innerHTML = ""; $("tabs").innerHTML = ""; $("floors").innerHTML = `<div class="card state">${html}</div>`; }

async function init() {
  $("floors").innerHTML = `<div class="card state">Memuat data kamar…</div>`;
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
  renderStats(); renderTabs(); renderFloors();
}

$("tabs").addEventListener("click", (e) => {
  const b = e.target.closest(".tab");
  if (!b) return;
  filter = b.dataset.f; renderTabs(); renderFloors();
});

init();
