<div class="row justify-content-center align-items-center">
  <div class="col-12 col-sm-10 col-md-6 col-lg-4">
    <h3 class="text-center">Identifique-se</h3>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="alert alert-warning" role="alert">
      Este sistema salva cookies de autenticação para manter seu login neste navegador por até 30 dias. Ao sair pelo menu, esse cookie será removido.
    </div>
    <form method="post" action="<?= url('user/login'); ?>">
      <div class="mb-3">
        <input autocomplete="off" id="email" name="email" type="email" class="form-control" placeholder="E-mail" required="required" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
      </div>
      <div class="mb-3">
        <input autocomplete="off" id="password" name="password" type="password" class="form-control" placeholder="Senha" required="required">
      </div>
      <?php $rememberChecked = ($_SERVER['REQUEST_METHOD'] !== 'POST') || !empty($_POST['remember_me']); ?>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" value="1" <?= $rememberChecked ? 'checked' : ''; ?>>
        <label class="form-check-label" for="remember_me">Manter conectado neste navegador</label>
      </div>
      <button class="btn btn-primary w-100" type="submit">Autenticar</button>
    </form>
  </div>
</div>
