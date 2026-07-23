<?php
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$actionUrl = url('system_setting_module/update/' . (int) ($module['id'] ?? 0));
$pageTitle = 'Editar módulo de configurações';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('system_setting_module'); ?>">Módulos de configurações</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= $e($module['name'] ?? 'Módulo'); ?> - editar</li>
  </ol>
</nav>

<?php require __DIR__ . '/form.php'; ?>
