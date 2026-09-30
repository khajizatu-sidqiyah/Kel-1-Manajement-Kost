@php
    // $role dikirim dari routes/web.php: 'pemilik' atau 'penghuni'
    $isPemilik = ($role ?? 'pemilik') === 'pemilik';
    $roleName  = $isPemilik ? 'Pemilik' : 'Penghuni';
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login {{ $roleName }} - Sistem Manajemen Kos</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>

<body class="page-login">

    <!-- Sprite ikon: <svg class="ic"><use href="#i-nama"/></svg> -->
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.5" r=".6"/></symbol>
        <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="i-eyeoff" viewBox="0 0 24 24"><path d="M3 3l18 18M10.5 6.2A10 10 0 0 1 12 5c6 0 10 7 10 7a17 17 0 0 1-3.2 4M6.5 7.5A17 17 0 0 0 2 12s4 7 10 7c1.7 0 3.2-.4 4.5-1.1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></symbol>
        <symbol id="i-xcircle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></symbol>
        <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M4 12h16m-6-6 6 6-6 6"/></symbol>
    </svg>

    <main class="login" id="login" data-role="{{ $role ?? 'pemilik' }}">
        <div class="login-panel">

            <h1 class="login-title">Login {{ $roleName }}</h1>

            <form id="login-form" class="login-form" method="POST" action="#" novalidate>
                @csrf

                <!-- Email / Username -->
                <div class="field" id="field-email">
                    <label for="email">
                        <span>Email / Username {{ $roleName }} <b class="req" aria-hidden="true">*</b></span>
                        <em>Format Terdaftar</em>
                    </label>
                    <div class="input-wrap">
                        <svg class="ic ic-left"><use href="#i-mail"/></svg>
                        <input type="text" id="email" name="email" placeholder="adminkos@gmail.com" autocomplete="username" required>
                        <svg class="ic ic-right ic-error" aria-hidden="true"><use href="#i-xcircle"/></svg>
                    </div>
                </div>

                <!-- Kata sandi -->
                <div class="field" id="field-password">
                    <label for="password">
                        <span>Kata Sandi <b class="req" aria-hidden="true">*</b></span>
                    </label>
                    <div class="input-wrap">
                        <svg class="ic ic-left"><use href="#i-lock"/></svg>
                        <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
                        <button type="button" class="toggle-pw" id="toggle-pw" aria-label="Tampilkan kata sandi">
                            <svg class="ic"><use href="#i-eye"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Pesan error (state gagal login) -->
                <p class="login-error" id="login-error" role="alert" hidden></p>

                <!-- Ingat sesi (desktop) + Lupa kata sandi -->
                <div class="row-options" id="row-options">
                    <label class="check">
                        <input type="checkbox" name="remember"> <span>Ingat sesi saya</span>
                    </label>
                    <a href="#" class="link">Lupa kata sandi ?</a>
                </div>

                <button type="submit" class="btn btn-primary" id="btn-submit">
                    <span class="txt-short">Login</span>
                    <span class="txt-long">Masuk Sebagai {{ $roleName }}</span>
                    <svg class="ic btn-arrow"><use href="#i-arrow"/></svg>
                </button>
            </form>

            <!-- SSO (disembunyikan saat state error) -->
            <section class="sso-block" id="sso-block">
                <div class="divider"><span>Atau masuk dengan SSO</span></div>

                <div class="sso">
                    <button type="button" class="btn btn-sso">
                        <span class="sso-ic">
                            <svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.6 5.9c4.4-4.1 7-10.1 7-17.6z"/><path fill="#FBBC05" d="M10.5 28.7c-.5-1.4-.8-3-.8-4.7s.3-3.2.8-4.7l-7.9-6.1C.9 16.5 0 20.1 0 24s.9 7.5 2.6 10.8l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
                        </span>
                        <span class="txt-long">Lanjutkan dengan Google</span>
                        <span class="txt-short">Google</span>
                    </button>

                    <button type="button" class="btn btn-sso">
                        <span class="sso-ic">
                            <svg viewBox="0 0 384 512" aria-hidden="true"><path fill="#111827" d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zm-56.6-164.2c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z"/></svg>
                        </span>
                        <span class="txt-long">Lanjutkan dengan Apple</span>
                        <span class="txt-short">Apple ID</span>
                    </button>
                </div>
            </section>

            <!-- Pindah peran (tidak ada di mockup; hapus blok ini bila tidak diperlukan) -->
            <p class="switch-role">
                @if ($isPemilik)
                    Penghuni kos? <a href="{{ route('login.penghuni') }}" class="link">Masuk sebagai Penghuni</a>
                @else
                    Pemilik kos? <a href="{{ route('login') }}" class="link">Masuk sebagai Pemilik</a>
                @endif
            </p>

        </div>
    </main>

    <script src="{{ asset('js/login.js') }}"></script>
</body>

</html>
