<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('state'); ?>">Estados</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre <?= htmlspecialchars($state['name'], ENT_QUOTES, 'UTF-8'); ?></li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Dados do estado</h5>
<div class="table-responsive mb-3">
  <table class="table table-hover align-middle">
    <tbody>
      <tr>
        <th class="text-muted" style="width: 220px;">Nome</th>
        <td><?= htmlspecialchars($state['name'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Sigla</th>
        <td><?= htmlspecialchars($state['acronym'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">País</th>
        <td><?= htmlspecialchars($state['country_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>
</div>
