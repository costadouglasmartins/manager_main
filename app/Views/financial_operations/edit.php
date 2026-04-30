<?php
$categories = $categories ?? [];
$types = $types ?? [];
$paymentTypes = $paymentTypes ?? [];
$sessionUser = $sessionUser ?? [];

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

<link rel="stylesheet" href="<?= url('assets/css/financial_operations/edit.css?v=1'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation'); ?>">Operações financeiras</a></li>
    <li class="breadcrumb-item active" aria-current="page">Operação financeira #<?= (int) $financial_operation['id']; ?> - editar</li>
  </ol>
</nav>

<h5>Editar operação financeira</h5>
<form action="<?= url('financial_operation/update/' . $financial_operation['id']); ?>" method="POST" id="financialOperationEditForm">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="session_user">Usuário</label>
      <input class="form-control" id="session_user" type="text" value="<?= htmlspecialchars($sessionUserLabel, ENT_QUOTES, 'UTF-8') ?>" disabled>
    </div>

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="financial_operation_category_id">Categoria</label>
      <select class="form-select" id="financial_operation_category_id" name="financial_operation_category_id" required>
        <option value="">Selecione</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['id'] ?>" <?= (int) $financial_operation['financial_operation_category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="type">Tipo</label>
      <select class="form-select" id="type" name="type" required>
        <option value="">Selecione</option>
        <?php foreach ($types as $value => $label): ?>
          <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $financial_operation['type'] === $value ? 'selected' : '' ?>>
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="total_value">Valor total</label>
      <input autocomplete="off" class="form-control" id="total_value" name="total_value" min="0" step="0.01" type="number" value="<?= number_format((float) $financial_operation['total_value'], 2, '.', '') ?>" required>
    </div>

  </div>

  <div class="financial-operation-recurrence">
    <div class="financial-operation-recurrence__header">
      <div>
        <span>Recorrência mensal</span>
        <strong>Recriar parcelas por faixa de datas</strong>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" id="generate_installments" name="generate_installments" type="checkbox" value="1">
        <label class="form-check-label" for="generate_installments">Recriar parcelas</label>
      </div>
    </div>

    <div class="alert alert-warning py-2 mb-3 d-none" id="recurrenceWarning" role="alert">
      Ao salvar, os pagamentos atuais desta operação serão substituídos pela nova recorrência.
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
        <input autocomplete="off" class="form-control" id="recurrence_start_date" name="recurrence_start_date" type="date" data-recurrence-field disabled>
      </div>

      <div class="mb-2 col-md-6 col-lg-3">
        <label class="form-label" for="recurrence_end_date">Data final</label>
        <input autocomplete="off" class="form-control" id="recurrence_end_date" name="recurrence_end_date" type="date" data-recurrence-field disabled>
      </div>
    </div>

    <div class="financial-operation-recurrence__preview" id="recurrencePreview">
      <i class="bi bi-calendar-check"></i>
      <span>Ative para substituir os pagamentos por uma nova recorrência mensal.</span>
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

<script src="<?= url('assets/js/financial_operations/edit.js?v=1'); ?>"></script>
