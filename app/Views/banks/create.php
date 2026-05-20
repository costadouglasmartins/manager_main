<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('bank'); ?>">Bancos</a></li>
    <li class="breadcrumb-item active" aria-current="page">Banco - criar</li>
  </ol>
</nav>

<h5>Criar banco</h5>
<form action="<?= url('bank/store'); ?>" method="POST">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="number">Número</label>
      <input autocomplete="off" class="form-control" id="number" maxlength="45" name="number" required type="text">
    </div>

    <div class="mb-2 col-md-6 col-lg-6">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" maxlength="255" name="name" required type="text">
    </div>

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="active">Status</label>
      <select class="form-select" id="active" name="active">
        <option value="1" selected>Ativo</option>
        <option value="0">Inativo</option>
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
