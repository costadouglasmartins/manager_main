<?php
$log = $log ?? [];
$userTimeline = $userTimeline ?? [];
$actions = $actions ?? [];

$decodeJson = static function ($value) {
  if ($value === null || $value === '') {
    return null;
  }

  $decoded = json_decode((string) $value, true);
  return json_last_error() === JSON_ERROR_NONE ? $decoded : (string) $value;
};

$prettyJson = static function ($value): string {
  if ($value === null) {
    return 'Nenhum dado disponível.';
  }

  if (is_string($value)) {
    return $value;
  }

  $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  return $json !== false ? $json : 'Não foi possível formatar o conteúdo.';
};

$beforeData = $decodeJson($log['before_data'] ?? null);
$afterData = $decodeJson($log['after_data'] ?? null);
$changedFields = $decodeJson($log['changed_fields'] ?? null);
$contextData = $decodeJson($log['context_json'] ?? null);

$tenantLabel = trim((string) ($log['tenant_name_snapshot'] ?? ''));
if ($tenantLabel === '') {
  $tenantLabel = trim((string) ($log['tenant_database'] ?? 'Sem tenant'));
}

$userLabel = trim((string) ($log['performed_by_name'] ?? ''));
if ($userLabel === '') {
  $userLabel = trim((string) ($log['performed_by_username'] ?? 'Sistema'));
}

$actionBadgeClass = static function (string $action): string {
  return match ($action) {
    'create' => 'text-bg-success',
    'update' => 'text-bg-warning',
    'delete' => 'text-bg-danger',
    'login' => 'text-bg-info',
    'logout' => 'text-bg-dark',
    default => 'text-bg-secondary',
  };
};
?>

<link rel="stylesheet" href="<?= url('assets/css/logs/informations.css?v=4'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('log'); ?>">Logs do sistema</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detalhes do log</li>
  </ol>
</nav>

<div class="audit-log-detail-shell">
  <div class="alert alert-success d-none mb-4" id="auditLogDeleteSuccess" role="alert"></div>

  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3 p-lg-4">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <span class="audit-log-kicker">Evento #<?= (int) ($log['id'] ?? 0); ?></span>
          <h5 class="mb-1"><?= htmlspecialchars((string) ($log['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> / <?= htmlspecialchars((string) ($log['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
          <p class="text-muted mb-0">Ocorrido em <?= htmlspecialchars(maskDateTimeBr((string) ($log['occurred_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <?php if (!empty($log['performed_by_user_id']) && authCan('log.userTimeline')): ?>
            <a class="btn btn-outline-primary" href="<?= url('log/user-timeline/' . (int) $log['performed_by_user_id']); ?>"><i class="bi bi-clock-history me-1"></i> Linha do tempo</a>
          <?php endif; ?>
          <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#logDeleteModal" data-log-id="<?= (int) ($log['id'] ?? 0); ?>" data-log-label="Log #<?= (int) ($log['id'] ?? 0); ?> - <?= htmlspecialchars((string) ($log['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>/<?= htmlspecialchars((string) ($actions[$log['action']] ?? $log['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-delete-url="<?= url('log/delete/' . (int) ($log['id'] ?? 0)); ?>">
            <i class="bi bi-trash me-1"></i> Remover
          </button>
          <a class="btn btn-outline-secondary" href="<?= url('log'); ?>"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <h6 class="mb-3">Resumo</h6>
          <dl class="row mb-0 audit-log-summary">
            <dt class="col-5">Tenant</dt>
            <dd class="col-7"><?= htmlspecialchars($tenantLabel, ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-5">Banco</dt>
            <dd class="col-7"><?= htmlspecialchars((string) ($log['tenant_database'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-5">Entidade</dt>
            <dd class="col-7"><?= htmlspecialchars((string) ($log['entity_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-5">Registro</dt>
            <dd class="col-7"><?= htmlspecialchars((string) ($log['entity_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-5">Usuário</dt>
            <dd class="col-7"><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-5">Método</dt>
            <dd class="col-7"><?= htmlspecialchars((string) ($log['request_method'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-5">Rota</dt>
            <dd class="col-7"><code><?= htmlspecialchars((string) ($log['route'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></code></dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <h6 class="mb-3">Rastreamento técnico</h6>
          <dl class="row mb-0 audit-log-summary">
            <dt class="col-4">UUID</dt>
            <dd class="col-8"><code><?= htmlspecialchars((string) ($log['event_uuid'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></code></dd>
            <dt class="col-4">IP</dt>
            <dd class="col-8"><?= htmlspecialchars((string) ($log['ip_address'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-4">User agent</dt>
            <dd class="col-8"><?= htmlspecialchars((string) ($log['user_agent'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
          </dl>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($userTimeline)): ?>
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white border-0 pb-0">
        <h6 class="mb-0">Linha do tempo recente de <?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8'); ?></h6>
      </div>
      <div class="card-body">
        <div class="audit-log-mini-timeline">
          <?php foreach ($userTimeline as $timelineRow): ?>
            <?php
            $timelineTenant = trim((string) ($timelineRow['tenant_name_snapshot'] ?? ''));
            if ($timelineTenant === '') {
              $timelineTenant = trim((string) ($timelineRow['tenant_database'] ?? 'Sem tenant'));
            }
            ?>
            <a class="audit-log-mini-timeline-item" href="<?= url('log/informations/' . (int) $timelineRow['id']); ?>">
              <span class="audit-log-mini-timeline-marker <?= htmlspecialchars((string) ($timelineRow['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></span>
              <span class="audit-log-mini-timeline-content">
                <span class="d-flex flex-wrap align-items-center gap-2 mb-1">
                  <span class="badge <?= $actionBadgeClass((string) ($timelineRow['action'] ?? '')); ?>"><?= htmlspecialchars((string) ($actions[$timelineRow['action']] ?? $timelineRow['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                  <strong><?= htmlspecialchars((string) ($timelineRow['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                  <small class="text-muted"><?= htmlspecialchars(maskDateTimeBr((string) ($timelineRow['occurred_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></small>
                </span>
                <span class="text-muted"><?= htmlspecialchars($timelineTenant, ENT_QUOTES, 'UTF-8'); ?> · <?= htmlspecialchars((string) ($timelineRow['entity_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> #<?= htmlspecialchars((string) ($timelineRow['entity_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-xl-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-0 pb-0">
          <h6 class="mb-0">Antes da alteração</h6>
        </div>
        <div class="card-body">
          <pre class="audit-log-json"><?= htmlspecialchars($prettyJson($beforeData), ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-0 pb-0">
          <h6 class="mb-0">Depois da alteração</h6>
        </div>
        <div class="card-body">
          <pre class="audit-log-json"><?= htmlspecialchars($prettyJson($afterData), ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-0 pb-0">
          <h6 class="mb-0">Campos alterados</h6>
        </div>
        <div class="card-body">
          <pre class="audit-log-json"><?= htmlspecialchars($prettyJson($changedFields), ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-0 pb-0">
          <h6 class="mb-0">Contexto adicional</h6>
        </div>
        <div class="card-body">
          <pre class="audit-log-json"><?= htmlspecialchars($prettyJson($contextData), ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="logDeleteModal" tabindex="-1" aria-labelledby="logDeleteModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content audit-log-delete-modal">
      <form id="logDeleteForm" data-redirect-url="<?= url('log'); ?>">
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

<script src="<?= url('assets/js/logs/informations.js?v=3'); ?>"></script>
