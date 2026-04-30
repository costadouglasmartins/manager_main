document.addEventListener("DOMContentLoaded", () => {
  const page = document.querySelector(".financial-operation-info");

  if (!page) {
    return;
  }

  window.requestAnimationFrame(() => {
    page.classList.add("is-ready");
  });
});
