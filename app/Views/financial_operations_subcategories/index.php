<?php
$filters = $filters ?? ['id' => '', 'financial_operation_category_id' => '', 'category_name' => '', 'name' => ''];
$categories = $categories ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($financial_operations_subcategories) ? count($financial_operations_subcategories) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];

$queryFilters = http_build_query([
  'id'                              => $filters['id'],
  'financial_operation_category_id' => $filters['financial_operation_category_id'],
  'category_name'                   => $filters['category_name'],
  'name'                            => $filters['name'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Subcategorias financeiras</li>
  </ol>
</nav>

<form action="<?= url('financial_operation_subcategory'); ?>" class="index-search-card mb-4" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="row g-3 align-items-center">

  <div class="col-12 col-lg-9">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-3">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-5">
        <select class="form-select" id="financial_operation_category_id" name="financial_operation_category_id">
          <option value="">Categoria</option>
          <?php foreach ($categories as $category): ?>
            <?php
            $categoryOptionLabel = trim((string) ($category['name'] ?? ''));
            if (!empty($category['description']))
              {
              $categoryOptionLabel .= ' - ' . trim((string) $category['description']);
              }
            ?>
            <option value="<?= (int) $category['id']; ?>" <?= (string) $filters['financial_operation_category_id'] === (string) $category['id'] ? 'selected' : ''; ?>>
              <?= htmlspecialchars($categoryOptionLabel, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <input class="form-control" id="name" name="name" type="text" placeholder="Nome" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('financial_operation_subcategory'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?= pdfExportButton('financial_operation_subcategory', $paginationQuery); ?>
      <?= excelExportButton('financial_operation_subcategory', $paginationQuery); ?>
      <?php if (authCan('financial_operation_subcategory.create')): ?>
        <a href="<?= url('financial_operation_subcategory/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
      <?php endif; ?>
    </div>
  </div>

  </div>

</form>

<hr>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <?= sortableTh('financial_operation_subcategory', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation_subcategory', 'category_name', 'Categoria', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation_subcategory', 'name', 'Nome', $sort, $queryFilters) ?>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($financial_operations_subcategories)): ?>
        <?php foreach ($financial_operations_subcategories as $row): ?>
          <tr>
            <td style="width:1%;"><?= (int) $row['id'] ?></td>
            <td><?= htmlspecialchars($row['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('financial_operation_subcategory.edit')): ?>
                <a href="<?= url('financial_operation_subcategory/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('financial_operation_subcategory.informations')): ?>
                <a href="<?= url('financial_operation_subcategory/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
              <?php if (authCan('financial_operation_subcategory.delete')): ?>
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= (int) $row['id'] ?>" data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>">
                  <i class="bi bi-trash"></i>
                </button>
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

<p class="text-muted"><?= (int) $total ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('financial_operation_subcategory', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
<?php endif; ?>

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
        <form method="POST" action="#" id="deleteForm" data-delete-base-url="<?= url('financial_operation_subcategory/delete'); ?>" style="display: inline;">
          <input type="hidden" name="id" id="recordId">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/financial_operations_subcategories/index.js?v=1'); ?>"></script>
