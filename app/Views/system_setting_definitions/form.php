<?php
$e = $e ?? static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$definition = is_array($definition ?? null) ? $definition : [];
$modules = is_array($modules ?? null) ? $modules : [];
$valueTypes = is_array($valueTypes ?? null) ? $valueTypes : [];
$pageTitle = $pageTitle ?? 'Definição de configuração';
$actionUrl = $actionUrl ?? url('system_setting_definition/store');
?>

<header class="d-flex align-items-center gap-3 mb-4">
  <span class="system-setting-catalog-icon d-inline-flex align-items-center justify-content-center rounded-3 p-3" aria-hidden="true">
    <i class="bi bi-sliders2 fs-4"></i>
  </span>
  <div>
    <p class="small fw-semibold text-primary text-uppercase mb-1">System settings</p>
    <h1 class="h3 fw-bold mb-1"><?= $e($pageTitle); ?></h1>
    <p class="text-body-secondary mb-0">Defina uma chave, seu tipo, valor padrão e regras opcionais de validação.</p>
  </div>
</header>

<?php if (empty($modules)): ?>
  <div class="alert alert-warning">Cadastre um módulo ativo antes de criar definições de configuração.</div>
<?php endif; ?>

<form action="<?= $e($actionUrl); ?>" class="card system-setting-catalog-card shadow-sm p-3 p-lg-4" method="POST">
  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <label class="form-label" for="module_id">Módulo</label>
      <select class="form-select" id="module_id" name="module_id" required>
        <option value="">Selecione</option>
        <?php foreach ($modules as $module): ?>
          <option value="<?= (int) $module['id']; ?>" <?= (int) ($definition['module_id'] ?? 0) === (int) $module['id'] ? 'selected' : ''; ?>>
            <?= $e($module['name'] ?? ''); ?><?= (int) ($module['active'] ?? 1) === 0 ? ' (inativo)' : ''; ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label" for="key_name">Chave</label>
      <input autocomplete="off" class="form-control font-monospace" id="key_name" maxlength="120" name="key_name" pattern="[a-z0-9_]+" placeholder="max_online_users" required type="text" value="<?= $e($definition['key_name'] ?? ''); ?>">
      <div class="form-text">Use letras minúsculas, números e underline.</div>
    </div>

    <div class="col-12 col-lg-3">
      <label class="form-label" for="value_type">Tipo</label>
      <select class="form-select" id="value_type" name="value_type" required>
        <?php foreach ($valueTypes as $type): ?>
          <option value="<?= $e($type); ?>" <?= (string) ($definition['value_type'] ?? 'string') === (string) $type ? 'selected' : ''; ?>><?= $e($type); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-lg-8">
      <label class="form-label" for="label">Rótulo</label>
      <input autocomplete="off" class="form-control" id="label" maxlength="160" name="label" placeholder="Max online users" required type="text" value="<?= $e($definition['label'] ?? ''); ?>">
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label" for="default_value">Valor padrão</label>
      <input autocomplete="off" class="form-control" id="default_value" name="default_value" type="text" value="<?= $e($definition['default_value'] ?? ''); ?>">
    </div>

    <div class="col-12">
      <label class="form-label" for="description">Descrição</label>
      <input autocomplete="off" class="form-control" id="description" maxlength="255" name="description" placeholder="Maximum number of users allowed online at the same time." type="text" value="<?= $e($definition['description'] ?? ''); ?>">
    </div>

    <div class="col-12">
      <label class="form-label" for="validation_rules">Regras de validação</label>
      <textarea class="form-control font-monospace" id="validation_rules" name="validation_rules" placeholder='{"min":1,"max":999}' rows="4"><?= $e($definition['validation_rules'] ?? ''); ?></textarea>
      <div class="form-text">Informe um objeto JSON válido. Deixe em branco quando não houver regras.</div>
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <label class="form-label" for="sort_order">Ordem</label>
      <input class="form-control" id="sort_order" name="sort_order" type="number" value="<?= (int) ($definition['sort_order'] ?? 0); ?>">
    </div>

    <div class="col-12 col-md-6 col-lg-3 d-flex align-items-end">
      <div class="form-check form-switch">
        <input class="form-check-input" id="active" name="active" type="checkbox" value="1" <?= (int) ($definition['active'] ?? 1) === 1 ? 'checked' : ''; ?>>
        <label class="form-check-label" for="active">Ativa</label>
      </div>
    </div>
  </div>

  <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 border-top pt-3 mt-4">
    <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="<?= url('system_setting_definition'); ?>">
      <i class="bi bi-arrow-left" aria-hidden="true"></i>
      <span>Voltar</span>
    </a>
    <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit" <?= empty($modules) ? 'disabled' : ''; ?>>
      <i class="bi bi-save" aria-hidden="true"></i>
      <span>Salvar</span>
    </button>
  </div>
</form>
