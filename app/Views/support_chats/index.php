<?php
$filters = $filters ?? ['date_from' => '', 'date_to' => '', 'status' => '', 'participant' => ''];
$chats = $chats ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? count($chats);

$queryFilters = http_build_query([
  'date_from' => $filters['date_from'] ?? '',
  'date_to' => $filters['date_to'] ?? '',
  'status' => $filters['status'] ?? '',
  'participant' => $filters['participant'] ?? '',
]);
?>

<link rel="stylesheet" href="<?= url('assets/css/support_chats/index.css?v=1'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Chats de suporte</li>
  </ol>
</nav>

<form action="<?= url('support_chat'); ?>" class="index-search-card support-chat-filter mb-4" method="get">
  <div class="row g-3 align-items-end">
    <div class="col-12 col-md-6 col-xl-2">
      <label class="form-label" for="date_from">Data inicial</label>
      <input class="form-control" id="date_from" name="date_from" type="date" value="<?= htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="col-12 col-md-6 col-xl-2">
      <label class="form-label" for="date_to">Data final</label>
      <input class="form-control" id="date_to" name="date_to" type="date" value="<?= htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="col-12 col-md-6 col-xl-2">
      <label class="form-label" for="status">Situação</label>
      <select class="form-select" id="status" name="status">
        <option value="">Todas</option>
        <option value="pending" <?= (($filters['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>Pendente</option>
        <option value="open" <?= (($filters['status'] ?? '') === 'open') ? 'selected' : ''; ?>>Em aberto</option>
        <option value="closed" <?= (($filters['status'] ?? '') === 'closed') ? 'selected' : ''; ?>>Finalizado</option>
      </select>
    </div>

    <div class="col-12 col-xl-4">
      <label class="form-label" for="participant">Participante</label>
      <input class="form-control" id="participant" name="participant" type="text" placeholder="Cliente, atendente ou tenant" value="<?= htmlspecialchars((string) ($filters['participant'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="col-12 col-xl-2">
      <div class="d-flex gap-2 justify-content-xl-end">
        <button class="btn btn-primary flex-fill" type="submit">Filtrar</button>
        <a class="btn btn-outline-secondary flex-fill" href="<?= url('support_chat'); ?>">Limpar</a>
      </div>
    </div>
  </div>
</form>

<div class="support-chat-toolbar mb-3">
  <p class="text-muted mb-0"><?= (int) $total; ?> chat(s) encontrado(s)</p>
  <div class="d-flex flex-wrap gap-2 justify-content-end">
    <?= pdfExportButton('support_chat', $queryFilters, 'Exportar PDF', 'btn btn-outline-danger'); ?>
    <?= excelExportButton('support_chat', $queryFilters, 'Exportar Excel', 'btn btn-outline-success'); ?>
    <a class="btn btn-success" href="<?= url('support_chat/attend'); ?>">
      <i class="bi bi-headset me-1"></i> Abrir atendimento
    </a>
  </div>
</div>

<div class="table-responsive support-chat-table-wrap">
  <table class="table table-striped table-hover align-middle support-chat-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Data</th>
        <th>Situação</th>
        <th>Cliente</th>
        <th>Tenant</th>
        <th>Atendente</th>
        <th>Última mensagem</th>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($chats)): ?>
        <?php foreach ($chats as $chat): ?>
          <?php
          $status = (string) ($chat['status'] ?? '');
          $statusLabel = ['pending' => 'Pendente', 'open' => 'Em aberto', 'closed' => 'Finalizado'][$status] ?? $status;
          $tenantName = trim((string) ($chat['tenant_name'] ?? ''));
          if ($tenantName === '') {
            $tenantName = 'Tenant ' . (string) ($chat['tenant_database_number'] ?? '');
          }
          ?>
          <tr>
            <td><?= (int) $chat['id']; ?></td>
            <td><?= htmlspecialchars(maskDateTimeBr((string) ($chat['created'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><span class="support-chat-status status-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
            <td>
              <strong><?= htmlspecialchars((string) ($chat['customer_name'] ?? $chat['customer_username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
              <small class="d-block text-muted"><?= htmlspecialchars((string) ($chat['customer_username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
            </td>
            <td><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars((string) ($chat['attendant_name'] ?? $chat['attendant_username'] ?? 'Sem atendente'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars(maskDateTimeBr((string) ($chat['last_message_at'] ?? $chat['created'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= url('support_chat/attend/' . (int) $chat['id']); ?>">
                <i class="bi bi-chat-dots me-1"></i> Atender
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="8" class="text-center text-muted py-4">Nenhum chat encontrado para os filtros informados.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('support_chat', (int) $page, (int) $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
<?php endif; ?>

<script src="<?= url('assets/js/support_chats/index.js?v=1'); ?>"></script>
