// --- Troca de tema ---
document.addEventListener("DOMContentLoaded", () => {
  const btn = document.getElementById("themeToggle");
  const icon = btn.querySelector("i");
  const root = document.documentElement;

  function applyTheme(theme) {
    root.setAttribute("data-bs-theme", theme);
    localStorage.setItem("theme", theme);
    icon.className = theme === "dark" ? "bi bi-sun-fill" : "bi bi-moon-fill";
  }

  const saved = localStorage.getItem("theme") || "light";
  applyTheme(saved);

  btn.addEventListener("click", () => {
    const current = root.getAttribute("data-bs-theme") === "dark" ? "light" : "dark";
    applyTheme(current);
  });
});

// --- Submenu funcional no mobile ---
document.addEventListener("click", function (e) {
  if (window.innerWidth < 992) {
    const isSubmenu = e.target.classList.contains("submenu-toggle");
    if (isSubmenu) {
      e.preventDefault();
      const submenu = e.target.closest(".dropdown-submenu");
      submenu.classList.toggle("show");
    }
  }
});