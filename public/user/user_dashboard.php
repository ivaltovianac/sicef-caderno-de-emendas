<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Função para verificar tabelas
function verificarTabela($pdo, $tabela) {
    try {
        $stmt = $pdo->query("SELECT 1 FROM $tabela LIMIT 1");
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao verificar tabela $tabela: " . $e->getMessage());
        return false;
    }
}

// Verificar tabelas necessárias
$tabelasNecessarias = ['usuarios', 'emendas', 'usuario_emendas'];
$tabelasFaltantes = [];

foreach ($tabelasNecessarias as $tabela) {
    if (!verificarTabela($pdo, $tabela)) {
        $tabelasFaltantes[] = $tabela;
    }
}

if (!empty($tabelasFaltantes)) {
    $tabelasList = implode(', ', $tabelasFaltantes);
    die("<div style='padding: 20px; background: #f8d7da; color: #721c24; border-radius: 5px;'>
        <h3>Erro no Banco de Dados</h3>
        <p>As seguintes tabelas não foram encontradas ou estão inacessíveis: <strong>$tabelasList</strong></p>
        <p>Por favor, verifique:</p>
        <ol>
            <li>Se as tabelas existem no banco de dados</li>
            <li>Se as permissões estão corretas</li>
            <li>Se os nomes das tabelas estão exatamente como: $tabelasList</li>
        </ol>
    </div>");
}

// Inicializar variáveis
$minhas_emendas = [];
$emendas_filtradas = [];
$tipos_emenda = [];
$eixos_tematicos = [];
$unidades = [];
$ods_values = [];

try {
    $usuario_id = $_SESSION['user']['id'];
    
    // Consulta principal
    $sql = "SELECT e.* FROM emendas e
            JOIN usuario_emendas ue ON e.id = ue.emenda_id
            WHERE ue.usuario_id = ?
            ORDER BY e.criado_em DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $minhas_emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Inicializar emendas_filtradas com todas as emendas inicialmente
    $emendas_filtradas = $minhas_emendas;
    
    // Obter valores distintos para filtros
    if (!empty($minhas_emendas)) {
        // Obter tipos de emenda
        $stmt = $pdo->prepare("SELECT DISTINCT tipo_emenda FROM emendas e
                              JOIN usuario_emendas ue ON e.id = ue.emenda_id 
                              WHERE ue.usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $tipos_emenda = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Obter eixos temáticos
        $stmt = $pdo->prepare("SELECT DISTINCT eixo_tematico FROM emendas e
                               JOIN usuario_emendas ue ON e.id = ue.emenda_id 
                               WHERE ue.usuario_id = ? 
                               ORDER BY eixo_tematico");
        $stmt->execute([$usuario_id]);
        $eixos_tematicos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Obter unidades
        $stmt = $pdo->prepare("SELECT DISTINCT orgao FROM emendas e
                              JOIN usuario_emendas ue ON e.id = ue.emenda_id 
                              WHERE ue.usuario_id = ? 
                              ORDER BY orgao");
        $stmt->execute([$usuario_id]);
        $unidades = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Obter ODS
        $stmt = $pdo->prepare("SELECT DISTINCT ods FROM emendas e
                              JOIN usuario_emendas ue ON e.id = ue.emenda_id 
                              WHERE ue.usuario_id = ? 
                              ORDER BY ods");
        $stmt->execute([$usuario_id]);
        $ods_values = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

} catch (PDOException $e) {
    die("<div style='padding: 20px; background: #f8d7da; color: #721c24; border-radius: 5px;'>
        <h3>Erro na Consulta</h3>
        <p>Ocorreu um erro ao acessar os dados.</p>
        <p>Detalhes técnicos: " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Consulta executada: <code>" . htmlspecialchars(str_replace('?', "'{$usuario_id}'", $sql)) . "</code></p>
    </div>");
}

// Função para renderizar seções de filtro
function renderFilterSection($title, $filters, $currentValues) {
    echo '<div class="filter-section">';
    echo '<h3><span class="material-icons">filter_alt</span> '.htmlspecialchars($title).'</h3>';
    
    foreach ($filters as $filter) {
        echo '<div class="filter-group">';
        echo '<label for="'.htmlspecialchars($filter['name']).'">'.htmlspecialchars($filter['label']).'</label>';
        
        if ($filter['type'] === 'select') {
            echo '<select id="'.htmlspecialchars($filter['name']).'" name="'.htmlspecialchars($filter['name']).'" class="filter-control">';
            echo '<option value="">Selecione</option>';
            
            foreach ($filter['options'] as $value => $label) {
                $selected = isset($currentValues[$filter['name']]) && $currentValues[$filter['name']] === $value ? 'selected' : '';
                echo '<option value="'.htmlspecialchars($value).'" '.$selected.'>'.htmlspecialchars($label).'</option>';
            }
            
            echo '</select>';
        } elseif ($filter['type'] === 'radio') {
            echo '<div class="radio-group">';
            
            foreach ($filter['options'] as $value => $label) {
                $checked = isset($currentValues[$filter['name']]) && $currentValues[$filter['name']] === $value ? 'checked' : '';
                echo '<label class="radio-option">';
                echo '<input type="radio" name="'.htmlspecialchars($filter['name']).'" value="'.htmlspecialchars($value).'" '.$checked.'>';
                echo htmlspecialchars($label);
                echo '</label>';
            }
            
            echo '</div>';
        } elseif ($filter['type'] === 'range') {
            echo '<div class="range-group">';
            echo '<input type="number" id="'.htmlspecialchars($filter['name'].'_de').'" name="'.htmlspecialchars($filter['name'].'_de').'" ';
            echo 'class="filter-control" value="'.htmlspecialchars($currentValues[$filter['name'].'_de'] ?? '').'" ';
            echo 'placeholder="De" min="'.htmlspecialchars($filter['min'] ?? '').'">';
            
            echo '<input type="number" id="'.htmlspecialchars($filter['name'].'_ate').'" name="'.htmlspecialchars($filter['name'].'_ate').'" ';
            echo 'class="filter-control" value="'.htmlspecialchars($currentValues[$filter['name'].'_ate'] ?? '').'" ';
            echo 'placeholder="Até" min="'.htmlspecialchars($filter['min'] ?? '').'">';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    echo '</div>';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Emendas - CICEF</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
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
            border-left: 3px solid var(--secondary-color);
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            display: flex;
            align-items: center;
            font-size: 1rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .stat-card .material-icons {
            margin-right: 0.5rem;
            color: var(--secondary-color);
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
        
        /* Tabs */
        .tab-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            color: #7f8c8d;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Filtros */
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .filter-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .filter-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .filter-section h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .filter-group {
            margin-bottom: 1rem;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
            font-size: 0.9rem;
        }
        
        .filter-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: white;
        }
        
        .filter-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(77, 182, 172, 0.2);
            outline: none;
        }
        
        .radio-group {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .radio-option input[type="radio"] {
            accent-color: var(--secondary-color);
        }
        
        .range-group {
            display: flex;
            gap: 1rem;
        }
        
        .range-group .filter-control {
            flex: 1;
        }
        
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        /* Filtros Aplicados */
        .applied-filters {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e0e0e0;
        }
        
        .applied-filters h4 {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .filter-tag {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .remove-filter {
            color: #6c757d;
            text-decoration: none;
            display: inline-flex;
            margin-left: 0.25rem;
        }
        
        .remove-filter .material-icons {
            font-size: 1rem;
        }
        
        .remove-filter:hover {
            color: #dc3545;
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
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3da89e;
            transform: translateY(-2px);
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        
        /* Export Section */
        .export-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .export-title {
            font-size: 1.1rem;
            color: var(--primary-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .export-actions {
            display: flex;
            gap: 1rem;
        }
        
        .export-btn {
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
            text-decoration: none;
        }
        
        .export-excel {
            background-color: #1d6f42;
            color: white;
        }
        
        .export-pdf {
            background-color: #d32f2f;
            color: white;
        }
        
        .export-excel:hover {
            background-color: #165a36;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .export-pdf:hover {
            background-color: #b71c1c;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        /* Table */
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
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
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .range-group {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .export-section {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .export-actions {
                width: 100%;
                flex-direction: column;
            }
            
            .export-btn {
                width: 100%;
                justify-content: center;
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
                <span class="material-icons">account_circle</span>
                Meu Painel
            </h2>
        </div>
        <div class="sidebar-menu">
            <a href="user_dashboard.php" class="menu-item active">
                <span class="material-icons">list_alt</span>
                Minhas Emendas
            </a>
            <a href="selecionar_emendas.php" class="menu-item">
                <span class="material-icons">add_circle</span>
                Selecionar Emendas
            </a>
            <a href="meu_perfil.php" class="menu-item">
                <span class="material-icons">person</span>
                Meu Perfil
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
            <h1>Minhas Emendas</h1>
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
                <h2>Olá, <?= htmlspecialchars($_SESSION['user']['nome']) ?>!</h2>
                <p>Aqui você pode visualizar e gerenciar suas emendas selecionadas</p>
            </section>
            
            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><span class="material-icons">list_alt</span> Total de Emendas</h3>
                    <div class="stat-value"><?= count($minhas_emendas) ?></div>
                    <div class="stat-description">Emendas selecionadas</div>
                </div>
                
                <div class="stat-card">
                    <h3><span class="material-icons">category</span> Tipos Diferentes</h3>
                    <div class="stat-value"><?= count($tipos_emenda) ?></div>
                    <div class="stat-description">Tipos de emendas</div>
                </div>
                
                <div class="stat-card">
                    <h3><span class="material-icons">assessment</span> Eixos Diferentes</h3>
                    <div class="stat-value"><?= count($eixos_tematicos) ?></div>
                    <div class="stat-description">Eixos temáticos</div>
                </div>
            </div>
            
            <!-- Abas para navegação -->
            <div class="tab-container">
                <div class="tabs">
                    <div class="tab active" data-tab="todas">Todas as Emendas</div>
                    <div class="tab" data-tab="filtros">Filtrar Emendas</div>
                </div>
                
                <!-- Conteúdo da aba Todas as Emendas -->
                <div class="tab-content active" id="todas">
                    <?php if (!empty($minhas_emendas)): ?>
                    <div class="export-section">
                        <div class="export-title">
                            <span class="material-icons">description</span>
                            Exportar Minhas Emendas
                        </div>
                        <div class="export-actions">
                            <a href="?export=excel" class="export-btn export-excel">
                                <span class="material-icons">description</span>
                                Exportar para Excel
                            </a>
                            <a href="?export=pdf" class="export-btn export-pdf">
                                <span class="material-icons">picture_as_pdf</span>
                                Exportar para PDF
                            </a>
                        </div>
                    </div>
                    
                    <table class="emendas-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Eixo Temático</th>
                                <th>Unidade</th>
                                <th>Objeto</th>
                                <th>ODS</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($minhas_emendas as $emenda): ?>
                            <tr>
                                <td><?= htmlspecialchars($emenda['tipo_emenda']) ?></td>
                                <td><?= htmlspecialchars($emenda['eixo_tematico']) ?></td>
                                <td><?= htmlspecialchars($emenda['orgao']) ?></td>
                                <td><?= htmlspecialchars(substr($emenda['objeto_intervencao'], 0, 50)) ?><?= strlen($emenda['objeto_intervencao']) > 50 ? '...' : '' ?></td>
                                <td><?= htmlspecialchars($emenda['ods'] ?? '-') ?></td>
                                <td><?= date('d/m/Y', strtotime($emenda['criado_em'])) ?></td>
                                <td class="actions-cell">
                                    <a href="visualizar_emenda.php?id=<?= $emenda['id'] ?>" class="action-link">
                                        <span class="material-icons" style="font-size: 1.1rem;">visibility</span>
                                        Visualizar
                                    </a>
                                    <form method="POST" action="remover_emenda.php" style="display: inline;">
                                        <input type="hidden" name="emenda_id" value="<?= $emenda['id'] ?>">
                                        <button type="submit" class="action-link danger" onclick="return confirm('Tem certeza que deseja remover esta emenda da sua lista?')">
                                            <span class="material-icons" style="font-size: 1.1rem;">delete</span>
                                            Remover
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <h3>Nenhuma emenda selecionada</h3>
                        <p>Você ainda não selecionou nenhuma emenda. <a href="selecionar_emendas.php">Clique aqui</a> para começar.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Conteúdo da aba Filtrar Emendas -->
                <div class="tab-content" id="filtros">
                    <div class="filters-card">
                        <form method="GET" action="user_dashboard.php">
                            <?php
                            $currentValues = $_GET;
                            
                            // Tipo de Emenda
                            renderFilterSection('Tipo de Emenda', [
                                [
                                    'name' => 'tipo_caderno',
                                    'label' => 'Selecione o tipo',
                                    'type' => 'radio',
                                    'options' => array_combine($tipos_emenda, $tipos_emenda)
                                ]
                            ], $currentValues);
                            
                            // Filtros Avançados
                            renderFilterSection('Filtrar por', [
                                [
                                    'name' => 'eixo_tematico',
                                    'label' => 'Eixo Temático',
                                    'type' => 'select',
                                    'options' => array_combine($eixos_tematicos, $eixos_tematicos)
                                ],
                                [
                                    'name' => 'ods',
                                    'label' => 'ODS',
                                    'type' => 'select',
                                    'options' => array_combine($ods_values, $ods_values)
                                ],
                                [
                                    'name' => 'unidade_responsavel',
                                    'label' => 'Unidade Responsável',
                                    'type' => 'select',
                                    'options' => array_combine($unidades, $unidades)
                                ]
                            ], $currentValues);
                            ?>
                            
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    <span class="material-icons">filter_alt</span>
                                    Aplicar Filtros
                                </button>
                                <a href="user_dashboard.php" class="btn btn-secondary">
                                    <span class="material-icons">clear</span>
                                    Limpar Filtros
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <?php if (!empty($_GET)): ?>
                    <div class="applied-filters">
                        <h4>Filtros Aplicados:</h4>
                        <div class="filter-tags">
                            <?php foreach ($_GET as $key => $value): ?>
                                <?php if (!empty($value) && !in_array($key, ['export', 'page'])): ?>
                                    <span class="filter-tag">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $key))) ?>: 
                                        <?= htmlspecialchars($value) ?>
                                        <a href="?" class="remove-filter">
                                            <?php 
                                            $newGet = $_GET;
                                            unset($newGet[$key]);
                                            $queryString = http_build_query($newGet);
                                            ?>
                                            <span class="material-icons">close</span>
                                        </a>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($emendas_filtradas)): ?>
                    <table class="emendas-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Eixo Temático</th>
                                <th>Unidade</th>
                                <th>Objeto</th>
                                <th>ODS</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emendas_filtradas as $emenda): ?>
                            <tr>
                                <td><?= htmlspecialchars($emenda['tipo_emenda']) ?></td>
                                <td><?= htmlspecialchars($emenda['eixo_tematico']) ?></td>
                                <td><?= htmlspecialchars($emenda['orgao']) ?></td>
                                <td><?= htmlspecialchars(substr($emenda['objeto_intervencao'], 0, 50)) ?><?= strlen($emenda['objeto_intervencao']) > 50 ? '...' : '' ?></td>
                                <td><?= htmlspecialchars($emenda['ods'] ?? '-') ?></td>
                                <td><?= date('d/m/Y', strtotime($emenda['criado_em'])) ?></td>
                                <td class="actions-cell">
                                    <a href="visualizar_emenda.php?id=<?= $emenda['id'] ?>" class="action-link">
                                        <span class="material-icons" style="font-size: 1.1rem;">visibility</span>
                                        Visualizar
                                    </a>
                                    <form method="POST" action="remover_emenda.php" style="display: inline;">
                                        <input type="hidden" name="emenda_id" value="<?= $emenda['id'] ?>">
                                        <button type="submit" class="action-link danger" onclick="return confirm('Tem certeza que deseja remover esta emenda da sua lista?')">
                                            <span class="material-icons" style="font-size: 1.1rem;">delete</span>
                                            Remover
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <h3>Nenhuma emenda encontrada</h3>
                        <p>Não há resultados com os filtros selecionados.</p>
                    </div>
                    <?php endif; ?>
                </div>
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
        
        // Controle das abas
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remover classe active de todas as abas e conteúdos
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Adicionar classe active à aba clicada e ao conteúdo correspondente
                tab.classList.add('active');
                const tabId = tab.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Validação dos filtros de intervalo
        const rangeInputs = document.querySelectorAll('.range-group input');
        rangeInputs.forEach(input => {
            input.addEventListener('change', function() {
                const minInput = document.getElementById(this.name.replace('_ate', '_de'));
                const maxInput = document.getElementById(this.name.replace('_de', '_ate'));
                
                if (minInput && maxInput && minInput.value && maxInput.value && parseFloat(minInput.value) > parseFloat(maxInput.value)) {
                    alert('O valor "De" deve ser menor que o valor "Até"');
                    this.value = '';
                }
            });
        });
    </script>
</body>
</html>