document.addEventListener("DOMContentLoaded", () => {
  // Time to open menu when mouse is hovering
  const HOVER_DELAY = 2000;

  /**
   * Theme
   */
  const themeBtn = document.getElementById("themeToggle");
  if (themeBtn) 
    {
    const themeIcon = themeBtn.querySelector("i");
    const root = document.documentElement;
    
    /**
     * Apply theme 
     * @param {string} theme 
     */
    function applyTheme(theme) 
      {
      root.setAttribute("data-bs-theme", theme);
      localStorage.setItem("theme", theme);
      themeIcon.className = theme === "dark" ? "bi bi-sun-fill" : "bi bi-moon-fill";
      }

    applyTheme(localStorage.getItem("theme") || "light");

    themeBtn.addEventListener("click", () => {
      applyTheme(root.getAttribute("data-bs-theme") === "dark" ? "light" : "dark");
    });
    }

  /**
   * Helpers of submenus
   * @param {component} submenu 
   */
  function openSubmenu(submenu) 
    {
    // Closes open brothers in the same level before opens this
    submenu.parentElement
      .querySelectorAll(":scope > .dropdown-submenu.show")
      .forEach(s => {
        if (s !== submenu)
          {
          closeSubmenu(s);
          }
      });
    const menu = submenu.querySelector(":scope > .dropdown-menu");
    if (menu)
      {
      menu.style.display = "block";
      }
    submenu.classList.add("show");
    }

  /**
   * Close submenu
   * @param {component} submenu 
   */
  function closeSubmenu(submenu) 
    {
    // Recursively closes all open child submenus
    submenu.querySelectorAll(".dropdown-submenu.show").forEach(s => {
      s.classList.remove("show");
      const m = s.querySelector(":scope > .dropdown-menu");
      if (m)
        {
        m.style.display = "";
        }
    });
    const menu = submenu.querySelector(":scope > .dropdown-menu");
    if (menu)
      {
      menu.style.display = "";
      }
    submenu.classList.remove("show");
    }

  /**
   * Main dropdowns — level 1 (navbar → dropdown)
   * Desktop: hovering for 2 seconds opens it; moving the pointer away closes it if it was opened by hover.
   * Click: Bootstrap already handles it; we set openedByHover = false.
   */
  document.querySelectorAll(".nav-item.dropdown").forEach(navItem => {
    const toggle = navItem.querySelector("[data-bs-toggle='dropdown']");
    if (!toggle)
      {
      return;
      }

    let hoverTimer  = null;
    let openedByHover = false;

    navItem.addEventListener("mouseenter", () => {
      if (window.innerWidth < 992)
        {
        return;
        }
      hoverTimer = setTimeout(() => {
        openedByHover = true;
        bootstrap.Dropdown.getOrCreateInstance(toggle).show();
      }, HOVER_DELAY);
    });

    navItem.addEventListener("mouseleave", () => {
      if (window.innerWidth < 992)
        {
        return;
        }
      clearTimeout(hoverTimer);
      hoverTimer = null;
      if (openedByHover) 
        {
        bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        openedByHover = false;
        }
    });

    // The click already is treated by Bootstrap, we just reset the flag
    toggle.addEventListener("click", () => {
      clearTimeout(hoverTimer);
      hoverTimer = null;
      openedByHover = false;
    });
  });

  /**
   * Submenus — levels 3, 4, …
   * Desktop: hovering for 2 seconds opens it; moving the pointer away closes it immediately.
   * Clicking opens it right away (toggle).
   * Mobile:  click only (toggle), no timer.
   */
  document.querySelectorAll(".dropdown-submenu").forEach(submenu => {
    const toggle = submenu.querySelector(":scope > .submenu-toggle");
    if (!toggle)
      {
      return;
      }

    let hoverTimer = null;

    // Desktop: hover
    submenu.addEventListener("mouseenter", () => {
      if (window.innerWidth < 992)
        {
        return;
        }
      hoverTimer = setTimeout(() => openSubmenu(submenu), HOVER_DELAY);
    });

    submenu.addEventListener("mouseleave", () => {
      if (window.innerWidth < 992)
        {
        return;
        }
      clearTimeout(hoverTimer);
      hoverTimer = null;
      closeSubmenu(submenu);
    });

    // Click: opens immediately (desktop and mobile)
    toggle.addEventListener("click", e => {
      e.preventDefault();
      e.stopPropagation();
      clearTimeout(hoverTimer);
      hoverTimer = null;
      if (submenu.classList.contains("show")) 
        {
        closeSubmenu(submenu);
        } 
      else 
        {
        openSubmenu(submenu);
        }
    });
  });

});