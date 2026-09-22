<?php
$filters = array_merge([
  'id'           => '',
  'name'         => '',
  'acronym'      => '',
  'country_id'   => '',
  'state_id'     => '',
  'city_id'      => '',
  'city_name'    => '',
  '_location_invalid' => '',
], $filters ?? []);
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($states) ? count($states) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];

$queryFilters = http_build_query([
  'id'           => $filters['id'],
  'name'         => $filters['name'],
  'acronym'      => $filters['acronym'],
  'country_id'   => $filters['country_id'],
  'state_id'     => $filters['state_id'],
  'city_id'      => $filters['city_id'],
  'city_name'    => $filters['city_name'],
  '_location_invalid' => $filters['_location_invalid'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
$activeFilterCount = count(array_filter([
  $filters['id'],
  $filters['name'],
  $filters['acronym'],
  $filters['country_id'],
  $filters['state_id'],
  $filters['city_id'],
  $filters['_location_invalid'],
], static fn ($value): bool => (string) $value !== ''));
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Estados</li>
  </ol>
</nav>

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true"><i class="bi bi-map fs-4"></i></span>
    <div><p class="small fw-semibold text-primary text-uppercase mb-1">Cadastros</p><h1 class="h3 fw-bold mb-1">Estados</h1><p class="text-body-secondary mb-0">Consulte e gerencie os estados cadastrados por país.</p></div>
  </div>
  <?php if (authCan('state.create')): ?><a href="<?= url('state/create'); ?>" class="btn btn-success d-inline-flex align-items-center justify-content-center gap-2"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Novo estado</span></a><?php endif; ?>
</header>

<form action="<?= url('state'); ?>" class="card border-0 shadow-sm mb-4 p-3 p-lg-4 app-index-filters" id="stateFilters" method="get" novalidate>
  <?= sortHiddenInputs($sort) ?>

  <div class="app-index-filter-heading d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div><h2 class="h5 fw-bold mb-1">Filtros de pesquisa</h2><p class="small text-body-secondary mb-0">Refine a lista de estados.</p></div>
    <?php if ($activeFilterCount > 0): ?><span class="badge rounded-pill text-bg-primary"><?= $activeFilterCount; ?> filtro(s) ativo(s)</span><?php endif; ?>
  </div>

  <?php if ((string) $filters['_location_invalid'] === '1'): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>Um filtro de localização é inválido ou não corresponde à hierarquia selecionada. Selecione novamente os campos e pesquise.</span></div>
  <?php endif; ?>

  <div class="row g-3 align-items-end">

  <div class="col-12">
    <div class="row g-3">

      <div class="col-12 col-md-3">
        <label class="form-label small fw-semibold" for="id">Código</label>
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label small fw-semibold" for="name">Nome</label>
        <input class="form-control" id="name" name="name" placeholder="Nome" type="text" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label small fw-semibold" for="acronym">Sigla</label>
        <input class="form-control" id="acronym" name="acronym" type="text" placeholder="Sigla" value="<?= htmlspecialchars($filters['acronym'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold" for="country_id">País</label>
        <select aria-label="País" class="form-select" data-states-endpoint="<?= url('state/getStatesByCountry'); ?>" id="country_id" name="country_id" <?= empty($countries) ? 'disabled' : ''; ?>>
          <option value=""><?= empty($countries) ? 'Nenhum país encontrado' : 'Selecione um país'; ?></option>
          <?php foreach ($countries ?? [] as $country): ?>
            <option value="<?= (int) ($country['id'] ?? 0); ?>" <?= (string) $filters['country_id'] === (string) ($country['id'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($country['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold" for="state_id">Estado</label>
        <select aria-label="Estado" class="form-select" id="state_id" name="state_id" <?= empty($countryStates) ? 'disabled' : ''; ?>>
          <option value=""><?= $filters['country_id'] === '' ? 'Selecione um país primeiro' : (empty($countryStates) ? 'Nenhum estado encontrado' : 'Selecione um estado'); ?></option>
          <?php foreach ($countryStates ?? [] as $countryState): ?>
            <option value="<?= (int) ($countryState['id'] ?? 0); ?>" <?= (string) $filters['state_id'] === (string) ($countryState['id'] ?? '') ? 'selected' : ''; ?>><?= htmlspecialchars((string) ($countryState['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4 position-relative">
        <label class="form-label small fw-semibold" for="cityName">Cidade</label>
        <input id="city_id" name="city_id" type="hidden" value="<?= htmlspecialchars((string) $filters['city_id'], ENT_QUOTES, 'UTF-8'); ?>">
        <input aria-controls="cityResults" aria-describedby="cityHelp cityFeedback" aria-invalid="false" autocomplete="off" class="form-control js-autocomplete" data-autocomplete-portal="body" data-endpoint="<?= url('state/cityAutocomplete'); ?>" data-extra='{"state_id":"#state_id","country_id":"#country_id"}' data-hidden="#city_id" data-minlen="1" data-param="term" data-results="#cityResults" id="cityName" name="city_name" placeholder="<?= $filters['state_id'] !== '' ? 'Digite o nome da cidade' : 'Selecione um estado primeiro'; ?>" type="text" value="<?= htmlspecialchars((string) $filters['city_name'], ENT_QUOTES, 'UTF-8'); ?>" <?= $filters['state_id'] === '' ? 'disabled' : ''; ?>>
        <ul aria-label="Sugestões de cidades" class="list-group mt-1 w-100" id="cityResults"></ul>
        <div aria-live="polite" class="form-text" id="cityHelp"><?= $filters['state_id'] !== '' ? 'Digite para buscar somente entre as cidades deste estado.' : 'A busca será habilitada após selecionar um estado.'; ?></div>
        <div class="invalid-feedback" id="cityFeedback">Selecione uma cidade na lista de sugestões.</div>
      </div>

    </div>
  </div>

  <div class="col-12">
    <div class="index-search-actions">
      <button type="submit" class="btn btn-primary btn-sm index-search-action" aria-label="Pesquisar" title="Pesquisar"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Pesquisar</span></button>
      <a href="<?= url('state'); ?>" class="btn btn-outline-secondary btn-sm index-search-action" aria-label="Limpar filtros" title="Limpar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span></a>
      <?= pdfExportButton('state', $paginationQuery); ?>
      <?= excelExportButton('state', $paginationQuery); ?>
    </div>
  </div>

  </div>

</form>

<div class="app-index-results-header">
  <div>
    <h2 class="h5 fw-bold mb-1">Resultados</h2>
    <p class="small text-body-secondary mb-0"><?= $activeFilterCount > 0 ? 'Registros encontrados para os filtros informados.' : 'Todos os registros cadastrados.'; ?></p>
  </div>
  <span class="badge rounded-pill text-bg-primary"><?= (int) $total; ?> registro(s)</span>
</div>

<div class="table-responsive app-index-results-table">
  <table class="table table-hover align-middle mb-0">
    <thead>
      <tr>
        <?= sortableTh('state', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('state', 'name', 'Nome', $sort, $queryFilters) ?>
        <?= sortableTh('state', 'acronym', 'Sigla', $sort, $queryFilters) ?>
        <?= sortableTh('state', 'country_name', 'País', $sort, $queryFilters) ?>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody class="table-group-divider">
      <?php if (!empty($states)): ?>
        <?php foreach ($states as $row): ?>
          <tr>
            <td style="width:1%;"><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['acronym'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['country_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('state.edit')): ?>
                <a href="<?= url('state/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('state.informations')): ?>
                <a href="<?= url('state/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Ver detalhes"><i class="bi bi-eye"></i></a>
              <?php endif; ?>
              <?php if (authCan('state.delete')): ?>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>">
                  <i class="bi bi-trash"></i>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="app-index-results-empty">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>


<?php if ($totalPages > 1): ?>
  <div class="app-index-pagination">
  <?= paginationLinks('state', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
  </div>
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
        <form method="POST" action="#" id="deleteForm" data-delete-url="<?= url('state/delete') ?>" class="d-inline">
          <input type="hidden" name="id" id="recordId">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="/manager_assets/autocomplete/autocomplete.js?v=4"></script>
<script src="<?= url('assets/js/states/index.js?v=2'); ?>"></script>
