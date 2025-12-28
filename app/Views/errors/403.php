<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('/'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Acesso negado</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<div class="row">
  <div class="col-12 col-lg-8">
    <h5 class="mb-3">Acesso negado</h5>

    <div class="alert alert-danger" role="alert">
      Você não possui permissão para executar esta ação.
      <?php if (!empty($ability)): ?>
        <div class="mt-2"><small><strong>Permissão:</strong> <?= htmlspecialchars((string) $ability); ?></small></div>
      <?php endif; ?>
    </div>

    <a class="btn btn-outline-secondary" href="<?= url('/'); ?>">Voltar ao início</a>
  </div>
</div>
