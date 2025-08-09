<?php
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/sincronizador.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Processar sincronização se solicitado
if (isset($_GET['sincronizar'])) {
    $sincronizador = new SincronizadorEmendas(
        $pdo, 
        __DIR__ . '/../../uploads/Cadernos_DeEmendas_.xlsx'
    );
    $resultado = $sincronizador->sincronizar();
    $_SESSION['mensagem_sincronizacao'] = $resultado['message'];
    header('Location: admin_dashboard.php');
    exit;
}

if (!class_exists('TCPDF')) {
    die('TCPDF não está instalado. Por favor, instale via composer: composer require tecnickcom/tcpdf');
}

// Função para ler a planilha Excel
function lerPlanilhaEmendas($caminhoArquivo) {
    try {
        if (!file_exists($caminhoArquivo)) {
            throw new Exception("Arquivo não encontrado: " . $caminhoArquivo);
        }

        $spreadsheet = IOFactory::load($caminhoArquivo);
        $sheet = $spreadsheet->getActiveSheet();
        $emendas = [];
        
        foreach ($sheet->getRowIterator(2) as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }
            
            if (!empty($cells[0])) {
                // Converter valor monetário para float
                $valor = $cells[10] ?? '0';
                if (is_string($valor)) {
                    $valor = str_replace(['.', ','], ['', '.'], $valor);
                }
                $valorFormatado = number_format((float)$valor, 2, ',', '.');
                
                $emendas[] = [
                    'tipo' => $cells[0] ?? '',
                    'eixo' => $cells[1] ?? '',
                    'orgao' => $cells[2] ?? '',
                    'objeto' => $cells[3] ?? '',
                    'ods' => $cells[4] ?? '',
                    'valor' => $valorFormatado,
                    'justificativa' => $cells[11] ?? ''
                ];
            }
        }
        
        return $emendas;
    } catch (Exception $e) {
        error_log("Erro ao ler planilha: " . $e->getMessage());
        return [];
    }
}

// Processar filtros
$where = [];
$params = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['tipo_caderno'])) {
        $where[] = "tipo_emenda = ?";
        $params[] = $_GET['tipo_caderno'];
    }
    if (!empty($_GET['eixo_tematico']) && $_GET['eixo_tematico'] !== 'Selecione') {
        $where[] = "eixo_tematico = ?";
        $params[] = $_GET['eixo_tematico'];
    }
    if (!empty($_GET['unidade_responsavel']) && $_GET['unidade_responsavel'] !== 'Selecione') {
        $where[] = "orgao = ?";
        $params[] = $_GET['unidade_responsavel'];
    }
    if (!empty($_GET['pontuacao_de'])) {
        $where[] = "pontuacao >= ?";
        $params[] = $_GET['pontuacao_de'];
    }
    if (!empty($_GET['pontuacao_ate'])) {
        $where[] = "pontuacao <= ?";
        $params[] = $_GET['pontuacao_ate'];
    }
    if (!empty($_GET['ods']) && $_GET['ods'] !== 'Selecione') {
        $where[] = "ods = ?";
        $params[] = $_GET['ods'];
    }
    if (!empty($_GET['ano_projeto'])) {
        $where[] = "EXTRACT(YEAR FROM criado_em) = ?";
        $params[] = $_GET['ano_projeto'];
    }
    if (!empty($_GET['outros_recursos']) && isset($_GET['tipo_caderno']) && $_GET['tipo_caderno'] === 'OPERAÇÃO DE CRÉDITO') {
        $where[] = "outros_recursos = ?";
        $params[] = 1;
    }

    // Processar exportação
    if (isset($_GET['export'])) {
        $export_type = $_GET['export'];
        $sql = "SELECT * FROM emendas" . (!empty($where) ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY criado_em DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($export_type === 'excel') {
            exportToExcel($emendas);
        } elseif ($export_type === 'pdf') {
            exportToPDF($emendas);
        }
        exit;
    }
}

