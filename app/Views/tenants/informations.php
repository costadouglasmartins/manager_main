<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Inicio</a></li>
    <li class="breadcrumb-item"><a href="<?= url('tenant'); ?>">Tenants</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informacoes</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<header class="app-detail-heading">
  <span class="app-detail-heading-icon"><i class="bi bi-buildings"></i></span>
  <div class="app-detail-heading-content">
    <span class="app-detail-eyebrow">Detalhes do cadastro</span>
    <h1>Informações do tenant #<?= (int) $tenant['id']; ?></h1>
  </div>
</header>

<div class="card app-detail-card">
  <div class="card-body">
    <div class="table-responsive app-detail-table mb-0">
      <table class="table table-hover align-middle mb-0">
        <tbody>
          <tr><th>Código</th><td><?= (int) $tenant['id']; ?></td></tr>
          <tr><th>Pessoa associada</th><td><?= htmlspecialchars($tenant['associated_person_name'] ?? ('Tenant #' . (int) $tenant['id']), ENT_QUOTES, 'UTF-8') ?></td></tr>
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
  <div class="card-footer d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <a href="<?= url('tenant'); ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    <?php if (authCan('tenant.edit')): ?>
      <a href="<?= url('tenant/edit/' . $tenant['id']); ?>" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i>Editar</a>
    <?php endif; ?>
  </div>
</div>
