<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Manajemen Kos</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>

<body class="page-login">

    <!-- Sprite ikon (dipakai lewat <svg class="ic"><use href="#i-nama"/></svg>) -->
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></symbol>
        <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="m11 12 9-9m-3 3 3 3"/></symbol>
        <symbol id="i-wifi" viewBox="0 0 24 24"><path d="M2 9a15 15 0 0 1 20 0M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19" r="1"/></symbol>
        <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 4v11m-4-4 4 4 4-4M5 20h14"/></symbol>
        <symbol id="i-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></symbol>
        <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></symbol>
        <symbol id="i-building" viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 8h2M13 8h2M9 12h2M13 12h2M10 21v-4h4v4"/></symbol>
        <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol>
        <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></symbol>
        <symbol id="i-external" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M18 14v5H5V6h5"/></symbol>
        <symbol id="i-receipt" viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6"/></symbol>
        <symbol id="i-help" viewBox="0 0 24 24"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1"/><rect x="17" y="14" width="4" height="6" rx="1"/></symbol>
        <symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5h.01"/></symbol>
        <symbol id="i-xcircle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></symbol>
        <symbol id="i-eyeoff" viewBox="0 0 24 24"><path d="M3 3l18 18M10.5 6.2A10 10 0 0 1 12 5c6 0 10 7 10 7a17 17 0 0 1-3.2 4M6.5 7.5A17 17 0 0 0 2 12s4 7 10 7c1.7 0 3.2-.4 4.5-1.1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></symbol>
    </svg>

    <!-- HEADER -->
    <header class="header">
        <div class="brand">
            <div class="logo">K</div>
            <div class="brand-text">
                <h1>KostPro</h1>
                <span>Sistem Manajemen Kos</span>
            </div>
        </div>

        <nav class="navigation">
            <a href="login.html" class="active">Masuk Portal</a>
            <a href="#">Pusat Bantuan</a>
            <a href="#">Hubungi CS</a>
        </nav>

        <div class="lang-switch">
            <button type="button" class="active">ID</button>
            <button type="button">EN</button>
        </div>
    </header>

    <!-- KONTEN UTAMA -->
    <main class="main-content login-main">

        <div class="login-wrap">

        <!-- Bar state error -->
        <div class="error-bar" id="error-bar" hidden>
            <span class="crumb"><svg class="ic"><use href="#i-building"/></svg> KostPro Network <i>/</i> <b>Autentikasi Pengelola</b></span>
            <span class="badge-fail" id="attempt-badge">Percobaan Gagal (1/4)</span>
        </div>

        <div class="login-card" id="login-card" data-role="pemilik">

            <!-- PANEL KIRI -->
            <section class="login-aside">

                <div class="role-tabs" role="tablist">
                    <button type="button" role="tab" class="tab active" data-role="pemilik" aria-selected="true"><svg class="ic"><use href="#i-building"/></svg> Pemilik Kos</button>
                    <button type="button" role="tab" class="tab" data-role="penghuni" aria-selected="false"><svg class="ic"><use href="#i-user"/></svg> Penghuni</button>
                </div>

                <!-- Isi panel: PEMILIK -->
                <div data-only="pemilik">
                    <span class="badge-soft"><svg class="ic"><use href="#i-check"/></svg> Portal Resmi Pemilik Properti</span>
                    <h2>Sistem Manajemen Kos</h2>
                    <p>Kelola unit kamar, pantau status keterisian real-time, kelola tagihan sewa otomatis, dan tangani komplain penghuni dalam satu platform terpadu.</p>

                    <div class="preview-card" aria-hidden="true">
                        <div class="preview-head">
                            <div class="ph-left">
                                <span class="mini-ic"><svg class="ic"><use href="#i-building"/></svg></span>
                                <div><strong>24 Unit Aktif</strong><small>Pavilion Kartini 4B</small></div>
                            </div>
                            <div class="ph-right">
                                <div><span class="occupancy">94% Okupansi</span><small>+6% vs bulan lalu</small></div>
                                <svg class="ring" viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e9f7" stroke-width="3.5"/><circle cx="18" cy="18" r="15.9" fill="none" stroke="#15803d" stroke-width="3.5" stroke-dasharray="94 6" stroke-linecap="round" transform="rotate(-90 18 18)"/></svg>
                            </div>
                        </div>
                        <div class="room-grid">
                            <div class="room ok"><b>K-101</b><span>Tersedia</span></div>
                            <div class="room busy"><b>K-102</b><span>Terisi</span></div>
                            <div class="room busy"><b>K-103</b><span>Terisi</span></div>
                            <div class="room busy"><b>K-104</b><span>Terisi</span></div>
                            <div class="room busy"><b>K-201</b><span>Terisi</span></div>
                            <div class="room fix"><b>K-202</b><span>Repair</span></div>
                            <div class="room busy"><b>K-203</b><span>Terisi</span></div>
                            <div class="room ok"><b>K-204</b><span>Tersedia</span></div>
                        </div>
                        <div class="revenue">
                            <div><small>Arus Pendapatan Bulan Ini</small><strong>Rp 38.450.000</strong></div>
                            <svg class="spark" viewBox="0 0 100 30"><polyline points="0,24 12,22 22,25 34,18 46,20 58,14 70,17 82,10 94,8" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="94" cy="8" r="2.5" fill="#2563eb"/></svg>
                        </div>
                    </div>

                    <div class="trust">
                        <span class="avatars"><i>JK</i><i>SP</i><i>AL</i></span>
                        <span>Dipercaya lebih dari <b>1.400+</b> juragan properti di seluruh Indonesia.</span>
                    </div>
                </div>

                <!-- Isi panel: PENGHUNI -->
                <div data-only="penghuni">
                    <div class="logo-row">
                        <span class="logo-chip"><svg class="ic"><use href="#i-building"/></svg> KostPro</span>
                        <span class="badge-green">Portal Layanan Penghuni Kos</span>
                    </div>
                    <h2>Sistem Manajemen Kos</h2>
                    <p>Akses riwayat tagihan bulanan, unduh nota pembayaran resmi, laporkan kerusakan fasilitas kamar, dan nikmati kemudahan tinggal di kos impian.</p>

                    <div class="info-cards">
                        <div class="mini-card"><span class="mini-ic"><svg class="ic"><use href="#i-key"/></svg></span><div><small>Kunci Digital</small><strong>Kamar A-102</strong></div></div>
                        <div class="mini-card"><span class="mini-ic green"><svg class="ic"><use href="#i-wifi"/></svg></span><div><small>Wi-Fi Fiber</small><strong>KostPro-Ultra-5G</strong></div></div>
                    </div>

                    <div class="bill-card">
                        <span class="mini-ic"><svg class="ic"><use href="#i-receipt"/></svg></span>
                        <div><small>Tagihan Sewa Bulan Ini</small><strong>Rp 1.850.0</strong> <span class="tag-lunas">Lunas</span></div>
                        <button type="button" class="btn-nota"><svg class="ic"><use href="#i-download"/></svg> Nota</button>
                    </div>

                    <div class="ticket-card">
                        <div class="ticket-head"><span>Tiket Servis: AC Kurang Dingin</span><a href="#">Tiket #KP-208</a></div>
                        <div class="steps">
                            <div class="step done"><i>✓</i>Baru</div>
                            <div class="step now"><i>2</i>Diproses</div>
                            <div class="step"><i>3</i>Selesai</div>
                        </div>
                    </div>

                    <div class="aside-foot"><span><svg class="ic"><use href="#i-check"/></svg> Terhubung dengan 1.2+ unit kos aktif</span><span class="ver">KostPro v3.4.1</span></div>
                </div>

            </section>

            <!-- PANEL KANAN: FORM -->
            <section class="login-form-wrap">

                <h2 id="form-title">Login Pemilik</h2>
                <p class="form-desc" id="form-desc">Masuk dengan kredensial pengelola atau pemilik properti kos.</p>

                <div class="alert-error" id="login-alert" role="alert" hidden>
                    <svg class="ic"><use href="#i-alert"/></svg>
                    <div>
                        <strong id="alert-title">Email atau password salah. Silakan periksa kembali Anda.</strong>
                        <span id="alert-sub">Sisa percobaan: 3 kali sebelum akun dikunci sementara selama 15 menit.</span>
                    </div>
                </div>

                <form id="login-form" novalidate>

                    <div class="field" id="field-email">
                        <label for="email"><span id="label-email">Email / Username Pemilik</span><em data-only="pemilik">Format Terdaftar</em><em data-only="penghuni">Wajib diisi</em></label>
                        <div class="input-wrap ico">
                            <svg class="ic ic-left"><use href="#i-mail"/></svg>
                            <input type="text" id="email" name="email" placeholder="pemilik@kostpro.id" autocomplete="username">
                            <svg class="ic ic-x"><use href="#i-xcircle"/></svg>
                        </div>
                        <small class="field-msg" id="email-msg" hidden>Email ini tidak terdaftar dalam sistem.</small>
                    </div>

                    <div class="field" id="field-password">
                        <label for="password"><span id="label-pw">Kata Sandi</span><em data-only="pemilik">Min. 8 karakter</em><a href="#" class="link small" data-only="penghuni">Lupa sandi?</a></label>
                        <div class="input-wrap ico">
                            <svg class="ic ic-left"><use href="#i-lock"/></svg>
                            <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password">
                            <button type="button" class="toggle-pw" id="toggle-pw" aria-label="Tampilkan kata sandi"><svg class="ic"><use href="#i-eye"/></svg></button>
                        </div>
                        <small class="field-msg" id="password-msg" hidden>Kata sandi tidak cocok.</small>
                    </div>

                    <div class="notice" data-only="penghuni">
                        <svg class="ic"><use href="#i-info"/></svg>
                        <p><b>Belum punya kata sandi?</b> Penghuni baru yang telah bayar DP dapat mengaktifkan akun lewat tautan di WhatsApp resmi KostPro.</p>
                    </div>

                    <div class="row-between">
                        <label class="check"><input type="checkbox" name="remember"> Ingat sesi saya</label>
                        <a href="#" class="link" data-only="pemilik">Lupa kata sandi?</a>
                        <span class="safe" data-only="penghuni"><svg class="ic"><use href="#i-check"/></svg> Sesi Aman 30 Hari</span>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btn-submit"><span id="btn-text">Masuk sebagai Pemilik</span> <svg class="ic"><use href="#i-arrow"/></svg></button>

                </form>

                <div class="help-box" id="help-box" data-only="pemilik" hidden>
                    <span class="mini-ic"><svg class="ic"><use href="#i-help"/></svg></span>
                    <p>Kesulitan masuk? <a href="#" class="link">Reset kata sandi via email pemilik</a> atau hubungi <b>Super Admin</b>.</p>
                </div>
                <p class="switch-role" id="switch-role" data-only="pemilik" hidden>Bukan pemilik kos? <a href="#" class="link" id="go-penghuni">Masuk sebagai Penyewa Kamar →</a></p>

                <!-- SSO: hanya Pemilik -->
                <div data-only="pemilik" class="sso-block">
                    <div class="divider"><span>Atau masuk dengan SSO</span></div>
                    <div class="sso">
                        <button type="button" class="btn btn-secondary">
                            <svg class="brand-ic" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.200l7.9 6.100C12.4 13.6 17.7 9.5 24 9.500z"/><path fill="#4285F4" d="M46.5 24.500c0-1.6-.1-3.1-.4-4.500H24v9h12.700c-.6 3-2.3 5.5-4.8 7.200l7.6 5.900c4.4-4.1 7-10.1 7-17.600z"/><path fill="#FBBC05" d="M10.5 28.700c-.5-1.4-.8-3-.8-4.700s.3-3.2.8-4.700l-7.9-6.100C.9 16.5 0 20.1 0 24s.9 7.5 2.6 10.800l7.9-6.100z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.800l-7.6-5.900c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.800l-7.9 6.100C6.5 42.6 14.6 48 24 48z"/></svg>
                            Google Pro
                        </button>
                        <button type="button" class="btn btn-secondary">
                            <svg class="brand-ic" viewBox="0 0 384 512"><path fill="currentColor" d="M318.7 268.700c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.700C63.3 141.2 4 184.8 4 273.500q0 39.3 14.4 81.200c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.900zm-56.6-164.200c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.300z"/></svg>
                            Apple ID
                        </button>
                    </div>
                    <div class="demo-box">
                        <span>Akun Uji Coba: <b>pemilik@kostpro.id • sandi123</b></span>
                        <button type="button" class="btn-mini" data-demo="pemilik@kostpro.id">Gunakan</button>
                    </div>
                </div>

                <!-- Bawah form: hanya Penghuni -->
                <div data-only="penghuni">
                    <div class="demo-pill"><span>Akun Demo: <b>kamar08.dewi@kostpro.id / sandi123</b></span></div>
                    <div class="help-row">
                        <span><svg class="ic"><use href="#i-help"/></svg> Butuh bantuan check-in?</span>
                        <a href="#" class="link">Hubungi Pengelola Kos <svg class="ic"><use href="#i-external"/></svg></a>
                    </div>
                    <button type="button" class="btn-mini demo-fill" data-demo="kamar08.dewi@kostpro.id">Isi akun demo</button>
                </div>

            </section>

        </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <p>© 2026 Sistem Manajemen Kos</p>
    </footer>

    <script src="{{ asset('js/login.js') }}"></script>
</body>

</html>