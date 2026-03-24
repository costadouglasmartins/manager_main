<?php
$filters = $filters ?? ['id' => '', 'name' => '', 'city_id' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($neighborhoods) ? count($neighborhoods) : 0);

$queryFilters = http_build_query([
  'id'      => $filters['id'],
  'name'    => $filters['name'],
  'city_id' => $filters['city_id'],
]);
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Bairros</li>
  </ol>
</nav>

<h5>Lista de Bairros</h5>

<form action="<?= url('neighborhood'); ?>" class="row g-3 mb-3" method="get">

  <div class="col-12 col-lg-9">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-4">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <input class="form-control" id="name" name="name" type="text" placeholder="Nome" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <input class="form-control" id="city_id" name="city_id" type="text" placeholder="ID da cidade" value="<?= htmlspecialchars($filters['city_id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('neighborhood'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?php if (authCan('neighborhood.create')): ?>
        <a href="<?= url('neighborhood/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
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
        <th>Cidade</th>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($neighborhoods)): ?>
        <?php foreach ($neighborhoods as $row): ?>
          <tr>
            <td style="width:1%;"><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['city_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('neighborhood.edit')): ?>
                <a href="<?= url('neighborhood/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('neighborhood.informations')): ?>
                <a href="<?= url('neighborhood/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
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
  <div class="overflow-auto">
  <nav aria-label="Paginação de bairros">
    <ul class="pagination pagination-sm flex-nowrap mb-0">
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= url('neighborhood?page=' . max(1, $page - 1)) ?><?= $queryFilters ? '&' . $queryFilters : '' ?>">Anterior</a></li>

      <?php
      $delta = 2;
      $pgList = array_unique(array_merge([1], range(max(2, $page - $delta), min($totalPages - 1, $page + $delta)), $totalPages > 1 ? [$totalPages] : []));
      sort($pgList);
      $prevPg = null;
      foreach ($pgList as $p):
        if ($prevPg !== null && $p - $prevPg > 1): ?>
          <li class="page-item disabled"><span class="page-link">...</span></li>
        <?php endif; ?>
        <li class="page-item <?= $p === (int) $page ? 'active' : '' ?>"><a class="page-link" href="<?= url('neighborhood?page=' . $p) ?><?= $queryFilters ? '&' . $queryFilters : '' ?>"><?= $p ?></a></li>
      <?php $prevPg = $p; endforeach; ?>

      <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= url('neighborhood?page=' . min($totalPages, $page + 1)) ?><?= $queryFilters ? '&' . $queryFilters : '' ?>">Próxima</a></li>
    </ul>
  </nav>
</div>
<?php endif; ?>
