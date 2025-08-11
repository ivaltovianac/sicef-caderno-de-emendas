<?php
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// Obter dados para relatórios
$emendas_por_tipo = $pdo->query("SELECT tipo_emenda, COUNT(*) as total FROM emendas GROUP BY tipo_emenda")->fetchAll(PDO::FETCH_ASSOC);
$emendas_por_eixo = $pdo->query("SELECT eixo_tematico, COUNT(*) as total FROM emendas GROUP BY eixo_tematico ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$emendas_por_ano = $pdo->query("SELECT EXTRACT(YEAR FROM criado_em) as ano, COUNT(*) as total FROM emendas GROUP BY ano ORDER BY ano DESC")->fetchAll(PDO::FETCH_ASSOC);

// Obter estatísticas gerais
$total_emendas = $pdo->query("SELECT COUNT(*) as total FROM emendas")->fetch()['total'];
$total_usuarios = $pdo->query("SELECT COUNT(*) as total FROM usuarios")->fetch()['total'];
$ano_atual = date('Y');
$emendas_ano_atual = $pdo->query("SELECT COUNT(*) as total FROM emendas WHERE EXTRACT(YEAR FROM criado_em) = $ano_atual")->fetch()['total'];

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
    <title>Relatórios - CICEF</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-description {
            color: #666;
            font-size: 0.9rem;
        }
        
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: 400px;
        }
        
        .chart-container h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .chart-wrapper {
            height: 350px;
            position: relative;
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
            
            .stats-grid {
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
                <a href="relatorios.php" class="active">
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
                    <h1>Relatórios e Estatísticas</h1>
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
                <!-- Cards com estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><i class="material-icons">list_alt</i> Total de Emendas</h3>
                        <div class="stat-value"><?= $total_emendas ?></div>
                        <div class="stat-description">Emendas cadastradas no sistema</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><i class="material-icons">people</i> Total de Usuários</h3>
                        <div class="stat-value"><?= $total_usuarios ?></div>
                        <div class="stat-description">Usuários cadastrados no sistema</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><i class="material-icons">assessment</i> Emendas em <?= $ano_atual ?></h3>
                        <div class="stat-value"><?= $emendas_ano_atual ?></div>
                        <div class="stat-description">Emendas cadastradas este ano</div>
                    </div>
                </div>
                
                <!-- Gráficos -->
                <div class="chart-container">
                    <h3><i class="material-icons">pie_chart</i> Distribuição por Tipo de Emenda</h3>
                    <div class="chart-wrapper">
                        <canvas id="tipoEmendaChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container">
                    <h3><i class="material-icons">bar_chart</i> Top 5 Eixos Temáticos</h3>
                    <div class="chart-wrapper">
                        <canvas id="eixosChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container">
                    <h3><i class="material-icons">show_chart</i> Emendas por Ano</h3>
                    <div class="chart-wrapper">
                        <canvas id="anosChart"></canvas>
                    </div>
                </div>
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

        // Dados para os gráficos
        const tipoEmendaData = {
            labels: <?= json_encode(array_column($emendas_por_tipo, 'tipo_emenda')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($emendas_por_tipo, 'total')) ?>,
                backgroundColor: [
                    '<?= $user_colors['primary'] ?>',
                    '<?= $user_colors['secondary'] ?>',
                    '<?= $user_colors['accent'] ?>',
                    '#dc3545',
                    '#6c757d'
                ]
            }]
        };
        
        const eixosData = {
            labels: <?= json_encode(array_column($emendas_por_eixo, 'eixo_tematico')) ?>,
            datasets: [{
                label: 'Número de Emendas',
                data: <?= json_encode(array_column($emendas_por_eixo, 'total')) ?>,
                backgroundColor: '<?= $user_colors['primary'] ?>',
                borderColor: '<?= $user_colors['secondary'] ?>',
                borderWidth: 1
            }]
        };
        
        const anosData = {
            labels: <?= json_encode(array_column($emendas_por_ano, 'ano')) ?>,
            datasets: [{
                label: 'Emendas por Ano',
                data: <?= json_encode(array_column($emendas_por_ano, 'total')) ?>,
                backgroundColor: '<?= $user_colors['secondary'] ?>',
                borderColor: '<?= $user_colors['primary'] ?>',
                borderWidth: 1,
                fill: true
            }]
        };
        
        // Inicializar gráficos
        window.onload = function() {
            // Gráfico de pizza para tipos de emenda
            new Chart(document.getElementById('tipoEmendaChart'), {
                type: 'pie',
                data: tipoEmendaData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
            
            // Gráfico de barras para eixos temáticos
            new Chart(document.getElementById('eixosChart'), {
                type: 'bar',
                data: eixosData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Gráfico de linha para emendas por ano
            new Chart(document.getElementById('anosChart'), {
                type: 'line',
                data: anosData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        };

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

