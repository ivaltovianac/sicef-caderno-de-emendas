<?php
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// Obter lista de usuários
$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY nome ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar ações (excluir, editar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['excluir_usuario'])) {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: gerenciar_usuarios.php");
        exit;
    }
    
    if (isset($_POST['editar_usuario'])) {
        // Implementar lógica de edição
    }
    
    if (isset($_POST['adicionar_usuario'])) {
        // Implementar lógica de adição
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Painel Admin CICEF</title>
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
        
        /* Formulário de usuário */
        .user-form {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
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
            margin-top: 1rem;
        }
        
        /* Tabela */
        .emendas-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .emendas-table thead th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            position: sticky;
            top: 68px;
        }
        
        .emendas-table tbody tr {
            transition: background-color 0.2s;
        }
        
        .emendas-table tbody tr:hover {
            background-color: var(--hover-color);
        }
        
        .emendas-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }
        
        .emendas-table tr:last-child td {
            border-bottom: none;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .role-admin {
            background-color: #d4edda;
            color: #155724;
        }
        
        .role-user {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .actions-cell {
            white-space: nowrap;
        }
        
        .action-link {
            color: var(--secondary-color);
            text-decoration: none;
            margin-right: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .action-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
        
        .action-link.danger {
            color: #dc3545;
        }
        
        .action-link.danger:hover {
            color: #c82333;
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
        
        .btn-primary:hover {
            background-color: #3da89e;
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
            
            .emendas-table {
                display: block;
                overflow-x: auto;
            }
            
            .emendas-table thead th {
                top: 60px;
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
            <a href="gerenciar_usuarios.php" class="menu-item active">
                <span class="material-icons">people</span>
                Gerenciar Usuários
            </a>
            <a href="relatorios.php" class="menu-item">
                <span class="material-icons">assessment</span>
                Relatórios
            </a>
            <a href="configuracoes.php" class="menu-item">
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
            <h1>Gerenciar Usuários</h1>
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
                <h2>Gerenciamento de Usuários</h2>
                <p>Adicione, edite ou remova usuários do sistema</p>
            </section>
            
            <!-- Formulário para adicionar/editar usuário -->
            <div class="user-form">
                <h3><span class="material-icons">person_add</span> Adicionar Novo Usuário</h3>
                <form method="POST" action="gerenciar_usuarios.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome Completo</label>
                            <input type="text" id="nome" name="nome" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email">E-mail</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="senha">Senha</label>
                            <input type="password" id="senha" name="senha" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="confirmar_senha">Confirmar Senha</label>
                            <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="is_admin">Tipo de Usuário</label>
                            <select id="is_admin" name="is_admin" class="form-control">
                                <option value="0">Usuário Normal</option>
                                <option value="1">Administrador</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="adicionar_usuario" class="btn btn-primary">
                            <span class="material-icons">save</span>
                            Salvar Usuário
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Tabela de Usuários -->
            <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: var(--shadow);">
                <h3 style="color: var(--primary-color); margin-bottom: 1rem; font-size: 1.25rem; font-weight: 600; border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem;">
                    <span class="material-icons">people</span>
                    Usuários Cadastrados
                </h3>
                
                <table class="emendas-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Tipo</th>
                            <th>Data Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?= htmlspecialchars($usuario['nome']) ?></td>
                            <td><?= htmlspecialchars($usuario['email']) ?></td>
                            <td>
                                <span class="role-badge <?= $usuario['is_admin'] ? 'role-admin' : 'role-user' ?>">
                                    <?= $usuario['is_admin'] ? 'Administrador' : 'Usuário' ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($usuario['criado_em'])) ?></td>
                            <td class="actions-cell">
                                <a href="#" class="action-link" onclick="editarUsuario(<?= $usuario['id'] ?>)">
                                    <span class="material-icons" style="font-size: 1.1rem;">edit</span>
                                    Editar
                                </a>
                                <form method="POST" action="gerenciar_usuarios.php" style="display: inline;">
                                    <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                    <button type="submit" name="excluir_usuario" class="action-link danger" onclick="return confirm('Tem certeza que deseja excluir este usuário?')">
                                        <span class="material-icons" style="font-size: 1.1rem;">delete</span>
                                        Excluir
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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

        // Função para preencher formulário de edição
        function editarUsuario(id) {
            // Implementar lógica para buscar dados do usuário e preencher formulário
            alert('Funcionalidade de edição será implementada');
        }
    </script>
</body>
</html>