<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Inicio</a></li>
    <li class="breadcrumb-item"><a href="<?= url('tenant'); ?>">Tenants</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informacoes</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Informacoes do tenant #<?= $tenant['id'] ?></h5>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <tbody>
          <tr><th style="width: 30%;">Codigo</th><td><?= $tenant['id'] ?></td></tr>
          <tr><th>Nome</th><td><?= htmlspecialchars($tenant['name']) ?></td></tr>
          <tr><th>Nome fantasia</th><td><?= htmlspecialchars($tenant['fantasy'] ?? '') ?></td></tr>
          <tr><th>Documento</th><td><?= htmlspecialchars($tenant['document'] ? maskCpfCnpj($tenant['document']) : '') ?></td></tr>
          <tr><th>Banco de dados</th><td><code>manager_<?= htmlspecialchars($tenant['database_number']) ?></code></td></tr>
          <tr>
            <th>Situacao</th>
            <td>
              <?php if ($tenant['status'] === 'active'): ?>
                <span class="badge text-bg-success">Ativo</span>
              <?php elseif ($tenant['status'] === 'suspended'): ?>
                <span class="badge text-bg-danger">Suspenso</span>
              <?php else: ?>
                <span class="badge text-bg-warning">Pendente</span>
              <?php endif; ?>
            </td>
          </tr>
          <tr><th>Observacoes</th><td><?= nl2br(htmlspecialchars($tenant['observations'] ?? '')) ?></td></tr>
          <tr><th>Criado em</th><td><?= $tenant['created'] ? maskDateBr(substr($tenant['created'], 0, 10)) : '' ?></td></tr>
          <tr><th>Modificado em</th><td><?= $tenant['modified'] ? maskDateBr(substr($tenant['modified'], 0, 10)) : '' ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <a href="<?= url('tenant'); ?>" class="btn btn-secondary">Voltar</a>
    <?php if (authCan('tenant.edit')): ?>
      <a href="<?= url('tenant/edit/' . $tenant['id']); ?>" class="btn btn-primary">Editar</a>
    <?php endif; ?>
  </div>
</div>
