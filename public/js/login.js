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

// ---------- INTEGRASI LOGIN API & PENYIMPANAN TOKEN KE LOCAL STORAGE ----------
const loginForm = $("login-form");
if (loginForm) {
  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    
    const email = $("email").value;
    const password = $("password").value;
    const role = login.dataset.role || "pemilik";
    
    // Sesuaikan endpoint dengan fungsi loginApi di Controller Anda
    const endpoint = role === "penghuni" ? "/api/login/penghuni" : "/api/login";

    try {
      const btnSubmit = $("btn-submit");
      btnSubmit.disabled = true; // Cegah klik ganda

      const res = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify({ email, password })
      });

      const data = await res.json().catch(() => ({}));

      // Sesuai format JSON dari loginApi Anda: data.data.token
      if (res.ok && data.data && data.data.token) {
        // SIMPAN TOKEN KE LOCAL STORAGE AGAR TIDAK 401 UNAUTHORIZED
        localStorage.setItem("token", data.data.token);

        // Arahkan ke halaman dashboard setelah sukses
        window.location.href = "/dashboard";
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
      $("btn-submit").disabled = false;
      showLoginError({ message: "Terjadi kesalahan pada koneksi server." });
    }
  });
}