<?php
// Filters and pagination variables
$filters = $filters ?? ['id' => '', 'name' => '', 'type' => '', 'document' => '', 'active' => '', 'birthdate' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($people) ? count($people) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];
$protectedPersonIds = \App\Models\Person::PROTECTED_PERSON_IDS;

// Create an array of filters to use on pagination
$queryFilters = http_build_query([
	'id' => $filters['id'],
	'name' => $filters['name'],
	'type' => $filters['type'],
	'document' => $filters['document'],
	'active' => $filters['active'],
	'birthdate' => $filters['birthdate'],
	]);
$paginationQuery = sortQueryString($queryFilters, $sort);
$activeFilterCount = count(array_filter($filters, static fn ($value): bool => (string) $value !== ''));
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pessoas</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->
<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true"><i class="bi bi-people fs-4"></i></span>
    <div><p class="small fw-semibold text-primary text-uppercase mb-1">Cadastros</p><h1 class="h3 fw-bold mb-1">Pessoas</h1><p class="text-body-secondary mb-0">Consulte e gerencie pessoas físicas e jurídicas.</p></div>
  </div>
  <?php if (authCan('person.create')): ?><a href="<?= url('person/create'); ?>" class="btn btn-success d-inline-flex align-items-center justify-content-center gap-2"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Nova pessoa</span></a><?php endif; ?>
</header>
<!-- Search form -->
<form action="<?= url('person'); ?>" class="card border-0 shadow-sm mb-4 p-3 p-lg-4 app-index-filters" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="app-index-filter-heading d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div><h2 class="h5 fw-bold mb-1">Filtros de pesquisa</h2><p class="small text-body-secondary mb-0">Refine a lista de pessoas.</p></div>
    <?php if ($activeFilterCount > 0): ?><span class="badge rounded-pill text-bg-primary"><?= $activeFilterCount; ?> filtro(s) ativo(s)</span><?php endif; ?>
  </div>

  <div class="row g-3 align-items-end">
    <div class="col-12">
      <div class="row g-3">
        <div class="col-12 col-md-6 col-lg-4">
          <label class="form-label small fw-semibold" for="id">Código</label>
          <input class="form-control" id="id" name="id" type="text" placeholder="Código pessoa" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6 col-lg-4 position-relative">
          <label class="form-label small fw-semibold" for="name">Nome</label>
          <input id="name" type="text" class="form-control js-autocomplete" autocomplete="off" placeholder="Digite o nome da pessoa..." name="name" data-endpoint="<?= url('person/autocomplete'); ?>" data-results="#personResults" data-hidden="#id" data-minlen="1" data-param="term" value="<?= $filters['name']; ?>">
          <ul id="personResults" class="list-group mt-1 w-100"></ul>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <label class="form-label small fw-semibold" for="type">Tipo</label>
          <select autocomplete="off" class="form-select" id="type" name="type">
            <option value="">Selecione um tipo de pessoa...</option>
            <option value="individual" <?= ($filters['type'] == 'individual' ? 'selected="selected"' : ''); ?>>Pessoa física</option>
            <option value="company" <?= ($filters['type'] == 'company' ? 'selected="selected"' : ''); ?>>Pessoa jurídica</option>
          </select>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <label class="form-label small fw-semibold" for="document">CPF ou CNPJ</label>
          <input autocomplete="off" class="form-control mask-cpf-cnpj" id="document" placeholder="Digite o CPF ou CNPJ..." name="document" value="<?= htmlspecialchars(maskCpfCnpj($filters['document']), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <label class="form-label small fw-semibold" for="birthdate">Data de nascimento</label>
          <input autocomplete="off" class="form-control mask-date" id="birthdate" placeholder="DD/MM/AAAA" name="birthdate" value="<?= htmlspecialchars(maskDateBr($filters['birthdate']) ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <label class="form-label small fw-semibold" for="active">Situação</label>
          <select autocomplete="off" class="form-select" id="active" name="active">
            <option value="">Selecione uma situação...</option>
            <option value="1" <?= ($filters['active'] === '1' ? 'selected="selected"' : ''); ?>>Ativa</option>
            <option value="0" <?= ($filters['active'] === '0' ? 'selected="selected"' : ''); ?>>Inativa</option>
          </select>
        </div>
      </div>
    </div>
    <div class="col-12">
      <div class="index-search-actions">
        <button type="submit" class="btn btn-primary btn-sm index-search-action" aria-label="Pesquisar" title="Pesquisar"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Pesquisar</span></button>
        <a href="<?= url('person'); ?>" class="btn btn-outline-secondary btn-sm index-search-action" aria-label="Limpar filtros" title="Limpar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span></a>
        <?= pdfExportButton('person', $paginationQuery); ?>
        <?= excelExportButton('person', $paginationQuery); ?>
      </div>
    </div>
  </div>
</form>
<!-- /Search form -->

<div class="app-index-results-header">
  <div>
    <h2 class="h5 fw-bold mb-1">Resultados</h2>
    <p class="small text-body-secondary mb-0"><?= $activeFilterCount > 0 ? 'Registros encontrados para os filtros informados.' : 'Todos os registros cadastrados.'; ?></p>
  </div>
  <span class="badge rounded-pill text-bg-primary"><?= (int) $total; ?> registro(s)</span>
