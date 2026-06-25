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

$actionBadgeClass = static function (string $action): string {
  return match ($action) {
    'create' => 'text-bg-success',
    'update' => 'text-bg-warning',
    'delete' => 'text-bg-danger',
    default => 'text-bg-secondary',
  };
};
?>

<link rel="stylesheet" href="<?= url('assets/css/logs/index.css?v=1'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Logs do sistema</li>
  </ol>
</nav>

<form action="<?= url('log'); ?>" class="index-search-card audit-log-filter mb-4" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="row g-3">
    <div class="col-12 col-xl-10">
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

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="route">Rota</label>
          <input class="form-control" id="route" name="route" type="text" value="<?= htmlspecialchars((string) ($filters['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="date_from">Data inicial</label>
          <input class="form-control" id="date_from" name="date_from" type="date" value="<?= htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="date_to">Data final</label>
          <input class="form-control" id="date_to" name="date_to" type="date" value="<?= htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-2">
      <div class="d-flex flex-column gap-2 align-items-center align-items-xl-end">
        <button class="btn btn-primary w-100" type="submit">Filtrar</button>
        <a class="btn btn-secondary w-100" href="<?= url('log'); ?>">Limpar</a>
      </div>
    </div>
  </div>
</form>

<div class="audit-log-toolbar mb-3">
  <p class="text-muted mb-0"><?= (int) $total; ?> log(s) encontrado(s)</p>
</div>

<div class="table-responsive audit-log-table-wrap">
  <table class="table table-striped table-hover align-middle audit-log-table">
    <thead>
      <tr>
        <?= sortableTh('log', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('log', 'occurred_at', 'Data e hora', $sort, $queryFilters) ?>
        <?= sortableTh('log', 'tenant_name_snapshot', 'Tenant', $sort, $queryFilters) ?>
        <?= sortableTh('log', 'module', 'Módulo', $sort, $queryFilters) ?>
        <?= sortableTh('log', 'action', 'Ação', $sort, $queryFilters) ?>
        <?= sortableTh('log', 'entity_name', 'Entidade', $sort, $queryFilters) ?>
        <th>Registro</th>
        <?= sortableTh('log', 'performed_by_name', 'Usuário', $sort, $queryFilters) ?>
        <th>Rota</th>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
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
          <tr>
            <td><?= (int) $row['id']; ?></td>
            <td><?= htmlspecialchars(maskDateTimeBr((string) ($row['occurred_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
              <strong><?= htmlspecialchars($tenantLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
              <?php if (!empty($row['tenant_database'])): ?>
                <small class="d-block text-muted"><?= htmlspecialchars((string) $row['tenant_database'], ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) ($row['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><span class="badge <?= $actionBadgeClass((string) ($row['action'] ?? '')); ?>"><?= htmlspecialchars((string) ($actions[$row['action']] ?? $row['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
            <td><?= htmlspecialchars((string) ($row['entity_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars((string) ($row['entity_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><code><?= htmlspecialchars((string) ($row['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
            <td class="text-end text-nowrap">
              <?php if (authCan('log.informations')): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= url('log/informations/' . (int) $row['id']); ?>">
                  <i class="bi bi-search me-1"></i> Ver
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="10" class="text-center text-muted py-4">Nenhum log encontrado para os filtros informados.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('log', (int) $page, (int) $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima'); ?>
<?php endif; ?>

<script src="<?= url('assets/js/logs/index.js?v=1'); ?>"></script>
