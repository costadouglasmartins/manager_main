<?php $formError = $formError ?? null; ?>
<link rel="stylesheet" href="<?= url('assets/css/people/create.css?v=3'); ?>">

<div class="people-create-page">
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('person'); ?>">Pessoas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Nova pessoa</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<div class="people-create-toolbar">
	<div>
		<h5 class="mb-0">Nova pessoa</h5>
	</div>
	<a class="btn btn-outline-secondary btn-sm" href="<?= url('person'); ?>">
		<i class="bi bi-arrow-left"></i>
		<span>Voltar para pessoas</span>
	</a>
</div>

<?php if (!empty($formError)): ?>
	<div class="alert alert-danger" role="alert">
		<?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?>
	</div>
<?php endif; ?>

<!-- Progress -->
<div class="card shadow-sm mb-3 people-progress-card">
	<div class="card-body">
		<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
			<div>
				<div class="fw-semibold" id="stepTitle">Etapa 1 de 6</div>
				<div class="text-body-secondary small" id="stepSubtitle">Pessoa</div>
			</div>
			<span class="badge text-bg-secondary" id="stepBadge">1/6</span>
		</div>

		<div class="progress mt-3 people-progress" role="progressbar" aria-label="Progresso do cadastro" aria-valuemin="0" aria-valuemax="100">
			<div class="progress-bar" id="progressBar" style="width: 20%"></div>
		</div>

		<div class="row g-2 mt-3 people-step-grid">
			<div class="col-6 col-md">
				<button type="button" class="btn w-100 btn-primary people-step-button" data-step-jump="1">
					<span class="people-step-number">1</span>
					<span>Pessoa</span>
				</button>
			</div>
			<div class="col-6 col-md">
				<button type="button" class="btn w-100 btn-outline-secondary people-step-button" data-step-jump="2">
					<span class="people-step-number">2</span>
					<span>Usuário</span>
				</button>
			</div>
			<div class="col-6 col-md">
				<button type="button" class="btn w-100 btn-outline-secondary people-step-button" data-step-jump="3">
					<span class="people-step-number">3</span>
					<span>Tenant</span>
				</button>
			</div>
			<div class="col-6 col-md">
				<button type="button" class="btn w-100 btn-outline-secondary people-step-button" data-step-jump="4">
					<span class="people-step-number">4</span>
					<span>Endereços</span>
				</button>
			</div>
			<div class="col-6 col-md">
				<button type="button" class="btn w-100 btn-outline-secondary people-step-button" data-step-jump="5">
					<span class="people-step-number">5</span>
					<span>Contatos</span>
				</button>
			</div>
			<div class="col-12 col-md">
				<button type="button" class="btn w-100 btn-outline-secondary people-step-button" data-step-jump="6">
					<span class="people-step-number">6</span>
					<span>Revisão</span>
				</button>
			</div>
		</div>
	</div>
</div>

