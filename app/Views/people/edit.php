<?php
$person     = is_array($person ?? null) ? $person : [];
$user       = is_array($user ?? null) ? $user : [];
$addresses  = is_array($addresses ?? null) ? $addresses : [];
$phones     = is_array($phones ?? null) ? $phones : [];
$emails     = is_array($emails ?? null) ? $emails : [];
$tenants    = is_array($tenants ?? null) ? $tenants : [];
$databaseTenants = is_array($database_tenants ?? null) ? $database_tenants : [];
$databaseTenantMembers = is_array($database_tenant_members ?? null) ? $database_tenant_members : [];
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

$personName = trim((string) ($person['name'] ?? ''));
$personLabel = ($personName !== '') ? $personName : ('Pessoa #' . $personId);
$personTypeLabel = (($person['type'] ?? '') === 'company') ? 'Pessoa jurídica' : 'Pessoa física';
$personStatusLabel = ((int) ($person['active'] ?? 1) === 1) ? 'Ativo' : 'Inativo';
$personDocumentLabel = !empty($person['document']) ? maskCpfCnpj($person['document']) : 'Documento não informado';
$userStatusLabel = ($userId > 0) ? 'Usuário vinculado' : 'Sem usuário vinculado';

$currentTenantId = (int) ($user['tenant_id'] ?? 0);
if ($currentTenantId <= 0 && !empty($tenants[0]['id']))
  {
  $currentTenantId = (int) $tenants[0]['id'];
  }

$currentTenantDatabaseNumber = '';
foreach ($allTenants as $tenantOption)
  {
  if ((int) ($tenantOption['id'] ?? 0) === $currentTenantId)
    {
    $currentTenantDatabaseNumber = (string) ($tenantOption['database_number'] ?? '');
    break;
    }
  }
?>

<link rel="stylesheet" href="<?= url('assets/css/people/edit.css?v=1'); ?>">

