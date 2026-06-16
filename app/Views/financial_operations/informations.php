<?php
if (!function_exists('financialOperationInformationMoney'))
	{
	function financialOperationInformationMoney($value): string
		{
		return 'R$ ' . number_format((float) $value, 2, ',', '.');
		}
	}

if (!function_exists('financialOperationInformationDate'))
	{
	function financialOperationInformationDate($value, string $format = 'd/m/Y H:i'): string
		{
		if (empty($value))
			{
			return '-';
			}

		$timestamp = strtotime((string) $value);
		if ($timestamp === false)
			{
			return '-';
			}

		return date($format, $timestamp);
		}
	}

if (!function_exists('financialOperationInformationPaymentIcon'))
	{
	function financialOperationInformationPaymentIcon(string $type): string
		{
		$icons = [
			'CASH'      => 'bi-cash-stack',
			'CHECK'     => 'bi-file-earmark-check',
			'PIX'       => 'bi-qr-code',
			'CARD'      => 'bi-credit-card-2-front',
			'BANK_SLIP' => 'bi-upc-scan',
		];

		return $icons[$type] ?? 'bi-wallet2';
		}
	}

if (!function_exists('financialOperationInformationPaymentStatus'))
	{
	function financialOperationInformationPaymentStatus(array $payment): array
		{
		if (!empty($payment['payment_date']))
			{
			return ['key' => 'paid', 'label' => 'Pago', 'class' => 'foi-status-paid', 'icon' => 'bi-check-circle-fill'];
			}

		if (empty($payment['due_date']))
			{
			return ['key' => 'neutral', 'label' => 'Sem vencimento', 'class' => 'foi-status-neutral', 'icon' => 'bi-dash-circle'];
			}

		$dueTimestamp = strtotime((string) $payment['due_date']);
		$todayTimestamp = strtotime(date('Y-m-d'));
		if ($dueTimestamp !== false && $todayTimestamp !== false)
			{
			if ($dueTimestamp < $todayTimestamp)
				{
				return ['key' => 'overdue', 'label' => 'Vencido', 'class' => 'foi-status-overdue', 'icon' => 'bi-exclamation-circle-fill'];
				}

			if ($dueTimestamp === $todayTimestamp)
				{
				return ['key' => 'today', 'label' => 'Vence hoje', 'class' => 'foi-status-today', 'icon' => 'bi-clock-fill'];
				}
			}

		return ['key' => 'open', 'label' => 'Em aberto', 'class' => 'foi-status-open', 'icon' => 'bi-hourglass-split'];
		}
	}

$financial_operation = is_array($financial_operation ?? null) ? $financial_operation : [];
$financial_operation_payments = is_array($financial_operation_payments ?? null) ? $financial_operation_payments : [];
$types = $types ?? [];
$paymentTypes = $paymentTypes ?? [];

$hasOperation = !empty($financial_operation);
$operationId = (int) ($financial_operation['id'] ?? 0);
$operationType = (string) ($financial_operation['type'] ?? '');
$operationTypeLabel = $types[$operationType] ?? $operationType;
$operationTypeClass = $operationType === 'CREDIT' ? 'foi-type-credit' : 'foi-type-debit';
$operationTotal = (float) ($financial_operation['total_value'] ?? 0);
$categoryId = (int) ($financial_operation['financial_operation_category_id'] ?? 0);
$categoryLabel = trim((string) ($financial_operation['category_name'] ?? ''));
$categoryLabel = $categoryLabel !== '' ? $categoryLabel : 'Sem categoria';
$categoryDescription = trim((string) ($financial_operation['category_description'] ?? ''));
if ($categoryDescription !== '')
	{
	$categoryLabel .= ' - ' . $categoryDescription;
	}
$categoryDetail = $categoryId > 0 ? '#' . $categoryId . ' - ' . $categoryLabel : $categoryLabel;
$subcategoryId = (int) ($financial_operation['financial_operation_subcategory_id'] ?? 0);
$subcategoryLabel = trim((string) ($financial_operation['subcategory_name'] ?? ''));
$subcategoryLabel = $subcategoryLabel !== '' ? $subcategoryLabel : 'Sem subcategoria';
$subcategoryDetail = $subcategoryId > 0 ? '#' . $subcategoryId . ' - ' . $subcategoryLabel : $subcategoryLabel;

