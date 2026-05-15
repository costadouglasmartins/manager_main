(function ()
  {
  function byId(id)
    {
    return document.getElementById(id);
    }

  function setValue(id, value)
    {
    const element = byId(id);
    if (element)
      {
      element.value = value || '';
      }
    }

  function clearCity()
    {
    setValue('id', '');
    setValue('name', '');
    }

  function clearState()
    {
    setValue('state_id', '');
    setValue('stateName', '');
    clearCity();
    }

  const countryName = byId('countryName');
  const stateName = byId('stateName');
  const cityName = byId('name');

  if (countryName)
    {
    countryName.addEventListener('input', clearState);
    countryName.addEventListener('autocomplete:selected', clearState);
    }

  if (stateName)
    {
    stateName.addEventListener('input', clearCity);
    stateName.addEventListener('autocomplete:selected', function (event)
      {
      const item = event.detail || {};
      const countryId = byId('country_id');

      if (item.country_id && countryId && !countryId.value)
        {
        setValue('country_id', item.country_id);
        setValue('countryName', item.country_name);
        }

      clearCity();
      });
    }

  if (cityName)
    {
    cityName.addEventListener('autocomplete:selected', function (event)
      {
      const item = event.detail || {};

      if (item.country_id)
        {
        setValue('country_id', item.country_id);
        setValue('countryName', item.country_name);
        }

      if (item.state_id)
        {
        setValue('state_id', item.state_id);
        setValue('stateName', item.state_name);
        }
      });
    }

  const deleteModal = byId('deleteModal');
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
    const recordIdInput = byId('recordId');
    const recordNameElement = byId('recordName');
    const deleteForm = byId('deleteForm');

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
