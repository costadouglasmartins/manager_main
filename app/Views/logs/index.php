<?php
$filters = $filters ?? [];
$logs = $logs ?? [];
$modules = $modules ?? [];
$actions = $actions ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? count($logs);
$sort = $sort ?? ['sort' => 'occurred_at', 'direction' => 'desc'];

$queryFilters = http_build_query([
  'id' => $filters['id'] ?? '',
  'tenant' => $filters['tenant'] ?? '',
  'module' => $filters['module'] ?? '',
  'action' => $filters['action'] ?? '',
  'entity_name' => $filters['entity_name'] ?? '',
  'entity_id' => $filters['entity_id'] ?? '',
  'performed_by' => $filters['performed_by'] ?? '',
  'route' => $filters['route'] ?? '',
  'date_from' => $filters['date_from'] ?? '',
  'date_to' => $filters['date_to'] ?? '',
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
$activeFilterCount = count(array_filter($filters, static fn ($value): bool => (string) $value !== ''));

$actionBadgeClass = static function (string $action): string {
  return match ($action) {
    'create' => 'text-bg-success',
    'update' => 'text-bg-warning',
    'delete' => 'text-bg-danger',
    'login' => 'text-bg-info',
    'logout' => 'text-bg-dark',
    'list' => 'text-bg-primary',
    default => 'text-bg-secondary',
  };
};
?>

<link rel="stylesheet" href="<?= url('assets/css/logs/index.css?v=3'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Logs do sistema</li>
  </ol>
</nav>

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true"><i class="bi bi-activity fs-4"></i></span>
    <div><p class="small fw-semibold text-primary text-uppercase mb-1">Auditoria</p><h1 class="h3 fw-bold mb-1">Logs do sistema</h1><p class="text-body-secondary mb-0">Consulte eventos, responsáveis e alterações registradas.</p></div>
  </div>
</header>

<form action="<?= url('log'); ?>" class="card border-0 shadow-sm mb-4 p-3 p-lg-4 app-index-filters audit-log-filter" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="app-index-filter-heading d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div><h2 class="h5 fw-bold mb-1">Filtros de pesquisa</h2><p class="small text-body-secondary mb-0">Refine os eventos do histórico de auditoria.</p></div>
    <?php if ($activeFilterCount > 0): ?><span class="badge rounded-pill text-bg-primary"><?= $activeFilterCount; ?> filtro(s) ativo(s)</span><?php endif; ?>
  </div>

  <div class="row g-3">
    <div class="col-12">
      <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-2">
          <label class="form-label" for="id">Código</label>
          <input class="form-control" id="id" name="id" type="text" value="<?= htmlspecialchars((string) ($filters['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="tenant">Tenant</label>
          <input class="form-control" id="tenant" name="tenant" type="text" placeholder="Nome ou banco" value="<?= htmlspecialchars((string) ($filters['tenant'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="module">Módulo</label>
          <select class="form-select" id="module" name="module">
            <option value="">Todos</option>
            <?php foreach ($modules as $module): ?>
              <option value="<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8'); ?>" <?= (($filters['module'] ?? '') === $module) ? 'selected' : ''; ?>><?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-6 col-xl-2">
          <label class="form-label" for="action">Ação</label>
          <select class="form-select" id="action" name="action">
            <option value="">Todas</option>
            <?php foreach ($actions as $actionValue => $actionLabel): ?>
              <option value="<?= htmlspecialchars($actionValue, ENT_QUOTES, 'UTF-8'); ?>" <?= (($filters['action'] ?? '') === $actionValue) ? 'selected' : ''; ?>><?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-6 col-xl-2">
          <label class="form-label" for="entity_id">ID registro</label>
          <input class="form-control" id="entity_id" name="entity_id" type="text" value="<?= htmlspecialchars((string) ($filters['entity_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="entity_name">Entidade</label>
          <input class="form-control" id="entity_name" name="entity_name" type="text" value="<?= htmlspecialchars((string) ($filters['entity_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="performed_by">Usuário</label>
          <input class="form-control" id="performed_by" name="performed_by" type="text" placeholder="Nome ou username" value="<?= htmlspecialchars((string) ($filters['performed_by'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-2">
          <label class="form-label" for="route">Rota</label>
          <input class="form-control" id="route" name="route" type="text" value="<?= htmlspecialchars((string) ($filters['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-2">
          <label class="form-label" for="date_from">Data inicial</label>
          <input class="form-control" id="date_from" name="date_from" type="date" value="<?= htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-2">
          <label class="form-label" for="date_to">Data final</label>
          <input class="form-control" id="date_to" name="date_to" type="date" value="<?= htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="index-search-actions">
        <button class="btn btn-primary btn-sm index-search-action" type="submit" aria-label="Filtrar" title="Filtrar"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Filtrar</span></button>
        <a class="btn btn-outline-secondary btn-sm index-search-action" href="<?= url('log'); ?>" aria-label="Limpar filtros" title="Limpar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span></a>
        <?= pdfExportButton('log', $paginationQuery); ?>
        <?= excelExportButton('log', $paginationQuery); ?>
      </div>
    </div>
  </div>
</form>

<div class="app-index-results-header audit-log-toolbar">
  <div>
    <h2 class="h5 fw-bold mb-1">Resultados</h2>
    <p class="small text-body-secondary mb-0"><?= $activeFilterCount > 0 ? 'Logs encontrados para os filtros informados.' : 'Todos os logs registrados.'; ?></p>
  </div>
  <span class="badge rounded-pill text-bg-primary" id="auditLogTotal" data-total="<?= (int) $total; ?>"><?= (int) $total; ?> registro(s)</span>
</div>

<div class="table-responsive app-index-results-table">
  <table class="table table-hover align-middle mb-0 audit-log-table">
    <thead>
      <tr>
        <?= sortableTh('log', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('log', 'occurred_at', 'Data e hora', $sort, $queryFilters) ?>
        <?= sortableTh('log', 'tenant_name_snapshot', 'Tenant', $sort, $queryFilters) ?>
        <?= sortableTh('log', 'module', 'Módulo', $sort, $queryFilters) ?>
        <?= sortableTh('log', 'action', 'Ação', $sort, $queryFilters) ?>
        <?= sortableTh('log', 'entity_name', 'Entidade', $sort, $queryFilters) ?>
        <?= sortableTh('log', 'performed_by_name', 'Usuário', $sort, $queryFilters) ?>
        <th>Rota</th>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody class="table-group-divider">
      <?php if (!empty($logs)): ?>
        <?php foreach ($logs as $row): ?>
          <?php
          $tenantLabel = trim((string) ($row['tenant_name_snapshot'] ?? ''));
          if ($tenantLabel === '') {
            $tenantLabel = trim((string) ($row['tenant_database'] ?? 'Sem tenant'));
          }

          $userLabel = trim((string) ($row['performed_by_name'] ?? ''));
          if ($userLabel === '') {
            $userLabel = trim((string) ($row['performed_by_username'] ?? 'Sistema'));
          }
          ?>
          <tr data-log-row data-log-id="<?= (int) $row['id']; ?>">
            <td><?= (int) $row['id']; ?></td>
            <td><?= htmlspecialchars(maskLogDateTimeBr((string) ($row['occurred_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
              <strong><?= htmlspecialchars($tenantLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
              <?php if (!empty($row['tenant_database'])): ?>
                <small class="d-block text-muted"><?= htmlspecialchars((string) $row['tenant_database'], ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) ($row['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><span class="badge <?= $actionBadgeClass((string) ($row['action'] ?? '')); ?>"><?= htmlspecialchars((string) ($actions[$row['action']] ?? $row['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
            <td><?= htmlspecialchars((string) ($row['entity_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
              <span><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8'); ?></span>
              <?php if (!empty($row['performed_by_user_id']) && authCan('log.userTimeline')): ?>
                <a class="audit-log-user-timeline" href="<?= url('log/user-timeline/' . (int) $row['performed_by_user_id']); ?>">Linha do tempo</a>
              <?php endif; ?>
            </td>
            <td><code><?= htmlspecialchars((string) ($row['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
            <td class="text-end text-nowrap">
              <?php if (authCan('log.informations')): ?>
                <a class="btn btn-sm btn-primary" href="<?= url('log/informations/' . (int) $row['id']); ?>" title="Ver log" aria-label="Ver log">
                  <i class="bi bi-search"></i>
                </a>
                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#logDeleteModal" data-log-id="<?= (int) $row['id']; ?>" data-log-label="Log #<?= (int) $row['id']; ?> - <?= htmlspecialchars((string) ($row['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>/<?= htmlspecialchars((string) ($actions[$row['action']] ?? $row['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-delete-url="<?= url('log/delete/' . (int) $row['id']); ?>" title="Remover log" aria-label="Remover log">
                  <i class="bi bi-trash"></i>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="10" class="app-index-results-empty">Nenhum log encontrado para os filtros informados.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
  <div class="app-index-pagination">
    <?= paginationLinks('log', (int) $page, (int) $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima'); ?>
  </div>
<?php endif; ?>

<div class="modal fade" id="logDeleteModal" tabindex="-1" aria-labelledby="logDeleteModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content audit-log-delete-modal">
      <form id="logDeleteForm">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="logDeleteModalTitle">Remover log</h5>
            <small class="text-muted">Confirmação pela senha de <code>admin@admin.com</code>.</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">Você está prestes a remover permanentemente <strong id="logDeleteTargetLabel">este log</strong>.</p>
          <label class="form-label" for="logDeletePassword">Senha de confirmação</label>
          <input class="form-control" id="logDeletePassword" name="password" type="password" autocomplete="current-password" required>
          <div class="form-text">A remoção acontece apenas quando a senha informada estiver correta.</div>
          <div class="alert alert-danger d-none mt-3 mb-0" id="logDeleteFeedback" role="alert"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-danger" type="submit" id="logDeleteSubmit">Remover log</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/logs/index.js?v=4'); ?>"></script>