$sql = "SELECT * FROM emendas" . (!empty($where) ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY criado_em DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter valores distintos para filtros
$tipos_emenda = ['EMENDA PARLAMENTAR FEDERAL', 'OPERAÇÃO DE CRÉDITO'];
$eixos_tematicos = $pdo->query("SELECT DISTINCT eixo_tematico FROM emendas ORDER BY eixo_tematico")->fetchAll(PDO::FETCH_COLUMN);
$unidades = $pdo->query("SELECT DISTINCT orgao FROM emendas ORDER BY orgao")->fetchAll(PDO::FETCH_COLUMN);
$ods_values = $pdo->query("SELECT DISTINCT ods FROM emendas ORDER BY ods")->fetchAll(PDO::FETCH_COLUMN);
$anos = $pdo->query("SELECT DISTINCT EXTRACT(YEAR FROM criado_em) as ano FROM emendas ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);

// Carregar emendas da planilha Excel
$emendas_excel = lerPlanilhaEmendas(__DIR__ . '/../../uploads/Cadernos_DeEmendas_.xlsx');

// Função para exportar para Excel
function exportToExcel($data) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Cabeçalhos
    $sheet->setCellValue('A1', 'Tipo')
          ->setCellValue('B1', 'Eixo Temático')
          ->setCellValue('C1', 'Unidade')
          ->setCellValue('D1', 'Objeto')
          ->setCellValue('E1', 'Pontuação')
          ->setCellValue('F1', 'ODS')
          ->setCellValue('G1', 'Ano')
          ->setCellValue('H1', 'Data Criação');
    
    // Dados
    $row = 2;
    foreach ($data as $emenda) {
        $sheet->setCellValue('A'.$row, $emenda['tipo_emenda'])
              ->setCellValue('B'.$row, $emenda['eixo_tematico'])
              ->setCellValue('C'.$row, $emenda['orgao'])
              ->setCellValue('D'.$row, $emenda['objeto_intervencao'])
              ->setCellValue('E'.$row, $emenda['pontuacao'] ?? '-')
              ->setCellValue('F'.$row, $emenda['ods'] ?? '-')
              ->setCellValue('G'.$row, date('Y', strtotime($emenda['criado_em'])))
              ->setCellValue('H'.$row, date('d/m/Y H:i', strtotime($emenda['criado_em'])));
        $row++;
    }
    
    // Formatar
    $sheet->getStyle('A1:H1')->getFont()->setBold(true);
    foreach(range('A','H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Configurar download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="relatorio_emendas.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Função para exportar para PDF
function exportToPDF($data) {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    $pdf->SetCreator('Sistema CICEF');
    $pdf->SetAuthor('Painel Administrativo');
    $pdf->SetTitle('Relatório de Emendas');
    
    $pdf->SetHeaderData('', 0, 'Relatório de Emendas', 'Gerado em ' . date('d/m/Y H:i'));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, 15, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->AddPage();
    
    // HTML content
    $html = '<h2>Relatório de Emendas</h2>
            <table border="1" cellpadding="4">
                <tr style="background-color:#007b5e;color:white;">
                    <th width="15%">Tipo</th>
                    <th width="15%">Eixo</th>
                    <th width="15%">Unidade</th>
                    <th width="25%">Objeto</th>
                    <th width="10%">Pontuação</th>
                    <th width="10%">ODS</th>
                    <th width="10%">Data</th>
                </tr>';
    
    foreach ($data as $emenda) {
        $html .= '<tr>
                    <td>'.$emenda['tipo_emenda'].'</td>
                    <td>'.$emenda['eixo_tematico'].'</td>
                    <td>'.$emenda['orgao'].'</td>
                    <td>'.substr($emenda['objeto_intervencao'], 0, 50).'...</td>
                    <td>'.($emenda['pontuacao'] ?? '-').'</td>
                    <td>'.($emenda['ods'] ?? '-').'</td>
                    <td>'.date('d/m/Y', strtotime($emenda['criado_em'])).'</td>
                </tr>';
    }
    
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('relatorio_emendas.pdf', 'D');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - CICEF</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007b5e;
            --secondary-color: #4db6ac;
            --accent-color: #ffc107;
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
        
        .user-name {
            font-weight: 500;
        }
        
        .logout-btn {
            color: var(--dark-color);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        /* Content Area */
        .content-area {
            padding: 2rem;
        }
        
        /* Sections */
        .export-section, .filters-section, .emendas-section, .sync-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .export-section h2, .filters-section h2, .sync-section h2 {
            font-size: 1.25rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
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
            background-color: #006a50;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-excel {
            background-color: #1d6f42;
            color: white;
        }
        
        .btn-pdf {
            background-color: #d32f2f;
            color: white;
        }
        
        .btn-sync {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-sync:hover {
            background-color: #138496;
            transform: translateY(-2px);
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .export-options, .sync-actions {
            display: flex;
            gap: 1rem;
        }
        
        /* Filters */
        .filter-row {
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
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .radio-group {
            display: flex;
            gap: 1.5rem;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .radio-option input {
            accent-color: var(--primary-color);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        /* Table */
        .emendas-table, .sync-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .emendas-table thead th, .sync-table th {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 500;
        }
        
        .emendas-table tbody tr {
            transition: background-color 0.2s;
        }
        
        .emendas-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .emendas-table td, .sync-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .emendas-table tr:last-child td, .sync-table tr:last-child td {
            border-bottom: none;
        }
        
        .actions {
            white-space: nowrap;
        }
        
        .btn-view, .btn-edit, .btn-delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-view {
            background-color: var(--primary-color);
        }
        
        .btn-edit {
            background-color: var(--accent-color);
        }
        
        .btn-delete {
            background-color: var(--error-color);
        }
        
        .btn-view:hover, .btn-edit:hover, .btn-delete:hover {
            transform: scale(1.1);
        }
        
        .no-results {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .modal-section {
            margin-bottom: 1.5rem;
        }
        
        .modal-section h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-sucesso {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-erro {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert {
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Responsividade */
        @media (max-width: 992px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-sidebar.active {
                transform: translateX(0);
            }
            
            .admin-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-group {
                min-width: 100%;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .export-options, .sync-actions {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>
                    <i class="material-icons">admin_panel_settings</i>
                    Painel Admin
                </h2>
            </div>
            <nav class="sidebar-menu">
                <a href="admin_dashboard.php" class="active">
                    <i class="material-icons">list_alt</i>
                    Emendas
                </a>
                <a href="gerenciar_usuarios.php">
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
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="admin-content">
            <!-- Header -->
            <header class="admin-header">
                <button class="menu-toggle" id="menuToggle">
                    <i class="material-icons">menu</i>
                </button>
                <h1>Gerenciamento de Emendas</h1>
                <div class="user-area">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="material-icons">logout</i>
                    </a>
                </div>
            </header>
            
            <!-- Content Area -->
            <main class="content-area">
                <!-- Seção de Sincronização -->
                <section class="sync-section">
                    <h2><i class="material-icons">sync</i> Sincronização de Dados</h2>
                    
                    <?php if (!empty($_SESSION['mensagem_sincronizacao'])): ?>
                        <div class="alert <?= strpos($_SESSION['mensagem_sincronizacao'], 'Erro') !== false ? 'alert-danger' : 'alert-success' ?>">
                            <?= htmlspecialchars($_SESSION['mensagem_sincronizacao']) ?>
                        </div>
                        <?php unset($_SESSION['mensagem_sincronizacao']); ?>
                    <?php endif; ?>
                    
                    <div class="sync-actions">
                        <a href="?sincronizar=1" class="btn btn-sync" onclick="return confirm('Deseja sincronizar os dados com a planilha atual?')">
                            <i class="material-icons">sync</i> Sincronizar Agora
                        </a>
                        
                        <a href="#" class="btn btn-info" onclick="openModal('historicoSincronizacao')">
                            <i class="material-icons">history</i> Ver Histórico
                        </a>
                    </div>
                </section>
                
                <!-- Seção de Exportação -->
                <section class="export-section">
                    <h2><i class="material-icons">description</i> Exportar Dados</h2>
                    <div class="export-options">
                        <a href="?export=excel" class="btn btn-excel">
                            <i class="material-icons">description</i>
                            Exportar para Excel
                        </a>
                        <a href="?export=pdf" class="btn btn-pdf">
                            <i class="material-icons">picture_as_pdf</i>
                            Exportar para PDF
                        </a>
                    </div>
                </section>
                
                <!-- Filtros -->
                <section class="filters-section">
                    <h2><i class="material-icons">filter_alt</i> Filtros</h2>
                    <form method="GET" action="admin_dashboard.php">
                        <div class="filter-row">
                            <div class="form-group">
                                <label>Tipo de Emenda:</label>
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="tipo_caderno" value="EMENDA PARLAMENTAR FEDERAL" 
                                               <?= (isset($_GET['tipo_caderno']) && $_GET['tipo_caderno'] === 'EMENDA PARLAMENTAR FEDERAL') ? 'checked' : '' ?>>
                                        Emenda Parlamentar
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="tipo_caderno" value="OPERAÇÃO DE CRÉDITO"
                                               <?= (isset($_GET['tipo_caderno']) && $_GET['tipo_caderno'] === 'OPERAÇÃO DE CRÉDITO') ? 'checked' : '' ?>>
                                        Operação de Crédito
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="filter-row">
                            <div class="form-group">
                                <label for="eixo_tematico">Eixo Temático:</label>
                                <select id="eixo_tematico" name="eixo_tematico" class="form-control">
                                    <option value="Selecione">Selecione</option>
                                    <?php foreach ($eixos_tematicos as $eixo): ?>
                                        <option value="<?= htmlspecialchars($eixo) ?>" <?= isset($_GET['eixo_tematico']) && $_GET['eixo_tematico'] === $eixo ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($eixo) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="unidade_responsavel">Unidade Responsável:</label>
                                <select id="unidade_responsavel" name="unidade_responsavel" class="form-control">
                                    <option value="Selecione">Selecione</option>
                                    <?php foreach ($unidades as $unidade): ?>
                                        <option value="<?= htmlspecialchars($unidade) ?>" <?= isset($_GET['unidade_responsavel']) && $_GET['unidade_responsavel'] === $unidade ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($unidade) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="ods">ODS:</label>
                                <select id="ods" name="ods" class="form-control">
                                    <option value="Selecione">Selecione</option>
                                    <?php foreach ($ods_values as $ods): ?>
                                        <option value="<?= htmlspecialchars($ods) ?>" <?= isset($_GET['ods']) && $_GET['ods'] === $ods ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ods) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filter-row">
                            <div class="form-group">
                                <label for="pontuacao_de">Pontuação de:</label>
                                <input type="number" id="pontuacao_de" name="pontuacao_de" class="form-control" 
                                       value="<?= htmlspecialchars($_GET['pontuacao_de'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="pontuacao_ate">Pontuação até:</label>
                                <input type="number" id="pontuacao_ate" name="pontuacao_ate" class="form-control" 
                                       value="<?= htmlspecialchars($_GET['pontuacao_ate'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="ano_projeto">Ano:</label>
                                <select id="ano_projeto" name="ano_projeto" class="form-control">
                                    <option value="">Selecione</option>
                                    <?php foreach ($anos as $ano): ?>
                                        <option value="<?= htmlspecialchars($ano) ?>" <?= isset($_GET['ano_projeto']) && $_GET['ano_projeto'] == $ano ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ano) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons">filter_alt</i>
                                Aplicar Filtros
                            </button>
                            <a href="admin_dashboard.php" class="btn btn-secondary">
                                <i class="material-icons">clear</i>
                                Limpar Filtros
                            </a>
                        </div>
                    </form>
                </section>
                
                <!-- Tabela de Emendas -->
                <section class="emendas-section">
                    <?php if (count($emendas) > 0): ?>
                        <table class="emendas-table">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Eixo Temático</th>
                                    <th>Unidade</th>
                                    <th>Objeto</th>
                                    <th>Pontuação</th>
                                    <th>ODS</th>
                                    <th>Ano</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($emendas as $emenda): ?>
                                <tr>
                                    <td><?= htmlspecialchars($emenda['tipo_emenda']) ?></td>
                                    <td><?= htmlspecialchars($emenda['eixo_tematico']) ?></td>
                                    <td><?= htmlspecialchars($emenda['orgao']) ?></td>
                                    <td><?= htmlspecialchars(substr($emenda['objeto_intervencao'], 0, 50)) ?><?= strlen($emenda['objeto_intervencao']) > 50 ? '...' : '' ?></td>
                                    <td><?= htmlspecialchars($emenda['pontuacao'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($emenda['ods'] ?? '-') ?></td>
                                    <td><?= date('Y', strtotime($emenda['criado_em'])) ?></td>
                                    <td class="actions">
                                        <a href="visualizar_emenda.php?id=<?= $emenda['id'] ?>" class="btn-view" title="Visualizar">
                                            <i class="material-icons">visibility</i>
                                        </a>
                                        <a href="editar_emenda.php?id=<?= $emenda['id'] ?>" class="btn-edit" title="Editar">
                                            <i class="material-icons">edit</i>
                                        </a>
                                        <a href="excluir_emenda.php?id=<?= $emenda['id'] ?>" class="btn-delete" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir esta emenda?')">
                                            <i class="material-icons">delete</i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <?php if (count($emendas_excel) > 0): ?>
                            <h2>Emendas da Planilha Excel</h2>
                            <table class="emendas-table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Eixo Temático</th>
                                        <th>Órgão</th>
                                        <th>Objeto</th>
                                        <th>ODS</th>
                                        <th>Valor (R$)</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($emendas_excel as $index => $emenda): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($emenda['tipo']) ?></td>
                                        <td><?= htmlspecialchars($emenda['eixo']) ?></td>
                                        <td><?= htmlspecialchars($emenda['orgao']) ?></td>
                                        <td><?= htmlspecialchars(substr($emenda['objeto'], 0, 50)) ?><?= strlen($emenda['objeto']) > 50 ? '...' : '' ?></td>
                                        <td><?= htmlspecialchars($emenda['ods']) ?></td>
                                        <td><?= htmlspecialchars($emenda['valor']) ?></td>
                                        <td class="actions">
                                            <a href="#" class="btn-view" onclick="openModal(<?= $index ?>)" title="Detalhes">
                                                <i class="material-icons">visibility</i>
                                            </a>
                                            <a href="#" class="btn-edit" onclick="alert('Funcionalidade de edição será implementada')" title="Editar">
                                                <i class="material-icons">edit</i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-results">
                                <p>Nenhuma emenda encontrada com os filtros aplicados.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <!-- Modal para detalhes da emenda -->
    <div id="emendaModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('emendaModal')">&times;</span>
            <h3>Detalhes da Emenda</h3>
            <div class="modal-body" id="modalBody">
                <!-- Conteúdo será preenchido via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Modal Histórico de Sincronização -->
    <div id="historicoSincronizacao" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('historicoSincronizacao')">&times;</span>
            <h3>Histórico de Sincronizações</h3>
            <div class="modal-body">
                <table class="sync-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Total</th>
                            <th>Novos</th>
                            <th>Atualizados</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $historico = $pdo->query("SELECT * FROM sincronizacoes ORDER BY data_hora DESC LIMIT 10")->fetchAll();
                        foreach ($historico as $reg): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($reg['data_hora'])) ?></td>
                            <td><?= $reg['total_registros'] ?></td>
                            <td><?= $reg['novos_registros'] ?></td>
                            <td><?= $reg['registros_atualizados'] ?></td>
                            <td><span class="status-badge status-<?= $reg['status'] ?>"><?= ucfirst($reg['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Menu Toggle
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

        // Modal functions
        const emendasExcel = <?= json_encode($emendas_excel) ?>;
        
        function openModal(index) {
            if (typeof index === 'string') {
                // Abrir modal por ID
                document.getElementById(index).style.display = 'flex';
                return;
            }
            
            // Modal de detalhes da emenda
            const emenda = emendasExcel[index];
            const modalBody = document.getElementById('modalBody');
            
            let html = `
                <div class="modal-section">
                    <h4>Tipo de Emenda</h4>
                    <p>${emenda.tipo}</p>
                </div>
                
                <div class="modal-section">
                    <h4>Eixo Temático</h4>
                    <p>${emenda.eixo}</p>
                </div>
                
                <div class="modal-section">
                    <h4>Órgão Responsável</h4>
                    <p>${emenda.orgao}</p>
                </div>
                
                <div class="modal-section">
                    <h4>Objeto de Intervenção</h4>
                    <p>${emenda.objeto}</p>
                </div>
                
                <div class="modal-section">
                    <h4>ODS Relacionado</h4>
                    <p>${emenda.ods || 'Não especificado'}</p>
                </div>
                
                <div class="modal-section">
                    <h4>Valor Pretendido</h4>
                    <p>R$ ${emenda.valor}</p>
                </div>
                
                <div class="modal-section">
                    <h4>Justificativa</h4>
                    <p>${emenda.justificativa || 'Não disponível'}</p>
                </div>
            `;
            
            modalBody.innerHTML = html;
            document.getElementById('emendaModal').style.display = 'flex';
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>