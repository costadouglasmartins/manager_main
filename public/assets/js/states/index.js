(function ()
  {
  const deleteModal = document.getElementById('deleteModal');
  if (!deleteModal)
    {
    return;
    }

  deleteModal.addEventListener('show.bs.modal', function (event)
    {
    const trigger = event.relatedTarget;
    if (!trigger)
      {
      return;
      }

    const recordId = trigger.dataset.id || '';
    const recordName = trigger.dataset.name || '';
    const recordIdInput = document.getElementById('recordId');
    const recordNameElement = document.getElementById('recordName');
    const deleteForm = document.getElementById('deleteForm');

    if (recordIdInput)
      {
      recordIdInput.value = recordId;
      }

    if (recordNameElement)
      {
      recordNameElement.textContent = recordName;
      }

    if (deleteForm && deleteForm.dataset.deleteUrl)
      {
      deleteForm.action = deleteForm.dataset.deleteUrl + '/' + encodeURIComponent(recordId);
      }
    });
  })();
