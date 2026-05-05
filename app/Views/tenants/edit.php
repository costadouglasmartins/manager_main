<?php
$tenantUsers    = $tenantUsers    ?? [];
$availableUsers = $availableUsers ?? [];
$flash          = $flash          ?? null;
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Inicio</a></li>
    <li class="breadcrumb-item"><a href="<?= url('tenant'); ?>">Tenants</a></li>
    <li class="breadcrumb-item active" aria-current="page">Editar tenant</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Editar tenant #<?= $tenant['id'] ?></h5>

<?php if (!empty($flash)): ?>
  <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
  </div>
<?php endif; ?>

<form action="<?= url('tenant/update/' . $tenant['id']); ?>" method="POST" class="needs-validation" novalidate>
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <label class="form-label">Pessoa associada</label>
          <input class="form-control" type="text" value="<?= htmlspecialchars($tenant['associated_person_name'] ?? ('Tenant #' . (int) $tenant['id']), ENT_QUOTES, 'UTF-8') ?>" disabled />
          <div class="form-text">Definida pelo vínculo em <code>tenant_users</code>, priorizando o perfil Owner.</div>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Banco de dados</label>
          <input class="form-control" type="text" value="manager_<?= htmlspecialchars($tenant['database_number']) ?>" disabled />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="status">Situacao</label>
          <select autocomplete="off" class="form-select" id="status" name="status">
            <option value="active" <?= ($tenant['status'] === 'active' ? 'selected' : '') ?>>Ativo</option>
            <option value="suspended" <?= ($tenant['status'] === 'suspended' ? 'selected' : '') ?>>Suspenso</option>
            <option value="pending" <?= ($tenant['status'] === 'pending' ? 'selected' : '') ?>>Pendente</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label" for="observations">Observacoes</label>
          <textarea class="form-control" id="observations" name="observations" rows="3"><?= htmlspecialchars($tenant['observations'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
      </div>
    </div>
    <div class="card-footer bg-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <a href="<?= url('tenant'); ?>" class="btn btn-secondary">Voltar</a>
      <button type="submit" class="btn btn-primary">Salvar alteracoes</button>
    </div>
  </div>
</form>

<!-- Usuarios vinculados a este tenant (mesma base de dados manager_<?= htmlspecialchars($tenant['database_number']) ?>) -->
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <strong>Usuarios com acesso a este tenant</strong>
      <small class="text-muted d-block">Base de dados: <code>manager_<?= htmlspecialchars($tenant['database_number']) ?></code></small>
    </div>
    <span class="badge text-bg-primary"><?= count($tenantUsers) ?> usuario(s)</span>
  </div>

  <div class="card-body">

    <!-- Formulario: adicionar usuario existente ao tenant -->
    <?php if (authCan('tenant.addUser')): ?>
      <?php if (!empty($availableUsers)): ?>
        <form action="<?= url('tenant/addUser/' . $tenant['id']); ?>" method="POST" class="row g-2 align-items-end mb-3">
          <div class="col-12 col-md-6">
            <label class="form-label" for="user_id">Adicionar usuario existente</label>
            <select class="form-select" id="user_id" name="user_id" required>
              <option value="">Selecione um usuario...</option>
              <?php foreach ($availableUsers as $u): ?>
                <option value="<?= (int) $u['id'] ?>">
                  <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label" for="role">Papel</label>
            <select class="form-select" id="role" name="role">
              <option value="user" selected>Usuario</option>
              <option value="admin">Admin</option>
              <option value="owner">Owner</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label" for="active">Situacao</label>
            <select class="form-select" id="active" name="active">
              <option value="1" selected>Ativo</option>
              <option value="0">Inativo</option>
            </select>
          </div>
          <div class="col-12 col-md-1 d-grid">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
        </form>
      <?php else: ?>
        <div class="alert alert-info mb-3">
          Todos os usuarios ativos ja estao vinculados a este tenant.
          <?php if (authCan('user.create')): ?>
            <a href="<?= url('user/create'); ?>">Criar novo usuario</a>.
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Tabela: usuarios vinculados -->
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead>
          <tr>
            <th style="width: 1%;">#</th>
            <th>Nome</th>
            <th>Usuario</th>
            <th>E-mail</th>
            <th style="width: 1%;">Papel</th>
            <th style="width: 1%;">Situacao</th>
            <th style="width: 1%;" class="text-nowrap text-end">Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($tenantUsers)): ?>
            <?php foreach ($tenantUsers as $tu): ?>
              <tr>
                <td><?= (int) $tu['user_id'] ?></td>
                <td><?= htmlspecialchars($tu['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td><code><?= htmlspecialchars($tu['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= htmlspecialchars($tu['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?php if ($tu['role'] === 'owner'): ?>
                    <span class="badge text-bg-dark">Owner</span>
                  <?php elseif ($tu['role'] === 'admin'): ?>
                    <span class="badge text-bg-primary">Admin</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Usuario</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int) $tu['active'] === 1): ?>
                    <span class="badge text-bg-success">Ativo</span>
                  <?php else: ?>
                    <span class="badge text-bg-warning">Inativo</span>
                  <?php endif; ?>
                </td>
                <td class="text-nowrap text-end">
                  <?php if (authCan('tenant.updateUser')): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#editUserModal"
                            data-user-id="<?= (int) $tu['user_id'] ?>"
                            data-user-name="<?= htmlspecialchars($tu['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-user-role="<?= htmlspecialchars($tu['role'], ENT_QUOTES, 'UTF-8') ?>"
                            data-user-active="<?= (int) $tu['active'] ?>"
                            data-bs-toggle-tooltip="tooltip"
                            title="Editar papel/situacao">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                  <?php endif; ?>
                  <?php if (authCan('tenant.removeUser')): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal"
                            data-bs-target="#removeUserModal"
                            data-user-id="<?= (int) $tu['user_id'] ?>"
                            data-user-name="<?= htmlspecialchars($tu['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            title="Remover do tenant">
                      <i class="bi bi-person-x"></i>
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="text-muted">Nenhum usuario vinculado a este tenant.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: editar role/active do vinculo -->
<?php if (authCan('tenant.updateUser')): ?>
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editUserForm" method="POST" action="#">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Editar vinculo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">Usuario: <strong id="editUserName"></strong></p>
          <div class="mb-3">
            <label class="form-label" for="editRole">Papel</label>
            <select class="form-select" id="editRole" name="role" required>
              <option value="user">Usuario</option>
              <option value="admin">Admin</option>
              <option value="owner">Owner</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editActive">Situacao</label>
            <select class="form-select" id="editActive" name="active">
              <option value="1">Ativo</option>
              <option value="0">Inativo</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Modal: confirmar remocao -->
<?php if (authCan('tenant.removeUser')): ?>
<div class="modal fade" id="removeUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="removeUserForm" method="POST" action="#">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Remover usuario do tenant</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          Tem certeza que deseja remover o acesso de <strong id="removeUserName"></strong> a este tenant?
          <div class="form-text">O usuario nao sera apagado, apenas deixara de ter acesso a esta base de dados.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">Remover</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  var tenantId = <?= (int) $tenant['id'] ?>;

  var editModal = document.getElementById('editUserModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', function (e) {
      var btn    = e.relatedTarget;
      var uid    = btn.getAttribute('data-user-id');
      var uname  = btn.getAttribute('data-user-name');
      var urole  = btn.getAttribute('data-user-role');
      var uact   = btn.getAttribute('data-user-active');

      document.getElementById('editUserName').textContent = uname;
      document.getElementById('editRole').value   = urole;
      document.getElementById('editActive').value = uact;
      document.getElementById('editUserForm').action =
        '<?= url('tenant/updateUser') ?>/' + tenantId + '/' + uid;
    });
  }

  var removeModal = document.getElementById('removeUserModal');
  if (removeModal) {
    removeModal.addEventListener('show.bs.modal', function (e) {
      var btn   = e.relatedTarget;
      var uid   = btn.getAttribute('data-user-id');
      var uname = btn.getAttribute('data-user-name');

      document.getElementById('removeUserName').textContent = uname;
      document.getElementById('removeUserForm').action =
        '<?= url('tenant/removeUser') ?>/' + tenantId + '/' + uid;
    });
  }
})();
</script>
