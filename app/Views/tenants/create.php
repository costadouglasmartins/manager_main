<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Inicio</a></li>
    <li class="breadcrumb-item"><a href="<?= url('tenant'); ?>">Tenants</a></li>
    <li class="breadcrumb-item active" aria-current="page">Novo tenant</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Novo tenant</h5>

<!-- Person selector -->
<div class="card shadow-sm mb-3">
  <div class="card-header">
    <small class="text-muted fw-semibold">Pre-preencher a partir de pessoa cadastrada (opcional)</small>
  </div>
  <div class="card-body pb-2">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-5">
        <input
          type="text"
          id="personSearch"
          class="form-control"
          placeholder="Filtrar por nome..."
          autocomplete="off"
        >
      </div>
      <div class="col-12 col-md-7">
        <select id="personSelect" class="form-select">
          <option value="">Selecione uma pessoa...</option>
          <?php foreach ($people as $person): ?>
            <option
              value="<?= (int) $person['id']; ?>"
              data-name="<?= htmlspecialchars($person['name'], ENT_QUOTES); ?>"
              data-fantasy="<?= htmlspecialchars($person['fantasy'] ?? '', ENT_QUOTES); ?>"
              data-document="<?= htmlspecialchars($person['document'] ?? '', ENT_QUOTES); ?>"
            >
              <?= htmlspecialchars($person['name']); ?>
              <?php if (!empty($person['document'])): ?>
                &mdash; <?= maskCpfCnpj($person['document']); ?>
              <?php endif; ?>
              <?php if (!empty($person['fantasy'])): ?>
                (<?= htmlspecialchars($person['fantasy']); ?>)
              <?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="mt-2 d-flex gap-2">
      <button type="button" id="btnApplyPerson" class="btn btn-sm btn-outline-primary" disabled>
        <i class="bi bi-arrow-down-circle me-1"></i> Preencher formulario
      </button>
      <button type="button" id="btnClearPerson" class="btn btn-sm btn-outline-secondary" style="display:none;">
        Limpar
      </button>
    </div>
  </div>
</div>
<!-- /Person selector -->

<form action="<?= url('tenant/store'); ?>" method="POST" class="needs-validation" novalidate>
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label" for="name">Nome</label>
          <input autocomplete="off" class="form-control" id="name" name="name" placeholder="Nome do tenant" type="text" required />
          <div class="invalid-feedback">Informe o nome.</div>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="fantasy">Nome fantasia</label>
          <input autocomplete="off" class="form-control" id="fantasy" name="fantasy" placeholder="Nome fantasia" type="text" />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="document">Documento (CPF/CNPJ)</label>
          <input autocomplete="off" class="form-control" id="document" name="document" placeholder="CPF ou CNPJ" type="text" />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="database_number">N. do banco</label>
          <input class="form-control" id="database_number" type="text" value="<?= htmlspecialchars($nextNumber) ?>" disabled />
          <div class="form-text">Gerado automaticamente: <code>manager_<?= htmlspecialchars($nextNumber) ?></code></div>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="status">Situacao</label>
          <select autocomplete="off" class="form-select" id="status" name="status">
            <option value="pending" selected>Pendente</option>
            <option value="active">Ativo</option>
            <option value="suspended">Suspenso</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label" for="observations">Observacoes</label>
          <textarea class="form-control" id="observations" name="observations" rows="3" placeholder="Observacoes gerais..."></textarea>
        </div>
      </div>
    </div>
    <div class="card-footer bg-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <a href="<?= url('tenant'); ?>" class="btn btn-secondary">Voltar</a>
      <button type="submit" class="btn btn-success">Salvar e provisionar banco</button>
    </div>
  </div>
</form>

<script>
(function () {
  var searchInput  = document.getElementById('personSearch');
  var personSelect = document.getElementById('personSelect');
  var btnApply     = document.getElementById('btnApplyPerson');
  var btnClear     = document.getElementById('btnClearPerson');

  // Snapshot of all options (except placeholder) for filtering
  var allOptions = Array.from(personSelect.options).slice(1).map(function (o) {
    return { value: o.value, text: o.text, dataset: JSON.parse(JSON.stringify(o.dataset)) };
  });

  function rebuildSelect(term) {
    while (personSelect.options.length > 1) {
      personSelect.remove(1);
    }
    allOptions.forEach(function (item) {
      if (term === '' || item.text.toLowerCase().includes(term)) {
        var opt = document.createElement('option');
        opt.value = item.value;
        opt.text  = item.text;
        Object.keys(item.dataset).forEach(function (k) {
          opt.dataset[k] = item.dataset[k];
        });
        personSelect.appendChild(opt);
      }
    });
    personSelect.selectedIndex = 0;
    btnApply.disabled = true;
  }

  // Live filter by name
  searchInput.addEventListener('input', function () {
    rebuildSelect(this.value.toLowerCase().trim());
  });

  // Enable apply button when a person is chosen
  personSelect.addEventListener('change', function () {
    btnApply.disabled = (this.value === '');
  });

  // Fill form fields from selected person
  btnApply.addEventListener('click', function () {
    var sel = personSelect.options[personSelect.selectedIndex];
    if (!sel || sel.value === '') return;

    document.getElementById('name').value     = sel.dataset.name     || '';
    document.getElementById('fantasy').value  = sel.dataset.fantasy  || '';
    document.getElementById('document').value = sel.dataset.document || '';

    btnClear.style.display = '';
    btnApply.disabled = true;
    document.getElementById('name').focus();
  });

  // Clear the pre-fill
  btnClear.addEventListener('click', function () {
    document.getElementById('name').value     = '';
    document.getElementById('fantasy').value  = '';
    document.getElementById('document').value = '';
    searchInput.value = '';
    btnClear.style.display = 'none';
    rebuildSelect('');
  });

  // Bootstrap form validation
  var form = document.querySelector('.needs-validation');
  if (form) {
    form.addEventListener('submit', function (e) {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  }
})();
</script>
