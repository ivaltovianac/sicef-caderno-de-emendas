<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Processar filtros (sem filtro por usuário, pois a tabela não tem usuario_id)
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
    if (!empty($_GET['ods']) && $_GET['ods'] !== 'Selecione') {
        $where[] = "ods = ?";
        $params[] = $_GET['ods'];
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

// Obter valores distintos para filtros (sem filtro por usuário)
$tipos_emenda = ['EMENDA PARLAMENTAR FEDERAL', 'OPERAÇÃO DE CRÉDITO'];
$eixos_tematicos = $pdo->query("SELECT DISTINCT eixo_tematico FROM emendas ORDER BY eixo_tematico")->fetchAll(PDO::FETCH_COLUMN);
$unidades = $pdo->query("SELECT DISTINCT orgao FROM emendas ORDER BY orgao")->fetchAll(PDO::FETCH_COLUMN);
$ods_values = $pdo->query("SELECT DISTINCT ods FROM emendas ORDER BY ods")->fetchAll(PDO::FETCH_COLUMN);

// Função para exportar para Excel
function exportToExcel($data) {
    $objPHPExcel = new PHPExcel();
    
    // Propriedades do documento
    $objPHPExcel->getProperties()->setCreator("Sistema CICEF")
                                 ->setLastModifiedBy("Sistema CICEF")
                                 ->setTitle("Minhas Emendas")
                                 ->setSubject("Emendas Parlamentares")
                                 ->setDescription("Exportação de emendas do sistema CICEF");
    
    // Adicionar dados
    $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A1', 'Tipo')
                ->setCellValue('B1', 'Eixo Temático')
                ->setCellValue('C1', 'Unidade')
                ->setCellValue('D1', 'Objeto')
                ->setCellValue('E1', 'ODS')
                ->setCellValue('F1', 'Data');
    
    $row = 2;
    foreach ($data as $emenda) {
        $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue('A'.$row, $emenda['tipo_emenda'])
                    ->setCellValue('B'.$row, $emenda['eixo_tematico'])
                    ->setCellValue('C'.$row, $emenda['orgao'])
                    ->setCellValue('D'.$row, $emenda['objeto_intervencao'])
                    ->setCellValue('E'.$row, $emenda['ods'] ?? '-')
                    ->setCellValue('F'.$row, date('d/m/Y', strtotime($emenda['criado_em'])));
        $row++;
    }
    
    // Formatar cabeçalho
    $objPHPExcel->getActiveSheet()->getStyle('A1:F1')->getFont()->setBold(true);
    
    // Auto dimensionar colunas
    foreach(range('A','F') as $columnID) {
        $objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // Configurar cabeçalhos para download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="minhas_emendas.xlsx"');
    header('Cache-Control: max-age=0');
    
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $objWriter->save('php://output');
    exit;
}

// Função para exportar para PDF
function exportToPDF($data) {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Sistema CICEF');
    $pdf->SetTitle('Minhas Emendas');
    $pdf->SetSubject('Emendas Parlamentares');
    $pdf->SetKeywords('PDF, CICEF, Emendas');
    
    $pdf->SetHeaderData('', 0, 'Minhas Emendas', 'Sistema CICEF - ' . date('d/m/Y'));
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
    $html = '<h2>Minhas Emendas</h2>
            <table border="1" cellpadding="4">
                <tr style="background-color:#007b5e;color:white;">
                    <th width="20%">Tipo</th>
                    <th width="20%">Eixo Temático</th>
                    <th width="15%">Unidade</th>
                    <th width="25%">Objeto</th>
                    <th width="10%">ODS</th>
                    <th width="10%">Data</th>
                </tr>';
    
    // Dados
    foreach ($data as $emenda) {
        $html .= '<tr>
                    <td>'.$emenda['tipo_emenda'].'</td>
                    <td>'.$emenda['eixo_tematico'].'</td>
                    <td>'.$emenda['orgao'].'</td>
                    <td>'.substr($emenda['objeto_intervencao'], 0, 100).'...</td>
                    <td>'.($emenda['ods'] ?? '-').'</td>
                    <td>'.date('d/m/Y', strtotime($emenda['criado_em'])).'</td>
                </tr>';
    }
    
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('minhas_emendas.pdf', 'D');
    exit;
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
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .user-menu a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: opacity 0.3s;
        }
        
        .user-menu a:hover {
            opacity: 0.9;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 0 auto;
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
        
        .btn-primary:hover {
            background-color: #3da89e;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
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
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                padding: 1rem;
                gap: 1rem;
            }
            
            .user-menu {
                width: 100%;
                justify-content: space-between;
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
        }
    </style>
</head>
<body>
    <header class="header">
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
    </header>
    
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
        
        <section class="welcome-section">
            <h2>Olá, <?= htmlspecialchars($_SESSION['user']['nome']) ?>!</h2>
            <p>Aqui você pode visualizar e gerenciar suas emendas parlamentares</p>
        </section>
        
        <!-- Seção de Exportação -->
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
        
        <!-- Filtros -->
        <div class="filters-card">
            <form method="GET" action="user_dashboard.php">
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
                            <input type="radio" name="tipo_caderno" value="OPERAÇÃO DE CRÉDITO"
                                   <?= (isset($_GET['tipo_caderno']) && $_GET['tipo_caderno'] === 'OPERAÇÃO DE CRÉDITO') ? 'checked' : '' ?>>
                            OUTROS RECURSOS
                        </label>
                    </div>
                    
                    <?php if (isset($_GET['tipo_caderno']) && $_GET['tipo_caderno'] === 'OPERAÇÃO DE CRÉDITO'): ?>
                    <div class="checkbox-group">
                        <label class="radio-option">
                            <input type="checkbox" name="outros_recursos" value="1"
                                   <?= isset($_GET['outros_recursos']) ? 'checked' : '' ?>>
                            OUTROS RECURSOS
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="filter-section">
                    <h3><span class="material-icons">tune</span> Filtros Avançados</h3>
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
                    <a href="user_dashboard.php" class="btn btn-secondary">
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
                    <th>ODS</th>
                    <th>Data</th>
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
                    <td><?= htmlspecialchars($emenda['ods'] ?? '-') ?></td>
                    <td><?= date('d/m/Y', strtotime($emenda['criado_em'])) ?></td>
                    <td class="actions-cell">
                        <a href="visualizar_emenda.php?id=<?= $emenda['id'] ?>" class="action-link">
                            <span class="material-icons" style="font-size: 1.1rem;">visibility</span>
                            Visualizar
                        </a>
                        <a href="editar_emenda.php?id=<?= $emenda['id'] ?>" class="action-link">
                            <span class="material-icons" style="font-size: 1.1rem;">edit</span>
                            Editar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <h3>Nenhuma emenda encontrada</h3>
            <p>Você ainda não cadastrou nenhuma emenda ou não há resultados com os filtros selecionados.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>