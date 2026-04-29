<?php
$types = $types ?? [];
$userLabel = trim(($financial_operation['user_name'] ?? '') . ' ' . (!empty($financial_operation['username']) ? '(' . $financial_operation['username'] . ')' : ''));
$created = !empty($financial_operation['created']) ? date('d/m/Y H:i', strtotime($financial_operation['created'])) : '';
$modified = !empty($financial_operation['modified']) ? date('d/m/Y H:i', strtotime($financial_operation['modified'])) : '';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('financial_operation'); ?>">Operações financeiras</a></li>
    <li class="breadcrumb-item active" aria-current="page">Operação financeira #<?= (int) $financial_operation['id']; ?></li>
  </ol>
</nav>

<h5>Dados da operação financeira</h5>
<div class="table-responsive mb-3">
  <table class="table table-hover align-middle">
    <tbody>
      <tr>
        <th class="text-muted" style="width: 220px;">Código</th>
        <td><?= (int) $financial_operation['id']; ?></td>
      </tr>
      <tr>
        <th class="text-muted">Usuário</th>
        <td><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Categoria</th>
        <td><?= htmlspecialchars($financial_operation['category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Tipo</th>
        <td><?= htmlspecialchars($types[$financial_operation['type']] ?? $financial_operation['type'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Valor total</th>
        <td>R$ <?= number_format((float) $financial_operation['total_value'], 2, ',', '.'); ?></td>
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
