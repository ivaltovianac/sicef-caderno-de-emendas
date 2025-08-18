<?php
// SICEF-caderno-de-emendas/public/user/user_dashboard.php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION["user"]["is_admin"]) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";

$usuario_id = $_SESSION["user"]["id"];

// Carregar estatísticas do usuário
try {
    // Total de emendas selecionadas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_emendas WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $total_minhas_emendas = $stmt->fetchColumn();

    // Valor total das emendas selecionadas
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(e.valor), 0) as total_valor
        FROM usuario_emendas ue 
        JOIN emendas e ON ue.emenda_id = e.id 
        WHERE ue.usuario_id = ?
    ");
    $stmt->execute([$usuario_id]);
    $valor_total = $stmt->fetchColumn();

    // Valor total destinado
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(vd.valor_destinado), 0) as total_destinado
        FROM valores_destinados vd 
        WHERE vd.usuario_id = ?
    ");
    $stmt->execute([$usuario_id]);
    $valor_destinado = $stmt->fetchColumn();

    // Sugestões enviadas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sugestoes_emendas WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $total_sugestoes = $stmt->fetchColumn();

    // Sugestões pendentes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sugestoes_emendas WHERE usuario_id = ? AND status = 'pendente'");
    $stmt->execute([$usuario_id]);
    $sugestoes_pendentes = $stmt->fetchColumn();

    // Notificações não lidas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = false");
    $stmt->execute([$usuario_id]);
    $notificacoes_nao_lidas = $stmt->fetchColumn();

    // Últimas emendas selecionadas
    $stmt = $pdo->prepare("
        SELECT e.*, ue.criado_em as data_selecao
        FROM usuario_emendas ue 
        JOIN emendas e ON ue.emenda_id = e.id 
        WHERE ue.usuario_id = ? 
        ORDER BY ue.criado_em DESC 
        LIMIT 5
    ");
    $stmt->execute([$usuario_id]);
    $ultimas_emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Últimas notificações
    $stmt = $pdo->prepare("
        SELECT * FROM notificacoes 
        WHERE usuario_id = ? 
        ORDER BY criado_em DESC 
        LIMIT 5
    ");
    $stmt->execute([$usuario_id]);
    $ultimas_notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro ao carregar dashboard do usuário: " . $e->getMessage());
    $total_minhas_emendas = $valor_total = $valor_destinado = $total_sugestoes = $sugestoes_pendentes = $notificacoes_nao_lidas = 0;
    $ultimas_emendas = $ultimas_notificacoes = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SICEF</title>
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

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .stat-icon.primary {
            background: rgba(0, 121, 107, 0.1);
            color: var(--primary-color);
        }

        .stat-icon.success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .stat-icon.warning {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .stat-icon.info {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
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

            .stats-grid {
                grid-template-columns: 1fr;
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

        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .welcome-card h3 {
            margin-bottom: 0.5rem;
        }

        .welcome-card p {
            opacity: 0.9;
            margin-bottom: 1.5rem;
        }

        .quick-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .quick-actions .btn {
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
        }

        .notification-item {
            padding: 0.75rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: start;
            gap: 0.75rem;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item.unread {
            background-color: #f8f9fa;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 121, 107, 0.1);
            color: var(--primary-color);
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-time {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .emenda-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .emenda-item:last-child {
            border-bottom: none;
        }

        .emenda-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .emenda-meta {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .valor-badge {
            background: rgba(0, 121, 107, 0.1);
            color: var(--primary-color);
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
    </style>
</head>

<body>
    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar melhorado -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4>SICEF</h4>
            <p><?= htmlspecialchars($_SESSION['user']['nome']) ?></p>
        </div>
        <nav class="sidebar-menu">
            <a href="user_dashboard.php" class="active">
                <span class="material-icons">dashboard</span>
                Dashboard
            </a>
            <a href="selecionar_emendas.php">
                <span class="material-icons">search</span>
                Buscar Emendas
            </a>
            <a href="minhas_emendas.php">
                <span class="material-icons">bookmark</span>
                Minhas Emendas
                <?php if ($total_minhas_emendas > 0): ?>
                    <span class="badge bg-primary"><?= $total_minhas_emendas ?></span>
                <?php endif; ?>
            </a>
            <a href="sugestoes_emenda.php">
                <span class="material-icons">lightbulb</span>
                Sugestões
                <?php if ($sugestoes_pendentes > 0): ?>
                    <span class="badge bg-warning"><?= $sugestoes_pendentes ?></span>
                <?php endif; ?>
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
            <h2>Dashboard</h2>
            <div class="user-info">
                <span class="material-icons">account_circle</span>
                <span>Olá, <?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
                <?php if ($notificacoes_nao_lidas > 0): ?>
                    <span class="badge bg-danger"><?= $notificacoes_nao_lidas ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <h3>Bem-vindo ao SICEF!</h3>
                <p>Sistema de Caderno de Emendas Federais - Gerencie suas emendas parlamentares de forma eficiente</p>
                <div class="quick-actions">
                    <a href="selecionar_emendas.php" class="btn btn-light">
                        <span class="material-icons me-1">search</span>
                        Buscar Emendas
                    </a>
                    <a href="minhas_emendas.php" class="btn btn-outline-light">
                        <span class="material-icons me-1">bookmark</span>
                        Ver Minhas Emendas
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <span class="material-icons">bookmark</span>
                    </div>
                    <div>
                        <h3><?= number_format($total_minhas_emendas) ?></h3>
                        <p>Emendas Selecionadas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <span class="material-icons">attach_money</span>
                    </div>
                    <div>
                        <h3>R$ <?= number_format($valor_total, 2, ',', '.') ?></h3>
                        <p>Valor Total</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <span class="material-icons">trending_up</span>
                    </div>
                    <div>
                        <h3>R$ <?= number_format($valor_destinado, 2, ',', '.') ?></h3>
                        <p>Valor Destinado</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <span class="material-icons">lightbulb</span>
                    </div>
                    <div>
                        <h3><?= number_format($total_sugestoes) ?></h3>
                        <p>Sugestões Enviadas</p>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="row">
                <!-- Últimas Emendas -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <span class="material-icons me-2">bookmark</span>
                            Últimas Emendas Selecionadas
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($ultimas_emendas)): ?>
                                <div class="text-center py-4">
                                    <span class="material-icons me-2"
                                        style="font-size: 3rem; color: #6c757d;">bookmark_border</span>
                                    <p class="text-muted">Você ainda não selecionou nenhuma emenda</p>
                                    <a href="selecionar_emendas.php" class="btn btn-primary">
                                        <span class="material-icons me-1">search</span>
                                        Buscar Emendas
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($ultimas_emendas as $emenda): ?>
                                    <div class="emenda-item">
                                        <div class="emenda-title">
                                            <?= htmlspecialchars(substr($emenda['objeto_intervencao'], 0, 100)) ?>...
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="emenda-meta">
                                                <strong>Tipo:</strong> <?= htmlspecialchars($emenda['tipo_emenda']) ?><br>
                                                <strong>Órgão:</strong>
                                                <?= htmlspecialchars(substr($emenda['orgao'], 0, 50)) ?>...
                                            </div>
                                            <div class="text-end">
                                                <div class="valor-badge">
                                                    R$ <?= number_format($emenda['valor'], 2, ',', '.') ?>
                                                </div>
                                                <small class="text-muted d-block mt-1">
                                                    <?= date('d/m/Y', strtotime($emenda['data_selecao'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="p-3 text-center">
                                    <a href="minhas_emendas.php" class="btn btn-outline-primary">
                                        Ver todas as emendas
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Notificações -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <span class="material-icons me-2">notifications</span>
                            Notificações
                            <?php if ($notificacoes_nao_lidas > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $notificacoes_nao_lidas ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($ultimas_notificacoes)): ?>
                                <div class="text-center py-4">
                                    <span class="material-icons me-2"
                                        style="font-size: 3rem; color: #6c757d;">notifications_none</span>
                                    <p class="text-muted">Nenhuma notificação</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($ultimas_notificacoes as $notificacao): ?>
                                    <div class="notification-item <?= !$notificacao['lida'] ? 'unread' : '' ?>">
                                        <div class="notification-icon">
                                            <span class="material-icons">
                                                <?php
                                                switch ($notificacao['tipo']) {
                                                    case 'resposta_sugestao':
                                                        echo 'lightbulb';
                                                        break;
                                                    case 'acesso_aprovado':
                                                        echo 'check_circle';
                                                        break;
                                                    default:
                                                        echo 'info';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="notification-content">
                                            <div><?= htmlspecialchars($notificacao['mensagem']) ?></div>
                                            <div class="notification-time">
                                                <?= date('d/m/Y H:i', strtotime($notificacao['criado_em'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ações Rápidas -->
            <div class="card">
                <div class="card-header">
                    <span class="material-icons me-2">flash_on</span>
                    Ações Rápidas
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="selecionar_emendas.php" class="btn btn-outline-primary w-100">
                                <span class="material-icons d-block mb-2">search</span>
                                Buscar Emendas
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="minhas_emendas.php" class="btn btn-outline-success w-100">
                                <span class="material-icons d-block mb-2">bookmark</span>
                                Minhas Emendas
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="sugestoes_emenda.php" class="btn btn-outline-warning w-100">
                                <span class="material-icons d-block mb-2">lightbulb</span>
                                Enviar Sugestão
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="minhas_emendas.php?export=pdf" class="btn btn-outline-info w-100">
                                <span class="material-icons d-block mb-2">download</span>
                                Exportar PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Menu toggle responsivo
        document.addEventListener('DOMContentLoaded', function () {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            menuToggle.addEventListener('click', function () {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });

            sidebarOverlay.addEventListener('click', function () {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            });

            // Fechar sidebar ao clicar em link (mobile)
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function () {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('show');
                        sidebarOverlay.classList.remove('show');
                    }
                });
            });
        });
    </script>
</body>

</html>