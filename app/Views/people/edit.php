<?php
$person     = is_array($person ?? null) ? $person : [];
$user       = is_array($user ?? null) ? $user : [];
$addresses  = is_array($addresses ?? null) ? $addresses : [];
$phones     = is_array($phones ?? null) ? $phones : [];
$emails     = is_array($emails ?? null) ? $emails : [];
$tenants    = is_array($tenants ?? null) ? $tenants : [];
$allTenants = is_array($allTenants ?? null) ? $allTenants : [];

$personId = (int) ($person['id'] ?? 0);
$userId   = (int) ($user['id'] ?? 0);

$e = function ($value): string
  {
  return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
  };

$selected = function ($actual, $expected): string
  {
  return ((string) $actual === (string) $expected) ? 'selected' : '';
  };
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('person'); ?>">Pessoas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Editar - <?= $e($person['name'] ?? ''); ?></li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Editar pessoa</h5>

<form action="<?= url('person/update/' . $personId); ?>" method="POST" id="personEditForm" class="needs-validation" novalidate>
  <input type="hidden" name="person[id]" value="<?= $personId; ?>">

  <div class="card shadow-sm">
    <div class="card-body">
      <!-- Pessoa -->
      <section class="mb-4">
        <h2 class="h6 mb-3">Pessoa</h2>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label" for="personName">Nome completo</label>
            <input autocomplete="off" class="form-control" id="personName" name="person[name]" maxlength="255" type="text" value="<?= $e($person['name'] ?? ''); ?>" required>
            <div class="invalid-feedback">Informe o nome.</div>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="personFantasy">Nome fantasia</label>
            <input autocomplete="off" class="form-control" id="personFantasy" name="person[fantasy]" maxlength="255" type="text" value="<?= $e($person['fantasy'] ?? ''); ?>">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="personType">Tipo de pessoa</label>
            <select autocomplete="off" class="form-select" id="personType" name="person[type]" required>
              <option value="">Selecione...</option>
              <option value="individual" <?= $selected($person['type'] ?? '', 'individual'); ?>>Pessoa física</option>
              <option value="company" <?= $selected($person['type'] ?? '', 'company'); ?>>Pessoa jurídica</option>
            </select>
            <div class="invalid-feedback">Selecione o tipo.</div>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="personDocument">Documento (CPF/CNPJ)</label>
            <input autocomplete="off" class="form-control mask-cpf-cnpj" id="personDocument" name="person[document]" maxlength="18" type="text" value="<?= $e(maskCpfCnpj($person['document'] ?? '')); ?>" required>
            <div class="invalid-feedback">Informe o documento.</div>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="personBirthdate">Data de nascimento</label>
            <input autocomplete="off" class="form-control mask-date" id="personBirthdate" name="person[birthdate]" type="text" value="<?= $e(maskDateBr($person['birthdate'] ?? null)); ?>" placeholder="DD/MM/AAAA">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="personActive">Situação</label>
            <select autocomplete="off" class="form-select" id="personActive" name="person[active]" required>
              <option value="">Selecione...</option>
              <option value="1" <?= $selected((int) ($person['active'] ?? 1), 1); ?>>Ativo</option>
              <option value="0" <?= $selected((int) ($person['active'] ?? 1), 0); ?>>Inativo</option>
            </select>
          </div>
        </div>
      </section>
      <!-- /Pessoa -->

      <!-- Usuário -->
      <section class="border-top pt-4 mb-4">
        <h2 class="h6 mb-3">Usuário</h2>
        <input type="hidden" name="user[id]" value="<?= $userId ?: ''; ?>">

        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label" for="userUsername">Nome de usuário</label>
            <input autocomplete="off" class="form-control" id="userUsername" name="user[username]" maxlength="45" type="text" value="<?= $e($user['username'] ?? ''); ?>" <?= ($userId > 0 ? 'required' : ''); ?>>
            <div class="invalid-feedback">Informe o nome de usuário.</div>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="userType">Tipo de acesso</label>
            <select autocomplete="off" class="form-select" id="userType" name="user[type]" <?= ($userId > 0 ? 'required' : ''); ?>>
              <option value="">Selecione...</option>
              <option value="root" <?= $selected($user['type'] ?? '', 'root'); ?>>Root</option>
              <option value="admin" <?= $selected($user['type'] ?? '', 'admin'); ?>>Administrador</option>
              <option value="user" <?= $selected($user['type'] ?? '', 'user'); ?>>Usuário</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="userActive">Situação</label>
            <select autocomplete="off" class="form-select" id="userActive" name="user[active]">
              <option value="1" <?= $selected((int) ($user['active'] ?? 1), 1); ?>>Ativo</option>
              <option value="0" <?= $selected((int) ($user['active'] ?? 1), 0); ?>>Inativo</option>
            </select>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="userPassword">Senha</label>
            <input autocomplete="off" class="form-control" id="userPassword" name="user[password]" placeholder="Deixe em branco para manter a senha atual" type="password" minlength="8">
            <div class="invalid-feedback">A senha deve ter pelo menos 8 caracteres.</div>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="userPassword2">Confirmar senha</label>
            <input autocomplete="off" class="form-control" id="userPassword2" name="user[password_confirm]" placeholder="Confirmar senha" type="password" minlength="8">
            <div class="invalid-feedback">Confirme a senha corretamente.</div>
          </div>

          <div class="col-12">
            <label class="form-label" for="userTenantId">Tenant principal</label>
            <select autocomplete="off" class="form-select" id="userTenantId" name="user[tenant_id]">
              <option value="">Sem tenant principal</option>
              <?php foreach ($allTenants as $tenant): ?>
                <option value="<?= (int) $tenant['id']; ?>" <?= $selected((int) ($user['tenant_id'] ?? 0), (int) $tenant['id']); ?>>
                  <?= $e($tenant['name'] ?? ''); ?> (manager_<?= $e($tenant['database_number'] ?? ''); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </section>
      <!-- /Usuário -->

      <!-- Tenant -->
      <section class="border-top pt-4 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <h2 class="h6 mb-0">Tenants vinculados</h2>
          <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddTenantLink">Adicionar tenant</button>
        </div>

        <div class="mt-2" id="tenantLinksContainer">
          <?php foreach ($tenants as $i => $tenant): ?>
            <div class="border rounded p-3 mt-2" data-repeat-item data-tenant-link-item data-existing="1">
              <input type="hidden" name="tenant_links[<?= (int) $i; ?>][tenant_id]" value="<?= (int) $tenant['id']; ?>">
              <input type="hidden" name="tenant_links[<?= (int) $i; ?>][_delete]" value="0">

              <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                  <label class="form-label">Nome do tenant</label>
                  <input autocomplete="off" class="form-control" name="tenant_links[<?= (int) $i; ?>][name]" type="text" value="<?= $e($tenant['name'] ?? ''); ?>">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label">Nome fantasia</label>
                  <input autocomplete="off" class="form-control" name="tenant_links[<?= (int) $i; ?>][fantasy]" type="text" value="<?= $e($tenant['fantasy'] ?? ''); ?>">
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label">Documento (CPF/CNPJ)</label>
                  <input autocomplete="off" class="form-control mask-cpf-cnpj" name="tenant_links[<?= (int) $i; ?>][document]" type="text" value="<?= $e(maskCpfCnpj($tenant['document'] ?? '')); ?>">
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label">Banco</label>
                  <input class="form-control" type="text" value="manager_<?= $e($tenant['database_number'] ?? ''); ?>" disabled>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label">Situação do tenant</label>
                  <select class="form-select" name="tenant_links[<?= (int) $i; ?>][status]">
                    <option value="active" <?= $selected($tenant['status'] ?? '', 'active'); ?>>Ativo</option>
                    <option value="suspended" <?= $selected($tenant['status'] ?? '', 'suspended'); ?>>Suspenso</option>
                    <option value="pending" <?= $selected($tenant['status'] ?? '', 'pending'); ?>>Pendente</option>
                  </select>
                </div>
                <div class="col-12 col-md-3">
                  <label class="form-label">Perfil no tenant</label>
                  <select class="form-select" name="tenant_links[<?= (int) $i; ?>][role]">
                    <option value="owner" <?= $selected($tenant['role'] ?? '', 'owner'); ?>>Owner</option>
                    <option value="admin" <?= $selected($tenant['role'] ?? '', 'admin'); ?>>Administrador</option>
                    <option value="user" <?= $selected($tenant['role'] ?? '', 'user'); ?>>Usuário</option>
                  </select>
                </div>
                <div class="col-12 col-md-2">
                  <label class="form-label">Vínculo</label>
                  <select class="form-select" name="tenant_links[<?= (int) $i; ?>][active]">
                    <option value="1" <?= $selected((int) ($tenant['active'] ?? 1), 1); ?>>Ativo</option>
                    <option value="0" <?= $selected((int) ($tenant['active'] ?? 1), 0); ?>>Inativo</option>
                  </select>
                </div>
                <div class="col-12 col-md-1 d-grid">
                  <button type="button" class="btn btn-outline-danger" data-remove="tenant-link">X</button>
                </div>
                <div class="col-12">
                  <label class="form-label">Observações</label>
                  <textarea class="form-control" name="tenant_links[<?= (int) $i; ?>][observations]" rows="2"><?= $e($tenant['observations'] ?? ''); ?></textarea>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <template id="tenantLinkTemplate">
          <div class="border rounded p-3 mt-2" data-repeat-item data-tenant-link-item data-existing="0">
            <input type="hidden" name="tenant_links[__INDEX__][_delete]" value="0">
            <div class="row g-2 align-items-end">
              <div class="col-12 col-md-7">
                <label class="form-label">Tenant</label>
                <select class="form-select" name="tenant_links[__INDEX__][tenant_id]">
                  <option value="">Selecione um tenant...</option>
                  <?php foreach ($allTenants as $tenant): ?>
                    <option value="<?= (int) $tenant['id']; ?>">
                      <?= $e($tenant['name'] ?? ''); ?> (manager_<?= $e($tenant['database_number'] ?? ''); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-2">
                <label class="form-label">Perfil</label>
                <select class="form-select" name="tenant_links[__INDEX__][role]">
                  <option value="user" selected>Usuário</option>
                  <option value="admin">Administrador</option>
                  <option value="owner">Owner</option>
                </select>
              </div>
              <div class="col-12 col-md-2">
                <label class="form-label">Vínculo</label>
                <select class="form-select" name="tenant_links[__INDEX__][active]">
                  <option value="1" selected>Ativo</option>
                  <option value="0">Inativo</option>
                </select>
              </div>
              <div class="col-12 col-md-1 d-grid">
                <button type="button" class="btn btn-outline-danger" data-remove="tenant-link">X</button>
              </div>
            </div>
          </div>
        </template>
      </section>
      <!-- /Tenant -->

      <!-- Endereços -->
      <section class="border-top pt-4 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <h2 class="h6 mb-0">Endereços</h2>
          <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddAddress">Adicionar endereço</button>
        </div>

        <div class="mt-2" id="addressesContainer">
          <?php foreach ($addresses as $i => $address): ?>
            <?php
            $neighborhoodLabel = trim(implode(' - ', array_filter([
              $address['neighborhood_name'] ?? '',
              $address['city_name'] ?? '',
              $address['state_name'] ?? '',
            ])));
            ?>
            <div class="border rounded p-3 mt-2" data-repeat-item data-address-item data-existing="1">
              <input type="hidden" name="addresses[<?= (int) $i; ?>][id]" value="<?= (int) $address['id']; ?>">
              <input type="hidden" name="addresses[<?= (int) $i; ?>][_delete]" value="0">

              <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                  <label class="form-label">CEP</label>
                  <input class="form-control mask-cep" name="addresses[<?= (int) $i; ?>][zip_code]" type="text" maxlength="9" value="<?= $e($address['zip_code'] ?? ''); ?>" placeholder="00000-000">
                </div>
                <div class="col-12 col-md-5">
                  <label class="form-label">Logradouro</label>
                  <input class="form-control" name="addresses[<?= (int) $i; ?>][street]" type="text" maxlength="255" value="<?= $e($address['street'] ?? ''); ?>">
                </div>
                <div class="col-12 col-md-2">
                  <label class="form-label">Número</label>
                  <input class="form-control" name="addresses[<?= (int) $i; ?>][number]" type="text" maxlength="10" value="<?= $e($address['number'] ?? ''); ?>">
                </div>
                <div class="col-12 col-md-2 d-grid">
                  <label class="form-label">&nbsp;</label>
                  <button type="button" class="btn btn-outline-danger" data-remove="address">Remover</button>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label">Observações</label>
                  <input class="form-control" name="addresses[<?= (int) $i; ?>][observations]" type="text" maxlength="255" value="<?= $e($address['observations'] ?? ''); ?>">
                </div>
                <div class="col-12 col-md-6 position-relative">
                  <label class="form-label">Bairro</label>
                  <input type="hidden" class="addr-neighborhood-id" name="addresses[<?= (int) $i; ?>][neighborhood_id]" value="<?= (int) ($address['neighborhood_id'] ?? 0); ?>">
                  <input type="text" class="form-control addr-neighborhood-input" autocomplete="off" value="<?= $e($neighborhoodLabel); ?>" placeholder="Digite o nome do bairro...">
                  <ul class="list-group addr-neighborhood-results" style="display:none; position:absolute; width:100%; z-index:1050; max-height:220px; overflow-y:auto;"></ul>
                </div>
                <div class="col-12 addr-create-panel d-none">
                  <div class="border border-warning rounded p-3 bg-warning-subtle">
                    <p class="fw-semibold small mb-2">Cadastrar novo bairro</p>
                    <div class="row g-2 align-items-end">
                      <div class="col-12 col-md-5">
                        <label class="form-label small">Nome do bairro</label>
                        <input type="text" class="form-control form-control-sm addr-new-neighborhood-name" placeholder="Ex.: Centro">
                      </div>
                      <div class="col-12 col-md-5 position-relative">
                        <label class="form-label small">Cidade</label>
                        <input type="hidden" class="addr-new-city-id" value="">
                        <input type="text" class="form-control form-control-sm addr-new-city-input" autocomplete="off" placeholder="Digite o nome da cidade...">
                        <ul class="list-group addr-new-city-results" style="display:none; position:absolute; width:100%; z-index:1060; max-height:180px; overflow-y:auto;"></ul>
                      </div>
                      <div class="col-12 col-md-2 d-flex gap-2 align-items-end flex-wrap">
                        <button type="button" class="btn btn-warning btn-sm addr-btn-save-neighborhood">Salvar</button>
                        <button type="button" class="btn btn-link btn-sm p-0 text-secondary addr-btn-cancel-neighborhood">Cancelar</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <template id="addressTemplate">
          <div class="border rounded p-3 mt-2" data-repeat-item data-address-item data-existing="0">
            <input type="hidden" name="addresses[__INDEX__][_delete]" value="0">
            <div class="row g-2 align-items-end">
              <div class="col-12 col-md-3">
                <label class="form-label">CEP</label>
                <input class="form-control mask-cep" name="addresses[__INDEX__][zip_code]" type="text" maxlength="9" placeholder="00000-000">
              </div>
              <div class="col-12 col-md-5">
                <label class="form-label">Logradouro</label>
                <input class="form-control" name="addresses[__INDEX__][street]" type="text" maxlength="255" placeholder="Rua, Avenida, etc.">
              </div>
              <div class="col-12 col-md-2">
                <label class="form-label">Número</label>
                <input class="form-control" name="addresses[__INDEX__][number]" type="text" maxlength="10" placeholder="Nº">
              </div>
              <div class="col-12 col-md-2 d-grid">
                <label class="form-label">&nbsp;</label>
                <button type="button" class="btn btn-outline-danger" data-remove="address">Remover</button>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Observações</label>
                <input class="form-control" name="addresses[__INDEX__][observations]" type="text" maxlength="255" placeholder="Complemento, referência, etc.">
              </div>
              <div class="col-12 col-md-6 position-relative">
                <label class="form-label">Bairro</label>
                <input type="hidden" class="addr-neighborhood-id" name="addresses[__INDEX__][neighborhood_id]" value="">
                <input type="text" class="form-control addr-neighborhood-input" autocomplete="off" placeholder="Digite o nome do bairro...">
                <ul class="list-group addr-neighborhood-results" style="display:none; position:absolute; width:100%; z-index:1050; max-height:220px; overflow-y:auto;"></ul>
              </div>
              <div class="col-12 addr-create-panel d-none">
                <div class="border border-warning rounded p-3 bg-warning-subtle">
                  <p class="fw-semibold small mb-2">Cadastrar novo bairro</p>
                  <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-5">
                      <label class="form-label small">Nome do bairro</label>
                      <input type="text" class="form-control form-control-sm addr-new-neighborhood-name" placeholder="Ex.: Centro">
                    </div>
                    <div class="col-12 col-md-5 position-relative">
                      <label class="form-label small">Cidade</label>
                      <input type="hidden" class="addr-new-city-id" value="">
                      <input type="text" class="form-control form-control-sm addr-new-city-input" autocomplete="off" placeholder="Digite o nome da cidade...">
                      <ul class="list-group addr-new-city-results" style="display:none; position:absolute; width:100%; z-index:1060; max-height:180px; overflow-y:auto;"></ul>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2 align-items-end flex-wrap">
                      <button type="button" class="btn btn-warning btn-sm addr-btn-save-neighborhood">Salvar</button>
                      <button type="button" class="btn btn-link btn-sm p-0 text-secondary addr-btn-cancel-neighborhood">Cancelar</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </section>
      <!-- /Endereços -->

      <!-- Contatos -->
      <section class="border-top pt-4">
        <div class="row g-3">
          <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <h2 class="h6 mb-0">Telefones</h2>
              <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddPhone">Adicionar telefone</button>
            </div>

            <div class="mt-2" id="phonesContainer">
              <?php foreach ($phones as $i => $phone): ?>
                <div class="border rounded p-3 mt-2" data-repeat-item data-phone-item data-existing="1">
                  <input type="hidden" name="phones[<?= (int) $i; ?>][id]" value="<?= (int) $phone['id']; ?>">
                  <input type="hidden" name="phones[<?= (int) $i; ?>][_delete]" value="0">

                  <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                      <label class="form-label">Número</label>
                      <input class="form-control" name="phones[<?= (int) $i; ?>][number]" type="text" inputmode="numeric" value="<?= $e(maskPhoneBr($phone['phone_number'] ?? '')); ?>" placeholder="(XX) XXXXX-XXXX">
                    </div>
                    <div class="col-12 col-md-7">
                      <label class="form-label">Observações</label>
                      <input class="form-control" name="phones[<?= (int) $i; ?>][notes]" type="text" value="<?= $e($phone['observations'] ?? ''); ?>" placeholder="Ex.: celular, comercial, WhatsApp">
                    </div>
                    <div class="col-12 col-md-1 d-grid">
                      <button type="button" class="btn btn-outline-danger" data-remove="phone">X</button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <template id="phoneTemplate">
              <div class="border rounded p-3 mt-2" data-repeat-item data-phone-item data-existing="0">
                <input type="hidden" name="phones[__INDEX__][_delete]" value="0">
                <div class="row g-2 align-items-end">
                  <div class="col-12 col-md-4">
                    <label class="form-label">Número</label>
                    <input class="form-control" name="phones[__INDEX__][number]" type="text" inputmode="numeric" placeholder="(XX) XXXXX-XXXX">
                  </div>
                  <div class="col-12 col-md-7">
                    <label class="form-label">Observações</label>
                    <input class="form-control" name="phones[__INDEX__][notes]" type="text" placeholder="Ex.: celular, comercial, WhatsApp">
                  </div>
                  <div class="col-12 col-md-1 d-grid">
                    <button type="button" class="btn btn-outline-danger" data-remove="phone">X</button>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <h2 class="h6 mb-0">E-mails</h2>
              <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddEmail">Adicionar e-mail</button>
            </div>

            <div class="mt-2" id="emailsContainer">
              <?php foreach ($emails as $i => $email): ?>
                <div class="border rounded p-3 mt-2" data-repeat-item data-email-item data-existing="1">
                  <input type="hidden" name="emails[<?= (int) $i; ?>][id]" value="<?= (int) $email['id']; ?>">
                  <input type="hidden" name="emails[<?= (int) $i; ?>][_delete]" value="0">

                  <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                      <label class="form-label">Endereço de e-mail</label>
                      <input class="form-control" name="emails[<?= (int) $i; ?>][address]" type="email" value="<?= $e($email['email'] ?? ''); ?>" placeholder="exemplo@email.com">
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label">Descrição</label>
                      <input class="form-control" name="emails[<?= (int) $i; ?>][description]" type="text" value="<?= $e($email['observations'] ?? ''); ?>" placeholder="Ex.: financeiro, pessoal, comercial">
                    </div>
                    <div class="col-12 col-md-3">
                      <label class="form-label">E-mail de login?</label>
                      <select class="form-select" name="emails[<?= (int) $i; ?>][is_login]">
                        <option value="0" <?= $selected((int) ($email['is_login'] ?? 0), 0); ?>>Não</option>
                        <option value="1" <?= $selected((int) ($email['is_login'] ?? 0), 1); ?>>Sim</option>
                      </select>
                    </div>
                    <div class="col-12 col-md-1 d-grid">
                      <button type="button" class="btn btn-outline-danger" data-remove="email">X</button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <template id="emailTemplate">
              <div class="border rounded p-3 mt-2" data-repeat-item data-email-item data-existing="0">
                <input type="hidden" name="emails[__INDEX__][_delete]" value="0">
                <div class="row g-2 align-items-end">
                  <div class="col-12 col-md-4">
                    <label class="form-label">Endereço de e-mail</label>
                    <input class="form-control" name="emails[__INDEX__][address]" type="email" placeholder="exemplo@email.com">
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label">Descrição</label>
                    <input class="form-control" name="emails[__INDEX__][description]" type="text" placeholder="Ex.: financeiro, pessoal, comercial">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">E-mail de login?</label>
                    <select class="form-select" name="emails[__INDEX__][is_login]">
                      <option value="0" selected>Não</option>
                      <option value="1">Sim</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-1 d-grid">
                    <button type="button" class="btn btn-outline-danger" data-remove="email">X</button>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </section>
      <!-- /Contatos -->
    </div>

    <div class="card-footer bg-body d-flex justify-content-end gap-2">
      <a href="<?= url('person'); ?>" class="btn btn-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit">Salvar</button>
    </div>
  </div>
</form>

<script>
  const PERSON_NEIGHBORHOOD_SEARCH_URL = "<?= url('person/neighborhoodSearch'); ?>";
  const PERSON_NEIGHBORHOOD_CREATE_URL = "<?= url('person/neighborhoodCreate'); ?>";
  const PERSON_CITY_SEARCH_URL         = "<?= url('person/citySearch'); ?>";
</script>

<script>
(function ()
  {
  const form = document.getElementById("personEditForm");
  if (!form)
    {
    return;
    }

  let addressCounter    = <?= count($addresses); ?>;
  let phoneCounter      = <?= count($phones); ?>;
  let emailCounter      = <?= count($emails); ?>;
  let tenantLinkCounter = <?= count($tenants); ?>;

  function addFromTemplate(templateId, containerId, counterName, initializer)
    {
    const template = document.getElementById(templateId);
    const container = document.getElementById(containerId);
    if (!template || !container)
      {
      return;
      }

    let index = 0;
    if (counterName === "address")
      {
      index = addressCounter++;
      }
    else if (counterName === "phone")
      {
      index = phoneCounter++;
      }
    else if (counterName === "email")
      {
      index = emailCounter++;
      }
    else
      {
      index = tenantLinkCounter++;
      }

    const html = template.innerHTML.split("__INDEX__").join(index);
    const wrapper = document.createElement("div");
    wrapper.innerHTML = html.trim();
    const block = wrapper.firstElementChild;
    container.appendChild(block);

    if (initializer)
      {
      initializer(block);
      }
    }

  function clearList(listEl)
    {
    listEl.innerHTML = "";
    listEl.style.display = "none";
    }

  function initMiniAutocomplete(endpoint, inputEl, listEl, onSelect, extraOption)
    {
    if (!endpoint || !inputEl || !listEl)
      {
      return;
      }

    let abort = null;

    inputEl.addEventListener("input", async function ()
      {
      const term = inputEl.value.trim();
      if (term.length < 1)
        {
        clearList(listEl);
        return;
        }

      if (abort)
        {
        abort.abort();
        }
      abort = new AbortController();

      try
        {
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set("term", term);
        const res = await fetch(url.toString(), {
          method: "GET",
          credentials: "same-origin",
          headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"},
          signal: abort.signal
        });
        const data = await res.json();
        const items = data.items || [];
        listEl.innerHTML = "";

        items.forEach(function (item)
          {
          const li = document.createElement("li");
          li.className = "list-group-item list-group-item-action py-1 px-2";
          li.textContent = item.label;
          li.addEventListener("mousedown", function (e)
            {
            e.preventDefault();
            onSelect(item);
            clearList(listEl);
            inputEl.value = item.name || item.label;
            });
          listEl.appendChild(li);
          });

        if (extraOption)
          {
          const li = document.createElement("li");
          li.className = "list-group-item list-group-item-action py-1 px-2 text-primary fw-semibold";
          li.textContent = extraOption.label(term);
          li.addEventListener("mousedown", function (e)
            {
            e.preventDefault();
            clearList(listEl);
            extraOption.onClick(term);
            });
          listEl.appendChild(li);
          }

        listEl.style.display = listEl.children.length > 0 ? "block" : "none";
        }
      catch (err)
        {
        if (err && err.name !== "AbortError")
          {
          console.error("miniAutocomplete fetch error:", err);
          }
        }
      });

    document.addEventListener("click", function (e)
      {
      if (e.target !== inputEl && !listEl.contains(e.target))
        {
        clearList(listEl);
        }
      });
    }

  function initAddressAutocomplete(block)
    {
    const hiddenId = block.querySelector(".addr-neighborhood-id");
    const textInput = block.querySelector(".addr-neighborhood-input");
    const resultsList = block.querySelector(".addr-neighborhood-results");
    const createPanel = block.querySelector(".addr-create-panel");
    const newNameInput = block.querySelector(".addr-new-neighborhood-name");
    const newCityId = block.querySelector(".addr-new-city-id");
    const newCityInput = block.querySelector(".addr-new-city-input");
    const newCityList = block.querySelector(".addr-new-city-results");
    const btnSave = block.querySelector(".addr-btn-save-neighborhood");
    const btnCancel = block.querySelector(".addr-btn-cancel-neighborhood");

    if (!hiddenId || !textInput || !resultsList || !createPanel)
      {
      return;
      }

    function showCreatePanel(term)
      {
      createPanel.classList.remove("d-none");
      newNameInput.value = term;
      newCityId.value = "";
      newCityInput.value = "";
      textInput.value = "";
      hiddenId.value = "";
      }

    function hideCreatePanel()
      {
      createPanel.classList.add("d-none");
      newNameInput.value = "";
      newCityId.value = "";
      newCityInput.value = "";
      }

    initMiniAutocomplete(
      PERSON_NEIGHBORHOOD_SEARCH_URL,
      textInput,
      resultsList,
      function (item)
        {
        hiddenId.value = item.id;
        textInput.value = item.label;
        hideCreatePanel();
        },
      {
        label: function (term) { return "+ Criar bairro: \"" + term + "\""; },
        onClick: function (term) { showCreatePanel(term); }
      }
    );

    initMiniAutocomplete(
      PERSON_CITY_SEARCH_URL,
      newCityInput,
      newCityList,
      function (item)
        {
        newCityId.value = item.id;
        newCityInput.value = item.label;
        },
      null
    );

    btnSave.addEventListener("click", async function ()
      {
      const name = newNameInput.value.trim();
      const cityId = newCityId.value.trim();

      if (!name || !cityId)
        {
        return;
        }

      btnSave.disabled = true;
      btnSave.textContent = "Salvando...";

      try
        {
        const body = new URLSearchParams({name: name, city_id: cityId});
        const res = await fetch(PERSON_NEIGHBORHOOD_CREATE_URL, {
          method: "POST",
          credentials: "same-origin",
          headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest", "Content-Type": "application/x-www-form-urlencoded"},
          body: body.toString()
        });
        const data = await res.json();

        if (data.error)
          {
          alert("Erro: " + data.error);
          return;
          }

        hiddenId.value = data.id;
        textInput.value = data.name;
        hideCreatePanel();
        }
      catch (err)
        {
        alert("Erro de comunicação ao salvar bairro.");
        console.error(err);
        }
      finally
        {
        btnSave.disabled = false;
        btnSave.textContent = "Salvar";
        }
      });

    btnCancel.addEventListener("click", function ()
      {
      hideCreatePanel();
      });
    }

  document.querySelectorAll("[data-address-item]").forEach(initAddressAutocomplete);

  document.getElementById("btnAddAddress")?.addEventListener("click", function ()
    {
    addFromTemplate("addressTemplate", "addressesContainer", "address", initAddressAutocomplete);
    });

  document.getElementById("btnAddPhone")?.addEventListener("click", function ()
    {
    addFromTemplate("phoneTemplate", "phonesContainer", "phone");
    });

  document.getElementById("btnAddEmail")?.addEventListener("click", function ()
    {
    addFromTemplate("emailTemplate", "emailsContainer", "email");
    });

  document.getElementById("btnAddTenantLink")?.addEventListener("click", function ()
    {
    addFromTemplate("tenantLinkTemplate", "tenantLinksContainer", "tenantLink");
    });

  document.addEventListener("click", function (e)
    {
    const btn = e.target instanceof HTMLElement ? e.target.closest("[data-remove]") : null;
    if (!btn)
      {
      return;
      }

    const block = btn.closest("[data-repeat-item]");
    if (!block)
      {
      return;
      }

    const deleteInput = block.querySelector("input[name$='[_delete]']");
    if (block.getAttribute("data-existing") === "1" && deleteInput)
      {
      deleteInput.value = "1";
      block.classList.add("d-none");
      return;
      }

    block.remove();
    });

  function applyCepMask(value)
    {
    const digits = value.replace(/\D/g, "").slice(0, 8);
    return digits.replace(/(\d{5})(\d{1,3})$/, "$1-$2");
    }

  document.addEventListener("input", function (e)
    {
    if (e.target && e.target.classList.contains("mask-cep"))
      {
      const el = e.target;
      const pos = el.selectionStart;
      const prev = el.value;
      el.value = applyCepMask(el.value);
      const diff = el.value.length - prev.length;
      el.setSelectionRange(pos + diff, pos + diff);
      }
    });

  form.addEventListener("submit", function (e)
    {
    const password = document.getElementById("userPassword");
    const password2 = document.getElementById("userPassword2");

    if (password && password2)
      {
      password2.setCustomValidity(password.value === password2.value ? "" : "As senhas não coincidem.");
      }

    if (!form.checkValidity())
      {
      e.preventDefault();
      e.stopPropagation();
      form.classList.add("was-validated");
      }
    });
  })();
</script>
