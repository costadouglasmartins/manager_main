<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('company'); ?>">Empresas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Criar</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Nova empresa</h5>
<form action="<?= url('company/store'); ?>" method="POST">
  <!-- Company -->
  <div class="row mb-2">
    <!-- Company name -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="companyName">Nome</label>
      <input autocomplete="off" class="form-control" id="companyName" name="company[name]" maxlength="255" type="text">
    </div>
    <!-- /Company fantasy -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="companyFantasy">Fantasia</label>
      <input autocomplete="off" class="form-control" id="companyFantasy" name="company[fantasy]" maxlength="255" type="text">
    </div>
    <!-- Company document -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="companyDocument">CNPJ</label>
      <input autocomplete="off" class="form-control mask-cpf-cnpj" id="companyDocument" name="company[document]" maxlength="18" type="text">
    </div>
    <!-- /Company document -->
    <!-- Company database -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="companyDatabase">Banco de dados</label>
      <input autocomplete="off" class="form-control" id="companyDatabase" name="company[database]" maxlength="45" type="text">
    </div>
    <!-- /Company database -->
    <!-- Company active -->
    <input autocomplete="off" id="companyActive" name="company[active]" type="hidden" value="1">
    <!-- /Company active -->
  </div>
  <!-- /Company -->
  <!-- Person -->
  <h5>Nova pessoa, responsável pela empresa</h5>
  <div class="row mb-2">
    <!-- Person name -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="personName">Nome</label>
      <input autocomplete="off" class="form-control" id="personName" name="person[name]" maxlength="255" type="text">
    </div>
    <!-- /Person name -->
    <!-- Person type -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="personType">Tipo de pessoa</label>
      <select autocomplete="off" aria-label="Tipo de pessoa, física ou jurídica" class="form-select" id="personType" name="person[type]">
        <option value="individual">Pessoa física</option>
        <!-- <option value="company">Pessoa jurídica</option> -->
      </select>
    </div>
    <!-- /Person type -->
    <!-- Person document -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="personDocument">CPF</label>
      <input autocomplete="off" class="form-control mask-cpf-cnpj" id="personDocument" name="person[document]" maxlength="14" type="text">
    </div>
    <!-- /Person document -->
    <!-- Person birthdate -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="personBirthdate">Data de nascimento</label>
      <input autocomplete="off" class="form-control" id="personBirthdate" name="person[birthdate]">
    </div>
    <!-- /Person birthdate -->
    <!-- Person active -->
    <input autocomplete="off" id="personActive" name="person[active]" type="hidden" value="1">
    <!-- /Person active -->
  </div>
  <!-- /Person -->
  <!-- User -->
  <h5>Novo(a) usuário(a) associado à pessoa</h5>
  <div class="row">
    <!-- User username -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="userUsername">Usuário</label>
      <input autocomplete="off" class="form-control" id="userUsername" name="user[username]" maxlength="45" type="text">
    </div>
    <!-- /User username -->
    <!-- User password -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="userPassword">Senha</label>
      <input autocomplete="off" class="form-control" id="userPassword" name="user[password]" maxlength="255" type="password">
    </div>
    <!-- /User password -->
    <!-- User email -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="userEmail">E-mail</label>
      <input autocomplete="off" class="form-control" id="userEmail" name="user[email]" maxlength="" type="email">
    </div>
    <!-- /User email -->
    <!-- User type -->
    <div class="col-xs-12 col-md-6 col-lg-3">
      <label class="form-label" for="userType">Tipo</label>
      <select autocomplete="off" aria-label="Tipo de usuário(a) no sistema" class="form-select" id="userType" name="user[type]">
        <option value="admin" selected="selected">Administrador</option>
        <option value="root">Root</option>
        <option value="user">Usuário(a)</option>
      </select>
    </div>
    <!-- /User type -->
    <input type="hidden" name="user[active]" value="1">
  </div>
  <!-- /User -->
  <br>
  <div class="row">
    <div class="col-lg-12">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button class="btn btn-primary" type="submit">Salvar</button>
      </div>
    </div>
  </div>
</form>