<?php
$categories = $categories ?? [];
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation_subcategory'); ?>">Subcategorias financeiras</a></li>
    <li class="breadcrumb-item active" aria-current="page">Subcategoria financeira #<?= (int) $financial_operation_subcategory['id']; ?> - editar</li>
  </ol>
</nav>

<h5>Editar subcategoria financeira</h5>
<form action="<?= url('financial_operation_subcategory/update/' . $financial_operation_subcategory['id']); ?>" method="POST">
  <div class="row">

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
          <option value="<?= (int) $category['id']; ?>" <?= (int) $financial_operation_subcategory['financial_operation_category_id'] === (int) $category['id'] ? 'selected' : ''; ?>>
            <?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" name="name" maxlength="255" type="text" value="<?= htmlspecialchars($financial_operation_subcategory['name'], ENT_QUOTES, 'UTF-8') ?>" required>
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
