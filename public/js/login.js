// Interaksi UI dasar untuk halaman login.
// Validasi & proses autentikasi (2.1/2.2) ditambahkan di tahap berikutnya.

const roles = {
  pemilik: {
    title: "Login Pemilik",
    desc: "Masuk dengan kredensial pengelola atau pemilik properti kos.",
    label: "Email / Username Pemilik",
    pw: "Kata Sandi",
    placeholder: "pemilik@kostpro.id",
    button: "Masuk sebagai Pemilik",
  },
  penghuni: {
    title: "Login Penghuni",
    desc: "Gunakan nomor WhatsApp atau email yang terdaftar pada saat registrasi / pembayaran DP kamar.",
    label: "Email atau No. WhatsApp Terdaftar",
    pw: "Kata Sandi / PIN Kamar",
    placeholder: "kamar08.dewi@kostpro.id",
    button: "Masuk ke Portal Penghuni",
  },
};

const $ = (id) => document.getElementById(id);
const card = $("login-card");

// Ganti peran: mengubah panel kiri (lewat data-role) dan teks form
document.querySelectorAll(".tab").forEach((tab) => {
  tab.addEventListener("click", () => {
    const role = tab.dataset.role;
    const r = roles[role];
    card.dataset.role = role;
    document.querySelectorAll(".tab").forEach((t) => {
      t.classList.toggle("active", t === tab);
      t.setAttribute("aria-selected", t === tab);
    });
    $("form-title").textContent = r.title;
    $("form-desc").textContent = r.desc;
    $("label-email").textContent = r.label;
    $("label-pw").textContent = r.pw;
    $("email").placeholder = r.placeholder;
    $("btn-text").textContent = r.button;
  });
});

// Tampilkan / sembunyikan kata sandi
$("toggle-pw").addEventListener("click", () => {
  const input = $("password");
  const show = input.type === "password";
  input.type = show ? "text" : "password";
  $("toggle-pw").setAttribute("aria-label", show ? "Sembunyikan kata sandi" : "Tampilkan kata sandi");
});

// Isi otomatis akun uji coba
document.querySelectorAll("[data-demo]").forEach((btn) => {
  btn.addEventListener("click", () => {
    $("email").value = btn.dataset.demo;
    $("password").value = "sandi123";
  });
});

// Sementara: cegah reload saat submit (logika login menyusul di 2.1/2.2)
$("login-form").addEventListener("submit", (e) => e.preventDefault());


// =========================================================
// TAMPILAN LOGIN GAGAL
// Dipanggil saat backend menolak login (2.1/2.2), contoh:
//   showLoginError({ attempt: 1, max: 4 });
//   showLoginError({ attempt: 2, email: null, password: "Kata sandi tidak cocok." });
// Beri null pada email/password untuk tidak menandai kolom tersebut.
// =========================================================

function setFieldError(name, msg) {
  $("field-" + name).classList.toggle("has-error", !!msg);
  $(name + "-msg").textContent = msg || "";
  $(name + "-msg").hidden = !msg;
}

function showLoginError(opts = {}) {
  const {
    attempt = 1,
    max = 4,
    title = "Email atau password salah. Silakan periksa kembali Anda.",
    email = "Email ini tidak terdaftar dalam sistem.",
    password = "Kata sandi tidak cocok.",
  } = opts;
  const left = Math.max(max - attempt, 0);

  $("alert-title").textContent = title;
  $("alert-sub").textContent = left > 0
    ? `Sisa percobaan: ${left} kali sebelum akun dikunci sementara selama 15 menit.`
    : "Akun dikunci sementara selama 15 menit. Coba lagi nanti atau hubungi Super Admin.";
  $("attempt-badge").textContent = `Percobaan Gagal (${attempt}/${max})`;
  setFieldError("email", email);
  setFieldError("password", password);

  ["login-alert", "error-bar", "help-box", "switch-role"].forEach((id) => ($(id).hidden = false));
  card.classList.add("is-error");
}

function clearLoginError() {
  ["login-alert", "error-bar", "help-box", "switch-role"].forEach((id) => ($(id).hidden = true));
  setFieldError("email", null);
  setFieldError("password", null);
  card.classList.remove("is-error");
}

// Ikon mata ikut berganti saat kata sandi ditampilkan
$("toggle-pw").addEventListener("click", () => {
  const show = $("password").type === "text";
  $("toggle-pw").querySelector("use").setAttribute("href", show ? "#i-eyeoff" : "#i-eye");
});

// Hapus tanda error saat pengguna mengetik ulang / ganti peran
["email", "password"].forEach((n) => $(n).addEventListener("input", () => setFieldError(n, null)));
document.querySelectorAll(".tab").forEach((t) => t.addEventListener("click", clearLoginError));
$("go-penghuni").addEventListener("click", (e) => {
  e.preventDefault();
  document.querySelector('.tab[data-role="penghuni"]').click();
});

// Pratinjau tanpa backend: buka login.html?state=error
if (new URLSearchParams(location.search).get("state") === "error") {
  $("email").value = "pemilik.salah@kostpro.id";
  $("password").value = "1234567890";
  showLoginError({ attempt: 1, max: 4 });
}