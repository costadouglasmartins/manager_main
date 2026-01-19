<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('country'); ?>">Países</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre <?= $data['countries']['name']; ?></li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Dados gerais da pessoa</h5><div class="table-responsive mb-3">
<table class="table table-hover align-middle">
  <tbody>
    <tr>
      <th class="text-muted" style="width: 220px;">Nome</th>
      <td><?= htmlspecialchars($data['countries']['name']); ?></td>
    </tr>
    <tr>
      <th class="text-muted">Acronym</th>
      <td><?= $data['countries']['acronym']; ?></td>
    </tr>
  </tbody>
</table>