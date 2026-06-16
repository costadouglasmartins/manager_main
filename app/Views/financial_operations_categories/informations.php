<?php
$created = !empty($financial_operation_category['created']) ? date('d/m/Y H:i', strtotime($financial_operation_category['created'])) : '';
$modified = !empty($financial_operation_category['modified']) ? date('d/m/Y H:i', strtotime($financial_operation_category['modified'])) : '';
$financial_operation_subcategories = $financial_operation_subcategories ?? [];
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation_category'); ?>">Categorias financeiras</a></li>
    <li class="breadcrumb-item active" aria-current="page">Categoria financeira #<?= (int) $financial_operation_category['id']; ?></li>
  </ol>
</nav>

<h5>Dados da categoria financeira</h5>
<div class="table-responsive mb-3">
  <table class="table table-hover align-middle">
    <tbody>
      <tr>
        <th class="text-muted" style="width: 220px;">Código</th>
        <td><?= (int) $financial_operation_category['id']; ?></td>
      </tr>
      <tr>
        <th class="text-muted">Nome</th>
        <td><?= htmlspecialchars($financial_operation_category['name'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Descrição</th>
        <td><?= htmlspecialchars($financial_operation_category['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
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

<h5>Subcategorias vinculadas</h5>
<div class="table-responsive">
  <table class="table table-striped table-hover align-middle">
    <thead>
      <tr>
        <th style="width:1%;">#</th>
        <th>Nome</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($financial_operation_subcategories)): ?>
        <?php foreach ($financial_operation_subcategories as $subcategory): ?>
          <tr>
            <td><?= (int) $subcategory['id']; ?></td>
            <td><?= htmlspecialchars($subcategory['name'], ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="2" class="text-center">Nenhuma subcategoria vinculada.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
