
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('company'); ?>">Empresas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Empresa - <?= $company['name']; ?> - editar</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- Company -->
<h5>Editar empresa</h5>
<form action="<?= url('company/update/' . $data['company']['id']); ?>" method="POST">
  <div class="row">
    <!-- Company name -->
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="companyName">Nome</label>
      <input autocomplete="off" class="form-control" id="companyName" name="company[name]" maxlength="255" type="text" value="<?= $data['company']['name']; ?>">
    </div>
    <!-- /Company fantasy -->
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="companyFantasy">Fantasia</label>
      <input autocomplete="off" class="form-control" id="companyFantasy" name="company[fantasy]" maxlength="255" type="text" value="<?= $data['company']['fantasy']; ?>">
    </div>
    <!-- Company document -->
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="companyDocument">CNPJ</label>
      <input autocomplete="off" class="mask-cpf-cnpj form-control" id="companyDocument" name="company[document]" maxlength="18" type="text" value="<?= maskCpfCnpj($data['company']['document']); ?>">
    </div>
    <!-- /Company document -->
    <!-- Company database -->
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="companyDatabase">Banco de dados</label>
      <input autocomplete="off" class="form-control" id="companyDatabase" name="company[database]" maxlength="45" type="text" value="<?= $data['company']['database']; ?>">
    </div>
    <!-- /Company database -->
    <!-- Company status -->
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="companyActive">Situação</label>
      <select autocomplete="off" aria-label="Situação da empresa no sistema" class="form-select" id="companyActive" name="company[active]">
        <option value="1" <?= ($data['company']['active'] === 1 ? 'selected="selected"' : ''); ?>>Ativa</option>
        <option value="0" <?= ($data['company']['active'] === 0 ? 'selected="selected"' : ''); ?>>Inativa</option>
      </select>
    </div>
    <!-- /Company status -->
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
<!-- /Company -->