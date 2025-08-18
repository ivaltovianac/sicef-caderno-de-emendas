
<?php
// SICEF-caderno-de-emendas/public/admin/sugestoes.php
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

// Campos editáveis para sugestões
$campos_editaveis = [
    'objeto_intervencao' => 'Objeto de Intervenção',
    'valor' => 'Valor',
    'eixo_tematico' => 'Eixo Temático',
    'orgao' => 'Unidade Responsável',
    'ods' => 'ODS',
    'justificativa' => 'Justificativa',
    'regionalizacao' => 'Regionalização',
    'unidade_orcamentaria' => 'Unidade Orçamentária',
    'programa' => 'Programa',
    'acao' => 'Ação',
    'categoria_economica' => 'Categoria Econômica'
];

// Processar resposta à sugestão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'responder_sugestao') {
            $sugestao_id = (int)$_POST['sugestao_id'];
            $resposta = trim($_POST['resposta'] ?? '');
            $status = $_POST['status'] ?? '';
            $aplicar_mudanca = isset($_POST['aplicar_mudanca']) && $_POST['aplicar_mudanca'] === '1';

            if (empty($sugestao_id) || empty($status) || !in_array($status, ['aprovado', 'rejeitado'])) {
                throw new Exception('Dados inválidos');
            }

            $pdo->beginTransaction();

            // Buscar dados da sugestão
            $stmt_sugestao = $pdo->prepare("
                SELECT s.*, e.* 
                FROM sugestoes_emendas s 
                JOIN emendas e ON s.emenda_id = e.id 
                WHERE s.id = ? AND s.status = 'pendente'
            ");
            $stmt_sugestao->execute([$sugestao_id]);
            $sugestao = $stmt_sugestao->fetch(PDO::FETCH_ASSOC);

            if (!$sugestao) {
                throw new Exception('Sugestão não encontrada ou já processada');
            }

            // Atualizar status da sugestão
            $stmt = $pdo->prepare("UPDATE sugestoes_emendas SET status = ?, resposta = ?, respondido_em = NOW(), respondido_por = ? WHERE id = ?");
            $stmt->execute([$status, $resposta, $_SESSION['user']['id'], $sugestao_id]);

            // Se aprovado e deve aplicar mudança, atualizar a emenda
            if ($status === 'aprovado' && $aplicar_mudanca) {
                $campo_permitido = array_key_exists($sugestao['campo_sugerido'], $campos_editaveis);

                if ($campo_permitido) {
                    $stmt_update = $pdo->prepare("UPDATE emendas SET {$sugestao['campo_sugerido']} = ?, atualizado_em = NOW() WHERE id = ?");
                    $stmt_update->execute([$sugestao['valor_sugerido'], $sugestao['emenda_id']]);
                }
            }

            // Criar notificação para o usuário
            $mensagem_notif = $status === 'aprovado' 
                ? "Sua sugestão foi aprovada" . ($aplicar_mudanca ? " e aplicada" : "") 
                : "Sua sugestão foi rejeitada";
            
            if (!empty($resposta)) {
                $mensagem_notif .= ": " . $resposta;
            }

            $stmt_notif = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem, referencia_id, criado_em) SELECT usuario_id, 'resposta_sugestao', ?, id, NOW() FROM sugestoes_emendas WHERE id = ?");
            $stmt_notif->execute([$mensagem_notif, $sugestao_id]);

            $pdo->commit();
            $message = "Resposta enviada com sucesso!";

        } else {
            throw new Exception('Ação inválida');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao processar sugestão: " . $e->getMessage());
        $error = "Erro interno do servidor";
    }
}

// Carregar sugestões com filtros e paginação
$filtro_status = $_GET['status'] ?? '';
$where_conditions = [];
$params = [];

