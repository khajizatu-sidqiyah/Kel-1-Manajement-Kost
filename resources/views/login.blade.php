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
        <symbol id="i-arrow" viewBox="0 0 25 12"><path d="M0 6h23M18.5 1.2 24 6l-5.5 4.8"/></symbol>
        <!-- Logo Google (4 warna sesuai palet) & Apple -->
        <symbol id="i-google" viewBox="0 0 48 48">
            <path fill="#EA4335" stroke="none" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" stroke="none" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" stroke="none" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" stroke="none" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </symbol>
        <symbol id="i-apple" viewBox="0 0 24 24"><path fill="#131B2E" stroke="none" d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701"/></symbol>
    </svg>

    <main class="login" id="login" data-role="{{ $role ?? 'pemilik' }}" data-error="{{ $errors->first() }}">
        <div class="login-panel">
          <div class="login-box">

            <h1 class="login-title">Login {{ $roleName }}</h1>

            <form id="login-form" class="login-form" method="POST"
                action="{{ $isPemilik ? route('login.process') : route('login.penghuni.process') }}">
                @csrf
                <input type="hidden" name="role" value="{{ $role ?? 'pemilik' }}">

                <!-- Email / Username -->
                <div class="field" id="field-email">
                    <label for="email">
                        <span>Email / Username {{ $roleName }} <b class="req" aria-hidden="true">*</b></span>
                        <em>Format Terdaftar</em>
                    </label>
                    <div class="input-wrap">
                        <svg class="ic ic-left"><use href="#i-mail"/></svg>
                        <input type="text" id="email" name="email" value="{{ old('email') }}" placeholder="adminkos@gmail.com" autocomplete="username" required>
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
                <div class="login-error" id="login-error" role="alert" hidden>
                    <span></span>
                </div>

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
                        <span class="sso-ic"><svg viewBox="0 0 48 48"><use href="#i-google"/></svg></span>
                        <span class="txt-long">Masuk dengan Google</span><span class="txt-short">Google</span>
                    </button>
                    <button type="button" class="btn btn-sso">
                        <span class="sso-ic"><svg viewBox="0 0 24 24"><use href="#i-apple"/></svg></span>
                        <span class="txt-long">Masuk dengan Apple ID</span><span class="txt-short">Apple ID</span>
                    </button>
                </div>
            </section>

            
          </div>
        </div>
    </main>

    <script src="{{ asset('js/login.js') }}"></script>
</body>

</html>
