/**
 * Popover
 */
if (window.bootstrap?.Popover) {
  document.querySelectorAll('[data-bs-toggle="popover"]').forEach((trigger) => {
    bootstrap.Popover.getOrCreateInstance(trigger);
  });
}

/**
 * Regiões de tabela com rolagem acessível por teclado.
 */
const appView = document.querySelector(".app-view");

appView?.querySelectorAll(".table-responsive").forEach((region) => {
  const card = region.closest(".card");
  const title = card?.querySelector(".card-title, .card-header h1, .card-header h2, .card-header h3, .card-header h4, .card-header h5, .card-header h6")
    || appView.querySelector("h1, h2, h3, h4, h5, h6");

  if (!region.hasAttribute("tabindex")) {
    region.setAttribute("tabindex", "0");
  }

  if (!region.hasAttribute("role")) {
    region.setAttribute("role", "region");
  }

  if (!region.hasAttribute("aria-label")) {
    const titleText = title?.textContent?.trim();
    region.setAttribute("aria-label", titleText ? `Tabela: ${titleText}` : "Tabela de dados");
  }
});

/**
 * Flash messages
 */
document.querySelectorAll("[data-flash-message]").forEach((message) => {
  window.setTimeout(() => {
    if (!document.body.contains(message)) {
      return;
    }

    if (window.bootstrap && bootstrap.Alert) {
      bootstrap.Alert.getOrCreateInstance(message).close();
      return;
    }

    message.classList.remove("show");
    window.setTimeout(() => message.remove(), 300);
  }, 10000);
});
