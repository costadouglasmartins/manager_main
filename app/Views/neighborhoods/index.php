<?php
$filters = array_merge([
  'id'         => '',
  'name'       => '',
  'country_id' => '',
  'state_id'   => '',
  'city_id'    => '',
], is_array($filters ?? null) ? $filters : []);
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
$activeFilterCount = count(array_filter($filters, static fn ($value): bool => (string) $value !== ''));
?>

<nav class="mb-3" aria-label="breadcrumb">
  <ol class="breadcrumb mb-0">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Bairros</li>
  </ol>
</nav>

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true">
      <i class="bi bi-signpost-split fs-4"></i>
    </span>
    <div>
      <p class="small fw-semibold text-primary text-uppercase mb-1">Cadastros</p>
      <h1 class="h3 fw-bold mb-1">Bairros</h1>
      <p class="text-body-secondary mb-0">Consulte e gerencie os bairros cadastrados por localização.</p>
    </div>
  </div>

  <?php if (authCan('neighborhood.create')): ?>
    <a href="<?= url('neighborhood/create'); ?>" class="btn btn-success d-inline-flex align-items-center justify-content-center gap-2">
      <i class="bi bi-plus-lg" aria-hidden="true"></i>
      <span>Novo bairro</span>
    </a>
  <?php endif; ?>
</header>

<form action="<?= url('neighborhood'); ?>" class="card border-0 shadow-sm mb-4" id="neighborhoodFilters" method="get" novalidate>
  <?= sortHiddenInputs($sort) ?>

  <div class="card-body p-3 p-lg-4">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
      <div>
        <h2 class="h5 fw-bold mb-1">Filtros de pesquisa</h2>
        <p class="small text-body-secondary mb-0">Refine a lista por código, nome ou localização.</p>
      </div>
      <?php if ($activeFilterCount > 0): ?>
        <span class="badge rounded-pill text-bg-primary"><?= $activeFilterCount; ?> filtro(s) ativo(s)</span>
      <?php endif; ?>
    </div>

    <div class="row g-3">
      <div class="col-12 col-md-3">
        <label class="form-label small fw-semibold" for="id">Código</label>
        <input class="form-control" id="id" inputmode="numeric" name="id" placeholder="Ex.: 123" type="text" value="<?= htmlspecialchars((string) $filters['id'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="col-12 col-md-9">
        <label class="form-label small fw-semibold" for="name">Nome do bairro</label>
        <input autocomplete="off" class="form-control" id="name" name="name" placeholder="Digite o nome do bairro" type="text" value="<?= htmlspecialchars((string) $filters['name'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold" for="country_id">País</label>
        <select
          class="form-select"
          data-countries-endpoint="<?= url('neighborhood/getAllCountries'); ?>"
          data-current-value="<?= htmlspecialchars((string) $filters['country_id'], ENT_QUOTES, 'UTF-8'); ?>"
          id="country_id"
          name="country_id"
        >
          <option value="">País</option>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold" for="state_id">Estado</label>
        <select
          class="form-select"
          data-current-value="<?= htmlspecialchars((string) $filters['state_id'], ENT_QUOTES, 'UTF-8'); ?>"
          data-states-endpoint="<?= url('neighborhood/getStatesByCountry'); ?>"
          disabled
          id="state_id"
          name="state_id"
        >
          <option value="">Estado</option>
        </select>
      </div>

      <div class="col-12 col-md-4 position-relative">
        <label class="form-label small fw-semibold" for="cityName">Cidade</label>
        <input id="city_id" name="city_id" type="hidden" value="<?= htmlspecialchars((string) $filters['city_id'], ENT_QUOTES, 'UTF-8'); ?>">
        <input
          aria-describedby="cityHelp cityFeedback"
          autocomplete="off"
          class="form-control js-autocomplete"
          data-autocomplete-portal="body"
          data-cities-endpoint="<?= url('neighborhood/getCitiesByState'); ?>"
          data-current-id="<?= htmlspecialchars((string) $filters['city_id'], ENT_QUOTES, 'UTF-8'); ?>"
          data-endpoint="<?= url('city/autocomplete'); ?>"
          data-extra='{"state_id":"#state_id"}'
          data-hidden="#city_id"
          data-minlen="1"
          data-param="term"
          data-results="#cityResults"
          disabled
          id="cityName"
          placeholder="Selecione um estado primeiro"
          type="text"
        >
        <ul class="list-group mt-1 w-100" id="cityResults" aria-label="Sugestões de cidades"></ul>
        <div class="form-text" id="cityHelp">A busca será limitada às cidades do estado selecionado.</div>
        <div class="invalid-feedback" id="cityFeedback">Selecione uma cidade na lista de sugestões.</div>
      </div>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 border-top pt-3 mt-4">
      <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="submit">
        <i class="bi bi-search" aria-hidden="true"></i>
        <span>Pesquisar</span>
      </button>
      <a href="<?= url('neighborhood'); ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
        <span>Limpar</span>
      </a>
      <span class="vr d-none d-sm-block mx-1" aria-hidden="true"></span>
      <a href="<?= htmlspecialchars(pdfExportUrl('neighborhood', $paginationQuery), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-danger d-inline-flex align-items-center gap-2">
        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
        <span>PDF</span>
      </a>
      <a href="<?= htmlspecialchars(excelExportUrl('neighborhood', $paginationQuery), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-success d-inline-flex align-items-center gap-2">
        <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
        <span>Excel</span>
      </a>
    </div>
  </div>
