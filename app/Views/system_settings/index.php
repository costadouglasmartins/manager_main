<?php
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$selectedTenantId = (int) ($tenantId ?? 0);
$tenantLabel = static function (array $tenant): string {
	$name = trim((string) ($tenant['associated_person_name'] ?? ''));
	$database = trim((string) ($tenant['database_number'] ?? ''));
	/** Display the representative user's type when a user is linked to the tenant. **/
	$userType = trim((string) ($tenant['associated_user_type'] ?? ''));
	if ($name === '')
		{
		$name = 'Tenant #' . (int) ($tenant['id'] ?? 0);
		}
	/** Preserve the tenant name and database number before appending the user type. **/
	$label = $database !== '' ? $name . ' - ' . $database : $name;
	return $userType !== '' ? $label . ' - ' . $userType : $label;
};
$settingValue = static function (array $definition, array $values): string {
	$definitionId = (int) ($definition['id'] ?? 0);
	if (array_key_exists($definitionId, $values))
		{
		return (string) $values[$definitionId];
		}
	return (string) ($definition['default_value'] ?? '');
};
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page">System settings</li>
  </ol>
</nav>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
  <div><p class="small fw-semibold text-primary text-uppercase mb-1">System</p><h1 class="h3 fw-bold mb-1">System settings</h1><p class="text-body-secondary mb-0">Manage tenant overrides grouped by module.</p></div>
</div>

<form action="<?= url('system_setting'); ?>" class="index-search-card mb-4" method="get">
  <div class="row g-3 align-items-end">
    <div class="col-12 col-lg-9">
      <label class="form-label" for="tenant_id">Tenant</label>
      <select class="form-select" id="tenant_id" name="tenant_id">
        <?php foreach ($tenants as $tenant): ?>
          <option value="<?= (int) $tenant['id']; ?>" <?= (int) $tenant['id'] === $selectedTenantId ? 'selected' : ''; ?>><?= $e($tenantLabel($tenant)); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-lg-3 index-search-actions">
      <button class="btn btn-primary btn-sm index-search-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Load</span></button>
    </div>
  </div>
</form>

<?php if ($selectedTenantId <= 0): ?>
  <div class="alert alert-warning">No tenant available.</div>
<?php else: ?>
  <form action="<?= url('system_setting/update'); ?>" method="post">
    <input type="hidden" name="tenant_id" value="<?= $selectedTenantId; ?>">
    <?php if (empty($modules)): ?>
      <div class="alert alert-info">No active setting definitions were found.</div>
    <?php endif; ?>
    <?php foreach ($modules as $module): ?>
      <section class="mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
          <div><h2 class="h5 mb-1"><?= $e($module['name'] ?? 'Module'); ?></h2><?php if (!empty($module['description'])): ?><p class="text-body-secondary mb-0"><?= $e($module['description']); ?></p><?php endif; ?></div>
          <span class="badge text-bg-secondary align-self-start"><?= $e($module['slug'] ?? 'module'); ?></span>
        </div>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead>
              <tr>
                <th scope="col">Setting</th>
                <th scope="col">Default</th>
                <th scope="col">Tenant value</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($module['definitions'] as $definition): ?>
                <?php $definitionId = (int) ($definition['id'] ?? 0); ?>
                <?php $inputId = 'setting_' . $definitionId; ?>
                <?php $value = $settingValue($definition, $values); ?>
                <?php $type = (string) ($definition['value_type'] ?? 'string'); ?>
                <?php $rules = is_array($definition['validation_rules'] ?? null) ? $definition['validation_rules'] : []; ?>
                <tr>
                  <td><label class="fw-semibold" for="<?= $e($inputId); ?>"><?= $e($definition['label'] ?? $definition['key_name'] ?? 'Setting'); ?></label><?php if (!empty($definition['description'])): ?><div class="small text-body-secondary"><?= $e($definition['description']); ?></div><?php endif; ?><div class="small text-muted font-monospace"><?= $e($definition['key_name'] ?? ''); ?></div></td>
                  <td><span class="badge text-bg-light border text-body"><?= $e($definition['default_value'] ?? ''); ?></span></td>
                  <td>
                    <?php if ($type === 'integer'): ?>
                      <input class="form-control" id="<?= $e($inputId); ?>" name="settings[<?= $definitionId; ?>]" type="number" step="1" <?php if (isset($rules['min'])): ?>min="<?= (int) $rules['min']; ?>" <?php endif; ?><?php if (isset($rules['max'])): ?>max="<?= (int) $rules['max']; ?>" <?php endif; ?>value="<?= $e($value); ?>">
                    <?php elseif ($type === 'boolean'): ?>
                      <select class="form-select" id="<?= $e($inputId); ?>" name="settings[<?= $definitionId; ?>]"><option value="0" <?= $value === '0' ? 'selected' : ''; ?>>Disabled</option><option value="1" <?= $value === '1' ? 'selected' : ''; ?>>Enabled</option></select>
                    <?php else: ?>
                      <input class="form-control" id="<?= $e($inputId); ?>" name="settings[<?= $definitionId; ?>]" type="text" value="<?= $e($value); ?>">
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>
    <div class="d-flex justify-content-end gap-2 mb-4">
      <a class="btn btn-outline-secondary" href="<?= url('tenant'); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1" aria-hidden="true"></i>Save settings</button>
    </div>
  </form>
<?php endif; ?>
