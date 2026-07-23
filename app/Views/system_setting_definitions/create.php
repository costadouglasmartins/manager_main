<?php
$definition = [
	'module_id' => '',
	'key_name' => '',
	'label' => '',
	'description' => '',
	'value_type' => 'string',
	'default_value' => '',
	'validation_rules' => '',
	'active' => 1,
	'sort_order' => 0,
];
$actionUrl = url('system_setting_definition/store');
$pageTitle = 'Nova definição de configuração';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('system_setting_definition'); ?>">Definições de configurações</a></li>
    <li class="breadcrumb-item active" aria-current="page">Criar</li>
  </ol>
</nav>

<?php require __DIR__ . '/form.php'; ?>
