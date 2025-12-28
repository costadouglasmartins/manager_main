<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('company'); ?>">Empresas</a></li>
    <li class="breadcrumb-item"><a href="<?= url('person'); ?>">Pessoas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Nova pessoa</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Nova pessoa</h5>
<form action="<?= url('person/store'); ?>" method="POST">
  <div class="row mb-2">
    <!-- Company -->
    <div class="col-xs-12 col-md-12 col-lg-12 mb-2">
      <label for="personCompanyId" class="form-label">Empresa</label>
      <select autocomplete="off" class="form-control" id="personCompanyId" name="person[company_id]" required="required">
        <option value="">Selecione uma empresa...</option>
        <?php foreach($dataListCompanies as $company): ?>
          <option value="<?= $company['id']; ?>"><?= $company['name']; ?>
        <?php endforeach; ?>
      </select>
    </div>
    <!-- /Company -->
    <!-- Person name -->
    <div class="col-xs-12 col-md-12 col-lg-12">
      <label for="personName" class="form-label">Name:</label>
      <input autocomplete="off" class="form-control" id="personName" name="person[name]" type="text" required="required">
    </div>
    <!-- /Person name -->
    <!-- Person type -->
    <div class="col-xs-12 col-md-12 col-lg-12">
      <label for="personType" class="form-label">Tipo</label>
      <select autocomplete="off" class="form-control" id="personType" name="person[type]" required="required">
        <option value="">Selecione um tipo...</option>
        <option value="individual">Pessoa física</option>
        <option value="company">Pessoa jurídica</option>
      </select>
    </div>
    <!-- /Person type -->
    <!-- Person document -->
    <div class="col-xs-12 col-md-12 col-lg-12">
      <label for="personDocument" class="form-label">Documento</label>
      <input autocomplete="off" class="form-control mask-cpf-cnpj" id="personDocument" name="person[document]" maxlength="18" type="text">
    </div>
    <!-- /Person document -->
    <!-- Person birthdate -->
    <div class="col-xs-12 col-md-12 col-lg-12">
      <label for="personBirthdate" class="form-label">Data de nascimento</label>
      <input autocomplete="off" class="form-control mask-date" id="personBirthdate" name="person[birthdate]" maxlength="10" type="text">
    </div>
    <!-- /Person birthdate -->
    <!-- Person active -->
    <input autocomplete="off" id="personActive" name="person[active]" type="hidden" value="1">
    <!-- /Person active -->
  </div>
  <h5>Novo(a) usuário(a)</h5>
  <div class="row mt-2">
    <!-- User username -->
    <div class="col-xs-12 col-md-12 col-lg-12">
      <label class="form-label" for="username">Usuário</label>
      <input autocomplete="off" class="form-control" id="username" name="user[username]" type="text">
    </div>
    <!-- /User username -->
    <!--  User password -->
    <div class="col-xs-12 col-md-12 col-lg-12"> 
      <label class="form-label" for="userPassword">Senha</label>
      <input autocomplete="off" class="form-control" id="userPassword" name="user[password]" type="password">
    </div>
    <!-- /User password -->
    <!-- User e-mail -->
    <div class="col-xs-12 col-md-12 col-lg-12">
      <label class="form-label" for="userEmail">E-mail</label>
      <input autocomplete="off" class="form-control" id="userEmail" name="user[email]" type="text">
    </div>
    <!-- /User e-mail -->
    <!-- User type -->
    <div class="col-xs-12 col-md-12 col-lg-12">
      <label class="form-label" for="userType">Tipo</label>
      <select autocomplete="off" class="form-control" id="userType" name="user[type]">
        <option value="">Selecione...</option>
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