document.addEventListener("DOMContentLoaded", () => {
  // Time to open menu when mouse is hovering
  const OPEN_DELAY = 2000;
  const CLOSE_DELAY = 2000;

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
   * Defines which side has enough room to render a submenu.
   * @param {component} submenu
   */
  function updateSubmenuDirection(submenu)
    {
    const menu = submenu.querySelector(":scope > .dropdown-menu");
    if (!menu)
      {
      return;
      }

    submenu.classList.remove("submenu-open-left");
    if (window.innerWidth < 992)
      {
      return;
      }

    const submenuRect = submenu.getBoundingClientRect();
    const menuWidth = menu.offsetWidth;
    const viewportWidth = document.documentElement.clientWidth;
    const rightSpace = viewportWidth - submenuRect.right;
    const leftSpace = submenuRect.left;

    submenu.classList.toggle("submenu-open-left", rightSpace < menuWidth && leftSpace > rightSpace);
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
      updateSubmenuDirection(submenu);
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
      s.classList.remove("show", "submenu-open-left");
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
    submenu.classList.remove("show", "submenu-open-left");
    }

  /**
   * Checks whether a desktop menu should use hover and delayed close behavior.
   * @return {boolean}
   */
  function isDesktopMenu()
    {
    return window.innerWidth >= 992;
    }

  /**
   * Checks whether pointer or keyboard focus is still inside an element.
   * @param {HTMLElement} element
   * @return {boolean}
   */
  function hasPointerOrFocusInside(element)
    {
    return element.matches(":hover") || element.contains(document.activeElement);
    }

  /**
   * Main dropdowns — level 1 (navbar → dropdown)
   * Desktop: hovering for 2 seconds opens it; moving the pointer/focus away closes it after 2 seconds.
   * Click: Bootstrap opens immediately and the same delayed close behavior is applied.
   */
  document.querySelectorAll(".nav-item.dropdown").forEach(navItem => {
    const toggle = navItem.querySelector("[data-bs-toggle='dropdown']");
    if (!toggle)
      {
      return;
      }

    const menu = navItem.querySelector(":scope > .dropdown-menu");
    let openTimer = null;
    let closeTimer = null;

    function isDropdownOpen()
      {
      return toggle.classList.contains("show") || (menu && menu.classList.contains("show"));
      }

    function cancelOpenTimer()
      {
      clearTimeout(openTimer);
      openTimer = null;
      }

    function cancelCloseTimer()
      {
      clearTimeout(closeTimer);
      closeTimer = null;
      }

    function scheduleClose()
      {
      if (!isDesktopMenu())
        {
        return;
        }

      cancelCloseTimer();
      closeTimer = setTimeout(() => {
        closeTimer = null;

        if (!isDropdownOpen() || hasPointerOrFocusInside(navItem))
          {
          return;
          }

        navItem.querySelectorAll(".dropdown-submenu.show").forEach(closeSubmenu);
        bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
      }, CLOSE_DELAY);
      }

    navItem.addEventListener("mouseenter", () => {
      if (!isDesktopMenu())
        {
        return;
        }

      cancelCloseTimer();
      cancelOpenTimer();

      if (isDropdownOpen())
        {
        return;
        }

      openTimer = setTimeout(() => {
        openTimer = null;
        bootstrap.Dropdown.getOrCreateInstance(toggle).show();
      }, OPEN_DELAY);
    });

    navItem.addEventListener("mouseleave", () => {
      if (!isDesktopMenu())
        {
        return;
        }

      cancelOpenTimer();
      scheduleClose();
    });

    navItem.addEventListener("focusin", () => {
      if (isDesktopMenu())
        {
        cancelCloseTimer();
        }
    });

    navItem.addEventListener("focusout", () => {
      if (!isDesktopMenu())
        {
        return;
        }

      window.setTimeout(() => {
        if (!hasPointerOrFocusInside(navItem))
          {
          scheduleClose();
          }
      }, 0);
    });

    // Bootstrap handles the click. We only cancel pending hover/close timers.
    toggle.addEventListener("click", () => {
      cancelOpenTimer();
      cancelCloseTimer();
    });

    toggle.addEventListener("hidden.bs.dropdown", () => {
      cancelOpenTimer();
      cancelCloseTimer();
      navItem.querySelectorAll(".dropdown-submenu.show").forEach(closeSubmenu);
    });
  });

  /**
   * Submenus - nested dropdown items
   * Desktop: hovering for 2 seconds opens it; moving the pointer/focus away closes it after 2 seconds.
   * Clicking opens it right away (toggle).
   * Mobile:  click only (toggle), no timer.
   */
  document.querySelectorAll(".dropdown-submenu").forEach(submenu => {
    const toggle = submenu.querySelector(":scope > .submenu-toggle");
    if (!toggle)
      {
      return;
      }

    let openTimer = null;
    let closeTimer = null;

    function cancelOpenTimer()
      {
      clearTimeout(openTimer);
      openTimer = null;
      }

    function cancelCloseTimer()
      {
      clearTimeout(closeTimer);
      closeTimer = null;
      }

    function scheduleClose()
      {
      if (!isDesktopMenu())
        {
        return;
        }

      cancelCloseTimer();
      closeTimer = setTimeout(() => {
        closeTimer = null;

        if (!submenu.classList.contains("show") || hasPointerOrFocusInside(submenu))
          {
          return;
          }

        closeSubmenu(submenu);
      }, CLOSE_DELAY);
      }

    // Desktop: hover
    submenu.addEventListener("mouseenter", () => {
      if (!isDesktopMenu())
        {
        return;
        }

      cancelCloseTimer();
      cancelOpenTimer();

      if (submenu.classList.contains("show"))
        {
        return;
        }

      openTimer = setTimeout(() => {
        openTimer = null;
        openSubmenu(submenu);
      }, OPEN_DELAY);
    });

    submenu.addEventListener("mouseleave", () => {
      if (!isDesktopMenu())
        {
        return;
        }

      cancelOpenTimer();
      scheduleClose();
    });

    submenu.addEventListener("focusin", () => {
      if (isDesktopMenu())
        {
        cancelCloseTimer();
        }
    });

    submenu.addEventListener("focusout", () => {
      if (!isDesktopMenu())
        {
        return;
        }

      window.setTimeout(() => {
        if (!hasPointerOrFocusInside(submenu))
          {
          scheduleClose();
          }
      }, 0);
    });

    // Click: opens immediately (desktop and mobile)
    toggle.addEventListener("click", e => {
      e.preventDefault();
      e.stopPropagation();
      cancelOpenTimer();
      cancelCloseTimer();
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

  window.addEventListener("resize", () => {
    document.querySelectorAll(".dropdown-submenu.show").forEach(updateSubmenuDirection);
  });

});
