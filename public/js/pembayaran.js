// Halaman Pembayaran & Tagihan  (resources/views/pembayaran.blade.php)
// User story 5.2a: pencatatan pembayaran DP, cicilan, dan pelunasan.
//
// Endpoint yang dipakai:
//   GET   /api/penghunian                 -> penghunian + relasi kamar, penghuni, pembayaran     (SUDAH ADA)
//   POST  /api/penghunian/{id}/dp         -> catat DP + simpan skema/total/tenggat (sekali per kamar)
//   GET   /api/pengaturan-sewa            -> DP minimum tahunan
//   PUT   /api/penghunian/{id}/tenggat    -> ubah tenggat pelunasan                              (SUDAH ADA)
//   POST  /api/pembayaran                 -> pelunasan bulan pertama / tagihan bulanan / cicilan / pelunasan
//   PATCH /api/pembayaran/{id}/status     -> konfirmasi pembayaran {status:"lunas"}              (BELUM ADA - ada di kontrak OpenAPI)
//
// "Tagihan" belum punya tabel/endpoint sendiri, jadi daftar tagihan DIHITUNG di sini dari data penghunian + pembayaran.
// Ganti fungsi hitungTagihan() bila backend sudah menyediakan GET /api/tagihan.
(function () {
  "use strict";

  const PER_PAGE = 5; // desain: "Menampilkan 1–5 dari 12 tagihan"
  const state = { rows: [], tab: "tagihan", page: 1, loading: true, error: "", view: [], dpTahunan: 300000 };
  const F = { saving: false, mode: "", tenggatManual: false, pickable: false };
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
    const opts = { method, headers: { Accept: "application/json" } };
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
      const first = json.errors && Object.values(json.errors)[0];
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
      id: p.id_penghunian, id_kamar: p.id_kamar, no_kamar: no, lantai: Number(k.lantai) || lantaiDariNomor(no), harga: num(k.harga),
      nama: p.penghuni?.nama_penghuni || "-", utama: flagUtama(p.penyewa_utama) || flagUtama(p.is_penyewa_utama),
      status: String(p.status || "aktif").toLowerCase(), mulai: iso(p.tanggal_mulai),
      skema: String(p.skema_sewa || "").toLowerCase(), total: num(p.total_sewa), dpMin: num(p.dp_minimum), tenggat: iso(p.tenggat_pelunasan),
      bayar: (p.pembayaran || []).map((b) => ({
        id: b.id_pembayaran, tanggal: iso(b.tanggal_bayar), jumlah: num(b.jumlah),
        metode: String(b.metode_pembayaran || "").replace(/^DP\s*-\s*/i, ""), jenis: jenisOf(b), periode: Number(b.periode_cicilan) || 0, status: statusBayarOf(b), ket: b.keterangan || "",
      })),
    };
  }

  async function muatData() {
    const res = await fetch("/api/penghunian", { headers: { Accept: "application/json" } });
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = (await res.json()).data;
    if (!Array.isArray(data)) throw new Error("Format data tidak dikenali");
    return data.map(normalize);
  }

  // Kelompokkan per kamar. Pembayaran dicatat atas nama penyewa utama; kamar 1 orang -> orang itu.
  function hitungKamar() {
    const map = new Map();
    state.rows.forEach((r) => {
      if (!map.has(r.id_kamar)) map.set(r.id_kamar, { id: r.id_kamar, no: r.no_kamar, lantai: r.lantai, harga: r.harga, semua: [] });
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

  // Satu tagihan per kamar berpenyewa utama. Bulanan: periode berjalan (bulan pertama = bulan DP). Tahunan: total sewa setahun.
  function hitungTagihan(k) {
    if (!k.utama || !k.total) return null;
    const r = ringkas(k), today = todayIso(), pending = k.bayarAktif.find((b) => b.status === "menunggu");
    if (r.tahunan) {
      return {
        tipe: "tagihan", key: "t" + k.id, kamar: k, nama: k.utama.nama, jenis: "Sewa Tahunan", bulan: today.slice(0, 7),
        periode: `Tahun ${(k.utama.mulai || dpDate(k) || today).slice(0, 4)}`,
        jumlah: k.total, paid: r.T, nSudah: r.n, sisa: r.sisa, status: r.lunas ? "sudah" : pending ? "menunggu" : "belum", pendingId: pending?.id,
      };
    }
    const first = dpDate(k) || k.utama.mulai || today, idx = (d) => Number(d.slice(0, 4)) * 12 + Number(d.slice(5, 7)) - 1;
    const base = idx(first), dueKe = idx(today) - base + 1, openKe = r.paidPeriods + 1, sebagian = r.sisa < r.H;
    // Periode terbuka tampil bila sudah jatuh tempo / sebagian dibayar; jika sudah terkejar, tampilkan periode terakhir yang lunas.
    const ke = sebagian || openKe <= dueKe || !r.paidPeriods ? openKe : r.paidPeriods, paid = ke === openKe ? r.H - r.sisa : r.H, m = base + ke - 1;
    const key = `${Math.floor(m / 12)}-${pad((m % 12) + 1)}`;
    return {
      tipe: "tagihan", key: "t" + k.id, kamar: k, nama: k.utama.nama, jenis: "Sewa Kamar", bulan: key, periode: periodeLabel(key),
      jumlah: r.H, paid, nSudah: r.n, sisa: Math.max(0, r.H - paid), status: paid >= r.H ? "sudah" : pending ? "menunggu" : "belum", pendingId: pending?.id,
    };
  }

  function semuaItem() {
    const kamar = hitungKamar(), byId = new Map(kamar.map((k) => [k.id, k]));
    const tagihan = kamar.map(hitungTagihan).filter(Boolean);
    const riwayat = state.rows.flatMap((r) => r.bayar.map((b) => ({
      tipe: "riwayat", key: "p" + b.id, kamar: byId.get(r.id_kamar), nama: r.nama, jenis: jenisLabel(b), pay: b,
      bulan: b.tanggal.slice(0, 7), periode: b.tanggal ? periodeLabel(b.tanggal.slice(0, 7)) : "-", jumlah: b.jumlah, status: b.status,
    }))).sort((a, b) => (b.pay.tanggal || "").localeCompare(a.pay.tanggal || "") || b.pay.id - a.pay.id);
    return { kamar, tagihan, riwayat };
  }

  /* ---------------------------------------------------------
     Render: ringkasan, filter, tabel
  --------------------------------------------------------- */
  function renderStats(tagihan) {
    const total = tagihan.reduce((s, t) => s + t.jumlah, 0);
    const masuk = tagihan.reduce((s, t) => s + Math.min(t.paid, t.jumlah), 0);
    const nMasuk = tagihan.reduce((s, t) => s + t.nSudah, 0);
    const belum = tagihan.filter((t) => t.status !== "sudah");
    const rate = total ? Math.round((masuk / total) * 100) : 0;
    const kartu = (ico, lbl, val, sub) => `<div class="pb-stat"><span class="ico">${ic(ico)}</span><span class="lbl">${lbl}</span><span class="val">${val}</span>${sub}</div>`;
    $("pb-stats").innerHTML =
      kartu("doc", "Total Tagihan Bulan Ini", rp(total), `<span class="sub">${tagihan.length} tagihan</span>`) +
      kartu("bag", "Total Pembayaran Masuk", rp(masuk), `<span class="sub">${nMasuk} pembayaran</span>`) +
      kartu("clock", "Tagihan Belum Dibayar", rp(belum.reduce((s, t) => s + t.sisa, 0)), `<span class="sub">${belum.length} tagihan</span>`) +
      kartu("trend", "Tingkat Pembayaran", rate + "%", `<div class="pb-bar" role="progressbar" aria-valuenow="${rate}" aria-valuemin="0" aria-valuemax="100"><i style="width:${rate}%"></i></div>`);
  }

  function isiFilter(items) {
    const fill = (id, first, opts) => {
      const el = $(id), cur = el.value;
      el.innerHTML = `<option value="">${first}</option>` + opts.map(([v, l]) => `<option value="${esc(v)}">${esc(l)}</option>`).join("");
      if (opts.some(([v]) => String(v) === cur)) el.value = cur;
    };
    const bulan = [...new Set([...items.tagihan, ...items.riwayat].map((i) => i.bulan).filter(Boolean))].sort().reverse();
    fill("pb-f-bulan", "Semua Bulan", bulan.map((b) => [b, periodeLabel(b)]));
    fill("pb-f-kamar", "Semua Kamar", items.kamar.map((k) => [k.id, "Kamar " + fmtNo(k.no)]));
  }

  const pill = (st) => ({
    belum: `<span class="pb-pill pb-pill--belum">${ic("warn")}Belum Dibayar</span>`,
    sudah: `<span class="pb-pill pb-pill--sudah">${ic("tick")}Sudah Dibayar</span>`,
    menunggu: `<span class="pb-pill pb-pill--menunggu">${ic("clock")}<span>Menunggu<br>Konfirmasi</span></span>`,
    ditolak: `<span class="pb-pill pb-pill--ditolak">Ditolak</span>`,
  }[st]);

  function aksiHtml(it) {
    const btn = it.status === "belum" && it.tipe === "tagihan" ? '<button type="button" class="pb-act pb-act--main" data-act="bayar">Bayar</button>'
      : it.status === "menunggu" ? '<button type="button" class="pb-act" data-act="konfirmasi">Konfirmasi</button>'
      : '<button type="button" class="pb-act" data-act="detail">Detail</button>';
    return `<div class="pb-aksi">${btn}<button type="button" class="pb-dots" data-act="menu" aria-label="Menu lainnya" aria-haspopup="menu">${ic("dots")}</button></div>`;
  }

  function rowHtml(it, no) {
    const k = it.kamar;
    return `<tr data-key="${esc(it.key)}"><td class="no">${no}</td><td class="nama">${esc(it.nama)}</td>
      <td class="kamar"><b>${esc(fmtNo(k.no))}</b><small>Lt. ${esc(k.lantai)}</small></td>
      <td class="jenis"><div>${ic("doc")}<span>${esc(it.jenis)}</span></div></td>
      <td class="per"><span>${esc(it.periode)}</span></td><td class="jml"><span>${rp(it.jumlah)}</span></td>
      <td>${pill(it.status)}</td><td>${aksiHtml(it)}</td></tr>`;
  }

  function filtered(items) {
    const q = $("pb-q").value.trim().toLowerCase(), bln = $("pb-f-bulan").value, st = $("pb-f-status").value, km = $("pb-f-kamar").value;
    return (state.tab === "tagihan" ? items.tagihan : items.riwayat).filter((i) => {
      if (q && !(i.nama.toLowerCase().includes(q) || String(i.kamar.no).toLowerCase().includes(q) || fmtNo(i.kamar.no).includes(q) || (i.pay?.ket || "").toLowerCase().includes(q))) return false;
      return (!bln || i.bulan === bln) && (!st || i.status === st) && (!km || String(i.kamar.id) === km);
    });
  }

  function render() {
    const box = $("pb-state"), items = semuaItem();
    isiFilter(items); renderStats(items.tagihan);
    const list = filtered(items); state.view = list;
    const pages = Math.max(1, Math.ceil(list.length / PER_PAGE));
    state.page = Math.min(state.page, pages);
    const start = (state.page - 1) * PER_PAGE, slice = list.slice(start, start + PER_PAGE);

    if (state.loading) { $("pb-rows").innerHTML = ""; box.hidden = false; box.innerHTML = "<b>Memuat data…</b>Mohon tunggu sebentar."; }
    else if (state.error) {
      $("pb-rows").innerHTML = ""; box.hidden = false;
      box.innerHTML = `<b>Data pembayaran gagal dimuat</b>${esc(state.error)}<br><button type="button" id="pb-retry">Coba lagi</button>`;
    } else if (!slice.length) {
      $("pb-rows").innerHTML = ""; box.hidden = false;
      box.innerHTML = state.rows.length
        ? (state.tab === "tagihan" ? "<b>Belum ada tagihan</b>Tidak ada tagihan yang cocok dengan filter." : "<b>Belum ada pembayaran</b>Pembayaran yang dicatat akan tampil di sini.")
        : '<b>Belum ada data penghuni</b>Tambahkan penghuni terlebih dahulu di menu Data Penyewa &amp; Arsip.';
    } else { box.hidden = true; $("pb-rows").innerHTML = slice.map((it, i) => rowHtml(it, start + i + 1)).join(""); }

    const total = list.length, label = state.tab === "tagihan" ? "tagihan" : "pembayaran";
    $("pb-info").innerHTML = state.loading || state.error ? "&nbsp;" : `Menampilkan <b>${total ? start + 1 : 0}–${start + slice.length}</b> dari <b>${total}</b> ${label}`;
    let nav = `<button type="button" data-p="${state.page - 1}" aria-label="Sebelumnya" ${state.page <= 1 ? "disabled" : ""}>${ic("chev-l")}</button>`;
    for (let p = 1; p <= pages; p++) nav += `<button type="button" data-p="${p}" class="${p === state.page ? "on" : ""}" ${p === state.page ? 'aria-current="page"' : ""}>${p}</button>`;
    nav += `<button type="button" data-p="${state.page + 1}" aria-label="Berikutnya" ${state.page >= pages ? "disabled" : ""}>${ic("chev-r")}</button>`;
    $("pb-pager").innerHTML = nav;
  }

  async function refresh() {
    state.loading = true; state.error = ""; render();
    try { state.rows = await muatData(); }
    catch (e) {
      state.rows = [];
      state.error = navigator.onLine === false ? "Koneksi terputus. Periksa jaringan Anda, lalu coba lagi." : "Periksa koneksi atau coba lagi.";
    }
    state.loading = false; render();
  }

  /* ---------------------------------------------------------
     Form Catat Pembayaran
       dp      : kamar belum punya DP -> pilih skema, catat DP, atur tenggat pelunasan
       bulanan : setelah DP -> Pelunasan bulan pertama / Tagihan bulanan
       tahunan : setelah DP -> Cicilan (periode dikunci) / Pelunasan (3 dari 3)
  --------------------------------------------------------- */
  const kamarAktif = () => hitungKamar().filter((k) => k.utama);
  const curRoom = () => kamarAktif().find((k) => String(k.id) === $("f-kamar").value);
  const modeOf = (k) => (!k.adaDp ? "dp" : k.skema === "tahunan" ? "tahunan" : "bulanan");
  const show = (id, on) => { $(id).hidden = !on; };
  const skemaDp = () => ($("f-skema").value === "tahunan" ? "tahunan" : "bulanan");
  const totalDp = (k) => (skemaDp() === "tahunan" ? parseRp($("f-tarif").value) : k.harga);
  const dpMin = (k) => (skemaDp() === "tahunan" ? state.dpTahunan : k.harga * 0.5);

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
  function clearErrors() { ["f-jenis-err", "f-tarif-err", "f-jumlah-help", "f-tgl-err", "f-tenggat-err", "f-metode-err"].forEach((id) => fieldErr(id, "")); $("f-metode-seg").classList.remove("err"); formError(""); }

  // Ringkasan "Sisa Tagihan Setelah ..." di bawah form
  function hitungRingkas() {
    const k = curRoom(), box = $("f-sum");
    if (!k || F.selesai) { box.hidden = true; return; }
    const jml = parseRp($("f-jumlah").value), dp = F.mode === "dp";
    const sisa = dp ? Math.max(0, totalDp(k) - jml) : Math.max(0, ringkas(k).sisa - jml);
    const lunas = (dp ? totalDp(k) > 0 : true) && sisa <= 0;
    $("f-sum-l").textContent = dp ? "Sisa Tagihan Setelah DP" : "Sisa Tagihan Setelah Pembayaran";
    $("f-sum-v").textContent = rpRapat(sisa);
    $("f-sum-p").className = "pb-tag" + (lunas ? " pb-tag--soft" : "");
    $("f-sum-p").innerHTML = lunas ? ic("tick") + "Lunas" : dp ? ic("clock") + "Menunggu Pelunasan" : ic("tick") + "Belum Lunas";
    box.hidden = false;
  }

  // Tenggat pelunasan otomatis (bisa diubah): bulanan = tanggal DP + 1 bulan, tahunan = tanggal masuk (atau tanggal DP) + 2 bulan
  function autoTenggat() {
    const k = curRoom(); if (F.mode !== "dp" || F.tenggatManual || !k) return;
    const tgl = $("f-tgl").value;
    setDate("f-tenggat", !tgl ? "" : skemaDp() === "tahunan" ? tambahBulan(k.utama.mulai || tgl, 2) : tambahBulan(tgl, 1));
  }

  // Mode DP: skema berubah -> tarif, DP minimum, jumlah awal, tenggat
  function onSkema() {
    const k = curRoom(); if (!k || F.mode !== "dp") return;
    const tahunan = skemaDp() === "tahunan";
    $("f-tarif-l").textContent = tahunan ? "Total Tagihan Tahunan" : "Tarif Kamar";
    $("f-tarif").readOnly = !tahunan; $("f-tarif").value = tahunan ? "" : `${rpRapat(k.harga)} / bulan`;
    $("f-tarif-help").textContent = tahunan ? "Diisi oleh pemilik" : "Otomatis dari harga unit kamar";
    $("f-min-l").textContent = tahunan ? "Min. DP Tahunan" : "Min. Rekomendasi DP (50%)";
    $("f-min").value = rpRapat(dpMin(k));
    setJumlah(dpMin(k), false); fieldErr("f-tarif-err", ""); fieldErr("f-jumlah-help", "");
    autoTenggat(); hitungRingkas();
  }

  // Dipanggil saat kamar berubah / form dibuka
  function onKamar() {
    const k = curRoom(); clearErrors();
    F.mode = k ? modeOf(k) : ""; F.selesai = false;
    const dp = F.mode === "dp";
    show("f-card-t", !F.pickable); show("f-kamar-w", F.pickable); show("f-info-dp", dp); show("f-info-ok", !!k && !dp);
    ["f-skema-g", "f-tarif-row", "f-jenis-g", "f-fields"].forEach((id) => show(id, !!k));
    show("f-jenis-sel", !dp); show("f-jenis-fix", dp); show("f-tenggat-wrap", dp); show("f-periode-g", F.mode === "tahunan");
    $("f-simpan").disabled = !k;
    if (!k) { hitungRingkas(); return; }
    $("f-card-t").textContent = `Kamar ${fmtNo(k.no)} - ${k.utama.nama}`;
    $("f-card-b").textContent = dp ? "Pencatatan DP" : F.mode === "tahunan" ? "Skema Tahunan" : "Skema Bulanan";
    setDate("f-tgl", todayIso()); setMetode(""); F.tenggatManual = false; setDate("f-tenggat", "");

    if (dp) {
      $("f-skema").disabled = false; $("f-skema").value = "bulanan";
      $("f-jumlah-l").innerHTML = 'Jumlah DP <span class="req">*</span>';
      $("f-jenis-help").textContent = "Setelah DP tersimpan, jenis tagihan berikutnya yang terbuka adalah Pelunasan bulan pertama atau Cicilan.";
      onSkema(); return;
    }

    // Sudah ada DP
    const r = ringkas(k), tahunan = r.tahunan, totalDpAmt = dpRows(k).reduce((s, b) => s + b.jumlah, 0);
    $("f-skema").disabled = true; $("f-skema").value = F.mode;
    $("f-jumlah-l").innerHTML = 'Jumlah Pembayaran <span class="req">*</span>';
    $("f-info-ok-t").textContent = `DP ${rpRapat(totalDpAmt)} pada ${tglPanjang(dpDate(k))}`;
    $("f-tarif").readOnly = true;
    $("f-tarif-l").textContent = tahunan ? "Total Tagihan Tahunan" : "Jumlah Tagihan";
    $("f-tarif").value = tahunan ? rpRapat(k.total) : `${rpRapat(r.H)} / bulan`;
    $("f-tarif-help").textContent = tahunan ? "Diisi oleh pemilik" : "Otomatis dari harga kamar";
    $("f-min-l").textContent = tahunan ? "Sisa Tagihan" : "Sisa Tagihan Bulan Ini";
    $("f-min").value = rpRapat(r.sisa);
    $("f-jenis-help").textContent = tahunan
      ? "Setelah DP tersimpan (skema tahunan), jenis tagihan yang tersedia adalah Cicilan dan Pelunasan."
      : "Setelah DP tersimpan (skema bulanan), jenis tagihan yang tersedia adalah Pelunasan bulan pertama dan Tagihan bulanan.";

    let opts;
    if (tahunan) opts = r.cicilan < CICILAN_N - 1 ? ["cicilan", "pelunasan"] : ["pelunasan"]; // cicilan hanya periode 1 dan 2
    else opts = r.pertamaLunas ? ["bulanan"] : ["pelunasan_pertama", "bulanan"];
    const nama = { cicilan: "Cicilan", pelunasan: "Pelunasan", pelunasan_pertama: "Pelunasan bulan pertama", bulanan: "Tagihan bulanan" };
    $("f-jenis").innerHTML = opts.map((j) => `<option value="${j}">${nama[j]}</option>`).join("");

    if (tahunan && r.lunas) { // tidak ada yang bisa dicatat lagi
      F.selesai = true; show("f-jenis-g", false); show("f-fields", false); $("f-simpan").disabled = true;
      formError("Sewa tahunan kamar ini sudah lunas. Tidak ada pembayaran yang perlu dicatat."); hitungRingkas(); return;
    }
    onJenis();
  }

  // Mode setelah DP: jenis tagihan berubah -> jumlah awal, periode cicilan (hanya teks)
  function onJenis() {
    const k = curRoom(); if (!k || F.mode === "dp") return;
    const r = ringkas(k), j = $("f-jenis").value;
    if (r.tahunan) {
      if (j === "cicilan") {
        const dpAmt = dpRows(k).reduce((s, b) => s + b.jumlah, 0);
        setJumlah(Math.min(r.sisa - 1, Math.round((k.total - dpAmt) / CICILAN_N)), false); // cicilan sama rata; sisa dilunasi di periode 3
        $("f-periode").value = `${r.cicilan + 1} dari ${CICILAN_N}`;
      } else { setJumlah(r.sisa, true); $("f-periode").value = `${CICILAN_N} dari ${CICILAN_N}`; }
    } else setJumlah(r.sisa, j === "pelunasan_pertama");
    fieldErr("f-jumlah-help", ""); hitungRingkas();
  }

  // opts: { kamarId }
  function bukaForm(opts = {}) {
    F.saving = false;
    $("f-simpan").disabled = false; $("f-simpan").textContent = "Simpan";
    $("f-kamar").innerHTML = kamarAktif().map((r) => `<option value="${r.id}">Kamar ${esc(fmtNo(r.no))} - ${esc(r.utama.nama)}</option>`).join("");
    F.pickable = !opts.kamarId; // dari tombol "Bayar" / "Catat DP sekarang" kamar sudah pasti
    // Tombol header: langsung tampil form, kamar pertama yang belum punya DP terpilih otomatis (bisa diganti di kartu kamar)
    const awal = opts.kamarId ? kamarAktif().find((r) => r.id === opts.kamarId) : kamarAktif().find((r) => !r.adaDp) || kamarAktif()[0];
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
    const v = { k, dp, skema, jenis: dp ? "dp" : $("f-jenis").value, metode: $("f-metode").value, tanggal: $("f-tgl").value, jumlah: parseRp($("f-jumlah").value), tenggat: dp ? $("f-tenggat").value : "", total: 0, dpMin: 0 };
    if (dp) {
      v.total = totalDp(k); v.dpMin = dpMin(k);
      if (!v.total) { fieldErr("f-tarif-err", skema === "tahunan" ? "Total tagihan tahunan wajib diisi." : "Harga sewa kamar belum diisi."); ok = false; }
      else if (v.jumlah > v.total) { fieldErr("f-jumlah-help", "DP tidak boleh melebihi total tagihan."); ok = false; }
      if (!v.tenggat) { fieldErr("f-tenggat-err", "Tenggat pelunasan wajib diisi."); ok = false; }
      else if (v.tanggal && v.tenggat < v.tanggal) { fieldErr("f-tenggat-err", "Tenggat tidak boleh sebelum tanggal bayar."); ok = false; }
    } else {
      if (!v.jenis) { fieldErr("f-jenis-err", "Pilih jenis tagihan."); ok = false; }
      const sisa = ringkas(k).sisa;
      if (v.jumlah > sisa) { fieldErr("f-jumlah-help", `Jumlah melebihi sisa tagihan (${rp(sisa)}).`); ok = false; }
    }
    if (!v.jumlah && $("f-jumlah-help").hidden) { fieldErr("f-jumlah-help", "Jumlah pembayaran wajib diisi."); ok = false; }
    if (!v.metode) { fieldErr("f-metode-err", "Pilih metode pembayaran."); $("f-metode-seg").classList.add("err"); ok = false; }
    if (!v.tanggal) { fieldErr("f-tgl-err", "Tanggal bayar wajib diisi."); ok = false; }
    return ok ? v : null;
  }

  function simpan(lewatiPeringatan) {
    if (F.saving) return;
    const v = validasi(); if (!v) return;
    // Skenario: DP di bawah minimum = peringatan, bukan penolakan
    if (v.dp && v.dpMin && v.jumlah < v.dpMin && !lewatiPeringatan) {
      $("w-judul").textContent = `DP di bawah minimum ${rpRapat(v.dpMin)}`;
      $("w-isi").textContent = `Nominal DP ${rp(v.jumlah)} lebih kecil dari DP minimum kamar ${fmtNo(v.k.no)}. Anda dapat melanjutkan atau mengubah nominal.`;
      $("pb-peringatan").hidden = false; $("w-ubah").focus(); return;
    }
    kirimPembayaran(v);
  }

  async function kirimPembayaran(v) {
    const u = v.k.utama, sisaSebelum = v.dp ? v.total : ringkas(v.k).sisa;
    F.saving = true; $("f-simpan").disabled = true; $("f-simpan").textContent = "Menyimpan…";
    try {
      if (v.dp) {
        const data = { tanggal_bayar: v.tanggal, jumlah: v.jumlah, metode_pembayaran: v.metode, skema_sewa: v.skema, tenggat_pelunasan: v.tenggat };
        if (v.skema === "tahunan") data.total_sewa = v.total;
        await kirim(`/api/penghunian/${u.id}/dp`, "POST", data);
      } else {
        // periode cicilan sengaja tidak dikirim: dihitung server dari jumlah cicilan yang sudah tercatat
        await kirim("/api/pembayaran", "POST", { id_penghunian: u.id, jenis_pembayaran: v.jenis, tanggal_bayar: v.tanggal, jumlah: v.jumlah, metode_pembayaran: v.metode });
      }
      tutupForm(); state.tab = "tagihan"; syncTabs();
      const lunas = sisaSebelum - v.jumlah <= 0;
      toast(v.dp ? `DP kamar ${fmtNo(v.k.no)} berhasil dicatat atas nama ${u.nama}.` + (lunas ? "" : " Menunggu pelunasan.")
        : lunas ? `Pembayaran tersimpan. Kamar ${fmtNo(v.k.no)} sudah lunas.` : `Pembayaran kamar ${fmtNo(v.k.no)} berhasil dicatat atas nama ${u.nama}.`);
      refresh();
    } catch (e) {
      formError(e.userMessage || "Gagal menyimpan pembayaran. Coba lagi.");
      if (e.status === 422 && /sudah tercatat/i.test(e.userMessage || "")) refresh(); // DP ganda: muat ulang
    } finally {
      F.saving = false; $("f-simpan").disabled = false; $("f-simpan").textContent = "Simpan";
    }
  }

  /* ---------------------------------------------------------
     Detail & konfirmasi
  --------------------------------------------------------- */
  function bukaDetail(it) {
    const k = it.kamar, t = hitungTagihan(k);
    $("d-judul").textContent = `Detail Pembayaran Kamar ${fmtNo(k.no)}`;
    const baris = [...k.bayarAktif].sort((a, b) => a.tanggal.localeCompare(b.tanggal));
    $("d-body").innerHTML = `<div class="pb-d-meta">
        <div>Penyewa utama<b>${esc(k.utama?.nama || "-")}</b></div><div>Skema sewa<b>${esc(k.skema ? k.skema[0].toUpperCase() + k.skema.slice(1) : "Belum diatur")}</b></div>
        <div>Total sewa<b>${k.total ? rp(k.total) : "-"}</b></div><div>Sisa pembayaran<b>${t ? rp(t.sisa) : "-"}</b></div>
        ${t && k.skema === "tahunan" ? `<div>Periode cicilan<b>${Math.min(CICILAN_N, ringkas(k).lunas ? CICILAN_N : ringkas(k).cicilan)} dari ${CICILAN_N}</b></div>` : ""}
        ${k.tenggat ? `<div>Tenggat pelunasan<b>${esc(tglPendek(k.tenggat))}</b></div>` : ""}</div>` +
      (baris.length
        ? `<table class="pb-d-list"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Jumlah</th><th>Metode</th><th>Status</th></tr></thead><tbody>${baris.map((b) =>
            `<tr><td>${esc(tglPendek(b.tanggal) || "-")}</td><td>${esc(jenisLabel(b))}</td><td>${rp(b.jumlah)}</td><td>${esc(b.metode || "-")}</td><td>${{ sudah: "Sudah Dibayar", menunggu: "Menunggu Konfirmasi", ditolak: "Ditolak" }[b.status]}</td></tr>`).join("")}</tbody></table>`
        : '<p class="pb-d-kosong">Belum ada pembayaran untuk kamar ini.</p>');
    $("pb-det").hidden = false; document.body.style.overflow = "hidden";
  }
  const tutupDetail = () => { $("pb-det").hidden = true; document.body.style.overflow = ""; };

  async function konfirmasi(it) {
    const id = it.tipe === "tagihan" ? it.pendingId : it.pay?.id;
    if (!id) return toast("Data pembayaran yang menunggu konfirmasi tidak ditemukan.", true);
    if (!confirm("Konfirmasi pembayaran ini sebagai lunas?")) return;
    try { await kirim(`/api/pembayaran/${id}/status`, "PATCH", { status: "lunas" }); toast("Pembayaran dikonfirmasi."); refresh(); }
    catch (e) { toast(e.userMessage || "Gagal mengonfirmasi pembayaran.", true); }
  }

  /* ---------------------------------------------------------
     Event
  --------------------------------------------------------- */
  function syncTabs() { document.querySelectorAll(".pb-tab").forEach((b) => b.setAttribute("aria-selected", b.dataset.tab === state.tab)); }
  const tutupMenu = () => document.querySelectorAll(".pb-menu").forEach((m) => m.remove());

  document.querySelectorAll(".pb-tab").forEach((b) => b.addEventListener("click", () => { state.tab = b.dataset.tab; state.page = 1; syncTabs(); render(); }));
  ["pb-f-bulan", "pb-f-status", "pb-f-kamar"].forEach((id) => $(id).addEventListener("change", () => { state.page = 1; render(); }));
  $("pb-q").addEventListener("input", () => { state.page = 1; render(); });
  $("pb-pager").addEventListener("click", (e) => { const b = e.target.closest("button[data-p]"); if (b && !b.disabled) { state.page = Number(b.dataset.p); render(); } });
  $("pb-state").addEventListener("click", (e) => { if (e.target.id === "pb-retry") refresh(); });

  // Header "Catat Pembayaran": kosong kamar / tanpa penghuni -> pesan, form tidak dibuka
  $("pb-catat").addEventListener("click", () => {
    if (!kamarAktif().length) return toast("Tambahkan penghuni terlebih dahulu.", true);
    bukaForm();
  });

  $("pb-rows").addEventListener("click", (e) => {
    const b = e.target.closest("button[data-act]"); if (!b) return;
    const it = state.view.find((x) => x.key === b.closest("tr").dataset.key); if (!it) return;
    const act = b.dataset.act; tutupMenu();
    if (act === "menu") {
      e.stopPropagation();
      const m = document.createElement("div"); m.className = "pb-menu"; m.setAttribute("role", "menu");
      m.innerHTML = '<button type="button" role="menuitem" data-m="detail">Lihat detail</button>' +
        (it.tipe === "tagihan" && it.status !== "sudah" ? '<button type="button" role="menuitem" data-m="bayar">Catat pembayaran</button>' : "");
      m.addEventListener("click", (ev) => { const x = ev.target.closest("[data-m]"); if (!x) return; tutupMenu(); x.dataset.m === "bayar" ? bayar(it) : bukaDetail(it); });
      b.parentElement.appendChild(m);
    } else if (act === "bayar") bayar(it);
    else if (act === "konfirmasi") konfirmasi(it);
    else bukaDetail(it);
  });
  document.addEventListener("click", tutupMenu);

  function bayar(it) { bukaForm({ kamarId: it.kamar.id }); } // belum ada DP -> form DP; sudah ada -> form pelunasan/cicilan/bulanan

  // Form
  $("f-kamar").addEventListener("change", onKamar);
  $("f-skema").addEventListener("change", onSkema);
  $("f-jenis").addEventListener("change", () => { fieldErr("f-jenis-err", ""); onJenis(); });
  [["f-jumlah", () => fieldErr("f-jumlah-help", "")], ["f-tarif", () => { fieldErr("f-tarif-err", ""); }]].forEach(([id, extra]) => {
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
  window.addEventListener("offline", () => toast("Koneksi terputus. Perubahan belum bisa disimpan.", true));
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

  fetch("/api/pengaturan-sewa", { headers: { Accept: "application/json" } }).then((r) => r.json()).then((j) => { const n = num(j.data?.dp_minimum_tahunan); if (n) state.dpTahunan = n; }).catch(() => {});
  refresh().then(dariParameter);
})();