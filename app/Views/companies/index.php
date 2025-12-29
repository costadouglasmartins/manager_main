<?php
// Filters and pagination variables
$filters    = $filters    ?? ['id' => '', 'name' => '', 'fantasy' => '', 'document' => '', 'active' => ''];
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$total      = $total      ?? (is_array($companies) ? count($companies) : 0);

// Create an array of filters to use on pagination
$queryFilters = http_build_query([
	'id'        => $filters['id'],
	'name'      => $filters['name'],
  'fantasy'   => $filters['fantasy'],
	'document'  => $filters['document'],
	'active'    => $filters['active']
	]);
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Empresas</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Lista de empresas</h5>

<!-- Search form -->
<form action="<?= url('company'); ?>" class="row g-3 mb-3">

  <div class="col-12 col-lg-9">
    <div class="row g-3">
      <!-- Linha 1 -->
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label class="form-label" for="id">#</label>-->
        <input class="form-control" id="id" name="id" placeholder="Código empresa" type="text" value="<?= htmlspecialchars($filters['id'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label class="form-label" for="name">Nome</label>-->
        <input class="form-control" id="name" name="name" placeholder="Nome da empresa" type="text" value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label class="form-label" for="fantasy">Fantasia</label>-->
        <input autocomplete="off" type="text" class="form-control" id="fantasy" name="fantasy" placeholder="Fantasia" value="<?= htmlspecialchars($filters['fantasy'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      
      <!-- Linha 2 -->
      <div class="col-12 col-md-6 col-lg-4">
        <!--<label for="document" class="form-label">CNPJ</label>-->
        <input autocomplete="off" type="text" class="form-control mask-cpf-cnpj" id="document" name="document" placeholder="CNPJ" value="<?= htmlspecialchars(maskCpfCnpj($filters['document']), ENT_QUOTES, 'UTF-8') ?>" maxlength="18">
      </div>
      <div class="col-12 col-md-6 col-lg-8">
        <!--<label for="active" class="form-label">Situação</label>-->
        <select autocomplete="off" class="form-control" id="active" name="active">
          <option value="">Selecione a situação...</option>
          <option value="1" <?= ($filters['active'] === '1' ? 'selected="selected"' : ''); ?>>Ativa</option>
          <option value="0" <?= ($filters['active'] === '0' ? 'selected="selected"' : ''); ?>>Inativa</options>
        </select>
      </div>
      <!--
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f6">Filtro 6</label>
        <select class="form-select" id="f6">
          <option value="">Selecione</option>
          <option>Opção 1</option>
          <option>Opção 2</option>
        </select>
      </div>
      -->
      <!-- Linha 3 
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f7">Filtro 7</label>
        <input class="form-control" id="f7" type="text">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f8">Filtro 8</label>
        <input class="form-control" id="f8" type="text">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f9">Filtro 9</label>
        <select class="form-select" id="f9">
          <option value="">Selecione</option>
          <option>Opção 1</option>
          <option>Opção 2</option>
        </select>
      </div>
      -->
      <!-- Linha 4
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f10">Filtro 10</label>
        <input class="form-control" id="f10" type="text">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f11">Filtro 11</label>
        <input class="form-control" id="f11" type="text">
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label" for="f12">Filtro 12</label>
        <select class="form-select" id="f12">
          <option value="">Selecione</option>
          <option>Opção 1</option>
          <option>Opção 2</option>
        </select>
      </div>
      -->
    </div>
  </div>

  <div class="col-12 col-lg-3">
    <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
      <!--<label>&nbsp;</label>-->
      <button type="submit" class="btn btn-primary w-100">Pesquisar</button>
      <a href="<?= url('company'); ?>" class="btn btn-secondary w-100">Limpar</a>
      <?php if (authCan('company.create')): ?>
        <a href="<?= url('company/create'); ?>" class="btn btn-success w-100">Novo(a)</a>
      <?php endif; ?>
    </div>
  </div>

</form>
<!-- /Search form -->

<hr>

<!-- Results list -->
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <tr>
      <th>#</th>
      <th>Nome / fantasia</th>
      <th>CNPJ</th>
      <th>Situação</th>
      <th>Ações</th>
    </tr>
		<?php if (!empty($companies)): ?>
			<?php foreach ($companies as $company): ?>
				<tr>
					<td style="width: 1%;"><?= $company['id'] ?></td>
					<td><?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($company['fantasy'], ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars(maskCpfCnpj($company['document']), ENT_QUOTES, 'UTF-8') ?></td>
					<td><span class="badge <?= ($company['active'] == 1 ? 'text-bg-primary': 'text-bg-secondary'); ?>"><?= ($company['active'] == 1 ? 'Ativo(a)' : 'Inativo(a)'); ?></span></td>
					<td style="width: 1%;" class="text-nowrap text-end">
						<?php if (authCan('company.edit')): ?>
							<a href="<?= url('company/edit/' . $company['id']); ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Editar"><i class="bi bi-pencil-square"></i></a>
						<?php endif; ?>
						<?php if (authCan('company.informations')): ?>
              <a href="<?= url('company/informations/' . $company['id']);?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Saiba mais"><i class="bi bi-search"></i></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else: ?>
			<tr>
				<td colspan="5" class="text-center">Nenhum registro encontrado.</td>
			</tr>
		<?php endif; ?>
	</table>
</div>
<!-- /Results list -->

<!-- Total records -->
<p class="text-muted">
	<?= $total ?> registro(s) encontrado(s)
</p>
<!-- /Total records -->

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
	<nav aria-label="Paginação de empresas" class="d-flex justify-content-center">
		<ul class="pagination">
			<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
				<a class="page-link" href="<?= url('company?page=' . max(1, $page - 1))?><?= $queryFilters ? '&' . $queryFilters : '' ?>">
					Anterior
				</a>
			</li>

			<?php for ($i = 1; $i <= $totalPages; $i++): ?>
				<li class="page-item <?= $i === (int) $page ? 'active' : '' ?>">
					<a class="page-link" href="<?= url('company?page=' . $i) ?><?= $queryFilters ? '&' . $queryFilters : '' ?>">
            <?= $i ?>
					</a>
				</li>
			<?php endfor; ?>

			<li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
				<a class="page-link" href="<?= url('company?page=' . min($totalPages, $page + 1)) ?><?= $queryFilters ? '&' . $queryFilters : '' ?>">
					Próxima
				</a>
			</li>
		</ul>
	</nav>
<?php endif; ?>
<!-- /Pagination -->