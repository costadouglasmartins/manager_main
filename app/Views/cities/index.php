<?php
$filters = array_merge([
  'id'           => '',
  'name'         => '',
  'country_id'   => '',
  'country_name' => '',
  'state_id'     => '',
  'state_name'   => '',
], $filters ?? []);
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($cities) ? count($cities) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];

$queryFilters = http_build_query([
  'id'           => $filters['id'],
  'name'         => $filters['name'],
  'country_id'   => $filters['country_id'],
  'country_name' => $filters['country_name'],
  'state_id'     => $filters['state_id'],
  'state_name'   => $filters['state_name'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Cidades</li>
  </ol>
</nav>

<h5>Lista de Cidades</h5>

<form action="<?= url('city'); ?>" class="row g-3 mb-3 index-search-card" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="col-12 col-lg-9">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-12">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-6 position-relative">
        <input type="hidden" id="country_id" name="country_id" value="<?= htmlspecialchars($filters['country_id'], ENT_QUOTES, 'UTF-8') ?>">
        <input id="countryName" type="text" class="form-control js-autocomplete" autocomplete="off" placeholder="País" name="country_name" data-endpoint="<?= url('country/autocomplete'); ?>" data-results="#countryResults" data-hidden="#country_id" data-minlen="1" data-param="term" value="<?= htmlspecialchars($filters['country_name'], ENT_QUOTES, 'UTF-8') ?>">
        <ul id="countryResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
      </div>

      <div class="col-12 col-md-6 col-lg-6 position-relative">
        <input type="hidden" id="state_id" name="state_id" value="<?= htmlspecialchars($filters['state_id'], ENT_QUOTES, 'UTF-8') ?>">
        <input id="stateName" type="text" class="form-control js-autocomplete" autocomplete="off" placeholder="Estado" name="state_name" data-endpoint="<?= url('state/autocomplete'); ?>" data-results="#stateResults" data-hidden="#state_id" data-minlen="1" data-param="term" data-extra='{"country_id":"#country_id","country_name":"#countryName"}' value="<?= htmlspecialchars($filters['state_name'], ENT_QUOTES, 'UTF-8') ?>">
        <ul id="stateResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
      </div>

      <div class="col-12 col-md-6 col-lg-12 position-relative">
        <input id="name" type="text" class="form-control js-autocomplete" autocomplete="off" placeholder="Cidade" name="name" data-endpoint="<?= url('city/autocomplete'); ?>" data-results="#cityResults" data-hidden="#id" data-minlen="1" data-param="term" data-extra='{"country_id":"#country_id","country_name":"#countryName","state_id":"#state_id","state_name":"#stateName"}' value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
        <ul id="cityResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
      </div>

    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('city'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?php if (authCan('city.create')): ?>
        <a href="<?= url('city/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
      <?php endif; ?>
    </div>
  </div>

</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <?= sortableTh('city', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('city', 'name', 'Nome', $sort, $queryFilters) ?>
        <?= sortableTh('city', 'state_name', 'Estado', $sort, $queryFilters) ?>
        <?= sortableTh('city', 'country_name', 'País', $sort, $queryFilters) ?>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($cities)): ?>
        <?php foreach ($cities as $row): ?>
          <tr>
            <td style="width:1%;"><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['state_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['country_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('city.edit')): ?>
                <a href="<?= url('city/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('city.informations')): ?>
                <a href="<?= url('city/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
              <?php if (authCan('city.delete')): ?>
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
  <?= paginationLinks('city', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
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
        <form method="POST" action="#" id="deleteForm" data-delete-url="<?= url('city/delete') ?>" style="display: inline;">
          <input type="hidden" name="id" id="recordId">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/autocomplete.js?v=2'); ?>"></script>
<script src="<?= url('assets/js/cities/index.js?v=1'); ?>"></script>
