<?php
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php'; 


use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Função para ler a planilha Excel
function lerPlanilhaEmendas($caminhoArquivo) {
    try {
        // Verifique se o arquivo existe
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
                $emendas[] = [
                    'tipo' => $cells[0] ?? '',
                    'eixo' => $cells[1] ?? '',
                    'orgao' => $cells[2] ?? '',
                    'objeto' => $cells[3] ?? '',
                    'ods' => $cells[4] ?? '',
                    'valor' => isset($cells[10]) ? number_format($cells[10], 2, ',', '.') : '0,00',
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
$anos = $pdo->query("SELECT DISTINCT EXTRACT(YEAR FROM criado_em)::integer as ano FROM emendas ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);

// Carregar emendas da planilha Excel
$emendas_excel = lerPlanilhaEmendas(__DIR__ . '/uploads/Cadernos_DeEmendas_.xlsx');

// Função para exportar para Excel
function exportToExcel($data) {
    $objPHPExcel = new PHPExcel();
    
    // Propriedades do documento
    $objPHPExcel->getProperties()->setCreator("Sistema CICEF - Admin")
                                 ->setLastModifiedBy("Sistema CICEF - Admin")
                                 ->setTitle("Relatório de Emendas")
                                 ->setSubject("Emendas Parlamentares")
                                 ->setDescription("Exportação administrativa de emendas");
    

    // Adicionar dados
    $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A1', 'Tipo')
                ->setCellValue('B1', 'Eixo Temático')
                ->setCellValue('C1', 'Unidade')
                ->setCellValue('D1', 'Objeto')
                ->setCellValue('E1', 'Pontuação')
                ->setCellValue('F1', 'ODS')
                ->setCellValue('G1', 'Ano')
                ->setCellValue('H1', 'Data Criação');
    
    $row = 2;
    foreach ($data as $emenda) {
        $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue('A'.$row, $emenda['tipo_emenda'])
                    ->setCellValue('B'.$row, $emenda['eixo_tematico'])
                    ->setCellValue('C'.$row, $emenda['orgao'])
                    ->setCellValue('D'.$row, $emenda['objeto_intervencao'])
                    ->setCellValue('E'.$row, $emenda['pontuacao'] ?? '-')
                    ->setCellValue('F'.$row, $emenda['ods'] ?? '-')
                    ->setCellValue('G'.$row, date('Y', strtotime($emenda['criado_em'])))
                    ->setCellValue('H'.$row, date('d/m/Y H:i', strtotime($emenda['criado_em'])));
        $row++;
    }
    
    // Formatar cabeçalho
    $objPHPExcel->getActiveSheet()->getStyle('A1:H1')->getFont()->setBold(true);
    
    // Auto dimensionar colunas
    foreach(range('A','H') as $columnID) {
        $objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // Configurar cabeçalhos para download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="relatorio_emendas_admin.xlsx"');
    header('Cache-Control: max-age=0');
    
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $objWriter->save('php://output');
    exit;
}

// Função para exportar para PDF
function exportToPDF($data) {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Sistema CICEF - Admin');
    $pdf->SetTitle('Relatório de Emendas');
    $pdf->SetSubject('Emendas Parlamentares');
    $pdf->SetKeywords('PDF, CICEF, Emendas, Admin');
    
    $pdf->SetHeaderData('', 0, 'Relatório de Emendas - Painel Admin', 'Sistema CICEF - ' . date('d/m/Y H:i'));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->AddPage();
    
    // Cabeçalho da tabela
    $html = '<h2>Relatório de Emendas</h2>
            <p><strong>Data:</strong> '.date('d/m/Y H:i').'</p>
            <p><strong>Total de registros:</strong> '.count($data).'</p>
            <table border="1" cellpadding="4">
                <tr style="background-color:#007b5e;color:white;">
                    <th width="15%">Tipo</th>
                    <th width="15%">Eixo Temático</th>
                    <th width="15%">Unidade</th>
                    <th width="20%">Objeto</th>
                    <th width="10%">Pontuação</th>
                    <th width="10%">ODS</th>
                    <th width="10%">Ano</th>
                    <th width="10%">Data</th>
                </tr>';
    
    // Dados
    foreach ($data as $emenda) {
        $html .= '<tr>
                    <td>'.$emenda['tipo_emenda'].'</td>
                    <td>'.$emenda['eixo_tematico'].'</td>
                    <td>'.$emenda['orgao'].'</td>
                    <td>'.substr($emenda['objeto_intervencao'], 0, 100).'...</td>
                    <td>'.($emenda['pontuacao'] ?? '-').'</td>
                    <td>'.($emenda['ods'] ?? '-').'</td>
                    <td>'.date('Y', strtotime($emenda['criado_em'])).'</td>
                    <td>'.date('d/m/Y', strtotime($emenda['criado_em'])).'</td>
                </tr>';
    }
    
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('relatorio_emendas_admin.pdf', 'D');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - CICEF</title>
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
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
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
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-left: 1.5rem;
            margin-top: 0.75rem;
        }
        
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1rem;
        }
        
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
        
        .btn-danger {
            background-color: #dc3545;
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
        
        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        
        /* Tabela */
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
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
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
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
        }
        
        /* Estilos para a tabela de emendas da planilha */
        .emendas-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: var(--shadow);
        }
        
        .emendas-container h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.25rem;
            font-weight: 600;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }
        
        /* Modal para detalhes */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            width: 80%;
            max-width: 800px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .modal-body {
            margin-top: 1rem;
        }
        
        .modal-section {
            margin-bottom: 1.5rem;
        }
        
        .modal-section h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
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
            
            .filter-row {
                flex-direction: column;
                gap: 1rem;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .emendas-table {
                display: block;
                overflow-x: auto;
            }
            
            .emendas-table thead th {
                top: 60px;
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
            
            .modal-content {
                width: 95%;
                margin: 2% auto;
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
            <a href="admin_dashboard.php" class="menu-item active">
                <span class="material-icons">list_alt</span>
                Emendas
            </a>
            <a href="gerenciar_usuarios.php" class="menu-item">
                <span class="material-icons">people</span>
                Gerenciar Usuários
            </a>
            <a href="relatorios.php" class="menu-item">
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
            <h1>Painel Administrativo</h1>
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
                <h2>Gerenciamento de Emendas</h2>
                <p>Visualize e gerencie todas as emendas parlamentares cadastradas no sistema</p>
            </section>
            
            <!-- Seção de Exportação -->
            <div class="export-section">
                <div class="export-title">
                    <span class="material-icons">description</span>
                    Exportar Relatório
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
            
            <!-- Filtros -->
            <div class="filters-card">
                <form method="GET" action="admin_dashboard.php">
                    <div class="filter-section">
                        <h3><span class="material-icons">filter_alt</span> Tipo de Caderno</h3>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="tipo_caderno" value="EMENDA PARLAMENTAR FEDERAL" 
                                       <?= (isset($_GET['tipo_caderno']) && $_GET['tipo_caderno'] === 'EMENDA PARLAMENTAR FEDERAL') ? 'checked' : '' ?>>
                                EMENDA PARLAMENTAR FEDERAL/Outros OGU
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_caderno" value="OPERAÇÃO DE CRÉDITO"
                                       <?= (isset($_GET['tipo_caderno']) && $_GET['tipo_caderno'] === 'OPERAÇÃO DE CRÉDITO') ? 'checked' : '' ?>>
                                OPERAÇÃO DE CRÉDITO
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_caderno" value="OUTROS RECURSOS"
                                       <?= (isset($_GET['tipo_caderno']) && $_GET['tipo_caderno'] === 'OPERAÇÃO DE CRÉDITO') ? 'checked' : '' ?>>
                                OUTROS RECURSOS
                            </label>
                        </div>
                        
                        <?php if (isset($_GET['tipo_caderno']) && $_GET['tipo_caderno'] === 'OPERAÇÃO DE CRÉDITO'): ?>
                        <!-- <div class="checkbox-group">
                            <label class="radio-option">
                                <input type="checkbox" name="outros_recursos" value="1"
                                       <?= isset($_GET['outros_recursos']) ? 'checked' : '' ?>>
                                OUTROS RECURSOS
                            </label>
                        </div> -->
                        <?php endif; ?>
                    </div>
                    
                    <div class="filter-section">
                        <h3><span class="material-icons">tune</span> Filtros Avançados</h3>
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="pontuacao_de">Pontuação de:</label>
                                <input type="number" id="pontuacao_de" name="pontuacao_de" class="filter-control" 
                                       value="<?= htmlspecialchars($_GET['pontuacao_de'] ?? '') ?>" min="0">
                            </div>
                            <div class="filter-group">
                                <label for="pontuacao_ate">Pontuação até:</label>
                                <input type="number" id="pontuacao_ate" name="pontuacao_ate" class="filter-control" 
                                       value="<?= htmlspecialchars($_GET['pontuacao_ate'] ?? '') ?>" min="0">
                            </div>
                            <div class="filter-group">
                                <label for="ano_projeto">Ano Projeto:</label>
                                <select id="ano_projeto" name="ano_projeto" class="filter-control">
                                    <option value="">Selecione</option>
                                    <?php foreach ($anos as $ano): ?>
                                        <option value="<?= htmlspecialchars($ano) ?>" <?= isset($_GET['ano_projeto']) && $_GET['ano_projeto'] == $ano ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ano) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="filter-section">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="eixo_tematico">Eixo Temático</label>
                                <select id="eixo_tematico" name="eixo_tematico" class="filter-control">
                                    <option value="Selecione">Selecione</option>
                                    <?php foreach ($eixos_tematicos as $eixo): ?>
                                        <option value="<?= htmlspecialchars($eixo) ?>" <?= isset($_GET['eixo_tematico']) && $_GET['eixo_tematico'] === $eixo ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($eixo) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="ods">ODS - Objetivo de Desenv. Sustentável</label>
                                <select id="ods" name="ods" class="filter-control">
                                    <option value="Selecione">Selecione</option>
                                    <?php foreach ($ods_values as $ods): ?>
                                        <option value="<?= htmlspecialchars($ods) ?>" <?= isset($_GET['ods']) && $_GET['ods'] === $ods ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ods) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="unidade_responsavel">Unidade Responsável</label>
                                <select id="unidade_responsavel" name="unidade_responsavel" class="filter-control">
                                    <option value="Selecione">Selecione</option>
                                    <?php foreach ($unidades as $unidade): ?>
                                        <option value="<?= htmlspecialchars($unidade) ?>" <?= isset($_GET['unidade_responsavel']) && $_GET['unidade_responsavel'] === $unidade ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($unidade) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons">filter_alt</span>
                            Aplicar Filtros
                        </button>
                        <a href="admin_dashboard.php" class="btn btn-secondary">
                            <span class="material-icons">clear</span>
                            Limpar Filtros
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Tabela de Emendas -->
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
                        <td class="actions-cell">
                            <a href="visualizar_emenda.php?id=<?= $emenda['id'] ?>" class="action-link">
                                <span class="material-icons" style="font-size: 1.1rem;">visibility</span>
                                Visualizar
                            </a>
                            <a href="editar_emenda.php?id=<?= $emenda['id'] ?>" class="action-link">
                                <span class="material-icons" style="font-size: 1.1rem;">edit</span>
                                Editar
                            </a>
                            <a href="excluir_emenda.php?id=<?= $emenda['id'] ?>" class="action-link danger" onclick="return confirm('Tem certeza que deseja excluir esta emenda?')">
                                <span class="material-icons" style="font-size: 1.1rem;">delete</span>
                                Excluir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="emendas-container">
                    <h3>Emendas da Planilha Excel</h3>
                    <table class="emendas-table">
                        <thead>
                            <tr>
                                <th>Tipo de Emenda</th>
                                <th>Eixo Temático</th>
                                <th>Órgão</th>
                                <th>Objeto de Intervenção</th>
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
                                <td class="actions-cell">
                                    <a href="#" class="action-link" onclick="openModal(<?= $index ?>)">
                                        <span class="material-icons" style="font-size: 1.1rem;">visibility</span>
                                        Detalhes
                                    </a>
                                    <a href="#" class="action-link" onclick="alert('Funcionalidade de edição será implementada')">
                                        <span class="material-icons" style="font-size: 1.1rem;">edit</span>
                                        Editar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para detalhes -->
    <div id="emendaModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h3>Detalhes da Emenda</h3>
            <div class="modal-body" id="modalBody">
                <!-- Conteúdo será preenchido via JavaScript -->
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

        // Modal functions
        const emendasExcel = <?= json_encode($emendas_excel) ?>;
        
        function openModal(index) {
            const emenda = emendasExcel[index];
            const modal = document.getElementById('emendaModal');
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
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('emendaModal').style.display = 'none';
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('emendaModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>