<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('person'); ?>">Pessoas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Nova pessoa</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Nova pessoa</h5>
<form action="<?= url('person/store'); ?>" method="POST">
  <div class="row mb-2">
    <!-- Person name -->
    <div class="col-xs-12 col-md-12 col-lg-12 mb-4">
      <input autocomplete="off" class="form-control" id="personName" name="person[name]" placeholder="Nome da pessoa" type="text" required="required">
    </div>
    <!-- /Person name -->
    <!-- Person type -->
    <div class="col-xs-12 col-md-12 col-lg-12 mb-4">
      <select autocomplete="off" class="form-control" id="personType" name="person[type]" required="required">
        <option value="">Selecione um tipo de pessoa...</option>
        <option value="individual">Pessoa física</option>
        <option value="company">Pessoa jurídica</option>
      </select>
    </div>
    <!-- /Person type -->
    <!-- Person document -->
    <div class="col-xs-12 col-md-12 col-lg-12 mb-4">
      <input autocomplete="off" class="form-control mask-cpf-cnpj" id="personDocument" name="person[document]" maxlength="18" placeholder="Documento CPF/CNPJ" type="text">
    </div>
    <!-- /Person document -->
    <!-- Person birthdate -->
    <div class="col-xs-12 col-md-12 col-lg-12 mb-4">
      <input autocomplete="off" class="form-control mask-date" id="personBirthdate" name="person[birthdate]" maxlength="10" placeholder="Data de nascimento" type="text">
    </div>
    <!-- /Person birthdate -->
    <!-- Person active -->
    <input autocomplete="off" id="personActive" name="person[active]" type="hidden" value="1">
    <!-- /Person active -->
  </div>
  <h5>Novo(a) usuário(a)</h5>
  <div class="row mt-2">
    <!-- User username -->
    <div class="col-xs-12 col-md-12 col-lg-12 mb-4">
      <input autocomplete="off" class="form-control" id="username" name="user[username]" placeholder="Nome de usuário" type="text">
    </div>
    <!-- /User username -->
    <!--  User password -->
    <div class="col-xs-12 col-md-12 col-lg-12 mb-4"> 
      <input autocomplete="off" class="form-control" id="userPassword" name="user[password]" placeholder="Senha" type="password">
    </div>
    <!-- /User password -->
    <!-- User e-mail -->
    <div class="col-xs-12 col-md-12 col-lg-12 mb-4">
      <input autocomplete="off" class="form-control" id="userEmail" name="user[email]" placeholder="E-mail" type="text">
    </div>
    <!-- /User e-mail -->
    <!-- User type -->
    <div class="col-xs-12 col-md-12 col-lg-12 mb-4">
      <select autocomplete="off" class="form-control" id="userType" name="user[type]">
        <option value="">Selecione o tipo de usuário</option>
        <option value="root">Root</option>
        <option value="admin">Admin</option>
        <option value="user">Usuário</option>
      </select>
    </div>
    <!-- /User type --> 
    <!-- User active -->
    <input autocomplete="off" name="user[active]" value="1" type="hidden">
    <!-- /User active -->
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