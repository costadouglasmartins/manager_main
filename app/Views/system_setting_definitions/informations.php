<?php
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('system_setting_definition'); ?>">Definições de configurações</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre <?= $e($definition['label'] ?? 'definição'); ?></li>
  </ol>
</nav>

<header class="app-detail-heading">
  <span class="app-detail-heading-icon"><i class="bi bi-sliders2"></i></span>
  <div class="app-detail-heading-content">
    <span class="app-detail-eyebrow">Detalhes do catálogo</span>
    <h1>Dados da definição</h1>
  </div>
</header>

<div class="table-responsive app-detail-table">
  <table class="table table-hover align-middle mb-0">
    <tbody>
      <tr>
        <th class="text-muted">Código</th>
        <td>#<?= (int) ($definition['id'] ?? 0); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Módulo</th>
        <td><?= $e($definition['module_name'] ?? ''); ?> <code class="system-setting-catalog-code"><?= $e($definition['module_slug'] ?? ''); ?></code></td>
      </tr>
      <tr>
        <th class="text-muted">Rótulo</th>
        <td><?= $e($definition['label'] ?? ''); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Chave</th>
        <td><code class="system-setting-catalog-code"><?= $e($definition['key_name'] ?? ''); ?></code></td>
      </tr>
      <tr>
        <th class="text-muted">Descrição</th>
        <td><?= $e($definition['description'] ?? ''); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Tipo</th>
        <td><?= $e($definition['value_type'] ?? ''); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Valor padrão</th>
        <td><code class="system-setting-catalog-code"><?= $e($definition['default_value'] ?? ''); ?></code></td>
      </tr>
      <tr>
        <th class="text-muted">Regras de validação</th>
        <td><pre class="system-setting-catalog-pre mb-0"><code><?= $e($definition['validation_rules'] ?? ''); ?></code></pre></td>
      </tr>
      <tr>
        <th class="text-muted">Status</th>
        <td><?= (int) ($definition['active'] ?? 0) === 1 ? 'Ativa' : 'Inativa'; ?></td>
      </tr>
      <tr>
        <th class="text-muted">Ordem</th>
        <td><?= (int) ($definition['sort_order'] ?? 0); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Valores por tenant</th>
        <td><?= (int) ($definition['tenant_values_count'] ?? 0); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Criada em</th>
        <td><?= $e($definition['created'] ?? ''); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Modificada em</th>
        <td><?= $e($definition['modified'] ?? ''); ?></td>
      </tr>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-end mt-3">
  <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="<?= url('system_setting_definition'); ?>">
    <i class="bi bi-arrow-left" aria-hidden="true"></i>
    <span>Voltar</span>
  </a>
</div>
