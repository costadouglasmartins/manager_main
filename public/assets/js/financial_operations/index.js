document.addEventListener('DOMContentLoaded', function () {
  const categoryInput = document.getElementById('financial_operation_category_id');
  const subcategoryInput = document.getElementById('financial_operation_subcategory_id');
  const deleteModal = document.getElementById('deleteModal');

  function syncSubcategoryOptions() {
    if (!categoryInput || !subcategoryInput) {
      return;
    }

    const categoryId = categoryInput.value;

    Array.from(subcategoryInput.options).forEach(function (option) {
      if (option.value === '' || categoryId === '') {
        option.hidden = false;
        option.disabled = false;
        return;
      }

      const belongsToCategory = option.dataset.categoryId === categoryId;
      option.hidden = !belongsToCategory;
      option.disabled = !belongsToCategory;
    });

    if (subcategoryInput.selectedOptions.length > 0 && subcategoryInput.selectedOptions[0].disabled) {
      subcategoryInput.value = '';
    }
  }

  if (categoryInput) {
    categoryInput.addEventListener('change', syncSubcategoryOptions);
  }

  syncSubcategoryOptions();

  if (deleteModal) {
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
  }
});
