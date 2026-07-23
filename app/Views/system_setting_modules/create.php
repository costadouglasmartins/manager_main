<?php
$module = [
	'slug' => '',
	'name' => '',
	'description' => '',
	'active' => 1,
	'sort_order' => 0,
];
$actionUrl = url('system_setting_module/store');
$pageTitle = 'Novo módulo de configurações';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('system_setting_module'); ?>">Módulos de configurações</a></li>
    <li class="breadcrumb-item active" aria-current="page">Criar</li>
  </ol>
</nav>

<?php require __DIR__ . '/form.php'; ?>
