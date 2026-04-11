<?php
// Expected variables: $orphaned, $stats, $title

$deleted = null;
if (isset($_GET['deleted'])) {
	$deleted = (int) $_GET['deleted'];
}

$usersAffected = null;
if (isset($_GET['users_affected'])) {
	$usersAffected = (int) $_GET['users_affected'];
}

$error = null;
if (isset($_GET['error'])) {
	$error = (string) $_GET['error'];
}
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('permission'); ?>">Permissões</a></li>
    <li class="breadcrumb-item active" aria-current="page">Permissões Órfãs</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5>Permissões Órfãs</h5>
  <a href="<?= url('permission'); ?>" class="btn btn-secondary btn-sm">Voltar</a>
</div>

<p class="text-muted">
  Estas são permissões que não existem mais no código.
  Você pode removê-las com segurança. Os usuários que as possuem perderão o acesso.
</p>

<?php if ($deleted === 1): ?>
  <div class="alert alert-success py-2">
    <strong>Permissão órfã removida com sucesso!</strong>
    <?php if ($usersAffected !== null && $usersAffected > 0): ?>
      <br><small><?= $usersAffected; ?> usuário(s) teve/tiveram esta permissão removida.</small>
    <?php endif; ?>
  </div>
<?php elseif ($deleted === 0): ?>
  <div class="alert alert-danger py-2">
    <strong>Erro ao remover permissão.</strong>
    <?php if ($error === 'not_orphaned'): ?>
      Esta permissão não é órfã ou não existe.
    <?php elseif ($error === 'exception'): ?>
      Erro ao processar a operação. Tente novamente.
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($stats['total_orphaned'] === 0): ?>
  <div class="alert alert-info">
    Nenhuma permissão órfã encontrada. Todas as permissões no catálogo existem no código.
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-header bg-light">
      <strong><?= $stats['total_orphaned']; ?> permissão(ões) órfã(s)</strong>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Ability</th>
            <th>Descrição</th>
            <th class="text-center">Usuários afetados</th>
            <th class="text-center">Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orphaned as $perm): ?>
            <?php
              $permId = (int) $perm['id'];
              $userCount = $stats['permissions'][$permId]['user_count'] ?? 0;
            ?>
            <tr class="<?= ($userCount > 0 ? 'table-warning' : ''); ?>">
              <td><code><?= htmlspecialchars($perm['ability']); ?></code></td>
              <td><?= htmlspecialchars((string) ($perm['description'] ?? '—')); ?></td>
              <td class="text-center">
                <?php if ($userCount > 0): ?>
                  <span class="badge text-bg-danger"><?= $userCount; ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <button
                  class="btn btn-sm btn-danger"
                  data-bs-toggle="modal"
                  data-bs-target="#deleteModal"
                  data-id="<?= $permId; ?>"
                  data-ability="<?= htmlspecialchars($perm['ability']); ?>"
                  data-user-count="<?= $userCount; ?>"
                  title="Deletar permissão órfã"
                >
                  Remover
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar remoção de permissão órfã</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Tem certeza que deseja remover a permissão?</p>
        <div class="bg-light p-3 rounded mb-3 border">
          <code id="abilityName" class="fw-semibold"></code>
        </div>

        <div id="affectedUsersSection" style="display: none;">
          <div class="alert alert-danger">
            <strong>⚠️ Usuários afetados:</strong>
            <p class="mb-2">Os seguintes <span id="userCountText"></span> usuário(s) perderá/perderão acesso a esta permissão:</p>
            <div id="affectedUsersList" class="text-sm"></div>
          </div>
        </div>

        <div id="noUsersSection" style="display: none;">
          <div class="alert alert-info">
            <small>Nenhum usuário possui esta permissão no momento.</small>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="#" id="deleteForm" style="display: inline;">
          <button type="submit" class="btn btn-danger">Remover Permissão</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function (e) {
  const permId = e.relatedTarget.dataset.id;
  const ability = e.relatedTarget.dataset.ability;
  const userCount = parseInt(e.relatedTarget.dataset.userCount) || 0;

  document.getElementById('abilityName').textContent = ability;
  document.getElementById('deleteForm').action = '<?= url('permission/deleteOrphaned') ?>/' + permId;

  // Mostrar ou esconder seção de usuários afetados
  const affectedSection = document.getElementById('affectedUsersSection');
  const noUsersSection = document.getElementById('noUsersSection');

  if (userCount > 0) {
    affectedSection.style.display = 'block';
    noUsersSection.style.display = 'none';
    document.getElementById('userCountText').textContent = userCount;
  } else {
    affectedSection.style.display = 'none';
    noUsersSection.style.display = 'block';
  }
});
</script>
