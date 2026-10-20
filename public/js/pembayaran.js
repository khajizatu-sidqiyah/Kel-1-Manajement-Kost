// Halaman Pembayaran & Tagihan  (resources/views/pembayaran.blade.php)
// User story 5.2a: pencatatan pembayaran DP, cicilan, dan pelunasan.
//
// Endpoint yang dipakai:
//   GET   /api/penghunian                 -> penghunian + relasi kamar, penghuni, pembayaran     (SUDAH ADA)
//   POST  /api/penghunian/{id}/dp         -> catat DP (hanya sekali per kamar)                   (SUDAH ADA)
//   PUT   /api/penghunian/{id}/tenggat    -> ubah tenggat pelunasan                              (SUDAH ADA)
//   POST  /api/pembayaran                 -> catat cicilan / pelunasan                           (BELUM ADA - usulan kontrak)
//   PATCH /api/pembayaran/{id}/status     -> konfirmasi pembayaran {status:"lunas"}              (BELUM ADA - ada di kontrak OpenAPI)
//
// "Tagihan" belum punya tabel/endpoint sendiri, jadi daftar tagihan DIHITUNG di sini dari data penghunian + pembayaran.
// Ganti fungsi hitungTagihan() bila backend sudah menyediakan GET /api/tagihan.
(function () {
  "use strict";

  const PER_PAGE = 5; // desain: "Menampilkan 1–5 dari 12 tagihan"
  const state = { rows: [], tab: "tagihan", page: 1, loading: true, error: "", view: [] };
  const F = { saving: false, locked: false, file: null };

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
  const JENIS = { dp: "DP", cicilan: "Cicilan", pelunasan: "Pelunasan" };

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
        metode: String(b.metode_pembayaran || "").replace(/^DP\s*-\s*/i, ""), jenis: jenisOf(b), status: statusBayarOf(b), ket: b.keterangan || "",
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
      k.adaDp = k.semua.some((r) => r.bayar.some((b) => b.jenis === "dp" && b.status !== "ditolak")); // BE: DP hanya sekali per kamar
      k.bayarAktif = k.aktif.flatMap((r) => r.bayar);
      const u = k.utama;
      k.skema = u?.skema || "";
      k.total = u ? (u.total || (k.skema === "tahunan" ? 0 : u.harga)) : 0; // tanpa pengaturan sewa: pakai harga bulanan kamar
      k.dpMin = u?.dpMin || 0; k.tenggat = u?.tenggat || "";
    });
    return list.sort((a, b) => String(a.no).localeCompare(String(b.no), undefined, { numeric: true }));
  }

  // Satu tagihan per kamar berpenyewa utama. Bulanan: periode bulan ini. Tahunan: total sewa setahun.
  function hitungTagihan(k) {
    if (!k.utama || !k.total) return null;
    const tahunan = k.skema === "tahunan", key = todayIso().slice(0, 7);
    const pays = k.bayarAktif.filter((b) => b.status !== "ditolak" && (tahunan || b.tanggal.slice(0, 7) === key));
    const sudah = pays.filter((b) => b.status === "sudah"), pending = pays.find((b) => b.status === "menunggu");
    const paid = sudah.reduce((s, b) => s + b.jumlah, 0);
    return {
      tipe: "tagihan", key: "t" + k.id, kamar: k, nama: k.utama.nama,
      jenis: tahunan ? "Sewa Tahunan" : "Sewa Kamar", bulan: key,
      periode: tahunan ? `Tahun ${(k.utama.mulai || todayIso()).slice(0, 4)}` : periodeLabel(key),
      jumlah: k.total, paid, nSudah: sudah.length, sisa: Math.max(0, k.total - paid),
      status: paid >= k.total ? "sudah" : pending ? "menunggu" : "belum", pendingId: pending?.id,
    };
  }

  function semuaItem() {
    const kamar = hitungKamar(), byId = new Map(kamar.map((k) => [k.id, k]));
    const tagihan = kamar.map(hitungTagihan).filter(Boolean);
    const riwayat = state.rows.flatMap((r) => r.bayar.map((b) => ({
      tipe: "riwayat", key: "p" + b.id, kamar: byId.get(r.id_kamar), nama: r.nama, jenis: JENIS[b.jenis], pay: b,
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
     Form Catat Pembayaran (Dekstop_19)
  --------------------------------------------------------- */
  const kamarAktif = () => hitungKamar().filter((k) => k.utama);
  const curRoom = () => kamarAktif().find((k) => String(k.id) === $("f-kamar").value);

  function setDate(id, val) {
    $(id).value = val || "";
    const v = $(id + "-v"); v.textContent = val ? tglPendek(val) : "Pilih Tanggal"; v.classList.toggle("ph", !val);
  }
  function fieldErr(id, msg) {
    const e = $(id); if (!e) return;
    e.textContent = msg || ""; e.hidden = !msg;
    const wrap = e.previousElementSibling; if (wrap) wrap.classList.toggle("err", !!msg);
  }
  function formError(msg) { $("f-error").textContent = msg || ""; $("f-error").hidden = !msg; }
  function clearErrors() { ["f-jenis-err", "f-periode-err", "f-metode-err", "f-tgl-err", "f-file-err"].forEach((id) => fieldErr(id, "")); formError(""); resetJumlahHelp(); }

  function jenisOptions(k) {
    // Aturan jenis per skema: bulanan = DP + Pelunasan; tahunan = DP + Cicilan + Pelunasan; belum diatur = semua.
    let o = k.skema === "bulanan" ? ["dp", "pelunasan"] : ["dp", "cicilan", "pelunasan"];
    if (k.adaDp) o = o.filter((x) => x !== "dp"); // DP hanya sekali
    return o;
  }
  function periodeOptions() {
    const d = new Date(), out = [];
    for (let i = -3; i <= 3; i++) { const x = new Date(d.getFullYear(), d.getMonth() + i, 1); const key = `${x.getFullYear()}-${pad(x.getMonth() + 1)}`; out.push([key, periodeLabel(key)]); }
    return out;
  }
  function resetJumlahHelp() {
    const k = curRoom(), j = $("f-jenis").value, p = [];
    $("f-jumlah-help").classList.remove("pb-help--err");
    if (k) {
      if (j === "dp" && k.dpMin) p.push(`DP minimum <b>${rpRapat(k.dpMin)}</b>`);
      const t = hitungTagihan(k); if (t) p.push(`Sisa pembayaran <b>${rp(t.sisa)}</b>`);
    }
    $("f-jumlah-help").innerHTML = p.join(" · ");
  }

  // Dipanggil saat kamar berubah: isi nama, jenis, tenggat
  function onKamar(jenisAwal) {
    const k = curRoom();
    $("f-nama").value = k ? k.utama.nama : "";
    const opts = k ? jenisOptions(k) : [];
    const cur = jenisAwal ?? $("f-jenis").value;
    $("f-jenis").innerHTML = '<option value="">Pilih Jenis</option>' + opts.map((j) => `<option value="${j}">${JENIS[j]}</option>`).join("");
    $("f-jenis").value = opts.includes(cur) ? cur : "";
    onJenis();
  }
  function onJenis() {
    const k = curRoom(), j = $("f-jenis").value;
    const tampil = !!(k && j === "dp" && k.skema === "tahunan");
    $("f-tenggat-wrap").hidden = !tampil;
    if (tampil) setDate("f-tenggat", k.tenggat);   // nilai awal dari perhitungan 5.1, bisa diubah
    resetJumlahHelp();
  }

  function setFile(file) {
    fieldErr("f-file-err", "");
    if (file && file.size > 5 * 1024 * 1024) { fieldErr("f-file-err", "Ukuran file maksimal 5 MB."); file = null; }
    F.file = file || null;
    $("f-drop-t").innerHTML = F.file
      ? `${ic("doc")}${esc(F.file.name)}<button type="button" id="f-file-x">Hapus</button>`
      : `${ic("upload")}Klik atau Seret file`;
    if (!F.file) $("f-file").value = "";
  }

  // opts: { kamarId, jenis, periode, locked }
  function bukaForm(opts = {}) {
    const k = kamarAktif();
    F.saving = false; F.locked = !!opts.locked;
    $("f-simpan").disabled = false; $("f-simpan").textContent = "Simpan Pembayaran";
    $("f-kamar").innerHTML = '<option value="">Pilih Kamar / Penghuni</option>' +
      k.map((r) => `<option value="${r.id}">${esc(fmtNo(r.no))} Lt. ${esc(r.lantai)} - ${esc(r.utama.nama)}</option>`).join("");
    $("f-kamar").value = opts.kamarId ? String(opts.kamarId) : "";
    $("f-kamar").disabled = F.locked;
    $("f-kamar-help").hidden = !F.locked; $("f-kamar-help").textContent = "Kamar tidak dapat diganti pada pencatatan ini.";
    $("f-periode").innerHTML = '<option value="">Pilih Periode</option>' + periodeOptions().map(([v, l]) => `<option value="${v}">${l}</option>`).join("");
    $("f-periode").value = opts.periode || ""; // kosong = "Pilih Periode" (desain); dari tombol Bayar terisi periode tagihan
    $("f-metode").value = ""; $("f-jumlah").value = ""; $("f-ket").value = ""; setDate("f-tgl", ""); setFile(null);
    clearErrors(); onKamar(opts.jenis || "");
    $("pb-form").hidden = false; document.body.style.overflow = "hidden";
    ($("f-kamar").value ? $("f-jenis") : $("f-kamar")).focus();
  }
  function tutupForm() { $("pb-form").hidden = true; $("pb-peringatan").hidden = true; document.body.style.overflow = ""; }

  function validasi() {
    clearErrors();
    const k = curRoom(); let ok = true;
    if (!k) { formError("Pilih kamar terlebih dahulu."); return null; }
    const v = {
      jenis: $("f-jenis").value, periode: $("f-periode").value, metode: $("f-metode").value, tanggal: $("f-tgl").value,
      jumlah: Number($("f-jumlah").value.replace(/\D/g, "")), ket: $("f-ket").value.trim(), tenggat: $("f-tenggat-wrap").hidden ? "" : $("f-tenggat").value,
    };
    if (!v.jenis) { fieldErr("f-jenis-err", "Pilih jenis tagihan."); ok = false; }
    if (!v.periode) { fieldErr("f-periode-err", "Pilih periode tagihan."); ok = false; }
    if (!v.jumlah) { $("f-jumlah-help").textContent = "Jumlah pembayaran wajib diisi."; $("f-jumlah-help").classList.add("pb-help--err"); ok = false; }
    if (!v.metode) { fieldErr("f-metode-err", "Pilih metode pembayaran."); ok = false; }
    if (!v.tanggal) { fieldErr("f-tgl-err", "Tanggal bayar wajib diisi."); ok = false; }
    return ok ? { k, ...v } : null;
  }

  function simpan(lewatiPeringatan) {
    if (F.saving) return;
    const v = validasi(); if (!v) return;
    // Skenario: DP di bawah minimum = peringatan, bukan penolakan
    if (v.jenis === "dp" && v.k.dpMin && v.jumlah < v.k.dpMin && !lewatiPeringatan) {
      $("w-judul").textContent = `DP di bawah minimum ${rpRapat(v.k.dpMin)}`;
      $("w-isi").textContent = `Nominal DP ${rp(v.jumlah)} lebih kecil dari DP minimum kamar ${fmtNo(v.k.no)}. Anda dapat melanjutkan atau mengubah nominal.`;
      $("pb-peringatan").hidden = false; $("w-ubah").focus(); return;
    }
    kirimPembayaran(v);
  }

  async function kirimPembayaran(v) {
    const u = v.k.utama, tagihan = hitungTagihan(v.k), sisaSebelum = tagihan ? tagihan.sisa : null;
    F.saving = true; $("f-simpan").disabled = true; $("f-simpan").textContent = "Menyimpan…";
    try {
      const data = { tanggal_bayar: v.tanggal, jumlah: v.jumlah, metode_pembayaran: v.metode };
      let url = `/api/penghunian/${u.id}/dp`;
      if (v.jenis !== "dp") { url = "/api/pembayaran"; Object.assign(data, { id_penghunian: u.id, jenis_pembayaran: v.jenis, periode: v.periode }); }
      if (v.ket) data.keterangan = v.ket;
      let body = data;
      if (F.file) { body = new FormData(); Object.entries(data).forEach(([a, b]) => body.append(a, b)); body.append("bukti_pembayaran", F.file); }
      await kirim(url, "POST", body);

      let tenggatGagal = false;
      if (v.jenis === "dp" && v.tenggat && v.tenggat !== v.k.tenggat) {
        try { await kirim(`/api/penghunian/${u.id}/tenggat`, "PUT", { tenggat_pelunasan: v.tenggat }); } catch (_) { tenggatGagal = true; }
      }
      tutupForm(); state.tab = "tagihan"; syncTabs();
      const lunas = sisaSebelum !== null && sisaSebelum - v.jumlah <= 0;
      if (tenggatGagal) toast("Pembayaran tersimpan, tetapi tenggat pelunasan gagal diubah. Ubah lagi lewat pengaturan sewa.", true);
      else toast(lunas ? `Pembayaran tersimpan. Kamar ${fmtNo(v.k.no)} sudah lunas.` : `Pembayaran ${JENIS[v.jenis]} kamar ${fmtNo(v.k.no)} berhasil dicatat atas nama ${u.nama}.`);
      refresh();
    } catch (e) {
      formError(e.userMessage || "Gagal menyimpan pembayaran. Coba lagi.");
      if (e.status === 422 && /sudah tercatat/i.test(e.userMessage || "")) refresh(); // DP ganda: muat ulang agar opsi DP hilang
    } finally {
      F.saving = false; $("f-simpan").disabled = false; $("f-simpan").textContent = "Simpan Pembayaran";
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
        ${k.tenggat ? `<div>Tenggat pelunasan<b>${esc(tglPendek(k.tenggat))}</b></div>` : ""}</div>` +
      (baris.length
        ? `<table class="pb-d-list"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Jumlah</th><th>Metode</th><th>Status</th></tr></thead><tbody>${baris.map((b) =>
            `<tr><td>${esc(tglPendek(b.tanggal) || "-")}</td><td>${JENIS[b.jenis]}</td><td>${rp(b.jumlah)}</td><td>${esc(b.metode || "-")}</td><td>${{ sudah: "Sudah Dibayar", menunggu: "Menunggu Konfirmasi", ditolak: "Ditolak" }[b.status]}</td></tr>`).join("")}</tbody></table>`
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

  function bayar(it) { bukaForm({ kamarId: it.kamar.id, periode: it.bulan }); }

  // Form
  $("f-kamar").addEventListener("change", () => { clearErrors(); onKamar(); });
  $("f-jenis").addEventListener("change", () => { fieldErr("f-jenis-err", ""); onJenis(); });
  $("f-periode").addEventListener("change", () => fieldErr("f-periode-err", ""));
  $("f-metode").addEventListener("change", () => fieldErr("f-metode-err", ""));
  $("f-jumlah").addEventListener("input", (e) => { const d = e.target.value.replace(/\D/g, ""); e.target.value = d ? Number(d).toLocaleString("id-ID") : ""; resetJumlahHelp(); });
  ["f-tgl", "f-tenggat"].forEach((id) => {
    $(id).addEventListener("change", (e) => { setDate(id, e.target.value); if (id === "f-tgl") fieldErr("f-tgl-err", ""); });
    $(id).addEventListener("click", (e) => { try { e.target.showPicker(); } catch (_) {} });
  });
  $("f-file").addEventListener("change", (e) => setFile(e.target.files[0]));
  $("f-drop").addEventListener("click", (e) => { if (e.target.id === "f-file-x") { e.preventDefault(); setFile(null); } });
  ["dragover", "dragenter"].forEach((ev) => $("f-drop").addEventListener(ev, (e) => { e.preventDefault(); $("f-drop").classList.add("over"); }));
  ["dragleave", "drop"].forEach((ev) => $("f-drop").addEventListener(ev, (e) => { e.preventDefault(); $("f-drop").classList.remove("over"); if (ev === "drop") setFile(e.dataTransfer.files[0]); }));
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
    bukaForm({ kamarId: k.id, jenis: String(q.get("jenis") || "").toLowerCase(), locked: true });
  }

  refresh().then(dariParameter);
})();
