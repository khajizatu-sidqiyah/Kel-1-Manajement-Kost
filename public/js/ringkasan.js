// Halaman Dashboard (ringkasan). Membutuhkan rooms-api.js (dimuat lebih dulu).
let rooms = [];
const byNo = (a, b) => Number(a.no_kamar) - Number(b.no_kamar);

function showState(html) {
  $("state").hidden = false;
  $("state").innerHTML = html;
  set("stats", "");
  $("panels").hidden = true;
  $("note").hidden = true;
}

function renderLists() {
  // Jatuh tempo terdekat: hanya kamar terisi yang punya data jatuh tempo (mendesak lebih dulu)
  const tempo = rooms.filter((r) => r.status === "terisi" && r.jatuh_tempo && r.jatuh_tempo !== "-")
    .sort((a, b) => Number(b.mendesak) - Number(a.mendesak) || byNo(a, b)).slice(0, 5);
  set("list-tempo", tempo.length
    ? tempo.map((r) => `<li><div><b>${esc(r.penghuni || "-")}</b><small>Kamar ${esc(fmtNo(r.no_kamar))}</small></div>
        <span class="end ${r.mendesak ? "late" : ""}">${esc(r.jatuh_tempo)}</span></li>`).join("")
    : `<li class="list-empty">Belum ada data jatuh tempo</li>`);

  // Kamar kosong siap disewa
  const kosong = rooms.filter((r) => r.status !== "terisi").sort(byNo).slice(0, 5);
  set("list-kosong", kosong.length
    ? kosong.map((r) => `<li><div><b>Kamar ${esc(fmtNo(r.no_kamar))}</b><small>${esc([r.tipe_kamar, "Lt. " + r.lantai].filter(Boolean).join(" • "))}</small></div>
        <span class="end price">${rp(r.harga)}</span></li>`).join("")
    : `<li class="list-empty">Tidak ada kamar kosong</li>`);
}

async function init() {
  $("state").hidden = false;
  $("state").textContent = "Memuat data kamar…";
  try {
    rooms = await loadRooms();
  } catch (e) {
    showState(`<b>Data kamar gagal dimuat</b>Periksa koneksi Anda lalu coba lagi.<br><br><button type="button" class="btn-main" id="retry">Coba lagi</button>`);
    $("retry").addEventListener("click", () => { $("panels").hidden = false; $("note").hidden = false; init(); });
    return;
  }
  if (!rooms.length) {
    showState(`<b>Belum ada data kamar</b><a class="btn-main" href="${esc($("btn-denah")?.href || "/denah")}">Tambah kamar</a>`);
    return;
  }
  $("state").hidden = true;
  $("panels").hidden = false;
  $("note").hidden = false;
  $("note").textContent = USE_MOCK
    ? "Data kamar masih contoh. Pembayaran dan komplain menunggu endpoint dari backend."
    : "Data jatuh tempo, pembayaran, dan komplain menunggu endpoint dari backend.";
  renderStats(rooms);
  renderLists();
}

init();
