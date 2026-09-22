<?php
$filters = $filters ?? ['id' => '', 'phone_number' => '', 'person_id' => '', 'person_name' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($phone_numbers) ? count($phone_numbers) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];
$protectedPersonIds = \App\Models\Person::PROTECTED_PERSON_IDS;

$queryFilters = http_build_query([
  'id'           => $filters['id'],
  'phone_number' => $filters['phone_number'],
  'person_id'    => $filters['person_id'],
  'person_name'  => $filters['person_name'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
$activeFilterCount = count(array_filter($filters, static fn ($value): bool => (string) $value !== ''));
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Telefones</li>
  </ol>
</nav>

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true"><i class="bi bi-telephone fs-4"></i></span>
    <div><p class="small fw-semibold text-primary text-uppercase mb-1">Cadastros</p><h1 class="h3 fw-bold mb-1">Telefones</h1><p class="text-body-secondary mb-0">Consulte e gerencie os telefones vinculados às pessoas.</p></div>
  </div>
  <?php if (authCan('phone_number.create')): ?><a href="<?= url('phone_number/create'); ?>" class="btn btn-success d-inline-flex align-items-center justify-content-center gap-2"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Novo telefone</span></a><?php endif; ?>
</header>

<form action="<?= url('phone_number'); ?>" class="card border-0 shadow-sm mb-4 p-3 p-lg-4 app-index-filters" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="app-index-filter-heading d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div><h2 class="h5 fw-bold mb-1">Filtros de pesquisa</h2><p class="small text-body-secondary mb-0">Refine a lista de telefones.</p></div>
    <?php if ($activeFilterCount > 0): ?><span class="badge rounded-pill text-bg-primary"><?= $activeFilterCount; ?> filtro(s) ativo(s)</span><?php endif; ?>
  </div>

  <div class="row g-3 align-items-end">

  <div class="col-12">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label small fw-semibold" for="id">Código</label>
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label small fw-semibold" for="phone_number">Telefone</label>
        <input class="form-control" id="phone_number" name="phone_number" type="text" placeholder="Telefone" value="<?= htmlspecialchars($filters['phone_number'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-4 position-relative">
        <label class="form-label small fw-semibold" for="person_name">Pessoa</label>
        <input type="hidden" id="person_id" name="person_id" value="<?= htmlspecialchars($filters['person_id'], ENT_QUOTES, 'UTF-8') ?>">
        <input id="person_name" name="person_name" type="text" class="form-control js-autocomplete" autocomplete="off"
          placeholder="Digite o nome da pessoa..."
          data-endpoint="<?= url('person/autocomplete'); ?>"
          data-results="#personResults"
          data-hidden="#person_id"
          data-minlen="1"
          data-param="term"
          value="<?= htmlspecialchars($filters['person_name'], ENT_QUOTES, 'UTF-8') ?>">
        <ul id="personResults" class="list-group mt-1 w-100"></ul>
      </div>

    </div>
  </div>

  <div class="col-12">
    <div class="index-search-actions">
      <button type="submit" class="btn btn-primary btn-sm index-search-action" aria-label="Pesquisar" title="Pesquisar"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Pesquisar</span></button>
      <a href="<?= url('phone_number'); ?>" class="btn btn-outline-secondary btn-sm index-search-action" aria-label="Limpar filtros" title="Limpar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span></a>
      <?= pdfExportButton('phone_number', $paginationQuery); ?>
      <?= excelExportButton('phone_number', $paginationQuery); ?>
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
        <?= sortableTh('phone_number', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('phone_number', 'phone_number', 'Telefone', $sort, $queryFilters) ?>
        <?= sortableTh('phone_number', 'person_name', 'Pessoa', $sort, $queryFilters) ?>
        <?= sortableTh('phone_number', 'observations', 'Observações', $sort, $queryFilters) ?>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody class="table-group-divider">
      <?php if (!empty($phone_numbers)): ?>
        <?php foreach ($phone_numbers as $row): ?>
          <tr>
            <td style="width:1%;"><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['phone_number'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['person_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['observations'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('phone_number.edit')): ?>
                <?php if (in_array((int) $row['person_id'], $protectedPersonIds, true)): ?>
                  <button class="btn btn-sm btn-link p-0 text-warning" type="button" data-bs-toggle="modal" data-bs-target="#protectedEditModal" data-id="<?= (int) $row['id'] ?>" data-name="<?= htmlspecialchars($row['phone_number'], ENT_QUOTES, 'UTF-8') ?>" data-person-name="<?= htmlspecialchars($row['person_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" title="Editar com senha de proteção" aria-label="Editar telefone protegido">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                <?php else: ?>
                  <a href="<?= url('phone_number/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
                <?php endif; ?>
              <?php endif; ?>
              <?php if (authCan('phone_number.informations')): ?>
                <a href="<?= url('phone_number/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Ver detalhes"><i class="bi bi-eye"></i></a>
              <?php endif; ?>
              <?php if (authCan('phone_number.delete')): ?>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['phone_number'], ENT_QUOTES, 'UTF-8') ?>">
                  <i class="bi bi-trash"></i>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="app-index-results-empty">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>


<?php if ($totalPages > 1): ?>
  <div class="app-index-pagination">
  <?= paginationLinks('phone_number', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
  </div>
<?php endif; ?>

<!-- Protected Edit Authorization Modal -->
<div class="modal fade" id="protectedEditModal" tabindex="-1" aria-labelledby="protectedEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning-subtle">
        <h5 class="modal-title" id="protectedEditModalLabel">Liberar edição protegida</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <form method="POST" action="#" id="protectedEditForm" data-authorization-base-url="<?= url('phone_number/authorizeProtectedEdit'); ?>">
        <div class="modal-body">
          <p class="small">Informe a senha do usuário ID 1 para editar o telefone <strong id="protectedEditRecordName"></strong>, vinculado a <strong id="protectedEditPersonName"></strong>.</p>
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
  document.getElementById('deleteForm').action = '<?= url('phone_number/delete') ?>/' + recordId;
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
    document.getElementById('protectedEditPersonName').textContent = trigger.dataset.personName;
    form.action = form.dataset.authorizationBaseUrl.replace(/\/$/, '') + '/' + encodeURIComponent(recordId);
    password.value = '';
  });

  modal.addEventListener('shown.bs.modal', function () {
    password.focus();
  });
})();
</script>

<script src="/manager_assets/autocomplete/autocomplete.js?v=4"></script>
