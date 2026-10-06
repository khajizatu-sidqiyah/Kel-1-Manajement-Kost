// Halaman Denah & Status Kamar. Membutuhkan rooms-api.js (dimuat lebih dulu).
let rooms = [];
let filter = "all";

// Komponen kartu kamar: nomor + label status + warna
function roomCard(r) {
  const idx = rooms.indexOf(r);
  const free = String(r.status).toLowerCase() !== "terisi";
  const body = free
    ? `<div class="room-box"><div>Harga Sewa <b>${rp(r.harga)}</b></div><span class="room-ok">${icon("check")}Siap Huni Bersih</span></div>
       <button type="button" class="room-btn">${icon("key")}Check-in / Isi Kamar</button>`
    : `<div class="room-box"><div>Penghuni <b>${esc(r.penghuni || "-")}</b></div>
       <div>Jatuh Tempo <b class="${r.mendesak ? "late" : ""}">${esc(r.jatuh_tempo)}${r.catatan ? " " + esc(r.catatan) : ""}</b></div></div>
       <button type="button" class="room-btn">${icon("clip")}Detail Sewa</button>`;
  return `<article class="card room room--${free ? "kosong" : "terisi"}" data-idx="${idx}" tabindex="0" role="button" aria-label="Ubah Kamar ${esc(r.no_kamar)}">
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
    const list = rooms.filter((r) => r.lantai === f).sort((a, b) => Number(a.no_kamar) - Number(b.no_kamar)), busy = list.filter((r) => r.status === "terisi").length;
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
    $("btn-add-room").disabled = true;
    return;
  }
  $("btn-add-room").disabled = false;
  if (!rooms.length) {
    showMessage(`<b>Belum ada data kamar</b><button type="button" class="btn-main" id="add-room">${icon("plus")}Tambah kamar</button>`);
    $("add-room").addEventListener("click", () => openModal("add"));
    return;
  }
  renderStats(rooms); renderTabs(); renderFloors();
}

$("tabs")?.addEventListener("click", (e) => {
  const b = e.target.closest(".tab");
  if (!b) return;
  filter = b.dataset.f; renderTabs(); renderFloors();
});

// ---------- Modal Manajemen Kamar (tambah / ubah) ----------
const FASILITAS = ["AC", "TV", "KM Dalam", "Kasur", "Jendela"];
const TIPE = ["Standard AC", "Deluxe AC", "Deluxe AC + KM Dalam", "Deluxe Plus"];
const LANTAI = [1, 2];
let mode = "add", editing = null, extraFas = [];

const fmtRp = (n) => (n === "" || n == null || isNaN(n) ? "" : "RP " + Number(n).toLocaleString("id-ID"));
const readRp = () => Number(String($("f-harga").value).replace(/\D/g, "")) || 0;
const showErr = (msg) => { const el = $("form-error"); el.textContent = msg || ""; el.hidden = !msg; };

function fillOptions() {
  set("f-lantai", LANTAI.map((l) => `<option value="${l}">Lantai ${l}</option>`).join(""));
  set("f-tipe", TIPE.map((t) => `<option>${esc(t)}</option>`).join(""));
  set("f-fas", FASILITAS.map((f) => `<label class="check"><input type="checkbox" value="${esc(f)}"><span class="box"></span>${esc(f)}</label>`).join(""));
}

function fillForm(r) {
  showErr("");
  extraFas = [];
  $("f-no").value = r ? r.no_kamar : "";
  $("f-lantai").value = r ? r.lantai : 1;
  if (r && !TIPE.includes(r.tipe_kamar)) $("f-tipe").insertAdjacentHTML("beforeend", `<option>${esc(r.tipe_kamar)}</option>`);
  $("f-tipe").value = r ? r.tipe_kamar : TIPE[0];
  const have = r ? String(r.fasilitas || "").split(",").map((x) => x.trim()).filter(Boolean) : [];
  extraFas = have.filter((x) => !FASILITAS.includes(x)); // fasilitas lama yang tidak ada di daftar tetap dipertahankan
  document.querySelectorAll("#f-fas input").forEach((c) => (c.checked = have.includes(c.value)));
  $("f-harga").value = r ? fmtRp(r.harga) : "";
  $("f-status").value = r ? (String(r.status).toLowerCase() === "terisi" ? "terisi" : "kosong") : "kosong";
}

function setMode(m, room = null) {
  mode = m; editing = room;
  document.querySelectorAll(".seg-btn").forEach((b) => b.setAttribute("aria-selected", String(b.dataset.mode === m)));
  const pickWrap = $("pick-wrap");
  if (m === "add") {
    $("form-title").textContent = "Detail Kamar Baru";
    pickWrap.hidden = true;
    fillForm(null);
  } else {
    $("form-title").textContent = "Detail Kamar";
    pickWrap.hidden = !!room && pickedFromCard;
    set("f-pick", `<option value="">— Pilih kamar —</option>` + rooms.map((r, i) => `<option value="${i}">Kamar ${esc(r.no_kamar)} (Lt. ${esc(r.lantai)})</option>`).join(""));
    $("f-pick").value = room ? String(rooms.indexOf(room)) : "";
    fillForm(room);
  }
}
let pickedFromCard = false;

function openModal(m, room = null) {
  pickedFromCard = !!room;
  setMode(m, room);
  $("room-modal").hidden = false;
  document.body.classList.add("modal-open");
  (m === "edit" && !room ? $("f-pick") : $("f-no")).focus();
}
function closeModal() {
  $("room-modal").hidden = true;
  document.body.classList.remove("modal-open");
}

async function saveForm() {
  const no = $("f-no").value.trim(), harga = readRp();
  if (mode === "edit" && !editing) return showErr("Pilih kamar yang akan diubah terlebih dahulu.");
  if (!no) return showErr("Nomor kamar wajib diisi.");
  if (!harga) return showErr("Harga per bulan wajib diisi.");
  if (rooms.some((r) => r !== editing && String(r.no_kamar) === no)) return showErr(`Nomor kamar ${no} sudah dipakai.`);
  const fas = [...document.querySelectorAll("#f-fas input:checked")].map((c) => c.value).concat(extraFas);
  const payload = { no_kamar: no, lantai: Number($("f-lantai").value), tipe_kamar: $("f-tipe").value, fasilitas: fas.join(", "), harga, status: $("f-status").value };

  const btn = $("btn-save");
  btn.disabled = true; btn.textContent = "Menyimpan…";
  try {
    const saved = await saveRoom(payload, editing);
    if (editing) rooms[rooms.indexOf(editing)] = saved; else rooms.push(saved);
  } catch (e) {
    showErr("Gagal menyimpan data kamar. Periksa koneksi Anda lalu coba lagi.");
    return;
  } finally {
    btn.disabled = false; btn.textContent = "Simpan";
  }
  closeModal();
  renderStats(rooms); renderTabs(); renderFloors(); // perbarui denah tanpa reload halaman
}

fillOptions();
$("btn-add-room").addEventListener("click", () => openModal("add"));
$("btn-save").addEventListener("click", saveForm);
$("btn-cancel").addEventListener("click", closeModal);
$("modal-x").addEventListener("click", closeModal);
$("room-modal").addEventListener("click", (e) => { if (e.target.id === "room-modal") closeModal(); });
document.addEventListener("keydown", (e) => { if (e.key === "Escape" && !$("room-modal").hidden) closeModal(); });
document.querySelectorAll(".seg-btn").forEach((b) => b.addEventListener("click", () => {
  if (b.dataset.mode === "add") setMode("add"); else { pickedFromCard = false; setMode("edit", null); }
}));
$("f-pick").addEventListener("change", (e) => { const r = rooms[e.target.value]; editing = r || null; fillForm(r || null); });
$("f-harga").addEventListener("input", (e) => { const n = readRp(); e.target.value = n ? fmtRp(n) : ""; });
$("room-form").addEventListener("keydown", (e) => { if (e.key === "Enter" && e.target.tagName === "INPUT" && e.target.type === "text") saveForm(); });

// Klik kartu kamar -> modal ubah (terisi otomatis)
function cardOpen(e) {
  const card = e.target.closest(".room");
  if (card) openModal("edit", rooms[card.dataset.idx]);
}
$("floors").addEventListener("click", cardOpen);
$("floors").addEventListener("keydown", (e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); cardOpen(e); } });

init();