</div>

<div class="table-responsive app-index-results-table">
  <table class="table table-hover align-middle mb-0">
    <thead>
    <tr>
      <?= sortableTh('person', 'id', '#', $sort, $queryFilters, 'class="text-nowrap"') ?>
      <?= sortableTh('person', 'name', 'Nome', $sort, $queryFilters) ?>
      <?= sortableTh('person', 'document', 'Documento', $sort, $queryFilters) ?>
      <?= sortableTh('person', 'birthdate', 'Data de nascimento', $sort, $queryFilters) ?>
      <?= sortableTh('person', 'type', 'Tipo', $sort, $queryFilters) ?>
      <th class="text-end">Ações</th>
    </tr>
		</thead>
		<tbody class="table-group-divider">
		<?php if (!empty($people)): ?>
			<?php foreach ($people as $person): ?>
				<tr>
					<td class="text-nowrap"><span class="badge bg-body-tertiary border text-body">#<?= (int) $person['id']; ?></span></td>
					<td class="fw-semibold"><?= htmlspecialchars($person['name'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?= maskCpfCnpj($person['document']); ?></td>
					<td><?= (!empty($person['birthdate']) ? maskDateBr($person['birthdate']) : '-'); ?></td>
					<td><?= ($person['type'] == 'company' ? 'Pessoa jurídica' : 'Pessoa física') ?></td>
					<td style="width: 1%;" class="text-nowrap text-end">
						<?php if (authCan('person.edit')): ?>
							<?php if (in_array((int) $person['id'], $protectedPersonIds, true)): ?>
								<button class="btn btn-sm btn-link p-0 text-warning" type="button" data-bs-toggle="modal" data-bs-target="#protectedEditModal" data-id="<?= (int) $person['id'] ?>" data-name="<?= htmlspecialchars($person['name'], ENT_QUOTES, 'UTF-8') ?>" title="Editar com senha de proteção" aria-label="Editar pessoa protegida">
									<i class="bi bi-pencil-square"></i>
								</button>
							<?php else: ?>
								<a href="<?= url('person/edit/' . $person['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
							<?php endif; ?>
						<?php endif; ?>
						<?php if (authCan('person.informations')): ?>
							<a href="<?= url('person/informations/' . $person['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Ver detalhes"><i class="bi bi-eye"></i></a>
						<?php endif; ?>
						<?php if (authCan('person.delete')): ?>
							<?php if (in_array((int) $person['id'], $protectedPersonIds, true)): ?>
								<button class="btn btn-sm btn-secondary" type="button" disabled title="Esta pessoa é protegida e não pode ser excluída" aria-label="Pessoa protegida contra exclusão">
									<i class="bi bi-shield-lock"></i>
								</button>
							<?php else: ?>
								<button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= (int) $person['id'] ?>" data-name="<?= htmlspecialchars($person['name'], ENT_QUOTES, 'UTF-8') ?>">
									<i class="bi bi-trash"></i>
								</button>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else: ?>
			<tr>
				<td colspan="6" class="app-index-results-empty">Nenhum registro encontrado.</td>
			</tr>
		<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
  <div class="app-index-pagination">
	<?= paginationLinks('person', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
  </div>
<?php endif; ?>
<!-- /Pagination -->

<!-- Protected Edit Authorization Modal -->
<div class="modal fade" id="protectedEditModal" tabindex="-1" aria-labelledby="protectedEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning-subtle">
        <h5 class="modal-title" id="protectedEditModalLabel">Liberar edição protegida</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <form method="POST" action="#" id="protectedEditForm" data-authorization-base-url="<?= url('person/authorizeProtectedEdit'); ?>">
        <div class="modal-body">
          <p class="small">Informe a senha do usuário ID 1 para editar <strong id="protectedEditRecordName"></strong>.</p>
          <label class="form-label" for="protectedEditPassword">Senha</label>
          <input class="form-control" type="password" id="protectedEditPassword" name="authorization_password" autocomplete="current-password" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning">Liberar edição</button>
        </div>
      </form>
    </div>
  </div>
</div>

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
        <form method="POST" action="#" id="deleteForm" class="d-inline">
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

<script>
(function () {
  const modal = document.getElementById('protectedEditModal');
  const form = document.getElementById('protectedEditForm');
  const password = document.getElementById('protectedEditPassword');

  modal.addEventListener('show.bs.modal', function (event) {
    const trigger = event.relatedTarget;
    if (!trigger) {
      return;
    }

    const recordId = trigger.dataset.id;
    document.getElementById('protectedEditRecordName').textContent = trigger.dataset.name;
    form.action = form.dataset.authorizationBaseUrl.replace(/\/$/, '') + '/' + encodeURIComponent(recordId);
    password.value = '';
  });

  modal.addEventListener('shown.bs.modal', function () {
    password.focus();
  });
})();
</script>

<script src="/manager_assets/autocomplete/autocomplete.js?v=4"></script>
