<?php
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// Processar atualização de configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Implementar lógica para salvar configurações
    $mensagem = "Configurações salvas com sucesso!";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Painel Admin CICEF</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        /* Estilos idênticos ao admin_dashboard.php */
        :root {
            --primary-color: #007b5e;
            --secondary-color: #4db6ac;
            --accent-color: #ffc107;
            --dark-color: #003366;
            --light-color: #f8f9fa;
            --sidebar-color: #2c3e50;
            --text-color: #333;
            --border-color: #e0e0e0;
            --hover-color: #f1f1f1;
            --shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--text-color);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background-color: var(--sidebar-color);
            color: white;
            padding: 1.5rem 0;
            position: fixed;
            height: 100vh;
            transition: all 0.3s;
            z-index: 100;
        }
        
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .menu-item {
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: rgba(255,255,255,0.8);
        }
        
        .menu-item:hover, .menu-item.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        
        .menu-item .material-icons {
            font-size: 1.25rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: all 0.3s;
        }
        
        /* Header */
        .header {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 90;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .user-menu a {
            color: var(--dark-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .user-menu a:hover {
            color: var(--primary-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Content */
        .content {
            padding: 2rem;
        }
        
        .welcome-section {
            margin-bottom: 2rem;
        }
        
        .welcome-section h2 {
            font-size: 1.8rem;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .welcome-section p {
            color: #666;
        }
        
        /* Estilos específicos para configurações */
        .settings-form {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .settings-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .settings-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .settings-section h3 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-row {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: white;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(77, 182, 172, 0.2);
            outline: none;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Switch toggle */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary-color);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .switch-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        /* Mensagem de sucesso */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Botões */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            border: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3da89e;
            transform: translateY(-2px);
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        
        /* Responsividade */
        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }
            
            .content {
                padding: 1.5rem;
            }
            
            .form-row {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-group {
                min-width: 100%;
            }
        }
        
        /* Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--dark-color);
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 1rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>
                <span class="material-icons">admin_panel_settings</span>
                Painel Admin
            </h2>
        </div>
        <div class="sidebar-menu">
            <a href="admin_dashboard.php" class="menu-item">
                <span class="material-icons">list_alt</span>
                Emendas
            </a>
            <a href="gerenciar_usuarios.php" class="menu-item">
                <span class="material-icons">people</span>
                Gerenciar Usuários
            </a>
            <a href="relatorios.php" class="menu-item">
                <span class="material-icons">assessment</span>
                Relatórios
            </a>
            <a href="configuracoes.php" class="menu-item active">
                <span class="material-icons">settings</span>
                Configurações
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <button class="menu-toggle" id="menuToggle">
                <span class="material-icons">menu</span>
            </button>
            <h1>Configurações do Sistema</h1>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['user']['nome'], 0, 1)) ?>
                    </div>
                    <span><?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
                </div>
                <a href="../logout.php">
                    <span class="material-icons">logout</span>
                    Sair
                </a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <section class="welcome-section">
                <h2>Configurações do Sistema</h2>
                <p>Configure as preferências e opções do sistema</p>
            </section>
            
            <?php if (isset($mensagem)): ?>
            <div class="success-message">
                <span class="material-icons">check_circle</span>
                <p><?= $mensagem ?></p>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="configuracoes.php" class="settings-form">
                <div class="settings-section">
                    <h3><span class="material-icons">lock</span> Configurações de Segurança</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="login_attempts">Tentativas de Login Permitidas</label>
                            <input type="number" id="login_attempts" name="login_attempts" class="form-control" value="3" min="1" max="10">
                        </div>
                        <div class="form-group">
                            <label for="password_expiry">Expiração de Senha (dias)</label>
                            <input type="number" id="password_expiry" name="password_expiry" class="form-control" value="90" min="30" max="365">
                        </div>
                    </div>
                    
                    <div class="switch-container">
                        <label class="switch">
                            <input type="checkbox" name="force_2fa" checked>
                            <span class="slider"></span>
                        </label>
                        <span>Forçar Autenticação em Dois Fatores para Administradores</span>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3><span class="material-icons">notifications</span> Configurações de Notificações</h3>
                    
                    <div class="switch-container">
                        <label class="switch">
                            <input type="checkbox" name="notify_new_emendas" checked>
                            <span class="slider"></span>
                        </label>
                        <span>Notificar sobre novas emendas cadastradas</span>
                    </div>
                    
                    <div class="switch-container">
                        <label class="switch">
                            <input type="checkbox" name="notify_user_changes" checked>
                            <span class="slider"></span>
                        </label>
                        <span>Notificar sobre alterações de usuários</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="notification_email">E-mail para Notificações</label>
                            <input type="email" id="notification_email" name="notification_email" class="form-control" value="admin@cicef.org">
                        </div>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3><span class="material-icons">system_update</span> Manutenção do Sistema</h3>
                    
                    <div class="switch-container">
                        <label class="switch">
                            <input type="checkbox" name="maintenance_mode">
                            <span class="slider"></span>
                        </label>
                        <span>Modo de Manutenção (acesso apenas para administradores)</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="backup_frequency">Frequência de Backup Automático</label>
                            <select id="backup_frequency" name="backup_frequency" class="form-control">
                                <option value="daily">Diariamente</option>
                                <option value="weekly" selected>Semanalmente</option>
                                <option value="monthly">Mensalmente</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-icons">save</span>
                        Salvar Configurações
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <span class="material-icons">undo</span>
                        Redefinir
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Menu Toggle para mobile
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
        
        // Fechar menu ao clicar fora (para mobile)
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && !sidebar.contains(e.target) && e.target !== menuToggle) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>