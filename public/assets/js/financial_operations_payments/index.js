document.addEventListener('DOMContentLoaded', function () {
  const deleteModal = document.getElementById('deleteModal');

  if (!deleteModal) {
    return;
  }

  deleteModal.addEventListener('show.bs.modal', function (event) {
    const trigger = event.relatedTarget;
    const deleteForm = document.getElementById('deleteForm');
    const recordIdInput = document.getElementById('recordId');
    const recordName = document.getElementById('recordName');

    if (!trigger || !deleteForm || !recordIdInput || !recordName) {
      return;
    }

    const recordId = trigger.dataset.id || '';
    const baseUrl = (deleteForm.dataset.deleteBaseUrl || '').replace(/\/$/, '');

    recordIdInput.value = recordId;
    recordName.textContent = trigger.dataset.name || recordId;
    deleteForm.action = baseUrl + '/' + encodeURIComponent(recordId);
  });
});