if (!empty($filtro_status)) {
    $where_conditions[] = "s.status = ?";
    $params[] = $filtro_status;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Paginação
$itens_por_pagina = 20;
$pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Count query
$count_query = "SELECT COUNT(*) FROM sugestoes_emendas s $where_clause";
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_sugestoes = $stmt_count->fetchColumn();
$total_paginas = ceil($total_sugestoes / $itens_por_pagina);

// Main query
$query = "SELECT s.*, u.nome as usuario_nome, e.objeto_intervencao, ur.nome as respondido_por_nome
          FROM sugestoes_emendas s 
          JOIN usuarios u ON s.usuario_id = u.id 
          JOIN emendas e ON s.emenda_id = e.id 
          LEFT JOIN usuarios ur ON s.respondido_por = ur.id
          $where_clause 
          ORDER BY s.criado_em DESC 
          LIMIT ? OFFSET ?";
$params[] = $itens_por_pagina;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sugestoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sugestões - SICEF Admin</title>
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

        .sugestao-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: white;
        }

        .sugestao-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .sugestao-info {
            flex: 1;
        }

        .sugestao-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .campo-sugerido {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 0.75rem;
            margin: 0.5rem 0;
        }

        .valor-original {
            color: #6c757d;
            text-decoration: line-through;
        }

        .valor-sugerido {
            color: var(--primary-color);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar melhorado -->
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
            <a href="solicitacoes_acesso.php">
                <span class="material-icons">person_add</span>
                Solicitações
                <?php if ($solicitacoes_pendentes > 0): ?>
                    <span class="badge bg-warning"><?= $solicitacoes_pendentes ?></span>
                <?php endif; ?>
            </a>
            <a href="sugestoes.php" class="active">
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
            <h2>Sugestões dos Usuários</h2>
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
                    <a href="sugestoes.php" class="btn btn-secondary">
                        <span class="material-icons me-1">clear</span>
                        Limpar
                    </a>
                </form>
            </div>

            <!-- Lista de Sugestões -->
            <div class="card">
                <div class="card-header">
                    <span class="material-icons me-2">lightbulb</span>
                    Sugestões (<?= number_format($total_sugestoes) ?> total)
                </div>
                <div class="card-body">
                    <?php if (empty($sugestoes)): ?>
                        <div class="text-center py-4">
                            <span class="material-icons me-2" style="font-size: 3rem; color: #6c757d;">lightbulb</span>
                            <p class="text-muted">Nenhuma sugestão encontrada</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sugestoes as $sugestao): ?>
                            <div class="sugestao-card">
                                <div class="sugestao-header">
                                    <div class="sugestao-info">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <strong><?= htmlspecialchars($sugestao['usuario_nome']) ?></strong>
                                            <span class="status-badge status-<?= $sugestao['status'] ?>">
                                                <?= ucfirst($sugestao['status']) ?>
                                            </span>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($sugestao['criado_em'])) ?>
                                            </small>
                                        </div>
                                        <p class="mb-1">
                                            <strong>Emenda:</strong> 
                                            <?= htmlspecialchars(substr($sugestao['objeto_intervencao'], 0, 100)) ?>...
                                        </p>
                                    </div>
                                    <?php if ($sugestao['status'] === 'pendente'): ?>
                                        <div class="sugestao-actions">
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="responderSugestao(<?= $sugestao['id'] ?>, 'aprovar')">
                                                <span class="material-icons">check</span>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="responderSugestao(<?= $sugestao['id'] ?>, 'rejeitar')">
                                                <span class="material-icons">close</span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="campo-sugerido">
                                    <strong>Campo:</strong> <?= htmlspecialchars($campos_editaveis[$sugestao['campo_sugerido']] ?? $sugestao['campo_sugerido']) ?><br>
                                    <strong>Valor Sugerido:</strong> 
                                    <span class="valor-sugerido"><?= htmlspecialchars($sugestao['valor_sugerido']) ?></span>
                                </div>

                                <?php if ($sugestao['status'] !== 'pendente'): ?>
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <strong>Resposta:</strong> <?= htmlspecialchars($sugestao['resposta'] ?? 'Sem resposta') ?><br>
                                        <small class="text-muted">
                                            Respondido em <?= date('d/m/Y H:i', strtotime($sugestao['respondido_em'])) ?>
                                            <?php if ($sugestao['respondido_por_nome']): ?>
                                                por <?= htmlspecialchars($sugestao['respondido_por_nome']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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

    <!-- Modal Responder Sugestão -->
    <div class="modal fade" id="responderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="material-icons me-2">lightbulb</span>
                        Responder Sugestão
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="responder_sugestao">
                    <input type="hidden" name="sugestao_id" id="responder_id">
                    <input type="hidden" name="status" id="responder_status">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="resposta" class="form-label">Resposta</label>
                            <textarea class="form-control" id="resposta" name="resposta" rows="3" 
                                      placeholder="Digite sua resposta (opcional)..."></textarea>
                        </div>
                        <div class="mb-3" id="aplicar_mudanca_div" style="display: none;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="aplicar_mudanca" name="aplicar_mudanca" value="1">
                                <label class="form-check-label" for="aplicar_mudanca">
                                    Aplicar mudança na emenda
                                </label>
                                <small class="form-text text-muted">
                                    Marque esta opção para aplicar automaticamente a sugestão na emenda.
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="responder_btn">
                            <span class="material-icons me-1">send</span>
                            Enviar Resposta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

        // Função para responder sugestão
        function responderSugestao(id, acao) {
            document.getElementById('responder_id').value = id;
            document.getElementById('responder_status').value = acao === 'aprovar' ? 'aprovado' : 'rejeitado';
            
            const aplicarDiv = document.getElementById('aplicar_mudanca_div');
            const btn = document.getElementById('responder_btn');
            
            if (acao === 'aprovar') {
                aplicarDiv.style.display = 'block';
                btn.className = 'btn btn-success';
                btn.innerHTML = '<span class="material-icons me-1">check</span>Aprovar';
            } else {
                aplicarDiv.style.display = 'none';
                btn.className = 'btn btn-danger';
                btn.innerHTML = '<span class="material-icons me-1">close</span>Rejeitar';
            }
            
            new bootstrap.Modal(document.getElementById('responderModal')).show();
        }

        // Limpar modal ao fechar
        document.getElementById('responderModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('resposta').value = '';
            document.getElementById('aplicar_mudanca').checked = false;
        });
    </script>
</body>
</html>
