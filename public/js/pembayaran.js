// Halaman Pembayaran & Tagihan  (resources/views/pembayaran.blade.php)
// User story 5.2a: pencatatan pembayaran DP, cicilan, dan pelunasan.
//
// Endpoint yang dipakai:
//   GET   /api/penghunian                 -> penghunian + relasi kamar, penghuni, pembayaran     (SUDAH ADA)
//   POST  /api/penghunian/{id}/hitung-dp  -> hitung & simpan skema/total sewa/DP minimum/tenggat
//   POST  /api/pembayaran                 -> catat DP, cicilan (tahunan), dan pelunasan (jenis_pembayaran: dp | cicilan | pelunasan)
//   PATCH /api/pembayaran/{id}/status     -> (tidak dipakai di 5.2b)
// Semua request API membawa token Bearer dari localStorage (disimpan login.js), karena route API memakai auth:sanctum.
//
// "Tagihan" belum punya tabel/endpoint sendiri, jadi daftar tagihan (tab Tagihan) dan riwayat (tab Riwayat Pembayaran)
// DIHITUNG di sini dari data penghunian + pembayaran, mengikuti aturan tagihan card 5.2b.
// Ganti hitungTagihan() bila backend sudah menyediakan data tagihan.
(function () {
  "use strict";

  const AUTH = { Authorization: `Bearer ${localStorage.getItem("token") || ""}` };

  const PER_PAGE = 10; // desain: 10 baris per halaman, tombol "Lihat semua data" menampilkan semuanya
  const state = { rows: [], tab: "tagihan", page: 1, loading: true, error: "", all: false, view: [] };
  // Filter yang sedang diterapkan (dropdown di panel Filter). Satu nilai per filter; "" = semua.
  //   Tab Tagihan : Status Tagihan, Skema Sewa, Lantai
  //   Tab Riwayat : Periode (tanggal pembayaran), Skema Sewa, Lantai
  const FILTER_KEYS = ["status", "periode", "skema", "lantai"];
  const FILTER_TAB = { tagihan: ["status", "skema", "lantai"], riwayat: ["periode", "skema", "lantai"] };
  const applied = { status: "", periode: "", skema: "", lantai: "" };
  const keysAktif = () => FILTER_TAB[state.tab];
  const adaFilter = () => keysAktif().some((k) => applied[k] !== "") || $("pb-q").value.trim() !== "";
  let syncFilterUI = () => {}; // diisi initFilterPanel(): sesuaikan isi panel & badge dengan tab aktif
  const F = { saving: false, mode: "", tenggatManual: false, pickable: false, hitung: null, seq: 0 }; // hitung = hasil BE (hitung-dp): total_sewa, dp_minimum, tenggat
  const CICILAN_N = 3; // skema tahunan: cicilan 1, cicilan 2, pelunasan = 3 periode

  /* ---------------------------------------------------------
     Util
  --------------------------------------------------------- */
  const $ = (id) => document.getElementById(id);
  const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const ic = (n, c = "i") => `<svg class="${c}"><use href="#pb-${n}"/></svg>`;
  const num = (v) => (v === null || v === undefined || v === "" ? 0 : Number(v) || 0);
  const iso = (v) => (v ? String(v).slice(0, 10) : "");
  const rp = (n) => "Rp " + Math.round(n).toLocaleString("id-ID");
  const rpRapat = (n) => "Rp" + Math.round(n).toLocaleString("id-ID"); // penulisan di user story: "Rp300.000"
  const BULAN = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
  const BLN = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
  const tglPendek = (s) => { const [y, m, d] = iso(s).split("-"); return y && BLN[m - 1] ? `${Number(d)} ${BLN[m - 1]} ${y}` : ""; };
  const pad = (n) => String(n).padStart(2, "0");
  const todayIso = () => { const d = new Date(); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; };
  const periodeLabel = (key) => { const [y, m] = key.split("-"); return BULAN[m - 1] ? `${BULAN[m - 1]} ${y}` : key; };
  const fmtNo = (no) => (/^\d+$/.test(String(no)) ? String(no).padStart(2, "0") : String(no));
  const lantaiDariNomor = (no) => { const n = Number(no); return (n >= 1 && n <= 9) || n === 21 ? 1 : 2; }; // sama dengan rooms-api.js skrng menjadi kamar-api
  const JENIS = { dp: "DP", pelunasan_pertama: "Pelunasan Bulan Pertama", bulanan: "Tagihan Bulanan", cicilan: "Cicilan", pelunasan: "Pelunasan" };
  const jenisLabel = (b) => (b.jenis === "cicilan" || b.jenis === "pelunasan") && b.periode ? `${JENIS[b.jenis]} ${b.periode}/${CICILAN_N}` : JENIS[b.jenis] || "-";
  const tglPanjang = (s) => { const [y, m, d] = iso(s).split("-"); return y && BULAN[m - 1] ? `${pad(d)} ${BULAN[m - 1]} ${y}` : ""; };
  const parseRp = (v) => Number(String(v || "").replace(/\D/g, "")) || 0;
  function tambahBulan(s, n) { // setara addMonthsNoOverflow
    const [y, m, d] = iso(s).split("-").map(Number), t = new Date(y, m - 1 + n, 1), last = new Date(t.getFullYear(), t.getMonth() + 1, 0).getDate();
    return `${t.getFullYear()}-${pad(t.getMonth() + 1)}-${pad(Math.min(d, last))}`;
  }

  let toastTimer;
  function toast(msg, err) {
    document.querySelectorAll(".pb-toast").forEach((t) => t.remove());
    const t = document.createElement("div");
    t.className = "pb-toast" + (err ? " pb-toast--err" : ""); t.textContent = msg; t.setAttribute("role", "status");
    document.body.appendChild(t);
    clearTimeout(toastTimer); toastTimer = setTimeout(() => t.remove(), 4500);
  }

  // Pemanggil API: menerjemahkan galat jaringan / server menjadi pesan yang bisa dibaca pemilik kos
  async function kirim(url, method, body) {
    const isForm = body instanceof FormData;
    const opts = { method, headers: { Accept: "application/json", ...AUTH } };
    if (body !== undefined) { opts.body = isForm ? body : JSON.stringify(body); if (!isForm) opts.headers["Content-Type"] = "application/json"; }
    let res;
    try { res = await fetch(url, opts); }
    catch (_) {
      const e = new Error("offline"); e.offline = true;
      e.userMessage = "Koneksi terputus. Periksa jaringan Anda, lalu tekan Simpan lagi. Isian Anda tidak hilang.";
      throw e;
    }
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
      const e = new Error("HTTP " + res.status); e.status = res.status;
      e.code = json.error && json.error.code;
      const det = json.errors || (json.error && typeof json.error.details === "object" ? json.error.details : null);
      const first = det && Object.values(det)[0];
      let msg = (Array.isArray(first) ? first[0] : first) || json.message || "";
      if (/could not be found|not supported for route/i.test(msg) || res.status === 405) msg = `Fitur ini belum tersedia di server (${method} ${url}). Hubungi tim backend.`;
      else if (res.status === 401 || res.status === 419) msg = "Sesi Anda berakhir. Silakan login ulang.";
      else if (res.status >= 500) msg = msg || "Terjadi kesalahan pada server. Coba lagi beberapa saat lagi.";
      e.userMessage = msg || "Permintaan gagal. Coba lagi.";
      throw e;
    }
    return json;
  }

  /* ---------------------------------------------------------
     Data: normalisasi, kamar, tagihan, riwayat
  --------------------------------------------------------- */
  // Jenis pembayaran. Saat ini BE menyimpan 'dp' di kolom status; kontrak OpenAPI memakai jenis_pembayaran.
  function jenisOf(p) {
    const j = String(p.jenis_pembayaran || "").toLowerCase(); if (JENIS[j]) return j;
    const s = String(p.status || "").toLowerCase(); if (JENIS[s]) return s;
    return /^dp\b/i.test(p.metode_pembayaran || "") ? "dp" : "cicilan";
  }
  function statusBayarOf(p) {
    const s = String(p.status || "").toLowerCase();
    if (s === "menunggu_verifikasi" || s === "pending") return "menunggu";
    if (s === "ditolak") return "ditolak";
    return "sudah"; // dp / lunas / dst: dicatat langsung oleh pemilik
  }
  const flagUtama = (x) => x === true || Number(x) === 1;

  function normalize(p) {
    const k = p.kamar || {}, no = k.no_kamar ?? "";
    return {
      id: p.id_penghunian, id_kamar: p.id_kamar, no_kamar: no, lantai: Number(k.lantai) || lantaiDariNomor(no), harga: num(k.harga), hargaTahunan: "harga_tahunan" in k ? num(k.harga_tahunan) : undefined,
      nama: p.penghuni?.nama_penghuni || "-", utama: flagUtama(p.penyewa_utama) || flagUtama(p.is_penyewa_utama),
      status: String(p.status || "aktif").toLowerCase(), mulai: iso(p.tanggal_mulai),
      skema: String(p.skema_sewa || "").toLowerCase(), total: num(p.total_sewa), dpMin: num(p.dp_minimum), tenggat: iso(p.tenggat_pelunasan),
      bayar: (p.pembayaran || []).map((b) => ({
        id: b.id_pembayaran, tanggal: iso(b.tanggal_bayar), jumlah: num(b.jumlah),
        metode: String(b.metode_pembayaran || "").replace(/^DP\s*-\s*/i, ""), jenis: jenisOf(b), periode: Number(b.periode_cicilan) || 0, periodeTagihan: iso(b.periode_tagihan), status: statusBayarOf(b), ket: b.keterangan || "",
      })),
    };
  }

  // Status tagihan -> kunci tampilan: belum_bayar / belum_lunas / lunas ("Terlambat" menyusul di card 5.5)
  const jenisTagihanLabel = (s) => (s === "bulanan" ? "Bulanan" : s === "tahunan" ? "Tahunan" : "-");
  const periodeBulan = (d) => (d ? periodeLabel(iso(d).slice(0, 7)) : "-"); // "November 2026"; "-" bila tahunan

  async function muatData() {
    // per_page=all wajib: tanpa itu BE hanya mengirim 10 data pertama
    // Timeout panjang (60 dtk) hanya sebagai pengaman bila koneksi benar-benar menggantung.
    // Server yang sekadar lambat TIDAK dianggap galat: tampilan tetap "Memuat data…" sampai data tampil.
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 60000);
    try {
      const res = await fetch("/api/penghunian?per_page=all", { headers: { Accept: "application/json", ...AUTH }, signal: ctrl.signal });
      if (res.status === 401) { localStorage.removeItem("token"); location.href = "/login"; return null; }
      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = (await res.json()).data;
      if (!Array.isArray(data)) throw new Error("Format data tidak dikenali");
      return data.map(normalize);
    } finally { clearTimeout(timer); }
  }

  // Kelompokkan per kamar. Pembayaran dicatat atas nama penyewa utama; kamar 1 orang -> orang itu.
  function hitungKamar() {
    const map = new Map();
    state.rows.forEach((r) => {
      if (!map.has(r.id_kamar)) map.set(r.id_kamar, { id: r.id_kamar, no: r.no_kamar, lantai: r.lantai, harga: r.harga, hargaTahunan: r.hargaTahunan, semua: [] });
      map.get(r.id_kamar).semua.push(r);
    });
    const list = [...map.values()];
    list.forEach((k) => {
      k.aktif = k.semua.filter((r) => r.status === "aktif").sort((a, b) => a.id - b.id);
      k.utama = k.aktif.find((r) => r.utama) || k.aktif[0] || null;
      k.adaDp = k.aktif.some((r) => r.bayar.some((b) => b.jenis === "dp" && b.status !== "ditolak")); // DP sekali per kamar untuk penghuni AKTIF; DP penghuni lama (sudah keluar) tidak dihitung
      k.bayarAktif = k.aktif.flatMap((r) => r.bayar);
      const u = k.utama;
      k.skema = u?.skema || "";
      k.total = u ? (u.total || (k.skema === "tahunan" ? 0 : u.harga)) : 0; // tanpa pengaturan sewa: pakai harga bulanan kamar
      k.dpMin = u?.dpMin || 0; k.tenggat = u?.tenggat || "";
    });
    return list.sort((a, b) => String(a.no).localeCompare(String(b.no), undefined, { numeric: true }));
  }

  // Posisi pembayaran satu kamar (hanya pembayaran berstatus sudah dibayar).
  //   Tahunan : sisa = total - dibayar; cicilan = berapa kali cicilan sudah tercatat (maks 2; periode ke-3 = pelunasan).
  //   Bulanan : dihitung berjalan (ledger). Periode ke-N lunas bila total dibayar >= N x tarif.
  const dpRows = (k) => k.aktif.flatMap((r) => r.bayar).filter((b) => b.jenis === "dp" && b.status !== "ditolak");
  const dpDate = (k) => dpRows(k).map((b) => b.tanggal).filter(Boolean).sort()[0] || "";
  function ringkas(k) {
    const sudah = k.bayarAktif.filter((b) => b.status === "sudah"), T = Math.round(sudah.reduce((s, b) => s + b.jumlah, 0));
    if (k.skema === "tahunan") {
      return { tahunan: true, T, total: k.total, sisa: Math.max(0, k.total - T), cicilan: sudah.filter((b) => b.jenis === "cicilan").length, lunas: k.total > 0 && T >= k.total, n: sudah.length };
    }
    const H = k.total || k.harga;
    return { tahunan: false, T, H, sisa: H > 0 ? H - (T % H) : 0, paidPeriods: H > 0 ? Math.floor(T / H) : 0, pertamaLunas: H > 0 && T >= H, n: sudah.length };
  }

  // Tenggat berikutnya: tanggal 29-31 menjadi hari terakhir bulan berikutnya (sama dengan BE / card 5.1)
  function tenggatBerikutnya(s) {
    const [y, m, d] = iso(s).split("-").map(Number), last = new Date(y, m + 1, 0).getDate();
    return `${new Date(y, m, 1).getFullYear()}-${pad(new Date(y, m, 1).getMonth() + 1)}-${pad(d >= 29 ? last : Math.min(d, last))}`;
  }
  const awalBulan = (s) => iso(s).slice(0, 7) + "-01";
  const bulanSebelum = (s) => { const [y, m] = iso(s).split("-").map(Number), t = new Date(y, m - 2, 1); return `${t.getFullYear()}-${pad(t.getMonth() + 1)}-01`; };

  // Satu baris tagihan per kamar berpenghuni: tagihan yang sudah terbit, belum lunas, dan paling lama.
  // Bila semua lunas: status "lunas" dengan Jatuh Tempo = tenggat tagihan berikutnya ("-" untuk tahunan).
  //   Tahunan : satu tagihan untuk seluruh masa sewa.
  //   Bulanan : satu tagihan per bulan; tagihan pertama terbit saat penyewa utama tersimpan, berikutnya pada tanggal tenggatnya.
  //             tenggat_pelunasan selalu menunjuk tagihan belum lunas terlama (baru maju setelah periodenya lunas),
  //             jadi pembayaran otomatis dihitung untuk tagihan terlama lebih dulu.
  function hitungTagihan(k) {
    const u = k.utama; if (!u) return null;
    const sudah = u.bayar.filter((b) => b.status === "sudah"), jumlahOf = (arr) => Math.round(arr.reduce((s, b) => s + b.jumlah, 0));
    const T = jumlahOf(sudah), today = todayIso();
    const dasar = { tipe: "tagihan", key: "t" + k.id, id_kamar: k.id, no: k.no, lantai: k.lantai, nama: u.nama, skema: k.skema, nBayar: k.bayarAktif.length };
    const hasil = (periode, tempo, jumlah, paid, status) => ({ ...dasar, periode, tempo, jumlah, paid: Math.min(paid, jumlah), sisa: Math.max(0, jumlah - paid), status });
    const statusDari = (paid, jml) => (paid >= jml ? "lunas" : paid > 0 ? "belum_lunas" : "belum_bayar");

    // Skema / total sewa belum dihitung: kamar tetap tampil dengan tarif bulanan kamar, status Belum Bayar
    if (!["bulanan", "tahunan"].includes(k.skema) || !k.total) return hasil("", "", k.harga, 0, "belum_bayar");

    if (k.skema === "tahunan") {
      const lunas = T >= k.total;
      return hasil("", lunas ? "" : iso(k.tenggat), k.total, T, statusDari(T, k.total));
    }

    const H = k.total;
    if (!k.tenggat) return hasil("", "", H, 0, "belum_bayar");
    const P = awalBulan(k.tenggat), paidP = jumlahOf(sudah.filter((b) => b.periodeTagihan === P));
    if (paidP >= H) return hasil(P, tenggatBerikutnya(k.tenggat), H, H, "lunas"); // kondisi tepi: tenggat belum dimajukan
    const adaSebelumnya = sudah.some((b) => b.periodeTagihan && b.periodeTagihan < P);
    if (!adaSebelumnya || iso(k.tenggat) <= today) return hasil(P, iso(k.tenggat), H, paidP, statusDari(paidP, H)); // sudah terbit, belum lunas
    return hasil(bulanSebelum(P), iso(k.tenggat), H, H, "lunas"); // tagihan terakhir lunas, tagihan berikutnya belum terbit
  }

  function hitungRiwayat() {
    return state.rows.flatMap((r) => r.bayar.map((b) => ({
      tipe: "riwayat", key: "p" + b.id, id: b.id, no: r.no_kamar, lantai: r.lantai, nama: r.nama, skema: r.skema,
      periode: b.periodeTagihan, tanggal: b.tanggal, jumlah: b.jumlah, metode: b.metode || "-",
    }))).sort((a, b) => (b.tanggal || "").localeCompare(a.tanggal || "") || b.id - a.id);
  }

  function semuaItem() {
    return { tagihan: hitungKamar().map(hitungTagihan).filter(Boolean), riwayat: hitungRiwayat() };
  }

  /* ---------------------------------------------------------
     Render: ringkasan, filter, tabel
  --------------------------------------------------------- */
  // tagihan === null saat memuat / galat: tampilkan "-" supaya tidak ada angka kosong (Rp 0, 0%)
  function renderStats(tagihan) {
    const kartu = (ico, lbl, val, sub) => `<div class="pb-stat"><span class="ico">${ic(ico)}</span><span class="lbl">${lbl}</span><span class="val">${val}</span>${sub}</div>`;
    if (!tagihan) {
      const kosong = '<span class="sub">&nbsp;</span>';
      $("pb-stats").innerHTML =
        kartu("doc", "Total Tagihan Bulan Ini", "-", kosong) + kartu("bag", "Total Pembayaran Masuk", "-", kosong) +
        kartu("clock", "Tagihan Belum Dibayar", "-", kosong) + kartu("trend", "Tingkat Pembayaran", "-", kosong);
      return;
    }
    const total = tagihan.reduce((s, t) => s + t.jumlah, 0);
    const masuk = tagihan.reduce((s, t) => s + Math.min(t.paid, t.jumlah), 0);
    const nMasuk = tagihan.reduce((s, t) => s + t.nBayar, 0);
    const belum = tagihan.filter((t) => t.status !== "lunas");
    const rate = total ? Math.round((masuk / total) * 100) : 0;
    $("pb-stats").innerHTML =
      kartu("doc", "Total Tagihan Bulan Ini", rp(total), `<span class="sub">${tagihan.length} tagihan</span>`) +
      kartu("bag", "Total Pembayaran Masuk", rp(masuk), `<span class="sub">${nMasuk} pembayaran</span>`) +
      kartu("clock", "Tagihan Belum Dibayar", rp(belum.reduce((s, t) => s + t.sisa, 0)), `<span class="sub">${belum.length} tagihan</span>`) +
      kartu("trend", "Tingkat Pembayaran", rate + "%", `<div class="pb-bar" role="progressbar" aria-valuenow="${rate}" aria-valuemin="0" aria-valuemax="100"><i style="width:${rate}%"></i></div>`);
  }

  // Kunci status = status dari BE: belum_bayar / belum_lunas / lunas ("Terlambat" menyusul di card 5.5)
  const statusKey = (it) => it.status;

  const pill = (st) => ({
    belum_bayar: '<span class="pb-pill pb-pill--belum">Belum Bayar</span>',
    belum_lunas: '<span class="pb-pill pb-pill--lunas">Belum Lunas</span>',
    lunas: '<span class="pb-pill pb-pill--sudah">Sudah Lunas</span>',
  }[st] || `<span class="pb-pill pb-pill--ditolak">${esc(st)}</span>`);

  // Tab Tagihan: tombol Bayar (nonaktif bila lunas). Tab Riwayat: tombol Detail.
  function aksiHtml(it) {
    if (it.tipe === "riwayat") return '<div class="pb-aksi"><button type="button" class="pb-act pb-act--main" data-act="detail">Detail</button></div>';
    const lunas = it.status === "lunas";
    return `<div class="pb-aksi"><button type="button" class="pb-act pb-act--main" data-act="bayar"${lunas ? ' disabled aria-disabled="true" title="Tagihan sudah lunas"' : ""}>Bayar</button></div>`;
  }

  const HEAD = {
    tagihan: '<tr><th class="th-no">No.</th><th>Nama Penghuni</th><th>Kamar</th><th>Jenis Tagihan</th><th>Periode</th><th>Jatuh Tempo</th><th>Jumlah</th><th class="th-status">Status</th><th class="th-aksi">Aksi</th></tr>',
    riwayat: '<tr><th class="th-no">No.</th><th>Nama Penghuni</th><th>Kamar</th><th>Jenis Tagihan</th><th>Periode</th><th>Tanggal Pembayaran</th><th>Jumlah Dibayar</th><th class="th-status">Metode</th><th class="th-aksi">Aksi</th></tr>',
  };

  function rowHtml(it, no) {
    const tg = it.tipe === "tagihan";
    const awal = `<tr data-key="${esc(it.key)}"><td class="no">${no}</td><td class="nama">${esc(it.nama)}</td>
      <td class="kamar">${esc(fmtNo(it.no))} (Lt. ${esc(it.lantai)})</td><td class="jenis">${esc(jenisTagihanLabel(it.skema))}</td>
      <td class="per">${esc(periodeBulan(it.periode))}</td>`;
    const akhir = `<td class="aksi">${aksiHtml(it)}</td></tr>`;
    return tg
      ? `${awal}<td class="tempo">${esc(it.tempo ? tglPendek(it.tempo) : "-")}</td><td class="jml">${rp(it.jumlah)}</td><td class="status">${pill(statusKey(it))}</td>${akhir}`
      : `${awal}<td class="tempo">${esc(it.tanggal ? tglPendek(it.tanggal) : "-")}</td><td class="jml">${rp(it.jumlah)}</td><td class="status">${esc(it.metode)}</td>${akhir}`;
  }

  /* ---------------------------------------------------------
     FILTER  ->  logika penyaringan idealnya ada di BACKEND.
     Front-end mengirim parameter di bawah ini (query string):
       GET /api/tagihan?status=belum_bayar|belum_lunas|lunas
                       &skema=bulanan|tahunan&lantai=1|2
                       &q=<kata kunci>&page=1&per_page=10        (atau per_page=all)
       GET /api/pembayaran?periode=bulan_ini|bulan_lalu|6_bulan|tahun_ini|tahun_lalu|2_tahun_lalu
                          &skema=...&lantai=...&q=...
     Selama endpoint tersebut belum ada, filtered() di bawah menyaring di browser
     agar tampilan tetap berfungsi. Setelah BE siap: panggil paramFilter() lalu
     hapus penyaringan lokal di filtered().
  --------------------------------------------------------- */
  function paramFilter() {
    const p = new URLSearchParams();
    keysAktif().forEach((k) => { if (applied[k]) p.set(k, applied[k]); });
    const q = $("pb-q").value.trim(); if (q) p.set("q", q);
    p.set("page", state.all ? 1 : state.page); p.set("per_page", state.all ? "all" : PER_PAGE);
    return p;
  }

  // Rentang tanggal [awal, akhir] untuk filter Periode (berdasarkan tanggal pembayaran).
  // "6 Bulan Lalu" = 6 bulan kalender terakhir termasuk bulan ini.
  function rentangPeriode(p) {
    const d = new Date(), y = d.getFullYear(), m = d.getMonth();
    const f = (t) => `${t.getFullYear()}-${pad(t.getMonth() + 1)}-${pad(t.getDate())}`;
    switch (p) {
      case "bulan_ini": return [f(new Date(y, m, 1)), f(new Date(y, m + 1, 0))];
      case "bulan_lalu": return [f(new Date(y, m - 1, 1)), f(new Date(y, m, 0))];
      case "6_bulan": return [f(new Date(y, m - 5, 1)), f(new Date(y, m + 1, 0))];
      case "tahun_ini": return [`${y}-01-01`, `${y}-12-31`];
      case "tahun_lalu": return [`${y - 1}-01-01`, `${y - 1}-12-31`];
      case "2_tahun_lalu": return [`${y - 2}-01-01`, `${y - 2}-12-31`];
      default: return null;
    }
  }

  // Pencarian & filter dilakukan di browser (seluruh kamar dimuat sekaligus).
  function filtered(items) {
    const q = $("pb-q").value.trim().toLowerCase();
    return (state.tab === "tagihan" ? items.tagihan : items.riwayat).filter((i) => {
      if (q && !(i.nama.toLowerCase().includes(q) || String(i.no).toLowerCase().includes(q) || fmtNo(i.no).includes(q))) return false;
      if (state.tab === "tagihan" && applied.status && i.status !== applied.status) return false;
      if (state.tab === "riwayat" && applied.periode) {
        const r = rentangPeriode(applied.periode);
        if (r && (!i.tanggal || i.tanggal < r[0] || i.tanggal > r[1])) return false;
      }
      if (applied.skema && i.skema !== applied.skema) return false;
      if (applied.lantai && String(i.lantai) !== applied.lantai) return false;
      return true;
    });
  }

  function render() {
    const box = $("pb-state"), items = semuaItem();
    const gagal = state.loading || !!state.error;
    renderStats(gagal ? null : items.tagihan);
    $("pb-head").innerHTML = HEAD[state.tab];
    const list = filtered(items); state.view = list;
    const pages = state.all ? 1 : Math.max(1, Math.ceil(list.length / PER_PAGE));
    state.page = Math.min(state.page, pages);
    const start = state.all ? 0 : (state.page - 1) * PER_PAGE, slice = state.all ? list : list.slice(start, start + PER_PAGE);

    if (state.loading) { $("pb-rows").innerHTML = ""; box.hidden = false; box.innerHTML = "<b>Memuat data…</b>Mohon tunggu sebentar."; }
    else if (state.error) {
      $("pb-rows").innerHTML = ""; box.hidden = false;
      box.innerHTML = `<b>Data pembayaran gagal dimuat</b>${esc(state.error)}<br><button type="button" id="pb-retry">Coba lagi</button>`;
    } else if (!slice.length) {
      $("pb-rows").innerHTML = ""; box.hidden = false;
      const adaData = state.tab === "tagihan" ? items.tagihan.length : items.riwayat.length;
      box.innerHTML = adaData
        ? "<b>Tidak ada data yang cocok</b>Coba ubah kata kunci atau filter yang dipakai."
        : state.tab === "tagihan" ? "<b>Belum ada kamar berpenghuni</b>Tagihan akan tampil di sini setelah ada kamar yang dihuni." : "<b>Belum ada pembayaran</b>Pembayaran yang dicatat akan tampil di sini.";
    } else { box.hidden = true; $("pb-rows").innerHTML = slice.map((it, i) => rowHtml(it, start + i + 1)).join(""); }

    const total = list.length, label = state.tab === "tagihan" ? "Tagihan" : "Pembayaran";
    $("pb-count").innerHTML = gagal ? "&nbsp;" : `Menampilkan <b>${total ? start + 1 : 0}–${start + slice.length}</b> dari <b>${total}</b> ${label}`;
    let nav = `<button type="button" data-p="${state.page - 1}" aria-label="Sebelumnya" ${state.page <= 1 ? "disabled" : ""}>${ic("chev-l")}</button>`;
    for (let p = 1; p <= pages; p++) nav += `<button type="button" data-p="${p}" class="${p === state.page && !state.all ? "on" : ""}" ${p === state.page && !state.all ? 'aria-current="page"' : ""}>${p}</button>`;
    nav += `<button type="button" class="pb-all${state.all ? " on" : ""}" data-all="1">Lihat semua data</button>`;
    nav += `<button type="button" data-p="${state.page + 1}" aria-label="Berikutnya" ${state.page >= pages ? "disabled" : ""}>${ic("chev-r")}</button>`;
    $("pb-pager").innerHTML = gagal ? "" : nav;
  }

  const PESAN_OFFLINE = "Koneksi terputus. Periksa jaringan Anda, lalu coba lagi.";
  let muatSeq = 0; // hanya hasil pemuatan terbaru yang dipakai

  async function refresh() {
    const seq = ++muatSeq;
    state.error = ""; state.rows = [];
    if (navigator.onLine === false) { // offline: langsung tampilkan error, tidak perlu menunggu fetch
      state.loading = false; state.error = PESAN_OFFLINE; render(); return;
    }
    state.loading = true; render();
    try {
      const rows = await muatData();
      if (!rows) return;
      if (seq !== muatSeq) return;
      state.rows = rows;
    } catch (e) {
      if (seq !== muatSeq) return;
      state.rows = [];
      state.error = navigator.onLine === false ? PESAN_OFFLINE
        : e.name === "AbortError" ? "Server terlalu lama merespons. Coba lagi."
        : "Periksa koneksi atau coba lagi.";
    }
    state.loading = false; render();
  }

  /* ---------------------------------------------------------
     Form Catat Pembayaran
       dp      : kamar belum punya DP -> pilih skema, catat DP, atur tenggat pelunasan
       bulanan : setelah DP -> Pelunasan (sebesar sisa tagihan periode berjalan)
       tahunan : setelah DP -> Cicilan (periode dikunci) / Pelunasan (3 dari 3)
  --------------------------------------------------------- */
  const kamarAktif = () => hitungKamar().filter((k) => k.utama);
  const curRoom = () => kamarAktif().find((k) => String(k.id) === $("f-kamar").value);
  const modeOf = (k) => (!k.adaDp ? "dp" : k.skema === "tahunan" ? "tahunan" : "bulanan");
  const show = (id, on) => { $(id).hidden = !on; };
  const skemaDp = () => $("f-skema").value; // "", "bulanan", "tahunan"
  // Sisa tagihan, sama seperti BE: bulanan dihitung per periode tagihan yang sedang berjalan,
  // tahunan dari total sewa dikurangi seluruh pembayaran terverifikasi.
  const sisaBE = (k) => {
    if (k.skema === "bulanan") { const t = hitungTagihan(k); return t ? t.sisa : 0; }
    return Math.max(0, k.total - ringkas(k).T);
  };
  const phSync = () => { // warna abu-abu bila masih placeholder
    $("f-kamar-w").classList.toggle("ph", !$("f-kamar").value);
    $("f-skema").closest(".pb-in").classList.toggle("ph", !$("f-skema").value);
  };

  function setDate(id, val) {
    $(id).value = val || "";
    const v = $(id + "-v"); v.textContent = val ? tglPendek(val) : "Pilih Tanggal"; v.classList.toggle("ph", !val);
  }
  function setJumlah(n, lock) { $("f-jumlah").value = n > 0 ? rpRapat(n) : ""; $("f-jumlah").readOnly = !!lock; }
  function setMetode(v) { $("f-metode").value = v; document.querySelectorAll("#f-metode-seg button").forEach((b) => b.setAttribute("aria-checked", b.dataset.metode === v)); }
  function fieldErr(id, msg) {
    const e = $(id); if (!e) return;
    e.textContent = msg || ""; e.hidden = !msg;
    const wrap = e.previousElementSibling; if (wrap) wrap.classList.toggle("err", !!msg);
  }
  function formError(msg) { $("f-error").textContent = msg || ""; $("f-error").hidden = !msg; }
  function clearErrors() { ["f-skema-err", "f-jenis-err", "f-tarif-err", "f-jumlah-help", "f-tgl-err", "f-tenggat-err", "f-metode-err"].forEach((id) => fieldErr(id, "")); $("f-metode-seg").classList.remove("err"); formError(""); }

  // Ringkasan "Sisa Tagihan Setelah ..." di bawah form
  function hitungRingkas() {
    const k = curRoom(), box = $("f-sum"), dp = F.mode === "dp";
    if (!k || F.selesai || (dp && !F.hitung)) { box.hidden = true; return; }
    const jml = parseRp($("f-jumlah").value);
    const sisa = dp ? Math.max(0, F.hitung.total - jml) : Math.max(0, sisaBE(k) - jml);
    const lunas = (dp ? F.hitung.total > 0 : true) && sisa <= 0;
    $("f-sum-l").textContent = dp ? "Sisa Tagihan Setelah DP" : "Sisa Tagihan Setelah Pembayaran";
    $("f-sum-v").textContent = rpRapat(sisa);
    $("f-sum-p").className = "pb-tag" + (lunas ? " pb-tag" : "");
    $("f-sum-p").innerHTML = lunas ? ic("tick") + "Lunas" : dp ? ic("clock") + "Menunggu Pelunasan" : ic("clock") + "Belum Lunas";
    box.hidden = false;
  }

  // Tenggat pelunasan dihitung BE (hitung-dp); tidak lagi dihitung di browser.
  function autoTenggat() {}

  // Kosongkan isian tampilan DP (tarif, DP minimum, jumlah, tenggat) -- dipakai saat form dibuka / kamar atau skema diganti
  function kosongkanDp() {
    F.hitung = null;
    $("f-tarif-l").textContent = "Tarif Kamar"; $("f-tarif").readOnly = true; $("f-tarif").value = ""; $("f-tarif").tabIndex = -1; // kolom otomatis dari BE: tidak ikut urutan fokus
    $("f-tarif-help").textContent = "Otomatis dari harga unit kamar";
    $("f-min-l").textContent = "Min. Rekomendasi DP (50%)"; $("f-min").value = "";
    $("f-jumlah-l").innerHTML = 'Jumlah DP <span class="req">*</span>'; setJumlah(0, false);
    setDate("f-tenggat", ""); $("f-tenggat").disabled = false; $("f-sum").hidden = true;
  }

  // Opsi "Tahunan" selalu bisa dipilih. Bila kamar belum punya harga tahunan, BE yang menolak
  // dan pesannya tampil di bawah kolom Skema Sewa.
  function opsiTahunan() {
    const o = $("f-skema").querySelector ? $("f-skema").querySelector('option[value="tahunan"]') : null; if (!o) return;
    o.disabled = false; o.textContent = "Tahunan";
  }

  // Mode DP: skema dipilih -> BE menghitung & menyimpan total sewa, DP minimum, tenggat (POST /api/penghunian/{id}/hitung-dp)
  async function onSkema() {
    phSync(); formError(""); fieldErr("f-jumlah-help", ""); fieldErr("f-skema-err", ""); fieldErr("f-tarif-err", "");
    const k = curRoom(), s = skemaDp();
    F.seq++; kosongkanDp();
    if (!k) { $("f-skema").value = ""; phSync(); formError("Pilih kamar terlebih dahulu."); return; }
    if (F.mode !== "dp" || !s) return;
    await hitungSkema(k, s);
  }

  // Frontend hanya mengirim skema sewa; total sewa, DP minimum, dan tenggat dihitung BE.
  async function hitungSkema(k, s) {
    const seq = ++F.seq;
    const tahunan = s === "tahunan";

    $("f-skema").disabled = true;
    $("f-simpan").disabled = true;

    try {
      // Backend menghitung total sewa, DP minimum, dan tenggat.
      // Frontend hanya mengirim skema sewa.
      const res = await kirim(
        `/api/penghunian/${k.utama.id}/hitung-dp`,
        "POST",
        { skema_sewa: s }
      );

      if (seq !== F.seq) return;

      const d = res.data || res;

      F.hitung = {
        total: num(d.total_sewa),
        dpMin: num(d.dp_minimum),
        tenggat: iso(d.tenggat_pelunasan || d.tenggat)
      };

      if (!F.hitung.total || !F.hitung.dpMin) {
        throw new Error("Data perhitungan sewa dari backend belum lengkap.");
      }

      $("f-tarif-l").textContent = tahunan
        ? "Total Tagihan Tahunan"
        : "Tarif Kamar";

      // Total sewa selalu berasal dari backend dan tidak dapat diedit.
      $("f-tarif").readOnly = true;
      $("f-tarif").tabIndex = -1;

      $("f-tarif").value = tahunan
        ? rpRapat(F.hitung.total)
        : `${rpRapat(F.hitung.total)} / bulan`;

      $("f-tarif-help").textContent = tahunan
        ? "Otomatis dihitung oleh sistem berdasarkan jumlah penghuni aktif."
        : "Otomatis dari harga unit kamar.";

      $("f-min-l").textContent = tahunan
        ? "DP Minimum Tahunan"
        : "Min. Rekomendasi DP (50%)";

      // DP minimum selalu mengikuti hasil backend.
      $("f-min").value = rpRapat(F.hitung.dpMin);

      const jumlahSekarang = parseRp($("f-jumlah").value);

      if (!jumlahSekarang) {
        setJumlah(F.hitung.dpMin, false);
      }

      // Tenggat diisi dari backend.
      setDate("f-tenggat", F.hitung.tenggat);

      // Tenggat tahunan boleh disesuaikan, bulanan tidak.
      $("f-tenggat").disabled = !tahunan;

      hitungRingkas();


    } catch (e) {
      if (seq !== F.seq) return;

      F.hitung = null;
      $("f-min").value = "";
      $("f-tarif").value = "";
      $("f-sum").hidden = true;

      const pesan = e.userMessage || e.message ||
        "Gagal menghitung skema sewa. Coba lagi.";

      fieldErr("f-skema-err", pesan);
      $("f-skema-err").scrollIntoView({
        block: "nearest",
        behavior: "smooth"
      });

    } finally {
      if (seq === F.seq) {
        $("f-skema").disabled = false;
        $("f-simpan").disabled = false;
      }
    }
  }

  // Simpan pembayaran: validasi di browser, aturan akhir ada di BE
  async function simpan() {
    if (F.saving) return;

    const v = validasi();
    if (!v) return;

    await kirimPembayaran(v);
  }

  // Dipanggil saat kamar berubah / form dibuka. Seluruh form selalu tampil; isinya terisi setelah kamar & skema dipilih.
  function onKamar() {
    const k = curRoom(); clearErrors(); phSync(); opsiTahunan(k);
    F.mode = k ? modeOf(k) : ""; F.selesai = false; F.seq++;
    const dp = !k || F.mode === "dp"; // sebelum kamar dipilih: tampilan DP (default)
    show("f-card-t", !F.pickable && !!k); show("f-kamar-w", F.pickable); show("f-info-dp", dp); show("f-info-ok", !!k && !dp);
    show("f-card-b", !!k); ["f-skema-g", "f-tarif-row", "f-jenis-g", "f-fields"].forEach((id) => show(id, true));
    show("f-jenis-sel", !dp); show("f-jenis-fix", dp); show("f-tenggat-wrap", dp); show("f-periode-g", F.mode === "tahunan");
    $("f-simpan").disabled = false;
    setDate("f-tgl", todayIso()); setMetode(""); F.tenggatManual = false;
    if (dp) {
      $("f-skema").disabled = false; $("f-skema").value = ""; phSync(); // default: "Pilih skema"
      kosongkanDp();
      $("f-jenis-help").textContent = "Setelah DP tersimpan, jenis tagihan berikutnya yang terbuka adalah Pelunasan atau Cicilan (cicilan hanya untuk sewa tahunan).";
      if (k) { $("f-card-t").textContent = `Kamar ${fmtNo(k.no)} - ${k.utama.nama}`; $("f-card-b").textContent = "Pencatatan DP"; }
      hitungRingkas(); return;
    }
    $("f-card-t").textContent = `Kamar ${fmtNo(k.no)} - ${k.utama.nama}`;
    $("f-card-b").textContent = F.mode === "tahunan" ? "Skema Tahunan" : "Skema Bulanan";
    setDate("f-tenggat", "");

    // Sudah ada DP: skema sudah tersimpan di BE dan tidak bisa diganti
    const r = ringkas(k), tahunan = r.tahunan, sisa = sisaBE(k), totalDpAmt = dpRows(k).reduce((s, b) => s + b.jumlah, 0);
    $("f-skema").disabled = true; $("f-skema").value = F.mode; phSync();
    $("f-jumlah-l").innerHTML = 'Jumlah Pembayaran <span class="req">*</span>';
    $("f-info-ok-t").textContent = `DP ${rpRapat(totalDpAmt)} pada ${tglPanjang(dpDate(k))}`;
    $("f-tarif").readOnly = true;
    $("f-tarif-l").textContent = tahunan ? "Total Tagihan Tahunan" : "Jumlah Tagihan";
    $("f-tarif").value = tahunan ? rpRapat(k.total) : `${rpRapat(k.total)} / bulan`;
    $("f-tarif-help").textContent = tahunan ? "Otomatis dari harga tahunan kamar" : "Otomatis dari harga kamar";
    $("f-min-l").textContent = "Sisa Tagihan";
    $("f-min").value = rpRapat(sisa);
    $("f-jenis-help").textContent = tahunan
      ? "Sewa tahunan: Cicilan (sebagian) atau Pelunasan (sama dengan sisa tagihan)."
      : "Sewa bulanan: Pelunasan sebesar sisa tagihan.";

    if (sisa <= 0) { // BE menolak pembayaran bila tagihan sudah lunas (BILL_ALREADY_PAID)
      F.selesai = true; show("f-jenis-g", false); show("f-fields", false); $("f-simpan").disabled = true;
      formError("Tagihan kamar ini sudah lunas. Tidak ada pembayaran yang perlu dicatat."); hitungRingkas(); return;
    }
    const opts = tahunan ? ["cicilan", "pelunasan"] : ["pelunasan"]; // jenis yang diterima BE; cicilan hanya tahunan
    const nama = { cicilan: "Cicilan", pelunasan: "Pelunasan" };
    $("f-jenis").innerHTML = opts.map((j) => `<option value="${j}">${nama[j]}</option>`).join("");
    onJenis();
  }

  // Mode setelah DP: jenis berubah -> jumlah awal, periode cicilan (hanya teks)
  function onJenis() {
    const k = curRoom(); if (!k || F.mode === "dp") return;
    const r = ringkas(k), j = $("f-jenis").value, sisa = sisaBE(k);
    if (j === "cicilan") {
      const dpAmt = dpRows(k).reduce((s, b) => s + b.jumlah, 0);
      setJumlah(Math.max(1, Math.min(sisa - 1, Math.round((k.total - dpAmt) / CICILAN_N))), false); // saran saja; BE: cicilan harus lebih kecil dari sisa
      $("f-periode").value = `${r.cicilan + 1} dari ${CICILAN_N}`;
    } else { setJumlah(sisa, true); $("f-periode").value = `${CICILAN_N} dari ${CICILAN_N}`; } // BE: pelunasan = sisa tagihan
    fieldErr("f-jumlah-help", ""); hitungRingkas();
  }

  // opts: { kamarId }
  function bukaForm(opts = {}) {
    F.saving = false; F.hitung = null; F.seq++;
    $("f-simpan").disabled = false; $("f-simpan").textContent = "Simpan";
    // Default: "Pilih kamar yang akan melakukan pembayaran"; klik untuk melihat daftar kamar
    $("f-kamar").innerHTML = '<option value="" disabled selected>Pilih kamar yang akan melakukan pembayaran</option>' +
      kamarAktif().map((r) => `<option value="${r.id}">Kamar ${esc(fmtNo(r.no))} - ${esc(r.utama.nama)}</option>`).join("");
    F.pickable = !opts.kamarId; // dari tombol "Bayar" kamar sudah pasti
    const awal = opts.kamarId ? kamarAktif().find((r) => r.id === opts.kamarId) : null;
    $("f-kamar").value = awal ? String(awal.id) : "";
    onKamar();
    $("pb-form").hidden = false; document.body.style.overflow = "hidden";
  }
  function tutupForm() { $("pb-form").hidden = true; $("pb-peringatan").hidden = true; document.body.style.overflow = ""; }

  function validasi() {
    clearErrors();
    const k = curRoom(); let ok = true;
    if (!k) { formError("Pilih kamar terlebih dahulu."); return null; }
    const dp = F.mode === "dp", skema = dp ? skemaDp() : F.mode;
    if (dp && !skema) { formError("Pilih skema sewa terlebih dahulu."); return null; }
    if (dp && !F.hitung) {
      if (skema === "tahunan" && !$("f-tarif").readOnly) fieldErr("f-tarif-err", "Isi total tagihan tahunan terlebih dahulu.");
      else formError("Skema sewa belum dihitung. Pilih ulang skema sewa.");
      return null;
    }
    const v = { k, dp, skema, jenis: dp ? "dp" : $("f-jenis").value, metode: $("f-metode").value, tanggal: $("f-tgl").value, jumlah: parseRp($("f-jumlah").value), tenggat: dp && skema === "tahunan" ? $("f-tenggat").value : "", total: dp ? F.hitung.total : k.total, dpMin: dp ? F.hitung.dpMin : 0 };
    if (dp) {
      if (v.jumlah > v.total) { fieldErr("f-jumlah-help", "Jumlah DP tidak boleh melebihi total sewa."); ok = false; }
      else if (v.jumlah > 0 && v.jumlah < v.dpMin) { fieldErr("f-jumlah-help", `Jumlah DP tidak boleh kurang dari DP minimum (${rp(v.dpMin)}).`); ok = false; }
      if (skema === "tahunan") {
        if (!v.tenggat) { fieldErr("f-tenggat-err", "Tenggat pelunasan wajib diisi."); ok = false; }
        else if (k.utama.mulai && v.tenggat < k.utama.mulai) { fieldErr("f-tenggat-err", "Tenggat pelunasan tidak boleh sebelum tanggal mulai sewa."); ok = false; }
      }
    } else {
      const sisa = sisaBE(k);
      if (!v.jenis) { fieldErr("f-jenis-err", "Pilih jenis tagihan."); ok = false; }
      else if (v.jumlah > sisa) { fieldErr("f-jumlah-help", `Jumlah melebihi sisa tagihan (${rp(sisa)}).`); ok = false; }
      else if (v.jenis === "pelunasan" && v.jumlah !== sisa) { fieldErr("f-jumlah-help", `Jumlah pelunasan harus sama dengan sisa tagihan (${rp(sisa)}).`); ok = false; }
      else if (v.jenis === "cicilan" && v.jumlah >= sisa) { fieldErr("f-jumlah-help", "Jika membayar seluruh sisa tagihan, pilih jenis Pelunasan."); ok = false; }
    }
    if (!v.jumlah && $("f-jumlah-help").hidden) { fieldErr("f-jumlah-help", "Jumlah pembayaran wajib diisi."); ok = false; }
    if (!v.metode) { fieldErr("f-metode-err", "Pilih metode pembayaran."); $("f-metode-seg").classList.add("err"); ok = false; }
    if (!v.tanggal) { fieldErr("f-tgl-err", "Tanggal bayar wajib diisi."); ok = false; }
    return ok ? v : null;
  }

  // DP, cicilan, dan pelunasan semuanya lewat POST /api/pembayaran (aturan bisnis & validasi akhir ada di BE)
  async function kirimPembayaran(v) {
    const u = v.k.utama, sisaSebelum = v.dp ? v.total : sisaBE(v.k);
    F.saving = true; $("f-simpan").disabled = true; $("f-simpan").textContent = "Menyimpan…";
    try {
      const data = { id_penghunian: u.id, jenis_pembayaran: v.jenis, tanggal_bayar: v.tanggal, jumlah: v.jumlah, metode_pembayaran: v.metode };
      if (v.dp && v.skema === "tahunan" && v.tenggat) data.tenggat_pelunasan = v.tenggat;
      await kirim("/api/pembayaran", "POST", data);
      tutupForm(); state.tab = "tagihan"; syncTabs(); syncFilterUI();
      const lunas = sisaSebelum - v.jumlah <= 0;
      toast(v.dp ? `DP kamar ${fmtNo(v.k.no)} berhasil dicatat atas nama ${u.nama}.` + (lunas ? "" : " Menunggu pelunasan.")
        : lunas ? `Pembayaran tersimpan. Kamar ${fmtNo(v.k.no)} sudah lunas.` : `Pembayaran kamar ${fmtNo(v.k.no)} berhasil dicatat atas nama ${u.nama}.`);
      refresh();
    } catch (e) {
      formError(e.userMessage || "Gagal menyimpan pembayaran. Coba lagi.");
      if (e.code === "DP_ALREADY_EXISTS" || e.code === "BILL_ALREADY_PAID") refresh(); // data di layar sudah usang: muat ulang
    } finally {
      F.saving = false; $("f-simpan").disabled = false; $("f-simpan").textContent = "Simpan";
    }
  }

  /* ---------------------------------------------------------
     Detail (isi modal detail pembayaran menyusul)
  --------------------------------------------------------- */
  const tutupDetail = () => { $("pb-det").hidden = true; document.body.style.overflow = ""; };

  /* ---------------------------------------------------------
     Event
  --------------------------------------------------------- */
  function syncTabs() { document.querySelectorAll(".pb-tab").forEach((b) => b.setAttribute("aria-selected", b.dataset.tab === state.tab)); }
  const tutupMenu = () => document.querySelectorAll(".pb-menu").forEach((m) => m.remove());

  document.querySelectorAll(".pb-tab").forEach((b) => b.addEventListener("click", () => { state.tab = b.dataset.tab; state.page = 1; syncTabs(); syncFilterUI(); render(); }));
  // Panel Filter berisi dropdown (default tertutup; badge muncul bila ada filter yang diterapkan).
  // Isi panel mengikuti tab: Tagihan = Status/Skema/Lantai, Riwayat = Periode/Skema/Lantai.
  (function initFilterPanel() {
    const panel = $("fp-panel"), btn = $("fp-toggle"), wrap = $("fp-wrap");
    const sel = (k) => $("fp-" + k);
    const TEKS = {
      tagihan: ["Tagihan", "Atur filter di bawah untuk melihat perubahan data tagihan"],
      riwayat: ["Riwayat Pembayaran", "Atur filter di bawah untuk melihat perubahan data riwayat pembayaran"],
    };
    const setOpen = (open) => { panel.hidden = !open; btn.setAttribute("aria-expanded", String(open)); };
    const syncSelects = () => FILTER_KEYS.forEach((k) => { sel(k).value = applied[k]; }); // kembalikan ke filter yang berlaku
    const syncBadge = () => {
      const n = keysAktif().filter((k) => applied[k] !== "").length;
      $("fp-badge").textContent = n; $("fp-badge").hidden = n === 0;
      btn.classList.toggle("has-filter", n > 0);
    };
    syncFilterUI = () => {
      $("fp-g-status").hidden = state.tab !== "tagihan";
      $("fp-g-periode").hidden = state.tab !== "riwayat";
      $("fp-title").textContent = TEKS[state.tab][0]; $("fp-sub").textContent = TEKS[state.tab][1];
      syncSelects(); syncBadge(); setOpen(false);
    };
    const apply = () => {
      keysAktif().forEach((k) => { applied[k] = sel(k).value; });
      syncBadge(); setOpen(false); state.page = 1; render();
    };
    const reset = () => {
      FILTER_KEYS.forEach((k) => { applied[k] = ""; sel(k).value = ""; });
      syncBadge(); setOpen(false); state.page = 1; render();
    };
    btn.addEventListener("click", (e) => { e.stopPropagation(); const buka = panel.hidden; syncSelects(); setOpen(buka); });
    $("fp-close").addEventListener("click", () => { syncSelects(); setOpen(false); });
    $("fp-apply").addEventListener("click", apply);
    $("fp-reset").addEventListener("click", reset);
    document.addEventListener("click", (e) => { if (!panel.hidden && !wrap.contains(e.target)) { syncSelects(); setOpen(false); } });
    document.addEventListener("keydown", (e) => { if (e.key === "Escape" && !panel.hidden) { syncSelects(); setOpen(false); btn.focus(); } });
    syncFilterUI();
  })();
  $("pb-q").addEventListener("input", () => { state.page = 1; render(); });
  $("pb-pager").addEventListener("click", (e) => {
    const all = e.target.closest("button[data-all]");
    if (all) { state.all = true; state.page = 1; render(); return; }
    const b = e.target.closest("button[data-p]");
    if (b && !b.disabled) { state.all = false; state.page = Number(b.dataset.p); render(); }
  });
  $("pb-state").addEventListener("click", (e) => { if (e.target.id === "pb-retry") refresh(); });

  // Header "Catat Pembayaran": kosong kamar / tanpa penghuni -> pesan, form tidak dibuka
  $("pb-catat").addEventListener("click", () => {
    if (!kamarAktif().length) return toast("Tambahkan penghuni terlebih dahulu.", true);
    bukaForm();
  });

  $("pb-rows").addEventListener("click", (e) => {
    const b = e.target.closest("button[data-act]");
    if (!b) return;
    if (b.disabled) return; // tagihan lunas: tombol Bayar nonaktif
    const it = state.view.find((x) => x.key === b.closest("tr").dataset.key);
    if (!it) return;
    const act = b.dataset.act;
    if (act === "bayar") bayar(it);
    // "detail" (tab Riwayat): tombol tampil, isi detail dikerjakan kemudian.
  });
  document.addEventListener("click", tutupMenu);

  function bayar(it) { bukaForm({ kamarId: it.id_kamar }); } // belum ada DP -> form DP; sudah ada -> form pelunasan/cicilan

  // Form
  $("f-kamar").addEventListener("change", onKamar);
  $("f-skema").addEventListener("change", onSkema);
  $("f-kamar").addEventListener("change", phSync);

  $("f-jenis").addEventListener("change", () => { fieldErr("f-jenis-err", ""); onJenis(); });
  [["f-jumlah", () => fieldErr("f-jumlah-help", "")]].forEach(([id, extra]) => {
    $(id).addEventListener("input", (e) => {
      if (e.target.readOnly) return;
      const d = e.target.value.replace(/\D/g, ""); e.target.value = d ? rpRapat(Number(d)) : ""; extra(); hitungRingkas();
    });
  });
  document.querySelectorAll("#f-metode-seg button").forEach((b) => b.addEventListener("click", () => { setMetode(b.dataset.metode); fieldErr("f-metode-err", ""); $("f-metode-seg").classList.remove("err"); }));
  ["f-tgl", "f-tenggat"].forEach((id) => {
    $(id).addEventListener("change", (e) => {
      setDate(id, e.target.value);
      if (id === "f-tgl") { fieldErr("f-tgl-err", ""); autoTenggat(); } else { F.tenggatManual = true; fieldErr("f-tenggat-err", ""); }
    });
    $(id).addEventListener("click", (e) => { try { e.target.showPicker(); } catch (_) {} });
  });
  $("f-simpan").addEventListener("click", () => simpan(false));
  $("f-x").addEventListener("click", tutupForm);
  $("f-batal").addEventListener("click", tutupForm);
  $("pb-form").addEventListener("mousedown", (e) => { if (e.target === $("pb-form")) tutupForm(); });

  // Dialog peringatan DP
  $("w-ubah").addEventListener("click", () => { $("pb-peringatan").hidden = true; $("f-jumlah").focus(); });
  $("w-lanjut").addEventListener("click", () => { $("pb-peringatan").hidden = true; simpan(true); });

  // Detail
  $("d-x").addEventListener("click", tutupDetail); $("d-tutup").addEventListener("click", tutupDetail);
  $("pb-det").addEventListener("mousedown", (e) => { if (e.target === $("pb-det")) tutupDetail(); });

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    if (!$("pb-peringatan").hidden) $("pb-peringatan").hidden = true;
    else if (!$("pb-form").hidden) tutupForm();
    else if (!$("pb-det").hidden) tutupDetail();
  });

  // Kondisi koneksi
  window.addEventListener("offline", () => {
    toast("Koneksi terputus. Perubahan belum bisa disimpan.", true);
    if (state.loading) { // putus di tengah loading -> langsung jadi error state
      muatSeq++; state.loading = false; state.rows = []; state.error = PESAN_OFFLINE; render();
    }
  });
  window.addEventListener("online", () => { toast("Koneksi tersambung kembali."); if (state.error) refresh(); });

  // Dibuka dari tombol "Catat DP sekarang" (halaman penghuni): /pembayaran?kamar=..&penghunian=..&jenis=DP
  function dariParameter() {
    const q = new URLSearchParams(location.search);
    if (!q.has("kamar") && !q.has("penghunian")) return;
    let idKamar = Number(q.get("kamar")) || 0;
    if (!idKamar && q.get("penghunian")) idKamar = state.rows.find((r) => r.id === Number(q.get("penghunian")))?.id_kamar || 0;
    history.replaceState(null, "", location.pathname); // supaya muat ulang halaman tidak membuka form lagi
    const k = kamarAktif().find((x) => x.id === idKamar);
    if (!k) return toast("Tambahkan penghuni terlebih dahulu.", true); // kamar tanpa penghuni aktif: form tidak dibuka
    bukaForm({ kamarId: k.id });
  }


  refresh().then(dariParameter);
})();