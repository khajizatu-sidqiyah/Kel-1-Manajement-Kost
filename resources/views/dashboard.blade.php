<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Denah &amp; Status Kamar - KelolaKos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body class="app">

    <!-- Sprite ikon -->
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <symbol id="i-home" viewBox="0 0 24 24"><path d="M4 11l8-7 8 7v9H4zM10 20v-6h4v6"/></symbol>
        <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
        <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
        <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></symbol>
        <symbol id="i-receipt" viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6"/></symbol>
        <symbol id="i-tools" viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.7 2.7-2.3-.7-.7-2.3z"/></symbol>
        <symbol id="i-idcard" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.5-2 5.5-2 6 0M14 10h4M14 14h4"/></symbol>
        <symbol id="i-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/></symbol>
        <symbol id="i-building" viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"/></symbol>
        <symbol id="i-door" viewBox="0 0 24 24"><path d="M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17M3 21h18M14 12v1"/></symbol>
        <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 8l3 3"/></symbol>
        <symbol id="i-clip" viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v3H9zM9 12h6M9 16h6"/></symbol>
        <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 16v-5a6 6 0 0 1 12 0v5l2 2H4zM10 21h4"/></symbol>
        <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s7-6 7-11a7 7 0 0 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></symbol>
        <symbol id="i-chev" viewBox="0 0 24 24"><path d="m7 9 5 5 5-5"/></symbol>
        <symbol id="i-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></symbol>
        <symbol id="i-userplus" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6M19 8v6M16 11h6"/></symbol>
        <symbol id="i-snow" viewBox="0 0 24 24"><path d="M12 3v18M4 7.5l16 9M20 7.5l-16 9"/></symbol>
        <symbol id="i-shower" viewBox="0 0 24 24"><path d="M5 13a7 7 0 0 1 14 0zM8 17v2M12 17v2M16 17v2"/></symbol>
        <symbol id="i-logout" viewBox="0 0 24 24"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/></symbol>
    </svg>

    <div class="shell" id="shell">

        <!-- ========== SIDEBAR (drawer di mobile) ========== -->
        <aside class="sidebar" id="sidebar" aria-label="Menu utama">
            <div class="brand">
                <span class="brand-logo"><svg class="i"><use href="#i-home"/></svg></span>
                <span class="brand-text"><b>KelolaKos</b><small>PRO SYSTEM</small></span>
                <button type="button" class="icon-btn sidebar-close" id="sidebar-close" aria-label="Tutup menu"><svg class="i"><use href="#i-x"/></svg></button>
            </div>

            <button type="button" class="kost-switch">
                <svg class="i"><use href="#i-building"/></svg>
                <span><b>Kost Griya Harmoni</b><small>23 Kamar Aktif</small></span>
                <svg class="i i-sm"><use href="#i-chev"/></svg>
            </button>

            <p class="nav-label">OPERASIONAL KOS</p>
            <nav class="nav">
                <a href="{{ route('dashboard') }}" class="nav-item is-active"><svg class="i"><use href="#i-grid"/></svg>Denah &amp; Status Kamar</a>
                <a href="#" class="nav-item"><svg class="i"><use href="#i-receipt"/></svg>Pembayaran &amp; Tagihan</a>
                <a href="#" class="nav-item"><svg class="i"><use href="#i-tools"/></svg>Komplain &amp; Kerusakan</a>
                <a href="#" class="nav-item"><svg class="i"><use href="#i-idcard"/></svg>Data Penyewa &amp; Arsip</a>
                <a href="#" class="nav-item"><svg class="i"><use href="#i-gear"/></svg>Pengaturan Kos</a>
            </nav>

            <div class="sidebar-foot">
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="nav-item nav-logout"><svg class="i"><use href="#i-logout"/></svg>Keluar</button>
                </form>
                <div class="license">
                    <svg class="i"><use href="#i-check"/></svg>
                    <span><b>Lisensi Pro Aktif</b><small>23 Kamar Aktif</small></span>
                </div>
            </div>
        </aside>
        <div class="overlay" id="overlay"></div>

        <!-- ========== AREA UTAMA ========== -->
        <div class="main">
            <header class="topbar">
                <button type="button" class="icon-btn hamburger" id="hamburger" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                    <svg class="i"><use href="#i-menu"/></svg>
                </button>

                <button type="button" class="chip chip-kost"><svg class="i i-sm"><use href="#i-pin"/></svg>Kost Griya Harmoni (23 Kamar)<svg class="i i-sm"><use href="#i-chev"/></svg></button>
                <span class="topbar-info"><svg class="i i-sm"><use href="#i-check"/></svg>Otomatisasi Tagihan WhatsApp Aktif</span>

                <div class="topbar-right">
                    <button type="button" class="icon-btn bell" aria-label="Notifikasi"><svg class="i"><use href="#i-bell"/></svg><span class="bell-dot"></span></button>
                    <div class="user">
                        <span class="user-text"><b>{{ auth()->user()->name ?? 'Pemilik' }}</b><small>Pengelola Kos</small></span>
                        <span class="avatar">{{ strtoupper(mb_substr(auth()->user()->name ?? 'P', 0, 1)) }}</span>
                    </div>
                </div>
            </header>

            <main class="content">

                <!-- Judul halaman -->
                <section class="card page-head">
                    <span class="tile"><svg class="i"><use href="#i-grid"/></svg></span>
                    <div class="page-head-text">
                        <h1>Denah &amp; Status Kamar <span class="badge-live">Live Monitor</span></h1>
                        <p>Kost Griya Harmoni • Pembaruan otomatis 10 detik lalu</p>
                    </div>
                    <div class="page-head-actions">
                        <span class="sync"><i class="dot"></i>Auto-Sync Aktif</span>
                        <button type="button" class="btn-main"><svg class="i i-sm"><use href="#i-userplus"/></svg>Registrasi Penghuni Cepat</button>
                    </div>
                </section>

                <!-- Ringkasan -->
                <section class="stats" id="stats" aria-label="Ringkasan kamar"></section>

                <!-- Filter lantai + legenda -->
                <section class="card filters">
                    <div class="tabs" id="tabs" role="tablist"></div>
                    <div class="legend">
                        <span class="chip chip-soft"><i class="dot dot-green"></i>Hijau = Kosong <i class="sep"></i> <i class="dot dot-red"></i>Merah = Terisi</span>
                        <span class="chip chip-soft"><svg class="i i-sm"><use href="#i-snow"/></svg>Tipe AC (Semua)</span>
                        <span class="chip chip-soft" id="chip-km"><svg class="i i-sm"><use href="#i-shower"/></svg>KM Dalam</span>
                    </div>
                </section>

                <!-- Denah per lantai (diisi oleh denah.js) -->
                <div id="floors" aria-live="polite"></div>
            </main>
        </div>
    </div>

    <script src="{{ asset('js/dashboard.js') }}"></script>
    <script src="{{ asset('js/denah.js') }}"></script>
</body>
</html>
