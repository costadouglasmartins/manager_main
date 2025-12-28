<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('company'); ?>">Empresas</a></li>
    <li class="breadcrumb-item"><a href="<?= url('person'); ?>">Pessoas</a></li>
    <li class="breadcrumb-item"><a href="<?= url('user'); ?>">Usuários(as)</a></li>
    <li class="breadcrumb-item active" aria-current="page">Usuário(a) - <?= $user['username']; ?> - editar</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- User -->
<h5>Editar Usuário</h5>
<form method="POST" action="<?= url('/user/update/' . $user['id']); ?>"> 
  <div class="row">
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="username">Usuário(a):</label>
      <input autocomplete="off" class="form-control" type="text" id="username" name="username" value="<?= $user['username']; ?>" readonly="readonly" required="required" value="<?= $user['username']; ?>">
    </div>
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="password">Senha</label>
      <input autocomplete="off" class="form-control" type="password" id="password" name="password" required="required">
    </div>
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="email">Email:</label><br>
      <input autocomplete="off" class="form-control" type="email" id="email" name="email" value="<?= $user['email']; ?>" required="required">
    </div>
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="type">Tipo</label>
      <select autocomplete="off" class="form-control" id="type" name="type" required="required">
        <option value="">Selecione</option>
        <option value="root" <?= ($user['type'] === 'root' ? 'selected="selected"' : '' ); ?>>Root</option>
        <option value="admin" <?= ($user['type'] === 'admin' ? 'selected="selected"' : ''); ?>>Admin</option>
        <option value="user" <?= ($user['type'] === 'user' ? 'selected="selected"' : ''); ?>>Usuário</option>
      </select>
    </div>
    <div class="col-md-6 col-lg-12">
      <label class="form-label" for="active">Ativo</label>
      <select autocomplete="off" class="form-control" id="active" name="active" required="required">
        <option value="">Selecione</option>
        <option value="1" <?= ($user['active'] === 1 ? 'selected="selected"' : '' ); ?>>Ativo</option>
        <option value="0" <?= ($user['active'] === 0 ? 'selected="selected"' : ''); ?>>Inativo</option>
      </select>
    </div>
  </div>
  <br>
  <div class="row">
    <div class="col-lg-12">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button class="btn btn-primary" type="submit">Salvar</button>
      </div>
    </div>
  </div>
</form>
<!-- /User -->
