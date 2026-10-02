// Kerangka dashboard: buka/tutup sidebar (hamburger) di layar < 1024px.
(function () {
  const shell = document.getElementById("shell");
  const btn = document.getElementById("hamburger");
  const setOpen = (open) => {
    shell.classList.toggle("is-open", open);
    btn.setAttribute("aria-expanded", open);
    document.body.style.overflow = open ? "hidden" : "";
  };
  btn.addEventListener("click", () => setOpen(!shell.classList.contains("is-open")));
  document.getElementById("overlay").addEventListener("click", () => setOpen(false));
  document.getElementById("sidebar-close").addEventListener("click", () => setOpen(false));
  document.addEventListener("keydown", (e) => e.key === "Escape" && setOpen(false));
  window.matchMedia("(min-width: 1024px)").addEventListener("change", (e) => e.matches && setOpen(false));
})();
