document.addEventListener("DOMContentLoaded", () => {

  const HOVER_DELAY = 2000; // ms para abrir por hover

  // ── Troca de tema ────────────────────────────────────────────────────────
  const themeBtn = document.getElementById("themeToggle");
  if (themeBtn) {
    const themeIcon = themeBtn.querySelector("i");
    const root = document.documentElement;

    function applyTheme(theme) {
      root.setAttribute("data-bs-theme", theme);
      localStorage.setItem("theme", theme);
      themeIcon.className = theme === "dark" ? "bi bi-sun-fill" : "bi bi-moon-fill";
    }

    applyTheme(localStorage.getItem("theme") || "light");

    themeBtn.addEventListener("click", () => {
      applyTheme(root.getAttribute("data-bs-theme") === "dark" ? "light" : "dark");
    });
  }

  // ── Helpers de submenu ───────────────────────────────────────────────────
  function openSubmenu(submenu) {
    // Fecha irmãos abertos no mesmo nível antes de abrir este
    submenu.parentElement
      .querySelectorAll(":scope > .dropdown-submenu.show")
      .forEach(s => {
        if (s !== submenu) closeSubmenu(s);
      });
    const menu = submenu.querySelector(":scope > .dropdown-menu");
    if (menu) menu.style.display = "block";
    submenu.classList.add("show");
  }

  function closeSubmenu(submenu) {
    // Fecha recursivamente todos os submenus filhos abertos
    submenu.querySelectorAll(".dropdown-submenu.show").forEach(s => {
      s.classList.remove("show");
      const m = s.querySelector(":scope > .dropdown-menu");
      if (m) m.style.display = "";
    });
    const menu = submenu.querySelector(":scope > .dropdown-menu");
    if (menu) menu.style.display = "";
    submenu.classList.remove("show");
  }

  // ── Dropdowns principais — nível 1 (navbar → dropdown) ──────────────────
  // Desktop: hover 2 s abre; ao sair fecha se foi aberto por hover.
  // Clique: Bootstrap já gerencia; marcamos openedByHover = false.
  document.querySelectorAll(".nav-item.dropdown").forEach(navItem => {
    const toggle = navItem.querySelector("[data-bs-toggle='dropdown']");
    if (!toggle) return;

    let hoverTimer  = null;
    let openedByHover = false;

    navItem.addEventListener("mouseenter", () => {
      if (window.innerWidth < 992) return;
      hoverTimer = setTimeout(() => {
        openedByHover = true;
        bootstrap.Dropdown.getOrCreateInstance(toggle).show();
      }, HOVER_DELAY);
    });

    navItem.addEventListener("mouseleave", () => {
      if (window.innerWidth < 992) return;
      clearTimeout(hoverTimer);
      hoverTimer = null;
      if (openedByHover) {
        bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        openedByHover = false;
      }
    });

    // Clique já é tratado pelo Bootstrap; apenas resetamos a flag
    toggle.addEventListener("click", () => {
      clearTimeout(hoverTimer);
      hoverTimer = null;
      openedByHover = false;
    });
  });

  // ── Submenus — níveis 3, 4, … ────────────────────────────────────────────
  // Desktop : hover 2 s abre; ao sair fecha imediatamente.
  //           clique abre na hora (toggle).
  // Mobile  : apenas clique (toggle), sem timer.
  document.querySelectorAll(".dropdown-submenu").forEach(submenu => {
    const toggle = submenu.querySelector(":scope > .submenu-toggle");
    if (!toggle) return;

    let hoverTimer = null;

    // — Desktop: hover ———————————————————————————————————
    submenu.addEventListener("mouseenter", () => {
      if (window.innerWidth < 992) return;
      hoverTimer = setTimeout(() => openSubmenu(submenu), HOVER_DELAY);
    });

    submenu.addEventListener("mouseleave", () => {
      if (window.innerWidth < 992) return;
      clearTimeout(hoverTimer);
      hoverTimer = null;
      closeSubmenu(submenu);
    });

    // — Clique: abre imediatamente (desktop e mobile) ————
    toggle.addEventListener("click", e => {
      e.preventDefault();
      e.stopPropagation();
      clearTimeout(hoverTimer);
      hoverTimer = null;
      if (submenu.classList.contains("show")) {
        closeSubmenu(submenu);
      } else {
        openSubmenu(submenu);
      }
    });
  });

});
