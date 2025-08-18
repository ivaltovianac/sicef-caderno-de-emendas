
<?php
// SICEF-caderno-de-emendas/public/admin/solicitacoes_acesso.php
session_start();
if (!isset($_SESSION["user"]) || !$_SESSION["user"]["is_admin"]) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";

// Contadores para badges
$stmt_solicitacoes = $pdo->query("SELECT COUNT(*) as total FROM solicitacoes_acesso WHERE status = 'pendente'");
$solicitacoes_pendentes = $stmt_solicitacoes->fetch()['total'];

$stmt_sugestoes = $pdo->query("SELECT COUNT(*) FROM sugestoes_emendas WHERE status = 'pendente'");
$qtde_sugestoes_pendentes = $stmt_sugestoes->fetchColumn();

$message = "";
$error = "";

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $solicitacao_id = (int)($_POST['solicitacao_id'] ?? 0);

        if (empty($action) || empty($solicitacao_id)) {
            throw new Exception('Dados inválidos');
        }

        // Buscar solicitação
        $stmt = $pdo->prepare("SELECT * FROM solicitacoes_acesso WHERE id = ? AND status = 'pendente'");
        $stmt->execute([$solicitacao_id]);
        $solicitacao = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$solicitacao) {
            throw new Exception('Solicitação não encontrada ou já processada');
        }

        $pdo->beginTransaction();

        if ($action === 'aprovar') {
            // Cria usuário
            $senha_temporaria = bin2hex(random_bytes(4)); // Senha temporária de 8 caracteres
            $senha_hash = password_hash($senha_temporaria, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo, is_admin, is_user, criado_em) VALUES (?, ?, ?, ?, false, true, NOW())");
            $stmt->execute([$solicitacao['nome'], $solicitacao['email'], $senha_hash, $solicitacao['tipo']]);
            
            $stmt = $pdo->prepare("UPDATE solicitacoes_acesso SET status = 'aprovado', data_resposta = NOW(), processado_por = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user']['id'], $solicitacao_id]);
            
            $usuario_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem, criado_em) VALUES (?, 'acesso_aprovado', ?, NOW())");
            $mensagem_notif = "Seu acesso foi aprovado! Senha temporária: $senha_temporaria";
            $stmt->execute([$usuario_id, $mensagem_notif]);
            
            $message = "Solicitação aprovada! Usuário criado com senha temporária: $senha_temporaria";

        } elseif ($action === 'rejeitar') {
            $motivo = trim($_POST['motivo'] ?? '');
            if (empty($motivo)) {
                throw new Exception('Motivo da rejeição é obrigatório');
            }
            
            $stmt = $pdo->prepare("UPDATE solicitacoes_acesso SET status = 'rejeitado', motivo_rejeicao = ?, data_resposta = NOW(), processado_por = ? WHERE id = ?");
            $stmt->execute([$motivo, $_SESSION['user']['id'], $solicitacao_id]);
            
            $message = "Solicitação rejeitada com sucesso!";
        } else {
            throw new Exception('Ação inválida');
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao processar solicitação: " . $e->getMessage());
        $error = "Erro interno do servidor";
    }
}

// Carrega solicitações com paginação
$filtro_status = $_GET['status'] ?? '';
$where_conditions = [];
$params = [];

