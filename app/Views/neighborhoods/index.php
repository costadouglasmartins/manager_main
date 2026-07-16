<?php
$filters = $filters ?? ['id' => '', 'name' => '', 'country_id' => '', 'state_id' => '', 'city_id' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($neighborhoods) ? count($neighborhoods) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];

$queryFilters = http_build_query([
  'id'         => $filters['id'],
  'name'       => $filters['name'],
  'country_id' => $filters['country_id'],
  'state_id'   => $filters['state_id'],
  'city_id'    => $filters['city_id'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Bairros</li>
  </ol>
</nav>

<form action="<?= url('neighborhood'); ?>" class="index-search-card mb-4" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="row g-3 align-items-center">

  <div class="col-12 col-lg-9">
    <div class="row g-3">

      <div class="col-12 col-md-6">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6">
        <input class="form-control" id="name" name="name" type="text" placeholder="Nome" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-4">
        <select class="form-select" id="country_id" name="country_id">
          <option value="">País</option>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <select class="form-select" id="state_id" name="state_id" disabled>
          <option value="">Estado</option>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <select class="form-select" id="city_id" name="city_id" disabled>
          <option value="">Cidade</option>
        </select>
      </div>

    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="index-search-actions">
      <button type="submit" class="btn btn-primary btn-sm index-search-action" aria-label="Pesquisar" title="Pesquisar"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Pesquisar</span></button>
      <a href="<?= url('neighborhood'); ?>" class="btn btn-outline-secondary btn-sm index-search-action" aria-label="Limpar filtros" title="Limpar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span></a>
      <?= pdfExportButton('neighborhood', $paginationQuery); ?>
      <?= excelExportButton('neighborhood', $paginationQuery); ?>
      <?php if (authCan('neighborhood.create')): ?>
        <a href="<?= url('neighborhood/create'); ?>" class="btn btn-success btn-sm index-search-action" aria-label="Novo bairro" title="Novo bairro"><i class="bi bi-plus-lg" aria-hidden="true"></i><span class="visually-hidden">Novo bairro</span></a>
      <?php endif; ?>
    </div>
  </div>

  </div>

</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <?= sortableTh('neighborhood', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('neighborhood', 'name', 'Nome', $sort, $queryFilters) ?>
        <?= sortableTh('neighborhood', 'city_name', 'Cidade', $sort, $queryFilters) ?>
        <?= sortableTh('neighborhood', 'state_name', 'Estado', $sort, $queryFilters) ?>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($neighborhoods)): ?>
        <?php foreach ($neighborhoods as $row): ?>
          <tr>
            <td style="width:1%;"><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['city_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['state_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('neighborhood.edit')): ?>
                <a href="<?= url('neighborhood/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('neighborhood.informations')): ?>
                <a href="<?= url('neighborhood/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
              <?php if (authCan('neighborhood.delete')): ?>
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>">
                  <i class="bi bi-trash"></i>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="text-center">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-muted"><?= $total ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('neighborhood', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar exclusão</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Tem certeza que deseja excluir o registro "<strong id="recordName"></strong>"?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="#" id="deleteForm" style="display: inline;">
          <input type="hidden" name="id" id="recordId">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function (e) {
  const recordId = e.relatedTarget.dataset.id;
  document.getElementById('recordId').value = recordId;
  document.getElementById('recordName').textContent = e.relatedTarget.dataset.name;
  document.getElementById('deleteForm').action = '<?= url('neighborhood/delete') ?>/' + recordId;
});

const countrySelect = document.getElementById('country_id');
const stateSelect = document.getElementById('state_id');
const citySelect = document.getElementById('city_id');

async function loadCountries() {
  try {
    const response = await fetch('<?= url('neighborhood/getAllCountries') ?>');
    const data = await response.json();
    const currentCountryId = '<?= htmlspecialchars($filters['country_id'], ENT_QUOTES, 'UTF-8') ?>';

    countrySelect.innerHTML = '<option value="">País</option>';
    data.countries.forEach(country => {
      const option = document.createElement('option');
      option.value = country.id;
      option.textContent = country.name;
      if (country.id == currentCountryId) {
        option.selected = true;
      }
      countrySelect.appendChild(option);
    });

    if (currentCountryId) {
      await loadStatesByCountry(currentCountryId);
    }
  } catch (error) {
    console.error('Error loading countries:', error);
  }
}

async function loadStatesByCountry(countryId) {
  const currentStateId = '<?= htmlspecialchars($filters['state_id'], ENT_QUOTES, 'UTF-8') ?>';

  stateSelect.innerHTML = '<option value="">Estado</option>';
  citySelect.innerHTML = '<option value="">Cidade</option>';
  citySelect.disabled = true;

  if (!countryId) {
    stateSelect.disabled = true;
    return;
  }

  try {
    const response = await fetch('<?= url('neighborhood/getStatesByCountry') ?>?country_id=' + encodeURIComponent(countryId));
    const data = await response.json();

    data.states.forEach(state => {
      const option = document.createElement('option');
      option.value = state.id;
      option.textContent = state.name;
      if (state.id == currentStateId) {
        option.selected = true;
      }
      stateSelect.appendChild(option);
    });

    stateSelect.disabled = false;

    if (currentStateId) {
      await loadCitiesByState(currentStateId);
    }
  } catch (error) {
    console.error('Error loading states:', error);
  }
}

async function loadCitiesByState(stateId) {
  const currentCityId = '<?= htmlspecialchars($filters['city_id'], ENT_QUOTES, 'UTF-8') ?>';

  citySelect.innerHTML = '<option value="">Cidade</option>';

  if (!stateId) {
    citySelect.disabled = true;
    return;
  }

  try {
    const response = await fetch('<?= url('neighborhood/getCitiesByState') ?>?state_id=' + encodeURIComponent(stateId));
    const data = await response.json();

    data.cities.forEach(city => {
      const option = document.createElement('option');
      option.value = city.id;
      option.textContent = city.name;
      if (city.id == currentCityId) {
        option.selected = true;
      }
      citySelect.appendChild(option);
    });

    citySelect.disabled = false;
  } catch (error) {
    console.error('Error loading cities:', error);
  }
}

countrySelect.addEventListener('change', function () {
  loadStatesByCountry(this.value);
});

stateSelect.addEventListener('change', function () {
  loadCitiesByState(this.value);
});

loadCountries();
</script>
