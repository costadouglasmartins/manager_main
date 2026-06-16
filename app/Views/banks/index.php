<?php
$filters = $filters ?? ['id' => '', 'number' => '', 'name' => '', 'active' => ''];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($banks) ? count($banks) : 0);
$sort = $sort ?? ['sort' => 'id', 'direction' => 'desc'];

$queryFilters = http_build_query([
  'id'     => $filters['id'],
  'number' => $filters['number'],
  'name'   => $filters['name'],
  'active' => $filters['active'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);

$activeLabel = static function ($value): string {
  return ((int) $value === 1) ? 'Ativo' : 'Inativo';
};
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Bancos</li>
  </ol>
</nav>

<form action="<?= url('bank'); ?>" class="index-search-card mb-4" method="get">
  <?= sortHiddenInputs($sort) ?>

  <div class="row g-3 align-items-center">

  <div class="col-12 col-lg-9">
    <div class="row g-3">

      <div class="col-12 col-md-6 col-lg-6">
        <input class="form-control" id="id" name="id" type="text" placeholder="Código" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-6">
        <input class="form-control" id="number" maxlength="45" name="number" type="text" placeholder="Número" value="<?= htmlspecialchars($filters['number'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-6">
        <input class="form-control" id="name" maxlength="255" name="name" type="text" placeholder="Nome" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="col-12 col-md-6 col-lg-6">
        <select class="form-select" id="active" name="active">
          <option value="" <?= $filters['active'] === '' ? 'selected' : ''; ?>>Todos</option>
          <option value="1" <?= $filters['active'] === '1' ? 'selected' : ''; ?>>Ativos</option>
          <option value="0" <?= $filters['active'] === '0' ? 'selected' : ''; ?>>Inativos</option>
        </select>
      </div>

    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('bank'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?= pdfExportButton('bank', $paginationQuery); ?>
      <?= excelExportButton('bank', $paginationQuery); ?>
      <?php if (authCan('bank.create')): ?>
        <a href="<?= url('bank/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
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
        <?= sortableTh('bank', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('bank', 'number', 'Número', $sort, $queryFilters) ?>
        <?= sortableTh('bank', 'name', 'Nome', $sort, $queryFilters) ?>
        <?= sortableTh('bank', 'active', 'Status', $sort, $queryFilters) ?>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($banks)): ?>
        <?php foreach ($banks as $row): ?>
          <tr>
            <td style="width:1%;"><?= (int) $row['id'] ?></td>
            <td><?= htmlspecialchars($row['number'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <span class="badge <?= ((int) $row['active'] === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                <?= $activeLabel($row['active']); ?>
              </span>
            </td>
            <td style="width:1%;" class="text-nowrap text-end">
              <?php if (authCan('bank.edit')): ?>
                <a href="<?= url('bank/edit/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
              <?php endif; ?>
              <?php if (authCan('bank.informations')): ?>
                <a href="<?= url('bank/informations/' . $row['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
              <?php endif; ?>
              <?php if (authCan('bank.delete')): ?>
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= (int) $row['id'] ?>" data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>">
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

<p class="text-muted"><?= (int) $total ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('bank', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
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
        <form method="POST" action="#" id="deleteForm" data-delete-base-url="<?= url('bank/delete'); ?>" style="display: inline;">
          <input type="hidden" name="id" id="recordId">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/banks/index.js?v=1'); ?>"></script>
