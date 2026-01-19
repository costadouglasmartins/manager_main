
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('country'); ?>">Países</a></li>
    <li class="breadcrumb-item active" aria-current="page">País - <?= $country['name']; ?> - editar</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- País -->
<h5>Editar país</h5>
<form action="<?= url('country/update/' . $country['id']); ?>" method="POST">
  <div class="row">
    
  <!-- Person name -->
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" name="name" maxlength="255" type="text" value="<?= $country['name']; ?>">
    </div>
    <!-- /Person name -->

    <!-- /Person type -->
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="type">Tipo</label>
      <select autocomplete="off" class="form-control" id="type" name="type">
        <option value=""></option>
        <option <?= ($data['person']['type'] === 'individual' ? 'selected="selected"' : ''); ?> value="individual">Pessoa física</optiom>
        <option <?= ($data['person']['type'] === 'company' ? 'selected="selected"' : ''); ?> value="company">Pessoa jurídica</option>
      </select>
    </div>
    <!-- /Person type -->

    <!-- Person document -->
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="document">CNPJ</label>
      <input autocomplete="off" class="mask-cpf-cnpj form-control" id="document" name="document" maxlength="18" type="text" value="<?= maskCpfCnpj($data['person']['document']); ?>">
    </div>
    <!-- /Person document -->
    
    <!-- Person active -->
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="active">Ativa</label>
      <select autocomplete="off" class="form-control" id="" name="active">
        <option value="">Selecione...</option>
        <option value="0" <?= ($data['person']['active'] === 0 ? 'selected="selected"' : ''); ?>>Inativo</option>
        <option value="1" <?= ($data['person']['active'] === 1 ? 'selected="selected"' : ''); ?>>Ativo</option>
      </select>
    </div>
    <!-- /Person database -->
    <!-- Person status -->
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="birthdate">Data de nascimento</label>
      <input autocomplete="off" class="form-control mask-date" id="birthdate" name="birthdate" value="<?= date('d/m/Y', strtotime($data['person']['birthdate']));?>">
    </div>
    <!-- /Person status -->
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
<!-- /Person -->