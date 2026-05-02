<?php
$filters = $filters ?? ['id' => '', 'street' => '', 'zip_code' => '', 'person_id' => '', 'person_name' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($addresses) ? count($addresses) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];

$queryFilters = http_build_query([
  'id'          => $filters['id'],
  'street'      => $filters['street'],
  'zip_code'    => $filters['zip_code'],
  'person_id'   => $filters['person_id'],
  'person_name' => $filters['person_name'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Endereços</li>
  </ol>
</nav>

<h5>Lista de Endereços</h5>

<form action="<?= url('address'); ?>" class="row g-3 mb-3" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="col-12 col-lg-9">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="zip_code" name="zip_code" type="text" placeholder="CEP" value="<?= htmlspecialchars($filters['zip_code'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="street" name="street" type="text" placeholder="Logradouro" value="<?= htmlspecialchars($filters['street'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-3 position-relative">
        <input type="hidden" id="person_id" name="person_id" value="<?= htmlspecialchars($filters['person_id'], ENT_QUOTES, 'UTF-8') ?>">
        <input id="person_name" name="person_name" type="text" class="form-control js-autocomplete" autocomplete="off"
          placeholder="Digite o nome da pessoa..."
          data-endpoint="<?= url('person/autocomplete'); ?>"
          data-results="#personResults"
          data-hidden="#person_id"
          data-minlen="1"
          data-param="term"
          value="<?= htmlspecialchars($filters['person_name'], ENT_QUOTES, 'UTF-8') ?>">
        <ul id="personResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
      </div>

    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('address'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?php if (authCan('address.create')): ?>
        <a href="<?= url('address/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
      <?php endif; ?>
    </div>
  </div>

</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <?= sortableTh('address', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('address', 'zip_code', 'CEP', $sort, $queryFilters) ?>
        <?= sortableTh('address', 'street', 'Logradouro', $sort, $queryFilters) ?>
        <?= sortableTh('address', 'number', 'Número', $sort, $queryFilters) ?>
        <?= sortableTh('address', 'person_name', 'Pessoa', $sort, $queryFilters) ?>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($addresses)): ?>
        <?php foreach ($addresses as $row): ?>
          <tr>
            <td style="width:1%;"><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['zip_code'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['street'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['number'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['person_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('address.edit')): ?>
                <a href="<?= url('address/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('address.informations')): ?>
                <a href="<?= url('address/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
              <?php if (authCan('address.delete')): ?>
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['street'], ENT_QUOTES, 'UTF-8') ?>">
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
    </tbody>
  </table>
</div>

<p class="text-muted"><?= $total ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('address', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
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
  document.getElementById('deleteForm').action = '<?= url('address/delete') ?>/' + recordId;
});
</script>

<script src="<?= url('assets/js/autocomplete.js?v=2'); ?>"></script>
