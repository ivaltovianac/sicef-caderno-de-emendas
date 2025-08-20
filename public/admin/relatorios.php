<?php
// SICEF-caderno-de-emendas/public/admin/relatorios.php
session_start();
if (!isset($_SESSION["user"]) || !$_SESSION["user"]["is_admin"]) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Contadores para badges
$stmt_solicitacoes = $pdo->query("SELECT COUNT(*) as total FROM solicitacoes_acesso WHERE status = 'pendente'");
$solicitacoes_pendentes = $stmt_solicitacoes->fetch()['total'];

$stmt_sugestoes = $pdo->query("SELECT COUNT(*) FROM sugestoes_emendas WHERE status = 'pendente'");
$qtde_sugestoes_pendentes = $stmt_sugestoes->fetchColumn();

// Carregar dados para relatórios
try {
    // Estatísticas gerais
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM emendas");
    $stmt->execute();
    $total_emendas = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios");
    $stmt->execute();
    $total_usuarios = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sugestoes_emendas WHERE status = 'pendente'");
    $stmt->execute();
    $sugestoes_pendentes = $stmt->fetchColumn();

    // Emendas por ano
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM emendas WHERE EXTRACT(YEAR FROM criado_em) = ?");
    $stmt->execute([date('Y')]);
    $emendas_ano_atual = $stmt->fetchColumn();

    // Emendas por tipo
    $stmt = $pdo->prepare("SELECT tipo_emenda, COUNT(*) as total FROM emendas WHERE tipo_emenda IS NOT NULL GROUP BY tipo_emenda ORDER BY total DESC");
    $stmt->execute();
    $emendas_por_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top eixos temáticos
    $stmt = $pdo->prepare("SELECT eixo_tematico, COUNT(*) as total FROM emendas WHERE eixo_tematico IS NOT NULL GROUP BY eixo_tematico ORDER BY total DESC LIMIT 10");
    $stmt->execute();
    $top_eixos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Emendas por ano (histórico)
    $stmt = $pdo->prepare("SELECT EXTRACT(YEAR FROM criado_em) as ano, COUNT(*) as total FROM emendas GROUP BY ano ORDER BY ano DESC LIMIT 5");
    $stmt->execute();
    $emendas_por_ano = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top órgãos
    $stmt = $pdo->prepare("SELECT orgao, COUNT(*) as total FROM emendas WHERE orgao IS NOT NULL GROUP BY orgao ORDER BY total DESC LIMIT 8");
    $stmt->execute();
    $top_orgaos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Valores
    $stmt = $pdo->prepare("SELECT SUM(valor) as total_valor FROM emendas WHERE valor IS NOT NULL");
    $stmt->execute();
    $total_valor = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->prepare("SELECT AVG(valor) as media_valor FROM emendas WHERE valor IS NOT NULL AND valor > 0");
    $stmt->execute();
    $media_valor = $stmt->fetchColumn() ?: 0;

} catch (PDOException $e) {
    error_log("Erro ao carregar dados dos relatórios: " . $e->getMessage());
    $total_emendas = $total_usuarios = $sugestoes_pendentes = $emendas_ano_atual = 0;
    $emendas_por_tipo = $top_eixos = $emendas_por_ano = $top_orgaos = [];
    $total_valor = $media_valor = 0;
}

