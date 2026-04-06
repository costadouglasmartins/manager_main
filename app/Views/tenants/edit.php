<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Inicio</a></li>
    <li class="breadcrumb-item"><a href="<?= url('tenant'); ?>">Tenants</a></li>
    <li class="breadcrumb-item active" aria-current="page">Editar tenant</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Editar tenant #<?= $tenant['id'] ?></h5>

<form action="<?= url('tenant/update/' . $tenant['id']); ?>" method="POST" class="needs-validation" novalidate>
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label" for="name">Nome</label>
          <input autocomplete="off" class="form-control" id="name" name="name" placeholder="Nome do tenant" type="text" value="<?= htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8') ?>" required />
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="fantasy">Nome fantasia</label>
          <input autocomplete="off" class="form-control" id="fantasy" name="fantasy" placeholder="Nome fantasia" type="text" value="<?= htmlspecialchars($tenant['fantasy'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="document">Documento (CPF/CNPJ)</label>
          <input autocomplete="off" class="form-control mask-cpf-cnpj" id="document" name="document" placeholder="CPF ou CNPJ" type="text" value="<?= htmlspecialchars($tenant['document'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Banco de dados</label>
          <input class="form-control" type="text" value="manager_<?= htmlspecialchars($tenant['database_number']) ?>" disabled />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="status">Situacao</label>
          <select autocomplete="off" class="form-select" id="status" name="status">
            <option value="active" <?= ($tenant['status'] === 'active' ? 'selected' : '') ?>>Ativo</option>
            <option value="suspended" <?= ($tenant['status'] === 'suspended' ? 'selected' : '') ?>>Suspenso</option>
            <option value="pending" <?= ($tenant['status'] === 'pending' ? 'selected' : '') ?>>Pendente</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label" for="observations">Observacoes</label>
          <textarea class="form-control" id="observations" name="observations" rows="3"><?= htmlspecialchars($tenant['observations'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
      </div>
    </div>
    <div class="card-footer bg-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <a href="<?= url('tenant'); ?>" class="btn btn-secondary">Voltar</a>
      <button type="submit" class="btn btn-primary">Salvar alteracoes</button>
    </div>
  </div>
</form>
