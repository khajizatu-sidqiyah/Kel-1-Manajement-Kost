// public/js/network-status.js
document.addEventListener("DOMContentLoaded", () => {
    const banner = document.getElementById("offline-banner");

    function updateNetworkStatus() {
        const isOffline = !navigator.onLine;
        
        if (banner) {
            banner.style.display = isOffline ? "block" : "none";
        }

        const mainContent = document.querySelector("main") || document.body;
        if (isOffline) {
            mainContent.classList.add("app-offline");
            disableInteractiveElements(true);
        } else {
            mainContent.classList.remove("app-offline");
            disableInteractiveElements(false);
        }
    }

    function disableInteractiveElements(disabled) {
        const interactiveSelectors = "#btn-add-room, .tabs button, .room-btn, .seg-btn, button, select, input";
        document.querySelectorAll(interactiveSelectors).forEach(el => {
            // Jangan disable tombol keluar/logout atau hamburger menu navigasi utama agar aplikasi tetap responsif navigasinya
            if (el.closest('.nav-logout') || el.closest('#hamburger') || el.closest('#sidebar-close')) return;
            
            el.disabled = disabled;
            if (disabled) {
                el.classList.add("disabled-offline");
            } else {
                el.classList.remove("disabled-offline");
            }
        });
    }

    window.addEventListener("online", updateNetworkStatus);
    window.addEventListener("offline", updateNetworkStatus);

    updateNetworkStatus();
});