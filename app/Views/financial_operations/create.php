<?php
$categories = $categories ?? [];
$types = $types ?? [];
$users = $users ?? [];
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation'); ?>">Operações financeiras</a></li>
    <li class="breadcrumb-item active" aria-current="page">Operação financeira - criar</li>
  </ol>
</nav>

<h5>Criar operação financeira</h5>
<form action="<?= url('financial_operation/store/'); ?>" method="POST">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="user_id">Usuário</label>
      <select class="form-select" id="user_id" name="user_id" required>
        <option value="">Selecione</option>
        <?php foreach ($users as $user): ?>
          <?php
          $userLabel = trim(($user['person_name'] ?? '') . ' ' . (!empty($user['username']) ? '(' . $user['username'] . ')' : ''));
          if ($userLabel === '')
            {
            $userLabel = 'Usuário #' . (int) $user['id'];
            }
          ?>
          <option value="<?= (int) $user['id'] ?>"><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-2 col-md-6 col-lg-6">
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

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="type">Tipo</label>
      <select class="form-select" id="type" name="type" required>
        <option value="">Selecione</option>
        <?php foreach ($types as $value => $label): ?>
          <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="total_value">Valor total</label>
      <input autocomplete="off" class="form-control" id="total_value" name="total_value" min="0" step="0.01" type="number" required>
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
