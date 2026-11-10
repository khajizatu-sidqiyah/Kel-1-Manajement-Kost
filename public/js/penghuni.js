// Halaman Data Penyewa & Arsip  (resources/views/penghuni.blade.php)
// Bergantung pada rooms-api.js skrng menjadi kamar-api(dimuat lebih dulu): loadRooms(), fmtNo(), fmtTanggal(), lantaiDariNomor().
//
// Endpoint (kontrak API Kelompok 1):
//   GET   /api/penghunian               -> daftar penghunian (+ relasi kamar & penghuni)
//   GET   /api/kamar                    -> lewat loadRooms() di rooms-api.js skrng menjadi kamar-api
//   POST  /api/penghuni                 -> buat penghuni baru
//   POST  /api/penghunian               -> catat penghuni menempati kamar
//   PUT   /api/penghunian/{id}/selesai  -> akhiri penghunian
// Semua request API membawa token Bearer dari localStorage (disimpan login.js), karena route API memakai auth:sanctum.
(function () {
  "use strict";

  const PER_PAGE = 10; // desain: "Menampilkan 1–10 dari 24 penyewa"
  const state = { rows: [], rooms: [], tab: "aktif", page: 1, showAll: false, loading: true, error: false };
  // Filter yang sedang diterapkan -> dikirim ke BE sebagai query string (skema[]=bulanan&lantai[]=2 ...)
  const FILTER_KEYS = ["skema", "status_data", "peran", "lantai"];
  const applied = { skema: [], status_data: [], peran: [], lantai: [] };
  const modal = { open: false, kamarId: null, saving: false };

  const $ = (id) => document.getElementById(id);
  const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const ic = (name, cls = "i") => `<svg class="${cls}"><use href="#i-${name}"/></svg>`;
  const AUTH = { Authorization: `Bearer ${localStorage.getItem("token") || ""}` };
  const JSON_HEADERS = { Accept: "application/json", "Content-Type": "application/json", ...AUTH };

  // "2026-11-05" -> "05 Nov 2026" (sesuai desain)
  const BLN = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
  const tgl = (iso) => {
    const [y, m, d] = String(iso || "").slice(0, 10).split("-");
    return y && BLN[m - 1] ? `${String(Number(d)).padStart(2, "0")} ${BLN[m - 1]} ${y}` : "";
  };

  /* ---------------------------------------------------------
     Data: ubah 1 baris dari BE ke bentuk yang dipakai tampilan
  --------------------------------------------------------- */
  function normalize(p, index, all) {
    const noKamar = p.kamar?.no_kamar ?? "";
    // Flag peran: BE memakai kolom `penyewa_utama` (kadang `is_penyewa_utama`).
    let utama = p.penyewa_utama ?? p.is_penyewa_utama;
    if (utama === undefined || utama === null) {
      // Cadangan bila BE belum mengirim flag: penghuni aktif paling awal di kamar itu = penyewa utama.
      const sekamar = all.filter((x) => x.id_kamar === p.id_kamar && x.status === p.status);
      utama = sekamar.sort((a, b) => a.id_penghunian - b.id_penghunian)[0]?.id_penghunian === p.id_penghunian;
    }
    const nama = p.penghuni?.nama_penghuni ?? "-";
    const hp = p.penghuni?.no_telepon || "";
    const mulai = p.tanggal_mulai ? String(p.tanggal_mulai).slice(0, 10) : "";
    return {
      id: p.id_penghunian, id_penghuni: p.id_penghuni, id_kamar: p.id_kamar,
      nama, hp, mulai, selesai: p.tanggal_selesai ? String(p.tanggal_selesai).slice(0, 10) : "",
      no_kamar: noKamar, lantai: Number(p.kamar?.lantai) || (noKamar ? lantaiDariNomor(noKamar) : ""),
      status: String(p.status || "aktif").toLowerCase(), // aktif | selesai
      utama: !!Number(utama),
      lengkap: !!(hp && mulai),
    };
  }

  // Pencarian & filter dikerjakan BE. FE hanya menyusun parameter: q, skema[], status_data[], peran[], lantai[]
  function buildQuery() {
    const qs = new URLSearchParams();
    const q = $("f-q").value.trim();
    if (q) qs.set("q", q);
    FILTER_KEYS.forEach((k) => applied[k].forEach((v) => qs.append(k + "[]", v)));
    const str = qs.toString();
    return str ? "?" + str : "";
  }

  async function loadPenghunian() {
    const res = await fetch("/api/penghunian?per_page=all", { headers: { Accept: "application/json", ...AUTH } });
    if (res.status === 401) { localStorage.removeItem("token"); location.href = "/login"; return []; }
    if (!res.ok) throw new Error("HTTP " + res.status);
    const rows = (await res.json()).data;
    if (!Array.isArray(rows)) throw new Error("Format data tidak dikenali");
    return rows.map((p, i, all) => normalize(p, i, all));
  }

  /* ---------------------------------------------------------
     Render: kartu ringkasan
  --------------------------------------------------------- */
  function renderStats() {
    const aktif = state.rows.filter((r) => r.status === "aktif");
    const arsip = state.rows.filter((r) => r.status !== "aktif");
    const utama = aktif.filter((r) => r.utama).length;
    const card = (iconL, label, n) => `
      <div class="pg-stat">
        <span class="ico">${ic(iconL)}</span>
        <span class="lbl">${label}</span>
        <div class="val"><b>${n}</b><span>Orang</span></div>
      </div>`;
    $("pg-stats").innerHTML =
      card("users", "Total Penyewa Aktif", aktif.length) +
      card("user", "Penyewa Utama", utama) +
      card("users", "Penghuni Tambahan", aktif.length - utama) +
      card("archive", "Data Diarsipkan", arsip.length);
  }

  /* ---------------------------------------------------------
     Render: tabel + filter + pagination
  --------------------------------------------------------- */
  // Urutan tabel: lantai 1 dulu, lalu nomor kamar naik (numerik). Dalam satu kamar: penyewa utama di atas, lalu yang lebih dulu masuk.
  const urutKamar = (a, b) =>
    (Number(a.lantai) || 99) - (Number(b.lantai) || 99) ||
    String(a.no_kamar).localeCompare(String(b.no_kamar), undefined, { numeric: true }) ||
    Number(b.utama) - Number(a.utama) || a.id - b.id;

  // Pencarian & filter dikerjakan di browser (semua data penghunian sudah dimuat sekali),
  // jadi tidak bergantung pada parameter query di API.
  function cocok(r) {
    const q = $("f-q").value.trim().toLowerCase();
    if (q) {
      const hay = [r.nama, r.no_kamar, fmtNo(r.no_kamar), r.hp].join(" ").toLowerCase();
      if (!hay.includes(q)) return false;
    }
    if (applied.status_data.length && !applied.status_data.includes(r.lengkap ? "lengkap" : "belum")) return false;
    if (applied.peran.length && !applied.peran.includes(r.utama ? "utama" : "tambahan")) return false;
    if (applied.lantai.length && !applied.lantai.includes(String(r.lantai))) return false;
    return true;
  }

  function filtered() {
    return state.rows.filter((r) => (state.tab === "aktif") === (r.status === "aktif") && cocok(r)).sort(urutKamar);
  }

  const peranPill = (r) => r.utama
    ? `<span class="pill pill--ok pill--utama">Penyewa Utama</span>`
    : `<span class="pill pill--add">Penghuni Tambahan</span>`;
  const statusPill = (r) => r.lengkap
    ? `<span class="pill pill--ok pill--lengkap">Lengkap</span>`
    : `<span class="pill pill--warn">Data belum lengkap</span>`;

  function rowHtml(r, no) {
    const aktif = r.status === "aktif";
    return `<tr data-id="${r.id}">
      <td class="no">${no}</td>
      <td class="nama">${esc(r.nama)}</td>
      <td class="kamar">${esc(fmtNo(r.no_kamar))}${r.lantai ? ` (Lt. ${esc(r.lantai)})` : ""}</td>
      <td class="hp">${r.hp ? esc(fmtHp(r.hp)) : '<span class="empty">Belum diisi</span>'}</td>
      <td class="tgl">${r.mulai ? esc(tgl(r.mulai)) : '<span class="empty">Belum diisi</span>'}</td>
      <td class="ta-c">${peranPill(r)}</td>
      <td class="ta-c">${statusPill(r)}</td>
      <td class="aksi-td"><div class="aksi">
        <button type="button" data-act="ubah" aria-label="Ubah ${esc(r.nama)}">${ic("edit")}</button>
        ${aktif ? `<button type="button" data-act="menu" aria-label="Menu lainnya" aria-haspopup="menu">${ic("dots")}</button>` : `<span style="width:24px"></span>`}
      </div></td>
    </tr>`;
  }

  // Apakah ada pencarian / filter yang sedang aktif?
  const adaFilter = () => FILTER_KEYS.some((k) => applied[k].length > 0) || $("f-q").value.trim() !== "";

  function renderTable() {
    const list = filtered();
    const perPage = state.showAll ? Math.max(list.length, 1) : PER_PAGE; // "Lihat semua data" = semua baris dalam 1 halaman
    const pages = Math.max(1, Math.ceil(list.length / perPage));
    state.page = Math.min(state.page, pages);
    const start = (state.page - 1) * perPage;
    const slice = list.slice(start, start + perPage);
    const box = $("pg-state");

    if (state.loading) {
      $("pg-rows").innerHTML = ""; box.hidden = false; box.innerHTML = "<b>Memuat data…</b>Mohon tunggu sebentar.";
    } else if (state.error) {
      $("pg-rows").innerHTML = ""; box.hidden = false;
      box.innerHTML = '<b>Data penyewa gagal dimuat</b>Periksa koneksi atau coba lagi.<br><button type="button" id="btn-retry">Coba lagi</button>';
    } else if (!state.rows.length) {
      $("pg-rows").innerHTML = ""; box.hidden = false;
      if (adaFilter()) {
        // kosong karena pencarian/filter yang sedang aktif, BUKAN karena belum ada data
        box.innerHTML = '<b>Tidak ada data yang cocok</b>Coba ubah kata kunci atau filter yang dipakai.<br><button type="button" id="btn-reset-filter">Reset pencarian &amp; filter</button>';
      } else {
        // belum ada penghuni sama sekali
        box.innerHTML = '<b>Belum ada data penghuni</b><br><button type="button" id="btn-kosong-tambah">Tambah penghuni</button>';
      }
    } else if (!slice.length) {
      $("pg-rows").innerHTML = ""; box.hidden = false;
      if (adaFilter()) {
        box.innerHTML = '<b>Tidak ada data yang cocok</b>Coba ubah kata kunci atau filter yang dipakai.<br><button type="button" id="btn-reset-filter">Reset pencarian &amp; filter</button>';
      } else {
        box.innerHTML = state.tab === "aktif" ? "<b>Belum ada penyewa</b>Belum ada penyewa aktif." : "<b>Arsip kosong</b>Belum ada penyewa yang keluar dari kos.";
      }
    } else {
      box.hidden = true;
      $("pg-rows").innerHTML = slice.map((r, i) => rowHtml(r, start + i + 1)).join("");
    }

    // Info jumlah
    const total = list.length, from = total ? start + 1 : 0, to = start + slice.length;
    $("pg-info").textContent = state.loading || state.error ? "\u00a0" : `Menampilkan ${from}–${to} dari ${total} penyewa`;

    // Pagination
    let nav = `<button type="button" data-p="${state.page - 1}" aria-label="Sebelumnya" ${state.page <= 1 ? "disabled" : ""}>${ic("chev-l")}</button>`;
    for (let p = 1; p <= pages; p++) nav += `<button type="button" data-p="${p}" class="${p === state.page && !state.showAll ? "is-on" : ""}" ${p === state.page && !state.showAll ? 'aria-current="page"' : ""}>${p}</button>`;
    nav += `<button type="button" class="pager-all" data-all="1" aria-pressed="${state.showAll}">Lihat semua data</button>`;
    nav += `<button type="button" data-p="${state.page + 1}" aria-label="Berikutnya" ${state.page >= pages ? "disabled" : ""}>${ic("chev-r")}</button>`;
    $("pg-pager").innerHTML = nav;
  }

  /* ---------------------------------------------------------
     Panel Filter (default tertutup, badge hanya muncul bila ada filter)
  --------------------------------------------------------- */
  function initFilterPanel() {
    const panel = $("fp-panel"), btn = $("fp-toggle"), wrap = $("fp-wrap");
    const boxes = () => panel.querySelectorAll('input[type="checkbox"]');
    const setOpen = (open) => { panel.hidden = !open; btn.setAttribute("aria-expanded", String(open)); };
    const syncBadge = () => {
      const n = FILTER_KEYS.reduce((t, k) => t + applied[k].length, 0);
      $("fp-badge").textContent = n; $("fp-badge").hidden = n === 0;
      btn.classList.toggle("has-filter", n > 0);
    };
    const syncBoxes = () => boxes().forEach((c) => { c.checked = applied[c.name].includes(c.value); }); // kembalikan ke filter yang berlaku
    const apply = () => {
      FILTER_KEYS.forEach((k) => { applied[k] = [...panel.querySelectorAll(`input[name="${k}"]:checked`)].map((c) => c.value); });
      syncBadge(); setOpen(false); state.page = 1; state.showAll = false; renderTable();
    };
    const reset = () => {
      boxes().forEach((c) => { c.checked = false; });
      FILTER_KEYS.forEach((k) => { applied[k] = []; });
      syncBadge(); setOpen(false); state.page = 1; state.showAll = false; renderTable();
    };
    btn.addEventListener("click", (e) => { e.stopPropagation(); if (panel.hidden) { syncBoxes(); setOpen(true); } else setOpen(false); });
    $("fp-close").addEventListener("click", () => { syncBoxes(); setOpen(false); });
    $("fp-apply").addEventListener("click", apply);
    $("fp-reset").addEventListener("click", reset);
    document.addEventListener("click", (e) => { if (!panel.hidden && !wrap.contains(e.target)) { syncBoxes(); setOpen(false); } });
    document.addEventListener("keydown", (e) => { if (e.key === "Escape" && !panel.hidden) { syncBoxes(); setOpen(false); btn.focus(); } });
    syncBadge();
  }

  async function refresh() {
    state.loading = true; state.error = false; renderTable();
    try {
      state.rows = await loadPenghunian();
    } catch (e) {
      state.error = true; state.rows = [];
    }
    state.loading = false; renderStats(); renderTable();
  }

  /* ---------------------------------------------------------
     Toast & menu aksi
  --------------------------------------------------------- */
  let toastTimer;
  function toast(msg, err) {
    document.querySelectorAll(".toast").forEach((t) => t.remove());
    const t = document.createElement("div");
    t.className = "toast" + (err ? " toast--err" : ""); t.textContent = msg; t.setAttribute("role", "status");
    document.body.appendChild(t);
    clearTimeout(toastTimer); toastTimer = setTimeout(() => t.remove(), 3500);
  }
  const closeMenus = () => document.querySelectorAll(".menu").forEach((m) => m.remove());

  async function akhiri(id) {
    if (!confirm("Akhiri penghunian penyewa ini? Penyewa akan masuk ke Arsip.")) return;
    try {
      const res = await fetch(`/api/penghunian/${id}/selesai`, { method: "PUT", headers: JSON_HEADERS });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(json.message || "HTTP " + res.status);
      toast("Penghunian berhasil diakhiri."); refresh();
    } catch (e) {
      toast("Gagal mengakhiri penghunian: " + e.message, true);
    }
  }

  /* ---------------------------------------------------------
     Modal: TAMBAH PENYEWA (skenario_3, id awalan "a-")
            EDIT PENYEWA   (skenario_2, id awalan "e-", ikon pena)
  --------------------------------------------------------- */
  // Belum ada di kontrak API -> konstanta sementara sesuai desain. Pindahkan ke BE bila sudah tersedia.
  const KAPASITAS = 2;            // "Kapasitas 2 Orang"
  const BIAYA_TAMBAHAN = 300000;  // "+Rp 300.000/bln"

  const modals = {
    add:  { p: "a", root: () => $("pm"), kamarId: null, saving: false, row: null, label: "Simpan Penghuni" },
    edit: { p: "e", root: () => $("pe"), kamarId: null, saving: false, row: null },
  };
  const rp = (n) => "Rp " + Number(n).toLocaleString("id-ID");
  const inisial = (nama) => String(nama).trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join("").toUpperCase();
  const blnTahun = (iso) => { const t = tgl(iso); return t ? t.split(" ").slice(1).join(" ") : ""; };
  const nowStr = () => { const d = new Date(), z = (n) => String(n).padStart(2, "0"); return `${tgl(d.toISOString().slice(0, 10))}, ${z(d.getHours())}:${z(d.getMinutes())}`; };
  const fmtHp = (hp) => String(hp || "").replace(/\D/g, "").replace(/^(\d{4})(\d{4})(\d+)$/, "$1-$2-$3") || "-";

  // Penghuni aktif pada sebuah kamar (opsional: abaikan 1 penghunian yang sedang diedit)
  function kamarCtx(idKamar, excludeId) {
    const list = state.rows.filter((r) => r.status === "aktif" && r.id_kamar === idKamar && r.id !== excludeId);
    return { list, count: list.length, utama: list.find((r) => r.utama) || list[0] || null, penuh: list.length >= KAPASITAS };
  }
  function badge(k, excludeId, long) {
    const c = kamarCtx(k.id, excludeId);
    if (!c.count) return '<span class="pm-badge">Kosong</span>';
    const txt = `Terisi (${c.count}/${KAPASITAS} Penghuni)` + (long ? ` - Kapasitas ${KAPASITAS} Orang` : "");
    return `<span class="pm-badge ${c.penuh ? "pm-badge--full" : "pm-badge--busy"}">${c.penuh ? "Penuh" : txt}</span>`;
  }
  const kamarName = (k) => `Kamar ${esc(fmtNo(k.no_kamar))} — Lantai ${esc(k.lantai)}`;
  const selKamar = (m) => state.rooms.find((k) => k.id === m.kamarId);
  const exId = (m) => (m.row ? m.row.id : undefined);

  // Pelengkap markup modal Tambah. Kalau di blade belum ada (a-owner, a-hhp, a-htgl, ikon & label tombol),
  // dibuat otomatis di sini, jadi blade lama tetap jalan dan "Peran Penghuni" tidak kosong.
  (function siapkanModalAdd() {
    if (!$("a-owner")) { const d = document.createElement("div"); d.id = "a-owner"; $("a-hkamar").after(d); }
    const bantuan = (inputId) => $(inputId).closest(".pm-f").querySelector(".pm-help");
    if (!$("a-hhp")) bantuan("a-hp").id = "a-hhp";
    if (!$("a-htgl")) bantuan("a-tgl").id = "a-htgl";
    const btn = $("a-simpan");
    if (!btn.querySelector(".lbl")) {
      btn.innerHTML = `<span id="a-simpan-ic" hidden>${ic("user-plus2")}</span><span class="lbl">Simpan Penghuni</span>`;
    } else if (!$("a-simpan-ic")) {
      btn.insertAdjacentHTML("afterbegin", `<span id="a-simpan-ic" hidden>${ic("user-plus2")}</span>`);
    }
  })();

  function renderPick(m) {
    const k = selKamar(m);
    $(m.p + "-pick").innerHTML = k
      ? `${ic("home")}<span>${kamarName(k)}</span>${badge(k, exId(m))}<span class="grow"></span>${ic("chev", "i ch")}`
      : `${ic("home")}<span class="ph">${m.p === "a" ? "Pilih kamar yang akan ditempati oleh penyewa." : "Pilih kamar"}</span><span class="grow"></span>${ic("chev", "i ch")}`;
  }
  function renderList(m) {
    $(m.p + "-list").innerHTML = state.rooms.length
      ? state.rooms.map((k) => {
          const penuh = kamarCtx(k.id, exId(m)).penuh && k.id !== m.kamarId;
          return `<button type="button" role="option" data-k="${k.id}" aria-selected="${k.id === m.kamarId}" ${penuh ? "disabled" : ""}>${ic("home")}<span>${kamarName(k)}</span>${badge(k, exId(m))}</button>`;
        }).join("")
      : '<p class="pm-help" style="padding:10px">Belum ada kamar.</p>';
  }
  function toggleList(m, open) {
    const list = $(m.p + "-list");
    list.hidden = !open; $(m.p + "-pick").setAttribute("aria-expanded", open);
    if (open) { renderList(m); list.scrollTop = 0; } // selalu mulai dari kamar paling atas
  }

  // Tanggal: teks terformat ("15 Feb 2024") di atas <input type=date> transparan
  function setDate(prefix, iso) {
    $(prefix + "-tgl").value = iso || "";
    const v = $(prefix + "-tgl-v");
    v.textContent = iso ? tgl(iso) : "Pilih tanggal"; v.classList.toggle("ph", !iso);
  }
  ["a", "e"].forEach((p) => {
    $(p + "-tgl").addEventListener("change", (e) => setDate(p, e.target.value));
    $(p + "-tgl").addEventListener("click", (e) => { try { e.target.showPicker(); } catch (_) {} });
  });

  function nameError(p, on, msg) {
    $(p + "-wnama").classList.toggle("has-err", on);
    const h = $(p + "-hnama");
    h.classList.toggle("err", on);
    h.textContent = on ? msg : "Nama penghuni wajib diisi.";
  }
  function formError(p, msg) { $(p + "-error").textContent = msg || ""; $(p + "-error").hidden = !msg; }

  /* ---- Render isi modal TAMBAH: tampilan & peran mengikuti kondisi kamar yang dipilih ----
         belum pilih / kamar kosong  -> kotak info hijau, tombol "Simpan Penghuni"
         kamar sudah dihuni (1/2)    -> kartu penyewa utama + kartu peran oranye, tombol "Konfirmasi & Tambah Penghuni" */
  function ownerHtml(u) {
    return `<div class="pm-owner">
      <span class="pm-av">${esc(inisial(u.nama))}</span>
      <div>
        <div class="nm">${esc(u.nama)}<span class="tg">Penyewa Utama${u.mulai ? " Sejak " + esc(blnTahun(u.mulai)) : ""}</span></div>
        <div class="sub">WA: ${u.hp ? esc(fmtHp(u.hp)) : "belum diisi"}<span class="dot">•</span><b>Kontrak Aktif</b></div>
      </div>
      <span class="rt">Pemegang Kontrak Utama</span>
    </div>`;
  }
  function renderAdd() {
    const m = modals.add, k = selKamar(m);
    const c = k ? kamarCtx(k.id) : { count: 0, utama: null, penuh: false };
    const terisi = !!(k && c.count);
    const noK = k ? esc(fmtNo(k.no_kamar)) : "";

    m.root().classList.toggle("is-terisi", terisi);
    m.label = terisi ? "Konfirmasi & Tambah Penghuni" : "Simpan Penghuni";

    // Bagian 1: kartu penyewa utama kamar (hanya bila kamar sudah dihuni)
    $("a-owner").innerHTML = terisi ? ownerHtml(c.utama) : "";

    // Bagian 2: teks bantuan mengikuti kondisi kamar
    $("a-hhp").textContent = terisi ? "Nomor HP dapat dihubungi melalui WhatsApp." : "Nomor HP dapat dilengkapi nanti.";
    $("a-htgl").textContent = terisi ? "Tanggal masuk disesuaikan dengan periode tinggal." : "Tanggal masuk dapat ditambahkan setelah data tersimpan.";

    // Bagian 3: peran penghuni
    if (!k) {
      $("a-peran").innerHTML = `<div class="pm-info">${ic("info")}<span>Pilih kamar terlebih dahulu. Peran penghuni ditentukan otomatis dari kamar yang dipilih.</span></div>`;
    } else if (!terisi) {
      $("a-peran").innerHTML = `<div class="pm-info">${ic("info")}<span>Karena kamar <b>${noK}</b> belum memiliki penyewa aktif, penghuni ini akan otomatis menjadi <b>Penyewa Utama.</b></span></div>`;
    } else {
      $("a-peran").innerHTML = `<div class="pm-role">
        <span class="ck">${ic("tick")}</span>
        <div>
          <div class="tt">Penyewa Tambahan</div>
          <div class="ds">Kamar ${noK} telah memiliki Penyewa Utama (${esc(c.utama.nama)}). Penghuni baru ini otomatis ditetapkan sebagai <em>Penyewa Tambahan (Teman Sekamar)</em>.</div>
        </div>
      </div>`;
    }

    // Tombol simpan: ikon + label
    $("a-simpan-ic").hidden = !terisi;
    if (!m.saving) $("a-simpan").querySelector(".lbl").textContent = m.label;

    kamarError(false);
    formError("a", c.penuh ? `Kamar ${noK} sudah penuh (${c.count}/${KAPASITAS} penghuni).` : "");
    $("a-simpan").disabled = c.penuh;
  }
  function kamarError(on) {
    $("a-pick").classList.toggle("has-err", on);
    $("a-hkamar").classList.toggle("err", on);
  }

  /* ---- Render isi modal EDIT (skenario 2) ---- */
  function renderEdit() {
    const m = modals.edit, k = selKamar(m), row = m.row;
    const c = k ? kamarCtx(k.id, row.id) : { count: 0, list: [], utama: null };
    const sama = k && k.id === row.id_kamar;                 // kamar tidak diganti -> peran tetap
    // Satu-satunya penghuni aktif di kamar ini -> otomatis penyewa utama (walau flag di data masih 0)
    const sendiri = !!(sama && c.count === 0);
    // Boleh dipilih jadi penyewa utama: penghuni tambahan aktif, kamar tidak diganti, dan ada penyewa utama lain
    const bisaPilih = !!(sama && row.status === "aktif" && !row.utama && !sendiri);
    if (!bisaPilih) m.jadikanUtama = false;
    const utama = sama ? !!(row.utama || sendiri || m.jadikanUtama) : c.count === 0;
    // Perlu memanggil endpoint penyewa-utama saat simpan?
    m.kirimUtama = !!(sama && row.status === "aktif" && !row.utama && (sendiri || m.jadikanUtama));
    const rekan = c.utama;                                   // penyewa utama kamar (selain yang diedit)
    const noK = k ? fmtNo(k.no_kamar) : "";
    const namaRekan = rekan ? esc(rekan.nama) : "-";

    $("e-hkamar").innerHTML = c.count
      ? `Kamar ini sudah terisi ${c.count} penghuni aktif (${esc(c.list.map((r) => r.nama).join(", "))}). Penambahan penghuni berikutnya akan terdaftar sebagai <b>Penyewa Tambahan</b>.`
      : "Pilih kamar yang akan ditempati oleh penyewa.";

    // Kotak info: gaya & warna yang sama (kuning) untuk kedua peran
    let pesan;
    if (utama && m.jadikanUtama && rekan) {
      pesan = `Setelah disimpan, <b>${esc(row.nama)}</b> menjadi <b>Penyewa Utama</b> kamar ${esc(noK)} dan <b>${namaRekan}</b> menjadi <em>Penghuni Tambahan</em>.`;
    } else if (utama) {
      pesan = `Penghuni ini adalah <b>Penyewa Utama</b> kamar ${esc(noK)} dan menjadi pemegang kontrak tagihan.`;
    } else {
      pesan = `Kamar ${esc(noK)} telah memiliki Penyewa Utama (${namaRekan}). Penghuni ini ditetapkan sebagai <em>Penyewa Tambahan (Teman Sekamar)</em>.${bisaPilih ? " Klik kartu <b>Penyewa Utama</b> di bawah bila ingin menjadikannya penyewa utama." : ""} Tagihan utama tetap dibebankan kepada Penyewa Utama.`;
    }
    $("e-info").innerHTML = `<div class="pm-info pm-info--warn">${ic("clock")}<span>${pesan}</span></div>`;

    // Kartu peran: posisi TETAP (Tambahan di kiri, Utama di kanan); yang terpilih selalu bergaya sama (oranye)
    const kartu = (role, judul, subOn, tag, subKlik, subKunci) => {
      const on = role === "utama" ? utama : !utama;
      if (on) return `<div class="pm-rc is-on"><span class="ic">${ic("tick")}</span><span><span class="tt">${judul}</span><span class="ss">${subOn}</span></span><span class="tag">${tag}</span></div>`;
      if (bisaPilih) return `<button type="button" class="pm-rc" data-role="${role}"><span class="ic">${ic("link2")}</span><span><span class="tt">${judul}</span><span class="ss">${subKlik}</span></span></button>`;
      return `<div class="pm-rc"><span class="ic">${ic("link2")}</span><span><span class="tt">${judul}</span><span class="ss">${subKunci}</span></span></div>`;
    };
    $("e-roles").innerHTML =
      kartu("tambahan", "Penyewa Tambahan", "Non-pemegang kontrak tagihan", "Teman<br>Sekamar",
            "Klik untuk memilih peran ini", "Kamar harus memiliki satu penyewa utama") +
      kartu("utama", "Penyewa Utama", "Pemegang kontrak tagihan", "Pemegang<br>Kontrak",
            `Klik untuk menggantikan ${namaRekan}`, `Sudah terisi oleh ${namaRekan}`);

    // Baris "Tertaut ke" / keterangan pergantian
    const link = $("e-link");
    if (!utama) {
      link.hidden = false;
      link.innerHTML = `Tertaut ke: <b>${ic("user")}${namaRekan} (Penyewa Utama / Kamar ${esc(noK)})</b>`;
    } else if (m.jadikanUtama && rekan) {
      link.hidden = false;
      link.innerHTML = `Menjadi penghuni tambahan: <b>${ic("user")}${namaRekan} (Kamar ${esc(noK)})</b>`;
    } else {
      link.hidden = true;
    }
  }

  const renderModal = (m) => { renderPick(m); (m === modals.add ? renderAdd : renderEdit)(); };

  async function ensureRooms() {
    try { state.rooms = (await loadRooms()).sort((a, b) => String(a.no_kamar).localeCompare(String(b.no_kamar), undefined, { numeric: true })); }
    catch (e) { state.rooms = []; }
  }
  // Buka modal selalu dari posisi paling atas (overlay, isi modal, dan daftar kamar di-reset)
  function show(m) {
    const root = m.root();
    root.hidden = false;
    root.scrollTop = 0;
    const body = root.querySelector(".pm-body"); if (body) body.scrollTop = 0;
    $(m.p + "-list").scrollTop = 0;
    document.body.style.overflow = "hidden";
  }
  function close(m) { m.root().hidden = true; toggleList(m, false); document.body.style.overflow = ""; }

  async function openAdd() {
    const m = modals.add; m.saving = false; m.row = null; m.kamarId = null; m.idPenghuni = null;
    $("a-nama").value = ""; $("a-hp").value = ""; setDate("a", "");
    nameError("a", false); kamarError(false); formError("a"); toggleList(m, false); show(m);
    renderModal(m);
    await ensureRooms();
    renderModal(m);
    $("a-pick").focus({ preventScroll: true });
  }

  async function openEdit(id) {
    const row = state.rows.find((r) => r.id === id); if (!row) return;
    const m = modals.edit; m.saving = false; m.row = row; m.kamarId = row.id_kamar;
    $("e-nama").value = row.nama; $("e-hp").value = row.hp; setDate("e", row.mulai); m.jadikanUtama = false;
    nameError("e", false); formError("e"); toggleList(m, false); show(m);
    await ensureRooms();
    // kamar milik penghunian ini harus tetap ada di daftar
    if (!state.rooms.some((k) => k.id === row.id_kamar)) state.rooms.unshift({ id: row.id_kamar, no_kamar: row.no_kamar, lantai: row.lantai, status: "terisi" });
    renderModal(m);
    // Sengaja TIDAK memfokuskan kolom nama: kursor "|" tidak langsung muncul, user klik sendiri kolom yang mau diubah.
  }

  function apiError(json, status) {
    const first = json.errors && Object.values(json.errors)[0];
    const err = new Error("HTTP " + status);
    err.userMessage = (Array.isArray(first) ? first[0] : first) || json.message;
    return err;
  }
  async function kirim(url, method, body) {
    let res;
    try {
      res = await fetch(url, { method, headers: JSON_HEADERS, body: JSON.stringify(body) });
    } catch (_) {
      // fetch gagal total = tidak ada koneksi / server tidak terjangkau
      const err = new Error("offline");
      err.userMessage = "Koneksi terputus. Periksa jaringan Anda, lalu tekan Simpan lagi. Isian Anda tidak hilang.";
      throw err;
    }
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw apiError(json, res.status);
    return json;
  }
  function setSaving(m, on, label) {
    m.saving = on; const b = $(m.p + "-simpan"); b.disabled = on;
    const teks = b.querySelector(".lbl") || b.querySelector("span");
    teks.textContent = on ? "Menyimpan…" : (label || m.label || "Simpan Penghuni");
  }

  async function simpanAdd() {
    const m = modals.add; if (m.saving) return;
    const nama = $("a-nama").value.trim(); formError("a");
    const salahKamar = !m.kamarId, salahNama = !nama;
    kamarError(salahKamar); nameError("a", salahNama, "Nama penghuni wajib diisi.");
    if (salahKamar || salahNama) { (salahKamar ? $("a-pick") : $("a-nama")).focus(); return; }
    setSaving(m, true);
    try {
      // "Hubungan dengan penghuni utama" & konfirmasi hanya UI untuk saat ini (belum ada field di API).
      const dataOrang = { nama_penghuni: nama, no_telepon: $("a-hp").value.trim() || null };
      if (m.idPenghuni) {
        // langkah 2 gagal sebelumnya: pakai penghuni yang sama, jangan buat entri baru
        await kirim(`/api/penghuni/${m.idPenghuni}`, "PUT", dataOrang);
      } else {
        const j1 = await kirim("/api/penghuni", "POST", dataOrang);
        m.idPenghuni = j1.data.id_penghuni;
      }
      const body = { id_kamar: m.kamarId, id_penghuni: m.idPenghuni };
      if ($("a-tgl").value) body.tanggal_mulai = $("a-tgl").value;
      const j2 = await kirim("/api/penghunian", "POST", body);
      m.idPenghuni = null;
      close(m); state.tab = "aktif"; syncTabs(); refresh();
      tampilKonfirmasi(j2.data);
    } catch (e) { formError("a", e.userMessage || "Gagal menyimpan data. Coba lagi."); }
    finally { setSaving(m, false); }
  }

  async function simpanEdit() {
    const m = modals.edit, row = m.row; if (m.saving) return;
    const nama = $("e-nama").value.trim(); formError("e");
    if (!nama) { nameError("e", true, "Nama penghuni wajib diisi."); $("e-nama").focus(); return; }
    nameError("e", false); setSaving(m, true);
    try {
      await kirim(`/api/penghuni/${row.id_penghuni}`, "PUT", { nama_penghuni: nama, no_telepon: $("e-hp").value.trim() || null });
      const tglBaru = $("e-tgl").value || row.mulai;
      if (m.kamarId !== row.id_kamar || tglBaru !== row.mulai) {
        const body = { id_kamar: m.kamarId, id_penghuni: row.id_penghuni };
        if (tglBaru) body.tanggal_mulai = tglBaru;
        await kirim(`/api/penghunian/${row.id}`, "PUT", body);
      }
      if (m.kirimUtama) {
        await kirim(`/api/penghunian/${row.id}/penyewa-utama`, "PUT", { id_kamar: row.id_kamar });
      }
      close(m); toast("Data penyewa berhasil diperbarui."); refresh();
    } catch (e) { formError("e", e.userMessage || "Gagal menyimpan data. Coba lagi."); }
    finally { setSaving(m, false, "Simpan Penghuni"); }
  }

  /* ---------------------------------------------------------
     Popup pintasan setelah simpan: "Catat DP sekarang" -> menu Pembayaran & Tagihan
  --------------------------------------------------------- */
  function tampilKonfirmasi(p) {
    const nama = p.penghuni?.nama_penghuni ?? "Penghuni";
    const noK = fmtNo(p.kamar?.no_kamar ?? "");
    const utama = !!p.penyewa_utama;
    // Tombol tampil hanya bila: penyewa utama DAN kamar belum punya pembayaran DP.
    // `kamar_sudah_ada_dp` dikirim backend di response POST /api/penghunian.
    const sudahDp = p.kamar_sudah_ada_dp === true;
    $("k-pesan").textContent = `${nama} tersimpan di Kamar ${noK}${utama ? " sebagai penyewa utama." : " sebagai penghuni tambahan."}`;

    const kurang = [];
    if (!p.penghuni?.no_telepon) kurang.push("nomor HP");
    if (!p.tanggal_mulai) kurang.push("tanggal masuk");
    $("k-info").hidden = !kurang.length;
    $("k-info").textContent = kurang.length ? `Belum diisi: ${kurang.join(" dan ")}. Bisa dilengkapi nanti lewat tombol ubah.` : "";

    // Tautan ke menu Pembayaran & Tagihan; kamar & jenis DP dibawa lewat parameter agar form terisi otomatis
    const a = $("k-dp");
    a.hidden = !(utama && !sudahDp);
    a.href = `${$("pk").dataset.url}?${new URLSearchParams({ kamar: p.id_kamar, penghunian: p.id_penghunian, jenis: "DP" })}`;
    $("pk").hidden = false;
  }
  const tutupKonfirmasi = () => { $("pk").hidden = true; };

  /* ---------------------------------------------------------
     Event
  --------------------------------------------------------- */
  function syncTabs() {
    document.querySelectorAll(".pg-tab").forEach((b) => b.setAttribute("aria-selected", b.dataset.tab === state.tab));
  }

  document.querySelectorAll(".pg-tab").forEach((b) => b.addEventListener("click", () => { state.tab = b.dataset.tab; state.page = 1; state.showAll = false; syncTabs(); renderTable(); }));
  // Pencarian: tunggu user berhenti mengetik sebentar, lalu saring di browser
  let qTimer;
  $("f-q").addEventListener("input", () => { clearTimeout(qTimer); qTimer = setTimeout(() => { state.page = 1; state.showAll = false; renderTable(); }, 150); });
  initFilterPanel();

  $("pg-pager").addEventListener("click", (e) => {
    if (e.target.closest("button[data-all]")) { state.showAll = true; state.page = 1; renderTable(); return; }
    const b = e.target.closest("button[data-p]");
    if (b && !b.disabled) { state.showAll = false; state.page = Number(b.dataset.p); renderTable(); }
  });
  $("pg-state").addEventListener("click", (e) => {
    if (e.target.id === "btn-retry") refresh();
    if (e.target.id === "btn-kosong-tambah") openAdd();
    if (e.target.id === "btn-reset-filter") { $("f-q").value = ""; $("fp-reset").click(); }  // kosongkan pencarian + semua filter
  });

  $("pg-rows").addEventListener("click", (e) => {
    const b = e.target.closest("button[data-act]");
    if (!b) return;
    const tr = b.closest("tr"), id = Number(tr.dataset.id), act = b.dataset.act;
    closeMenus();
    if (act === "menu") {
      e.stopPropagation();
      const m = document.createElement("div");
      m.className = "menu"; m.setAttribute("role", "menu");
      m.innerHTML = '<button type="button" role="menuitem" data-end>Akhiri penghunian</button>';
      m.querySelector("[data-end]").addEventListener("click", () => { closeMenus(); akhiri(id); });
      b.parentElement.appendChild(m);
    } else {
      if (act === "ubah") openEdit(id);
    }
  });
  document.addEventListener("click", closeMenus);

  $("btn-tambah").addEventListener("click", openAdd);

  Object.values(modals).forEach((m) => {
    const p = m.p;
    $(p + "-x").addEventListener("click", () => close(m));
    $(p + "-batal").addEventListener("click", () => close(m));
    $(m.root().id).addEventListener("mousedown", (e) => { if (e.target === m.root()) close(m); });
    $(p + "-nama").addEventListener("input", () => nameError(p, false));
    $(p + "-pick").addEventListener("click", () => toggleList(m, $(p + "-list").hidden));
    $(p + "-list").addEventListener("click", (e) => {
      const b = e.target.closest("button[data-k]");
      if (!b || b.disabled) return;
      m.kamarId = Number(b.dataset.k); toggleList(m, false); renderModal(m);
    });
    document.addEventListener("click", (e) => { if (!e.target.closest("#" + p + "-sel")) toggleList(m, false); });
  });
  $("e-roles").addEventListener("click", (e) => {
    const b = e.target.closest("button[data-role]");
    if (!b) return;
    modals.edit.jadikanUtama = b.dataset.role === "utama";
    renderEdit();
  });
  $("a-simpan").addEventListener("click", simpanAdd);
  $("e-simpan").addEventListener("click", simpanEdit);
  $("k-x").addEventListener("click", tutupKonfirmasi);
  $("k-selesai").addEventListener("click", tutupKonfirmasi);
  $("pk").addEventListener("mousedown", (e) => { if (e.target === $("pk")) tutupKonfirmasi(); });
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    if (!$("pk").hidden) tutupKonfirmasi();
    Object.values(modals).forEach((m) => {
      if (m.root().hidden) return;
      if (!$(m.p + "-list").hidden) toggleList(m, false); else close(m);
    });
  });

  refresh();
})();