<div class="row justify-content-center align-items-center">
  <div class="col-12 col-sm-10 col-md-6 col-lg-4">
    <h3 class="text-center">Identifique-se</h3>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= url('user/login'); ?>">
      <div class="mb-3">
        <input autocomplete="off" id="email" name="email" type="email" class="form-control" placeholder="E-mail" required="required" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
      </div>
      <div class="mb-3">
        <input autocomplete="off" id="password" name="password" type="password" class="form-control" placeholder="Senha" required="required">
      </div>
      <button class="btn btn-primary w-100" type="submit">Autenticar</button>
    </form>
  </div>
</div>