// Processar exportação
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (isset($_GET['export'])) {
    try {
        // Evita qualquer saída anterior que possa corromper o arquivo
        if (ob_get_length()) {
            ob_end_clean();
        }

        $tipo_export = $_GET['export'];
        $formato = $_GET['formato'] ?? 'excel';

        switch ($tipo_export) {
            case 'emendas':
                $stmt = $pdo->prepare("SELECT * FROM emendas ORDER BY criado_em DESC");
                $stmt->execute();
                $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $filename = 'relatorio_emendas_' . date('Y-m-d');
                $headers = ['ID', 'Tipo', 'Eixo Temático', 'Órgão', 'Objeto', 'ODS', 'Valor', 'Justificativa', 'Regionalização', 'Unidade Orçamentária', 'Programa', 'Ação', 'Categoria Econômica', 'Criado em'];
                break;

            case 'usuarios':
                $stmt = $pdo->prepare("SELECT id, nome, email, tipo, is_admin, is_user, criado_em FROM usuarios ORDER BY nome");
                $stmt->execute();
                $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $filename = 'relatorio_usuarios_' . date('Y-m-d');
                $headers = ['ID', 'Nome', 'E-mail', 'Tipo', 'Admin', 'Ativo', 'Criado em'];
                break;

            case 'sugestoes':
                $stmt = $pdo->prepare("
                    SELECT s.id, u.nome as usuario, e.objeto_intervencao, s.campo_sugerido, 
                           s.valor_sugerido, s.status, s.criado_em, s.respondido_em
                    FROM sugestoes_emendas s 
                    JOIN usuarios u ON s.usuario_id = u.id 
                    JOIN emendas e ON s.emenda_id = e.id 
                    ORDER BY s.criado_em DESC
                ");
                $stmt->execute();
                $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $filename = 'relatorio_sugestoes_' . date('Y-m-d');
                $headers = ['ID', 'Usuário', 'Emenda', 'Campo', 'Valor Sugerido', 'Status', 'Criado em', 'Respondido em'];
                break;

            default:
                throw new Exception('Tipo de relatório inválido');
        }

        if ($formato === 'excel') {
            // Função para truncar texto longo
            function limitarTexto($texto, $limite = 1000)
            {
                return mb_substr(trim((string) $texto), 0, $limite);
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Cabeçalhos
            $sheet->fromArray($headers, null, 'A1');

            // Estilo para cabeçalhos
            $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F81BD'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);

            // Preenche os dados
            $row = 2;
            foreach ($dados as $linha) {
                $sheet->fromArray(array_map(fn($v) => limitarTexto($v), array_values($linha)), null, "A{$row}");
                $row++;
            }

            // Estilo para dados
            $sheet->getStyle("A2:{$lastColumn}{$row}")->applyFromArray([
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ]);

            // Ajusta largura das colunas
            for ($col = 1; $col <= count($headers); $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }

            // Congela a primeira linha
            $sheet->freezePane('A2');

            // Adiciona filtro automático
            $sheet->setAutoFilter("A1:{$lastColumn}1");

            // Gera e envia o arquivo Excel
            $writer = new Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment;filename=\"{$filename}.xlsx\"");
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
            exit;
        }

    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao gerar relatório: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - SICEF Admin</title>
    <!-- Bootstrap e Material Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            background: linear-gradient(350deg, rgba(42, 123, 155, 1) 0%, rgba(87, 199, 133, 1) 58%, rgba(237, 221, 83, 1) 100%);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .stat-card h3 {
            font-size: 1.2rem;
            color: #fff;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: #f0f0f0;
            font-size: 0.95rem;
        }

        @media (max-width: 748px) {
            .stat-card {
                padding: 1rem;
            }
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
            color: var(--accent-color);
        }

        .stat-icon.success {
            background: #1987543d;
            color: var(--accent-color);
        }

        .stat-icon.info {
            background: #1987543d;
            color: var(--accent-color);
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

        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 2rem;
        }

        .export-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .export-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            transition: border-color 0.3s;
        }

        .export-card:hover {
            border-color: var(--primary-color);
        }

        .export-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
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
            <a href="relatorios.php" class="active">
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
            <h2>Relatórios e Estatísticas</h2>
            <div class="user-info">
                <span class="material-icons">account_circle</span>
                <span>Olá, <?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <span class="material-icons me-2">error</span>
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <span class="material-icons">description</span>
                    </div>
                    <div>
                        <h3><?= number_format($total_emendas) ?></h3>
                        <p>Total de Emendas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <span class="material-icons">people</span>
                    </div>
                    <div>
                        <h3><?= number_format($total_usuarios) ?></h3>
                        <p>Usuários Cadastrados</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <span class="material-icons">lightbulb</span>
                    </div>
                    <div>
                        <h3><?= number_format($sugestoes_pendentes) ?></h3>
                        <p>Sugestões Pendentes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <span class="material-icons">calendar_today</span>
                    </div>
                    <div>
                        <h3><?= number_format($emendas_ano_atual) ?></h3>
                        <p>Emendas em <?= date('Y') ?></p>
                    </div>
                </div>
            </div>

            <!-- Valores -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <span class="material-icons">attach_money</span>
                        </div>
                        <div>
                            <h3>R$ <?= number_format($total_valor, 2, ',', '.') ?></h3>
                            <p>Valor Total das Emendas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <span class="material-icons">trending_up</span>
                        </div>
                        <div>
                            <h3>R$ <?= number_format($media_valor, 2, ',', '.') ?></h3>
                            <p>Valor Médio por Emenda</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <span class="material-icons me-2">pie_chart</span>
                            Emendas por Tipo
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="tipoChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <span class="material-icons me-2">bar_chart</span>
                            Emendas por Ano
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="anoChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Eixos Temáticos -->
            <div class="card">
                <div class="card-header">
                    <span class="material-icons me-2">category</span>
                    Top 10 Eixos Temáticos
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="eixosChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Órgãos -->
            <div class="card">
                <div class="card-header">
                    <span class="material-icons me-2">business</span>
                    Top 8 Órgãos
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="orgaosChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Exportação de Relatórios -->
            <div class="export-section">
                <h5><span class="material-icons me-2">download</span>Exportar Relatórios</h5>
                <div class="export-grid">
                    <div class="export-card">
                        <div class="export-icon">
                            <span class="material-icons">description</span>
                        </div>
                        <h6>Relatório de Emendas</h6>
                        <p>Exportar todas as emendas cadastradas no sistema</p>
                        <a href="?export=emendas&formato=excel" class="btn btn-primary">
                            <span class="material-icons me-1">download</span>
                            Baixar Excel
                        </a>
                    </div>
                    <div class="export-card">
                        <div class="export-icon">
                            <span class="material-icons">people</span>
                        </div>
                        <h6>Relatório de Usuários</h6>
                        <p>Exportar lista completa de usuários do sistema</p>
                        <a href="?export=usuarios&formato=excel" class="btn btn-primary">
                            <span class="material-icons me-1">download</span>
                            Baixar Excel
                        </a>
                    </div>
                    <div class="export-card">
                        <div class="export-icon">
                            <span class="material-icons">lightbulb</span>
                        </div>
                        <h6>Relatório de Sugestões</h6>
                        <p>Exportar histórico de sugestões dos usuários</p>
                        <a href="?export=sugestoes&formato=excel" class="btn btn-primary">
                            <span class="material-icons me-1">download</span>
                            Baixar Excel
                        </a>
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

            // Inicializar gráficos
            initCharts();
        });

        function initCharts() {
            // Gráfico de Emendas por Tipo
            const tipoCtx = document.getElementById('tipoChart').getContext('2d');
            new Chart(tipoCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($emendas_por_tipo, 'tipo_emenda')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($emendas_por_tipo, 'total')) ?>,
                        backgroundColor: [
                            '#00796B', '#009688', '#4DB6AC', '#80CBC4', '#B2DFDB',
                            '#FFC107', '#FF9800', '#FF5722', '#E91E63', '#9C27B0'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Gráfico de Emendas por Ano
            const anoCtx = document.getElementById('anoChart').getContext('2d');
            new Chart(anoCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($emendas_por_ano, 'ano')) ?>,
                    datasets: [{
                        label: 'Emendas',
                        data: <?= json_encode(array_column($emendas_por_ano, 'total')) ?>,
                        backgroundColor: '#00796B',
                        borderColor: '#004D40',
                        borderWidth: 1
                    }]
                },
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

            // Gráfico de Top Eixos Temáticos
            const eixosCtx = document.getElementById('eixosChart').getContext('2d');
            new Chart(eixosCtx, {
                type: 'bar', // Chart.js v3+ usa 'bar' com indexAxis: 'y'
                data: {
                    labels: <?= json_encode(array_map(function ($item) {
                        return mb_substr($item['eixo_tematico'], 0, 30) . '...';
                    }, $top_eixos)) ?>,
                    datasets: [{
                        label: 'Emendas',
                        data: <?= json_encode(array_column($top_eixos, 'total')) ?>,
                        backgroundColor: '#009688',
                        borderColor: '#00796B',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y', // Isso transforma o gráfico em horizontal
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        },
                        y: {
                            ticks: {
                                autoSkip: false,
                                maxRotation: 0,
                                minRotation: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return `Total: ${context.parsed.x}`;
                                }
                            }
                        }
                    }
                }
            });

            // Gráfico de Top Órgãos
            const orgaosCtx = document.getElementById('orgaosChart').getContext('2d');
            new Chart(orgaosCtx, {
                type: 'bar', // Chart.js v3+ usa 'bar' com indexAxis: 'y'
                data: {
                    labels: <?= json_encode(array_map(function ($item) {
                        return mb_substr($item['orgao'], 0, 40) . '...';
                    }, $top_orgaos)) ?>,
                    datasets: [{
                        label: 'Emendas',
                        data: <?= json_encode(array_column($top_orgaos, 'total')) ?>,
                        backgroundColor: '#4DB6AC',
                        borderColor: '#00796B',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y', // transforma em gráfico horizontal
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        },
                        y: {
                            ticks: {
                                autoSkip: false,
                                maxRotation: 0,
                                minRotation: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return `Total de emendas: ${context.parsed.x}`;
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>