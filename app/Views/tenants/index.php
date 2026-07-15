<?php
$filters = $filters ?? ['id' => '', 'name' => '', 'database_number' => '', 'status' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($tenants) ? count($tenants) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];

$queryFilters = http_build_query([
	'id'              => $filters['id'],
	'name'            => $filters['name'],
	'database_number' => $filters['database_number'],
	'status'          => $filters['status'],
	]);
$paginationQuery = sortQueryString($queryFilters, $sort);
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Inicio</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tenants</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- Search form -->
<form action="<?= url('tenant'); ?>" class="index-search-card mb-4">
  <?= sortHiddenInputs($sort) ?>

  <div class="row g-3 align-items-center">
  <div class="col-12 col-lg-9">
    <div class="row g-3">
      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="id" name="id" placeholder="Codigo" type="text" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="name" name="name" placeholder="Pessoa associada" type="text" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
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
      <?= pdfExportButton('tenant', $paginationQuery); ?>
      <?= excelExportButton('tenant', $paginationQuery); ?>
      <?php if (authCan('tenant.create')): ?>
        <a href="<?= url('tenant/create'); ?>" class="btn btn-success w-100">Novo tenant</a>
      <?php endif; ?>
    </div>
  </div>
  </div>

</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <tr>
      <?= sortableTh('tenant', 'id', '#', $sort, $queryFilters, 'style="width: 1%;"') ?>
      <?= sortableTh('tenant', 'name', 'Pessoa associada', $sort, $queryFilters) ?>
      <?= sortableTh('tenant', 'database_number', 'Banco', $sort, $queryFilters) ?>
      <?= sortableTh('tenant', 'status', 'Situacao', $sort, $queryFilters) ?>
      <th style="width: 1%;" class="text-nowrap text-end">Acoes</th>
    </tr>
    <?php if (!empty($tenants)): ?>
      <?php foreach ($tenants as $t): ?>
        <tr>
          <td><?= $t['id'] ?></td>
          <td><?= htmlspecialchars($t['associated_person_name'] ?? ('Tenant #' . (int) $t['id']), ENT_QUOTES, 'UTF-8') ?></td>
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
              <?php if ((int) $t['id'] === 1): ?>
                <button class="btn btn-sm btn-secondary" type="button" disabled title="O tenant principal não pode ser excluído" aria-label="Tenant principal protegido contra exclusão">
                  <i class="bi bi-shield-lock"></i>
                </button>
              <?php else: ?>
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= (int) $t['id'] ?>" data-name="<?= htmlspecialchars($t['associated_person_name'] ?? ('Tenant #' . (int) $t['id']), ENT_QUOTES, 'UTF-8') ?>">
                  <i class="bi bi-trash"></i>
                </button>
              <?php endif; ?>
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
  <?= paginationLinks('tenant', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Proxima') ?>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true" data-impact-base-url="<?= url('tenant/deletionImpact'); ?>">
  <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Confirmar exclusão integral do tenant</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">
          A exclusão de "<strong id="recordName"></strong>" é permanente. A base dedicada e os registros abaixo serão removidos.
        </p>

        <div class="alert alert-danger" role="alert">
          <strong>Atenção:</strong> todas as permissões de todos os usuários associados a este tenant serão removidas, mesmo que algum usuário também esteja vinculado a outro tenant.
        </div>

        <div id="deletionImpactLoading" class="py-4 text-center" role="status">
          <div class="spinner-border text-danger" aria-hidden="true"></div>
          <div class="mt-2">Consultando módulos e registros associados...</div>
        </div>

        <div id="deletionImpactError" class="alert alert-danger d-none" role="alert"></div>

        <div id="deletionImpactContent" class="d-none">
          <div id="deletionDatabaseSummary" class="alert alert-warning"></div>
          <div id="deletionWarnings"></div>

          <div class="alert alert-success">
            <strong>Registros preservados no manager_main:</strong>
            <span id="preservedRecords"></span>.
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Registros que serão removidos por módulo</h6>
            <span class="badge text-bg-danger" id="deletionTotalRecords"></span>
          </div>
          <div id="deletionModules" class="border rounded p-2"></div>

          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" value="1" id="confirmDeletionReview" disabled>
            <label class="form-check-label" for="confirmDeletionReview">
              Revisei a lista e confirmo a exclusão permanente de todos os itens apresentados.
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="#" id="deleteForm" data-delete-base-url="<?= url('tenant/delete'); ?>" class="d-inline">
          <input type="hidden" name="id" id="recordId">
          <input type="hidden" name="confirm_full_deletion" value="1">
          <button type="submit" class="btn btn-danger" id="confirmDeleteButton" disabled>Excluir tenant e registros</button>
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

    if (!Array.isArray(module.records) || module.records.length === 0) {
      const empty = document.createElement('li');
      empty.className = 'text-muted';
      empty.textContent = 'Nenhum registro encontrado.';
      list.appendChild(empty);
    } else {
      module.records.forEach(function (record) {
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
    }

    section.appendChild(list);
  }

  function renderImpact(impact) {
    const database = impact.database || {};
    const databaseSummary = document.getElementById('deletionDatabaseSummary');
    databaseSummary.textContent = database.exists
      ? 'A base ' + database.name + ' será excluída por completo (' + database.table_count + ' tabela(s) e ' + database.record_count + ' registro(s) listados).'
      : 'A base ' + database.name + ' não existe; a limpeza dos registros no manager_main continuará normalmente.';

    (impact.warnings || []).forEach(function (message) {
      const warning = document.createElement('div');
      warning.className = 'alert alert-warning';
      warning.textContent = message;
      warningsContainer.appendChild(warning);
    });

    document.getElementById('preservedRecords').textContent = (impact.preserved || []).join(', ');
    document.getElementById('deletionTotalRecords').textContent = Number(impact.total_records || 0).toLocaleString('pt-BR') + ' registro(s)';

    (impact.modules || []).forEach(function (module) {
      const section = document.createElement('section');
      section.className = 'mb-3 pb-3 border-bottom';

      const heading = document.createElement('div');
      heading.className = 'd-flex flex-wrap gap-2 align-items-center mb-2';

      const scope = document.createElement('span');
      scope.className = 'badge text-bg-secondary';
      scope.textContent = module.scope || 'manager_main';
      heading.appendChild(scope);

      const title = document.createElement('strong');
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
