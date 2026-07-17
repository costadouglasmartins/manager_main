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

if (!function_exists('financialSheetIsPaidValue'))
  {
  function financialSheetIsPaidValue(int $cents, int $paidCents): bool
    {
    return $cents > 0 && $paidCents === $cents;
    }
  }

if (!function_exists('financialSheetEntryButton'))
  {
  function financialSheetEntryButton(int $cents, array $data, bool $blankWhenZero, bool $canEdit, bool $isPaid = false): string
    {
    $label = financialSheetMoney($cents, $blankWhenZero);
    if (!$canEdit || $cents === 0 || $label === '')
      {
      $displayLabel = $label !== '' ? $label : financialSheetMoney($cents);

      if ($isPaid && $displayLabel !== '')
        {
        return '<span class="financial-sheet-entry-paid">' . htmlspecialchars($displayLabel, ENT_QUOTES, 'UTF-8') . '</span>';
        }

      return $displayLabel;
      }

    $attributes = [
      'type' => 'button',
      'class' => 'financial-sheet-entry-edit',
      'data-sheet-entry' => '1',
      'aria-label' => 'Editar lançamento',
      'title' => 'Editar lançamento',
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

    $valueClasses = ['financial-sheet-entry-value'];
    if ($isPaid)
      {
      $valueClasses[] = 'financial-sheet-entry-paid';
      }

    return '<span class="financial-sheet-entry">'
      . '<button ' . implode(' ', $htmlAttributes) . '><i class="bi bi-pencil-square"></i></button>'
      . '<span class="' . implode(' ', $valueClasses) . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
      . '</span>';
    }
  }

if (!function_exists('financialSheetCreateButton'))
  {
  function financialSheetCreateButton(array $data, bool $canCreate): string
    {
    $label = '0,00';
    if (!$canCreate)
      {
      return $label;
      }

    $attributes = [
      'type' => 'button',
      'class' => 'financial-sheet-entry-create',
      'data-sheet-create' => '1',
      'aria-label' => 'Criar lançamento',
      'title' => 'Criar lançamento',
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

    return '<span class="financial-sheet-entry financial-sheet-entry-create-wrap">'
      . '<button ' . implode(' ', $htmlAttributes) . '><i class="bi bi-plus-lg"></i></button>'
      . '<span class="financial-sheet-entry-value">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
      . '</span>';
    }
  }

if (!function_exists('financialSheetCategoryLabel'))
  {
  function financialSheetCategoryLabel(string $label): string
    {
    $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    return '<span class="financial-sheet-category-label" tabindex="0"'
      . ' data-bs-toggle="popover"'
      . ' data-bs-trigger="hover focus"'
      . ' data-bs-placement="right"'
      . ' data-bs-container="body"'
      . ' data-bs-content="' . $escapedLabel . '">'
      . $escapedLabel
      . '</span>';
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
$currentMonthNumber = ((int) $year === (int) date('Y')) ? (int) date('n') : 0;
$financialSheetMonthClass = static function ($monthNumber) use ($currentMonthNumber): string
  {
  return ($currentMonthNumber !== 0 && (int) $monthNumber === $currentMonthNumber) ? 'financial-sheet-month-current' : '';
  };
$canCreateSheetOperations = authCan('financial_operation.create');
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

<link rel="stylesheet" href="<?= url('assets/css/financial_operations/create.css?v=3'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/financial_operations/sheet.css?v=24'); ?>">

<nav class="financial-sheet-breadcrumb mb-3" aria-label="breadcrumb">
  <ol class="breadcrumb mb-0">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation'); ?>">Operações financeiras</a></li>
    <li class="breadcrumb-item active" aria-current="page">Planilha anual</li>
  </ol>
</nav>

<div class="financial-sheet-page"
     data-sheet-operations-url="<?= url('financial_operation/sheetOperations'); ?>"
     data-sheet-update-base-url="<?= url('financial_operation/sheetUpdateOperation'); ?>"
     data-sheet-edit-base-url="<?= url('financial_operation/edit'); ?>"
     data-sheet-create-base-url="<?= url('financial_operation/create'); ?>"
     data-can-edit-sheet-operations="<?= $canEditSheetOperations ? '1' : '0'; ?>"
     data-can-create-sheet-operations="<?= $canCreateSheetOperations ? '1' : '0'; ?>">
  <header class="financial-sheet-header">
    <div class="financial-sheet-title">
      <span class="financial-sheet-title-icon" aria-hidden="true"><i class="bi bi-table"></i></span>
      <div class="financial-sheet-title-content">
        <span class="financial-sheet-kicker">Financeiro</span>
        <h1 class="h4">Planilha financeira <?= (int) $year; ?></h1>
        <p>Visão anual consolidada das receitas, despesas e lançamentos mensais.</p>
      </div>
    </div>
    <div class="financial-sheet-actions">
      <a class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-2" href="<?= url('financial_operation'); ?>">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        <span>Voltar</span>
      </a>
      <form class="financial-sheet-year-filter input-group input-group-sm" action="<?= url('financial_operation/sheet'); ?>" method="get" aria-label="Filtrar planilha por ano">
        <label class="input-group-text" for="financial_sheet_year"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Ano</label>
        <select class="form-select form-select-sm" id="financial_sheet_year" name="year">
          <?php foreach ($yearOptions as $yearOption): ?>
            <option value="<?= (int) $yearOption; ?>" <?= (int) $yearOption === $year ? 'selected' : ''; ?>>
              <?= (int) $yearOption; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2" type="submit">
          <i class="bi bi-funnel" aria-hidden="true"></i>
          <span>Aplicar</span>
        </button>
      </form>
      <?php if (authCan('financial_operation.create')): ?>
        <a class="btn btn-success btn-sm d-inline-flex align-items-center justify-content-center gap-2" href="<?= url('financial_operation/create'); ?>">
          <i class="bi bi-plus-lg" aria-hidden="true"></i>
          <span>Novo lançamento</span>
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

    <div class="table-responsive">
      <table class="table financial-sheet-table table-sm align-middle mb-0 small">
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
            <?php foreach ($months as $monthNumber => $monthLabel): ?>
              <?php $monthHeader = function_exists('mb_strtoupper') ? mb_strtoupper($monthLabel, 'UTF-8') : strtoupper($monthLabel); ?>
              <?php $monthTitle = htmlspecialchars($monthHeader, ENT_QUOTES, 'UTF-8'); ?>
              <th class="<?= $financialSheetMonthClass($monthNumber); ?>"><span class="d-block text-truncate" title="<?= $monthTitle; ?>"><?= $monthTitle; ?></span></th>
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
              <tr class="financial-sheet-data-row financial-sheet-income-data" data-sheet-row="1" data-sheet-type="CREDIT" data-sheet-category-id="<?= $categoryId; ?>">
                <th scope="row"><?= financialSheetCategoryLabel((string) ($row['label'] ?? '')); ?></th>
                <?php foreach (array_keys($months) as $monthNumber): ?>
                <?php
                $monthCents = (int) ($row['months'][$monthNumber] ?? 0);
                $monthPaidCents = (int) ($row['paidMonths'][$monthNumber] ?? 0);
                ?>
                  <td class="<?= $financialSheetMonthClass($monthNumber); ?>">
                    <?php if ($monthCents === 0): ?>
                      <?= financialSheetCreateButton([
                        'sheet_year' => $year,
                        'sheet_type' => 'CREDIT',
                        'sheet_category_id' => $categoryId,
                        'sheet_category_label' => (string) ($row['label'] ?? ''),
                        'sheet_month' => (int) $monthNumber,
                        'sheet_month_label' => (string) ($months[$monthNumber] ?? ''),
                      ], $canCreateSheetOperations); ?>
                    <?php else: ?>
                      <?= financialSheetEntryButton($monthCents, [
                        'sheet_year' => $year,
                        'sheet_type' => 'CREDIT',
                        'sheet_category_id' => $categoryId,
                        'sheet_category_label' => (string) ($row['label'] ?? ''),
                        'sheet_month' => (int) $monthNumber,
                        'sheet_month_label' => (string) ($months[$monthNumber] ?? ''),
                      ], true, $canEditSheetOperations, financialSheetIsPaidValue($monthCents, $monthPaidCents)); ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <?php
                $rowTotalCents = (int) ($row['total'] ?? 0);
                $rowPaidTotalCents = (int) ($row['paidTotal'] ?? 0);
                ?>
                <td><?= financialSheetEntryButton($rowTotalCents, [
                  'sheet_year' => $year,
                  'sheet_type' => 'CREDIT',
                  'sheet_category_id' => $categoryId,
                  'sheet_category_label' => (string) ($row['label'] ?? ''),
                  'sheet_month' => '',
                  'sheet_month_label' => 'Ano',
                ], false, $canEditSheetOperations, financialSheetIsPaidValue($rowTotalCents, $rowPaidTotalCents)); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="financial-sheet-data-row financial-sheet-empty-row">
              <th scope="row" title="Nenhuma receita lançada"><span class="d-block text-truncate">Nenhuma receita lançada</span></th>
              <?php foreach ($months as $_monthNumber => $_monthLabel): ?>
                <td class="<?= $financialSheetMonthClass($_monthNumber); ?>"></td>
              <?php endforeach; ?>
              <td>0,00</td>
            </tr>
          <?php endif; ?>

          <tr class="financial-sheet-total-row financial-sheet-income-total">
            <th scope="row" title="Subtotal receitas"><span class="d-block text-truncate">Subtotal receitas</span></th>
            <?php foreach (array_keys($months) as $monthNumber): ?>
              <td class="<?= $financialSheetMonthClass($monthNumber); ?>"><?= financialSheetMoney((int) ($incomeTotals['months'][$monthNumber] ?? 0)); ?></td>
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
              <tr class="financial-sheet-data-row financial-sheet-expense-data" data-sheet-row="1" data-sheet-type="DEBIT" data-sheet-category-id="<?= $categoryId; ?>">
                <th scope="row"><?= financialSheetCategoryLabel((string) ($row['label'] ?? '')); ?></th>
                <?php foreach (array_keys($months) as $monthNumber): ?>
                <?php
                $monthCents = (int) ($row['months'][$monthNumber] ?? 0);
                $monthPaidCents = (int) ($row['paidMonths'][$monthNumber] ?? 0);
                ?>
                  <td class="<?= $financialSheetMonthClass($monthNumber); ?>">
                    <?php if ($monthCents === 0): ?>
                      <?= financialSheetCreateButton([
                        'sheet_year' => $year,
                        'sheet_type' => 'DEBIT',
                        'sheet_category_id' => $categoryId,
                        'sheet_category_label' => (string) ($row['label'] ?? ''),
                        'sheet_month' => (int) $monthNumber,
                        'sheet_month_label' => (string) ($months[$monthNumber] ?? ''),
                      ], $canCreateSheetOperations); ?>
                    <?php else: ?>
                      <?= financialSheetEntryButton($monthCents, [
                        'sheet_year' => $year,
                        'sheet_type' => 'DEBIT',
                        'sheet_category_id' => $categoryId,
                        'sheet_category_label' => (string) ($row['label'] ?? ''),
                        'sheet_month' => (int) $monthNumber,
                        'sheet_month_label' => (string) ($months[$monthNumber] ?? ''),
                      ], true, $canEditSheetOperations, financialSheetIsPaidValue($monthCents, $monthPaidCents)); ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <?php
                $rowTotalCents = (int) ($row['total'] ?? 0);
                $rowPaidTotalCents = (int) ($row['paidTotal'] ?? 0);
                ?>
                <td><?= financialSheetEntryButton($rowTotalCents, [
                  'sheet_year' => $year,
                  'sheet_type' => 'DEBIT',
                  'sheet_category_id' => $categoryId,
                  'sheet_category_label' => (string) ($row['label'] ?? ''),
                  'sheet_month' => '',
                  'sheet_month_label' => 'Ano',
                ], false, $canEditSheetOperations, financialSheetIsPaidValue($rowTotalCents, $rowPaidTotalCents)); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="financial-sheet-data-row financial-sheet-empty-row">
              <th scope="row" title="Nenhuma despesa lançada"><span class="d-block text-truncate">Nenhuma despesa lançada</span></th>
              <?php foreach ($months as $_monthNumber => $_monthLabel): ?>
                <td class="<?= $financialSheetMonthClass($_monthNumber); ?>"></td>
              <?php endforeach; ?>
              <td>0,00</td>
            </tr>
          <?php endif; ?>

          <tr class="financial-sheet-total-row financial-sheet-expense-total">
            <th scope="row" title="Subtotal despesas"><span class="d-block text-truncate">Subtotal despesas</span></th>
            <?php foreach (array_keys($months) as $monthNumber): ?>
              <td class="<?= $financialSheetMonthClass($monthNumber); ?>"><?= financialSheetMoney((int) ($expenseTotals['months'][$monthNumber] ?? 0)); ?></td>
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
              <td class="<?= trim(($monthBalance < 0 ? 'financial-sheet-negative ' : '') . $financialSheetMonthClass($monthNumber)); ?>"><?= financialSheetMoney($monthBalance); ?></td>
            <?php endforeach; ?>
            <td class="<?= $yearBalance < 0 ? 'financial-sheet-negative' : ''; ?>"><?= financialSheetMoney($yearBalance); ?></td>
          </tr>

          <tr class="financial-sheet-total-row financial-sheet-percent-row">
            <th scope="row" title="Percentual despesas (%)"><span class="d-block text-truncate">Percentual despesas (%)</span></th>
            <?php foreach (array_keys($months) as $monthNumber): ?>
              <td class="<?= $financialSheetMonthClass($monthNumber); ?>"><?= financialSheetPercent($expensePercentages['months'][$monthNumber] ?? null); ?></td>
            <?php endforeach; ?>
            <td><?= financialSheetPercent($expensePercentages['total'] ?? null); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="financialSheetCreateModal" tabindex="-1" aria-labelledby="financialSheetCreateModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="financialSheetCreateModalTitle">Nova operação financeira</h5>
          <span class="financial-sheet-modal-subtitle" id="financialSheetCreateModalSubtitle"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="alert d-none" id="financialOperationCreateStatus" role="alert"></div>
        <div class="alert alert-info d-none" id="financialOperationCreatePeriodNotice" role="alert"></div>

        <form action="<?= url('financial_operation/store/'); ?>" method="POST" id="financialOperationCreateForm" data-ajax-submit="1">
          <div class="row">
            <div class="mb-2 col-md-6 col-lg-4">
              <label class="form-label" for="financial_operation_category_id">Categoria</label>
              <select class="form-select" id="financial_operation_category_id" name="financial_operation_category_id" required>
                <option value="">Selecione</option>
                <?php foreach ($categories as $category): ?>
                  <?php
                  $categoryLabel = trim((string) ($category['name'] ?? ''));
                  if (!empty($category['description']))
                    {
                    $categoryLabel .= ' - ' . trim((string) $category['description']);
                    }
                  ?>
                  <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-2 col-md-6 col-lg-4">
              <label class="form-label" for="type">Tipo</label>
              <select class="form-select" id="type" name="type" required>
                <option value="">Selecione</option>
                <?php foreach ($types as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-2 col-md-6 col-lg-4">
              <label class="form-label" for="total_value">Valor total</label>
              <input autocomplete="off" class="form-control" id="total_value" name="total_value" min="0" step="0.01" type="number" required>
            </div>
          </div>

          <div class="financial-operation-recurrence">
            <div class="financial-operation-recurrence__header">
              <div>
                <span>Recorrência mensal</span>
                <strong>Lançar parcelas por faixa de datas</strong>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" id="generate_installments" name="generate_installments" type="checkbox" value="1">
                <label class="form-check-label" for="generate_installments">Gerar parcelas</label>
              </div>
            </div>

            <div class="row d-none" id="recurrenceFields">
              <div class="mb-2 col-md-6 col-lg-3">
                <label class="form-label" for="installment_value">Valor da parcela</label>
                <input autocomplete="off" class="form-control" id="installment_value" name="installment_value" min="0.01" step="0.01" type="number" data-recurrence-field disabled>
              </div>

              <div class="mb-2 col-md-6 col-lg-3">
                <label class="form-label" for="payment_type">Tipo do pagamento</label>
                <select class="form-select" id="payment_type" name="payment_type" data-recurrence-field disabled>
                  <option value="">Selecione</option>
                  <?php foreach ($paymentTypes as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === 'PIX' ? 'selected' : '' ?>>
                      <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-2 col-md-6 col-lg-3">
                <label class="form-label" for="recurrence_start_date">Data inicial</label>
                <input autocomplete="off" class="form-control" id="recurrence_start_date" name="recurrence_start_date" maxlength="10" pattern="\d{2}/\d{2}/\d{4}" placeholder="dd/mm/aaaa" type="text" inputmode="numeric" data-recurrence-field disabled>
              </div>

              <div class="mb-2 col-md-6 col-lg-3">
                <label class="form-label" for="recurrence_end_date">Data final</label>
                <input autocomplete="off" class="form-control" id="recurrence_end_date" name="recurrence_end_date" maxlength="10" pattern="\d{2}/\d{2}/\d{4}" placeholder="dd/mm/aaaa" type="text" inputmode="numeric" data-recurrence-field disabled>
              </div>
            </div>

            <div class="financial-operation-recurrence__preview" id="recurrencePreview">
              <i class="bi bi-calendar-check"></i>
              <span>Informe a faixa para calcular as parcelas mensais.</span>
            </div>

            <div class="financial-operation-recurrence-installments d-none" id="recurrenceInstallments"></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="submit" class="btn btn-primary" id="financialSheetCreateSaveButton" form="financialOperationCreateForm">Salvar</button>
      </div>
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
          <span class="financial-sheet-modal-subtitle" id="financialSheetOperationSelectedValue"></span>
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

<script src="<?= url('assets/js/financial_operations/create.js?v=5'); ?>"></script>
<script src="<?= url('assets/js/financial_operations/sheet.js?v=6'); ?>"></script>
