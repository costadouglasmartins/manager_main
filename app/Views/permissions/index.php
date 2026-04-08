<?php
// Expected variables:
// $permissions, $selectedUserId, $selectedUser, $userPermissionIds, $effectiveAbilities

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
    <button class="btn btn-primary" type="submit">
      Sincronizar permissões
    </button>
  </form>

  <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#addPermCatalog" aria-expanded="false" aria-controls="addPermCatalog">
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

<!-- User autocomplete search -->
<div class="mb-3">
  <small class="text-muted d-block mb-2">Busque e selecione um usuário para editar suas permissões.</small>
  <div class="position-relative" id="userSearchWrapper">
    <input
      type="text"
      id="userSearchInput"
      class="form-control"
      placeholder="Digite o nome, e-mail ou username do usuário..."
      autocomplete="off"
    >
    <div id="userSearchResults" class="list-group position-absolute w-100 shadow-sm" style="z-index:1050; max-height:300px; overflow-y:auto; display:none;"></div>
  </div>
</div>

<!-- Selected user card -->
<?php if ($selectedUser): ?>
  <div class="card mb-3 border-primary">
    <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
      <div>
        <span class="fw-semibold"><?= htmlspecialchars((string) ($selectedUser['name'] ?? $selectedUser['username'])); ?></span>
        <span class="badge text-bg-info ms-2"><?= htmlspecialchars((string) $selectedUser['type']); ?></span>
        <br>
        <small class="text-muted"><?= htmlspecialchars((string) $selectedUser['email']); ?></small>
        <?php if (!empty($selectedUser['username']) && ($selectedUser['username'] !== ($selectedUser['name'] ?? ''))): ?>
          <small class="text-muted ms-2">(<?= htmlspecialchars($selectedUser['username']); ?>)</small>
        <?php endif; ?>
      </div>
      <a href="<?= url('permission'); ?>" class="btn btn-sm btn-outline-secondary" title="Limpar seleção">&times;</a>
    </div>
  </div>
<?php endif; ?>

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
                <th class="col-1">Permissão</th>
                <th class="col-4">Funcionalidade acesso</th>
                <th class="col-7">Descrição</th>
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
      <a class="btn btn-secondary" href="<?= url('permission?user_id=' . (int) $selectedUserId); ?>">
        Recarregar
      </a>
      <div class="ms-auto">
        <button class="btn btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#effectivePerms" aria-expanded="false" aria-controls="effectivePerms">
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

<script>
(function() {
  var input       = document.getElementById('userSearchInput');
  var resultsDiv  = document.getElementById('userSearchResults');
  var debounceTimer = null;
  var baseUrl     = '<?= url("permission/searchUsers"); ?>';
  var permUrl     = '<?= url("permission"); ?>';

  input.addEventListener('input', function() {
    clearTimeout(debounceTimer);
    var q = input.value.trim();

    if (q.length < 2) {
      resultsDiv.style.display = 'none';
      resultsDiv.innerHTML = '';
      return;
    }

    debounceTimer = setTimeout(function() {
      fetch(baseUrl + '?q=' + encodeURIComponent(q), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function(r) { return r.json(); })
      .then(function(users) {
        resultsDiv.innerHTML = '';

        if (users.length === 0) {
          resultsDiv.innerHTML = '<div class="list-group-item text-muted">Nenhum usuário encontrado.</div>';
          resultsDiv.style.display = 'block';
          return;
        }

        users.forEach(function(u) {
          var item = document.createElement('a');
          item.href = permUrl + '?user_id=' + u.id;
          item.className = 'list-group-item list-group-item-action py-2';

          var nameSpan = document.createElement('span');
          nameSpan.className = 'fw-semibold';
          nameSpan.textContent = u.name || u.username;

          var badge = document.createElement('span');
          badge.className = 'badge text-bg-info ms-2';
          badge.textContent = u.type;

          var emailDiv = document.createElement('div');
          emailDiv.className = 'small text-muted';
          emailDiv.textContent = u.email;

          if (u.username && u.username !== (u.name || '')) {
            emailDiv.textContent += ' (' + u.username + ')';
          }

          item.appendChild(nameSpan);
          item.appendChild(badge);
          item.appendChild(emailDiv);
          resultsDiv.appendChild(item);
        });

        resultsDiv.style.display = 'block';
      })
      .catch(function() {
        resultsDiv.style.display = 'none';
      });
    }, 300);
  });

  // Close results when clicking outside
  document.addEventListener('click', function(e) {
    if (!document.getElementById('userSearchWrapper').contains(e.target)) {
      resultsDiv.style.display = 'none';
    }
  });

  // Focus: reopen if has results
  input.addEventListener('focus', function() {
    if (resultsDiv.innerHTML.trim() !== '') {
      resultsDiv.style.display = 'block';
    }
  });
})();
</script>
