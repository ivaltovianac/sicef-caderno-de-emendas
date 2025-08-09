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
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Painel Admin CICEF</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Incluir Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Estilos específicos para relatórios */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
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
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
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
            
            .stats-grid {
                grid-template-columns: 1fr;
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
            <a href="relatorios.php" class="menu-item active">
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
            <h1>Relatórios e Estatísticas</h1>
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
                <h2>Relatórios e Estatísticas</h2>
                <p>Visualize dados e métricas do sistema</p>
            </section>
            
            <!-- Cards com estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><span class="material-icons">list_alt</span> Total de Emendas</h3>
                    <div class="stat-value"><?= $total_emendas ?></div>
                    <div class="stat-description">Emendas cadastradas no sistema</div>
                </div>
                
                <div class="stat-card">
                    <h3><span class="material-icons">people</span> Total de Usuários</h3>
                    <div class="stat-value"><?= $total_usuarios ?></div>
                    <div class="stat-description">Usuários cadastrados no sistema</div>
                </div>
                
                <div class="stat-card">
                    <h3><span class="material-icons">assessment</span> Emendas em <?= $ano_atual ?></h3>
                    <div class="stat-value"><?= $emendas_ano_atual ?></div>
                    <div class="stat-description">Emendas cadastradas este ano</div>
                </div>
            </div>
            
            <!-- Gráficos -->
            <div class="chart-container">
                <h3><span class="material-icons">pie_chart</span> Distribuição por Tipo de Emenda</h3>
                <div class="chart-wrapper">
                    <canvas id="tipoEmendaChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <h3><span class="material-icons">bar_chart</span> Top 5 Eixos Temáticos</h3>
                <div class="chart-wrapper">
                    <canvas id="eixosChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <h3><span class="material-icons">show_chart</span> Emendas por Ano</h3>
                <div class="chart-wrapper">
                    <canvas id="anosChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dados para os gráficos
        const tipoEmendaData = {
            labels: <?= json_encode(array_column($emendas_por_tipo, 'tipo_emenda')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($emendas_por_tipo, 'total')) ?>,
                backgroundColor: [
                    '#007b5e',
                    '#4db6ac',
                    '#ffc107',
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
                backgroundColor: '#007b5e',
                borderColor: '#005a46',
                borderWidth: 1
            }]
        };
        
        const anosData = {
            labels: <?= json_encode(array_column($emendas_por_ano, 'ano')) ?>,
            datasets: [{
                label: 'Emendas por Ano',
                data: <?= json_encode(array_column($emendas_por_ano, 'total')) ?>,
                backgroundColor: '#4db6ac',
                borderColor: '#3da89e',
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