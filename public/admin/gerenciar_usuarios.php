<?php
/**
 * Gerenciamento de Usuários - SICEF
 * 
 * Este arquivo é responsável por gerenciar os usuários do sistema SICEF.
 * Permite ao administrador criar, editar, deletar e visualizar usuários,
 * além de aplicar filtros e paginação na listagem.
 * 
 * Funcionalidades:
 * - Listagem de usuários com filtros (nome, email, tipo, status)
 * - Criação de novos usuários
 * - Edição de usuários existentes
 * - Exclusão de usuários
 * - Paginação dos resultados
 * - Validação de dados
 * 
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */

// Inicia a sessão para verificar se o usuário está logado e é administrador
session_start();

// Verifica se o usuário está logado e se é administrador
if (!isset($_SESSION["user"]) || !$_SESSION["user"]["is_admin"]) {
    // Redireciona para a página de login caso não seja administrador
    header("Location: ../login.php");
    exit;
}

// Inclui os arquivos de configuração do banco de dados e modelo de usuário
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../models/User.php";

// Contadores para badges no menu
$stmt_solicitacoes = $pdo->query("SELECT COUNT(*) as total FROM solicitacoes_acesso WHERE status = 'pendente'");
$solicitacoes_pendentes = $stmt_solicitacoes->fetch()['total'];

$stmt_sugestoes = $pdo->query("SELECT COUNT(*) FROM sugestoes_emendas WHERE status = 'pendente'");
$qtde_sugestoes_pendentes = $stmt_sugestoes->fetchColumn();

// Instancia o modelo de usuário
$userModel = new User($pdo);

// Variáveis para mensagens de feedback
$message = "";
$error = "";

// Processa ações do formulário (criar, editar, deletar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'create':
                // Cria um novo usuário
                $userData = [
                    'nome' => trim($_POST['nome'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'senha' => $_POST['senha'] ?? '',
                    'tipo' => $_POST['tipo'] ?? '',
                    'is_admin' => isset($_POST['is_admin']) ? 1 : 0
                ];
                
                // Validação dos dados
                if (empty($userData['nome']) || empty($userData['email']) || empty($userData['senha']) || empty($userData['tipo'])) {
                    throw new Exception("Todos os campos são obrigatórios");
                }
                
                if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("E-mail inválido");
                }
                
                if (strlen($userData['senha']) < 6) {
                    throw new Exception("A senha deve ter pelo menos 6 caracteres");
                }
                
                // Insere o novo usuário no banco de dados
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo, is_admin, is_user, criado_em) VALUES (?, ?, ?, ?, ?, true, NOW())");
                $hashedPassword = password_hash($userData['senha'], PASSWORD_DEFAULT);
                
                if ($stmt->execute([$userData['nome'], $userData['email'], $hashedPassword, $userData['tipo'], $userData['is_admin']])) {
                    $message = "Usuário criado com sucesso!";
                } else {
                    throw new Exception("Erro ao criar usuário");
                }
                break;

            case 'update':
                // Atualiza um usuário existente
                $id = (int)$_POST['id'];
                $userData = [
                    'nome' => trim($_POST['nome'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'tipo' => $_POST['tipo'] ?? '',
                    'is_admin' => isset($_POST['is_admin']) ? 1 : 0,
                    'is_user' => isset($_POST['is_user']) ? 1 : 0
                ];
                
                // Verifica se uma nova senha foi fornecida
                if (!empty($_POST['senha'])) {
                    if (strlen($_POST['senha']) < 6) {
                        throw new Exception("A senha deve ter pelo menos 6 caracteres");
                    }
                    $userData['senha'] = $_POST['senha'];
                }
                
                // Atualiza o usuário usando o modelo
                $result = $userModel->update($id, $userData);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    throw new Exception($result['message']);
                }
                break;

            case 'delete':
                // Deleta um usuário
                $id = (int)$_POST['id'];
                if ($id === $_SESSION['user']['id']) {
                    throw new Exception("Não é possível deletar seu próprio usuário");
                }
                
                // Deleta o usuário do banco de dados (exceto o próprio usuário logado)
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND id != ?");
                if ($stmt->execute([$id, $_SESSION['user']['id']])) {
                    $message = "Usuário deletado com sucesso!";
                } else {
                    throw new Exception("Erro ao deletar usuário");
                }
                break;

            default:
                throw new Exception("Ação inválida");
        }
    } catch (Exception $e) {
        // Trata erros de validação
        $error = $e->getMessage();
    } catch (PDOException $e) {
        // Trata erros de banco de dados
        error_log("Erro no gerenciamento de usuários: " . $e->getMessage());
        $error = "Erro interno do servidor";
    }
}

// Filtros e paginação
$filtros = [];
$where_conditions = [];
$params = [];

