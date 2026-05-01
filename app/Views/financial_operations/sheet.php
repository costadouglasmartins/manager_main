<?php
if (!function_exists('financialSheetMoney'))
  {
  function financialSheetMoney(int $cents, bool $blankWhenZero = false): string
    {
    if ($blankWhenZero && $cents === 0)
      {
      return '';
      }

    return number_format($cents / 100, 2, ',', '.');
    }
  }

if (!function_exists('financialSheetPercent'))
  {
  function financialSheetPercent($value): string
    {
    if ($value === null)
      {
      return '-';
      }

    return number_format((float) $value, 2, ',', '.');
    }
  }

if (!function_exists('financialSheetEntryButton'))
  {
  function financialSheetEntryButton(int $cents, array $data, bool $blankWhenZero, bool $canEdit): string
    {
    $label = financialSheetMoney($cents, $blankWhenZero);
    if (!$canEdit || $cents === 0 || $label === '')
      {
      return $label;
      }

    $attributes = [
      'type' => 'button',
      'class' => 'financial-sheet-entry-button',
      'data-sheet-entry' => '1',
    ];
    foreach ($data as $name => $value)
      {
      $attributes['data-' . str_replace('_', '-', $name)] = (string) $value;
      }

    $htmlAttributes = [];
    foreach ($attributes as $name => $value)
      {
      $htmlAttributes[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
      }

    return '<button ' . implode(' ', $htmlAttributes) . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</button>';
    }
  }

$sheet = $sheet ?? [];
$year = (int) ($sheet['year'] ?? date('Y'));
$yearOptions = $yearOptions ?? range(2026, 2031);
$months = $sheet['months'] ?? [];
$incomeRows = $sheet['incomeRows'] ?? [];
$expenseRows = $sheet['expenseRows'] ?? [];
$categories = $categories ?? [];
$types = $types ?? [];
$paymentTypes = $paymentTypes ?? [];
$sessionUser = $sessionUser ?? [];
$incomeTotals = $sheet['incomeTotals'] ?? ['months' => [], 'total' => 0];
$expenseTotals = $sheet['expenseTotals'] ?? ['months' => [], 'total' => 0];
$balanceTotals = $sheet['balanceTotals'] ?? ['months' => [], 'total' => 0];
$expensePercentages = $sheet['expensePercentages'] ?? ['months' => [], 'total' => null];
$incomeTotal = (int) ($incomeTotals['total'] ?? 0);
$expenseTotal = (int) ($expenseTotals['total'] ?? 0);
$yearBalance = (int) ($balanceTotals['total'] ?? 0);
$expensePercentageLabel = financialSheetPercent($expensePercentages['total'] ?? null);
if ($expensePercentageLabel !== '-')
  {
  $expensePercentageLabel .= '%';
  }
$canEditSheetOperations = authCan('financial_operation.edit') && authCan('financial_operation.update');
$sessionUserLabel = trim((string) ($sessionUser['name'] ?? ''));
if (!empty($sessionUser['username']))
  {
  $sessionUserLabel .= ' (' . trim((string) $sessionUser['username']) . ')';
  }
if ($sessionUserLabel === '')
  {
  $sessionUserLabel = 'Usuário #' . (int) ($sessionUser['id'] ?? 0);
  }
?>

<link rel="stylesheet" href="<?= url('assets/css/financial_operations/sheet.css?v=4'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation'); ?>">Operações financeiras</a></li>
    <li class="breadcrumb-item active" aria-current="page">Planilha anual</li>
  </ol>
</nav>

<div class="financial-sheet-page"
     data-sheet-operations-url="<?= url('financial_operation/sheetOperations'); ?>"
     data-sheet-update-base-url="<?= url('financial_operation/sheetUpdateOperation'); ?>"
     data-sheet-edit-base-url="<?= url('financial_operation/edit'); ?>">
  <header class="financial-sheet-header">
    <div class="financial-sheet-title">
      <span class="financial-sheet-title-icon"><i class="bi bi-table"></i></span>
      <div>
        <span class="financial-sheet-kicker">Resumo anual</span>
        <h5>Planilha financeira <?= $year; ?></h5>
      </div>
    </div>
    <div class="financial-sheet-actions">
      <form class="financial-sheet-year-filter" action="<?= url('financial_operation/sheet'); ?>" method="get">
        <label for="financial_sheet_year">Ano</label>
        <select class="form-select form-select-sm" id="financial_sheet_year" name="year">
          <?php foreach ($yearOptions as $yearOption): ?>
            <option value="<?= (int) $yearOption; ?>" <?= (int) $yearOption === $year ? 'selected' : ''; ?>>
              <?= (int) $yearOption; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
      </form>
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('financial_operation'); ?>">
        <i class="bi bi-arrow-left me-1"></i>Voltar
      </a>
      <?php if (authCan('financial_operation.create')): ?>
        <a class="btn btn-success btn-sm" href="<?= url('financial_operation/create'); ?>">
          <i class="bi bi-plus-lg me-1"></i>Novo lançamento
        </a>
      <?php endif; ?>
    </div>
  </header>

  <div class="financial-sheet-summary">
    <div class="financial-sheet-metric financial-sheet-metric-income">
      <span>Receitas</span>
      <strong>R$ <?= financialSheetMoney($incomeTotal); ?></strong>
    </div>
    <div class="financial-sheet-metric financial-sheet-metric-expense">
      <span>Despesas</span>
      <strong>R$ <?= financialSheetMoney($expenseTotal); ?></strong>
    </div>
    <div class="financial-sheet-metric <?= $yearBalance < 0 ? 'financial-sheet-metric-negative' : 'financial-sheet-metric-balance'; ?>">
      <span>Saldo</span>
      <strong>R$ <?= financialSheetMoney($yearBalance); ?></strong>
    </div>
    <div class="financial-sheet-metric financial-sheet-metric-percent">
      <span>Percentual despesas</span>
      <strong><?= htmlspecialchars($expensePercentageLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
    </div>
  </div>

  <div class="financial-sheet-board">
    <div class="financial-sheet-board-header">
      <h6>Detalhamento mensal</h6>
      <span><?= count($months); ?> meses</span>
    </div>

    <div class="financial-sheet-scroll overflow-x-hidden">
      <table class="financial-sheet-table table table-sm align-middle mb-0 w-100 small">
        <colgroup>
          <col class="financial-sheet-label-col">
          <?php foreach ($months as $_monthNumber => $_monthLabel): ?>
            <col class="financial-sheet-month-col">
          <?php endforeach; ?>
          <col class="financial-sheet-total-col">
        </colgroup>
        <thead>
          <tr>
            <th><span class="d-block text-truncate" title="Categoria">Categoria</span></th>
            <?php foreach ($months as $monthLabel): ?>
              <?php $monthHeader = function_exists('mb_strtoupper') ? mb_strtoupper($monthLabel, 'UTF-8') : strtoupper($monthLabel); ?>
              <?php $monthTitle = htmlspecialchars($monthHeader, ENT_QUOTES, 'UTF-8'); ?>
              <th><span class="d-block text-truncate" title="<?= $monthTitle; ?>"><?= $monthTitle; ?></span></th>
            <?php endforeach; ?>
            <th><span class="d-block text-truncate" title="Total">Total</span></th>
          </tr>
        </thead>
        <tbody>
          <tr class="financial-sheet-section-row financial-sheet-section-income">
            <th colspan="<?= count($months) + 2; ?>"><i class="bi bi-arrow-up-circle me-1"></i>Receitas</th>
          </tr>

          <?php if (!empty($incomeRows)): ?>
            <?php foreach ($incomeRows as $row): ?>
              <?php $rowLabel = htmlspecialchars((string) ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
              <?php $categoryId = (int) ($row['category_id'] ?? 0); ?>
              <tr class="financial-sheet-data-row financial-sheet-income-data">
                <th scope="row" title="<?= $rowLabel; ?>"><span class="d-block text-truncate"><?= $rowLabel; ?></span></th>
                <?php foreach (array_keys($months) as $monthNumber): ?>
                  <td><?= financialSheetEntryButton((int) ($row['months'][$monthNumber] ?? 0), [
                    'sheet_year' => $year,
                    'sheet_type' => 'CREDIT',
                    'sheet_category_id' => $categoryId,
                    'sheet_category_label' => (string) ($row['label'] ?? ''),
                    'sheet_month' => (int) $monthNumber,
                    'sheet_month_label' => (string) ($months[$monthNumber] ?? ''),
                  ], true, $canEditSheetOperations); ?></td>
                <?php endforeach; ?>
                <td><?= financialSheetEntryButton((int) ($row['total'] ?? 0), [
                  'sheet_year' => $year,
                  'sheet_type' => 'CREDIT',
                  'sheet_category_id' => $categoryId,
                  'sheet_category_label' => (string) ($row['label'] ?? ''),
                  'sheet_month' => '',
                  'sheet_month_label' => 'Ano',
                ], false, $canEditSheetOperations); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="financial-sheet-data-row financial-sheet-empty-row">
              <th scope="row" title="Nenhuma receita lançada"><span class="d-block text-truncate">Nenhuma receita lançada</span></th>
              <?php foreach ($months as $_monthNumber => $_monthLabel): ?>
                <td></td>
              <?php endforeach; ?>
              <td>0,00</td>
            </tr>
          <?php endif; ?>

          <tr class="financial-sheet-total-row financial-sheet-income-total">
            <th scope="row" title="Subtotal receitas"><span class="d-block text-truncate">Subtotal receitas</span></th>
            <?php foreach (array_keys($months) as $monthNumber): ?>
              <td><?= financialSheetMoney((int) ($incomeTotals['months'][$monthNumber] ?? 0)); ?></td>
            <?php endforeach; ?>
            <td><?= financialSheetMoney($incomeTotal); ?></td>
          </tr>

          <tr class="financial-sheet-section-row financial-sheet-section-expense">
            <th colspan="<?= count($months) + 2; ?>"><i class="bi bi-arrow-down-circle me-1"></i>Despesas</th>
          </tr>

          <?php if (!empty($expenseRows)): ?>
            <?php foreach ($expenseRows as $row): ?>
              <?php $rowLabel = htmlspecialchars((string) ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
              <?php $categoryId = (int) ($row['category_id'] ?? 0); ?>
              <tr class="financial-sheet-data-row financial-sheet-expense-data">
                <th scope="row" title="<?= $rowLabel; ?>"><span class="d-block text-truncate"><?= $rowLabel; ?></span></th>
                <?php foreach (array_keys($months) as $monthNumber): ?>
                  <td><?= financialSheetEntryButton((int) ($row['months'][$monthNumber] ?? 0), [
                    'sheet_year' => $year,
                    'sheet_type' => 'DEBIT',
                    'sheet_category_id' => $categoryId,
                    'sheet_category_label' => (string) ($row['label'] ?? ''),
                    'sheet_month' => (int) $monthNumber,
                    'sheet_month_label' => (string) ($months[$monthNumber] ?? ''),
                  ], true, $canEditSheetOperations); ?></td>
                <?php endforeach; ?>
                <td><?= financialSheetEntryButton((int) ($row['total'] ?? 0), [
                  'sheet_year' => $year,
                  'sheet_type' => 'DEBIT',
                  'sheet_category_id' => $categoryId,
                  'sheet_category_label' => (string) ($row['label'] ?? ''),
                  'sheet_month' => '',
                  'sheet_month_label' => 'Ano',
                ], false, $canEditSheetOperations); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="financial-sheet-data-row financial-sheet-empty-row">
              <th scope="row" title="Nenhuma despesa lançada"><span class="d-block text-truncate">Nenhuma despesa lançada</span></th>
              <?php foreach ($months as $_monthNumber => $_monthLabel): ?>
                <td></td>
              <?php endforeach; ?>
              <td>0,00</td>
            </tr>
          <?php endif; ?>

          <tr class="financial-sheet-total-row financial-sheet-expense-total">
            <th scope="row" title="Subtotal despesas"><span class="d-block text-truncate">Subtotal despesas</span></th>
            <?php foreach (array_keys($months) as $monthNumber): ?>
              <td><?= financialSheetMoney((int) ($expenseTotals['months'][$monthNumber] ?? 0)); ?></td>
            <?php endforeach; ?>
            <td><?= financialSheetMoney($expenseTotal); ?></td>
          </tr>

          <tr class="financial-sheet-section-row financial-sheet-section-balance">
            <th colspan="<?= count($months) + 2; ?>"><i class="bi bi-calculator me-1"></i>Saldos</th>
          </tr>

          <tr class="financial-sheet-total-row financial-sheet-balance-row">
            <th scope="row" title="Subtotal saldos (R$)"><span class="d-block text-truncate">Subtotal saldos (R$)</span></th>
            <?php foreach (array_keys($months) as $monthNumber): ?>
              <?php $monthBalance = (int) ($balanceTotals['months'][$monthNumber] ?? 0); ?>
              <td class="<?= $monthBalance < 0 ? 'financial-sheet-negative' : ''; ?>"><?= financialSheetMoney($monthBalance); ?></td>
            <?php endforeach; ?>
            <td class="<?= $yearBalance < 0 ? 'financial-sheet-negative' : ''; ?>"><?= financialSheetMoney($yearBalance); ?></td>
          </tr>

          <tr class="financial-sheet-total-row financial-sheet-percent-row">
            <th scope="row" title="Percentual despesas (%)"><span class="d-block text-truncate">Percentual despesas (%)</span></th>
            <?php foreach (array_keys($months) as $monthNumber): ?>
              <td><?= financialSheetPercent($expensePercentages['months'][$monthNumber] ?? null); ?></td>
            <?php endforeach; ?>
            <td><?= financialSheetPercent($expensePercentages['total'] ?? null); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="financialSheetOperationModal" tabindex="-1" aria-labelledby="financialSheetOperationModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="financialSheetOperationModalTitle">Editar lançamento</h5>
          <span class="financial-sheet-modal-subtitle" id="financialSheetOperationModalSubtitle"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="alert d-none" id="financialSheetModalStatus" role="alert"></div>

        <div class="financial-sheet-modal-grid">
          <div class="financial-sheet-entry-list" id="financialSheetEntryList" aria-label="Lançamentos da célula"></div>

          <form class="financial-sheet-entry-form d-none" id="financialSheetOperationForm">
            <input type="hidden" id="sheet_operation_id" name="operation_id">
            <input type="hidden" id="sheet_payment_id" name="payment_id">

            <div class="financial-sheet-form-section">
              <h6>Operação financeira</h6>
              <div class="row">
                <div class="mb-2 col-md-6">
                  <label class="form-label" for="sheet_session_user">Usuário</label>
                  <input class="form-control" id="sheet_session_user" type="text" value="<?= htmlspecialchars($sessionUserLabel, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                </div>

                <div class="mb-2 col-md-6">
                  <label class="form-label" for="sheet_financial_operation_category_id">Categoria</label>
                  <select class="form-select" id="sheet_financial_operation_category_id" name="financial_operation_category_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($categories as $category): ?>
                      <?php
                      $categoryLabel = (string) ($category['name'] ?? '');
                      if (!empty($category['description']))
                        {
                        $categoryLabel .= ' - ' . (string) $category['description'];
                        }
                      ?>
                      <option value="<?= (int) $category['id']; ?>"><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="mb-2 col-md-6">
                  <label class="form-label" for="sheet_type">Tipo</label>
                  <select class="form-select" id="sheet_type" name="type" required>
                    <option value="">Selecione</option>
                    <?php foreach ($types as $value => $label): ?>
                      <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="mb-2 col-md-6">
                  <label class="form-label" for="sheet_total_value">Valor total</label>
                  <input autocomplete="off" class="form-control" id="sheet_total_value" name="total_value" min="0" step="0.01" type="number" required>
                </div>
              </div>
            </div>

            <div class="financial-sheet-form-section d-none" id="financialSheetPaymentFields">
              <h6>Parcela selecionada</h6>
              <div class="row">
                <div class="mb-2 col-md-6 col-lg-3">
                  <label class="form-label" for="sheet_payment_type">Tipo do pagamento</label>
                  <select class="form-select" id="sheet_payment_type" name="payment_type" required>
                    <option value="">Selecione</option>
                    <?php foreach ($paymentTypes as $value => $label): ?>
                      <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="mb-2 col-md-6 col-lg-3">
                  <label class="form-label" for="sheet_payment_value">Valor</label>
                  <input autocomplete="off" class="form-control" id="sheet_payment_value" name="payment_value" min="0" step="0.01" type="number" required>
                </div>

                <div class="mb-2 col-md-6 col-lg-3">
                  <label class="form-label" for="sheet_payment_date">Data de pagamento</label>
                  <input autocomplete="off" class="form-control" id="sheet_payment_date" name="payment_date" type="datetime-local">
                </div>

                <div class="mb-2 col-md-6 col-lg-3">
                  <label class="form-label" for="sheet_due_date">Data de vencimento</label>
                  <input autocomplete="off" class="form-control" id="sheet_due_date" name="due_date" type="date" required>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
      <div class="modal-footer">
        <a class="btn btn-outline-secondary d-none me-auto" id="financialSheetFullEditLink" href="#">
          <i class="bi bi-pencil-square me-1"></i>Edição completa
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="submit" class="btn btn-primary d-none" id="financialSheetSaveButton" form="financialSheetOperationForm">Salvar</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/financial_operations/sheet.js?v=2'); ?>"></script>
