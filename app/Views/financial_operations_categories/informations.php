<?php
$created = !empty($financial_operation_category['created']) ? date('d/m/Y H:i', strtotime($financial_operation_category['created'])) : '';
$modified = !empty($financial_operation_category['modified']) ? date('d/m/Y H:i', strtotime($financial_operation_category['modified'])) : '';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation_category'); ?>">Categorias financeiras</a></li>
    <li class="breadcrumb-item active" aria-current="page">Categoria financeira #<?= (int) $financial_operation_category['id']; ?></li>
  </ol>
</nav>

<header class="app-detail-heading">
  <span class="app-detail-heading-icon"><i class="bi bi-tags"></i></span>
  <div class="app-detail-heading-content">
    <span class="app-detail-eyebrow">Detalhes do cadastro</span>
    <h1>Dados da categoria financeira</h1>
  </div>
</header>
<div class="table-responsive app-detail-table">
  <table class="table table-hover align-middle mb-0">
    <tbody>
      <tr>
        <th>Código</th>
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
