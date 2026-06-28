document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-bs-toggle="popover"]').forEach((element) => {
    if (window.bootstrap && window.bootstrap.Popover) {
      new window.bootstrap.Popover(element);
    }
  });
});
