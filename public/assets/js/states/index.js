(function ()
  {
  function byId(id)
    {
    return document.getElementById(id);
    }

  function resetSelect(select, placeholder)
    {
    if (select)
      {
      select.replaceChildren(new Option(placeholder, ''));
      }
    }

  async function requestJson(endpoint, parameters, signal)
    {
    const url = new URL(endpoint, window.location.origin);
    Object.keys(parameters).forEach(function (key)
      {
      if (parameters[key] !== '')
        {
        url.searchParams.set(key, parameters[key]);
        }
      });

    const response = await fetch(url.toString(), {
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      signal: signal
    });

    if (!response.ok)
      {
      throw new Error('HTTP ' + response.status);
      }

    return response.json();
    }

  const filterForm = byId('stateFilters');
  const countrySelect = byId('country_id');
  const stateSelect = byId('state_id');
  const cityIdInput = byId('city_id');
  const cityNameInput = byId('cityName');
  const cityHelp = byId('cityHelp');
  let statesAbort = null;

  function setCityEnabled(enabled)
    {
    if (!cityNameInput)
      {
      return;
      }

    cityNameInput.disabled = !enabled;
    cityNameInput.placeholder = enabled ? 'Digite o nome da cidade' : 'Selecione um estado primeiro';

    if (cityHelp)
      {
      cityHelp.textContent = enabled
        ? 'Digite para buscar somente entre as cidades deste estado.'
        : 'A busca será habilitada após selecionar um estado.';
      }
    }

  function clearCity()
    {
    if (cityIdInput)
      {
      cityIdInput.value = '';
      }

    if (cityNameInput)
      {
      cityNameInput.value = '';
      cityNameInput.classList.remove('is-invalid');
      cityNameInput.setAttribute('aria-invalid', 'false');
      cityNameInput.dispatchEvent(new Event('input'));
      }

    setCityEnabled(Boolean(stateSelect && stateSelect.value));
    }

  async function loadStates(countryId)
    {
    if (!stateSelect)
      {
      return;
      }

    if (statesAbort)
      {
      statesAbort.abort();
      }

    resetSelect(stateSelect, countryId ? 'Carregando estados...' : 'Selecione um país primeiro');
    stateSelect.disabled = true;
    clearCity();

    if (!countryId || !countrySelect || !countrySelect.dataset.statesEndpoint)
      {
      return;
      }

    statesAbort = new AbortController();

    try
      {
      const data = await requestJson(
        countrySelect.dataset.statesEndpoint,
        { country_id: countryId },
        statesAbort.signal
      );
      const states = Array.isArray(data.states) ? data.states : [];

      resetSelect(stateSelect, states.length ? 'Selecione um estado' : 'Nenhum estado encontrado');
      states.forEach(function (state)
        {
        stateSelect.appendChild(new Option(state.name || '', state.id || ''));
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
      stateSelect.disabled = true;
      console.error('Erro ao carregar estados:', error);
      }
    }

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

  if (cityNameInput)
    {
    cityNameInput.addEventListener('input', function ()
      {
      this.classList.remove('is-invalid');
      this.setAttribute('aria-invalid', 'false');
      });

    cityNameInput.addEventListener('autocomplete:selected', function ()
      {
      this.classList.remove('is-invalid');
      this.setAttribute('aria-invalid', 'false');
      });
    }

  if (filterForm)
    {
    filterForm.addEventListener('submit', function (event)
      {
      if (cityNameInput && cityNameInput.value.trim() !== '' && cityIdInput && cityIdInput.value === '')
        {
        event.preventDefault();
        cityNameInput.classList.add('is-invalid');
        cityNameInput.setAttribute('aria-invalid', 'true');
        cityNameInput.focus();
        }
      });
    }

  if (!stateSelect || !stateSelect.value)
    {
    clearCity();
    }
  setCityEnabled(Boolean(stateSelect && stateSelect.value));

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
