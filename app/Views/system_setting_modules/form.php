<?php
$e = $e ?? static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$module = is_array($module ?? null) ? $module : [];
$pageTitle = $pageTitle ?? 'Módulo de configurações';
$actionUrl = $actionUrl ?? url('system_setting_module/store');
?>

<header class="d-flex align-items-center gap-3 mb-4">
  <span class="system-setting-catalog-icon d-inline-flex align-items-center justify-content-center rounded-3 p-3" aria-hidden="true">
    <i class="bi bi-collection fs-4"></i>
  </span>
  <div>
    <p class="small fw-semibold text-primary text-uppercase mb-1">System settings</p>
    <h1 class="h3 fw-bold mb-1"><?= $e($pageTitle); ?></h1>
    <p class="text-body-secondary mb-0">Defina o grupo visual usado para organizar chaves de configuração.</p>
  </div>
</header>

<form action="<?= $e($actionUrl); ?>" class="card system-setting-catalog-card shadow-sm p-3 p-lg-4" method="POST">
  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <label class="form-label" for="slug">Slug</label>
      <input autocomplete="off" class="form-control font-monospace" id="slug" maxlength="80" name="slug" pattern="[a-z0-9_]+" placeholder="presence" required type="text" value="<?= $e($module['slug'] ?? ''); ?>">
      <div class="form-text">Use letras minúsculas, números e underline.</div>
    </div>

    <div class="col-12 col-lg-8">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" maxlength="120" name="name" placeholder="Presence" required type="text" value="<?= $e($module['name'] ?? ''); ?>">
    </div>

    <div class="col-12">
      <label class="form-label" for="description">Descrição</label>
      <input autocomplete="off" class="form-control" id="description" maxlength="255" name="description" placeholder="User presence and online limits." type="text" value="<?= $e($module['description'] ?? ''); ?>">
    </div>

    <div class="col-12 col-md-6 col-lg-3">
      <label class="form-label" for="sort_order">Ordem</label>
      <input class="form-control" id="sort_order" name="sort_order" type="number" value="<?= (int) ($module['sort_order'] ?? 0); ?>">
    </div>

    <div class="col-12 col-md-6 col-lg-3 d-flex align-items-end">
      <div class="form-check form-switch">
        <input class="form-check-input" id="active" name="active" type="checkbox" value="1" <?= (int) ($module['active'] ?? 1) === 1 ? 'checked' : ''; ?>>
        <label class="form-check-label" for="active">Ativo</label>
      </div>
    </div>
  </div>

  <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 border-top pt-3 mt-4">
    <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="<?= url('system_setting_module'); ?>">
      <i class="bi bi-arrow-left" aria-hidden="true"></i>
      <span>Voltar</span>
    </a>
    <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit">
      <i class="bi bi-save" aria-hidden="true"></i>
      <span>Salvar</span>
    </button>
  </div>
</form>
