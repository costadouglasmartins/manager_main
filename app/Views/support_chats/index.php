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
$activeFilterCount = count(array_filter($filters, static fn ($value): bool => (string) $value !== ''));
$currentListQuery = http_build_query([
  'date_from' => $filters['date_from'] ?? '',
  'date_to' => $filters['date_to'] ?? '',
  'status' => $filters['status'] ?? '',
  'participant' => $filters['participant'] ?? '',
  'page' => (int) $page,
]);
$currentListPath = 'support_chat' . ($currentListQuery !== '' ? '?' . $currentListQuery : '');
?>

<link rel="stylesheet" href="<?= url('assets/css/support_chats/index.css?v=3'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Chats de suporte</li>
  </ol>
</nav>

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true"><i class="bi bi-chat-dots fs-4"></i></span>
    <div><p class="small fw-semibold text-primary text-uppercase mb-1">Atendimento</p><h1 class="h3 fw-bold mb-1">Chats de suporte</h1><p class="text-body-secondary mb-0">Acompanhe conversas, participantes e situação dos atendimentos.</p></div>
  </div>
</header>

<form action="<?= url('support_chat'); ?>" class="card border-0 shadow-sm mb-4 p-3 p-lg-4 app-index-filters support-chat-filter" method="get">
  <div class="app-index-filter-heading d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div><h2 class="h5 fw-bold mb-1">Filtros de pesquisa</h2><p class="small text-body-secondary mb-0">Refine a lista de atendimentos.</p></div>
    <?php if ($activeFilterCount > 0): ?><span class="badge rounded-pill text-bg-primary"><?= $activeFilterCount; ?> filtro(s) ativo(s)</span><?php endif; ?>
  </div>
  <div class="row g-3 align-items-end">
    <div class="col-12">
      <div class="row g-3">
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

        <div class="col-12 col-xl-6">
          <label class="form-label" for="participant">Participante</label>
          <input class="form-control" id="participant" name="participant" type="text" placeholder="Cliente, atendente ou tenant" value="<?= htmlspecialchars((string) ($filters['participant'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="index-search-actions">
        <button class="btn btn-primary btn-sm index-search-action" type="submit" aria-label="Filtrar" title="Filtrar"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Filtrar</span></button>
        <a class="btn btn-outline-secondary btn-sm index-search-action" href="<?= url('support_chat'); ?>" aria-label="Limpar filtros" title="Limpar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span></a>
        <?= pdfExportButton('support_chat', $queryFilters); ?>
        <?= excelExportButton('support_chat', $queryFilters); ?>
        <a class="btn btn-success btn-sm index-search-action" href="<?= url('support_chat/attend'); ?>" aria-label="Abrir atendimento" title="Abrir atendimento"><i class="bi bi-headset" aria-hidden="true"></i><span class="visually-hidden">Abrir atendimento</span></a>
      </div>
    </div>
  </div>
</form>

<div class="app-index-results-header support-chat-toolbar">
  <div>
    <h2 class="h5 fw-bold mb-1">Resultados</h2>
    <p class="small text-body-secondary mb-0"><?= $activeFilterCount > 0 ? 'Atendimentos encontrados para os filtros informados.' : 'Todos os atendimentos registrados.'; ?></p>
  </div>
  <span class="badge rounded-pill text-bg-primary"><?= (int) $total; ?> registro(s)</span>
</div>

<div class="table-responsive app-index-results-table support-chat-table-wrap">
  <table class="table table-hover align-middle mb-0 support-chat-table">
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
    <tbody class="table-group-divider">
      <?php if (!empty($chats)): ?>
        <?php foreach ($chats as $chat): ?>
          <?php
          $status = (string) ($chat['status'] ?? '');
          $statusLabel = ['pending' => 'Pendente', 'open' => 'Em aberto', 'closed' => 'Finalizado'][$status] ?? $status;
          $tenantName = trim((string) ($chat['tenant_name'] ?? ''));
          if ($tenantName === '') {
            $tenantName = 'Tenant ' . (string) ($chat['tenant_database_number'] ?? '');
          }
          $chatLabel = 'Chat #' . (int) $chat['id'] . ' - ' . (string) ($chat['customer_name'] ?? $chat['customer_username'] ?? '');
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
              <div class="support-chat-actions">
                <a class="btn btn-sm btn-outline-secondary support-chat-action-button" href="<?= url('support_chat/informations/' . (int) $chat['id']); ?>" aria-label="Visualizar registros" title="Visualizar registros" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Visualizar registros">
                  <i class="bi bi-search"></i>
                </a>
                <?php if ($status !== 'closed'): ?>
                  <a class="btn btn-sm btn-outline-primary support-chat-action-button" href="<?= url('support_chat/attend/' . (int) $chat['id']); ?>" aria-label="Atender" title="Atender" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Atender">
                    <i class="bi bi-chat-dots"></i>
                  </a>
                <?php else: ?>
                  <button class="btn btn-sm btn-outline-success support-chat-action-button" type="button" aria-label="Reabrir" title="Reabrir" data-bs-toggle="modal" data-bs-target="#reopenModal" data-id="<?= (int) $chat['id']; ?>" data-name="<?= htmlspecialchars($chatLabel, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-danger support-chat-action-button" type="button" aria-label="Excluir" title="Excluir" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= (int) $chat['id']; ?>" data-name="<?= htmlspecialchars($chatLabel, ENT_QUOTES, 'UTF-8'); ?>">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="8" class="app-index-results-empty">Nenhum chat encontrado para os filtros informados.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
  <div class="app-index-pagination">
  <?= paginationLinks('support_chat', (int) $page, (int) $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
  </div>
<?php endif; ?>

<div class="modal fade" id="reopenModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar reabertura</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Tem certeza que deseja reabrir o <strong id="reopenRecordName"></strong>?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="#" id="reopenForm" data-action-base-url="<?= url('support_chat/reopen'); ?>" class="d-inline">
          <input type="hidden" name="id" id="reopenRecordId">
          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($currentListPath, ENT_QUOTES, 'UTF-8'); ?>">
          <button type="submit" class="btn btn-success">Reabrir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar exclusão</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Tem certeza que deseja excluir o <strong id="deleteRecordName"></strong>?
        <p class="text-muted mb-0 mt-2">Os registros da conversa também serão removidos.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="#" id="deleteForm" data-action-base-url="<?= url('support_chat/delete'); ?>" class="d-inline">
          <input type="hidden" name="id" id="deleteRecordId">
          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($currentListPath, ENT_QUOTES, 'UTF-8'); ?>">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/support_chats/index.js?v=2'); ?>"></script>