$userId = (int) ($financial_operation['user_id'] ?? 0);
$userName = trim((string) ($financial_operation['user_name'] ?? ''));
$username = trim((string) ($financial_operation['username'] ?? ''));
$userLabel = trim($userName . ($username !== '' ? ' (' . $username . ')' : ''));
$userLabel = $userLabel !== '' ? $userLabel : 'Usuário #' . $userId;
$userDetail = $userId > 0 ? '#' . $userId . ' - ' . $userLabel : $userLabel;

$paymentsTotal = 0.0;
$paidTotal = 0.0;
$pendingTotal = 0.0;
$paidCount = 0;
$openCount = 0;
$overdueCount = 0;
$nextDueDate = '-';

foreach ($financial_operation_payments as $payment)
	{
	$paymentValue = (float) ($payment['value'] ?? 0);
	$paymentsTotal += $paymentValue;

	$status = financialOperationInformationPaymentStatus($payment);
	if ($status['key'] === 'paid')
		{
		$paidTotal += $paymentValue;
		$paidCount++;
		continue;
		}

	$pendingTotal += $paymentValue;
	$openCount++;
	if ($status['key'] === 'overdue')
		{
		$overdueCount++;
		}

	if ($nextDueDate === '-' && !empty($payment['due_date']))
		{
		$nextDueDate = financialOperationInformationDate($payment['due_date'], 'd/m/Y');
		}
	}

$remainingTotal = $operationTotal - $paidTotal;
$allocationDelta = $operationTotal - $paymentsTotal;
$paidPercent = $operationTotal > 0 ? min(100, max(0, ($paidTotal / $operationTotal) * 100)) : 0;
$allocatedPercent = $operationTotal > 0 ? min(100, max(0, ($paymentsTotal / $operationTotal) * 100)) : 0;
$paymentCount = count($financial_operation_payments);
?>

<link rel="stylesheet" href="<?= url('assets/css/financial_operations/informations.css?v=1'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation'); ?>">Operações financeiras</a></li>
    <li class="breadcrumb-item active" aria-current="page">Operação financeira #<?= $operationId; ?></li>
  </ol>
</nav>

<?php if (!$hasOperation): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
    <i class="bi bi-exclamation-triangle"></i>
    <span>Operação financeira não encontrada.</span>
  </div>
  <a class="btn btn-secondary" href="<?= url('financial_operation'); ?>">
    <i class="bi bi-arrow-left me-1"></i>Voltar
  </a>
