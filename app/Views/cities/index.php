<?php
$filters = $filters ?? ['id' => '', 'name' => '', 'state_id' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($cities) ? count($cities) : 0);

$queryFilters = http_build_query([
  'id'       => $filters['id'],
  'name'     => $filters['name'],
  'state_id' => $filters['state_id'],
]);
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Cidades</li>
  </ol>
</nav>

<h5>Lista de Cidades</h5>

<form action="<?= url('city'); ?>" class="row g-3 mb-3" method="get">

  <div class="col-12 col-lg-9">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-4">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-4 position-relative">
        <input id="name" type="text" class="form-control js-autocomplete" autocomplete="off" placeholder="Digite o nome da cidade..." name="name" data-endpoint="<?= url('city/autocomplete'); ?>" data-results="#cityResults" data-hidden="#id" data-minlen="1" data-param="term" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
        <ul id="cityResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <input class="form-control" id="state_id" name="state_id" type="text" placeholder="ID do estado" value="<?= htmlspecialchars($filters['state_id'], ENT_QUOTES, 'UTF-8') ?>">
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
        <th>#</th>
        <th>Nome</th>
        <th>Estado</th>
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
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('city.edit')): ?>
                <a href="<?= url('city/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('city.informations')): ?>
                <a href="<?= url('city/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="4" class="text-center">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-muted"><?= $total ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('city', $page, $totalPages, $queryFilters, 2, 'Anterior', 'Próxima') ?>
<?php endif; ?>

<script src="<?= url('assets/js/autocomplete.js?v=2'); ?>"></script>