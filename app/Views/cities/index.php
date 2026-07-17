<?php
$filters = array_merge([
  'id'         => '',
  'name'       => '',
  'country_id' => '',
  'state_id'   => '',
], $filters ?? []);
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($cities) ? count($cities) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];

$queryFilters = http_build_query([
  'id'         => $filters['id'],
  'name'       => $filters['name'],
  'country_id' => $filters['country_id'],
  'state_id'   => $filters['state_id'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
$activeFilterCount = count(array_filter($filters, static fn ($value): bool => (string) $value !== ''));
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Cidades</li>
  </ol>
</nav>

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true"><i class="bi bi-buildings fs-4"></i></span>
    <div><p class="small fw-semibold text-primary text-uppercase mb-1">Cadastros</p><h1 class="h3 fw-bold mb-1">Cidades</h1><p class="text-body-secondary mb-0">Consulte e gerencie as cidades por país e estado.</p></div>
  </div>
  <?php if (authCan('city.create')): ?><a href="<?= url('city/create'); ?>" class="btn btn-success d-inline-flex align-items-center justify-content-center gap-2"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Nova cidade</span></a><?php endif; ?>
</header>

<form action="<?= url('city'); ?>" class="card border-0 shadow-sm mb-4 p-3 p-lg-4 app-index-filters" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="app-index-filter-heading d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div><h2 class="h5 fw-bold mb-1">Filtros de pesquisa</h2><p class="small text-body-secondary mb-0">Refine a lista de cidades por localização.</p></div>
    <?php if ($activeFilterCount > 0): ?><span class="badge rounded-pill text-bg-primary"><?= $activeFilterCount; ?> filtro(s) ativo(s)</span><?php endif; ?>
  </div>

  <div class="row g-3 align-items-end">

  <div class="col-12">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-12">
        <label class="form-label small fw-semibold" for="id">Código</label>
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-6">
        <label class="form-label small fw-semibold" for="country_id">País</label>
        <select class="form-select" id="country_id" name="country_id" aria-label="País" data-states-endpoint="<?= url('city/getStatesByCountry'); ?>">
          <option value="">País</option>
          <?php foreach ($countries ?? [] as $country): ?>
            <option value="<?= (int) $country['id'] ?>" <?= (string) $filters['country_id'] === (string) $country['id'] ? 'selected' : '' ?>><?= htmlspecialchars($country['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6 col-lg-6">
        <label class="form-label small fw-semibold" for="state_id">Estado</label>
        <select class="form-select" id="state_id" name="state_id" aria-label="Estado" <?= $filters['country_id'] === '' ? 'disabled' : '' ?>>
          <option value="">Estado</option>
          <?php foreach ($states ?? [] as $state): ?>
            <option value="<?= (int) $state['id'] ?>" <?= (string) $filters['state_id'] === (string) $state['id'] ? 'selected' : '' ?>><?= htmlspecialchars($state['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6 col-lg-12 position-relative">
        <label class="form-label small fw-semibold" for="name">Cidade</label>
        <input id="name" type="text" class="form-control js-autocomplete" autocomplete="off" placeholder="Cidade" name="name" data-endpoint="<?= url('city/autocomplete'); ?>" data-results="#cityResults" data-hidden="#id" data-minlen="1" data-param="term" data-extra='{"state_id":"#state_id"}' data-autocomplete-portal="body" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>" <?= $filters['state_id'] === '' ? 'disabled' : '' ?>>
        <ul id="cityResults" class="list-group mt-1 w-100"></ul>
      </div>

    </div>
  </div>

  <div class="col-12">
    <div class="index-search-actions">
      <button type="submit" class="btn btn-primary btn-sm index-search-action" aria-label="Pesquisar" title="Pesquisar"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Pesquisar</span></button>
      <a href="<?= url('city'); ?>" class="btn btn-outline-secondary btn-sm index-search-action" aria-label="Limpar filtros" title="Limpar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span></a>
      <?= pdfExportButton('city', $paginationQuery); ?>
      <?= excelExportButton('city', $paginationQuery); ?>
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
        <?= sortableTh('city', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('city', 'name', 'Nome', $sort, $queryFilters) ?>
        <?= sortableTh('city', 'state_name', 'Estado', $sort, $queryFilters) ?>
        <?= sortableTh('city', 'country_name', 'País', $sort, $queryFilters) ?>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody class="table-group-divider">
      <?php if (!empty($cities)): ?>
        <?php foreach ($cities as $row): ?>
          <tr>
            <td style="width:1%;"><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['state_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['country_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('city.edit')): ?>
                <a href="<?= url('city/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('city.informations')): ?>
                <a href="<?= url('city/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Ver detalhes"><i class="bi bi-eye"></i></a>
              <?php endif; ?>
              <?php if (authCan('city.delete')): ?>
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
  <?= paginationLinks('city', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
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
        <form method="POST" action="#" id="deleteForm" data-delete-url="<?= url('city/delete') ?>" class="d-inline">
          <input type="hidden" name="id" id="recordId">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="/assets/autocomplete/autocomplete.js?v=4"></script>
<script src="<?= url('assets/js/cities/index.js?v=2'); ?>"></script>
