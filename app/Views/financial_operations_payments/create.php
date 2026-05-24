<?php
$types = $types ?? [];
$operationTypes = $operationTypes ?? [];
$financialOperations = $financialOperations ?? [];
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation_payment'); ?>">Pagamentos financeiros</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pagamento financeiro - criar</li>
  </ol>
</nav>

<form action="<?= url('financial_operation_payment/store/'); ?>" method="POST">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="financial_operation_id">Operação financeira</label>
      <select class="form-select" id="financial_operation_id" name="financial_operation_id" required>
        <option value="">Selecione</option>
        <?php foreach ($financialOperations as $operation): ?>
          <?php
          $operationType = $operationTypes[$operation['type']] ?? $operation['type'];
          $userLabel = trim(($operation['user_name'] ?? '') . ' ' . (!empty($operation['username']) ? '(' . $operation['username'] . ')' : ''));
          $operationLabel = '#' . (int) $operation['id'] . ' - ' . $operationType . ' - R$ ' . number_format((float) $operation['total_value'], 2, ',', '.');
          if (!empty($operation['category_name']))
            {
            $operationLabel .= ' - ' . $operation['category_name'];
            }
          if ($userLabel !== '')
            {
            $operationLabel .= ' - ' . $userLabel;
            }
          ?>
          <option value="<?= (int) $operation['id'] ?>"><?= htmlspecialchars($operationLabel, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="type">Tipo</label>
      <select class="form-select" id="type" name="type" required>
        <option value="">Selecione</option>
        <?php foreach ($types as $value => $label): ?>
          <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="value">Valor</label>
      <input autocomplete="off" class="form-control" id="value" name="value" min="0" step="0.01" type="number" required>
    </div>

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="payment_date">Data de pagamento</label>
      <input autocomplete="off" class="form-control" id="payment_date" name="payment_date" type="datetime-local">
    </div>

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="due_date">Data de vencimento</label>
      <input autocomplete="off" class="form-control" id="due_date" name="due_date" type="date" required>
    </div>

  </div>
  <br>
  <div class="row">
    <div class="col-lg-12">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button class="btn btn-primary" type="submit">Salvar</button>
      </div>
    </div>
  </div>
</form>
