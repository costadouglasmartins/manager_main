
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('country'); ?>">Países</a></li>
    <li class="breadcrumb-item active" aria-current="page">País - criar</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- País -->
<form action="<?= url('country/store/'); ?>" method="POST">
  <div class="row">
    
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" name="name" maxlength="255" type="text">
    </div>

    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="acronym">Sigla</label>
      <input autocomplete="off" class="form-control" id="acronym" name="acronym" maxlength="5" type="text">
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
<!-- /País -->
