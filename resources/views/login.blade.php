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

    <main class="login" id="login" data-role="{{ $role ?? 'pemilik' }}" data-error="{{ $errors->first() }}">
        <div class="login-panel">

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

            
            <!-- Pindah peran -->
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