<form action="<?= url('person/store'); ?>" method="POST" id="signupForm" class="needs-validation people-create-form" novalidate>
	<div class="card shadow-sm people-form-card">
		<div class="card-body">

			<!-- Step 1: Pessoa -->
			<section data-step="1">
				<div class="people-section-header">
					<span class="people-section-icon"><i class="bi bi-person-vcard"></i></span>
					<div>
						<h2>Pessoa</h2>
						<p>Identificação principal</p>
					</div>
				</div>

				<div class="row g-3">
					<div class="col-12 col-md-6">
						<label class="form-label" for="personName">Nome completo</label>
						<input autocomplete="off" class="form-control" id="personName" name="person[name]" placeholder="Nome completo" type="text" required />
						<div class="invalid-feedback">Informe o nome.</div>
					</div>

					<div class="col-12 col-md-6">
						<label class="form-label" for="personFantasy">Nome fantasia</label>
						<input autocomplete="off" class="form-control" id="personFantasy" name="person[fantasy]" placeholder="Nome fantasia" type="text" />
					</div>

					<div class="col-12 col-md-4">
						<label class="form-label" for="personType">Tipo de pessoa</label>
						<select autocomplete="off" class="form-select" id="personType" name="person[type]" required>
							<option value="" selected disabled>Selecione o tipo...</option>
							<option value="individual">Pessoa física</option>
							<option value="company">Pessoa jurídica</option>
						</select>
						<div class="invalid-feedback">Selecione o tipo.</div>
					</div>

					<div class="col-12 col-md-4">
						<label class="form-label" for="personDocument">Documento (CPF/CNPJ)</label>
						<input autocomplete="off" class="form-control mask-cpf-cnpj" id="personDocument" name="person[document]" placeholder="CPF ou CNPJ" type="text" required />
						<div class="invalid-feedback">Informe o documento.</div>
					</div>

					<div class="col-12 col-md-4">
						<label class="form-label" for="personBirthdate">Data de nascimento</label>
						<input autocomplete="off" class="form-control mask-date" id="personBirthdate" name="person[birthdate]" placeholder="DD/MM/AAAA" type="text" />
					</div>

					<input type="hidden" value="1" name="person[active]">
				</div>
			</section>
			<!-- /Step 1 -->

			<!-- Step 2: Usuário -->
			<section data-step="2" class="d-none">
				<div class="people-section-header">
					<span class="people-section-icon"><i class="bi bi-person-lock"></i></span>
					<div>
						<h2>Usuário</h2>
						<p>Acesso ao sistema</p>
					</div>
				</div>

				<div class="row g-3">
					<div class="col-12 col-md-6">
						<label class="form-label" for="userUsername">Nome de usuário</label>
						<input autocomplete="off" class="form-control" id="userUsername" name="user[username]" placeholder="Nome de usuário" type="text" required />
						<div class="invalid-feedback">Informe o nome de usuário.</div>
					</div>

					<div class="col-12 col-md-6">
						<label class="form-label" for="userType">Tipo de acesso</label>
						<select autocomplete="off" class="form-select" id="userType" name="user[type]" required>
							<option value="" selected disabled>Selecione o tipo de acesso...</option>
							<option value="root">Root</option>
							<option value="admin">Administrador</option>
							<option value="user">Usuário</option>
						</select>
						<div class="invalid-feedback">Selecione o tipo de acesso.</div>
					</div>

					<div class="col-12 col-md-6">
						<label class="form-label" for="userPassword">Senha</label>
						<input autocomplete="off" class="form-control" id="userPassword" name="user[password]" placeholder="Senha" type="password" minlength="8" required />
						<div class="invalid-feedback">A senha deve ter pelo menos 8 caracteres.</div>
					</div>

					<div class="col-12 col-md-6">
						<label class="form-label" for="userPassword2">Confirmar senha</label>
						<input autocomplete="off" class="form-control" id="userPassword2" name="user[password_confirm]" placeholder="Confirmar senha" type="password" minlength="8" required />
						<div class="invalid-feedback">Confirme a senha.</div>
					</div>

					<input type="hidden" value="1" name="user[active]">
				</div>
			</section>
			<!-- /Step 2 -->

			<!-- Step 3: Tenant -->
			<section data-step="3" class="d-none">
				<div class="people-section-header">
					<span class="people-section-icon"><i class="bi bi-database"></i></span>
					<div>
						<h2>Tenant</h2>
						<p>Vínculo opcional</p>
					</div>
				</div>

				<div class="row g-3">
					<div class="col-12">
						<label class="form-label">Associar tenant?</label>
						<select autocomplete="off" class="form-select" id="tenantMode" name="tenant[mode]">
							<option value="none" selected>Não associar tenant</option>
							<option value="new">Criar novo tenant</option>
							<option value="existing">Selecionar tenant existente</option>
						</select>
					</div>

					<!-- Criar novo tenant -->
					<div class="col-12 d-none" id="tenantNewFields">
						<div class="border rounded p-3 people-inline-panel">
							<div class="row g-3">
								<div class="col-12 col-md-6">
									<label class="form-label" for="tenantDatabaseNumber">N. do banco</label>
									<input autocomplete="off" class="form-control" id="tenantDatabaseNumber" type="text" value="<?= htmlspecialchars($nextDatabaseNumber) ?>" disabled />
									<div class="form-text">Gerado automaticamente: <code>manager_<?= htmlspecialchars($nextDatabaseNumber) ?></code></div>
								</div>
								<div class="col-12 col-md-6">
									<label class="form-label" for="tenantStatus">Situação</label>
									<select autocomplete="off" class="form-select" id="tenantStatus" name="tenant[status]">
										<option value="active" selected>Ativo</option>
									</select>
								</div>
								<div class="col-12">
									<label class="form-label" for="tenantObservations">Observações</label>
									<textarea autocomplete="off" class="form-control" id="tenantObservations" name="tenant[observations]" rows="2" placeholder="Observações gerais..."></textarea>
								</div>
							</div>
						</div>
					</div>

					<!-- Selecionar tenant existente -->
					<div class="col-12 d-none" id="tenantExistingFields">
						<div class="border rounded p-3 people-inline-panel">
							<div class="row g-3">
								<div class="col-12">
									<label class="form-label" for="tenantExistingId">Selecionar tenant</label>
									<select autocomplete="off" class="form-select" id="tenantExistingId" name="tenant[existing_id]">
										<option value="" selected disabled>Selecione um tenant...</option>
										<?php foreach ($tenants as $t): ?>
											<option value="<?= (int) $t['id']; ?>">
												<?= htmlspecialchars($t['associated_person_name'] ?? ('Tenant #' . (int) $t['id'])); ?>
												— <?= htmlspecialchars($t['database_number']); ?>
												(<?= htmlspecialchars($t['status']); ?>)
											</option>
										<?php endforeach; ?>
									</select>
									<div class="invalid-feedback">Selecione um tenant.</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>
			<!-- /Step 3 -->

			<!-- Step 4: Endereços -->
			<section data-step="4" class="d-none">
				<div class="people-section-header">
					<span class="people-section-icon"><i class="bi bi-geo-alt"></i></span>
					<div>
						<h2>Endereços</h2>
						<p>Localização e bairro</p>
					</div>
				</div>

				<div class="row g-3">
					<div class="col-12">
						<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
							<div>
								<div class="fw-semibold">Endereços</div>
								<div class="text-body-secondary small">Obrigatório ao menos um endereço. Você pode cadastrar mais de um.</div>
							</div>
							<button type="button" class="btn btn-outline-primary btn-sm people-add-button" id="btnAddAddress">
								<i class="bi bi-plus-lg"></i>
								<span>Adicionar endereço</span>
							</button>
						</div>

						<div class="mt-2 people-repeat-container" id="addressesContainer"></div>

						<template id="addressTemplate">
							<div class="border rounded p-3 mt-2 people-repeat-item">
								<div class="row g-2 align-items-end">
									<div class="col-12 col-md-3">
										<label class="form-label">CEP</label>
										<input autocomplete="off" class="form-control mask-cep" name="addresses[][zip_code]" type="text" maxlength="9" placeholder="00000-000" />
									</div>
									<div class="col-12 col-md-5">
										<label class="form-label">Logradouro</label>
										<input autocomplete="off" class="form-control" name="addresses[][street]" type="text" maxlength="255" placeholder="Rua, Avenida, etc." />
									</div>
									<div class="col-12 col-md-2">
										<label class="form-label">Número</label>
										<input autocomplete="off" class="form-control" name="addresses[][number]" type="text" maxlength="10" placeholder="Nº" />
									</div>
									<div class="col-12 col-md-2 d-grid">
										<label class="form-label">&nbsp;</label>
										<button type="button" class="btn btn-outline-danger people-remove-button" data-remove="address" aria-label="Remover endereço">
											<i class="bi bi-trash"></i>
											<span class="d-md-none ms-1">Remover</span>
										</button>
									</div>
									<div class="col-12 col-md-6">
										<label class="form-label">Observações</label>
										<input autocomplete="off" class="form-control" name="addresses[][observations]" type="text" maxlength="255" placeholder="Complemento, referência, etc." />
									</div>
									<div class="col-12 col-md-6 position-relative">
										<label class="form-label">Bairro</label>
										<input type="hidden" class="addr-neighborhood-id" name="addresses[][neighborhood_id]" value=""/>
										<input autocomplete="off" type="text" class="form-control addr-neighborhood-input" name="addresses[][neighborhood_name]" placeholder="Digite o nome do bairro..." />
										<ul class="list-group addr-neighborhood-results" style="display:none; position:absolute; width:100%; z-index:1050; max-height:220px; overflow-y:auto;"></ul>
									</div>

									<!-- Painel inline: cadastrar novo bairro -->
									<div class="col-12 addr-create-panel d-none">
										<div class="border border-warning rounded p-3 bg-warning-subtle">
											<p class="fw-semibold small mb-2">Cadastrar novo bairro</p>
											<div class="row g-2 align-items-end">
												<div class="col-12 col-md-5">
													<label class="form-label small">Nome do bairro</label>
													<input autocomplete="off" type="text" class="form-control form-control-sm addr-new-neighborhood-name" placeholder="Ex.: Centro" />
												</div>
												<div class="col-12 col-md-5 position-relative">
													<label class="form-label small">Cidade</label>
													<input type="hidden" class="addr-new-city-id" value="" />
													<input type="text" class="form-control form-control-sm addr-new-city-input" autocomplete="off" placeholder="Digite o nome da cidade..." />
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
					</div>
				</div>
			</section>
			<!-- /Step 4 -->

			<!-- Step 5: Contatos -->
			<section data-step="5" class="d-none">
				<div class="people-section-header">
					<span class="people-section-icon"><i class="bi bi-chat-dots"></i></span>
					<div>
						<h2>Contatos</h2>
						<p>Telefone e e-mail</p>
					</div>
				</div>

				<div class="row g-3">
					<!-- Telefones -->
					<div class="col-12">
						<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
							<div>
								<div class="fw-semibold">Telefones</div>
								<div class="text-body-secondary small">Obrigatório ao menos um telefone. Você pode cadastrar mais de um.</div>
							</div>
							<button type="button" class="btn btn-outline-primary btn-sm people-add-button" id="btnAddPhone">
								<i class="bi bi-plus-lg"></i>
								<span>Adicionar telefone</span>
							</button>
						</div>

						<div class="mt-2 people-repeat-container" id="phonesContainer"></div>

						<template id="phoneTemplate">
							<div class="border rounded p-3 mt-2 people-repeat-item">
								<div class="row g-2 align-items-end">
									<div class="col-12 col-md-4">
										<label class="form-label">Número</label>
										<input autocomplete="off" class="form-control mask-phone" name="phones[][number]" type="text" inputmode="tel" maxlength="18" placeholder="+XX(XX)XXXXX-XXXX"/>
										<div class="invalid-feedback">Informe o telefone.</div>
									</div>
									<div class="col-12 col-md-7">
										<label class="form-label">Observações</label>
										<input autocomplete="off" class="form-control" name="phones[][notes]" type="text" placeholder="Ex.: celular, comercial, WhatsApp" />
									</div>
									<div class="col-12 col-md-1 d-grid">
										<button type="button" class="btn btn-outline-danger people-remove-button" data-remove="phone" aria-label="Remover telefone">
											<i class="bi bi-trash"></i>
											<span class="d-md-none ms-1">Remover</span>
										</button>
									</div>
								</div>
							</div>
						</template>
					</div>

					<!-- E-mails -->
					<div class="col-12 mt-2">
						<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
							<div>
								<div class="fw-semibold">E-mails</div>
								<div class="text-body-secondary small">Obrigatório ao menos um e-mail. Você pode cadastrar mais de um.</div>
							</div>
							<button type="button" class="btn btn-outline-primary btn-sm people-add-button" id="btnAddEmail">
								<i class="bi bi-plus-lg"></i>
								<span>Adicionar e-mail</span>
							</button>
						</div>

						<div class="mt-2 people-repeat-container" id="emailsContainer"></div>

						<template id="emailTemplate">
							<div class="border rounded p-3 mt-2 people-repeat-item">
								<div class="row g-2 align-items-end">
									<div class="col-12 col-md-4">
										<label class="form-label">Endereço de e-mail</label>
										<input autocomplete="off" class="form-control" name="emails[][address]" type="email" placeholder="exemplo@email.com" />
										<div class="invalid-feedback">Informe um e-mail válido.</div>
									</div>
									<div class="col-12 col-md-4">
										<label class="form-label">Descrição</label>
										<input autocomplete="off" class="form-control" name="emails[][description]" type="text" placeholder="Ex.: financeiro, pessoal, comercial" />
									</div>
									<div class="col-12 col-md-3">
										<label class="form-label">E-mail de login?</label>
										<select autocomplete="off" class="form-select" name="emails[][is_login]">
											<option value="0" selected>Não</option>
											<option value="1">Sim</option>
										</select>
									</div>
									<div class="col-12 col-md-1 d-grid">
										<button type="button" class="btn btn-outline-danger people-remove-button" data-remove="email" aria-label="Remover e-mail">
											<i class="bi bi-trash"></i>
											<span class="d-md-none ms-1">Remover</span>
										</button>
									</div>
								</div>
							</div>
						</template>
					</div>
				</div>
			</section>
			<!-- /Step 5: Contatos -->

			<!-- Step 6: Revisão -->
			<section data-step="6" class="d-none">
				<div class="people-section-header">
					<span class="people-section-icon"><i class="bi bi-check2-circle"></i></span>
					<div>
						<h2>Revisão</h2>
						<p>Confirmação final</p>
					</div>
				</div>

				<div class="alert alert-info mb-3 people-review-alert" role="alert">
					Revise seus dados antes de concluir.
				</div>

				<div class="table-responsive">
					<table class="table table-sm align-middle">
						<thead>
							<tr>
								<th style="width: 30%;">Campo</th>
								<th>Valor</th>
							</tr>
						</thead>
						<tbody id="reviewBody"></tbody>
					</table>
				</div>

				<div class="form-check mt-2">
					<input class="form-check-input" type="checkbox" id="confirmReview" required />
					<label class="form-check-label" for="confirmReview">Confirmo que revisei meus dados</label>
					<div class="invalid-feedback">Você precisa confirmar a revisão.</div>
				</div>
			</section>
			<!-- /Step 6 -->

		</div>

		<div class="card-footer bg-body d-flex flex-wrap gap-2 justify-content-between align-items-center people-form-footer">
			<button type="button" class="btn btn-secondary" id="btnBack" disabled>
				<i class="bi bi-arrow-left"></i>
				<span>Voltar</span>
			</button>

			<div class="d-flex flex-wrap gap-2">
				<button type="button" class="btn btn-primary" id="btnNext">
					<span>Continuar</span>
					<i class="bi bi-arrow-right"></i>
				</button>
				<button type="submit" class="btn btn-success d-none" id="btnSubmit">
					<i class="bi bi-check2"></i>
					<span>Concluir</span>
				</button>
			</div>
		</div>
	</div>
</form>
</div>
<script>
	const PERSON_NEIGHBORHOOD_SEARCH_URL = "<?= url('person/neighborhoodSearch'); ?>";
	const PERSON_NEIGHBORHOOD_CREATE_URL = "<?= url('person/neighborhoodCreate'); ?>";
	const PERSON_CITY_SEARCH_URL         = "<?= url('person/citySearch'); ?>";
	const PERSON_DOCUMENT_EXISTS_URL     = "<?= url('person/documentExists'); ?>";
	const PERSON_USERNAME_EXISTS_URL     = "<?= url('person/usernameExists'); ?>";
	const PERSON_PHONE_NUMBER_EXISTS_URL = "<?= url('person/phoneNumberExists'); ?>";
	const PERSON_EMAIL_EXISTS_URL        = "<?= url('person/emailExists'); ?>";
</script>
<script src="<?= url('assets/js/people/create.js?v=13'); ?>"></script>
<script src="<?= url('assets/js/masks.js?v=5'); ?>"></script>
