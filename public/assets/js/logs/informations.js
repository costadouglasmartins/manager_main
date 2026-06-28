document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-bs-toggle="popover"]').forEach((element) => {
    if (window.bootstrap && window.bootstrap.Popover) {
      new window.bootstrap.Popover(element);
    }
  });

  const modalElement = document.getElementById('logDeleteModal');
  if (!modalElement) {
    return;
  }

  const form = document.getElementById('logDeleteForm');
  const passwordInput = document.getElementById('logDeletePassword');
  const targetLabel = document.getElementById('logDeleteTargetLabel');
  const feedback = document.getElementById('logDeleteFeedback');
  const submitButton = document.getElementById('logDeleteSubmit');
  const successAlert = document.getElementById('auditLogDeleteSuccess');
  const bootstrapModal = window.bootstrap && window.bootstrap.Modal
    ? window.bootstrap.Modal.getOrCreateInstance(modalElement)
    : null;

  let activeUrl = '';

  const setFeedback = (message) => {
    feedback.textContent = message;
    feedback.classList.remove('d-none');
  };

  const clearFeedback = () => {
    feedback.textContent = '';
    feedback.classList.add('d-none');
  };

  modalElement.addEventListener('show.bs.modal', (event) => {
    const trigger = event.relatedTarget;
    if (!(trigger instanceof HTMLElement)) {
      return;
    }

    activeUrl = trigger.dataset.deleteUrl || '';
    targetLabel.textContent = trigger.dataset.logLabel || 'este log';
    form.reset();
    clearFeedback();

    window.setTimeout(() => {
      passwordInput.focus();
    }, 150);
  });

  modalElement.addEventListener('hidden.bs.modal', () => {
    activeUrl = '';
    form.reset();
    clearFeedback();
    submitButton.disabled = false;
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFeedback();

    if (!activeUrl) {
      setFeedback('Não foi possível identificar o log selecionado.');
      return;
    }

    submitButton.disabled = true;

    try {
      const response = await fetch(activeUrl, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: new URLSearchParams({
          password: passwordInput.value,
        }),
      });

      const payload = await response.json();
      if (!response.ok || !payload.success) {
        setFeedback(payload.message || 'Não foi possível remover o log.');
        submitButton.disabled = false;
        return;
      }

      if (bootstrapModal) {
        bootstrapModal.hide();
      }

      if (successAlert) {
        successAlert.textContent = payload.message || 'Log removido com sucesso.';
        successAlert.classList.remove('d-none');
      }

      window.setTimeout(() => {
        window.location.href = payload.redirect || form.dataset.redirectUrl || window.location.href;
      }, 700);
    } catch (error) {
      setFeedback('Não foi possível remover o log neste momento.');
      submitButton.disabled = false;
    }
  });
});
