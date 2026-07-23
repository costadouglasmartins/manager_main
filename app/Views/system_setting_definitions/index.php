<?php
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$canCreate = authIsRoot() || authCan('system_setting_definition.create');
$canEdit = authIsRoot() || authCan('system_setting_definition.edit');
$canView = authIsRoot() || authCan('system_setting_definition.informations');
$canDelete = authIsRoot() || authCan('system_setting_definition.delete');
$filters = array_merge([
	'id'         => '',
	'module_id'  => '',
	'key_name'   => '',
	'label'      => '',
	'value_type' => '',
	'active'     => '',
], is_array($filters ?? null) ? $filters : []);
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($definitions ?? null) ? count($definitions) : 0);
$sort = $sort ?? ['sort' => 'sort_order', 'direction' => 'asc'];
$queryFilters = http_build_query([
	'id'         => $filters['id'],
	'module_id'  => $filters['module_id'],
	'key_name'   => $filters['key_name'],
	'label'      => $filters['label'],
	'value_type' => $filters['value_type'],
	'active'     => $filters['active'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
$activeFilterCount = count(array_filter($filters, static fn($value): bool => (string) $value !== ''));
?>

<nav class="mb-3" aria-label="breadcrumb">
  <ol class="breadcrumb mb-0">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Definições de configurações</li>
  </ol>
</nav>

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="system-setting-catalog-icon d-inline-flex align-items-center justify-content-center rounded-3 p-3" aria-hidden="true">
      <i class="bi bi-sliders2 fs-4"></i>
    </span>
    <div>
      <p class="small fw-semibold text-primary text-uppercase mb-1">System settings</p>
      <h1 class="h3 fw-bold mb-1">Definições de configurações</h1>
      <p class="text-body-secondary mb-0">Cadastre as chaves que podem receber valores por tenant.</p>
    </div>
  </div>

  <?php if ($canCreate): ?>
    <a href="<?= url('system_setting_definition/create'); ?>" class="btn btn-success d-inline-flex align-items-center justify-content-center gap-2">
      <i class="bi bi-plus-lg" aria-hidden="true"></i>
      <span>Nova definição</span>
    </a>
  <?php endif; ?>
</header>

<form action="<?= url('system_setting_definition'); ?>" class="card system-setting-catalog-card shadow-sm mb-4 p-3 p-lg-4 app-index-filters" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="app-index-filter-heading d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div>
      <h2 class="h5 fw-bold mb-1">Filtros de pesquisa</h2>
      <p class="small text-body-secondary mb-0">Refine a lista por módulo, chave, rótulo, tipo ou status.</p>
    </div>
    <?php if ($activeFilterCount > 0): ?>
      <span class="badge rounded-pill text-bg-primary"><?= (int) $activeFilterCount; ?> filtro(s) ativo(s)</span>
    <?php endif; ?>
  </div>

  <div class="row g-3 align-items-end">
    <div class="col-12 col-md-6 col-lg-2">
      <label class="form-label small fw-semibold" for="id">Código</label>
      <input class="form-control" id="id" inputmode="numeric" name="id" placeholder="Ex.: 1" type="text" value="<?= $e($filters['id']); ?>">
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <label class="form-label small fw-semibold" for="module_id">Módulo</label>
      <select class="form-select" id="module_id" name="module_id">
        <option value="">Todos</option>
        <?php foreach ($modules as $module): ?>
          <option value="<?= (int) $module['id']; ?>" <?= (int) $module['id'] === (int) $filters['module_id'] ? 'selected' : ''; ?>><?= $e($module['name'] ?? ''); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <label class="form-label small fw-semibold" for="key_name">Chave</label>
      <input autocomplete="off" class="form-control font-monospace" id="key_name" name="key_name" placeholder="max_online_users" type="text" value="<?= $e($filters['key_name']); ?>">
    </div>

    <div class="col-12 col-md-6 col-lg-4">
      <label class="form-label small fw-semibold" for="label">Rótulo</label>
      <input autocomplete="off" class="form-control" id="label" name="label" placeholder="Max online users" type="text" value="<?= $e($filters['label']); ?>">
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <label class="form-label small fw-semibold" for="value_type">Tipo</label>
      <select class="form-select" id="value_type" name="value_type">
        <option value="">Todos</option>
        <?php foreach ($valueTypes as $type): ?>
          <option value="<?= $e($type); ?>" <?= (string) $filters['value_type'] === (string) $type ? 'selected' : ''; ?>><?= $e($type); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <label class="form-label small fw-semibold" for="active">Status</label>
      <select class="form-select" id="active" name="active">
        <option value="">Todos</option>
        <option value="1" <?= (string) $filters['active'] === '1' ? 'selected' : ''; ?>>Ativa</option>
        <option value="0" <?= (string) $filters['active'] === '0' ? 'selected' : ''; ?>>Inativa</option>
      </select>
    </div>

    <div class="col-12">
      <div class="index-search-actions">
        <button class="btn btn-primary btn-sm index-search-action" type="submit" aria-label="Pesquisar" title="Pesquisar">
          <i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Pesquisar</span>
        </button>
        <a href="<?= url('system_setting_definition'); ?>" class="btn btn-outline-secondary btn-sm index-search-action" aria-label="Limpar filtros" title="Limpar filtros">
          <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span>
        </a>
      </div>
    </div>
  </div>
</form>

<div class="app-index-results-header">
  <div>
    <h2 class="h5 fw-bold mb-1">Resultados</h2>
    <p class="small text-body-secondary mb-0"><?= $activeFilterCount > 0 ? 'Registros encontrados para os filtros informados.' : 'Todas as definições cadastradas.'; ?></p>
  </div>
  <span class="badge rounded-pill text-bg-primary"><?= (int) $total; ?> registro(s)</span>
</div>

<div class="table-responsive app-index-results-table">
  <table class="table table-hover align-middle mb-0">
    <caption class="visually-hidden">Lista de definições de configurações</caption>
    <thead>
      <tr>
        <?= sortableTh('system_setting_definition', 'sort_order', 'Ordem', $sort, $queryFilters, 'class="text-nowrap"') ?>
        <?= sortableTh('system_setting_definition', 'id', '#', $sort, $queryFilters, 'class="text-nowrap"') ?>
        <?= sortableTh('system_setting_definition', 'module_name', 'Módulo', $sort, $queryFilters) ?>
        <?= sortableTh('system_setting_definition', 'label', 'Definição', $sort, $queryFilters) ?>
        <?= sortableTh('system_setting_definition', 'key_name', 'Chave', $sort, $queryFilters) ?>
        <?= sortableTh('system_setting_definition', 'value_type', 'Tipo', $sort, $queryFilters, 'class="text-nowrap"') ?>
        <?= sortableTh('system_setting_definition', 'active', 'Status', $sort, $queryFilters, 'class="text-nowrap"') ?>
        <th class="text-end" scope="col">Ações</th>
      </tr>
    </thead>
    <tbody class="table-group-divider">
      <?php if (!empty($definitions)): ?>
        <?php foreach ($definitions as $row): ?>
          <?php $labelText = (string) ($row['label'] ?? ''); ?>
          <tr>
            <td class="text-nowrap"><?= (int) ($row['sort_order'] ?? 0); ?></td>
            <td class="text-nowrap"><span class="badge bg-body-tertiary border text-body">#<?= (int) ($row['id'] ?? 0); ?></span></td>
            <td>
              <span class="fw-semibold"><?= $e($row['module_name'] ?? ''); ?></span>
              <code class="system-setting-catalog-code small d-inline-flex mt-1"><?= $e($row['module_slug'] ?? ''); ?></code>
            </td>
            <td>
              <span class="fw-semibold"><?= $e($labelText); ?></span>
              <?php if (!empty($row['description'])): ?>
                <span class="small text-body-secondary d-block"><?= $e($row['description']); ?></span>
              <?php endif; ?>
            </td>
            <td><code class="system-setting-catalog-code"><?= $e($row['key_name'] ?? ''); ?></code></td>
            <td class="text-nowrap"><span class="badge bg-body-tertiary border text-body"><?= $e($row['value_type'] ?? ''); ?></span></td>
            <td class="text-nowrap">
              <span class="badge <?= (int) ($row['active'] ?? 0) === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                <?= (int) ($row['active'] ?? 0) === 1 ? 'Ativa' : 'Inativa'; ?>
              </span>
            </td>
            <td class="text-nowrap text-end">
              <div class="system-setting-catalog-actions" role="group" aria-label="Ações da definição <?= $e($labelText); ?>">
                <?php if ($canEdit): ?>
                  <a class="btn btn-outline-primary" href="<?= url('system_setting_definition/edit/' . (int) $row['id']); ?>" aria-label="Editar <?= $e($labelText); ?>" title="Editar">
                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                  </a>
                <?php endif; ?>
                <?php if ($canView): ?>
                  <a class="btn btn-outline-secondary" href="<?= url('system_setting_definition/informations/' . (int) $row['id']); ?>" aria-label="Ver detalhes de <?= $e($labelText); ?>" title="Ver detalhes">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                  </a>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                  <button class="btn btn-outline-danger" data-bs-target="#deleteModal" data-bs-toggle="modal" data-id="<?= (int) $row['id']; ?>" data-name="<?= $e($labelText); ?>" type="button" aria-label="Excluir <?= $e($labelText); ?>" title="Excluir">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td class="text-center py-5" colspan="8">
            <i class="bi bi-search fs-2 text-body-secondary d-block mb-2" aria-hidden="true"></i>
            <strong class="d-block">Nenhuma definição encontrada</strong>
            <span class="small text-body-secondary">Ajuste os filtros ou cadastre uma nova definição.</span>
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
  <div class="app-index-pagination">
    <?= paginationLinks('system_setting_definition', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
  </div>
<?php endif; ?>

<?php if ($canDelete): ?>
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="deleteModalLabel">Confirmar exclusão</h2>
          <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Tem certeza que deseja excluir a definição <strong id="recordName"></strong>?</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
          <form action="#" class="d-inline" data-delete-base-url="<?= url('system_setting_definition/delete'); ?>" id="deleteForm" method="POST">
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

<script src="<?= url('assets/js/system_setting_definitions/index.js?v=1'); ?>"></script>
