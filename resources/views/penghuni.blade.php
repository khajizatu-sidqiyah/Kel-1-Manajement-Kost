@extends('layouts.dashboard')

@section('title', 'Data Penyewa & Arsip')

{{-- CSS khusus halaman (layout memuat @stack('styles') setelah dashboard.css) --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/penghuni.css') }}?v={{ filemtime(public_path('css/penghuni.css')) }}">
@endpush

@section('content')

    {{-- Ikon tambahan khusus halaman ini (sprite tersembunyi) --}}
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.8c2 .6 3.5 2.2 3.5 5.2"/></symbol>
        <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 21c0-4 3.4-6.5 7.5-6.5s7.5 2.5 7.5 6.5"/></symbol>
        <symbol id="i-user-check" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s5 1 6 2M16 17l2 2 4-4"/></symbol>
        <symbol id="i-crown" viewBox="0 0 24 24"><path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5zM5 22h14"/></symbol>
        <symbol id="i-archive" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8M10 13h4"/></symbol>
        <symbol id="i-file" viewBox="0 0 24 24"><path d="M6 3h8l5 5v13H6zM14 3v5h5M9 13h6M9 17h6"/></symbol>
        <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="i-edit" viewBox="0 0 24 24"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16zM13.5 6.5l4 4"/></symbol>
        <symbol id="i-dots" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="12" cy="19" r="1.2"/></symbol>
        <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
        <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></symbol>
        <symbol id="i-phone" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></symbol>
        <symbol id="i-warn" viewBox="0 0 24 24"><path d="M12 3 2 20h20zM12 10v4M12 17h.01"/></symbol>
        <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></symbol>
        <symbol id="i-bed" viewBox="0 0 24 24"><path d="M3 19V6M3 15h18v4M21 15v-3a3 3 0 0 0-3-3h-7v6M7 12h.01"/></symbol>
        <symbol id="i-chev-l" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></symbol>
        <symbol id="i-chev-r" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
        <symbol id="i-tick" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7"/></symbol>
        <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11l9-7 9 7M5 10v10h14V10M10 20v-6h4v6"/></symbol>
        <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
        <symbol id="i-link2" viewBox="0 0 24 24"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></symbol>
        <symbol id="i-user-plus2" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6M19 8v6M16 11h6"/></symbol>
    </svg>

    <div class="pg">

       

        {{-- Judul halaman + tombol tambah --}}
        <header class="pg-head">
            <span class="pg-tile"><svg class="i"><use href="#i-users"/></svg></span>
            <div class="pg-title">
                <h1>Data Penyewa &amp; Arsip</h1>
                <p>Kelola data penyewa aktif dan riwayat penyewa yang sudah keluar dari kos.</p>
            </div>
            <button type="button" class="btn-pg" id="btn-tambah"><svg class="i"><use href="#i-plus"/></svg>Tambah Penyewa</button>
        </header>

        {{-- 4 kartu ringkasan (diisi penghuni.js) --}}
        <section class="pg-stats" id="pg-stats" aria-label="Ringkasan penyewa"></section>

        {{-- Tab Penyewa Aktif / Arsip --}}
        <div class="pg-tabs" role="tablist">
            <button type="button" class="pg-tab" role="tab" data-tab="aktif" aria-selected="true">Penyewa Aktif</button>
            <button type="button" class="pg-tab" role="tab" data-tab="arsip" aria-selected="false">Arsip Penyewa</button>
        </div>

        {{-- Pencarian & filter --}}
        <div class="pg-filters">
            <label class="pg-search">
                <svg class="i"><use href="#i-search"/></svg>
                <input type="search" id="f-q" placeholder="Cari nama penyewa atau nomor kamar..." autocomplete="off">
            </label>
            <div class="pg-select w1">
                <select id="f-lantai" aria-label="Filter lantai">
                    <option value="">Semua Lantai</option><option value="1">Lantai 1</option><option value="2">Lantai 2</option>
                </select>
                <svg class="i"><use href="#i-chev"/></svg>
            </div>
            <div class="pg-select w2">
                <select id="f-peran" aria-label="Filter peran">
                    <option value="">Semua Peran</option><option value="utama">Penyewa Utama</option><option value="tambahan">Penghuni Tambahan</option>
                </select>
                <svg class="i"><use href="#i-chev"/></svg>
            </div>
            <div class="pg-select w3">
                <select id="f-status" aria-label="Filter status data">
                    <option value="">Status Data</option><option value="lengkap">Lengkap</option><option value="belum">Data belum lengkap</option>
                </select>
                <svg class="i"><use href="#i-chev"/></svg>
            </div>
        </div>

        {{-- Tabel penyewa --}}
        <section class="pg-table-card" aria-live="polite">
            <div class="pg-table-scroll">
                <table class="pg-table">
                    <colgroup>
                        <col class="c-no"><col class="c-nama"><col class="c-kamar"><col class="c-hp">
                        <col class="c-tgl"><col class="c-peran"><col class="c-status"><col class="c-aksi">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="no">No.</th>
                            <th>Nama<br>Penyewa</th>
                            <th>Kamar</th>
                            <th>No. HP</th>
                            <th>Tanggal<br>Masuk</th>
                            <th class="ta-c">Peran</th>
                            <th class="ta-c">Status Data</th>
                            <th class="ta-c aksi-td">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="pg-rows"></tbody>
                </table>
                <div id="pg-state" class="pg-state" hidden></div>
            </div>
        </section>

        {{-- Info jumlah + pagination --}}
        <footer class="pg-foot">
            <span id="pg-info">&nbsp;</span>
            <nav class="pager" id="pg-pager" aria-label="Halaman"></nav>
        </footer>
    </div>

    {{-- ===== Modal TAMBAH PENYEWA  (desain: skenario_3) ===== --}}
    <div class="pm" id="pm" hidden>
        <div class="pm-box" role="dialog" aria-modal="true" aria-labelledby="a-title">
            <div class="pm-head">
                <span class="pm-ico"><svg class="i"><use href="#i-user-plus2"/></svg></span>
                <div>
                    <h2 id="a-title">Tambah Penyewa</h2>
                    <p>Masukkan data penyewa baru. Data dapat dilengkapi kemudian.</p>
                </div>
                <button type="button" class="pm-x" id="a-x" aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button>
            </div>

            <div class="pm-body">
                {{-- 1. Informasi Kamar & Penghuni Saat Ini --}}
                <section class="pm-sec">
                    <h3><span class="pm-num">1</span>Informasi Kamar &amp; Penghuni Saat Ini</h3>
                    <div class="pm-sel" id="a-sel">
                        <button type="button" class="pm-pick" id="a-pick" aria-haspopup="listbox" aria-expanded="false" aria-label="Pilih kamar"></button>
                        <div class="pm-list" id="a-list" role="listbox" hidden></div>
                    </div>
                    <div class="pm-owner" id="a-owner" hidden></div>
                </section>

                {{-- Persetujuan penghuni utama (hanya tampil bila kamar sudah berpenghuni) --}}
                <section class="pm-sec" id="a-approve" hidden>
                    <h3><span class="pm-num pm-num--ck"><svg class="i"><use href="#i-tick"/></svg></span>Persetujuan / Konfirmasi Penghuni Utama<span class="pm-verif">Verifikasi Diperlukan</span></h3>
                    <div class="pm-opts">
                        <button type="button" class="pm-opt is-on" id="a-opt1">
                            <span class="ck"><svg class="i"><use href="#i-tick"/></svg></span>
                            <span><span class="tt">Telah Dikonfirmasi (Lisan / WA)</span><span class="ss" id="a-conf-time"></span></span>
                        </button>
                        <div class="pm-opt">
                            <span><span class="tt" id="a-wa-tt">Kirim Notifikasi WA</span><span class="ss">Kirim template persetujuan otomatis</span></span>
                            <button type="button" class="btn-wa" id="a-wa">Kirim WA</button>
                        </div>
                    </div>
                    <label class="pm-agree">
                        <input type="checkbox" id="a-setuju" checked><span class="cb"></span>
                        <span class="tx" id="a-agree-tx"></span>
                    </label>
                </section>

                {{-- 2. Informasi Penghuni Baru --}}
                <section class="pm-sec">
                    <h3><span class="pm-num">2</span>Informasi Penghuni Baru</h3>
                    <div class="pm-row">
                        <div class="pm-f">
                            <label for="a-nama">Nama Lengkap <i>*</i></label>
                            <div class="pm-in" id="a-wnama"><svg class="i"><use href="#i-user"/></svg>
                                <input type="text" id="a-nama" maxlength="100" placeholder="Masukkan nama lengkap penghuni." autocomplete="off">
                            </div>
                            <p class="pm-help" id="a-hnama">Nama lengkap sesuai tanda pengenal / KTP.</p>
                        </div>
                        <div class="pm-f">
                            <label for="a-hp">Nomor HP</label>
                            <div class="pm-in"><svg class="i"><use href="#i-phone"/></svg>
                                <input type="text" id="a-hp" maxlength="20" inputmode="tel" placeholder="Contoh: 081234567890" autocomplete="off">
                            </div>
                            <p class="pm-help">Nomor kontak aktif untuk akses WhatsApp.</p>
                        </div>
                    </div>
                    <div class="pm-row">
                        <div class="pm-f">
                            <label for="a-tgl">Tanggal Mulai Gabung / Masuk</label>
                            <div class="pm-in pm-date"><svg class="i"><use href="#i-calendar"/></svg>
                                <span class="dv ph" id="a-tgl-v">Pilih tanggal</span><svg class="i r"><use href="#i-calendar"/></svg>
                                <input type="date" id="a-tgl">
                            </div>
                        </div>
                        <div class="pm-f" id="a-hub-wrap">
                            <label for="a-hub">Hubungan dengan Penghuni Utama</label>
                            <div class="pm-in">
                                <select id="a-hub">
                                    <option>Teman / Rekan Kerja</option><option>Saudara / Keluarga</option><option>Pasangan</option><option>Lainnya</option>
                                </select>
                                <svg class="i r"><use href="#i-chev"/></svg>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- 3. Peran & Penyesuaian Biaya --}}
                <section class="pm-sec">
                    <h3><span class="pm-num">3</span>Peran &amp; Penyesuaian Biaya</h3>
                    <div id="a-peran"></div>
                </section>

                <p class="pm-error" id="a-error" role="alert" hidden></p>
            </div>

            <div class="pm-foot">
                <button type="button" class="pm-btn pm-btn--ghost" id="a-batal">Batal</button>
                <button type="button" class="pm-btn pm-btn--main" id="a-simpan"><svg class="i"><use href="#i-user-plus2"/></svg><span>Konfirmasi &amp; Tambah Penghuni</span></button>
            </div>
        </div>
    </div>

    {{-- ===== Modal EDIT PENYEWA / ikon pena  (desain: skenario_2) ===== --}}
    <div class="pm pe" id="pe" hidden>
        <div class="pm-box" role="dialog" aria-modal="true" aria-labelledby="e-title">
            <div class="pm-head">
                <span class="pm-ico"><svg class="i"><use href="#i-user-plus2"/></svg></span>
                <div>
                    <h2 id="e-title">Ubah Data Penyewa</h2>
                    <p>Perbarui data penyewa. Nomor HP dan tanggal masuk dapat dilengkapi kemudian.</p>
                </div>
                <button type="button" class="pm-x" id="e-x" aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button>
            </div>

            <div class="pm-body">
                <section class="pm-sec">
                    <h3><span class="pm-num">1</span>Informasi Kamar</h3>
                    <div class="pm-f">
                        <span class="lb" id="e-lkamar">Pilih Kamar <i>*</i></span>
                        <div class="pm-sel" id="e-sel">
                            <button type="button" class="pm-pick" id="e-pick" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="e-lkamar"></button>
                            <div class="pm-list" id="e-list" role="listbox" hidden></div>
                        </div>
                        <p class="pm-help pm-help--lg" id="e-hkamar"></p>
                    </div>
                </section>

                <section class="pm-sec">
                    <h3><span class="pm-num">2</span>Informasi Penghuni</h3>
                    <div class="pm-row">
                        <div class="pm-f">
                            <label for="e-nama">Nama Lengkap <i>*</i></label>
                            <div class="pm-in" id="e-wnama"><svg class="i"><use href="#i-user"/></svg>
                                <input type="text" id="e-nama" maxlength="100" placeholder="Masukkan nama lengkap penghuni." autocomplete="off">
                            </div>
                            <p class="pm-help" id="e-hnama">Nama penghuni wajib diisi.</p>
                        </div>
                        <div class="pm-f">
                            <label for="e-hp">Nomor HP</label>
                            <div class="pm-in"><svg class="i"><use href="#i-phone"/></svg>
                                <input type="text" id="e-hp" maxlength="20" inputmode="tel" placeholder="Contoh: 081234567890" autocomplete="off">
                            </div>
                            <p class="pm-help">Nomor HP dapat dihubungi melalui WhatsApp.</p>
                        </div>
                    </div>
                    <div class="pm-f" style="margin-top:14px">
                        <label for="e-tgl">Tanggal Masuk</label>
                        <div class="pm-in pm-date"><svg class="i"><use href="#i-calendar"/></svg>
                            <span class="dv ph" id="e-tgl-v">Pilih tanggal</span><svg class="i r"><use href="#i-calendar"/></svg>
                            <input type="date" id="e-tgl">
                        </div>
                        <p class="pm-help">Tanggal masuk disesuaikan dengan periode tinggal.</p>
                    </div>
                </section>

                <section class="pm-sec">
                    <h3><span class="pm-num">3</span>Peran Penghuni</h3>
                    <div id="e-info"></div>
                    <div class="pm-peran">
                        <span>Status Peran Penghuni</span>
                        <div class="pm-roles" id="e-roles"></div>
                        <div class="pm-link" id="e-link" hidden></div>
                    </div>
                </section>

                <p class="pm-error" id="e-error" role="alert" hidden></p>
            </div>

            <div class="pm-foot">
                <button type="button" class="pm-btn pm-btn--ghost" id="e-batal">Batal</button>
                <button type="button" class="pm-btn pm-btn--main" id="e-simpan"><span>Simpan Penghuni</span></button>
            </div>
        </div>
    </div>

    {{-- ===== Popup pintasan setelah penghuni tersimpan (di tengah layar) ===== --}}
    <div class="shortcut-wrap" id="pk" data-url="{{ url('/pembayaran') }}" hidden>
        <div class="shortcut" role="dialog" aria-modal="true" aria-labelledby="k-judul">
            <button type="button" class="shortcut-x" id="k-x" aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button>
            <span class="shortcut-ico"><svg class="i"><use href="#i-tick"/></svg></span>
            <div class="shortcut-body">
                <b id="k-judul">Data penghuni tersimpan</b>
                <p id="k-pesan"></p>
                <p class="shortcut-note" id="k-info" hidden></p>
                <div class="shortcut-act">
                    <button type="button" class="shortcut-btn shortcut-btn--ghost" id="k-selesai">Tutup</button>
                    <a class="shortcut-btn shortcut-btn--main" id="k-dp" href="{{ url('/pembayaran') }}" hidden>Catat DP sekarang</a>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    {{-- Urutan penting: rooms-api.js skrng menjadi kamar-api (loadRooms, fmtNo, lantaiDariNomor) -> penghuni.js --}}
    <script src="{{ asset('js/kamar-api.js') }}?v={{ filemtime(public_path('js/kamar-api.js')) }}"></script>
    <script src="{{ asset('js/penghuni.js') }}?v={{ filemtime(public_path('js/penghuni.js')) }}"></script>
@endpush
