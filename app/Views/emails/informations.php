<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('email'); ?>">E-mails</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre <?= htmlspecialchars($email['email'], ENT_QUOTES, 'UTF-8'); ?></li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Dados do e-mail</h5>
<div class="table-responsive mb-3">
  <table class="table table-hover align-middle">
    <tbody>
      <tr>
        <th class="text-muted" style="width: 220px;">E-mail</th>
        <td><?= htmlspecialchars($email['email'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Observações</th>
        <td><?= htmlspecialchars($email['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Pessoa</th>
        <td><?= htmlspecialchars($email['person_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>
</div>
