<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('person'); ?>">Pessoas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre <?= $data['people']['name']; ?></li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Dados gerais da pessoa</h5><div class="table-responsive mb-3">
<table class="table table-hover align-middle">
  <tbody>
    <tr>
      <th class="text-muted" style="width: 220px;">Nome</th>
      <td><?= htmlspecialchars($data['people']['name']); ?></td>
    </tr>
    <tr>
      <th class="text-muted">Tipo</th>
      <td><?= ($data['people']['type'] === 'individual' ? 'Pessoa física' : 'Pessoa jurídica'); ?></td>
    </tr>
    <tr>
      <th class="text-muted">Documento</th>
      <td><?= maskCpfCnpj($data['people']['document']); ?></td>
    </tr>
    <tr>
      <th class="text-muted">Ativa</th>
      <td><?= ((int)$data['people']['active'] === 1 ? 'Sim' : 'Não'); ?></td>
    </tr>
    <tr>
      <th class="text-muted">Data de nascimento</th>
      <td><?= date('d/m/Y', strtotime($data['people']['birthdate'])); ?></td>
    </tr>
  </tbody>
</table>