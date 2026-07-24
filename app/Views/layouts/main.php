<?php
$supportChatNotificationsEnabled = !empty($_SESSION['manager_main']['user']) && function_exists('authIsRoot') && authIsRoot();
$isFinancialSheetView = strpos((string) ($content ?? ''), 'financial-sheet-page') !== false;
$managerMainUser = $_SESSION['manager_main']['user'] ?? null;
$userPresence = null;
if (!empty($managerMainUser) && class_exists('App\\Services\\UserPresenceService'))
  {
  $userPresence = (new \App\Services\UserPresenceService())->current((int) ($managerMainUser['id'] ?? 0));
  }
$userPresence = is_array($userPresence) ? $userPresence : \App\Services\UserPresenceService::statusMeta('offline') + ['status' => 'offline'];
$presenceOptions = [
  'online' => [
    'label' => 'Disponível',
    'description' => 'Pronto para atender',
    'icon' => 'bi-circle-fill',
  ],
  'away' => [
    'label' => 'Ausente',
    'description' => 'Volto em breve',
    'icon' => 'bi-clock-fill',
  ],
  'busy' => [
    'label' => 'Ocupado',
    'description' => 'Evitar novas interrupções',
    'icon' => 'bi-dash-circle-fill',
  ],
  'offline' => [
    'label' => 'Offline',
    'description' => 'Fora de atendimento',
    'icon' => 'bi-circle',
  ],
];
$currentPresenceStatus = (string) ($userPresence['status'] ?? 'offline');
?>
<!DOCTYPE html>
<html lang="pt-BR" id="htmlRoot" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <script src="/assets/helpers/helper.js?v=3"></script>
  <script>
    AppTheme.initialize({ storageKey: "theme-manager-main" });
  </script>
  <link rel="stylesheet" href="/assets/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/bootstrap/images/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('assets/css/main.css?v=12'); ?>">
  <link rel="stylesheet" href="<?= url('assets/css/menu.css?v=6'); ?>">
  <?php if ($supportChatNotificationsEnabled): ?>
    <link rel="stylesheet" href="<?= url('assets/css/support_chats/notifications.css?v=1'); ?>">
  <?php endif; ?>
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/bootstrap/images/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/bootstrap/images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/bootstrap/images/favicon-16x16.png">
  <link rel="manifest" href="<?= url('/site.webmanifest'); ?>">
  <title>Manager - <?=$title?></title>
