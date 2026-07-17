<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('person'); ?>">Pessoas</a></li>
    <li class="breadcrumb-item"><a href="<?= url('user'); ?>">Usuários(as)</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre <?= htmlspecialchars($data['user']['username'], ENT_QUOTES, 'UTF-8'); ?></li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<header class="app-detail-heading">
  <span class="app-detail-heading-icon"><i class="bi bi-person-badge"></i></span>
  <div class="app-detail-heading-content">
    <span class="app-detail-eyebrow">Detalhes do cadastro</span>
    <h1>Dados do usuário</h1>
  </div>
</header>
<div class="table-responsive app-detail-table">
<table class="table table-hover align-middle mb-0">
  <tbody>
    <tr>
      <th>Nome de usuário</th>
      <td><?= htmlspecialchars($data['user']['username'], ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
    <tr>
      <th class="text-muted">Email</th>
      <td><?= htmlspecialchars($data['user']['email'], ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
    <tr>
      <th class="text-muted">Tipo</th>
      <td><?php
        switch($data['user']['type'])
          {
          case 'root':
            echo 'Root (apenas sistema gerenciador central)';
          break;
          case 'admin':
            echo 'Administrador (Funcionários(as) da empresa)';
          break;
          case 'user':
            echo 'Usuário(a)';
          break;
          } ?>
        </td>
    </tr>
    <tr>
      <th class="text-muted">Ativa</th>
      <td>
        <span class="badge <?= (int) $data['user']['active'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
          <?= (int) $data['user']['active'] === 1 ? 'Ativo' : 'Inativo'; ?>
        </span>
      </td>
    </tr>
  </tbody>
</table>
</div>
