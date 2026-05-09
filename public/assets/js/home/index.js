document.addEventListener("DOMContentLoaded", () => {
  const dashboard = document.querySelector(".home-dashboard");
  if (!dashboard) {
    return;
  }

  const numberFormatter = new Intl.NumberFormat("pt-BR");
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /**
   * Animates integer dashboard counters.
   * @param {HTMLElement} element Counter element.
   */
  function animateCounter(element) {
    const target = Number.parseInt(element.dataset.target || "0", 10);
    if (!Number.isFinite(target) || target <= 0 || prefersReducedMotion) {
      element.textContent = numberFormatter.format(Math.max(target, 0));
      return;
    }

    const duration = 700;
    const startedAt = performance.now();

    function tick(now) {
      const progress = Math.min((now - startedAt) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      element.textContent = numberFormatter.format(Math.round(target * eased));

      if (progress < 1) {
        window.requestAnimationFrame(tick);
      }
    }

    window.requestAnimationFrame(tick);
  }

  dashboard.querySelectorAll(".home-counter").forEach(animateCounter);

  window.requestAnimationFrame(() => {
    dashboard.classList.add("is-ready");
  });
});