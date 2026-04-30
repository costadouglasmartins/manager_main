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

$sheet = $sheet ?? [];
$year = (int) ($sheet['year'] ?? date('Y'));
$yearOptions = $yearOptions ?? range(2026, 2031);
$months = $sheet['months'] ?? [];
$incomeRows = $sheet['incomeRows'] ?? [];
$expenseRows = $sheet['expenseRows'] ?? [];
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
?>

<link rel="stylesheet" href="<?= url('assets/css/financial_operations/sheet.css?v=1'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation'); ?>">Operações financeiras</a></li>
    <li class="breadcrumb-item active" aria-current="page">Planilha anual</li>
  </ol>
</nav>

<div class="financial-sheet-page">
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

    <div class="financial-sheet-scroll">
      <table class="financial-sheet-table">
        <colgroup>
          <col class="financial-sheet-label-col">
          <?php foreach ($months as $_monthNumber => $_monthLabel): ?>
            <col class="financial-sheet-month-col">
          <?php endforeach; ?>
          <col class="financial-sheet-total-col">
        </colgroup>
        <thead>
          <tr>
            <th>Categoria</th>
            <?php foreach ($months as $monthLabel): ?>
              <?php $monthHeader = function_exists('mb_strtoupper') ? mb_strtoupper($monthLabel, 'UTF-8') : strtoupper($monthLabel); ?>
              <th><?= htmlspecialchars($monthHeader, ENT_QUOTES, 'UTF-8'); ?></th>
            <?php endforeach; ?>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <tr class="financial-sheet-section-row financial-sheet-section-income">
            <th colspan="<?= count($months) + 2; ?>"><i class="bi bi-arrow-up-circle me-1"></i>Receitas</th>
          </tr>

          <?php if (!empty($incomeRows)): ?>
            <?php foreach ($incomeRows as $row): ?>
              <tr class="financial-sheet-data-row financial-sheet-income-data">
                <th scope="row"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                <?php foreach (array_keys($months) as $monthNumber): ?>
                  <td><?= financialSheetMoney((int) ($row['months'][$monthNumber] ?? 0), true); ?></td>
                <?php endforeach; ?>
                <td><?= financialSheetMoney((int) ($row['total'] ?? 0)); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="financial-sheet-data-row financial-sheet-empty-row">
              <th scope="row">Nenhuma receita lançada</th>
              <?php foreach ($months as $_monthNumber => $_monthLabel): ?>
                <td></td>
              <?php endforeach; ?>
              <td>0,00</td>
            </tr>
          <?php endif; ?>

          <tr class="financial-sheet-total-row financial-sheet-income-total">
            <th scope="row">Subtotal receitas</th>
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
              <tr class="financial-sheet-data-row financial-sheet-expense-data">
                <th scope="row"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                <?php foreach (array_keys($months) as $monthNumber): ?>
                  <td><?= financialSheetMoney((int) ($row['months'][$monthNumber] ?? 0), true); ?></td>
                <?php endforeach; ?>
                <td><?= financialSheetMoney((int) ($row['total'] ?? 0)); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="financial-sheet-data-row financial-sheet-empty-row">
              <th scope="row">Nenhuma despesa lançada</th>
              <?php foreach ($months as $_monthNumber => $_monthLabel): ?>
                <td></td>
              <?php endforeach; ?>
              <td>0,00</td>
            </tr>
          <?php endif; ?>

          <tr class="financial-sheet-total-row financial-sheet-expense-total">
            <th scope="row">Subtotal despesas</th>
            <?php foreach (array_keys($months) as $monthNumber): ?>
              <td><?= financialSheetMoney((int) ($expenseTotals['months'][$monthNumber] ?? 0)); ?></td>
            <?php endforeach; ?>
            <td><?= financialSheetMoney($expenseTotal); ?></td>
          </tr>

          <tr class="financial-sheet-section-row financial-sheet-section-balance">
            <th colspan="<?= count($months) + 2; ?>"><i class="bi bi-calculator me-1"></i>Saldos</th>
          </tr>

          <tr class="financial-sheet-total-row financial-sheet-balance-row">
            <th scope="row">Subtotal saldos (R$)</th>
            <?php foreach (array_keys($months) as $monthNumber): ?>
              <?php $monthBalance = (int) ($balanceTotals['months'][$monthNumber] ?? 0); ?>
              <td class="<?= $monthBalance < 0 ? 'financial-sheet-negative' : ''; ?>"><?= financialSheetMoney($monthBalance); ?></td>
            <?php endforeach; ?>
            <td class="<?= $yearBalance < 0 ? 'financial-sheet-negative' : ''; ?>"><?= financialSheetMoney($yearBalance); ?></td>
          </tr>

          <tr class="financial-sheet-total-row financial-sheet-percent-row">
            <th scope="row">Percentual despesas (%)</th>
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

<script src="<?= url('assets/js/financial_operations/sheet.js?v=1'); ?>"></script>
