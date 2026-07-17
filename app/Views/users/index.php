<?php
// Filters and pagination variables
$filters = $filters ?? ['id' => '', 'username' => '', 'type' => '', 'active' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($users) ? count($users) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];
$protectedUserIds = \App\Models\User::PROTECTED_USER_IDS;

// Create an array of filters to use on pagination
$queryFilters = http_build_query([
	'id' => $filters['id'],
	'username' => $filters['username'],
	'type' => $filters['type'],
	'active' => $filters['active']
	]);
$paginationQuery = sortQueryString($queryFilters, $sort);
$activeFilterCount = count(array_filter($filters, static fn ($value): bool => (string) $value !== ''));
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

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true"><i class="bi bi-person-badge fs-4"></i></span>
    <div><p class="small fw-semibold text-primary text-uppercase mb-1">Acessos</p><h1 class="h3 fw-bold mb-1">Usuários</h1><p class="text-body-secondary mb-0">Consulte e gerencie os acessos ao sistema.</p></div>
  </div>
  <a href="<?= url('person/create'); ?>" target="_blank" rel="noopener" class="btn btn-success d-inline-flex align-items-center justify-content-center gap-2"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Novo usuário</span></a>
</header>

<!-- Search form -->
<form action="<?= url('user'); ?>" class="card border-0 shadow-sm mb-4 p-3 p-lg-4 app-index-filters">
  <?= sortHiddenInputs($sort) ?>

  <div class="app-index-filter-heading d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div><h2 class="h5 fw-bold mb-1">Filtros de pesquisa</h2><p class="small text-body-secondary mb-0">Refine a lista de usuários.</p></div>
    <?php if ($activeFilterCount > 0): ?><span class="badge rounded-pill text-bg-primary"><?= $activeFilterCount; ?> filtro(s) ativo(s)</span><?php endif; ?>
  </div>

  <div class="row g-3 align-items-end">
  <div class="col-12">
    <div class="row g-3">
      <!-- Linha 1 -->
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label small fw-semibold" for="id">Código</label>
        <input class="form-control" id="id" name="id" placeholder="Código usuário" type="text" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label small fw-semibold" for="username">Nome de usuário</label>
        <input class="form-control" id="username" name="username" placeholder="Nome de usuário" type="text" value="<?= htmlspecialchars($filters['username'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <!--
      <div class="col-12 col-md-6 col-lg-4">
        <input autocomplete="off" type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?= htmlspecialchars($filters['email'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
        -->
      <!-- Linha 2 -->
      <div class="col-12 col-md-6 col-lg-4">
        <label for="type" class="form-label small fw-semibold">Tipo</label>
        <select autocomplete="off" class="form-select" id="type" name="type">
          <option value="">Selecione um tipo de usuário...</option>
          <option value="root" <?= ($filters['type'] === 'root' ? 'selected="selected"' : ''); ?>>ROOT</option>
          <option value="admin" <?= ($filters['type'] === 'admin' ? 'selected="selected"' : ''); ?>>ADMIN</option>
          <option value="user" <?= ($filters['type'] === 'user' ? 'selected="selected"' : ''); ?>>USUÁRIO(A)</option>
        </select>
      </div> 
      <div class="col-12 col-md-6 col-lg-12">
        <label for="active" class="form-label small fw-semibold">Situação</label>
        <select autocomplete="off" class="form-select" id="active" name="active">
          <option value="">Selecione uma situação...</option>
          <option value="1" <?= ($filters['active'] === '1' ? 'selected="selected"' : ''); ?>>Ativa</option>
          <option value="0" <?= ($filters['active'] === '0' ? 'selected="selected"' : ''); ?>>Inativa</option>
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

  <div class="col-12">
    <div class="index-search-actions">
      <button type="submit" class="btn btn-primary btn-sm index-search-action" aria-label="Pesquisar" title="Pesquisar"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Pesquisar</span></button>
      <a href="<?= url('user'); ?>" class="btn btn-outline-secondary btn-sm index-search-action" aria-label="Limpar filtros" title="Limpar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span></a>
      <?= pdfExportButton('user', $paginationQuery); ?>
      <?= excelExportButton('user', $paginationQuery); ?>
    </div>
  </div>
  </div>

</form>

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
      <?= sortableTh('user', 'id', '#', $sort, $queryFilters, 'class="text-nowrap"') ?>
      <?= sortableTh('user', 'username', 'Usuário', $sort, $queryFilters) ?>
      <?= sortableTh('user', 'type', 'Tipo', $sort, $queryFilters) ?>
      <th class="text-nowrap text-end">Ações</th>
    </tr>
		</thead>
		<tbody class="table-group-divider">
		<?php if (!empty($users)): ?>
			<?php foreach ($users as $user): ?>
				<tr>
					<td class="text-nowrap"><span class="badge bg-body-tertiary border text-body">#<?= (int) $user['id']; ?></span></td>
					<td class="fw-semibold"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?= htmlspecialchars($user['type'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td class="text-nowrap text-end">
						<?php if (authCan('user.edit')): ?>
							<?php if (in_array((int) $user['id'], $protectedUserIds, true)): ?>
								<button class="btn btn-sm btn-link p-0 text-warning" type="button" data-bs-toggle="modal" data-bs-target="#protectedEditModal" data-id="<?= (int) $user['id'] ?>" data-name="<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>" title="Editar com senha de proteção" aria-label="Editar usuário protegido">
									<i class="bi bi-pencil-square"></i>
								</button>
							<?php else: ?>
								<a href="<?= url('user/edit/' . $user['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
							<?php endif; ?>
						<?php endif; ?>
						<?php if (authCan('user.informations')): ?>
							<a href="<?= url('user/informations/' . $user['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Ver detalhes"><i class="bi bi-eye"></i></a>
						<?php endif; ?>
						<?php if (authCan('user.delete')): ?>
							<?php if (in_array((int) $user['id'], $protectedUserIds, true)): ?>
								<button class="btn btn-sm btn-secondary" type="button" disabled title="Este usuário é protegido e não pode ser excluído" aria-label="Usuário protegido contra exclusão">
									<i class="bi bi-shield-lock"></i>
								</button>
							<?php else: ?>
								<button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= (int) $user['id'] ?>" data-name="<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>">
									<i class="bi bi-trash"></i>
								</button>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else: ?>
			<tr>
				<td colspan="4" class="app-index-results-empty">Nenhum registro encontrado.</td>
			</tr>
		<?php endif; ?>
		</tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
  <div class="app-index-pagination">
	<?= paginationLinks('user', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
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
      <form method="POST" action="#" id="protectedEditForm" data-authorization-base-url="<?= url('user/authorizeProtectedEdit'); ?>">
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
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true" data-impact-base-url="<?= url('user/deletionImpact'); ?>">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteModalLabel">Confirmar exclusão integral do usuário</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger" role="alert">
          A exclusão do usuário "<strong id="recordName"></strong>", da pessoa associada e de todos os registros apresentados abaixo é permanente.
        </div>

        <div class="alert alert-success" role="alert">
          <strong>Nenhuma base de dados será excluída.</strong> Somente os registros associados ao usuário serão removidos.
        </div>

        <div id="deletionImpactLoading" class="py-4 text-center" role="status">
          <div class="spinner-border text-danger" aria-hidden="true"></div>
          <div class="mt-2">Consultando módulos e registros associados...</div>
        </div>

        <div id="deletionImpactError" class="alert alert-danger d-none" role="alert"></div>

        <div id="deletionImpactContent" class="d-none">
          <div id="deletionWarnings"></div>

          <div class="alert alert-secondary">
            <strong>Dados preservados:</strong> <span id="preservedRecords"></span>.
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0 text-danger">Dados que serão excluídos por módulo</h6>
            <span class="badge text-bg-danger" id="deletionTotalRecords"></span>
          </div>
          <div id="deletionModules" class="border border-danger rounded p-2"></div>

          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" value="1" id="confirmDeletionReview" disabled>
            <label class="form-check-label" for="confirmDeletionReview">
              Revisei os módulos e confirmo a exclusão permanente de todos os registros apresentados.
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer border-danger">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="#" id="deleteForm" data-delete-base-url="<?= url('user/delete'); ?>" class="d-inline">
          <input type="hidden" name="id" id="recordId">
          <input type="hidden" name="confirm_full_deletion" value="1">
          <button type="submit" class="btn btn-danger" id="confirmDeleteButton" disabled>Excluir usuário, pessoa e registros</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('deleteModal');
  const form = document.getElementById('deleteForm');
  const reviewCheckbox = document.getElementById('confirmDeletionReview');
  const confirmButton = document.getElementById('confirmDeleteButton');
  const loading = document.getElementById('deletionImpactLoading');
  const error = document.getElementById('deletionImpactError');
  const content = document.getElementById('deletionImpactContent');
  const modulesContainer = document.getElementById('deletionModules');
  const warningsContainer = document.getElementById('deletionWarnings');
  let impactRequest = null;

  function resetImpact() {
    if (impactRequest) {
      impactRequest.abort();
    }

    impactRequest = new AbortController();
    loading.classList.remove('d-none');
    error.classList.add('d-none');
    error.textContent = '';
    content.classList.add('d-none');
    modulesContainer.replaceChildren();
    warningsContainer.replaceChildren();
    reviewCheckbox.checked = false;
    reviewCheckbox.disabled = true;
    confirmButton.disabled = true;
  }

  function appendRecordList(module, section) {
    const list = document.createElement('ul');
    list.className = 'mb-0 small';

    (module.records || []).forEach(function (record) {
      const item = document.createElement('li');
      item.className = 'mb-1';

      const label = document.createElement('span');
      label.textContent = record.label || ('Registro #' + record.id);
      item.appendChild(label);

      const source = document.createElement('code');
      source.className = 'ms-2 text-muted';
      source.textContent = record.table + '#' + record.id;
      item.appendChild(source);
      list.appendChild(item);
    });

    section.appendChild(list);
  }

  function renderImpact(impact) {
    (impact.warnings || []).forEach(function (message) {
      const warning = document.createElement('div');
      warning.className = 'alert alert-warning';
      warning.textContent = message;
      warningsContainer.appendChild(warning);
    });

    document.getElementById('preservedRecords').textContent = (impact.preserved || []).join('; ');
    document.getElementById('deletionTotalRecords').textContent = Number(impact.total_records || 0).toLocaleString('pt-BR') + ' registro(s)';

    (impact.modules || []).forEach(function (module) {
      const section = document.createElement('section');
      section.className = 'mb-3 pb-3 border-bottom border-danger-subtle';

      const heading = document.createElement('div');
      heading.className = 'd-flex flex-wrap gap-2 align-items-center mb-2';

      const scope = document.createElement('span');
      scope.className = 'badge text-bg-dark';
      scope.textContent = module.scope || 'manager_main';
      heading.appendChild(scope);

      const title = document.createElement('strong');
      title.className = 'text-danger';
      title.textContent = module.label;
      heading.appendChild(title);

      const count = document.createElement('span');
      count.className = 'badge text-bg-danger';
      count.textContent = Number(module.count || 0).toLocaleString('pt-BR');
      heading.appendChild(count);

      section.appendChild(heading);
      appendRecordList(module, section);
      modulesContainer.appendChild(section);
    });

    loading.classList.add('d-none');
    content.classList.remove('d-none');
    reviewCheckbox.disabled = false;
  }

  reviewCheckbox.addEventListener('change', function () {
    confirmButton.disabled = !reviewCheckbox.checked;
  });

  modal.addEventListener('show.bs.modal', function (event) {
    const trigger = event.relatedTarget;
    if (!trigger) {
      return;
    }

    resetImpact();
    const recordId = trigger.dataset.id;
    document.getElementById('recordId').value = recordId;
    document.getElementById('recordName').textContent = trigger.dataset.name;
    form.action = form.dataset.deleteBaseUrl.replace(/\/$/, '') + '/' + encodeURIComponent(recordId);

    const impactUrl = modal.dataset.impactBaseUrl.replace(/\/$/, '') + '/' + encodeURIComponent(recordId);
    fetch(impactUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      signal: impactRequest.signal
    })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (payload) {
          if (!response.ok) {
            throw new Error(payload.message || 'Não foi possível carregar os registros associados.');
          }
          return payload;
        });
      })
      .then(renderImpact)
      .catch(function (requestError) {
        if (requestError.name === 'AbortError') {
          return;
        }
        loading.classList.add('d-none');
        error.textContent = requestError.message;
        error.classList.remove('d-none');
      });
  });

  modal.addEventListener('hidden.bs.modal', function () {
    if (impactRequest) {
      impactRequest.abort();
      impactRequest = null;
    }
  });
})();
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
