<?php
if (!isset($_SESSION["user"])) {
  header("Location: ../login.php");
  exit;
}
if (!isset($user_colors) || !is_array($user_colors)) {
  $user_colors = ['primary' => '#6f42c1', 'secondary' => '#e83e8c', 'accent' => '#fd7e14'];
}
if (empty($page_title)) {
  $page_title = "SICEF";
}
if (empty($active_menu)) {
  $active_menu = "";
}
$user = $_SESSION["user"];
$tipo = $user["tipo"] ?? "";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    :root {
      --primary-color: <?= $user_colors['primary'] ?>;
      --secondary-color: <?= $user_colors['secondary'] ?>;
      --accent-color: <?= $user_colors['accent'] ?>;
      --dark-color: #2c3e50;
      --light-color: #f8f9fa;
      --border-color: #e0e0e0;
      --error-color: #e74c3c;
      --success-color: #2ecc71;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Roboto', sans-serif; background-color: #f5f7fa; color: #333; line-height: 1.6; overflow-x: hidden; }
    .user-container { display: flex; min-height: 100vh; }
    .user-sidebar { width: 250px; background-color: var(--dark-color); color: white; padding: 1.5rem 0; position: fixed; height: 100vh; transition: all 0.3s; z-index: 100; overflow-y: auto; }
    .sidebar-header { padding: 0 1.5rem 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    .sidebar-menu a { display: flex; align-items: center; padding: 0.75rem 1.5rem; color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: all 0.3s; gap: 0.75rem; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgba(255, 255, 255, 0.1); color: white; }
    .user-content { flex: 1; margin-left: 250px; transition: all 0.3s; width: calc(100% - 250px); }
    .user-header { background: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); position: sticky; top: 0; z-index: 90; }
    .user-icon { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.2rem; }
    .content-area { padding: 2rem; max-width: 100%; }
    .export-section, .filters-section, .emendas-section { background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
    .btn { display: inline-flex; align-items: center; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; text-decoration: none; transition: all 0.3s; border: none; gap: 0.5rem; }
    .btn-primary { background-color: var(--primary-color); color: white; }
    .btn-success { background-color: var(--success-color); color: white; }
    .btn-danger { background-color: var(--error-color); color: white; }
    .btn-secondary { background: #95a5a6; color: #fff; }
    .btn-small { padding: 0.5rem; font-size: 0.8rem; }
    .table-container { overflow-x: auto; margin-top: 1rem; }
    .emendas-table { width: 100%; border-collapse: collapse; min-width: 1300px; }
    .emendas-table th { background: var(--primary-color); color: white; padding: 1rem 0.5rem; text-align: left; font-weight: 500; font-size: 0.9rem; position: sticky; top: 0; }
    .emendas-table td { padding: 1rem 0.5rem; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; vertical-align: top; }
    .emendas-table tbody tr:nth-child(odd) { background: #fafbfc; }
    .emendas-table tbody tr:hover { background: #f1f6ff; }
    .pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 2rem; flex-wrap: wrap; }
    .pagination a, .pagination .current { padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 4px; text-decoration: none; color: var(--dark-color); transition: all 0.3s; }
    .pagination .current { background: var(--primary-color); color: white; border-color: var(--primary-color); }
    .pagination a:hover { background: var(--primary-color); color: white; border-color: var(--primary-color); }
    .progress-wrap { width: 140px; height: 8px; background: #eef2f7; border-radius: 6px; overflow: hidden; margin-bottom: 0.25rem; }
    .progress-bar { height: 100%; background: var(--secondary-color); transition: width 0.4s ease; }
    .message { display:flex; align-items:center; gap:0.5rem; padding:0.75rem 1rem; border-radius:6px; margin-bottom:1rem; }
    .message-success { background:#eafaf1; color:#2e7d32; }
    .message-error { background:#fdecea; color:#c62828; }
    .form-control { padding: 0.5rem 0.75rem; border:1px solid var(--border-color); border-radius:6px; width:100%; }
    .filters-grid { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:1rem; }
    .filter-group label { display:block; margin-bottom:0.35rem; font-weight:500; }
    .filter-actions { display:flex; gap:0.75rem; margin-top:1rem; }
    .menu-toggle { background:transparent; border:none; cursor:pointer; }
    .logout-btn { margin-left: 0.75rem; color: var(--dark-color); text-decoration: none; display:inline-flex; align-items:center; gap:0.25rem; }
    .description { max-width: 200px; word-wrap: break-word; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
    .stat-card h3 { color: var(--primary-color); margin-bottom: 0.5rem; }
    .stat-card .stat-value { font-size: 2rem; font-weight: 700; color: var(--dark-color); }
    .export-buttons { display: flex; gap: 1rem; flex-wrap: wrap; }
    @media (max-width: 992px) {
      .emendas-table { min-width: 1000px; }
      .content-area { padding: 1rem; }
      .filters-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
    }
    @media (max-width: 768px) {
      .user-sidebar { position: fixed; left: -250px; }
      .user-sidebar.active { left: 0; }
      .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display:none; z-index: 99; }
      .sidebar-overlay.active { display:block; }
      .user-content { margin-left: 0; width: 100%; }
      .filters-grid { grid-template-columns: 1fr; }
      .export-buttons { flex-direction: column; }
      .stats-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="user-container">
    <nav class="user-sidebar" id="sidebar">
      <div class="sidebar-header">
        <h2>
          <i class="material-icons">dashboard</i>
          Dashboard
        </h2>
      </div>
      <div class="sidebar-menu">
        <a href="user_dashboard.php" class="<?= $active_menu === 'inicio' ? 'active' : '' ?>">
          <i class="material-icons">home</i> Início
        </a>
        <?php if ($tipo !== 'Administrador'): ?>
          <a href="minhas_emendas.php" class="<?= $active_menu === 'minhas_emendas' ? 'active' : '' ?>">
            <i class="material-icons">bookmark</i> Minhas Emendas
          </a>
          <a href="sugestoes.php" class="<?= $active_menu === 'sugestoes' ? 'active' : '' ?>">
            <i class="material-icons">lightbulb</i> Sugestões
          </a>
          <a href="selecionar_emendas.php" class="<?= $active_menu === 'selecionar_emendas' ? 'active' : '' ?>">
            <i class="material-icons">playlist_add_check</i> Selecionar Emendas
          </a>
          <a href="visualizar_emenda.php" class="<?= $active_menu === 'visualizar_emenda' ? 'active' : '' ?>">
            <i class="material-icons">visibility</i> Visualizar Emenda
          </a>
        <?php else: ?>
          <a href="admin_dashboard.php" class="<?= $active_menu === 'admin' ? 'active' : '' ?>">
            <i class="material-icons">admin_panel_settings</i> Admin
          </a>
          <a href="gerenciar_usuarios.php" class="<?= $active_menu === 'gerenciar_usuarios' ? 'active' : '' ?>">
            <i class="material-icons">group</i> Usuários
          </a>
          <a href="solicitacoes_acesso.php" class="<?= $active_menu === 'solicitacoes_acesso' ? 'active' : '' ?>">
            <i class="material-icons">how_to_reg</i> Solicitações de Acesso
          </a>
        <?php endif; ?>
        <a href="../logout.php">
          <i class="material-icons">logout</i> Sair
        </a>
      </div>
    </nav>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <main class="user-content">
      <header class="user-header">
        <div style="display: flex; align-items: center; gap: 1rem;">
          <button class="menu-toggle" onclick="toggleSidebar()">
            <i class="material-icons">menu</i>
          </button>
          <h1><?= htmlspecialchars($page_title) ?></h1>
        </div>
        <div class="user-area">
          <div class="user-icon">
            <?= strtoupper(substr($user["nome"] ?? 'U', 0, 1)) ?>
          </div>
          <span class="user-name"><?= htmlspecialchars($user["nome"] ?? 'Usuário') ?></span>
          <a href="../logout.php" class="logout-btn">
            <i class="material-icons">logout</i> Sair
          </a>
        </div>
      </header>

      <div class="content-area">