/**
 * Popover
 */
const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

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
