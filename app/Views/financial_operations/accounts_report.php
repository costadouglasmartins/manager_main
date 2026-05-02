<?php
$filters = $filters ?? ['due_date_start' => '', 'due_date_end' => '', 'financial_operation_category_id' => ''];
$categories = $categories ?? [];
$paymentTypes = $paymentTypes ?? [];
$accounts = $accounts ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($accounts) ? count($accounts) : 0);
$accountsReportTitle = $accountsReportTitle ?? 'Relatório financeiro';
$accountsReportRoute = $accountsReportRoute ?? 'financial_operation';
$accountsReportValueLabel = $accountsReportValueLabel ?? 'Valor';
$accountsReportPaidLabel = $accountsReportPaidLabel ?? 'Pago';
$accountsReportOpenLabel = $accountsReportOpenLabel ?? 'Em aberto';

$queryFilters = http_build_query([
  'due_date_start' => $filters['due_date_start'],
  'due_date_end' => $filters['due_date_end'],
  'financial_operation_category_id' => $filters['financial_operation_category_id'],
]);
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation'); ?>">Financeiro</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($accountsReportTitle, ENT_QUOTES, 'UTF-8'); ?></li>
  </ol>
</nav>

<h5><?= htmlspecialchars($accountsReportTitle, ENT_QUOTES, 'UTF-8'); ?></h5>

<form action="<?= url($accountsReportRoute); ?>" class="row g-3 mb-3" method="get">

  <div class="col-12 col-lg-9">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-3">
        <label class="form-label" for="due_date_start">Vencimento inicial</label>
        <input class="form-control" id="due_date_start" name="due_date_start" type="date" value="<?= htmlspecialchars($filters['due_date_start'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <label class="form-label" for="due_date_end">Vencimento final</label>
        <input class="form-control" id="due_date_end" name="due_date_end" type="date" value="<?= htmlspecialchars($filters['due_date_end'], ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <div class="col-12 col-lg-6">
        <label class="form-label" for="financial_operation_category_id">Categoria</label>
        <select class="form-select" id="financial_operation_category_id" name="financial_operation_category_id">
          <option value="">Todas as categorias</option>
          <?php foreach ($categories as $category): ?>
            <?php
            $categoryOptionLabel = trim((string) ($category['name'] ?? ''));
            if (!empty($category['description']))
              {
              $categoryOptionLabel .= ' - ' . trim((string) $category['description']);
              }
            ?>
            <option value="<?= (int) $category['id']; ?>" <?= (string) $filters['financial_operation_category_id'] === (string) $category['id'] ? 'selected' : ''; ?>>
              <?= htmlspecialchars($categoryOptionLabel, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end mt-lg-4">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url($accountsReportRoute); ?>" class="btn btn-secondary w-100">Limpar</a>
    </div>
  </div>

</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover align-middle">
    <thead>
      <tr>
        <th>#</th>
        <th>Operação</th>
        <th>Categoria</th>
        <th>Forma</th>
        <th><?= htmlspecialchars($accountsReportValueLabel, ENT_QUOTES, 'UTF-8'); ?></th>
        <th>Pagamento</th>
        <th>Vencimento</th>
        <th>Usuário</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($accounts)): ?>
        <?php foreach ($accounts as $row): ?>
          <?php
          $userLabel = trim(($row['user_name'] ?? '') . ' ' . (!empty($row['username']) ? '(' . $row['username'] . ')' : ''));
          $categoryLabel = trim((string) ($row['category_label'] ?? ''));
          if ($categoryLabel === '')
            {
            $categoryLabel = trim((string) ($row['category_name'] ?? ''));
            if (!empty($row['category_description']))
              {
              $categoryLabel .= ' - ' . trim((string) $row['category_description']);
              }
            }
          $paymentDate = !empty($row['payment_date']) ? date('d/m/Y H:i', strtotime($row['payment_date'])) : '';
          $dueDate = !empty($row['due_date']) ? date('d/m/Y', strtotime($row['due_date'])) : '';
          $statusLabel = $paymentDate !== '' ? $accountsReportPaidLabel : $accountsReportOpenLabel;
          ?>
          <tr>
            <td style="width:1%;"><?= (int) $row['id']; ?></td>
            <td class="text-nowrap">
              <?php if (authCan('financial_operation.informations')): ?>
                <a href="<?= url('financial_operation/informations/' . (int) $row['financial_operation_id']); ?>">#<?= (int) $row['financial_operation_id']; ?></a>
              <?php else: ?>
                #<?= (int) $row['financial_operation_id']; ?>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars($paymentTypes[$row['type']] ?? $row['type'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td>R$ <?= number_format((float) $row['value'], 2, ',', '.'); ?></td>
            <td><?= htmlspecialchars($paymentDate, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="9" class="text-center">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks($accountsReportRoute, $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima'); ?>
<?php endif; ?>
