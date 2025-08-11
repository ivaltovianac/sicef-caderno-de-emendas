<?php
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// Processar atualizações de configuração
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_system':
                // Lógica para atualizar configurações do sistema
                $_SESSION['message'] = 'Configurações do sistema atualizadas com sucesso!';
                break;
            case 'backup_database':
                // Lógica para backup do banco de dados
                $_SESSION['message'] = 'Backup do banco de dados iniciado!';
                break;
            case 'clear_cache':
                // Lógica para limpar cache
                $_SESSION['message'] = 'Cache do sistema limpo com sucesso!';
                break;
        }
        header('Location: configuracoes.php');
        exit;
    }
}

// Determinar cores do usuário baseado no tipo
$user_colors = [
    'primary' => '#6f42c1',
    'secondary' => '#e83e8c',
    'accent' => '#fd7e14'
];

if (isset($_SESSION["user"]["tipo"])) {
    switch ($_SESSION["user"]["tipo"]) {
        case 'Deputado':
            $user_colors = [
                'primary' => '#018bd2',
                'secondary' => '#51ae32',
                'accent' => '#fdfefe'
            ];
            break;
        case 'Senador':
            $user_colors = [
                'primary' => '#51b949',
                'secondary' => '#0094db',
                'accent' => '#fefefe'
            ];
            break;
        case 'Administrador':
            $user_colors = [
                'primary' => '#6f42c1',
                'secondary' => '#e83e8c',
                'accent' => '#fd7e14'
            ];
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - SICEF</title>
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .admin-sidebar {
            width: 250px;
            background-color: var(--dark-color);
            color: white;
            padding: 1.5rem 0;
            position: fixed;
            height: 100vh;
            transition: all 0.3s;
            z-index: 100;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h2 {
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            gap: 0.75rem;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu i {
            font-size: 1.25rem;
        }
        
        /* Main Content */
        .admin-content {
            flex: 1;
            margin-left: 250px;
            transition: all 0.3s;
            width: calc(100% - 250px);
        }
        
        /* Header */
        .admin-header {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 90;
        }
        
        .admin-header h1 {
            font-size: 1.5rem;
            color: var(--dark-color);
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--dark-color);
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .user-area {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .logout-btn {
            color: var(--dark-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background-color: var(--light-color);
        }
        
        /* Content Area */
        .content-area {
            padding: 2rem;
            max-width: 100%;
        }
        
        /* Config Sections */
        .config-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .config-section h2 {
            font-size: 1.25rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .config-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.3s;
        }
        
        .config-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .config-card h3 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .config-card p {
            color: #666;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background-color: var(--error-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background-color: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
            transform: translateY(-2px);
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-sidebar.active {
                transform: translateX(0);
            }
            
            .admin-content {
                margin-left: 0;
                width: 100%;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .admin-header {
                padding: 1rem;
            }
            
            .content-area {
                padding: 1rem;
            }
            
            .config-grid {
                grid-template-columns: 1fr;
            }
            
            .user-area {
                gap: 0.5rem;
            }
            
            .user-name {
                display: none;
            }
        }
        
        /* Overlay para mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 99;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>
                    <i class="material-icons">admin_panel_settings</i>
                    Painel Admin
                </h2>
            </div>
            <div class="sidebar-menu">
                <a href="admin_dashboard.php">
                    <i class="material-icons">dashboard</i>
                    Dashboard
                </a>
                <a href="gerenciar_usuarios.php">
                    <i class="material-icons">people</i>
                    Usuários
                </a>
                <a href="relatorios.php">
                    <i class="material-icons">assessment</i>
                    Relatórios
                </a>
                <a href="configuracoes.php" class="active">
                    <i class="material-icons">settings</i>
                    Configurações
                </a>
                <a href="#" onclick="showOtherResources()">
                    <i class="material-icons">more_horiz</i>
                    Outros Recursos
                </a>
            </div>
        </nav>

        <!-- Overlay para mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <!-- Main Content -->
        <main class="admin-content">
            <!-- Header -->
            <header class="admin-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="material-icons">menu</i>
                    </button>
                    <h1>Configurações do Sistema</h1>
                </div>
                <div class="user-area">
                    <div class="user-icon">
                        <?= strtoupper(substr($_SESSION["user"]["nome"], 0, 1)) ?>
                    </div>
                    <span class="user-name"><?= htmlspecialchars($_SESSION["user"]["nome"]) ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="material-icons">logout</i>
                        Sair
                    </a>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area">
                <?php if (isset($_SESSION["message"])): ?>
                    <div class="message <?= strpos($_SESSION["message"], 'Erro') !== false ? 'message-error' : 'message-success' ?>">
                        <i class="material-icons"><?= strpos($_SESSION["message"], 'Erro') !== false ? 'error' : 'check_circle' ?></i>
                        <?= htmlspecialchars($_SESSION["message"]) ?>
                    </div>
                    <?php unset($_SESSION["message"]); ?>
                <?php endif; ?>

                <!-- Configurações do Sistema -->
                <section class="config-section">
                    <h2>
                        <i class="material-icons">tune</i>
                        Configurações Gerais
                    </h2>
                    <div class="config-grid">
                        <div class="config-card">
                            <h3>
                                <i class="material-icons">system_update</i>
                                Sistema
                            </h3>
                            <p>Configurações gerais do sistema e parâmetros de funcionamento.</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_system">
                                <div class="form-group">
                                    <label for="system_name">Nome do Sistema:</label>
                                    <input type="text" id="system_name" name="system_name" class="form-control" value="SICEF - Sistema de Emendas">
                                </div>
                                <div class="form-group">
                                    <label for="max_upload_size">Tamanho Máximo de Upload (MB):</label>
                                    <input type="number" id="max_upload_size" name="max_upload_size" class="form-control" value="10">
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons">save</i>
                                    Salvar
                                </button>
                            </form>
                        </div>

                        <div class="config-card">
                            <h3>
                                <i class="material-icons">backup</i>
                                Backup
                            </h3>
                            <p>Gerenciar backups do banco de dados e arquivos do sistema.</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="backup_database">
                                <button type="submit" class="btn btn-warning">
                                    <i class="material-icons">backup</i>
                                    Fazer Backup
                                </button>
                            </form>
                        </div>

                        <div class="config-card">
                            <h3>
                                <i class="material-icons">cached</i>
                                Cache
                            </h3>
                            <p>Limpar cache do sistema para melhorar a performance.</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="clear_cache">
                                <button type="submit" class="btn btn-secondary">
                                    <i class="material-icons">clear_all</i>
                                    Limpar Cache
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Configurações de Email -->
                <section class="config-section">
                    <h2>
                        <i class="material-icons">email</i>
                        Configurações de Email
                    </h2>
                    <div class="config-grid">
                        <div class="config-card">
                            <h3>
                                <i class="material-icons">mail_outline</i>
                                SMTP
                            </h3>
                            <p>Configurar servidor SMTP para envio de emails.</p>
                            <form>
                                <div class="form-group">
                                    <label for="smtp_host">Servidor SMTP:</label>
                                    <input type="text" id="smtp_host" name="smtp_host" class="form-control" placeholder="smtp.gmail.com">
                                </div>
                                <div class="form-group">
                                    <label for="smtp_port">Porta:</label>
                                    <input type="number" id="smtp_port" name="smtp_port" class="form-control" placeholder="587">
                                </div>
                                <div class="form-group">
                                    <label for="smtp_user">Usuário:</label>
                                    <input type="email" id="smtp_user" name="smtp_user" class="form-control" placeholder="seu@email.com">
                                </div>
                                <div class="form-group">
                                    <label for="smtp_pass">Senha:</label>
                                    <input type="password" id="smtp_pass" name="smtp_pass" class="form-control">
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons">save</i>
                                    Salvar SMTP
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Configurações de Segurança -->
                <section class="config-section">
                    <h2>
                        <i class="material-icons">security</i>
                        Configurações de Segurança
                    </h2>
                    <div class="config-grid">
                        <div class="config-card">
                            <h3>
                                <i class="material-icons">lock</i>
                                Senhas
                            </h3>
                            <p>Configurar políticas de senha e segurança.</p>
                            <form>
                                <div class="form-group">
                                    <label for="min_password_length">Tamanho Mínimo da Senha:</label>
                                    <input type="number" id="min_password_length" name="min_password_length" class="form-control" value="8">
                                </div>
                                <div class="form-group">
                                    <label for="session_timeout">Timeout da Sessão (minutos):</label>
                                    <input type="number" id="session_timeout" name="session_timeout" class="form-control" value="30">
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons">save</i>
                                    Salvar Segurança
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function showOtherResources() {
            alert('Funcionalidade "Outros Recursos" em desenvolvimento.');
        }

        // Fechar sidebar ao clicar fora (mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target) && 
                sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        });

        // Ajustar layout em redimensionamento
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        });
    </script>
</body>
</html>

