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

  function requestJson(endpoint, parameters, signal)
    {
    const url = new URL(endpoint, window.location.origin);

    Object.keys(parameters).forEach(function (key)
      {
      if (parameters[key] !== '')
        {
        url.searchParams.set(key, parameters[key]);
        }
      });

    return fetch(url.toString(), {
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      signal: signal
    }).then(function (response)
      {
      if (!response.ok)
        {
        throw new Error('HTTP ' + response.status);
        }

      return response.json();
      });
    }

  const filterForm = byId('neighborhoodFilters');
  const countrySelect = byId('country_id');
  const stateSelect = byId('state_id');
  const cityIdInput = byId('city_id');
  const cityNameInput = byId('cityName');
  const cityHelp = byId('cityHelp');
  let countriesAbort = null;
  let statesAbort = null;
  let citiesAbort = null;

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
      cityNameInput.dispatchEvent(new Event('input'));
      }

    setCityEnabled(Boolean(stateSelect && stateSelect.value));
    }

  async function restoreCity(stateId, cityId)
    {
    if (!cityNameInput || !cityIdInput || !stateId || !cityId || !cityNameInput.dataset.citiesEndpoint)
      {
      return;
      }

    if (citiesAbort)
      {
      citiesAbort.abort();
      }

    citiesAbort = new AbortController();

    try
      {
      const data = await requestJson(cityNameInput.dataset.citiesEndpoint, { state_id: stateId }, citiesAbort.signal);
      const cities = Array.isArray(data.cities) ? data.cities : [];
      const selectedCity = cities.find(function (city)
        {
        return String(city.id || '') === String(cityId);
        });

      if (selectedCity)
        {
        cityIdInput.value = selectedCity.id || '';
        cityNameInput.value = selectedCity.name || '';
        }
      else
        {
        cityIdInput.value = '';
        }
      }
    catch (error)
      {
      if (error && error.name === 'AbortError')
        {
        return;
        }

      cityIdInput.value = '';
      console.error('Erro ao restaurar a cidade selecionada:', error);
      }
    }

  async function loadStates(countryId, selectedStateId, selectedCityId)
    {
    if (!stateSelect)
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

    if (!countryId || !stateSelect.dataset.statesEndpoint)
      {
      return;
      }

    statesAbort = new AbortController();

    try
      {
      const data = await requestJson(stateSelect.dataset.statesEndpoint, { country_id: countryId }, statesAbort.signal);
      const states = Array.isArray(data.states) ? data.states : [];

      resetSelect(stateSelect, states.length ? 'Estado' : 'Nenhum estado encontrado');
      states.forEach(function (state)
        {
        stateSelect.appendChild(new Option(state.name || '', state.id || ''));
        });

      stateSelect.disabled = states.length === 0;

      if (selectedStateId && states.some(function (state) { return String(state.id || '') === String(selectedStateId); }))
        {
        stateSelect.value = selectedStateId;
        setCityEnabled(true);
        await restoreCity(selectedStateId, selectedCityId);
        }
      }
    catch (error)
      {
      if (error && error.name === 'AbortError')
        {
        return;
        }

      resetSelect(stateSelect, 'Não foi possível carregar os estados');
      clearCity();
      console.error('Erro ao carregar estados:', error);
      }
    }

  async function loadCountries()
    {
    if (!countrySelect || !countrySelect.dataset.countriesEndpoint)
      {
      return;
      }

    if (countriesAbort)
      {
      countriesAbort.abort();
      }

    const selectedCountryId = countrySelect.dataset.currentValue || '';
    const selectedStateId = stateSelect ? stateSelect.dataset.currentValue || '' : '';
    const selectedCityId = cityNameInput ? cityNameInput.dataset.currentId || '' : '';

    resetSelect(countrySelect, 'Carregando países...');
    countrySelect.disabled = true;
    countriesAbort = new AbortController();

    try
      {
      const data = await requestJson(countrySelect.dataset.countriesEndpoint, {}, countriesAbort.signal);
      const countries = Array.isArray(data.countries) ? data.countries : [];

      resetSelect(countrySelect, countries.length ? 'País' : 'Nenhum país encontrado');
      countries.forEach(function (country)
        {
        countrySelect.appendChild(new Option(country.name || '', country.id || ''));
        });

      countrySelect.disabled = countries.length === 0;

      if (selectedCountryId && countries.some(function (country) { return String(country.id || '') === String(selectedCountryId); }))
        {
        countrySelect.value = selectedCountryId;
        await loadStates(selectedCountryId, selectedStateId, selectedCityId);
        }
      }
    catch (error)
      {
      if (error && error.name === 'AbortError')
        {
        return;
        }

      resetSelect(countrySelect, 'Não foi possível carregar os países');
      resetSelect(stateSelect, 'Estado');
      clearCity();
      console.error('Erro ao carregar países:', error);
      }
    }

  if (countrySelect)
    {
    countrySelect.addEventListener('change', function ()
      {
      loadStates(this.value, '', '');
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
      });

    cityNameInput.addEventListener('autocomplete:selected', function ()
      {
      this.classList.remove('is-invalid');
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
        cityNameInput.focus();
        }
      });
    }

  setCityEnabled(false);
  loadCountries();

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
