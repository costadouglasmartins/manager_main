<?php
// Filters and pagination variables
$filters = $filters ?? ['id' => '', 'name' => '', 'type' => '', 'document' => '', 'active' => '', 'birthdate' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($people) ? count($people) : 0);

// Create an array of filters to use on pagination
$queryFilters = http_build_query([
	'id' => $filters['id'],
	'name' => $filters['name'],
	'type' => $filters['type'],
	'document' => $filters['document'],
	'active' => $filters['active'],
	'birthdate' => $filters['birthdate'],
	]);
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pessoas</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->
<h5>Lista de Pessoas</h5>
<!-- Search form -->
<form action="<?= url('person'); ?>" class="row g-3 mb-3" method="get">

  <div class="col-12 col-lg-9">
    <div class="row g-3">
      <!-- Linha 1 -->
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label class="form-label" for="id">#</label>-->
        <input class="form-control" id="id" name="id" type="text" placeholder="Código pessoa" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      
      <div class="col-12 col-md-6 col-lg-4 position-relative">
        <!--<label for="name" class="form-label">Pessoa</label>-->
				<input id="name" type="text" class="form-control js-autocomplete" autocomplete="off" placeholder="Digite o nome da pessoa..." name="name" data-endpoint="<?= url('person/autocomplete'); ?>" data-results="#personResults" data-hidden="#id" data-minlen="1" data-param="term" value="<?= $filters['name']; ?>">
        <ul id="personResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
      </div>
      <!-- Linha 2 -->
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label for="type" class="form-label">Tipo</label>-->
        <select autocomplete="off" class="form-control" id="type" name="type">
          <option value="">Selecione um tipo de pessoa...</option>
          <option value="individual" <?= ($filters['type'] == 'individual' ? 'selected="selected"' : ''); ?>>Pessoa física</option>
          <option value="company" <?= ($filters['type'] == 'company' ? 'selected="selected"' : ''); ?>>Pessoa jurídica</option>
        </select>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label class="form-label" for="document">Documento</label>-->
        <input autocomplete="off" class="form-control mask-cpf-cnpj" id="document" placeholder="Digite o CPF ou CNPJ..." name="document" value="<?= htmlspecialchars(maskCpfCnpj($filters['document']), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label class="form-label" for="f6">Data de nascimento</label>-->
        <input autocomplete="off" class="form-control mask-date" id="birthdate" placeholder="Data de nascimento" name="birthdate" value="<?= htmlspecialchars(maskCpfCnpj($filters['birthdate']), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label for="active" class="form-label">Ativo(a)</label>--->
        <select autocomplete="off" class="form-control" id="active" name="active">
          <option value="">Selecione um tipo...</option>
          <option value="1" <?= ($filters['active'] === '1' ? 'selected="selected"' : ''); ?>>Ativa</option>
          <option value="0" <?= ($filters['active'] === '0' ? 'selected="selected"' : ''); ?>>Inativa</options>
        </select>
      </div>
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
      <a href="<?= url('person'); ?>" class="btn btn-secondary w-100">Limpar</a>
			<?php if (authCan('person.create')): ?>
				<a href="<?= url('person/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
			<?php endif; ?>
    </div>
  </div>

</form>
<!-- /Search form -->

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <tr>
      <th>#</th>
      <th>Nome</th>
      <th>Documento</th>
      <th>Data de nascimento</td>
      <th>Tipo</th>
      <th>Ações</th>
    </tr>
		<?php if (!empty($people)): ?>
			<?php foreach ($people as $person): ?>
				<tr>
					<td style="width: 1%;"><?= $person['id'] ?></td>
					<td><?= $person['name'] ?></td>
					<td><?= maskCpfCnpj($person['document']); ?></td>
					<td><?= (!empty($person['birthdate']) ? maskDateBr($person['birthdate']) : '-'); ?></td>
					<td><?= ($person['type'] == 'company' ? 'Pessoa jurídica' : 'Pessoa física') ?></td>
					<td style="width: 1%;" class="text-nowrap text-end">
						<?php if (authCan('person.edit')): ?>
							<a href="<?= url('person/edit/' . $person['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
						<?php endif; ?>
						<?php if (authCan('person.informations')): ?>
							<a href="<?= url('person/informations/' . $person['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
						<?php endif; ?>
						<?php if (authCan('person.delete')): ?>
							<button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $person['id'] ?>" data-name="<?= htmlspecialchars($person['name'], ENT_QUOTES, 'UTF-8') ?>">
								<i class="bi bi-trash"></i>
							</button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else: ?>
			<tr>
				<td colspan="6" class="text-center">Nenhum registro encontrado.</td>
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
	<?= paginationLinks('person', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima') ?>
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
  document.getElementById('deleteForm').action = '<?= url('person/delete') ?>/' + recordId;
});
</script>

<script src="<?= url('assets/js/autocomplete.js?v=2'); ?>"></script>