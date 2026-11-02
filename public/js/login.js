// Interaksi UI halaman login (Pemilik & Penghuni) & Integrasi Token ke LocalStorage.

const $ = (id) => document.getElementById(id);
const login = $("login");

// ---------- Tampilkan / sembunyikan kata sandi ----------
$("toggle-pw").addEventListener("click", () => {
  const input = $("password");
  const show = input.type === "password";
  input.type = show ? "text" : "password";
  $("toggle-pw").setAttribute("aria-label", show ? "Sembunyikan kata sandi" : "Tampilkan kata sandi");
  $("toggle-pw").querySelector("use").setAttribute("href", show ? "#i-eyeoff" : "#i-eye");
});

// ---------- State login gagal ----------
function showLoginError({ email = true, password = true, message = "Kata sandi tidak cocok" } = {}) {
  $("field-email").classList.toggle("has-error", email);
  $("field-password").classList.toggle("has-error", password);
  $("login-error").querySelector("span").textContent = "! " + message;
  $("login-error").hidden = false;
  login.classList.add("is-error");
}

function clearLoginError() {
  ["field-email", "field-password"].forEach((id) => $(id).classList.remove("has-error"));
  $("login-error").hidden = true;
  login.classList.remove("is-error");
}

// Hapus tanda error begitu pengguna mengetik ulang
["email", "password"].forEach((id) => $(id).addEventListener("input", clearLoginError));

// Error dari server (Laravel)
if (login.dataset.error) {
  showLoginError({ message: login.dataset.error });
}

// Pratinjau tanpa backend: /login?state=error
if (new URLSearchParams(location.search).get("state") === "error") {
  $("email").value = "adminkos@gmail.com";
  $("password").value = "Kos126";
  $("toggle-pw").click();
  showLoginError();
}

// ---------- LOGIN: token API (localStorage) + sesi web (untuk middleware auth) ----------
const loginForm = $("login-form");
if (loginForm) {
  loginForm.addEventListener("submit", async (e) => {
    const role = login.dataset.role || "pemilik";

    // Penghuni: belum ada endpoint /api/login/penghuni,
    // jadi biarkan form submit biasa ke route web (pakai sesi).
    if (role === "penghuni") return;

    e.preventDefault();
    const btnSubmit = $("btn-submit");
    btnSubmit.disabled = true; // Cegah klik ganda

    try {
      const res = await fetch("/api/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify({ email: $("email").value, password: $("password").value })
      });

      const data = await res.json().catch(() => ({}));

      if (res.ok && data.data && data.data.token) {
        // 1) Simpan token untuk panggilan API dari halaman (pembayaran.js, dll.)
        localStorage.setItem("token", data.data.token);

        // 2) Buat sesi lewat route web POST /login. Server akan redirect ke /dashboard.
        //    form.submit() tidak memicu event "submit" lagi, jadi tidak looping.
        loginForm.submit();
      } else {
        btnSubmit.disabled = false;
        let errorMsg = "Email atau kata sandi tidak sesuai.";
        if (data.error && data.error.message) {
          errorMsg = data.error.message;
        } else if (data.message) {
          errorMsg = data.message;
        }
        showLoginError({ message: errorMsg });
      }
    } catch (err) {
      btnSubmit.disabled = false;
      showLoginError({ message: "Terjadi kesalahan pada koneksi server." });
    }
  });
}