<div class="people-edit-page">
  <!-- Breadcrumbs -->
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
      <li class="breadcrumb-item"><a href="<?= url('person'); ?>">Pessoas</a></li>
      <li class="breadcrumb-item active" aria-current="page">Editar - <?= $e($personLabel); ?></li>
    </ol>
  </nav>
  <!-- /Breadcrumbs -->

  <div class="people-edit-toolbar">
    <div>
      <h5 class="mb-0">Editar pessoa</h5>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= url('person'); ?>">
      <i class="bi bi-arrow-left"></i>
      <span>Voltar para pessoas</span>
    </a>
  </div>

  <div class="card shadow-sm mb-3 people-summary-card">
    <div class="card-body">
      <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div class="people-summary-title">
          <span class="people-summary-icon"><i class="bi bi-person-vcard"></i></span>
          <div>
            <div class="fw-semibold"><?= $e($personLabel); ?></div>
            <div class="text-body-secondary small"><?= $e($personDocumentLabel); ?></div>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge text-bg-secondary"><?= $e($personTypeLabel); ?></span>
          <span class="badge <?= ((int) ($person['active'] ?? 1) === 1) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= $e($personStatusLabel); ?></span>
          <span class="badge <?= ($userId > 0) ? 'text-bg-primary' : 'text-bg-secondary'; ?>"><?= $e($userStatusLabel); ?></span>
        </div>
      </div>

      <div class="people-quick-nav">
        <a class="btn btn-outline-secondary btn-sm people-nav-button" href="#section-person">
          <i class="bi bi-person"></i>
          <span>Pessoa</span>
        </a>
        <a class="btn btn-outline-secondary btn-sm people-nav-button" href="#section-user">
          <i class="bi bi-person-lock"></i>
          <span>Usuário</span>
        </a>
        <a class="btn btn-outline-secondary btn-sm people-nav-button" href="#section-tenants">
          <i class="bi bi-database"></i>
          <span>Tenants</span>
        </a>
        <a class="btn btn-outline-secondary btn-sm people-nav-button" href="#section-addresses">
          <i class="bi bi-geo-alt"></i>
          <span>Endereços</span>
        </a>
        <a class="btn btn-outline-secondary btn-sm people-nav-button" href="#section-contacts">
          <i class="bi bi-chat-dots"></i>
          <span>Contatos</span>
        </a>
      </div>
    </div>
  </div>

  <form action="<?= url('person/update/' . $personId); ?>" method="POST" id="personEditForm" class="needs-validation people-edit-form" novalidate>
    <input type="hidden" name="person[id]" value="<?= $personId; ?>">

    <div class="card shadow-sm people-form-card">
      <div class="card-body">
        <!-- Pessoa -->
        <section id="section-person" class="people-form-section">
          <div class="people-section-header">
            <span class="people-section-icon"><i class="bi bi-person-vcard"></i></span>
            <div>
              <h2>Pessoa</h2>
              <p>Identificação principal</p>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <label class="form-label" for="personName">Nome completo</label>
              <input autocomplete="off" class="form-control" id="personName" name="person[name]" maxlength="255" type="text" value="<?= $e($person['name'] ?? ''); ?>" required>
              <div class="invalid-feedback">Informe o nome.</div>
            </div>

            <div class="col-12 col-lg-6">
              <label class="form-label" for="personFantasy">Nome fantasia</label>
              <input autocomplete="off" class="form-control" id="personFantasy" name="person[fantasy]" maxlength="255" type="text" value="<?= $e($person['fantasy'] ?? ''); ?>">
            </div>

            <div class="col-12 col-md-6 col-xl-3">
              <label class="form-label" for="personType">Tipo de pessoa</label>
              <select autocomplete="off" class="form-select" id="personType" name="person[type]" required>
                <option value="">Selecione...</option>
                <option value="individual" <?= $selected($person['type'] ?? '', 'individual'); ?>>Pessoa física</option>
                <option value="company" <?= $selected($person['type'] ?? '', 'company'); ?>>Pessoa jurídica</option>
              </select>
              <div class="invalid-feedback">Selecione o tipo.</div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
              <label class="form-label" for="personDocument">Documento (CPF/CNPJ)</label>
              <input autocomplete="off" class="form-control mask-cpf-cnpj" id="personDocument" name="person[document]" maxlength="18" type="text" value="<?= $e(maskCpfCnpj($person['document'] ?? '')); ?>" required>
              <div class="invalid-feedback">Informe o documento.</div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
              <label class="form-label" for="personBirthdate">Data de nascimento</label>
              <input autocomplete="off" class="form-control mask-date" id="personBirthdate" name="person[birthdate]" type="text" value="<?= $e(maskDateBr($person['birthdate'] ?? null)); ?>" placeholder="DD/MM/AAAA">
            </div>

            <div class="col-12 col-md-6 col-xl-3">
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
        <section id="section-user" class="people-form-section">
          <div class="people-section-header">
            <span class="people-section-icon"><i class="bi bi-person-lock"></i></span>
            <div>
              <h2>Usuário</h2>
              <p>Acesso ao sistema</p>
            </div>
          </div>

          <input type="hidden" name="user[id]" value="<?= $userId ?: ''; ?>">

          <div class="row g-3">
            <div class="col-12 col-lg-4">
              <label class="form-label" for="userUsername">Nome de usuário</label>
              <input autocomplete="off" class="form-control" id="userUsername" name="user[username]" maxlength="45" type="text" value="<?= $e($user['username'] ?? ''); ?>" <?= ($userId > 0 ? 'required' : ''); ?>>
              <div class="invalid-feedback">Informe o nome de usuário.</div>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label" for="userType">Tipo de acesso</label>
              <select autocomplete="off" class="form-select" id="userType" name="user[type]" <?= ($userId > 0 ? 'required' : ''); ?>>
                <option value="">Selecione...</option>
                <option value="root" <?= $selected($user['type'] ?? '', 'root'); ?>>Root</option>
                <option value="admin" <?= $selected($user['type'] ?? '', 'admin'); ?>>Administrador</option>
                <option value="user" <?= $selected($user['type'] ?? '', 'user'); ?>>Usuário</option>
              </select>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label" for="userActive">Situação</label>
              <select autocomplete="off" class="form-select" id="userActive" name="user[active]">
                <option value="1" <?= $selected((int) ($user['active'] ?? 1), 1); ?>>Ativo</option>
                <option value="0" <?= $selected((int) ($user['active'] ?? 1), 0); ?>>Inativo</option>
              </select>
            </div>

            <div class="col-12 col-lg-6">
              <label class="form-label" for="userPassword">Senha</label>
              <input autocomplete="off" class="form-control" id="userPassword" name="user[password]" placeholder="Deixe em branco para manter a senha atual" type="password" minlength="8">
              <div class="invalid-feedback">A senha deve ter pelo menos 8 caracteres.</div>
            </div>

            <div class="col-12 col-lg-6">
              <label class="form-label" for="userPassword2">Confirmar senha</label>
              <input autocomplete="off" class="form-control" id="userPassword2" name="user[password_confirm]" placeholder="Confirmar senha" type="password" minlength="8">
              <div class="invalid-feedback">Confirme a senha corretamente.</div>
            </div>
          </div>
        </section>
        <!-- /Usuário -->

        <!-- Tenant -->
        <section id="section-tenants" class="people-form-section">
          <div class="people-section-header people-section-header-actions">
            <span class="people-section-icon"><i class="bi bi-database"></i></span>
            <div>
              <h2>Tenants</h2>
              <p>Tenant principal e vínculos do usuário</p>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm people-add-button" id="btnAddTenantLink">
              <i class="bi bi-plus-lg"></i>
              <span>Adicionar tenant</span>
            </button>
          </div>

          <div class="border rounded p-3 mb-3 people-inline-panel">
            <div class="row g-3 align-items-end">
              <div class="col-12 col-lg-8">
                <label class="form-label" for="userTenantId">Tenant principal</label>
                <select autocomplete="off" class="form-select" id="userTenantId" name="user[tenant_id]">
                  <option value="">Sem tenant principal</option>
                  <?php foreach ($allTenants as $tenant): ?>
                    <option value="<?= (int) $tenant['id']; ?>" <?= $selected((int) ($user['tenant_id'] ?? 0), (int) $tenant['id']); ?>>
                      <?= $e($tenant['associated_person_name'] ?? ('Tenant #' . (int) $tenant['id'])); ?> (manager_<?= $e($tenant['database_number'] ?? ''); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">Resumo</label>
                <input class="form-control" type="text" value="<?= (!empty($databaseTenantMembers) ? count($databaseTenantMembers) : count($tenants)); ?> vínculo(s) no banco" disabled>
              </div>
            </div>
          </div>

          <div class="people-repeat-container" id="tenantLinksContainer">
            <?php if (!empty($databaseTenantMembers)): ?>
              <?php foreach ($databaseTenantMembers as $i => $member): ?>
                <?php $isCurrentUserTenantMember = ((int) ($member['user_id'] ?? 0) === $userId); ?>
                <div class="border rounded p-3 mt-2 people-repeat-item people-repeat-item-tenant" data-repeat-item data-tenant-link-item data-existing="<?= $isCurrentUserTenantMember ? '1' : '0'; ?>">
                  <?php if ($isCurrentUserTenantMember): ?>
                    <input type="hidden" name="tenant_links[<?= (int) $i; ?>][tenant_id]" value="<?= (int) $member['tenant_id']; ?>">
                    <input type="hidden" name="tenant_links[<?= (int) $i; ?>][_delete]" value="0">
                  <?php endif; ?>

                  <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-4">
                      <label class="form-label">Pessoa associada</label>
                      <input class="form-control" type="text" value="<?= $e($member['person_name'] ?? ('Pessoa #' . (int) $member['person_id'])); ?>" disabled>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                      <label class="form-label">Banco</label>
                      <input class="form-control" type="text" value="manager_<?= $e($member['database_number'] ?? ''); ?>" disabled>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                      <label class="form-label">Situação do tenant</label>
                      <select class="form-select" <?= $isCurrentUserTenantMember ? 'name="tenant_links[' . (int) $i . '][status]"' : 'disabled'; ?>>
                        <option value="active" <?= $selected($member['status'] ?? '', 'active'); ?>>Ativo</option>
                        <option value="suspended" <?= $selected($member['status'] ?? '', 'suspended'); ?>>Suspenso</option>
                        <option value="pending" <?= $selected($member['status'] ?? '', 'pending'); ?>>Pendente</option>
                      </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-1 d-grid">
                      <button type="button" class="btn btn-outline-danger people-remove-button" <?= $isCurrentUserTenantMember ? 'data-remove="tenant-link"' : 'disabled'; ?> aria-label="Remover tenant">
                        <i class="bi bi-trash"></i>
                        <span class="d-lg-none ms-1">Remover</span>
                      </button>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Perfil no tenant</label>
                      <select class="form-select" <?= $isCurrentUserTenantMember ? 'name="tenant_links[' . (int) $i . '][role]"' : 'disabled'; ?>>
                        <option value="owner" <?= $selected($member['role'] ?? '', 'owner'); ?>>Owner</option>
                        <option value="admin" <?= $selected($member['role'] ?? '', 'admin'); ?>>Administrador</option>
                        <option value="user" <?= $selected($member['role'] ?? '', 'user'); ?>>Usuário</option>
                      </select>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Observações</label>
                      <textarea class="form-control" <?= $isCurrentUserTenantMember ? 'name="tenant_links[' . (int) $i . '][observations]"' : 'disabled'; ?> rows="2"><?= $e($member['observations'] ?? ''); ?></textarea>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <?php foreach ($tenants as $i => $tenant): ?>
                <div class="border rounded p-3 mt-2 people-repeat-item people-repeat-item-tenant" data-repeat-item data-tenant-link-item data-existing="1">
                  <input type="hidden" name="tenant_links[<?= (int) $i; ?>][tenant_id]" value="<?= (int) $tenant['id']; ?>">
                  <input type="hidden" name="tenant_links[<?= (int) $i; ?>][_delete]" value="0">

                  <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-4">
                      <label class="form-label">Pessoa associada</label>
                      <input class="form-control" type="text" value="<?= $e($tenant['associated_person_name'] ?? ('Tenant #' . (int) $tenant['id'])); ?>" disabled>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                      <label class="form-label">Banco</label>
                      <input class="form-control" type="text" value="manager_<?= $e($tenant['database_number'] ?? ''); ?>" disabled>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                      <label class="form-label">Situação do tenant</label>
                      <select class="form-select" name="tenant_links[<?= (int) $i; ?>][status]">
                        <option value="active" <?= $selected($tenant['status'] ?? '', 'active'); ?>>Ativo</option>
                        <option value="suspended" <?= $selected($tenant['status'] ?? '', 'suspended'); ?>>Suspenso</option>
                        <option value="pending" <?= $selected($tenant['status'] ?? '', 'pending'); ?>>Pendente</option>
                      </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-1 d-grid">
                      <button type="button" class="btn btn-outline-danger people-remove-button" data-remove="tenant-link" aria-label="Remover tenant">
                        <i class="bi bi-trash"></i>
                        <span class="d-lg-none ms-1">Remover</span>
                      </button>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Perfil no tenant</label>
                      <select class="form-select" name="tenant_links[<?= (int) $i; ?>][role]">
                        <option value="owner" <?= $selected($tenant['role'] ?? '', 'owner'); ?>>Owner</option>
                        <option value="admin" <?= $selected($tenant['role'] ?? '', 'admin'); ?>>Administrador</option>
                        <option value="user" <?= $selected($tenant['role'] ?? '', 'user'); ?>>Usuário</option>
                      </select>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Observações</label>
                      <textarea class="form-control" name="tenant_links[<?= (int) $i; ?>][observations]" rows="2"><?= $e($tenant['observations'] ?? ''); ?></textarea>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <template id="tenantLinkTemplate">
            <div class="border rounded p-3 mt-2 people-repeat-item people-repeat-item-tenant" data-repeat-item data-tenant-link-item data-existing="0">
              <input type="hidden" name="tenant_links[__INDEX__][_delete]" value="0">
              <div class="row g-3 align-items-end">
                <div class="col-12 col-lg-8">
                  <label class="form-label">Tenant</label>
                  <select class="form-select" name="tenant_links[__INDEX__][tenant_id]">
                    <option value="">Selecione um tenant...</option>
                    <?php foreach ($allTenants as $tenant): ?>
                      <option value="<?= (int) $tenant['id']; ?>">
                        <?= $e($tenant['associated_person_name'] ?? ('Tenant #' . (int) $tenant['id'])); ?> (manager_<?= $e($tenant['database_number'] ?? ''); ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                  <label class="form-label">Perfil</label>
                  <select class="form-select" name="tenant_links[__INDEX__][role]">
                    <option value="user" selected>Usuário</option>
                    <option value="admin">Administrador</option>
                    <option value="owner">Owner</option>
                  </select>
                </div>
                <div class="col-12 col-md-6 col-lg-2 d-grid">
                  <button type="button" class="btn btn-outline-danger people-remove-button" data-remove="tenant-link" aria-label="Remover tenant">
                    <i class="bi bi-trash"></i>
                    <span class="ms-1">Remover</span>
                  </button>
                </div>
              </div>
            </div>
          </template>
        </section>
        <!-- /Tenant -->

        <!-- Endereços -->
        <section id="section-addresses" class="people-form-section">
          <div class="people-section-header people-section-header-actions">
            <span class="people-section-icon"><i class="bi bi-geo-alt"></i></span>
            <div>
              <h2>Endereços</h2>
              <p>Localização e bairro</p>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm people-add-button" id="btnAddAddress">
              <i class="bi bi-plus-lg"></i>
              <span>Adicionar endereço</span>
            </button>
          </div>

          <div class="people-repeat-container" id="addressesContainer">
            <?php foreach ($addresses as $i => $address): ?>
              <?php
              $neighborhoodLabel = trim(implode(' - ', array_filter([
                $address['neighborhood_name'] ?? '',
                $address['city_name'] ?? '',
                $address['state_name'] ?? '',
              ])));
              ?>
              <div class="border rounded p-3 mt-2 people-repeat-item" data-repeat-item data-address-item data-existing="1">
                <input type="hidden" name="addresses[<?= (int) $i; ?>][id]" value="<?= (int) $address['id']; ?>">
                <input type="hidden" name="addresses[<?= (int) $i; ?>][_delete]" value="0">

                <div class="row g-3 align-items-end">
                  <div class="col-12 col-lg-4 position-relative">
                    <label class="form-label">Bairro</label>
                    <input type="hidden" class="addr-neighborhood-id" name="addresses[<?= (int) $i; ?>][neighborhood_id]" value="<?= (int) ($address['neighborhood_id'] ?? 0); ?>">
                    <input type="text" class="form-control addr-neighborhood-input" autocomplete="off" value="<?= $e($neighborhoodLabel); ?>" placeholder="Digite o nome do bairro...">
                    <ul class="list-group addr-neighborhood-results" style="display:none; position:absolute; width:100%; z-index:1050; max-height:220px; overflow-y:auto;"></ul>
                  </div>
                  <div class="col-12 col-lg-4">
                    <label class="form-label">Logradouro</label>
                    <input class="form-control" name="addresses[<?= (int) $i; ?>][street]" type="text" maxlength="255" value="<?= $e($address['street'] ?? ''); ?>">
                  </div>
                  <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label">CEP</label>
                    <input class="form-control mask-cep" name="addresses[<?= (int) $i; ?>][zip_code]" type="text" maxlength="9" value="<?= $e($address['zip_code'] ?? ''); ?>" placeholder="00000-000">
                  </div>
                  <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label">Número</label>
                    <input class="form-control" name="addresses[<?= (int) $i; ?>][number]" type="text" maxlength="10" value="<?= $e($address['number'] ?? ''); ?>">
                  </div>
                  <div class="col-12 col-lg-10">
                    <label class="form-label">Observações</label>
                    <input class="form-control" name="addresses[<?= (int) $i; ?>][observations]" type="text" maxlength="255" value="<?= $e($address['observations'] ?? ''); ?>">
                  </div>
                  <div class="col-12 col-lg-2 d-grid">
                    <button type="button" class="btn btn-outline-danger people-remove-button" data-remove="address">
                      <i class="bi bi-trash"></i>
                      <span>Remover</span>
                    </button>
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
            <div class="border rounded p-3 mt-2 people-repeat-item" data-repeat-item data-address-item data-existing="0">
              <input type="hidden" name="addresses[__INDEX__][_delete]" value="0">
              <div class="row g-3 align-items-end">
                <div class="col-12 col-lg-4 position-relative">
                  <label class="form-label">Bairro</label>
                  <input type="hidden" class="addr-neighborhood-id" name="addresses[__INDEX__][neighborhood_id]" value="">
                  <input type="text" class="form-control addr-neighborhood-input" autocomplete="off" placeholder="Digite o nome do bairro...">
                  <ul class="list-group addr-neighborhood-results" style="display:none; position:absolute; width:100%; z-index:1050; max-height:220px; overflow-y:auto;"></ul>
                </div>
                <div class="col-12 col-lg-4">
                  <label class="form-label">Logradouro</label>
                  <input class="form-control" name="addresses[__INDEX__][street]" type="text" maxlength="255" placeholder="Rua, avenida, etc.">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                  <label class="form-label">CEP</label>
                  <input class="form-control mask-cep" name="addresses[__INDEX__][zip_code]" type="text" maxlength="9" placeholder="00000-000">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                  <label class="form-label">Número</label>
                  <input class="form-control" name="addresses[__INDEX__][number]" type="text" maxlength="10" placeholder="Número">
                </div>
                <div class="col-12 col-lg-10">
                  <label class="form-label">Observações</label>
                  <input class="form-control" name="addresses[__INDEX__][observations]" type="text" maxlength="255" placeholder="Complemento, referência, etc.">
                </div>
                <div class="col-12 col-lg-2 d-grid">
                  <button type="button" class="btn btn-outline-danger people-remove-button" data-remove="address">
                    <i class="bi bi-trash"></i>
                    <span>Remover</span>
                  </button>
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
        <section id="section-contacts" class="people-form-section">
          <div class="people-section-header">
            <span class="people-section-icon"><i class="bi bi-chat-dots"></i></span>
            <div>
              <h2>Contatos</h2>
              <p>Telefones e e-mails</p>
            </div>
          </div>

          <div class="people-contact-group">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <div class="fw-semibold">Telefones</div>
                <div class="text-body-secondary small"><?= count($phones); ?> telefone(s) cadastrado(s)</div>
              </div>
              <button type="button" class="btn btn-outline-primary btn-sm people-add-button" id="btnAddPhone">
                <i class="bi bi-plus-lg"></i>
                <span>Adicionar telefone</span>
              </button>
            </div>

            <div class="people-repeat-container mt-2" id="phonesContainer">
              <?php foreach ($phones as $i => $phone): ?>
                <div class="border rounded p-3 mt-2 people-repeat-item" data-repeat-item data-phone-item data-existing="1">
                  <input type="hidden" name="phones[<?= (int) $i; ?>][id]" value="<?= (int) $phone['id']; ?>">
                  <input type="hidden" name="phones[<?= (int) $i; ?>][_delete]" value="0">

                  <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-4">
                      <label class="form-label">Número</label>
                      <input class="form-control mask-phone-intl" name="phones[<?= (int) $i; ?>][number]" type="text" inputmode="tel" maxlength="18" value="<?= $e(maskPhoneBr($phone['phone_number'] ?? '')); ?>" placeholder="+55(XX)XXXXX-XXXX">
                    </div>
                    <div class="col-12 col-lg-6">
                      <label class="form-label">Observações</label>
                      <input class="form-control" name="phones[<?= (int) $i; ?>][notes]" type="text" value="<?= $e($phone['observations'] ?? ''); ?>" placeholder="Ex.: celular, comercial, WhatsApp">
                    </div>
                    <div class="col-12 col-lg-2 d-grid">
                      <button type="button" class="btn btn-outline-danger people-remove-button" data-remove="phone">
                        <i class="bi bi-trash"></i>
                        <span>Remover</span>
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <template id="phoneTemplate">
              <div class="border rounded p-3 mt-2 people-repeat-item" data-repeat-item data-phone-item data-existing="0">
                <input type="hidden" name="phones[__INDEX__][_delete]" value="0">
                <div class="row g-3 align-items-end">
                  <div class="col-12 col-lg-4">
                    <label class="form-label">Número</label>
                    <input class="form-control mask-phone-intl" name="phones[__INDEX__][number]" type="text" inputmode="tel" maxlength="18" placeholder="+55(XX)XXXXX-XXXX">
                  </div>
                  <div class="col-12 col-lg-6">
                    <label class="form-label">Observações</label>
                    <input class="form-control" name="phones[__INDEX__][notes]" type="text" placeholder="Ex.: celular, comercial, WhatsApp">
                  </div>
                  <div class="col-12 col-lg-2 d-grid">
                    <button type="button" class="btn btn-outline-danger people-remove-button" data-remove="phone">
                      <i class="bi bi-trash"></i>
                      <span>Remover</span>
                    </button>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <div class="people-contact-group">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <div class="fw-semibold">E-mails</div>
                <div class="text-body-secondary small"><?= count($emails); ?> e-mail(s) cadastrado(s)</div>
              </div>
              <button type="button" class="btn btn-outline-primary btn-sm people-add-button" id="btnAddEmail">
                <i class="bi bi-plus-lg"></i>
                <span>Adicionar e-mail</span>
              </button>
            </div>

            <div class="people-repeat-container mt-2" id="emailsContainer">
              <?php foreach ($emails as $i => $email): ?>
                <div class="border rounded p-3 mt-2 people-repeat-item" data-repeat-item data-email-item data-existing="1">
                  <input type="hidden" name="emails[<?= (int) $i; ?>][id]" value="<?= (int) $email['id']; ?>">
                  <input type="hidden" name="emails[<?= (int) $i; ?>][_delete]" value="0">

                  <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-4">
                      <label class="form-label">Endereço de e-mail</label>
                      <input class="form-control" name="emails[<?= (int) $i; ?>][address]" type="email" value="<?= $e($email['email'] ?? ''); ?>" placeholder="exemplo@email.com">
                    </div>
                    <div class="col-12 col-lg-4">
                      <label class="form-label">Descrição</label>
                      <input class="form-control" name="emails[<?= (int) $i; ?>][description]" type="text" value="<?= $e($email['observations'] ?? ''); ?>" placeholder="Ex.: financeiro, pessoal, comercial">
                    </div>
                    <div class="col-12 col-md-6 col-lg-2">
                      <label class="form-label">E-mail de login?</label>
                      <select class="form-select" name="emails[<?= (int) $i; ?>][is_login]">
                        <option value="0" <?= $selected((int) ($email['is_login'] ?? 0), 0); ?>>Não</option>
                        <option value="1" <?= $selected((int) ($email['is_login'] ?? 0), 1); ?>>Sim</option>
                      </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-2 d-grid">
                      <button type="button" class="btn btn-outline-danger people-remove-button" data-remove="email">
                        <i class="bi bi-trash"></i>
                        <span>Remover</span>
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <template id="emailTemplate">
              <div class="border rounded p-3 mt-2 people-repeat-item" data-repeat-item data-email-item data-existing="0">
                <input type="hidden" name="emails[__INDEX__][_delete]" value="0">
                <div class="row g-3 align-items-end">
                  <div class="col-12 col-lg-4">
                    <label class="form-label">Endereço de e-mail</label>
                    <input class="form-control" name="emails[__INDEX__][address]" type="email" placeholder="exemplo@email.com">
                  </div>
                  <div class="col-12 col-lg-4">
                    <label class="form-label">Descrição</label>
                    <input class="form-control" name="emails[__INDEX__][description]" type="text" placeholder="Ex.: financeiro, pessoal, comercial">
                  </div>
                  <div class="col-12 col-md-6 col-lg-2">
                    <label class="form-label">E-mail de login?</label>
                    <select class="form-select" name="emails[__INDEX__][is_login]">
                      <option value="0" selected>Não</option>
                      <option value="1">Sim</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-6 col-lg-2 d-grid">
                    <button type="button" class="btn btn-outline-danger people-remove-button" data-remove="email">
                      <i class="bi bi-trash"></i>
                      <span>Remover</span>
                    </button>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </section>
        <!-- /Contatos -->
      </div>

      <div class="card-footer bg-body d-flex justify-content-end gap-2 people-form-footer">
        <a href="<?= url('person'); ?>" class="btn btn-secondary">Cancelar</a>
        <button class="btn btn-primary" type="submit">Salvar alterações</button>
      </div>
    </div>
  </form>
</div>

<div class="modal fade" id="createLinkedPersonModal" tabindex="-1" aria-labelledby="createLinkedPersonModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form id="createLinkedPersonForm" novalidate>
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="createLinkedPersonModalLabel">Adicionar pessoa ao tenant</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="tenant_id" value="<?= (int) $currentTenantId; ?>">

          <div class="alert alert-secondary py-2 mb-3">
            <div class="small mb-0">
              Tenant alvo:
              <strong>
                <?php if ($currentTenantId > 0): ?>
                  #<?= (int) $currentTenantId; ?> (manager_<?= $e($currentTenantDatabaseNumber); ?>)
                <?php else: ?>
                  não definido
                <?php endif; ?>
              </strong>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-12">
              <h6 class="mb-0">Pessoa</h6>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Nome completo</label>
              <input type="text" class="form-control" name="person[name]" maxlength="255" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Nome fantasia</label>
              <input type="text" class="form-control" name="person[fantasy]" maxlength="255">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Tipo de pessoa</label>
              <select class="form-select" name="person[type]" required>
                <option value="">Selecione...</option>
                <option value="individual">Pessoa física</option>
                <option value="company">Pessoa jurídica</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Documento (CPF/CNPJ)</label>
              <input type="text" class="form-control mask-cpf-cnpj" name="person[document]" maxlength="18" required>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Data de nascimento</label>
              <input type="text" class="form-control mask-date" name="person[birthdate]" placeholder="DD/MM/AAAA">
            </div>
          </div>

          <hr class="my-3">

          <div class="row g-3">
            <div class="col-12">
              <h6 class="mb-0">Usuário</h6>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Nome de usuário</label>
              <input type="text" class="form-control" name="user[username]" maxlength="45" required>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Tipo de acesso</label>
              <select class="form-select" name="user[type]" required>
                <option value="">Selecione...</option>
                <option value="root">Root</option>
                <option value="admin">Administrador</option>
                <option value="user">Usuário</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Situação</label>
              <select class="form-select" name="user[active]">
                <option value="1" selected>Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Senha</label>
              <input type="password" class="form-control" name="user[password]" minlength="8" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Confirmar senha</label>
              <input type="password" class="form-control" name="user[password_confirm]" minlength="8" required>
            </div>
          </div>

          <hr class="my-3">

          <div class="row g-3">
            <div class="col-12">
              <h6 class="mb-0">E-mail</h6>
            </div>
            <div class="col-12 col-md-5">
              <label class="form-label">Endereço de e-mail</label>
              <input type="email" class="form-control" name="emails[0][address]" maxlength="255" placeholder="exemplo@email.com" required>
            </div>
            <div class="col-12 col-md-5">
              <label class="form-label">Descrição</label>
              <input type="text" class="form-control" name="emails[0][description]" maxlength="500" placeholder="Ex.: principal, financeiro, comercial">
            </div>
            <div class="col-12 col-md-2">
              <label class="form-label">E-mail de login?</label>
              <select class="form-select" name="emails[0][is_login]">
                <option value="1" selected>Sim</option>
                <option value="0">Não</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitCreateLinkedPerson">Salvar pessoa</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const PERSON_NEIGHBORHOOD_SEARCH_URL = "<?= url('person/neighborhoodSearch'); ?>";
  const PERSON_NEIGHBORHOOD_CREATE_URL = "<?= url('person/neighborhoodCreate'); ?>";
  const PERSON_CITY_SEARCH_URL         = "<?= url('person/citySearch'); ?>";
  const PERSON_STORE_LINKED_TENANT_URL = "<?= url('person/storeLinkedTenant'); ?>";
  const CURRENT_PERSON_TENANT_ID       = <?= (int) $currentTenantId; ?>;
  let addressCounter    = <?= (int) count($addresses); ?>;
  let phoneCounter      = <?= (int) count($phones); ?>;
  let emailCounter      = <?= (int) count($emails); ?>;
  let tenantLinkCounter = <?= (int) count($tenants); ?>;
</script>
<script src="<?= url('assets/js/people/edit.js?v=3'); ?>"></script>