<?php else: ?>
  <section class="financial-operation-info">
    <div class="foi-header mb-4">
      <div class="foi-header-main">
        <span class="foi-kicker">Operação financeira</span>
        <h1>Operação #<?= $operationId; ?></h1>
        <div class="foi-header-meta">
          <span class="foi-type-badge <?= htmlspecialchars($operationTypeClass, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="bi <?= $operationType === 'CREDIT' ? 'bi-arrow-down-left-circle' : 'bi-arrow-up-right-circle'; ?>"></i>
            <?= htmlspecialchars($operationTypeLabel, ENT_QUOTES, 'UTF-8'); ?>
          </span>
          <span><i class="bi bi-tag"></i><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?></span>
          <span><i class="bi bi-tags"></i><?= htmlspecialchars($subcategoryLabel, ENT_QUOTES, 'UTF-8'); ?></span>
          <span><i class="bi bi-person-circle"></i><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      </div>
      <div class="foi-actions">
        <a class="btn btn-outline-secondary" href="<?= url('financial_operation'); ?>">
          <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
        <?php if (authCan('financial_operation.edit')): ?>
          <a class="btn btn-primary" href="<?= url('financial_operation/edit/' . $operationId); ?>">
            <i class="bi bi-pencil-square me-1"></i>Editar
          </a>
        <?php endif; ?>
        <?php if (authCan('financial_operation_payment.create')): ?>
          <a class="btn btn-success" href="<?= url('financial_operation_payment/create'); ?>">
            <i class="bi bi-plus-lg me-1"></i>Pagamento
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-md-6 col-xl-3">
        <div class="foi-kpi">
          <span class="foi-kpi-icon foi-kpi-total"><i class="bi bi-receipt"></i></span>
          <span class="foi-kpi-label">Valor total</span>
          <strong><?= financialOperationInformationMoney($operationTotal); ?></strong>
          <small>Base da operação</small>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="foi-kpi">
          <span class="foi-kpi-icon foi-kpi-paid"><i class="bi bi-check2-circle"></i></span>
          <span class="foi-kpi-label">Total pago</span>
          <strong><?= financialOperationInformationMoney($paidTotal); ?></strong>
          <small><?= number_format($paidPercent, 0, ',', '.'); ?>% do total</small>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="foi-kpi">
          <span class="foi-kpi-icon foi-kpi-open"><i class="bi bi-hourglass-bottom"></i></span>
          <span class="foi-kpi-label">Saldo em aberto</span>
          <strong><?= financialOperationInformationMoney($remainingTotal); ?></strong>
          <small><?= financialOperationInformationMoney($pendingTotal); ?> em parcelas abertas</small>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="foi-kpi">
          <span class="foi-kpi-icon foi-kpi-count"><i class="bi bi-credit-card"></i></span>
          <span class="foi-kpi-label">Pagamentos</span>
          <strong><?= number_format($paymentCount, 0, ',', '.'); ?></strong>
          <small><?= number_format($paidCount, 0, ',', '.'); ?> pagos, <?= number_format($openCount, 0, ',', '.'); ?> em aberto</small>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-12 col-xl-8">
        <div class="foi-panel h-100">
          <div class="foi-panel-header">
            <div>
              <span class="foi-kicker">Resumo</span>
              <h2>Progresso financeiro</h2>
            </div>
            <?php if ($overdueCount > 0): ?>
              <span class="badge text-bg-danger"><?= number_format($overdueCount, 0, ',', '.'); ?> vencido(s)</span>
            <?php else: ?>
              <span class="badge text-bg-success">Sem atrasos</span>
            <?php endif; ?>
          </div>

          <div class="foi-progress-group">
            <div class="foi-progress-row">
              <div class="foi-progress-label">
                <span>Pago</span>
                <strong><?= financialOperationInformationMoney($paidTotal); ?></strong>
              </div>
              <div class="foi-progress-track" aria-label="Percentual pago">
                <span class="foi-progress-fill foi-progress-paid" style="--progress-value: <?= number_format($paidPercent, 4, '.', ''); ?>%;"></span>
              </div>
            </div>
            <div class="foi-progress-row">
              <div class="foi-progress-label">
                <span>Lançado em pagamentos</span>
                <strong><?= financialOperationInformationMoney($paymentsTotal); ?></strong>
              </div>
              <div class="foi-progress-track" aria-label="Percentual lançado">
                <span class="foi-progress-fill foi-progress-allocated" style="--progress-value: <?= number_format($allocatedPercent, 4, '.', ''); ?>%;"></span>
              </div>
            </div>
          </div>

          <div class="foi-balance-strip">
            <div>
              <span>Diferença entre operação e pagamentos</span>
              <strong class="<?= abs($allocationDelta) < 0.01 ? 'text-success' : 'text-warning'; ?>">
                <?= financialOperationInformationMoney($allocationDelta); ?>
              </strong>
            </div>
            <div>
              <span>Próximo vencimento</span>
              <strong>
                <?= htmlspecialchars($nextDueDate, ENT_QUOTES, 'UTF-8'); ?>
              </strong>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-4">
        <div class="foi-panel h-100">
          <div class="foi-panel-header">
            <div>
              <span class="foi-kicker">Associações</span>
              <h2>Vínculos da operação</h2>
            </div>
          </div>

          <dl class="foi-definition-list">
            <div>
              <dt><i class="bi bi-hash"></i>Código</dt>
              <dd>#<?= $operationId; ?></dd>
            </div>
            <div>
              <dt><i class="bi bi-person"></i>Usuário</dt>
              <dd><?= htmlspecialchars($userDetail, ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
            <div>
              <dt><i class="bi bi-tag"></i>Categoria</dt>
              <dd><?= htmlspecialchars($categoryDetail, ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
            <div>
              <dt><i class="bi bi-tags"></i>Subcategoria</dt>
              <dd><?= htmlspecialchars($subcategoryDetail, ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
            <div>
              <dt><i class="bi bi-credit-card"></i>Pagamentos vinculados</dt>
              <dd><?= number_format($paymentCount, 0, ',', '.'); ?></dd>
            </div>
            <div>
              <dt><i class="bi bi-calendar-plus"></i>Criado</dt>
              <dd><?= htmlspecialchars(financialOperationInformationDate($financial_operation['created'] ?? null), ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
            <div>
              <dt><i class="bi bi-calendar-check"></i>Modificado</dt>
              <dd><?= htmlspecialchars(financialOperationInformationDate($financial_operation['modified'] ?? null), ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
          </dl>
        </div>
      </div>
    </div>

    <div class="foi-panel mb-4">
      <div class="foi-panel-header">
        <div>
          <span class="foi-kicker">Tabela associada</span>
          <h2>Pagamentos da operação</h2>
        </div>
        <?php if (authCan('financial_operation_payment.index') || authCan('financial_operation.update')): ?>
          <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
            <?php if (authCan('financial_operation.update')): ?>
              <form class="m-0" method="POST" action="<?= url('financial_operation/recalculateTotal/' . $operationId); ?>">
                <button class="btn btn-outline-secondary btn-sm w-100" type="submit">
                  <i class="bi bi-calculator me-1"></i>Recalcular valor
                </button>
              </form>
            <?php endif; ?>
            <?php if (authCan('financial_operation_payment.index')): ?>
              <a class="btn btn-outline-primary btn-sm" href="<?= url('financial_operation_payment?financial_operation_id=' . $operationId); ?>">
                <i class="bi bi-list-ul me-1"></i>Ver no módulo
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($financial_operation_payments)): ?>
        <div class="table-responsive">
          <table class="table foi-payments-table align-middle mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Tipo</th>
                <th>Valor</th>
                <th>Situação</th>
                <th>Pagamento</th>
                <th>Vencimento</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($financial_operation_payments as $payment): ?>
                <?php
                $paymentStatus = financialOperationInformationPaymentStatus($payment);
                $paymentType = (string) ($payment['type'] ?? '');
                ?>
                <tr>
                  <td class="text-muted" style="width:1%;">#<?= (int) $payment['id']; ?></td>
                  <td>
                    <span class="foi-payment-type">
                      <i class="bi <?= htmlspecialchars(financialOperationInformationPaymentIcon($paymentType), ENT_QUOTES, 'UTF-8'); ?>"></i>
                      <?= htmlspecialchars($paymentTypes[$paymentType] ?? $paymentType, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </td>
                  <td><strong><?= financialOperationInformationMoney($payment['value'] ?? 0); ?></strong></td>
                  <td>
                    <span class="foi-status <?= htmlspecialchars($paymentStatus['class'], ENT_QUOTES, 'UTF-8'); ?>">
                      <i class="bi <?= htmlspecialchars($paymentStatus['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                      <?= htmlspecialchars($paymentStatus['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars(financialOperationInformationDate($payment['payment_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?= htmlspecialchars(financialOperationInformationDate($payment['due_date'] ?? null, 'd/m/Y'), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="text-nowrap text-end" style="width:1%;">
                    <?php if (authCan('financial_operation_payment.edit')): ?>
                      <a href="<?= url('financial_operation_payment/edit/' . $payment['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                    <?php endif; ?>
                    <?php if (authCan('financial_operation_payment.informations')): ?>
                      <a href="<?= url('financial_operation_payment/informations/' . $payment['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
                    <?php endif; ?>
                    <?php if (authCan('financial_operation_payment.delete')): ?>
                      <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= (int) $payment['id']; ?>" data-name="Pagamento #<?= (int) $payment['id']; ?>">
                        <i class="bi bi-trash"></i>
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="foi-empty">
          <i class="bi bi-credit-card-2-front"></i>
          <strong>Nenhum pagamento vinculado.</strong>
          <span>Esta operação ainda não possui registros em financial_operations_payments.</span>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!empty($financial_operation_payments) && authCan('financial_operation_payment.delete')): ?>
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
              <input type="hidden" name="redirect_to" value="financial_operation/informations/<?= $operationId; ?>">
              <button type="submit" class="btn btn-danger">Excluir</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<script src="<?= url('assets/js/financial_operations/informations.js?v=2'); ?>"></script>
