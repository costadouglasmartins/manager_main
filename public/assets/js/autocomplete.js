(function ()
  {
  function initAutocomplete(input)
    {
    const endpoint = input.dataset.endpoint || '';
    const resultsSelector = input.dataset.results || '';
    const hiddenSelector = input.dataset.hidden || '';

    if (!endpoint || !resultsSelector)
      {
      console.warn('Autocomplete: data-endpoint ou data-results ausente:', input);
      return;
      }

    let list = null;
    try
      {
      list = document.querySelector(resultsSelector);
      }
    catch (err)
      {
      console.warn('Autocomplete: data-results inválido:', resultsSelector, err);
      return;
      }

    if (!list)
      {
      console.warn('Autocomplete: lista não encontrada para data-results:', resultsSelector);
      return;
      }

    let hidden = null;
    if (hiddenSelector)
      {
      try
        {
        hidden = document.querySelector(hiddenSelector);
        }
      catch (err)
        {
        console.warn('Autocomplete: data-hidden inválido:', hiddenSelector, err);
        hidden = null;
        }
      }

    const minLen = parseInt(input.dataset.minlen || '2', 10);
    const paramName = input.dataset.param || 'term';

    let lastAbort = null;
    let activeIndex = -1;
    let itemsCache = [];

    function clearList()
      {
      list.innerHTML = '';
      activeIndex = -1;
      itemsCache = [];
      }

    function setActive(index)
      {
      const items = list.querySelectorAll('li');
      if (!items.length)
        {
        activeIndex = -1;
        return;
        }

      if (index < 0)
        {
        index = items.length - 1;
        }
      if (index >= items.length)
        {
        index = 0;
        }

      items.forEach((el) => { el.classList.remove('active'); });
      items[index].classList.add('active');
      activeIndex = index;

      items[index].scrollIntoView({ block: 'nearest' });
      }

    function selectItem(index)
      {
      if (index < 0 || index >= itemsCache.length)
        {
        return;
        }

      const item = itemsCache[index];
      input.value = item.label ?? '';

      if (hidden)
        {
        hidden.value = item.id ?? '';
        }

      clearList();

      // opcional: evento para escutar seleção externamente
      input.dispatchEvent(new CustomEvent('autocomplete:selected', { detail: item }));
      }

    function applyExtraParams(url)
      {
      if (!input.dataset.extra)
        {
        return;
        }

      try
        {
        const extraMap = JSON.parse(input.dataset.extra);

        Object.keys(extraMap).forEach((key) =>
          {
          const selector = extraMap[key];
          const el = document.querySelector(selector);

          if (el && el.value !== '')
            {
            url.searchParams.set(key, el.value);
            }
          });
        }
      catch (err)
        {
        console.warn('Autocomplete: data-extra inválido:', input.dataset.extra, err);
        }
      }

    async function fetchItems(term)
      {
      const url = new URL(endpoint, window.location.origin);
      url.searchParams.set(paramName, term);

      applyExtraParams(url);

      if (lastAbort)
        {
        lastAbort.abort();
        }
      lastAbort = new AbortController();

      try
        {
        const res = await fetch(url.toString(), {
          method: 'GET',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          signal: lastAbort.signal
        });

        if (!res.ok)
          {
          return [];
          }

        const ct = (res.headers.get('content-type') || '').toLowerCase();
        if (!ct.includes('application/json'))
          {
          const text = await res.text();
          console.error('Autocomplete: resposta não é JSON. Início:', text.slice(0, 200));
          return [];
          }

        const data = await res.json();
        return data.items || [];
        }
      catch (err)
        {
        // Abort é esperado quando o usuário digita rápido
        if (err && (err.name === 'AbortError' || String(err).toLowerCase().includes('aborted')))
          {
          return [];
          }

        console.error('Autocomplete: erro no fetch:', err);
        return [];
        }
      }

    input.addEventListener('input', async () =>
      {
      const term = input.value.trim();

      clearList();

      if (hidden)
        {
        hidden.value = '';
        }

      if (term.length < minLen)
        {
        return;
        }

      itemsCache = await fetchItems(term);

      itemsCache.forEach((item, idx) =>
        {
        const li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action';
        li.textContent = item.label ?? '';

        // mousedown evita perder o foco antes de selecionar
        li.addEventListener('mousedown', (e) =>
          {
          e.preventDefault();
          selectItem(idx);
          });

        li.addEventListener('mouseenter', () =>
          {
          setActive(idx);
          });

        list.appendChild(li);
        });

      if (itemsCache.length)
        {
        setActive(0);
        }
      });

    input.addEventListener('keydown', (e) =>
      {
      const hasList = list.querySelectorAll('li').length > 0;
      if (!hasList)
        {
        return;
        }

      if (e.key === 'ArrowDown')
        {
        e.preventDefault();
        setActive(activeIndex + 1);
        }
      else if (e.key === 'ArrowUp')
        {
        e.preventDefault();
        setActive(activeIndex - 1);
        }
      else if (e.key === 'Enter')
        {
        e.preventDefault();
        if (activeIndex >= 0)
          {
          selectItem(activeIndex);
          }
        }
      else if (e.key === 'Escape')
        {
        e.preventDefault();
        clearList();
        }
      });

    document.addEventListener('click', (e) =>
      {
      if (e.target !== input && !list.contains(e.target))
        {
        clearList();
        }
      });
    }

  document.querySelectorAll('.js-autocomplete').forEach((el) =>
    {
    initAutocomplete(el);
    });
  })();