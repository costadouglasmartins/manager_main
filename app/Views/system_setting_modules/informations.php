<?php
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('system_setting_module'); ?>">Módulos de configurações</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre <?= $e($module['name'] ?? 'módulo'); ?></li>
  </ol>
</nav>

<header class="app-detail-heading">
  <span class="app-detail-heading-icon"><i class="bi bi-collection"></i></span>
  <div class="app-detail-heading-content">
    <span class="app-detail-eyebrow">Detalhes do catálogo</span>
    <h1>Dados do módulo</h1>
  </div>
</header>

<div class="table-responsive app-detail-table">
  <table class="table table-hover align-middle mb-0">
    <tbody>
      <tr>
        <th class="text-muted">Código</th>
        <td>#<?= (int) ($module['id'] ?? 0); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Nome</th>
        <td><?= $e($module['name'] ?? ''); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Slug</th>
        <td><code class="system-setting-catalog-code"><?= $e($module['slug'] ?? ''); ?></code></td>
      </tr>
      <tr>
        <th class="text-muted">Descrição</th>
        <td><?= $e($module['description'] ?? ''); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Status</th>
        <td><?= (int) ($module['active'] ?? 0) === 1 ? 'Ativo' : 'Inativo'; ?></td>
      </tr>
      <tr>
        <th class="text-muted">Ordem</th>
        <td><?= (int) ($module['sort_order'] ?? 0); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Definições vinculadas</th>
        <td><?= (int) ($module['definitions_count'] ?? 0); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Criado em</th>
        <td><?= $e($module['created'] ?? ''); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Modificado em</th>
        <td><?= $e($module['modified'] ?? ''); ?></td>
      </tr>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-end mt-3">
  <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="<?= url('system_setting_module'); ?>">
    <i class="bi bi-arrow-left" aria-hidden="true"></i>
    <span>Voltar</span>
  </a>
</div>
