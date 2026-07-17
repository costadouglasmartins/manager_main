<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('country'); ?>">Países</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre <?= htmlspecialchars($data['countries']['name'], ENT_QUOTES, 'UTF-8'); ?></li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<header class="app-detail-heading">
  <span class="app-detail-heading-icon"><i class="bi bi-globe-americas"></i></span>
  <div class="app-detail-heading-content">
    <span class="app-detail-eyebrow">Detalhes do cadastro</span>
    <h1>Dados do país</h1>
  </div>
</header>
<div class="table-responsive app-detail-table">
<table class="table table-hover align-middle mb-0">
  <tbody>
    <tr>
      <th>Nome</th>
      <td><?= htmlspecialchars($data['countries']['name'], ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
    <tr>
      <th>Sigla</th>
      <td><?= htmlspecialchars($data['countries']['acronym'], ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
  </tbody>
</table>
</div>
