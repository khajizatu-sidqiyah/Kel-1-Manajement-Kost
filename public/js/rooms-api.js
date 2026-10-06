// Data & fungsi bersama untuk halaman Dashboard dan Denah.
// USE_MOCK = false -> data diambil dari endpoint backend card 3.1 (GET/POST/PUT /api/kamar).
// USE_MOCK = true  -> kembali memakai data tiruan di bawah (untuk uji tampilan tanpa server).
const USE_MOCK = false;
const API_URL = "/api/kamar";
const ID_KOST_DEFAULT = 1; // dipakai saat belum ada kamar sama sekali (id_kost wajib dikirim ke BE)
let kostId = ID_KOST_DEFAULT;

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

// Simulasi kondisi untuk uji (SQA/demo): ?mock=empty | ?mock=error | ?mock=saveerror
const mockMode = () => new URLSearchParams(location.search).get("mock");

// Ubah satu baris dari BE (id_kamar, no_kamar, tipe_kamar, harga, status, id_kost, ...) ke bentuk yang dipakai tampilan.
// BE belum punya kolom lantai & fasilitas -> lantai diturunkan dari nomor kamar (1-10 = Lt.1, selebihnya Lt.2).
function normalizeRoom(k) {
  const no = String(k.no_kamar ?? "");
  return {
    id: k.id_kamar ?? k.id,
    id_kost: k.id_kost,
    no_kamar: no,
    lantai: Number(k.lantai) || (Number(no) <= 10 ? 1 : 2),
    tipe_kamar: k.tipe_kamar || "",
    fasilitas: k.fasilitas || "",
    harga: Number(k.harga) || 0, // BE mengirim decimal sebagai string, mis. "750000.00"
    status: String(k.status).toLowerCase() === "terisi" ? "terisi" : "kosong", // seeder BE memakai "Kosong"
    penghuni: k.penghuni, jatuh_tempo: k.jatuh_tempo, mendesak: !!k.mendesak, catatan: k.catatan,
  };
}

// Satu-satunya tempat yang memanggil server (GET /api/kamar).
async function loadRooms() {
  const mode = mockMode();
  if (mode === "error") throw new Error("mock error");
  if (mode === "empty") return [];
  if (USE_MOCK) {
    return MOCK.map(([no, lantai, tipe, fas, harga, status, penghuni, tempo, urgent, note]) =>
      ({ no_kamar: no, lantai, tipe_kamar: tipe, fasilitas: fas, harga, status, penghuni, jatuh_tempo: tempo, mendesak: !!urgent, catatan: note }));
  }
  const res = await fetch(API_URL, { headers: { Accept: "application/json" } });
  if (!res.ok) throw new Error("HTTP " + res.status);
  const rows = (await res.json()).data;
  if (!Array.isArray(rows)) throw new Error("Format data tidak dikenali");
  if (rows[0]?.id_kost) kostId = rows[0].id_kost;
  return rows.map(normalizeRoom);
}

// Simpan kamar: POST /api/kamar (tambah) atau PUT /api/kamar/{id_kamar} (ubah).
// `old` = kamar lama saat mode ubah, `null` saat mode tambah. Mengembalikan data kamar terbaru.
async function saveRoom(payload, old) {
  if (mockMode() === "saveerror") throw new Error("mock save error");
  if (USE_MOCK) {
    const busy = payload.status === "terisi";
    return { ...(old || {}), ...payload,
      penghuni: busy ? (old?.penghuni || "Belum diisi") : undefined,
      jatuh_tempo: busy ? (old?.jatuh_tempo || "-") : undefined,
      mendesak: busy ? !!old?.mendesak : false, catatan: busy ? old?.catatan : undefined };
  }
  // lantai & fasilitas ikut dikirim; BE yang sekarang mengabaikannya (belum ada kolomnya).
  const body = { ...payload, id_kost: old?.id_kost ?? kostId };
  const res = await fetch(old ? `${API_URL}/${old.id}` : API_URL, {
    method: old ? "PUT" : "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    const err = new Error("HTTP " + res.status);
    const first = json.errors && Object.values(json.errors)[0];
    err.userMessage = (Array.isArray(first) ? first[0] : first) || json.message; // pesan validasi dari Laravel (422)
    throw err;
  }
  const saved = normalizeRoom({ ...body, ...json.data });
  return { ...saved, lantai: payload.lantai, fasilitas: payload.fasilitas, // ditampilkan sesuai isian form
    penghuni: old?.penghuni, jatuh_tempo: old?.jatuh_tempo, mendesak: !!old?.mendesak, catatan: old?.catatan };
}

// ---------- Helper ----------
const $ = (id) => document.getElementById(id);
const set = (id, html) => { const el = $(id); if (el) el.innerHTML = html; };
const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const rp = (n) => "Rp " + Number(n).toLocaleString("id-ID") + "/bln";
const icon = (id, cls = "i i-sm") => `<svg class="${cls}"><use href="#i-${id}"/></svg>`;

// Empat kartu ringkasan (dipakai Dashboard & Denah)
function renderStats(rooms) {
  const total = rooms.length, busy = rooms.filter((r) => r.status === "terisi").length, free = total - busy;
  const pct = total ? Math.round((busy / total) * 100) : 0, floors = new Set(rooms.map((r) => r.lantai)).size;
  const C = 2 * Math.PI * 20;
  set("stats", `
    <div class="card stat"><div><small>Total Kapasitas</small><div class="stat-num"><b>${total}</b><span>Unit Kamar</span></div><p>${floors} Lantai Bangunan</p></div><span class="stat-ico">${icon("building", "i")}</span></div>
    <div class="card stat stat--red"><div><small>Kamar Terisi</small><div class="stat-num"><b>${busy}</b><small class="tag tag--red">TERISI</small></div><p>Penghuni aktif terdaftar</p></div><span class="stat-ico">${icon("door", "i")}</span></div>
    <div class="card stat stat--green"><div><small>Kamar Kosong</small><div class="stat-num"><b>${free}</b><small class="tag tag--green">KOSONG</small></div><p>Siap huni / Disewa</p></div><span class="stat-ico">${icon("door", "i")}</span></div>
    <div class="card stat"><div style="flex:1"><small>Tingkat Okupansi</small><div class="stat-num"><b>${pct}%</b><span>${busy} / ${total} Kamar</span></div><div class="bar"><i style="width:${pct}%"></i></div></div>
      <div class="ring"><svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="none" stroke="#e2e8f0" stroke-width="5"/><circle cx="24" cy="24" r="20" fill="none" stroke="#047857" stroke-width="5" stroke-linecap="round" stroke-dasharray="${(C * pct) / 100} ${C}"/></svg><span>${busy}/${total}</span></div></div>`);
  if ($("chip-km")) {
    $("chip-km").hidden = !rooms.some((r) => r.fasilitas); // BE belum menyimpan fasilitas -> chip disembunyikan
    $("chip-km").lastChild.textContent = `KM Dalam (${rooms.filter((r) => !/KM Luar/i.test(r.fasilitas)).length})`;
  }
}
