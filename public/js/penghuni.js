// Halaman Data Penyewa & Arsip  (resources/views/penghuni.blade.php)
// Bergantung pada rooms-api.js (dimuat lebih dulu): loadRooms(), fmtNo(), fmtTanggal(), lantaiDariNomor().
//
// Endpoint (kontrak API Kelompok 1):
//   GET   /api/penghunian               -> daftar penghunian (+ relasi kamar & penghuni)
//   GET   /api/kamar                    -> lewat loadRooms() di rooms-api.js
//   POST  /api/penghuni                 -> buat penghuni baru
//   POST  /api/penghunian               -> catat penghuni menempati kamar
//   PATCH /api/penghunian/{id}/selesai  -> akhiri penghunian (RANCANGAN: belum ada di routes/api.php)
(function () {
  "use strict";

  const PER_PAGE = 4; // desain: "Menampilkan 1–4 dari 9 penyewa"
  const state = { rows: [], rooms: [], tab: "aktif", page: 1, loading: true, error: false };
  const modal = { open: false, kamarId: null, saving: false };

  const $ = (id) => document.getElementById(id);
  const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const ic = (name, cls = "i") => `<svg class="${cls}"><use href="#i-${name}"/></svg>`;
  const JSON_HEADERS = { Accept: "application/json", "Content-Type": "application/json" };

  // "2026-11-05" -> "5 Nov 2026" (tanpa nol di depan, sesuai desain)
  const BLN = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
  const tgl = (iso) => {
    const [y, m, d] = String(iso || "").slice(0, 10).split("-");
    return y && BLN[m - 1] ? `${Number(d)} ${BLN[m - 1]} ${y}` : "";
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

  async function loadPenghunian() {
    const res = await fetch("/api/penghunian", { headers: { Accept: "application/json" } });
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
    const card = (iconL, label, n, iconR) => `
      <div class="pg-stat">
        <span class="ico">${ic(iconL)}</span>
        <span class="lbl">${label}</span>
        <div class="val"><b>${n}</b><span>Orang</span></div>
        <span class="ico br">${ic(iconR)}</span>
      </div>`;
    $("pg-stats").innerHTML =
      card("users", "Total Penyewa Aktif", aktif.length, "user-check") +
      card("user", "Penyewa Utama", utama, "crown") +
      card("users", "Penghuni Tambahan", aktif.length - utama, "user-plus2") +
      card("archive", "Data Diarsipkan", arsip.length, "file");
  }

  /* ---------------------------------------------------------
     Render: tabel + filter + pagination
  --------------------------------------------------------- */
  function filtered() {
    const q = $("f-q").value.trim().toLowerCase();
    const lt = $("f-lantai").value, peran = $("f-peran").value, st = $("f-status").value;
    return state.rows.filter((r) => {
      if ((state.tab === "aktif") !== (r.status === "aktif")) return false;
      if (q && !(r.nama.toLowerCase().includes(q) || String(r.no_kamar).toLowerCase().includes(q) || fmtNo(r.no_kamar).includes(q))) return false;
      if (lt && String(r.lantai) !== lt) return false;
      if (peran && (peran === "utama") !== r.utama) return false;
      if (st && (st === "lengkap") !== r.lengkap) return false;
      return true;
    });
  }

  const peranPill = (r) => r.utama
    ? `<span class="pill pill--ok pill--utama">${ic("crown")}Penyewa Utama</span>`
    : `<span class="pill pill--add">${ic("user")}<span>Penghuni<br>Tambahan</span></span>`;
  const statusPill = (r) => r.lengkap
    ? `<span class="pill pill--ok pill--lengkap">${ic("check")}Lengkap</span>`
    : `<span class="pill pill--warn">${ic("warn")}<span>Data belum<br>lengkap</span></span>`;

  function rowHtml(r, no) {
    const aktif = r.status === "aktif";
    return `<tr data-id="${r.id}">
      <td class="no">${no}</td>
      <td class="nama">${esc(r.nama)}</td>
      <td class="kamar"><b>Kamar ${esc(fmtNo(r.no_kamar))}</b>${r.lantai ? `<small>Lt. ${esc(r.lantai)}</small>` : ""}</td>
      <td class="hp">${r.hp ? esc(r.hp) : '<span class="empty">Belum diisi</span>'}</td>
      <td class="tgl">${r.mulai ? esc(tgl(r.mulai)) : '<span class="empty">Belum diisi</span>'}</td>
      <td class="ta-c">${peranPill(r)}</td>
      <td class="ta-c">${statusPill(r)}</td>
      <td class="aksi-td"><div class="aksi">
        <button type="button" data-act="lihat" aria-label="Lihat detail ${esc(r.nama)}">${ic("eye")}</button>
        <button type="button" data-act="ubah" aria-label="Ubah ${esc(r.nama)}">${ic("edit")}</button>
        ${aktif ? `<button type="button" data-act="menu" aria-label="Menu lainnya" aria-haspopup="menu">${ic("dots")}</button>` : `<span style="width:24px"></span>`}
      </div></td>
    </tr>`;
  }

  function renderTable() {
    const list = filtered();
    const pages = Math.max(1, Math.ceil(list.length / PER_PAGE));
    state.page = Math.min(state.page, pages);
    const start = (state.page - 1) * PER_PAGE;
    const slice = list.slice(start, start + PER_PAGE);
    const box = $("pg-state");

    if (state.loading) {
      $("pg-rows").innerHTML = ""; box.hidden = false; box.innerHTML = "<b>Memuat data…</b>Mohon tunggu sebentar.";
    } else if (state.error) {
      $("pg-rows").innerHTML = ""; box.hidden = false;
      box.innerHTML = '<b>Data penyewa gagal dimuat</b>Periksa koneksi atau coba lagi.<br><button type="button" id="btn-retry">Coba lagi</button>';
    } else if (!slice.length) {
      $("pg-rows").innerHTML = ""; box.hidden = false;
      box.innerHTML = state.tab === "aktif" ? "<b>Belum ada penyewa</b>Tidak ada data yang cocok." : "<b>Arsip kosong</b>Belum ada penyewa yang keluar dari kos.";
    } else {
      box.hidden = true;
      $("pg-rows").innerHTML = slice.map((r, i) => rowHtml(r, start + i + 1)).join("");
    }

    // Info jumlah
    const total = list.length, from = total ? start + 1 : 0, to = start + slice.length;
    $("pg-info").textContent = state.loading || state.error ? "\u00a0" : `Menampilkan ${from}–${to} dari ${total} penyewa`;

    // Pagination
    let nav = `<button type="button" data-p="${state.page - 1}" aria-label="Sebelumnya" ${state.page <= 1 ? "disabled" : ""}>${ic("chev-l")}</button>`;
    for (let p = 1; p <= pages; p++) nav += `<button type="button" data-p="${p}" class="${p === state.page ? "is-on" : ""}" ${p === state.page ? 'aria-current="page"' : ""}>${p}</button>`;
    nav += `<button type="button" data-p="${state.page + 1}" aria-label="Berikutnya" ${state.page >= pages ? "disabled" : ""}>${ic("chev-r")}</button>`;
    $("pg-pager").innerHTML = nav;
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
      const res = await fetch(`/api/penghunian/${id}/selesai`, { method: "PATCH", headers: JSON_HEADERS });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(json.message || "HTTP " + res.status);
      toast("Penghunian berhasil diakhiri."); refresh();
    } catch (e) {
      toast("Gagal mengakhiri penghunian: " + e.message, true);
    }
  }

  /* ---------------------------------------------------------
     Modal Tambah Penyewa
  --------------------------------------------------------- */
  const selKamar = () => state.rooms.find((k) => k.id === modal.kamarId);
  const adaPenghuniAktif = (idKamar) => state.rows.some((r) => r.id_kamar === idKamar && r.status === "aktif");
  const badge = (k) => `<span class="pm-badge ${k.status === "terisi" ? "pm-badge--busy" : ""}">${k.status === "terisi" ? "Terisi" : "Kosong"}</span>`;
  const kamarLabel = (k) => `<b style="font-weight:600">Kamar ${esc(fmtNo(k.no_kamar))} — Lantai ${esc(k.lantai)}</b>`;

  function renderPick() {
    const k = selKamar();
    $("pm-pick").innerHTML = k
      ? `${ic("bed")}${kamarLabel(k)}${badge(k)}<span class="grow"></span>${ic("chev", "i ch")}`
      : `${ic("bed")}<span style="color:#94A3B8">Pilih kamar</span><span class="grow"></span>${ic("chev", "i ch")}`;
  }

  function renderPeran() {
    const k = selKamar();
    const utama = !k || !adaPenghuniAktif(k.id);
    const no = k ? fmtNo(k.no_kamar) : "";
    $("pm-info").className = "pm-info" + (utama ? "" : " pm-info--add");
    $("pm-info").innerHTML = utama
      ? `${ic("info")}<span>Karena kamar ${esc(no)} belum memiliki penyewa aktif, penghuni ini akan otomatis menjadi <b>Penyewa Utama</b>.</span>`
      : `${ic("info")}<span>Kamar ${esc(no)} sudah memiliki penyewa utama, penghuni ini akan menjadi <b>Penghuni Tambahan</b>.</span>`;
    $("pm-peran").innerHTML = utama
      ? `<span class="tagp">${ic("crown")}Penyewa Utama</span>`
      : `<span class="tagp add">${ic("user")}Penghuni Tambahan</span>`;
  }

  function renderList() {
    $("pm-list").innerHTML = state.rooms.length
      ? state.rooms.map((k) => `<button type="button" role="option" data-k="${k.id}" aria-selected="${k.id === modal.kamarId}">${ic("bed", "i i-sm")}${kamarLabel(k)}${badge(k)}</button>`).join("")
      : '<p class="pm-help" style="padding:10px">Belum ada kamar.</p>';
  }

  const toggleList = (open) => {
    $("pm-list").hidden = !open; $("pm-pick").setAttribute("aria-expanded", open);
    if (open) renderList();
  };

  async function openModal() {
    modal.open = true; modal.saving = false; modal.kamarId = null;
    ["m-nama", "m-hp", "m-tgl"].forEach((id) => ($(id).value = ""));
    $("m-tgl").type = "text"; // placeholder "Pilih tanggal" tampil; berubah jadi date picker saat difokus
    setErr(false); $("pm-error").hidden = true; toggleList(false);
    $("pm").hidden = false; document.body.style.overflow = "hidden";
    try { state.rooms = (await loadRooms()).sort((a, b) => String(a.no_kamar).localeCompare(String(b.no_kamar), undefined, { numeric: true })); }
    catch (e) { state.rooms = []; }
    // Pilihan awal: kamar kosong pertama (seperti desain), bila tidak ada pakai kamar pertama.
    modal.kamarId = (state.rooms.find((k) => k.status === "kosong") || state.rooms[0])?.id ?? null;
    renderPick(); renderPeran(); $("m-nama").focus();
  }
  function closeModal() {
    modal.open = false; $("pm").hidden = true; document.body.style.overflow = "";
  }

  function setErr(on, msg) {
    $("w-nama").classList.toggle("has-err", on);
    $("h-nama").classList.toggle("err", on);
    $("h-nama").textContent = on ? msg : "Nama penghuni wajib diisi.";
  }
  function formError(msg) { $("pm-error").textContent = msg; $("pm-error").hidden = !msg; }

  async function simpan() {
    if (modal.saving) return;
    const nama = $("m-nama").value.trim();
    formError("");
    if (!nama) { setErr(true, "Nama penghuni wajib diisi."); $("m-nama").focus(); return; }
    if (!modal.kamarId) { formError("Pilih kamar terlebih dahulu."); return; }
    setErr(false);

    modal.saving = true; $("pm-simpan").disabled = true; $("pm-simpan").textContent = "Menyimpan…";
    try {
      // 1) buat penghuni
      const r1 = await fetch("/api/penghuni", { method: "POST", headers: JSON_HEADERS, body: JSON.stringify({ nama_penghuni: nama, no_telepon: $("m-hp").value.trim() || null }) });
      const j1 = await r1.json().catch(() => ({}));
      if (!r1.ok) throw apiError(j1, r1.status);

      // 2) catat penghunian di kamar yang dipilih (BE menentukan penyewa utama / tambahan)
      const body = { id_kamar: modal.kamarId, id_penghuni: j1.data.id_penghuni };
      if ($("m-tgl").value) body.tanggal_mulai = $("m-tgl").value;
      const r2 = await fetch("/api/penghunian", { method: "POST", headers: JSON_HEADERS, body: JSON.stringify(body) });
      const j2 = await r2.json().catch(() => ({}));
      if (!r2.ok) throw apiError(j2, r2.status);

      closeModal(); toast("Penyewa berhasil ditambahkan."); state.tab = "aktif"; syncTabs(); refresh();
    } catch (e) {
      formError(e.userMessage || "Gagal menyimpan data. Coba lagi.");
    } finally {
      modal.saving = false; $("pm-simpan").disabled = false; $("pm-simpan").textContent = "Simpan Penghuni";
    }
  }
  function apiError(json, status) {
    const first = json.errors && Object.values(json.errors)[0];
    const err = new Error("HTTP " + status);
    err.userMessage = (Array.isArray(first) ? first[0] : first) || json.message;
    return err;
  }

  /* ---------------------------------------------------------
     Event
  --------------------------------------------------------- */
  function syncTabs() {
    document.querySelectorAll(".pg-tab").forEach((b) => b.setAttribute("aria-selected", b.dataset.tab === state.tab));
  }

  document.querySelectorAll(".pg-tab").forEach((b) => b.addEventListener("click", () => { state.tab = b.dataset.tab; state.page = 1; syncTabs(); renderTable(); }));
  ["f-lantai", "f-peran", "f-status"].forEach((id) => $(id).addEventListener("change", () => { state.page = 1; renderTable(); }));
  $("f-q").addEventListener("input", () => { state.page = 1; renderTable(); });

  $("pg-pager").addEventListener("click", (e) => {
    const b = e.target.closest("button[data-p]");
    if (b && !b.disabled) { state.page = Number(b.dataset.p); renderTable(); }
  });
  $("pg-state").addEventListener("click", (e) => e.target.id === "btn-retry" && refresh());

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
      toast(act === "lihat" ? "Halaman detail penyewa belum tersedia." : "Form ubah penyewa belum tersedia.");
    }
  });
  document.addEventListener("click", closeMenus);

  $("btn-tambah").addEventListener("click", openModal);
  $("pm-x").addEventListener("click", closeModal);
  $("pm-batal").addEventListener("click", closeModal);
  $("pm-simpan").addEventListener("click", simpan);
  $("pm").addEventListener("mousedown", (e) => { if (e.target === $("pm")) closeModal(); });
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    if (!$("pm-list").hidden) toggleList(false); else if (modal.open) closeModal();
  });
  $("m-nama").addEventListener("input", () => setErr(false));

  $("pm-pick").addEventListener("click", () => toggleList($("pm-list").hidden));
  $("pm-list").addEventListener("click", (e) => {
    const b = e.target.closest("button[data-k]");
    if (!b) return;
    modal.kamarId = Number(b.dataset.k); toggleList(false); renderPick(); renderPeran();
  });
  document.addEventListener("click", (e) => { if (!e.target.closest("#pm-sel")) toggleList(false); });

  refresh();
})();

