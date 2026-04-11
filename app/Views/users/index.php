<?php
// Filters and pagination variables
$filters = $filters ?? ['id' => '', 'username' => '', 'type' => '', 'active' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($users) ? count($users) : 0);

// Create an array of filters to use on pagination
$queryFilters = http_build_query([
	'id' => $filters['id'],
	'username' => $filters['username'],
	'type' => $filters['type'],
	'active' => $filters['active']
	]);
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('person'); ?>">Pessoas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Usuários</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Lista de usuários</h5>

<!-- Search form -->
<form action="<?= url('user'); ?>" class="row g-3 mb-3">
  <div class="col-12 col-lg-9">
    <div class="row g-3">
      <!-- Linha 1 -->
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label class="form-label" for="id">#</label>-->
        <input class="form-control" id="id" name="id" placeholder="Código usuário" type="text" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label class="form-label" for="username">Username</label>-->
        <input class="form-control" id="username" name="username" placeholder="Nome de usuário" type="text" value="<?= htmlspecialchars($filters['username'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <!--
      <div class="col-12 col-md-6 col-lg-4">
        <input autocomplete="off" type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?= htmlspecialchars($filters['email'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
        -->
      <!-- Linha 2 -->
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label for="type" class="form-label">Tipo</label>-->
        <select autocomplete="off" class="form-control" id="type" name="type">
          <option value="">Selecione um tipo de usuário...</option>
          <option value="root" <?= ($filters['type'] === 'root' ? 'selected="selected"' : ''); ?>>ROOT</option>
          <option value="admin" <?= ($filters['type'] === 'admin' ? 'selected="selected"' : ''); ?>>ADMIN</option>
          <option value="user" <?= ($filters['type'] === 'user' ? 'selected="selected"' : ''); ?>>USUÁRIO(A)</option>
        </select>
      </div> 
      <div class="col-12 col-md-6 col-lg-8">
        <!--<label for="active" class="form-label">Situação</label>-->
        <select autocomplete="off" class="form-control" id="active" name="active">
          <option value="">Selecione uma situação...</option>
          <option value="1" <?= ($filters['active'] === '1' ? 'selected="selected"' : ''); ?>>Ativa</option>
          <option value="0" <?= ($filters['active'] === '0' ? 'selected="selected"' : ''); ?>>Inativa</options>
        </select>
      </div>
      <!--
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f6">Filtro 6</label>
        <select class="form-select" id="f6">
          <option value="">Selecione</option>
          <option>Opção 1</option>
          <option>Opção 2</option>
        </select>
      </div>
      -->
      <!-- Linha 3 
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f7">Filtro 7</label>
        <input class="form-control" id="f7" type="text">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f8">Filtro 8</label>
        <input class="form-control" id="f8" type="text">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f9">Filtro 9</label>
        <select class="form-select" id="f9">
          <option value="">Selecione</option>
          <option>Opção 1</option>
          <option>Opção 2</option>
        </select>
      </div>
      -->
      <!-- Linha 4
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f10">Filtro 10</label>
        <input class="form-control" id="f10" type="text">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f11">Filtro 11</label>
        <input class="form-control" id="f11" type="text">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f12">Filtro 12</label>
        <select class="form-select" id="f12">
          <option value="">Selecione</option>
          <option>Opção 1</option>
          <option>Opção 2</option>
        </select>
      </div>
      -->
    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <!--<label>&nbsp;</label>-->
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('user'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <a href="<?= url('person/create'); ?>" class="btn btn-success w-100" target="_blank">Novo(a) usuário(a)</a>
    </div>
  </div>
</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <tr>
      <th style="width: 1%;">#</th>
      <th>Usuário</th>
      <th>Tipo</th>
      <th style="width: 1%;" class="text-nowrap text-end">Ações</th>
    </tr>
		<?php if (!empty($users)): ?>
			<?php foreach ($users as $user): ?>
				<tr>
					<td><?= $user['id'] ?></td>
					<td><?= $user['username'] ?></td>
          <td><?= $user['type']; ?></td>
					<td class="text-nowrap text-end">
						<?php if (authCan('user.edit')): ?>
							<a href="<?= url('user/edit/' . $user['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
						<?php endif; ?>
						<?php if (authCan('user.informations')): ?>
							<a href="<?= url('user/informations/' . $user['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
						<?php endif; ?>
						<?php if (authCan('user.delete')): ?>
							<button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>">
								<i class="bi bi-trash"></i>
							</button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else: ?>
			<tr>
				<td colspan="4">Nenhum registro encontrado.</td>
			</tr>
		<?php endif; ?>
  </table>
</div>

<!-- Total records -->
<p class="text-muted">
	<?= $total ?> registro(s) encontrado(s)
</p>
<!-- /Total records -->

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
	<?= paginationLinks('user', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima') ?>
<?php endif; ?>
<!-- /Pagination -->

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
  document.getElementById('deleteForm').action = '<?= url('user/delete') ?>/' + recordId;
});
</script>