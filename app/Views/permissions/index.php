<?php
// Expected variables:
// $permissions, $users, $selectedUserId, $userPermissionIds, $effectiveAbilities

function permGroup(string $ability): string
	{
	$pos = strpos($ability, '.');
	if ($pos === false)
		{
		return 'outros';
		}
	return substr($ability, 0, $pos);
	}

$groups = [];
foreach ($permissions as $p)
	{
	$ability = (string) $p['ability'];
	$group = permGroup($ability);
	if (!isset($groups[$group]))
		{
		$groups[$group] = [];
		}
	$groups[$group][] = $p;
	}
ksort($groups);

$syncCount = null;
if (isset($_GET['synced']))
	{
	$syncCount = (int) $_GET['synced'];
	}

$created = null;
if (isset($_GET['created']))
	{
	$created = (int) $_GET['created'];
	}
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Permissões</li>
  </ol>
</nav>

<h5 class="mb-3">Gerenciar permissões (por usuário)</h5>

<?php if ($syncCount !== null): ?>
  <div class="alert <?= ($syncCount > 0 ? 'alert-success' : 'alert-warning'); ?> py-2">
    <?= ($syncCount > 0 ? ('Sincronização concluída: ' . (int) $syncCount . ' permissões adicionadas ao catálogo.') : 'Sincronização concluída: nenhuma permissão nova foi encontrada.'); ?>
  </div>
<?php endif; ?>

<?php if ($created !== null): ?>
  <div class="alert <?= ($created === 1 ? 'alert-success' : 'alert-danger'); ?> py-2">
    <?= ($created === 1 ? 'Permissão adicionada ao catálogo.' : 'Não foi possível adicionar (já existe ou ability inválida).'); ?>
  </div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <form method="POST" action="<?= url('permission/sync'); ?>" class="d-inline">
    <input type="hidden" name="selected_user_id" value="<?= (int) ($selectedUserId ?? 0); ?>">
    <button class="btn btn-outline-primary btn-sm" type="submit">
      Sincronizar permissões automaticamente
    </button>
  </form>

  <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addPermCatalog" aria-expanded="false" aria-controls="addPermCatalog">
    Adicionar permissão manualmente
  </button>
</div>

<div class="collapse mb-4" id="addPermCatalog">
  <div class="border rounded p-3">
    <form method="POST" action="<?= url('permission/create'); ?>" class="row g-2 align-items-end">
      <input type="hidden" name="selected_user_id" value="<?= (int) ($selectedUserId ?? 0); ?>">
      <div class="col-12 col-md-4">
        <label class="form-label">Nova ability</label>
        <input class="form-control form-control-sm" name="ability" placeholder="ex.: report.index" required>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">Descrição</label>
        <input class="form-control form-control-sm" name="description" placeholder="Opcional">
      </div>
      <div class="col-12 col-md-2">
        <button class="btn btn-sm btn-primary w-100" type="submit">Adicionar</button>
      </div>
      <div class="col-12">
        <small class="text-muted">
          Dica: depois de criar a ability, conceda-a ao usuário abaixo para efetivar o acesso.
        </small>
      </div>
    </form>
  </div>
</div>

<div class="alert alert-secondary py-2">
  <div class="small mb-0">
    As permissões são atribuídas <strong>somente por usuário</strong>. Não há perfis/grupos.
  </div>
</div>

<form method="GET" action="<?= url('permission'); ?>" class="row g-2 align-items-end mb-3">
  <div class="col-12 col-md-8 col-lg-6">
    <label class="form-label">Usuário</label>
    <select name="user_id" class="form-select" onchange="this.form.submit()">
      <option value="0">Selecione um usuário...</option>
      <?php foreach ($users as $u): ?>
        <?php
          $uid = (int) $u['id'];
          $label = trim((string) $u['username']);
          if (!empty($u['email']))
            {
            $label .= ' (' . $u['email'] . ')';
            }
          if (!empty($u['type']))
            {
            $label .= ' [' . $u['type'] . ']';
            }
        ?>
        <option value="<?= $uid; ?>" <?= ($uid === (int) $selectedUserId ? 'selected' : ''); ?>><?= htmlspecialchars($label); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-12 col-md-4 col-lg-6 text-md-end">
    <small class="text-muted">Marque/desmarque as abilities para o usuário selecionado.</small>
  </div>
</form>

<?php if ((int) $selectedUserId <= 0): ?>
  <div class="alert alert-info mb-0">Selecione um usuário para editar permissões.</div>
<?php else: ?>

  <form method="POST" action="<?= url('permission/saveUser'); ?>">
    <input type="hidden" name="user_id" value="<?= (int) $selectedUserId; ?>">

    <?php foreach ($groups as $groupName => $items): ?>
      <div class="mb-3">
        <div class="fw-semibold mb-2 text-capitalize"><?= htmlspecialchars($groupName); ?></div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead>
              <tr>
                <th style="width: 90px;">Ativo</th>
                <th>Ability</th>
                <th>Descrição</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $p): ?>
                <?php
                  $pid = (int) $p['id'];
                  $checked = in_array($pid, $userPermissionIds, true);
                ?>
                <tr>
                  <td class="text-center">
                    <input class="form-check-input" type="checkbox" name="permission_ids[]" value="<?= $pid; ?>" <?= ($checked ? 'checked' : ''); ?>>
                  </td>
                  <td><code><?= htmlspecialchars($p['ability']); ?></code></td>
                  <td><?= htmlspecialchars((string) ($p['description'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="d-flex flex-wrap gap-2 align-items-center">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-save me-1"></i> Salvar permissões
      </button>
      <a class="btn btn-outline-secondary" href="<?= url('permission?user_id=' . (int) $selectedUserId); ?>">
        Recarregar
      </a>
      <div class="ms-auto">
        <button class="btn btn-outline-dark" type="button" data-bs-toggle="collapse" data-bs-target="#effectivePerms" aria-expanded="false" aria-controls="effectivePerms">
          Ver permissões ativas
        </button>
      </div>
    </div>

    <div class="collapse mt-3" id="effectivePerms">
      <div class="border rounded p-3">
        <div class="fw-semibold mb-2">Permissões ativas do usuário</div>
        <?php if (empty($effectiveAbilities)): ?>
          <div class="text-muted">Nenhuma permissão ativa encontrada.</div>
        <?php else: ?>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($effectiveAbilities as $ab): ?>
              <span class="badge text-bg-secondary"><code class="text-white"><?= htmlspecialchars($ab); ?></code></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </form>

<?php endif; ?>
