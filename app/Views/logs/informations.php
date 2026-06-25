<?php
$log = $log ?? [];

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
?>

<link rel="stylesheet" href="<?= url('assets/css/logs/informations.css?v=1'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('log'); ?>">Logs do sistema</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detalhes do log</li>
  </ol>
</nav>

<div class="audit-log-detail-shell">
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3 p-lg-4">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <span class="audit-log-kicker">Evento #<?= (int) ($log['id'] ?? 0); ?></span>
          <h5 class="mb-1"><?= htmlspecialchars((string) ($log['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> / <?= htmlspecialchars((string) ($log['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
          <p class="text-muted mb-0">Ocorrido em <?= htmlspecialchars(maskDateTimeBr((string) ($log['occurred_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('log'); ?>"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
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

<script src="<?= url('assets/js/logs/informations.js?v=1'); ?>"></script>
