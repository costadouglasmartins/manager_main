<?php
$types = $types ?? [];
$operationTypes = $operationTypes ?? [];
$operationType = $operationTypes[$financial_operation_payment['financial_operation_type']] ?? $financial_operation_payment['financial_operation_type'];
$operationLabel = '#' . (int) $financial_operation_payment['financial_operation_id'] . ' - ' . $operationType;
if (!empty($financial_operation_payment['financial_operation_category_name']))
  {
  $operationLabel .= ' - ' . $financial_operation_payment['financial_operation_category_name'];
  }
$userLabel = trim(($financial_operation_payment['user_name'] ?? '') . ' ' . (!empty($financial_operation_payment['username']) ? '(' . $financial_operation_payment['username'] . ')' : ''));
$paymentDate = !empty($financial_operation_payment['payment_date']) ? date('d/m/Y H:i', strtotime($financial_operation_payment['payment_date'])) : '';
$dueDate = !empty($financial_operation_payment['due_date']) ? date('d/m/Y', strtotime($financial_operation_payment['due_date'])) : '';
$created = !empty($financial_operation_payment['created']) ? date('d/m/Y H:i', strtotime($financial_operation_payment['created'])) : '';
$modified = !empty($financial_operation_payment['modified']) ? date('d/m/Y H:i', strtotime($financial_operation_payment['modified'])) : '';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation_payment'); ?>">Pagamentos financeiros</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pagamento financeiro #<?= (int) $financial_operation_payment['id']; ?></li>
  </ol>
</nav>

<h5>Dados do pagamento financeiro</h5>
<div class="table-responsive mb-3">
  <table class="table table-hover align-middle">
    <tbody>
      <tr>
        <th class="text-muted" style="width: 220px;">Código</th>
        <td><?= (int) $financial_operation_payment['id']; ?></td>
      </tr>
      <tr>
        <th class="text-muted">Operação financeira</th>
        <td><?= htmlspecialchars($operationLabel, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Usuário</th>
        <td><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Tipo</th>
        <td><?= htmlspecialchars($types[$financial_operation_payment['type']] ?? $financial_operation_payment['type'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Valor</th>
        <td>R$ <?= number_format((float) $financial_operation_payment['value'], 2, ',', '.'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Data de pagamento</th>
        <td><?= htmlspecialchars($paymentDate, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Data de vencimento</th>
        <td><?= htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Criado</th>
        <td><?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Modificado</th>
        <td><?= htmlspecialchars($modified, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>
</div>
