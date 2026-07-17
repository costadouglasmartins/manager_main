<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('phone_number'); ?>">Telefones</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre <?= htmlspecialchars($phone_number['phone_number'], ENT_QUOTES, 'UTF-8'); ?></li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<header class="app-detail-heading">
  <span class="app-detail-heading-icon"><i class="bi bi-telephone"></i></span>
  <div class="app-detail-heading-content">
    <span class="app-detail-eyebrow">Detalhes do cadastro</span>
    <h1>Dados do telefone</h1>
  </div>
</header>
<div class="table-responsive app-detail-table">
  <table class="table table-hover align-middle mb-0">
    <tbody>
      <tr>
        <th>Telefone</th>
        <td><?= htmlspecialchars($phone_number['phone_number'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Observações</th>
        <td><?= htmlspecialchars($phone_number['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Pessoa</th>
        <td><?= htmlspecialchars($phone_number['person_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>
</div>