</head>
<body class="app-shell">
  
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm app-navbar">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="<?= url('/'); ?>">
        <!--<i class="bi bi-boxes me-2"></i>-->
        <img src="/assets/bootstrap/images/favicon-32x32.png">
      </a>

      <!-- Botão toggle mobile -->
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
        aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- Itens -->
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php if (!empty($_SESSION['manager_main']['user'])): ?>
            <!-- Four levels menu -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="menuExemplo" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-layers me-1"></i> Cadastros
              </a>
              <ul class="dropdown-menu" aria-labelledby="menuExemplo">
                <?php if (authCan('neighborhood.index')): ?>
                  <!-- Neighborhood -->
                  <li class="dropdown-submenu">
                    <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-signpost-split me-2"></i>Bairros</a>
                    <ul class="dropdown-menu">
                      <?php if (authCan('neighborhood.create')): ?>
                      <li><a class="dropdown-item" href="<?= url('neighborhood/create'); ?>"><i class="bi bi-plus-square me-2"></i>Cadastrar</a></li>
                      <li><hr class="dropdown-divider" /></li>
                      <?php endif; ?>
                      <li><a class="dropdown-item" href="<?= url('neighborhood'); ?>"><i class="bi bi-list me-2"></i>Listar</a></li>
                    </ul>
                  </li>
                  <!-- /Neighborhood -->
                <?php endif; ?>

                <?php if (authCan('bank.index')): ?>
                  <li><hr class="dropdown-divider" /></li>
                <?php endif; ?>

                <?php if (authCan('city.index')): ?>
                  <!-- City -->
                  <li class="dropdown-submenu">
                    <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-globe-americas me-2"></i>Cidades</a>
                    <ul class="dropdown-menu">
                      <?php if (authCan('city.create')): ?>
                        <li><a class="dropdown-item" href="<?= url('city/create'); ?>"><i class="bi bi-plus-square me-2"></i>Cadastrar</a></li>
                        <li><hr class="dropdown-divider" /></li>
                      <?php endif; ?>
                      <li><a class="dropdown-item" href="<?= url('city'); ?>"><i class="bi bi-list me-2"></i>Listar</a></li>
                    </ul>
                  </li>
                  <!-- /City -->
                <?php endif; ?>

                <li><hr class="dropdown-divider" /></li>

                <?php if (authCan('state.index')): ?>
                  <!-- State -->
                  <li class="dropdown-submenu">
                    <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-globe-americas me-2"></i>Estados</a>
                    <ul class="dropdown-menu">
                      <?php if (authCan('state.index')): ?>
                        <li><a class="dropdown-item" href="<?= url('state/create'); ?>"><i class="bi bi-plus-square me-2"></i>Cadastrar</a></li>
                        <li><hr class="dropdown-divider" /></li>
                      <?php endif; ?>
                      <li><a class="dropdown-item" href="<?= url('state'); ?>"><i class="bi bi-list me-2"></i>Listar</a></li>
                    </ul>
                  </li>
                  <!-- /State -->
                <?php endif; ?>

                <li><hr class="dropdown-divider" /></li>

                <?php if (authCan('country.index')): ?>
                  <!-- Country -->
                  <li class="dropdown-submenu">
                    <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-globe-americas me-2"></i>Países</a>
                    <ul class="dropdown-menu">
                      <?php if (authCan('country.create')): ?>
                        <li><a class="dropdown-item" href="<?= url('country/create'); ?>"><i class="bi bi-plus-square me-2"></i>Cadastrar</a></li>
                        <li><hr class="dropdown-divider" /></li>
                      <?php endif; ?>
                      <li><a class="dropdown-item" href="<?= url('country'); ?>"><i class="bi bi-list me-2"></i></i>Listar</a></li>
                    </ul>
                  </li>
                  <!-- /Country -->
                <?php endif; ?>

                <li><hr class="dropdown-divider" /></li>

                <?php if (authCan('person.index')): ?>
                  <!-- Person -->
                  <li class="dropdown-submenu">
                    <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-people-fill me-2"></i>Pessoas / Tenants</a>
                    <ul class="dropdown-menu">

                      <li><a class="dropdown-item" href="<?= url('person/create'); ?>"><i class="bi bi-person-fill-add me-2"></i>Cadastrar</a></li>
                      <li><hr class="dropdown-divider" /></li>
                      <li><a class="dropdown-item" href="<?= url('person'); ?>"><i class="bi bi-person-lines-fill me-2"></i>Listar</a></li>

                      <li><hr class="dropdown-divider" /></li>

                      <li class="dropdown-submenu">
                        <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-asterisk me-2"></i>Associações</a>
                        <ul class="dropdown-menu">

                          <li><a class="dropdown-item" href="<?= url('email'); ?>"><i class="bi bi-envelope-paper-fill me-2"></i>E-mails</a></li>
                          <li><hr class="dropdown-divider" /></li>
                          <li><a class="dropdown-item" href="<?= url('address'); ?>"><i class="bi bi-signpost-split-fill me-2"></i>Endereços</a></li>
                          <li><hr class="dropdown-divider" /></li>
                          <li><a class="dropdown-item" href="<?= url('phone_number'); ?>"><i class="bi bi-telephone-fill me-2"></i>Telefones</a></li>

                        </ul>
                      </li>

                      <li><hr class="dropdown-divider" /></li>

                      <li class="dropdown-submenu">
                        <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-stack me-2"></i>Relatórios</a>
                        <ul class="dropdown-menu">

                          <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-text me-2"></i>A2 — Item 1</a></li>
                          <li><hr class="dropdown-divider" /></li>
                          <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-text me-2"></i>A2 — Item 2</a></li>

                        </ul>
                      </li>

                    </ul>
                  </li>
                  <!-- /Person -->
                <?php endif; ?>
              </ul>
            </li>
            <!-- /Four levels menu -->
            <?php if (authCan('financial_operation_category.index') || authCan('financial_operation.index') || authCan('financial_operation_payment.index')): ?>
              <!-- Finance menu -->
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="financeMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-cash-coin me-1"></i> Financeiro
                </a>
                <ul class="dropdown-menu" aria-labelledby="financeMenu">
                  <?php if (authCan('bank.index')): ?>
                    <!-- Bank -->
                    <li class="dropdown-submenu">
                      <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-bank me-2"></i>Bancos</a>
                      <ul class="dropdown-menu">
                        <?php if (authCan('bank.create')): ?>
                          <li><a class="dropdown-item" href="<?= url('bank/create'); ?>"><i class="bi bi-plus-square me-2"></i>Cadastrar</a></li>
                          <li><hr class="dropdown-divider" /></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= url('bank'); ?>"><i class="bi bi-list me-2"></i>Listar</a></li>
                      </ul>
                    </li>
                    <!-- /Bank -->
                    <li><hr classs="dropdown-divider" /></li>
                  <?php endif; ?>
                  <!-- Financial operations -->
                  <?php if (authCan('financial_operation.index')): ?>
                    <li class="dropdown-submenu">
                      <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-receipt me-2"></i>Operações financeiras</a>
                      <ul class="dropdown-menu">
                        <?php if (authCan('financial_operation.create')): ?>
                          <li><a class="dropdown-item" href="<?= url('financial_operation/create'); ?>"><i class="bi bi-plus-square me-2"></i>Cadastrar</a></li>
                          <li><hr class="dropdown-divider" /></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= url('financial_operation_category'); ?>"><i class="bi bi-tags me-2"></i>Categorias</a></li>
                        <li><hr class="dropdown-divider" /></li>
                        <li><a class="dropdown-item" href="<?= url('financial_operation'); ?>"><i class="bi bi-list me-2"></i>Listar</a></li>
                      </ul>
                    </li>
                  <?php endif; ?>

                  <?php if (authCan('financial_operation_category.index') && authCan('financial_operation_payment.index')): ?>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>

                  <?php if (authCan('financial_operation_payment.index')): ?>
                    <li class="dropdown-submenu">
                      <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-credit-card me-2"></i>Pagamentos</a>
                      <ul class="dropdown-menu">
                        <?php if (authCan('financial_operation_payment.create')): ?>
                          <li><a class="dropdown-item" href="<?= url('financial_operation_payment/create'); ?>"><i class="bi bi-plus-square me-2"></i>Cadastrar</a></li>
                          <li><hr class="dropdown-divider" /></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= url('financial_operation_payment'); ?>"><i class="bi bi-list me-2"></i>Listar</a></li>
                      </ul>
                    </li>
                  <?php endif; ?>
                  <!-- /Financial operations -->

                  <?php if (authCan('financial_operation.index')): ?>
                    <li><hr class="dropdown-divider" /></li>
                    <!-- Reports menu -->
                    <li class="dropdown-submenu">
                      <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-stack me-2"></i>Relatórios</a>
                      <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= url('financial_operation/sheet'); ?>"><i class="bi bi-table me-2"></i>Planilha anual</a></li>
                      </ul>
                    </li>
                    <!-- /Reports menu -->
                  <?php endif; ?>
                </ul>
              </li>
              <!-- /Finance menu -->
            <?php endif; ?>
          <?php endif; ?>
        </ul>
        <!-- Sistema -->
        <ul class="navbar-nav mb-2 mb-lg-0">
        <?php if (!empty($_SESSION['manager_main']['user'])): ?>
          <li class="nav-item dropdown me-lg-3">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="systemMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-gear-fill me-2"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="systemMenu">
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-sliders me-2"></i>Configurações</a>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="<?= url('permission'); ?>"><i class="bi bi-gear me-2"></i>Permissões</a></li>
                  <li><hr class="dropdown-divider" /></li>
                  <li><a class="dropdown-item" href="<?= url('log'); ?>"><i class="bi bi-journal-text me-2"></i>Logs do sistema</a></li>
                  <li><hr class="dropdown-divider" /></li>
                  <li><a class="dropdown-item" href="<?= url('tenant'); ?>"><i class="bi bi-database me-2"></i>Tenants</a></li>
                  <li><hr class="dropdown-divider" /></li>
                  <?php if (authCan('system_setting_module.index') || authIsRoot()): ?>
                    <li><a class="dropdown-item" href="<?= url('system_setting_module'); ?>"><i class="bi bi-collection me-2"></i>Módulos de configurações</a></li>
                  <?php endif; ?>
                  <?php if (authCan('system_setting_definition.index') || authIsRoot()): ?>
                    <li><a class="dropdown-item" href="<?= url('system_setting_definition'); ?>"><i class="bi bi-sliders2 me-2"></i>Definições de configurações</a></li>
                  <?php endif; ?>
                  <?php if (authCan('system_setting.index') || authIsRoot()): ?>
                    <li><a class="dropdown-item" href="<?= url('system_setting'); ?>"><i class="bi bi-toggles2 me-2"></i>Valores por tenant</a></li>
                  <?php endif; ?>
                </ul>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#"><i class="bi bi-tools me-2"></i>Ferramentas</a>
                <ul class="dropdown-menu">
                  <?php if (authIsRoot()): ?>
                    <li><a class="dropdown-item" href="<?= url('support_chat'); ?>"><i class="bi bi-chat-dots me-2"></i>Atendimento ao suporte</a></li>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>
                  <?php if (authCan('database_manager.index') || authIsRoot()): ?>
                    <li><a class="dropdown-item" href="<?= url('database_manager'); ?>"><i class="bi bi-database-gear me-2"></i>Database manager</a></li>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>
                  <li><a class="dropdown-item" href="<?= url('tool/zipCodeSearch'); ?>"><i class="bi bi-geo-alt-fill me-2"></i>Pesquisa de CEP</a></li>
                  <li><hr class="dropdown-divider" /></li>
                  <?php $canToolDocuments = authCan('tool.documents'); ?>
                  <?php $canToolEmailValidation = authCan('tool.email_validation'); ?>
                  <?php if ($canToolDocuments): ?>
                    <li><a class="dropdown-item" href="<?= url('tool/documents'); ?>"><i class="bi bi-person-vcard me-2"></i>Gerador/Validador de CPF / CNPJ</a></li>
                  <?php endif; ?>
                  <?php if ($canToolDocuments && $canToolEmailValidation): ?>
                    <li><hr class="dropdown-divider" /></li>
                  <?php endif; ?>
                  <?php if ($canToolEmailValidation): ?>
                    <li><a class="dropdown-item" href="<?= url('tool/email_validation'); ?>"><i class="bi bi-envelope-check-fill me-2"></i>Validador de E-mail</a></li>
                  <?php endif; ?>
                  <li><hr class="dropdown-divider" /></li>
                  <li><a class="dropdown-item" href="<?= url('tool/zipCodeNeighborhoodSearch'); ?>"><i class="bi bi-diagram-3-fill me-2"></i>Bairro e cidade por CEP</a></li>
                  <li><hr class="dropdown-divider" /></li>
                  <li><a class="dropdown-item" href="<?= url('tool/zipCodeCrawler'); ?>"><i class="bi bi-radar me-2"></i>Webcrawler de CEPs</a></li>
                </ul>
              </li>
              <li><hr class="dropdown-divider" /></li>
              <li><a class="dropdown-item" href="<?= url('user'); ?>"><i class="bi bi-person me-2"></i>Usuários</a></li>
            </ul>
          </li>
        <?php endif; ?>
        </ul>
        <!-- Usuário -->
        <ul class="navbar-nav mb-2 mb-lg-0">
          <li class="nav-item dropdown me-lg-3">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle me-2"></i> 
            </a>
            <ul class="dropdown-menu dropdown-menu-end app-user-menu" aria-labelledby="userMenu">
              <!--
              <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Perfil</a></li>
              <li><hr class="dropdown-divider" /></li>
              <li><a class="dropdown-item text-danger" href="<?= ('permission'); ?>"><i class="bi bi-box-arrow-right me-2"></i>Permissões</a></li>
              -->
              <?php if (!empty($_SESSION['manager_main']['user'])): ?>
                <li class="app-presence-menu" data-user-presence data-status-url="<?= url('user_presence/status'); ?>">
                  <div class="app-presence-summary">
                    <div>
                      <span class="app-presence-eyebrow">Presença</span>
                      <strong data-user-presence-label><?= htmlspecialchars((string) ($userPresence['label'] ?? 'Offline'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <span class="badge app-presence-badge <?= htmlspecialchars((string) ($userPresence['class'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8'); ?>" data-user-presence-badge>
                      <i class="bi <?= htmlspecialchars((string) ($userPresence['icon'] ?? 'bi-circle'), ENT_QUOTES, 'UTF-8'); ?>" data-user-presence-icon aria-hidden="true"></i>
                    </span>
                  </div>
                  <div class="app-presence-options" role="group" aria-label="Alterar situação da presença">
                    <?php foreach ($presenceOptions as $status => $option): ?>
                      <?php $isActivePresence = $currentPresenceStatus === $status; ?>
                      <button class="app-presence-option<?= $isActivePresence ? ' is-active' : ''; ?>" type="button" data-user-presence-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" aria-pressed="<?= $isActivePresence ? 'true' : 'false'; ?>">
                        <span class="app-presence-option-icon app-presence-option-icon--<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                          <i class="bi <?= htmlspecialchars($option['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                        </span>
                        <span class="app-presence-option-text">
                          <span><?= htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                          <small><?= htmlspecialchars($option['description'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </span>
                        <i class="bi bi-check2 app-presence-option-check" aria-hidden="true"></i>
                      </button>
                    <?php endforeach; ?>
                  </div>
                </li>
                <li><hr class="dropdown-divider" /></li>
                <li><a class="dropdown-item" href="<?= url('user/edit/' . $_SESSION['manager_main']['user']['id']); ?>"><i class="bi bi-person-lines-fill me-2"></i>Meus dados</a></li>
                <li><hr class="dropdown-divider" /></li>
                <li><a class="dropdown-item" href="<?= url('user/logout'); ?>" onclick="return confirm('Tem certeza que deseja sair?');" title="Encerrar sessão de <?= htmlspecialchars($_SESSION['manager_main']['user']['name'], ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-box-arrow-right me-2"></i>Sair da conta</a></li>
              <?php else: ?>
                <li><a class="dropdown-item" href="<?= url('user/login'); ?>">Entrar</a></li>
              <?php endif; ?>
            </ul>
          </li>
          <?php if ($supportChatNotificationsEnabled): ?>
            <li class="nav-item dropdown me-lg-2 support-chat-notifications" id="supportChatNotifications" data-ws-auth-url="<?= url('support_chat/wsAuth'); ?>" data-attend-url="<?= url('support_chat/attend'); ?>" data-notifications-url="<?= url('support_chat/notifications'); ?>">
              <a class="nav-link dropdown-toggle support-chat-notification-toggle" href="#" id="supportChatNotificationMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notificações de suporte">
                <i class="bi bi-bell-fill"></i>
                <span class="support-chat-notification-badge" id="supportChatNotificationBadge" hidden>0</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end support-chat-notification-menu" aria-labelledby="supportChatNotificationMenu">
                <li class="support-chat-notification-header">
                  <strong>Suporte</strong>
                  <button class="btn btn-sm btn-link" id="supportChatNotificationRefresh" type="button">Atualizar</button>
                </li>
                <li><hr class="dropdown-divider" /></li>
                <li id="supportChatNotificationEmpty" class="dropdown-item-text text-muted">Sem novas mensagens.</li>
                <li id="supportChatNotificationList"></li>
                <li><hr class="dropdown-divider" /></li>
                <li><a class="dropdown-item" href="<?= url('support_chat'); ?>"><i class="bi bi-list me-2"></i>Ver chats</a></li>
              </ul>
            </li>
          <?php endif; ?>
          <?php if (!empty($_SESSION['manager_main']['user'])): ?>
          <!-- Botão Home -->
          <li class="nav-item d-lg-flex align-items-lg-center me-lg-2">
            <a class="nav-link d-flex align-items-center" href="<?= url('/'); ?>" target="_blank" title="Abrir home em nova aba">
              <i class="bi bi-house-add-fill"></i>
            </a>
          </li>
          <!-- Botão de tema -->
          <li class="nav-item d-lg-flex align-items-lg-center">
            <button id="themeToggle" class="nav-link btn-theme d-flex align-items-center" type="button" title="Alternar tema">
              <i class="bi bi-moon-fill"></i>
            </button>
          </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <main class="app-main">
    <div class="container-fluid app-container<?= $isFinancialSheetView ? ' app-container--legacy' : ''; ?>">
      <div class="app-flash-stack">
        <?= renderFlashMessages() ?>
      </div>
      <div class="<?= $isFinancialSheetView ? 'app-view-legacy' : 'app-view'; ?>">
        <?= $content ?>
      </div>
    </div>
  </main>
  
  <script src="/assets/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="<?= url('assets/js/menu.js?v=4'); ?>"></script>
  <script src="<?= url('assets/js/main.js?v=3'); ?>"></script>
  <?php if (!empty($_SESSION['manager_main']['user'])): ?>
    <script src="<?= url('assets/js/user_presences/user_presence.js?v=2'); ?>"></script>
  <?php endif; ?>
  <?php if ($supportChatNotificationsEnabled): ?>
    <script src="<?= url('assets/js/support_chats/notifications.js?v=3'); ?>"></script>
  <?php endif; ?>
</body>
</html>
