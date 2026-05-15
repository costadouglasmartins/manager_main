<?php
$filters = array_merge(['id' => '', 'name' => '', 'acronym' => '', 'country_id' => '', 'country_name' => ''], $filters ?? []);
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($states) ? count($states) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];

$queryFilters = http_build_query([
  'id'           => $filters['id'],
  'name'         => $filters['name'],
  'acronym'      => $filters['acronym'],
  'country_id'   => $filters['country_id'],
  'country_name' => $filters['country_name'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Estados</li>
  </ol>
</nav>

<h5>Lista de Estados</h5>

<form action="<?= url('state'); ?>" class="row g-3 mb-3 index-search-card" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="col-12 col-lg-9">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-6">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-6">
        <input class="form-control" id="name" name="name" type="text" placeholder="Nome" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-6">
        <input class="form-control" id="acronym" name="acronym" type="text" placeholder="Sigla" value="<?= htmlspecialchars($filters['acronym'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-6 position-relative">
        <input type="hidden" id="country_id" name="country_id" value="<?= htmlspecialchars($filters['country_id'], ENT_QUOTES, 'UTF-8') ?>">
        <input id="countryName" type="text" class="form-control js-autocomplete" autocomplete="off" placeholder="País" name="country_name" data-endpoint="<?= url('country/autocomplete'); ?>" data-results="#countryResults" data-hidden="#country_id" data-minlen="1" data-param="term" value="<?= htmlspecialchars($filters['country_name'], ENT_QUOTES, 'UTF-8') ?>">
        <ul id="countryResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
      </div>

    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('state'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?php if (authCan('state.create')): ?>
        <a href="<?= url('state/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
      <?php endif; ?>
    </div>
  </div>

</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <?= sortableTh('state', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('state', 'name', 'Nome', $sort, $queryFilters) ?>
        <?= sortableTh('state', 'acronym', 'Sigla', $sort, $queryFilters) ?>
        <?= sortableTh('state', 'country_name', 'País', $sort, $queryFilters) ?>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($states)): ?>
        <?php foreach ($states as $row): ?>
          <tr>
            <td style="width:1%;"><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['acronym'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['country_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('state.edit')): ?>
                <a href="<?= url('state/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('state.informations')): ?>
                <a href="<?= url('state/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
              <?php if (authCan('state.delete')): ?>
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>">
                  <i class="bi bi-trash"></i>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="text-center">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-muted"><?= $total ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('state', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
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
        <form method="POST" action="#" id="deleteForm" data-delete-url="<?= url('state/delete') ?>" style="display: inline;">
          <input type="hidden" name="id" id="recordId">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/autocomplete.js?v=2'); ?>"></script>
<script src="<?= url('assets/js/states/index.js?v=1'); ?>"></script>
