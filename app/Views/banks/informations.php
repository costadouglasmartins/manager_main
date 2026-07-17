<?php
$formatDateTime = static function ($value): string {
  $value = trim((string) $value);
  if ($value === '' || $value === '0000-00-00 00:00:00')
    {
    return '';
    }

  try
    {
    return (new DateTimeImmutable($value))->format('d/m/Y H:i');
    }
  catch (Throwable $e)
    {
    return $value;
    }
};

$activeLabel = static function ($value): string {
  return ((int) $value === 1) ? 'Ativo' : 'Inativo';
};
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('bank'); ?>">Bancos</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre <?= htmlspecialchars($bank['name'], ENT_QUOTES, 'UTF-8'); ?></li>
  </ol>
</nav>

<header class="app-detail-heading">
  <span class="app-detail-heading-icon"><i class="bi bi-bank"></i></span>
  <div class="app-detail-heading-content">
    <span class="app-detail-eyebrow">Detalhes do cadastro</span>
    <h1>Dados do banco</h1>
  </div>
</header>
<div class="table-responsive app-detail-table">
  <table class="table table-hover align-middle mb-0">
    <tbody>
      <tr>
        <th>Código</th>
        <td><?= (int) $bank['id']; ?></td>
      </tr>
      <tr>
        <th class="text-muted">Número</th>
        <td><?= htmlspecialchars($bank['number'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Nome</th>
        <td><?= htmlspecialchars($bank['name'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Status</th>
        <td>
          <span class="badge <?= ((int) $bank['active'] === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>">
            <?= $activeLabel($bank['active']); ?>
          </span>
        </td>
      </tr>
      <tr>
        <th class="text-muted">Criado em</th>
        <td><?= htmlspecialchars($formatDateTime($bank['created'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Alterado em</th>
        <td><?= htmlspecialchars($formatDateTime($bank['modified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>
</div>

<div class="d-flex flex-wrap gap-2">
  <a href="<?= url('bank'); ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>
