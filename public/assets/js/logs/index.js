document.addEventListener('DOMContentLoaded', () => {
  const modalElement = document.getElementById('logDeleteModal');
  if (!modalElement) {
    return;
  }

  const form = document.getElementById('logDeleteForm');
  const passwordInput = document.getElementById('logDeletePassword');
  const targetLabel = document.getElementById('logDeleteTargetLabel');
  const feedback = document.getElementById('logDeleteFeedback');
  const submitButton = document.getElementById('logDeleteSubmit');
  const totalElement = document.getElementById('auditLogTotal');
  const tableBody = document.querySelector('.audit-log-table tbody');
  const bootstrapModal = window.bootstrap && window.bootstrap.Modal
    ? window.bootstrap.Modal.getOrCreateInstance(modalElement)
    : null;

  let activeRow = null;
  let activeUrl = '';

  const setFeedback = (message) => {
    feedback.textContent = message;
    feedback.classList.remove('d-none');
  };

  const clearFeedback = () => {
    feedback.textContent = '';
    feedback.classList.add('d-none');
  };

  const updateTotal = (nextTotal) => {
    if (!totalElement) {
      return;
    }

    totalElement.dataset.total = String(nextTotal);
    totalElement.textContent = `${nextTotal} registro(s)`;
  };

  const ensureEmptyState = () => {
    if (!tableBody) {
      return;
    }

    const remainingRows = tableBody.querySelectorAll('tr[data-log-row]');
    if (remainingRows.length > 0) {
      return;
    }

    const emptyRow = document.createElement('tr');
    emptyRow.innerHTML = '<td colspan="10" class="app-index-results-empty">Nenhum log encontrado para os filtros informados.</td>';
    tableBody.innerHTML = '';
    tableBody.appendChild(emptyRow);
  };

  modalElement.addEventListener('show.bs.modal', (event) => {
    const trigger = event.relatedTarget;
    if (!(trigger instanceof HTMLElement)) {
      return;
    }

    activeUrl = trigger.dataset.deleteUrl || '';
    activeRow = trigger.closest('tr[data-log-row]');
    targetLabel.textContent = trigger.dataset.logLabel || 'este log';
    form.reset();
    clearFeedback();

    window.setTimeout(() => {
      passwordInput.focus();
    }, 150);
  });

  modalElement.addEventListener('hidden.bs.modal', () => {
    activeUrl = '';
    activeRow = null;
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

      const rowToRemove = activeRow;
      if (rowToRemove) {
        rowToRemove.classList.add('audit-log-row-removing');
        window.setTimeout(() => {
          rowToRemove.remove();
          ensureEmptyState();
        }, 180);
      }

      if (totalElement) {
        const currentTotal = Number.parseInt(totalElement.dataset.total || '0', 10);
        updateTotal(Math.max(0, currentTotal - 1));
      }

      if (bootstrapModal) {
        bootstrapModal.hide();
      }
    } catch (error) {
      setFeedback('Não foi possível remover o log neste momento.');
      submitButton.disabled = false;
    }
  });
});
