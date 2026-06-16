<?php
$created = !empty($financial_operation_subcategory['created']) ? date('d/m/Y H:i', strtotime($financial_operation_subcategory['created'])) : '';
$modified = !empty($financial_operation_subcategory['modified']) ? date('d/m/Y H:i', strtotime($financial_operation_subcategory['modified'])) : '';
$categoryLabel = trim((string) ($financial_operation_subcategory['category_name'] ?? ''));
if (!empty($financial_operation_subcategory['category_description']))
  {
  $categoryLabel .= ' - ' . trim((string) $financial_operation_subcategory['category_description']);
  }
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation_subcategory'); ?>">Subcategorias financeiras</a></li>
    <li class="breadcrumb-item active" aria-current="page">Subcategoria financeira #<?= (int) $financial_operation_subcategory['id']; ?></li>
  </ol>
</nav>

<h5>Dados da subcategoria financeira</h5>
<div class="table-responsive mb-3">
  <table class="table table-hover align-middle">
    <tbody>
      <tr>
        <th class="text-muted" style="width: 220px;">Código</th>
        <td><?= (int) $financial_operation_subcategory['id']; ?></td>
      </tr>
      <tr>
        <th class="text-muted">Nome</th>
        <td><?= htmlspecialchars($financial_operation_subcategory['name'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Categoria</th>
        <td>
          #<?= (int) $financial_operation_subcategory['financial_operation_category_id']; ?>
          <?= htmlspecialchars($categoryLabel !== '' ? ' - ' . $categoryLabel : '', ENT_QUOTES, 'UTF-8'); ?>
        </td>
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