if (!empty($filtro_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $filtro_status;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Paginação
$itens_por_pagina = 20;
$pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Count query
$count_query = "SELECT COUNT(*) FROM solicitacoes_acesso $where_clause";
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_solicitacoes = $stmt_count->fetchColumn();
$total_paginas = ceil($total_solicitacoes / $itens_por_pagina);

// Main query
$query = "SELECT s.*, u.nome as processado_por_nome 
          FROM solicitacoes_acesso s 
          LEFT JOIN usuarios u ON s.processado_por = u.id 
          $where_clause 
          ORDER BY s.data_solicitacao DESC 
          LIMIT ? OFFSET ?";
$params[] = $itens_por_pagina;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitações de Acesso - SICEF Admin</title>
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

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-aprovado {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejeitado {
            background-color: #f8d7da;
            color: #721c24;
        }

        .filters-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            <a href="gerenciar_usuarios.php">
                <span class="material-icons">people</span>
                Usuários
            </a>
            <a href="solicitacoes_acesso.php" class="active">
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
            <h2>Solicitações de Acesso</h2>
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
                <h5><span class="material-icons me-2">filter_list</span>Filtros</h5>
                <form method="GET" class="d-flex gap-2 align-items-end">
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Todos</option>
                            <option value="pendente" <?= $filtro_status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="aprovado" <?= $filtro_status === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                            <option value="rejeitado" <?= $filtro_status === 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-icons me-1">search</span>
                        Filtrar
                    </button>
                    <a href="solicitacoes_acesso.php" class="btn btn-secondary">
                        <span class="material-icons me-1">clear</span>
                        Limpar
                    </a>
                </form>
            </div>

            <!-- Tabela de Solicitações -->
            <div class="card">
                <div class="card-header">
                    <span class="material-icons me-2">person_add</span>
                    Solicitações de Acesso (<?= number_format($total_solicitacoes) ?> total)
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
                                    <th>Data Solicitação</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($solicitacoes)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <span class="material-icons me-2">info</span>
                                            Nenhuma solicitação encontrada
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($solicitacoes as $solicitacao): ?>
                                        <tr>
                                            <td><?= $solicitacao['id'] ?></td>
                                            <td><?= htmlspecialchars($solicitacao['nome']) ?></td>
                                            <td><?= htmlspecialchars($solicitacao['email']) ?></td>
                                            <td><?= htmlspecialchars(ucfirst($solicitacao['tipo'])) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $solicitacao['status'] ?>">
                                                    <?= ucfirst($solicitacao['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($solicitacao['data_solicitacao'])) ?></td>
                                            <td>
                                                <?php if ($solicitacao['status'] === 'pendente'): ?>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-sm btn-success" 
                                                                onclick="aprovarSolicitacao(<?= $solicitacao['id'] ?>, '<?= htmlspecialchars($solicitacao['nome']) ?>')">
                                                            <span class="material-icons">check</span>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="rejeitarSolicitacao(<?= $solicitacao['id'] ?>, '<?= htmlspecialchars($solicitacao['nome']) ?>')">
                                                            <span class="material-icons">close</span>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <small class="text-muted">
                                                        Processado em <?= date('d/m/Y', strtotime($solicitacao['data_resposta'])) ?>
                                                        <?php if ($solicitacao['processado_por_nome']): ?>
                                                            por <?= htmlspecialchars($solicitacao['processado_por_nome']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
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
                                <a class="page-link" href="?pagina=<?= $pagina_atual - 1 ?><?= !empty($filtro_status) ? '&status=' . $filtro_status : '' ?>">
                                    Anterior
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                            <li class="page-item <?= $i === $pagina_atual ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?><?= !empty($filtro_status) ? '&status=' . $filtro_status : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($pagina_atual < $total_paginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?pagina=<?= $pagina_atual + 1 ?><?= !empty($filtro_status) ? '&status=' . $filtro_status : '' ?>">
                                    Próxima
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Rejeitar -->
    <div class="modal fade" id="rejeitarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="material-icons me-2">close</span>
                        Rejeitar Solicitação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="rejeitar">
                    <input type="hidden" name="solicitacao_id" id="rejeitar_id">
                    <div class="modal-body">
                        <p>Tem certeza que deseja rejeitar a solicitação de <strong id="rejeitar_nome"></strong>?</p>
                        <div class="mb-3">
                            <label for="motivo" class="form-label">Motivo da Rejeição *</label>
                            <textarea class="form-control" id="motivo" name="motivo" rows="3" required 
                                      placeholder="Digite o motivo da rejeição..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <span class="material-icons me-1">close</span>
                            Rejeitar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Forms ocultos -->
    <form id="aprovarForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="aprovar">
        <input type="hidden" name="solicitacao_id" id="aprovar_id">
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

        // Funções de solicitação
        function aprovarSolicitacao(id, nome) {
            if (confirm(`Tem certeza que deseja aprovar a solicitação de "${nome}"?\n\nUm usuário será criado com senha temporária.`)) {
                document.getElementById('aprovar_id').value = id;
                document.getElementById('aprovarForm').submit();
            }
        }

        function rejeitarSolicitacao(id, nome) {
            document.getElementById('rejeitar_id').value = id;
            document.getElementById('rejeitar_nome').textContent = nome;
            new bootstrap.Modal(document.getElementById('rejeitarModal')).show();
        }

        // Limpa modal ao fechar
        document.getElementById('rejeitarModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('motivo').value = '';
        });
    </script>
</body>
</html>
