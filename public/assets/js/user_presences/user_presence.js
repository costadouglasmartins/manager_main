(function (window, document) {
  "use strict";

  function clearStatusClasses(element)
    {
    ["text-bg-success", "text-bg-warning", "text-bg-danger", "text-bg-secondary"].forEach(function (className) {
      element.classList.remove(className);
    });
    }

  function applyPresence(root, presence)
    {
    const badge = root.querySelector("[data-user-presence-badge]");
    const label = root.querySelector("[data-user-presence-label]");
    const icon = root.querySelector("[data-user-presence-icon]");
    const status = presence.status || "offline";

    if (badge)
      {
      clearStatusClasses(badge);
      badge.classList.add(presence.class || "text-bg-secondary");
      }

    if (label)
      {
      label.textContent = presence.label || "Offline";
      }

    if (icon)
      {
      icon.className = "bi " + (presence.icon || "bi-circle");
      }

    root.querySelectorAll("[data-user-presence-status]").forEach(function (button) {
      const isActive = (button.getAttribute("data-user-presence-status") || "offline") === status;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-pressed", isActive ? "true" : "false");
    });
    }

  function setLoading(root, isLoading)
    {
    root.querySelectorAll("[data-user-presence-status]").forEach(function (button) {
      button.disabled = isLoading;
    });
    }

  function updateStatus(root, status)
    {
    const endpoint = root.getAttribute("data-status-url");
    const body = new FormData();
    body.append("status", status);

    setLoading(root, true);

    fetch(endpoint, {
      method: "POST",
      body: body,
      headers: {
        "Accept": "application/json",
        "X-Requested-With": "XMLHttpRequest"
      }
    })
      .then(function (response) {
        return response.json().then(function (payload) {
          if (!response.ok)
            {
            throw new Error(payload.message || "Status could not be updated.");
            }

          return payload;
        });
      })
      .then(function (payload) {
        if (payload.presence)
          {
          applyPresence(root, payload.presence);
          }
      })
      .catch(function (error) {
        window.alert(error.message || "Status could not be updated.");
      })
      .finally(function () {
        setLoading(root, false);
      });
    }

  function bind(root)
    {
    root.querySelectorAll("[data-user-presence-status]").forEach(function (button) {
      button.addEventListener("click", function () {
        updateStatus(root, button.getAttribute("data-user-presence-status") || "offline");
      });
    });
    }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-user-presence]").forEach(bind);
  });
})(window, document);
