(function ()
  {
  function byId(id)
    {
    return document.getElementById(id);
    }

  function setValue(id, value)
    {
    // Form element whose value must be updated.
    const element = byId(id);
    if (element)
      {
      element.value = value || '';
      }
    }

  function resetSelect(select, placeholder)
    {
    select.replaceChildren(new Option(placeholder, ''));
    }

  function clearCity()
    {
    setValue('id', '');

    if (cityName)
      {
      cityName.value = '';
      cityName.dispatchEvent(new Event('input'));
      cityName.disabled = !stateSelect || !stateSelect.value;
      }
    }

  async function loadStates(countryId)
    {
    if (!countrySelect || !stateSelect)
      {
      return;
      }

    if (statesAbort)
      {
      statesAbort.abort();
      }

    resetSelect(stateSelect, countryId ? 'Carregando estados...' : 'Estado');
    stateSelect.disabled = true;
    clearCity();

    if (!countryId || !countrySelect.dataset.statesEndpoint)
      {
      return;
      }

    statesAbort = new AbortController();

    try
      {
      // Endpoint URL scoped to the selected country.
      const url = new URL(countrySelect.dataset.statesEndpoint, window.location.origin);
      url.searchParams.set('country_id', countryId);

      // JSON response containing the states for the selected country.
      const response = await fetch(url.toString(), {
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        signal: statesAbort.signal
      });

      if (!response.ok)
        {
        throw new Error('HTTP ' + response.status);
        }

      // Parsed response payload.
      const data = await response.json();
      // Valid state collection returned by the endpoint.
      const states = Array.isArray(data.states) ? data.states : [];

      resetSelect(stateSelect, states.length ? 'Estado' : 'Nenhum estado encontrado');

      states.forEach(function (state)
        {
        // Select option for the current state.
        const option = new Option(state.name || '', state.id || '');
        stateSelect.appendChild(option);
        });

      stateSelect.disabled = states.length === 0;
      }
    catch (error)
      {
      if (error && error.name === 'AbortError')
        {
        return;
        }

      resetSelect(stateSelect, 'Não foi possível carregar os estados');
      console.error('Erro ao carregar estados:', error);
      }
    }

  // Country filter that controls the available states.
  const countrySelect = byId('country_id');
  // State filter used by the city autocomplete.
  const stateSelect = byId('state_id');
  // City autocomplete enabled after selecting a state.
  const cityName = byId('name');
  // Active states request, aborted when the country changes again.
  let statesAbort = null;

  if (countrySelect)
    {
    countrySelect.addEventListener('change', function ()
      {
      loadStates(this.value);
      });
    }

  if (stateSelect)
    {
    stateSelect.addEventListener('change', clearCity);
    }

  if (cityName)
    {
    cityName.disabled = !stateSelect || !stateSelect.value;
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
