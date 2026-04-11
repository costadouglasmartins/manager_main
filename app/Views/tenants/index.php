<?php
$filters = $filters ?? ['id' => '', 'name' => '', 'database_number' => '', 'status' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($tenants) ? count($tenants) : 0);

$queryFilters = http_build_query([
	'id'              => $filters['id'],
	'name'            => $filters['name'],
	'database_number' => $filters['database_number'],
	'status'          => $filters['status'],
	]);
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Inicio</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tenants</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Lista de tenants</h5>

<!-- Search form -->
<form action="<?= url('tenant'); ?>" class="row g-3 mb-3">
  <div class="col-12 col-lg-9">
    <div class="row g-3">
      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="id" name="id" placeholder="Codigo" type="text" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="name" name="name" placeholder="Nome" type="text" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="database_number" name="database_number" placeholder="N. banco" type="text" value="<?= htmlspecialchars($filters['database_number'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <select autocomplete="off" class="form-control" id="status" name="status">
          <option value="">Situacao...</option>
          <option value="active" <?= ($filters['status'] === 'active' ? 'selected="selected"' : ''); ?>>Ativo</option>
          <option value="suspended" <?= ($filters['status'] === 'suspended' ? 'selected="selected"' : ''); ?>>Suspenso</option>
          <option value="pending" <?= ($filters['status'] === 'pending' ? 'selected="selected"' : ''); ?>>Pendente</option>
        </select>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('tenant'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?php if (authCan('tenant.create')): ?>
        <a href="<?= url('tenant/create'); ?>" class="btn btn-success w-100">Novo tenant</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <tr>
      <th style="width: 1%;">#</th>
      <th>Nome</th>
      <th>Banco</th>
      <th>Situacao</th>
      <th style="width: 1%;" class="text-nowrap text-end">Acoes</th>
    </tr>
    <?php if (!empty($tenants)): ?>
      <?php foreach ($tenants as $t): ?>
        <tr>
          <td><?= $t['id'] ?></td>
          <td><?= htmlspecialchars($t['name']) ?></td>
          <td><code>manager_<?= htmlspecialchars($t['database_number']) ?></code></td>
          <td>
            <?php if ($t['status'] === 'active'): ?>
              <span class="badge text-bg-success">Ativo</span>
            <?php elseif ($t['status'] === 'suspended'): ?>
              <span class="badge text-bg-danger">Suspenso</span>
            <?php else: ?>
              <span class="badge text-bg-warning">Pendente</span>
            <?php endif; ?>
          </td>
          <td class="text-nowrap text-end">
            <?php if (authCan('tenant.edit')): ?>
              <a href="<?= url('tenant/edit/' . $t['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
            <?php endif; ?>
            <?php if (authCan('tenant.informations')): ?>
              <a href="<?= url('tenant/informations/' . $t['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
            <?php endif; ?>
            <?php if (authCan('tenant.delete')): ?>
              <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $t['id'] ?>" data-name="<?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-trash"></i>
              </button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr>
        <td colspan="5">Nenhum registro encontrado.</td>
      </tr>
    <?php endif; ?>
  </table>
</div>

<p class="text-muted"><?= $total ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('tenant', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Proxima') ?>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar exclusão</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Tem certeza que deseja excluir o registro "<strong id="recordName"></strong>"?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="#" id="deleteForm" style="display: inline;">
          <input type="hidden" name="id" id="recordId">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function (e) {
  const recordId = e.relatedTarget.dataset.id;
  document.getElementById('recordId').value = recordId;
  document.getElementById('recordName').textContent = e.relatedTarget.dataset.name;
  document.getElementById('deleteForm').action = '<?= url('tenant/delete') ?>/' + recordId;
});
</script>
