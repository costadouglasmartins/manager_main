<!DOCTYPE html>
<html lang="pt-BR" id="htmlRoot" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <script>
    (function() {
      const savedTheme = localStorage.getItem("theme") || "light";
      document.documentElement.setAttribute("data-bs-theme", savedTheme);
    })();
  </script>
  <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css'); ?>">
  <link rel="stylesheet" href="<?= url('assets/images/bootstrap-icons.min.css'); ?>">
  <link rel="stylesheet" href="<?= url('assets/css/main.css?v=2'); ?>">
  <link rel="stylesheet" href="<?= url('assets/css/menu.css?v=1'); ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= url('assets/images/apple-touch-icon.png'); ?>">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= url('assets/images/favicon-32x32.png'); ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= url('assets/images/favicon-16x16.png'); ?>">
  <link rel="manifest" href="<?= url('/site.webmanifest'); ?>">
  <title>Manager - <?=$title?></title>
</head>
<body class="font-monospace">
  
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="<?= url('/'); ?>">
        <!--<i class="bi bi-boxes me-2"></i>-->
        <img src="<?= url('/assets/images/favicon-32x32.png'); ?>">
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
          <!-- Menu Cadastros (2 níveis) -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="menuCadastros" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-folder-fill me-1"></i> Cadastros
            </a>
            <ul class="dropdown-menu" aria-labelledby="menuCadastros">
              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">Empresas</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('company.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('company'); ?>"><i class="bi bi-list-ul me-2"></i> Listar</a></li>
                  <?php endif; ?>
                  <?php if (authCan('company.create')): ?>
                    <li><a class="dropdown-item" href="<?= url('company/create'); ?>"><i class="bi bi-plus-circle me-2"></i> Cadastrar</a></li>
                  <?php endif; ?>
                </ul>
              </li>

              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">Pessoas</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('person.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('person'); ?>"><i class="bi bi-list-ul me-2"></i> Listar</a></li>
                  <?php endif; ?>
                  <?php if (authCan('person.create')): ?>
                    <li><a class="dropdown-item" href="<?= url('person/create'); ?>"><i class="bi bi-plus-circle me-2"></i> Cadastrar</a></li>
                  <?php endif; ?>
                </ul>
              </li>

              <li class="dropdown-submenu">
                <a class="dropdown-item submenu-toggle" href="#">Usuários</a>
                <ul class="dropdown-menu">
                  <?php if (authCan('user.index')): ?>
                    <li><a class="dropdown-item" href="<?= url('user'); ?>"><i class="bi bi-person-lines-fill me-2"></i> Listar</a></li>
                  <?php endif; ?>
                </ul>
              </li>
            </ul>
          </li>
          <!-- Menu Contato
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="menuContato" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-envelope-fill me-1"></i> Contato
            </a>
            <ul class="dropdown-menu" aria-labelledby="menuContato">
              <li><a class="dropdown-item" href="#"><i class="bi bi-telephone me-2"></i>Telefone</a></li>
              <li><a class="dropdown-item" href="#"><i class="bi bi-chat-dots me-2"></i>Chat</a></li>
            </ul>
          </li>
          -->
          <?php endif; ?>
        </ul>
        <!-- Sistema -->
        <ul class="navbar-nav mb-2 mb-lg-0">
          <li class="nav-item dropdown me-lg-3">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-gear-fill"></i>&nbsp;Sistema
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
              <?php if (authCan('home.index')): ?>
                <li><a class="dropdown-item" href="<?= url('permission'); ?>"><i class="bi bi-gear me-2"></i>Permissões</a></li>
              <?php endif; ?>
            </ul>
          </li>
        <!-- Usuário -->
        <ul class="navbar-nav mb-2 mb-lg-0">
          <li class="nav-item dropdown me-lg-3">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle me-2"></i> Usuário(a)
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
              <!--
              <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Perfil</a></li>
              <li><hr class="dropdown-divider" /></li>
              <li><a class="dropdown-item text-danger" href="<?= ('permission'); ?>"><i class="bi bi-box-arrow-right me-2"></i>Permissões</a></li>
              -->
              <?php if (!empty($_SESSION['manager_main']['user'])): ?>
                <li><a class="dropdown-item" href="<?= url('user/edit/' . $_SESSION['manager_main']['user']['id']); ?>"><i class="bi bi-person-lines-fill"></i> Meus dados</a></li>
                <li><hr class="dropdown-divider" /></li>
                <li><a class="dropdown-item" href="<?= url('user/logout'); ?>" onclick="return confirm('Tem certeza que deseja sair?');"><i class="bi bi-box-arrow-right me-2"></i>Olá, <?= htmlspecialchars($_SESSION['manager_main']['user']['name']) ?>, encerrar sessão?</a></li>
              <?php else: ?>
                <li><a class="dropdown-item" href="<?= url('user/login'); ?>">Entrar</a></li>
              <?php endif; ?>
            </ul>
          </li>

          <!-- Botão de tema -->
          <li class="nav-item">
            <button id="themeToggle" class="btn-theme" type="button" title="Alternar tema">
              <i class="bi bi-moon-fill"></i>
            </button>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container-fluid">
    <?= $content ?>
  </div>
  
  <script src="<?= url('assets/js/bootstrap.bundle.min.js'); ?>"></script>
  <script src="<?= url('assets/js/menu.js?v=1'); ?>"></script>
  <script src="<?= url('assets/js/main.js?v=1'); ?>"></script>
</body>
</html>