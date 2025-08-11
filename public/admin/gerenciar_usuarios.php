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

// Variável para usuário em edição
$usuario_edicao = null;

// Processar ações (excluir, editar, adicionar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['excluir_usuario'])) {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            if ($stmt->execute([$id])) {
                $_SESSION['sucesso'] = "Usuário excluído com sucesso!";
            } else {
                $_SESSION['erro'] = "Erro ao excluir usuário!";
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao excluir usuário: " . $e->getMessage();
        }
        header("Location: gerenciar_usuarios.php");
        exit;
    }
    
    if (isset($_POST['editar_usuario'])) {
        $id = $_POST['id'];
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        $tipo = $_POST['tipo'];
        
        // Converter is_admin para boolean corretamente
        $is_admin = ($tipo === 'admin') ? true : (isset($_POST['is_admin']) ? filter_var($_POST['is_admin'], FILTER_VALIDATE_BOOLEAN) : false);
        
        try {
            // Verificar se email já existe (exceto para o próprio usuário)
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                $_SESSION['erro'] = "Este e-mail já está cadastrado para outro usuário!";
                header("Location: gerenciar_usuarios.php");
                exit;
            }
            
            // Atualizar dados básicos
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, is_admin = ?, tipo = ? WHERE id = ?");
            if ($stmt->execute([$nome, $email, $is_admin ? 'true' : 'false', $tipo, $id])) {
                // Se senha foi fornecida, atualizar também
                if (!empty($senha)) {
                    if ($senha !== $confirmar_senha) {
                        $_SESSION['erro'] = "As senhas não coincidem!";
                        header("Location: gerenciar_usuarios.php");
                        exit;
                    }
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                    $stmt->execute([$senha_hash, $id]);
                }
                
                $_SESSION['sucesso'] = "Usuário atualizado com sucesso!";
            } else {
                $_SESSION['erro'] = "Erro ao atualizar usuário!";
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao atualizar usuário: " . $e->getMessage();
        }
        header("Location: gerenciar_usuarios.php");
        exit;
    }
    
    if (isset($_POST['adicionar_usuario'])) {
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        $tipo = $_POST['tipo'];
        
        // Converter is_admin para boolean corretamente
        $is_admin = ($tipo === 'admin') ? true : (isset($_POST['is_admin']) ? filter_var($_POST['is_admin'], FILTER_VALIDATE_BOOLEAN) : false);
        
        try {
            // Validações
            if (empty($nome) || empty($email) || empty($senha) || empty($tipo)) {
                $_SESSION['erro'] = "Preencha todos os campos obrigatórios!";
                header("Location: gerenciar_usuarios.php");
                exit;
            }
            
            if ($senha !== $confirmar_senha) {
                $_SESSION['erro'] = "As senhas não coincidem!";
                header("Location: gerenciar_usuarios.php");
                exit;
            }
            
            if (strlen($senha) < 8) {
                $_SESSION['erro'] = "A senha deve ter pelo menos 8 caracteres!";
                header("Location: gerenciar_usuarios.php");
                exit;
            }
            
            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $_SESSION['erro'] = "Este e-mail já está cadastrado!";
                header("Location: gerenciar_usuarios.php");
                exit;
            }
            
            // Criar hash da senha
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            
            // Inserir no banco de dados (convertendo explicitamente o boolean para string)
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, is_admin, tipo, is_user) VALUES (?, ?, ?, ?, ?, true)");
            if ($stmt->execute([$nome, $email, $senha_hash, $is_admin ? 'true' : 'false', $tipo])) {
                $_SESSION['sucesso'] = "Usuário cadastrado com sucesso!";
            } else {
                $_SESSION['erro'] = "Erro ao cadastrar usuário!";
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao cadastrar usuário: " . $e->getMessage();
        }
        header("Location: gerenciar_usuarios.php");
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
    <title>Gerenciar Usuários - CICEF</title>
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
        }
        
        /* Sidebar Overlay for Mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.5);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            z-index: 99;
        }
        
        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Content Area */
        .content-area {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
        
        /* Sections */
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
        
        .config-card {
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .config-card h3 {
            font-size: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--secondary-color);
        }
        
        .config-card p {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
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
            background-color: darken(var(--primary-color), 10%);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: darken(var(--secondary-color), 10%);
        }
        
        .btn-danger {
            background-color: var(--error-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: darken(var(--error-color), 10%);
        }
        
        .action-link {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .action-link:hover {
            color: darken(var(--primary-color), 10%);
        }
        
        .action-link.danger {
            color: var(--error-color);
        }
        
        .action-link.danger:hover {
            color: darken(var(--error-color), 10%);
        }
        
        /* Table */
        .emendas-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .emendas-table th {
            background: var(--primary-color);
            color: white;
            padding: 1rem 0.5rem;
            text-align: left;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .emendas-table td {
            padding: 1rem 0.5rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }
        
        .actions-cell {
            display: flex;
            gap: 1rem;
        }
        
        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .message-success {
            background: var(--success-color);
            color: white;
        }
        
        .message-error {
            background: var(--error-color);
            color: white;
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
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
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100vh;
                background: rgba(0,0,0,0.5);
                display: none;
                z-index: 999;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
            
            .content-area {
                padding: 1rem;
            }
            
            .emendas-table {
                font-size: 0.8rem;
            }
            
            .emendas-table th, .emendas-table td {
                padding: 0.5rem;
            }
            
            .actions-cell {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside id="sidebar" class="admin-sidebar">
            <div class="sidebar-header">
                <h2>
                    <i class="material-icons">admin_panel_settings</i>
                    Painel Admin
                </h2>
            </div>
            <nav class="sidebar-menu">
                <a href="admin_dashboard.php">
                    <i class="material-icons">dashboard</i>
                    Dashboard
                </a>
                <a href="gerenciar_usuarios.php" class="active">
                    <i class="material-icons">people</i>
                    Usuários
                </a>
                <a href="relatorios.php">
                    <i class="material-icons">assessment</i>
                    Relatórios
                </a>
                <a href="configuracoes.php">
                    <i class="material-icons">settings</i>
                    Configurações
                </a>
                <a href="#" onclick="showOtherResources()">
                    <i class="material-icons">more_horiz</i>
                    Outros Recursos
                </a>
                <a href="../logout.php">
                    <i class="material-icons">exit_to_app</i>
                    Sair
                </a>
            </nav>
        </aside>

        <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

        <div class="admin-content">
            <header class="admin-header">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="material-icons">menu</i>
                </button>
                <h1>Gerenciar Usuários</h1>
                <div class="user-area">
                    <div class="user-icon"><?= strtoupper(substr($_SESSION['user']['nome'], 0, 1)) ?></div>
                    <span class="user-name"><?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="material-icons">exit_to_app</i>
                        Sair
                    </a>
                </div>
            </header>

            <div class="content-area">
                <?php if (isset($_SESSION["erro"])): ?>
                    <div class="message message-error">
                        <?= htmlspecialchars($_SESSION["erro"]) ?>
                    </div>
                    <?php unset($_SESSION["erro"]); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION["sucesso"])): ?>
                    <div class="message message-success">
                        <?= htmlspecialchars($_SESSION["sucesso"]) ?>
                    </div>
                    <?php unset($_SESSION["sucesso"]); ?>
                <?php endif; ?>
                
                <!-- Formulário de Adicionar/Editar Usuário -->
                <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: var(--shadow); margin-bottom: 2rem;">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem; font-size: 1.25rem; font-weight: 600; border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem;">
                        <span class="material-icons">person_add</span>
                        <?= $usuario_edicao ? 'Editar Usuário' : 'Adicionar Usuário' ?>
                    </h3>
                    
                    <form method="POST" action="gerenciar_usuarios.php">
                        <input type="hidden" name="id" value="<?= $usuario_edicao['id'] ?? '' ?>">
                        <input type="hidden" name="<?= $usuario_edicao ? 'editar_usuario' : 'adicionar_usuario' ?>" value="1">
                        
                        <div class="form-group">
                            <label for="nome">Nome:</label>
                            <input type="text" id="nome" name="nome" class="form-control" value="<?= htmlspecialchars($usuario_edicao['nome'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-mail:</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($usuario_edicao['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="senha">Senha<?= $usuario_edicao ? ' (deixe em branco para manter a atual)' : '' ?>:</label>
                            <input type="password" id="senha" name="senha" class="form-control" <?= $usuario_edicao ? '' : 'required' ?>>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmar_senha">Confirmar Senha:</label>
                            <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" <?= $usuario_edicao ? '' : 'required' ?>>
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo">Tipo:</label>
                            <select id="tipo" name="tipo" class="form-control" required>
                                <option value="deputado" <?= ($usuario_edicao['tipo'] ?? '') == 'deputado' ? 'selected' : '' ?>>Deputado</option>
                                <option value="senador" <?= ($usuario_edicao['tipo'] ?? '') == 'senador' ? 'selected' : '' ?>>Senador</option>
                                <option value="admin" <?= ($usuario_edicao['tipo'] ?? '') == 'admin' ? 'selected' : '' ?>>Administrador</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="admin-privileges-group" style="display: <?= ($usuario_edicao['tipo'] ?? '') == 'admin' ? 'none' : 'block' ?>;">
                            <label for="is_admin">Privilégios de Administrador:</label>
                            <select id="is_admin" name="is_admin" class="form-control">
                                <option value="false" <?= ($usuario_edicao['is_admin'] ?? false) == false ? 'selected' : '' ?>>Não</option>
                                <option value="true" <?= ($usuario_edicao['is_admin'] ?? false) == true ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons">save</i>
                                Salvar
                            </button>
                            <?php if ($usuario_edicao): ?>
                                <a href="gerenciar_usuarios.php" class="btn btn-secondary">
                                    <i class="material-icons">cancel</i>
                                    Cancelar
                                </a>
                            <?php endif; ?>
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
                                <th>Admin</th>
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
                                    <?php 
                                    switch($usuario['tipo']) {
                                        case 'admin': echo 'Administrador'; break;
                                        case 'deputado': echo 'Deputado'; break;
                                        case 'senador': echo 'Senador'; break;
                                        default: echo htmlspecialchars($usuario['tipo']); 
                                    }
                                    ?>
                                </td>
                                <td><?= $usuario['is_admin'] ? 'Sim' : 'Não' ?></td>
                                <td><?= date('d/m/Y', strtotime($usuario['criado_em'])) ?></td>
                                <td class="actions-cell">
                                    <a href="gerenciar_usuarios.php?editar=<?= $usuario['id'] ?>" class="action-link">
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
    </div>

    <script>
        // Menu Toggle para mobile
        const menuToggle = document.querySelector('.menu-toggle');
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

        // Validação de senha no formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const senha = document.getElementById('senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            const isEdit = <?= $usuario_edicao ? 'true' : 'false' ?>;
            
            // Apenas validar se for adição ou se senha foi preenchida na edição
            if ((!isEdit || senha) && senha !== confirmarSenha) {
                alert('As senhas não coincidem!');
                e.preventDefault();
                return false;
            }
            
            if (!isEdit && senha.length < 8) {
                alert('A senha deve ter pelo menos 8 caracteres!');
                e.preventDefault();
                return false;
            }
        });

        // Controlar visibilidade do campo de privilégios de admin
        document.getElementById('tipo').addEventListener('change', function() {
            const adminGroup = document.getElementById('admin-privileges-group');
            if (this.value === 'admin') {
                adminGroup.style.display = 'none';
                document.getElementById('is_admin').value = 'true';
            } else {
                adminGroup.style.display = 'block';
            }
        });

        // Inicializar na carga da página
        document.addEventListener('DOMContentLoaded', function() {
            const tipoSelect = document.getElementById('tipo');
            if (tipoSelect && tipoSelect.value === 'admin') {
                document.getElementById('admin-privileges-group').style.display = 'none';
            }
        });
    </script>
</body>
</html>