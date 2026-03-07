<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('address'); ?>">Endereços</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre o endereço #<?= (int) $address['id']; ?></li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Dados do endereço</h5>
<div class="table-responsive mb-3">
  <table class="table table-hover align-middle">
    <tbody>
      <tr>
        <th class="text-muted" style="width: 220px;">CEP</th>
        <td><?= htmlspecialchars($address['zip_code'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Logradouro</th>
        <td><?= htmlspecialchars($address['street'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Número</th>
        <td><?= htmlspecialchars($address['number'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Observações</th>
        <td><?= htmlspecialchars($address['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Bairro</th>
        <td><?= htmlspecialchars($address['neighborhood_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Pessoa</th>
        <td><?= htmlspecialchars($address['person_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>
</div>
