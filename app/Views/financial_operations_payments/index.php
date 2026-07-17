<?php
$filters = $filters ?? ['id' => '', 'financial_operation_id' => '', 'financial_operation_type' => '', 'type' => '', 'payment_date' => '', 'due_date_start' => '', 'due_date_end' => '', 'due_date' => ''];
$types = $types ?? [];
$operationTypes = $operationTypes ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($financial_operations_payments) ? count($financial_operations_payments) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];

$queryFilters = http_build_query([
  'id'                       => $filters['id'],
  'financial_operation_id'   => $filters['financial_operation_id'],
  'financial_operation_type' => $filters['financial_operation_type'],
  'type'                     => $filters['type'],
  'payment_date'             => $filters['payment_date'],
  'due_date_start'           => $filters['due_date_start'],
  'due_date_end'             => $filters['due_date_end'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
$activeFilterCount = count(array_filter($filters, static fn ($value): bool => (string) $value !== ''));
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pagamentos financeiros</li>
  </ol>
</nav>

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true"><i class="bi bi-credit-card fs-4"></i></span>
    <div><p class="small fw-semibold text-primary text-uppercase mb-1">Financeiro</p><h1 class="h3 fw-bold mb-1">Pagamentos financeiros</h1><p class="text-body-secondary mb-0">Consulte e acompanhe os pagamentos das operações.</p></div>
  </div>
  <?php if (authCan('financial_operation_payment.create')): ?><a href="<?= url('financial_operation_payment/create'); ?>" class="btn btn-success d-inline-flex align-items-center justify-content-center gap-2"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Novo pagamento</span></a><?php endif; ?>
</header>

<form action="<?= url('financial_operation_payment'); ?>" class="card border-0 shadow-sm mb-4 p-3 p-lg-4 app-index-filters" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="app-index-filter-heading d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div><h2 class="h5 fw-bold mb-1">Filtros de pesquisa</h2><p class="small text-body-secondary mb-0">Refine a lista de pagamentos financeiros.</p></div>
    <?php if ($activeFilterCount > 0): ?><span class="badge rounded-pill text-bg-primary"><?= $activeFilterCount; ?> filtro(s) ativo(s)</span><?php endif; ?>
  </div>

  <div class="row g-3 align-items-end">

  <div class="col-12">
    <div class="row g-3">

      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold" for="id">Código</label>
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold" for="financial_operation_id">Operação</label>
        <input class="form-control" id="financial_operation_id" name="financial_operation_id" type="text" placeholder="Operação" value="<?= htmlspecialchars($filters['financial_operation_id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold" for="financial_operation_type">Tipo da operação</label>
        <select class="form-select" id="financial_operation_type" name="financial_operation_type">
          <option value="">Tipo operação</option>
          <?php foreach ($operationTypes as $value => $label): ?>
            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $filters['financial_operation_type'] === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold" for="type">Tipo do pagamento</label>
        <select class="form-select" id="type" name="type">
          <option value="">Tipo pagamento</option>
          <?php foreach ($types as $value => $label): ?>
            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold" for="payment_date">Data de pagamento</label>
        <input class="form-control" id="payment_date" name="payment_date" type="date" value="<?= htmlspecialchars($filters['payment_date'], ENT_QUOTES, 'UTF-8') ?>" title="Data de pagamento">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold" for="due_date_start">Período de vencimento</label>
        <div class="input-group">
          <input autocomplete="off" class="form-control mask-date" id="due_date_start" name="due_date_start" type="text" inputmode="numeric" maxlength="10" placeholder="DD/MM/AAAA" value="<?= htmlspecialchars(maskDateBr($filters['due_date_start']) ?? '', ENT_QUOTES, 'UTF-8') ?>" title="Vencimento inicial" aria-label="Vencimento inicial">
          <span class="input-group-text">até</span>
          <input autocomplete="off" class="form-control mask-date" id="due_date_end" name="due_date_end" type="text" inputmode="numeric" maxlength="10" placeholder="DD/MM/AAAA" value="<?= htmlspecialchars(maskDateBr($filters['due_date_end']) ?? '', ENT_QUOTES, 'UTF-8') ?>" title="Vencimento final" aria-label="Vencimento final">
        </div>
      </div>

    </div>
  </div>

  <div class="col-12">
    <div class="index-search-actions">
      <button type="submit" class="btn btn-primary btn-sm index-search-action" aria-label="Pesquisar" title="Pesquisar"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Pesquisar</span></button>
      <a href="<?= url('financial_operation_payment'); ?>" class="btn btn-outline-secondary btn-sm index-search-action" aria-label="Limpar filtros" title="Limpar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span></a>
      <?= pdfExportButton('financial_operation_payment', $paginationQuery); ?>
      <?= excelExportButton('financial_operation_payment', $paginationQuery); ?>
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
        <?= sortableTh('financial_operation_payment', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation_payment', 'financial_operation_id', 'Operação', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation_payment', 'type', 'Tipo', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation_payment', 'value', 'Valor', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation_payment', 'payment_date', 'Pagamento', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation_payment', 'due_date', 'Vencimento', $sort, $queryFilters) ?>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody class="table-group-divider">
      <?php if (!empty($financial_operations_payments)): ?>
        <?php foreach ($financial_operations_payments as $row): ?>
          <?php
          $operationType = $operationTypes[$row['financial_operation_type']] ?? $row['financial_operation_type'];
          $operationLabel = '#' . (int) $row['financial_operation_id'] . ' - ' . $operationType;
          if (!empty($row['financial_operation_category_name']))
            {
            $operationLabel .= ' - ' . $row['financial_operation_category_name'];
            }
          $paymentDate = !empty($row['payment_date']) ? date('d/m/Y H:i', strtotime($row['payment_date'])) : '';
          $dueDate = !empty($row['due_date']) ? date('d/m/Y', strtotime($row['due_date'])) : '';
          ?>
          <tr>
            <td style="width:1%;"><?= (int) $row['id'] ?></td>
            <td><?= htmlspecialchars($operationLabel, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($types[$row['type']] ?? $row['type'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>R$ <?= number_format((float) $row['value'], 2, ',', '.') ?></td>
            <td><?= htmlspecialchars($paymentDate, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('financial_operation_payment.edit')): ?>
                <a href="<?= url('financial_operation_payment/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('financial_operation_payment.informations')): ?>
                <a href="<?= url('financial_operation_payment/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Ver detalhes"><i class="bi bi-eye"></i></a>
              <?php endif; ?>
              <?php if (authCan('financial_operation_payment.delete')): ?>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= (int) $row['id'] ?>" data-name="Pagamento #<?= (int) $row['id'] ?>">
                  <i class="bi bi-trash"></i>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="app-index-results-empty">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>


<?php if ($totalPages > 1): ?>
  <div class="app-index-pagination">
  <?= paginationLinks('financial_operation_payment', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
  </div>
<?php endif; ?>

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
        <form method="POST" action="#" id="deleteForm" data-delete-base-url="<?= url('financial_operation_payment/delete'); ?>" class="d-inline">
          <input type="hidden" name="id" id="recordId">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="/assets/masks/masks.js?v=5"></script>
<script src="<?= url('assets/js/financial_operations_payments/index.js?v=1'); ?>"></script>
