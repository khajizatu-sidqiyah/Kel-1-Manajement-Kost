// Interaksi UI halaman login (Pemilik & Penghuni).
// Proses autentikasi ke backend ditambahkan di tahap berikutnya.

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
// Dipanggil saat backend menolak login, contoh:
//   showLoginError();                                   // kedua kolom ditandai merah
//   showLoginError({ email: false, message: "Kata sandi tidak cocok" });
function showLoginError({ email = true, password = true, message = "Kata sandi tidak cocok" } = {}) {
  $("field-email").classList.toggle("has-error", email);
  $("field-password").classList.toggle("has-error", password);
  $("login-error").textContent = "! " + message;
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

// Sementara: cegah reload saat submit (logika login menyusul)
$("login-form").addEventListener("submit", (e) => e.preventDefault());

// Pratinjau tanpa backend: /login?state=error  atau  /login/penghuni?state=error
if (new URLSearchParams(location.search).get("state") === "error") {
  $("email").value = "adminkos@gmail.com";
  $("password").value = "Kos126";
  showLoginError();
}
