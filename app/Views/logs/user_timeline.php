<?php
$user = $user ?? [];
$userId = (int) ($userId ?? 0);
$logs = $logs ?? [];
$filters = $filters ?? [];
$actions = $actions ?? [];
$modules = $modules ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? count($logs);

$queryFilters = http_build_query([
  'action' => $filters['action'] ?? '',
  'module' => $filters['module'] ?? '',
  'tenant' => $filters['tenant'] ?? '',
  'date_from' => $filters['date_from'] ?? '',
  'date_to' => $filters['date_to'] ?? '',
]);

$userLabel = trim((string) ($user['performed_by_name'] ?? ''));
if ($userLabel === '') {
  $userLabel = trim((string) ($user['performed_by_username'] ?? ('Usuário #' . $userId)));
}

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

<link rel="stylesheet" href="<?= url('assets/css/logs/user_timeline.css?v=2'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('log'); ?>">Logs do sistema</a></li>
    <li class="breadcrumb-item active" aria-current="page">Linha do tempo do usuário</li>
  </ol>
</nav>

<div class="audit-user-timeline-header mb-4">
  <div>
    <span class="audit-user-timeline-kicker">Usuário #<?= (int) $userId; ?></span>
    <h5 class="mb-1"><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8'); ?></h5>
    <p class="text-muted mb-0"><?= (int) $total; ?> evento(s) encontrado(s) para este usuário.</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= url('log'); ?>"><i class="bi bi-arrow-left me-1"></i> Voltar aos logs</a>
</div>

<form action="<?= url('log/user-timeline/' . (int) $userId); ?>" class="index-search-card audit-user-timeline-filter mb-4" method="get">
  <div class="row g-3 align-items-end">
    <div class="col-12 col-md-6 col-xl-2">
      <label class="form-label" for="action">Ação</label>
      <select class="form-select" id="action" name="action">
        <option value="">Todas</option>
        <?php foreach ($actions as $actionValue => $actionLabel): ?>
          <option value="<?= htmlspecialchars((string) $actionValue, ENT_QUOTES, 'UTF-8'); ?>" <?= (($filters['action'] ?? '') === $actionValue) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $actionLabel, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
      <label class="form-label" for="module">Módulo</label>
      <select class="form-select" id="module" name="module">
        <option value="">Todos</option>
        <?php foreach ($modules as $module): ?>
          <option value="<?= htmlspecialchars((string) $module, ENT_QUOTES, 'UTF-8'); ?>" <?= (($filters['module'] ?? '') === $module) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $module, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
      <label class="form-label" for="tenant">Tenant</label>
      <input class="form-control" id="tenant" name="tenant" type="text" placeholder="Nome ou banco" value="<?= htmlspecialchars((string) ($filters['tenant'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="col-12 col-md-6 col-xl-2">
      <label class="form-label" for="date_from">Data inicial</label>
      <input class="form-control" id="date_from" name="date_from" type="date" value="<?= htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="col-12 col-md-6 col-xl-2">
      <label class="form-label" for="date_to">Data final</label>
      <input class="form-control" id="date_to" name="date_to" type="date" value="<?= htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="col-12">
      <div class="d-flex flex-wrap gap-2 justify-content-end">
        <button class="btn btn-primary" type="submit">Filtrar</button>
        <a class="btn btn-secondary" href="<?= url('log/user-timeline/' . (int) $userId); ?>">Limpar</a>
      </div>
    </div>
  </div>
</form>

<div class="audit-user-timeline">
  <?php if (!empty($logs)): ?>
    <?php foreach ($logs as $row): ?>
      <?php
      $tenantLabel = trim((string) ($row['tenant_name_snapshot'] ?? ''));
      if ($tenantLabel === '') {
        $tenantLabel = trim((string) ($row['tenant_database'] ?? 'Sem tenant'));
      }
      ?>
      <a class="audit-user-timeline-item" href="<?= url('log/informations/' . (int) $row['id']); ?>">
        <span class="audit-user-timeline-marker <?= htmlspecialchars((string) ($row['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></span>
        <span class="audit-user-timeline-card">
          <span class="audit-user-timeline-card-top">
            <span class="badge <?= $actionBadgeClass((string) ($row['action'] ?? '')); ?>"><?= htmlspecialchars((string) ($actions[$row['action']] ?? $row['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            <strong><?= htmlspecialchars((string) ($row['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
            <small class="text-muted"><?= htmlspecialchars(maskDateTimeBr((string) ($row['occurred_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></small>
          </span>
          <span class="audit-user-timeline-card-body">
            <span><?= htmlspecialchars($tenantLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <span><?= htmlspecialchars((string) ($row['entity_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> #<?= htmlspecialchars((string) ($row['entity_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
            <code><?= htmlspecialchars((string) ($row['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
          </span>
        </span>
      </a>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="text-center text-muted py-4">Nenhum evento encontrado para os filtros informados.</div>
  <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('log/user-timeline/' . (int) $userId, (int) $page, (int) $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
<?php endif; ?>

<script src="<?= url('assets/js/logs/user_timeline.js?v=1'); ?>"></script>