</form>

<section class="card border-0 shadow-sm overflow-hidden" aria-labelledby="neighborhoodResultsTitle">
  <div class="card-header bg-body border-0 d-flex align-items-center justify-content-between gap-3 p-3 p-lg-4 pb-2">
    <div>
      <h2 class="h5 fw-bold mb-1" id="neighborhoodResultsTitle">Resultados</h2>
      <p class="small text-body-secondary mb-0"><?= $activeFilterCount > 0 ? 'Registros encontrados para os filtros informados.' : 'Todos os registros cadastrados.'; ?></p>
    </div>
    <span class="badge rounded-pill text-bg-primary"><?= (int) $total; ?> registro(s)</span>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <caption class="visually-hidden">Lista de bairros encontrados</caption>
      <thead>
        <tr>
          <?= sortableTh('neighborhood', 'id', '#', $sort, $queryFilters, 'class="text-nowrap"') ?>
          <?= sortableTh('neighborhood', 'name', 'Nome', $sort, $queryFilters) ?>
          <?= sortableTh('neighborhood', 'city_name', 'Cidade', $sort, $queryFilters) ?>
          <?= sortableTh('neighborhood', 'state_name', 'Estado', $sort, $queryFilters) ?>
          <th class="text-end" scope="col">Ações</th>
        </tr>
      </thead>
      <tbody class="table-group-divider">
        <?php if (!empty($neighborhoods)): ?>
          <?php foreach ($neighborhoods as $row): ?>
            <?php $neighborhoodName = (string) ($row['name'] ?? ''); ?>
            <tr>
              <td class="text-nowrap"><span class="badge bg-body-tertiary border text-body">#<?= (int) ($row['id'] ?? 0); ?></span></td>
              <td class="fw-semibold"><?= htmlspecialchars($neighborhoodName, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars((string) ($row['city_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?= htmlspecialchars((string) ($row['state_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="text-nowrap text-end">
                <div class="btn-group btn-group-sm" role="group" aria-label="Ações do bairro <?= htmlspecialchars($neighborhoodName, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php if (authCan('neighborhood.edit')): ?>
                    <a class="btn btn-outline-primary" href="<?= url('neighborhood/edit/' . (int) $row['id']); ?>" aria-label="Editar <?= htmlspecialchars($neighborhoodName, ENT_QUOTES, 'UTF-8'); ?>" title="Editar">
                      <i class="bi bi-pencil-square" aria-hidden="true"></i>
                    </a>
                  <?php endif; ?>
                  <?php if (authCan('neighborhood.informations')): ?>
                    <a class="btn btn-outline-secondary" href="<?= url('neighborhood/informations/' . (int) $row['id']); ?>" aria-label="Ver detalhes de <?= htmlspecialchars($neighborhoodName, ENT_QUOTES, 'UTF-8'); ?>" title="Ver detalhes">
                      <i class="bi bi-eye" aria-hidden="true"></i>
                    </a>
                  <?php endif; ?>
                  <?php if (authCan('neighborhood.delete')): ?>
                    <button
                      aria-label="Excluir <?= htmlspecialchars($neighborhoodName, ENT_QUOTES, 'UTF-8'); ?>"
                      class="btn btn-outline-danger"
                      data-bs-target="#deleteModal"
                      data-bs-toggle="modal"
                      data-id="<?= (int) $row['id']; ?>"
                      data-name="<?= htmlspecialchars($neighborhoodName, ENT_QUOTES, 'UTF-8'); ?>"
                      title="Excluir"
                      type="button"
                    >
                      <i class="bi bi-trash" aria-hidden="true"></i>
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td class="text-center py-5" colspan="5">
              <i class="bi bi-search fs-2 text-body-secondary d-block mb-2" aria-hidden="true"></i>
              <strong class="d-block">Nenhum bairro encontrado</strong>
              <span class="small text-body-secondary">Ajuste os filtros ou limpe a pesquisa para tentar novamente.</span>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-body border-0 p-3 p-lg-4">
      <?= paginationLinks('neighborhood', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
    </div>
  <?php endif; ?>
</section>

<?php if (authCan('neighborhood.delete')): ?>
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="deleteModalLabel">Confirmar exclusão</h2>
          <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Tem certeza que deseja excluir o bairro <strong id="recordName"></strong>?</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
          <form action="#" class="d-inline" data-delete-url="<?= url('neighborhood/delete'); ?>" id="deleteForm" method="POST">
            <input id="recordId" name="id" type="hidden">
            <button class="btn btn-danger d-inline-flex align-items-center gap-2" type="submit">
              <i class="bi bi-trash" aria-hidden="true"></i>
              <span>Excluir</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<script src="/assets/autocomplete/autocomplete.js?v=4"></script>
<script src="<?= url('assets/js/neighborhoods/index.js?v=1'); ?>"></script>