// Aplica filtros se fornecidos
if (!empty($_GET['nome'])) {
    $where_conditions[] = "nome ILIKE ?";
    $params[] = '%' . $_GET['nome'] . '%';
    $filtros['nome'] = $_GET['nome'];
}

if (!empty($_GET['email'])) {
    $where_conditions[] = "email ILIKE ?";
    $params[] = '%' . $_GET['email'] . '%';
    $filtros['email'] = $_GET['email'];
}

if (!empty($_GET['tipo'])) {
    $where_conditions[] = "tipo = ?";
    $params[] = $_GET['tipo'];
    $filtros['tipo'] = $_GET['tipo'];
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where_conditions[] = "is_user = ?";
    $params[] = (bool)$_GET['status'];
    $filtros['status'] = $_GET['status'];
}

// Monta a cláusula WHERE
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Paginação
$itens_por_pagina = 20;
$pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Query para contar o número total de usuários
$count_query = "SELECT COUNT(*) FROM usuarios $where_clause";
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_usuarios = $stmt_count->fetchColumn();
$total_paginas = ceil($total_usuarios / $itens_por_pagina);

// Query principal para buscar os usuários
$query = "SELECT * FROM usuarios $where_clause ORDER BY nome LIMIT $itens_por_pagina OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Carrega os tipos de usuário para os filtros
$tipos_usuario = $pdo->query("SELECT DISTINCT tipo FROM usuarios WHERE tipo IS NOT NULL ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - SICEF Admin</title>
    <!-- Bootstrap e Material Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00796B;
            --secondary-color: #009688;
            --accent-color: #FFC107;
            --light-color: #ECEFF1;
            --dark-color: #263238;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        /* Sidebar responsivo */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            color: white;
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .sidebar-header p {
            margin: 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu .material-icons {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .badge {
            margin-left: auto;
        }

        /* Main content responsivo */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .top-bar {
            background: white;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            cursor: pointer;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: auto;
        }

        .content-area {
            padding: 2rem;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--dark-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .pagination .page-link {
            color: var(--primary-color);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* Responsividade mobile */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .top-bar {
                flex-direction: column;
                gap: 1rem;
            }

            .user-info {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }

            .content-area {
                padding: 1rem;
            }

            .table-responsive {
                font-size: 0.875rem;
            }
        }

        /* Overlay para mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        .filters-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .admin-badge {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4>SICEF Admin</h4>
            <p>Painel Administrativo</p>
        </div>
        <nav class="sidebar-menu">
            <a href="admin_dashboard.php">
                <span class="material-icons">dashboard</span>
                Dashboard
            </a>
            <a href="gerenciar_usuarios.php" class="active">
                <span class="material-icons">manage_accounts</span>
                Gerenciar Usuários
            </a>
            <a href="solicitacoes_acesso.php">
                <span class="material-icons">person_add</span>
                Solicitações
                <?php if ($solicitacoes_pendentes > 0): ?>
                    <span class="badge bg-warning"><?= $solicitacoes_pendentes ?></span>
                <?php endif; ?>
            </a>
            <a href="sugestoes.php">
                <span class="material-icons">lightbulb</span>
                Sugestões
                <?php if ($qtde_sugestoes_pendentes > 0): ?>
                    <span class="badge bg-info"><?= $qtde_sugestoes_pendentes ?></span>
                <?php endif; ?>
            </a>
            <a href="relatorios.php">
                <span class="material-icons">assessment</span>
                Relatórios
            </a>
            <a href="configuracoes.php">
                <span class="material-icons">settings</span>
                Configurações
            </a>
            <a href="../logout.php">
                <span class="material-icons">logout</span>
                Sair
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="top-bar">
            <button class="menu-toggle" id="menuToggle">
                <span class="material-icons">menu</span>
            </button>
            <h2>Gerenciar Usuários</h2>
            <div class="user-info">
                <span class="material-icons">account_circle</span>
                <span>Olá, <?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <span class="material-icons me-2">check_circle</span>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <span class="material-icons me-2">error</span>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="filters-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><span class="material-icons me-2">filter_list</span>Filtros</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <span class="material-icons me-1">person_add</span>
                        Novo Usuário
                    </button>
                </div>
                <form method="GET" class="filters-form">
                    <div class="filters-grid">
                        <div>
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" 
                                   value="<?= htmlspecialchars($_GET['nome'] ?? '') ?>" 
                                   placeholder="Buscar por nome">
                        </div>
                        <div>
                            <label class="form-label">E-mail</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" 
                                   placeholder="Buscar por e-mail">
                        </div>
                        <div>
                            <label class="form-label">Tipo</label>
                            <select name="tipo" id="tipo" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($tipos_usuario as $tipo): ?>
                                    <option value="<?= htmlspecialchars($tipo) ?>" 
                                            <?= ($_GET["tipo"] ?? "") === $tipo ? "selected" : "" ?>>
                                        <?= htmlspecialchars(ucfirst($tipo)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">Todos</option>
                                <option value="1" <?= ($_GET["status"] ?? "") === "1" ? "selected" : "" ?>>Ativo</option>
                                <option value="0" <?= ($_GET["status"] ?? "") === "0" ? "selected" : "" ?>>Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons me-1">search</span>
                            Filtrar
                        </button>
                        <a href="gerenciar_usuarios.php" class="btn btn-secondary">
                            <span class="material-icons me-1">clear</span>
                            Limpar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabela de Usuários -->
            <div class="card">
                <div class="card-header">
                    <span class="material-icons me-2">people</span>
                    Usuários Cadastrados (<?= number_format($total_usuarios) ?> total)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Tipo</th>
                                    <th>Status</th>
                                    <th>Criado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <span class="material-icons me-2">info</span>
                                            Nenhum usuário encontrado
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td><?= $usuario['id'] ?></td>
                                            <td>
                                                <?= htmlspecialchars($usuario['nome']) ?>
                                                <?php if ($usuario['is_admin']): ?>
                                                    <span class="status-badge admin-badge ms-1">Admin</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($usuario['email']) ?></td>
                                            <td><?= htmlspecialchars(ucfirst($usuario['tipo'])) ?></td>
                                            <td>
                                                <span class="status-badge <?= $usuario['is_user'] ? 'status-active' : 'status-inactive' ?>">
                                                    <?= $usuario['is_user'] ? 'Ativo' : 'Inativo' ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($usuario['criado_em'])) ?></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="editUser(<?= htmlspecialchars(json_encode($usuario)) ?>)">
                                                        <span class="material-icons">edit</span>
                                                    </button>
                                                    <?php if ($usuario['id'] != $_SESSION['user']['id']): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteUser(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nome']) ?>')">
                                                            <span class="material-icons">delete</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($pagina_atual > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?pagina=<?= $pagina_atual - 1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : '' ?>">
                                    Anterior
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                            <li class="page-item <?= $i === $pagina_atual ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($pagina_atual < $total_paginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?pagina=<?= $pagina_atual + 1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : '' ?>">
                                    Próxima
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Criar Usuário -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="material-icons me-2">person_add</span>
                        Novo Usuário
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="create_nome" class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" id="create_nome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="create_email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="create_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="create_senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="create_senha" name="senha" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label for="create_tipo" class="form-label">Tipo</label>
                            <select name="tipo" id="create_tipo" class="form-control" required>
                                <option value="">Selecione...</option>
                                <option value="deputado">Deputado</option>
                                <option value="senador">Senador</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="create_is_admin" name="is_admin">
                                <label class="form-check-label" for="create_is_admin">
                                    Administrador
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons me-1">save</span>
                            Criar Usuário
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Usuário -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="material-icons me-2">edit</span>
                        Editar Usuário
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_nome" class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" id="edit_nome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_senha" class="form-label">Nova Senha (deixe em branco para manter)</label>
                            <input type="password" class="form-control" id="edit_senha" name="senha" minlength="6">
                        </div>
                        <div class="mb-3">
                            <label for="edit_tipo" class="form-label">Tipo</label>
                            <select name="tipo" id="edit_tipo" class="form-control" required>
                                <option value="">Selecione...</option>
                                <option value="deputado">Deputado</option>
                                <option value="senador">Senador</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_admin" name="is_admin">
                                <label class="form-check-label" for="edit_is_admin">
                                    Administrador
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_user" name="is_user">
                                <label class="form-check-label" for="edit_is_user">
                                    Usuário Ativo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons me-1">save</span>
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form para deletar (oculto) -->
    <form id="deleteForm" method="POST" action="gerenciar_usuarios.php" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Menu toggle responsivo
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });

            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            });

            // Fechar sidebar ao clicar em link (mobile)
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('show');
                        sidebarOverlay.classList.remove('show');
                    }
                });
            });
        });

        // Funções de usuário
        function editUser(user) {
            document.getElementById('edit_id').value = user.id;
            document.getElementById('edit_nome').value = user.nome;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_tipo').value = user.tipo;
            document.getElementById('edit_is_admin').checked = user.is_admin == 1;
            document.getElementById('edit_is_user').checked = user.is_user == 1;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function deleteUser(id, nome) {
            if (confirm(`Tem certeza que deseja deletar o usuário "${nome}"?`)) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Limpar modais ao fechar
        document.addEventListener('DOMContentLoaded', function() {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('hidden.bs.modal', function() {
                    const form = modal.querySelector('form');
                    if (form) form.reset();
                });
            });
        });
    </script>
</body>
</html>