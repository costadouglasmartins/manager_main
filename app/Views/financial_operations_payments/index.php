<?php
$filters = $filters ?? ['id' => '', 'financial_operation_id' => '', 'type' => '', 'payment_date' => '', 'due_date' => ''];
$types = $types ?? [];
$operationTypes = $operationTypes ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($financial_operations_payments) ? count($financial_operations_payments) : 0);

$queryFilters = http_build_query([
  'id'                     => $filters['id'],
  'financial_operation_id' => $filters['financial_operation_id'],
  'type'                   => $filters['type'],
  'payment_date'           => $filters['payment_date'],
  'due_date'               => $filters['due_date'],
]);
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pagamentos financeiros</li>
  </ol>
</nav>

<h5>Lista de Pagamentos Financeiros</h5>

<form action="<?= url('financial_operation_payment'); ?>" class="row g-3 mb-3" method="get">

  <div class="col-12 col-lg-9">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="financial_operation_id" name="financial_operation_id" type="text" placeholder="Operação" value="<?= htmlspecialchars($filters['financial_operation_id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-2">
        <select class="form-select" id="type" name="type">
          <option value="">Tipo</option>
          <?php foreach ($types as $value => $label): ?>
            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6 col-lg-2">
        <input class="form-control" id="payment_date" name="payment_date" type="date" value="<?= htmlspecialchars($filters['payment_date'], ENT_QUOTES, 'UTF-8') ?>" title="Data de pagamento">
      </div>

      <div class="col-12 col-md-6 col-lg-2">
        <input class="form-control" id="due_date" name="due_date" type="date" value="<?= htmlspecialchars($filters['due_date'], ENT_QUOTES, 'UTF-8') ?>" title="Data de vencimento">
      </div>

    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('financial_operation_payment'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?php if (authCan('financial_operation_payment.create')): ?>
        <a href="<?= url('financial_operation_payment/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
      <?php endif; ?>
    </div>
  </div>

</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <th>#</th>
        <th>Operação</th>
        <th>Tipo</th>
        <th>Valor</th>
        <th>Pagamento</th>
        <th>Vencimento</th>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
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
                <a href="<?= url('financial_operation_payment/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
              <?php if (authCan('financial_operation_payment.delete')): ?>
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= (int) $row['id'] ?>" data-name="Pagamento #<?= (int) $row['id'] ?>">
                  <i class="bi bi-trash"></i>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="text-center">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-muted"><?= (int) $total ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('financial_operation_payment', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima') ?>
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
        <form method="POST" action="#" id="deleteForm" data-delete-base-url="<?= url('financial_operation_payment/delete'); ?>" style="display: inline;">
          <input type="hidden" name="id" id="recordId">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/financial_operations_payments/index.js?v=1'); ?>"></script>
