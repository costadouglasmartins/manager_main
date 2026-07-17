<?php
$filters = $filters ?? ['id' => '', 'financial_operation_category_id' => '', 'category_name' => '', 'user_id' => '', 'user_name' => '', 'type' => '', 'total_value' => ''];
$categories = $categories ?? [];
$types = $types ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($financial_operations) ? count($financial_operations) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];

$queryFilters = http_build_query([
  'id'                              => $filters['id'],
  'financial_operation_category_id' => $filters['financial_operation_category_id'],
  'category_name'                   => $filters['category_name'],
  'user_id'                         => $filters['user_id'],
  'user_name'                       => $filters['user_name'],
  'type'                            => $filters['type'],
  'total_value'                     => $filters['total_value'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
$activeFilterCount = count(array_filter($filters, static fn ($value): bool => (string) $value !== ''));
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Operações financeiras</li>
  </ol>
</nav>

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true"><i class="bi bi-cash-stack fs-4"></i></span>
    <div><p class="small fw-semibold text-primary text-uppercase mb-1">Financeiro</p><h1 class="h3 fw-bold mb-1">Operações financeiras</h1><p class="text-body-secondary mb-0">Consulte e gerencie créditos e débitos cadastrados.</p></div>
  </div>
  <?php if (authCan('financial_operation.create')): ?><a href="<?= url('financial_operation/create'); ?>" class="btn btn-success d-inline-flex align-items-center justify-content-center gap-2"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Nova operação</span></a><?php endif; ?>
</header>

<form action="<?= url('financial_operation'); ?>" class="card border-0 shadow-sm mb-4 p-3 p-lg-4 app-index-filters" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="app-index-filter-heading d-flex flex-wrap align-items-start justify-content-between gap-2">
    <div><h2 class="h5 fw-bold mb-1">Filtros de pesquisa</h2><p class="small text-body-secondary mb-0">Refine a lista de operações financeiras.</p></div>
    <?php if ($activeFilterCount > 0): ?><span class="badge rounded-pill text-bg-primary"><?= $activeFilterCount; ?> filtro(s) ativo(s)</span><?php endif; ?>
  </div>

  <div class="row g-3 align-items-end">

  <div class="col-12">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-2">
        <label class="form-label small fw-semibold" for="id">Código</label>
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <label class="form-label small fw-semibold" for="financial_operation_category_id">Categoria</label>
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
            <option value="<?= (int) $category['id'] ?>" <?= (string) $filters['financial_operation_category_id'] === (string) $category['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($categoryOptionLabel, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <label class="form-label small fw-semibold" for="user_name">Usuário</label>
        <input class="form-control" id="user_name" name="user_name" type="text" placeholder="Usuário" value="<?= htmlspecialchars($filters['user_name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-2">
        <label class="form-label small fw-semibold" for="type">Tipo</label>
        <select class="form-select" id="type" name="type">
          <option value="">Tipo</option>
          <?php foreach ($types as $value => $label): ?>
            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6 col-lg-2">
        <label class="form-label small fw-semibold" for="total_value">Valor total</label>
        <input class="form-control" id="total_value" name="total_value" type="number" step="0.01" min="0" placeholder="Valor total" value="<?= htmlspecialchars($filters['total_value'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

    </div>
  </div>

  <div class="col-12">
    <div class="index-search-actions">
      <button type="submit" class="btn btn-primary btn-sm index-search-action" aria-label="Pesquisar" title="Pesquisar"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Pesquisar</span></button>
      <a href="<?= url('financial_operation'); ?>" class="btn btn-outline-secondary btn-sm index-search-action" aria-label="Limpar filtros" title="Limpar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span></a>
      <?= pdfExportButton('financial_operation', $paginationQuery); ?>
      <?= excelExportButton('financial_operation', $paginationQuery); ?>
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
        <?= sortableTh('financial_operation', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation', 'category_name', 'Categoria', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation', 'user_name', 'Usuário', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation', 'type', 'Tipo', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation', 'total_value', 'Valor total', $sort, $queryFilters) ?>
        <?= sortableTh('financial_operation', 'created', 'Criado', $sort, $queryFilters) ?>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody class="table-group-divider">
      <?php if (!empty($financial_operations)): ?>
        <?php foreach ($financial_operations as $row): ?>
          <?php
          $userLabel = trim(($row['user_name'] ?? '') . ' ' . (!empty($row['username']) ? '(' . $row['username'] . ')' : ''));
          $categoryLabel = trim((string) ($row['category_name'] ?? ''));
          if (!empty($row['category_description']))
            {
            $categoryLabel .= ' - ' . trim((string) $row['category_description']);
            }
          $created = !empty($row['created']) ? date('d/m/Y H:i', strtotime($row['created'])) : '';
          ?>
          <tr>
            <td style="width:1%;"><?= (int) $row['id'] ?></td>
            <td><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($types[$row['type']] ?? $row['type'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>R$ <?= number_format((float) $row['total_value'], 2, ',', '.') ?></td>
            <td><?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?></td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('financial_operation.edit')): ?>
                <a href="<?= url('financial_operation/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('financial_operation.informations')): ?>
                <a href="<?= url('financial_operation/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Ver detalhes"><i class="bi bi-eye"></i></a>
              <?php endif; ?>
              <?php if (authCan('financial_operation.delete')): ?>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= (int) $row['id'] ?>" data-name="Operação #<?= (int) $row['id'] ?>">
                  <i class="bi bi-trash"></i>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="app-index-results-empty">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>


<?php if ($totalPages > 1): ?>
  <div class="app-index-pagination">
  <?= paginationLinks('financial_operation', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
  </div>
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
        <form method="POST" action="#" id="deleteForm" data-delete-base-url="<?= url('financial_operation/delete'); ?>" class="d-inline">
          <input type="hidden" name="id" id="recordId">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/financial_operations/index.js?v=2'); ?>"></script>
