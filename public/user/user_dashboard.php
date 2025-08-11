<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: ../login.php");
    exit;
}
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!class_exists("TCPDF")) {
    die("TCPDF não está instalado. Por favor, instale via composer: composer require tecnickcom/tcpdf");
}

/**
 * Exportação para Excel (colunas completas)
 */
function exportToExcel($data) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Cabeçalhos
    $sheet->setCellValue('A1', 'Tipo')
        ->setCellValue('B1', 'Eixo Temático')
        ->setCellValue('C1', 'Órgão')
        ->setCellValue('D1', 'Objeto de Intervenção')
        ->setCellValue('E1', 'ODS')
        ->setCellValue('F1', 'Regionalização')
        ->setCellValue('G1', 'Unidade Orçamentária')
        ->setCellValue('H1', 'Programa')
        ->setCellValue('I1', 'Ação')
        ->setCellValue('J1', 'Categoria Econômica')
        ->setCellValue('K1', 'Valor (R$)')
        ->setCellValue('L1', 'Justificativa')
        ->setCellValue('M1', 'Ano')
        ->setCellValue('N1', 'Data Criação');

    // Dados
    $row = 2;
    foreach ($data as $emenda) {
        $sheet->setCellValue('A'.$row, $emenda['tipo_emenda'] ?? '')
            ->setCellValue('B'.$row, $emenda['eixo_tematico'] ?? '')
            ->setCellValue('C'.$row, $emenda['orgao'] ?? '')
            ->setCellValue('D'.$row, $emenda['objeto_intervencao'] ?? '')
            ->setCellValue('E'.$row, $emenda['ods'] ?? '-')
            ->setCellValue('F'.$row, $emenda['regionalizacao'] ?? '-')
            ->setCellValue('G'.$row, $emenda['unidade_orcamentaria'] ?? '-')
            ->setCellValue('H'.$row, $emenda['programa'] ?? '-')
            ->setCellValue('I'.$row, $emenda['acao'] ?? '-')
            ->setCellValue('J'.$row, $emenda['categoria_economica'] ?? '-')
            ->setCellValue('K'.$row, is_numeric($emenda['valor']) ? (float)$emenda['valor'] : $emenda['valor'])
            ->setCellValue('L'.$row, $emenda['justificativa'] ?? '-')
            ->setCellValue('M'.$row, isset($emenda['criado_em']) ? date('Y', strtotime($emenda['criado_em'])) : '-')
            ->setCellValue('N'.$row, isset($emenda['criado_em']) ? date('d/m/Y H:i', strtotime($emenda['criado_em'])) : '-');
        $row++;
    }

    // Estilo/colunas
    $sheet->getStyle('A1:N1')->getFont()->setBold(true);
    foreach(range('A','N') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="relatorio_emendas.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Exportação para PDF (colunas completas)
 */
function exportToPDF($data) {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Sistema CICEF');
    $pdf->SetAuthor('Painel do Usuário');
    $pdf->SetTitle('Relatório de Emendas');
    $pdf->SetHeaderData('', 0, 'Relatório de Emendas', 'Gerado em ' . date('d/m/Y H:i'));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, 15, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->AddPage();

    $html = '<h2>Relatório de Emendas</h2><table border="1" cellpadding="3">';
    $html .= '<tr style="background-color:#007b5e;color:white;">'
        . '<th>Tipo</th>'
        . '<th>Eixo</th>'
        . '<th>Órgão</th>'
        . '<th>Objeto</th>'
        . '<th>ODS</th>'
        . '<th>Regionalização</th>'
        . '<th>Unid. Orç.</th>'
        . '<th>Programa</th>'
        . '<th>Ação</th>'
        . '<th>Categoria</th>'
        . '<th>Valor (R$)</th>'
        . '<th>Justificativa</th>'
        . '<th>Data</th>'
        . '</tr>';

    foreach ($data as $emenda) {
        $html .= '<tr>'
            . '<td>'.htmlspecialchars($emenda['tipo_emenda'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['eixo_tematico'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['orgao'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars(substr($emenda['objeto_intervencao'] ?? '-', 0, 50)).'...</td>'
            . '<td>'.htmlspecialchars($emenda['ods'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['regionalizacao'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['unidade_orcamentaria'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['programa'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['acao'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['categoria_economica'] ?? '-').'</td>'
            . '<td>'.(isset($emenda['valor']) ? number_format((float)$emenda['valor'], 2, ',', '.') : '-').'</td>'
            . '<td>'.htmlspecialchars(substr($emenda['justificativa'] ?? '-', 0, 80)).'...</td>'
            . '<td>'.(isset($emenda['criado_em']) ? date('d/m/Y', strtotime($emenda['criado_em'])) : '-').'</td>'
            . '</tr>';
    }

    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('relatorio_emendas.pdf', 'D');
    exit;
}

/* ----------------------------
   Ações do usuário (add/remove)
   ---------------------------- */
$usuario_id = $_SESSION["user"]["id"];

// Processar ações do usuário (adicionar/remover emenda às "minhas emendas")
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && isset($_POST["emenda_id"])) {
    $emenda_id = $_POST["emenda_id"];
    $action = $_POST["action"];
    if ($action === "add") {
        try {
            $stmt = $pdo->prepare("INSERT INTO usuario_emendas (usuario_id, emenda_id) VALUES (?, ?)");
            $stmt->execute([$usuario_id, $emenda_id]);
            $_SESSION["message"] = "Emenda adicionada às suas emendas!";
        } catch (PDOException $e) {
            // Tratamento para chave duplicada (Postgres: 23505)
            if ($e->getCode() == 23505 || strpos($e->getMessage(), 'Duplicate') !== false) {
                $_SESSION["message"] = "Esta emenda já está nas suas emendas.";
            } else {
                $_SESSION["message"] = "Erro ao adicionar emenda: " . $e->getMessage();
            }
        }
    } elseif ($action === "remove") {
        try {
            $stmt = $pdo->prepare("DELETE FROM usuario_emendas WHERE usuario_id = ? AND emenda_id = ?");
            $stmt->execute([$usuario_id, $emenda_id]);
            $_SESSION["message"] = "Emenda removida das suas emendas!";
        } catch (PDOException $e) {
            $_SESSION["message"] = "Erro ao remover emenda: " . $e->getMessage();
        }
    }
    header("Location: user_dashboard.php");
    exit;
}

/* ----------------------------
   Processar filtros
   ---------------------------- */
$where = [];
$params = [];

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    if (!empty($_GET["tipo_caderno"])) {
        $where[] = "tipo_emenda = ?";
        $params[] = $_GET["tipo_caderno"];
    }
    if (!empty($_GET["eixo_tematico"]) && $_GET["eixo_tematico"] !== "Selecione") {
        $where[] = "eixo_tematico = ?";
        $params[] = $_GET["eixo_tematico"];
    }
    if (!empty($_GET["unidade_responsavel"]) && $_GET["unidade_responsavel"] !== "Selecione") {
        $where[] = "orgao = ?";
        $params[] = $_GET["unidade_responsavel"];
    }
    if (!empty($_GET["ods"]) && $_GET["ods"] !== "Selecione") {
        $where[] = "ods = ?";
        $params[] = $_GET["ods"];
    }
    if (!empty($_GET["regionalizacao"]) && $_GET["regionalizacao"] !== "Selecione") {
        $where[] = "regionalizacao = ?";
        $params[] = $_GET["regionalizacao"];
    }
    if (!empty($_GET["unidade_orcamentaria"]) && $_GET["unidade_orcamentaria"] !== "Selecione") {
        $where[] = "unidade_orcamentaria = ?";
        $params[] = $_GET["unidade_orcamentaria"];
    }
    if (!empty($_GET["programa"]) && $_GET["programa"] !== "Selecione") {
        $where[] = "programa = ?";
        $params[] = $_GET["programa"];
    }
    if (!empty($_GET["acao"]) && $_GET["acao"] !== "Selecione") {
        $where[] = "acao = ?";
        $params[] = $_GET["acao"];
    }
    if (!empty($_GET["categoria_economica"]) && $_GET["categoria_economica"] !== "Selecione") {
        $where[] = "categoria_economica = ?";
        $params[] = $_GET["categoria_economica"];
    }
    if (!empty($_GET["valor_de"])) {
        $where[] = "valor >= ?";
        $params[] = $_GET["valor_de"];
    }
    if (!empty($_GET["valor_ate"])) {
        $where[] = "valor <= ?";
        $params[] = $_GET["valor_ate"];
    }
    if (!empty($_GET["ano_projeto"])) {
        $where[] = "EXTRACT(YEAR FROM criado_em) = ?";
        $params[] = $_GET["ano_projeto"];
    }

    // Ajuste: aplicar o filtro 'outros_recursos' sempre que fornecido (usa o valor enviado: 0 ou 1)
    if (isset($_GET["outros_recursos"]) && $_GET["outros_recursos"] !== "") {
        $where[] = "outros_recursos = ?";
        $params[] = (int)$_GET["outros_recursos"];
    }

    // Processar exportação
    if (isset($_GET["export"])) {
        $export_type = $_GET["export"];
        $sql = "SELECT * FROM emendas" . (!empty($where) ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY criado_em DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($export_type === "excel") {
            exportToExcel($emendas);
        } elseif ($export_type === "pdf") {
            exportToPDF($emendas);
        }
        exit;
    }
}

/* ----------------------------
   Paginação & Consulta
   ---------------------------- */
$itens_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// total
$sql_count = "SELECT COUNT(*) as total FROM emendas" . (!empty($where) ? " WHERE " . implode(" AND ", $where) : "");
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_emendas = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ($total_emendas > 0) ? (int)ceil($total_emendas / $itens_por_pagina) : 1;

// query principal
$sql = "SELECT * FROM emendas" . (!empty($where) ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY criado_em DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
if (!empty($params)) {
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
}
$stmt->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// emendas do usuário (para mostrar botão add/remove)
$stmt_user_emendas = $pdo->prepare("SELECT emenda_id FROM usuario_emendas WHERE usuario_id = ?");
$stmt_user_emendas->execute([$usuario_id]);
$user_emenda_ids = $stmt_user_emendas->fetchAll(PDO::FETCH_COLUMN);

/* ----------------------------
   Valores distintos para filtros
   ---------------------------- */
// Adicionei "OUTROS RECURSOS" aqui também
$tipos_emenda = ['EMENDA PARLAMENTAR FEDERAL', 'OPERAÇÃO DE CRÉDITO', 'OUTROS RECURSOS'];
$eixos_tematicos = $pdo->query("SELECT DISTINCT eixo_tematico FROM emendas ORDER BY eixo_tematico")->fetchAll(PDO::FETCH_COLUMN);
$unidades = $pdo->query("SELECT DISTINCT orgao FROM emendas ORDER BY orgao")->fetchAll(PDO::FETCH_COLUMN);
$ods_values = $pdo->query("SELECT DISTINCT ods FROM emendas ORDER BY ods")->fetchAll(PDO::FETCH_COLUMN);
$regionalizacoes = $pdo->query("SELECT DISTINCT regionalizacao FROM emendas ORDER BY regionalizacao")->fetchAll(PDO::FETCH_COLUMN);
$unidades_orcamentarias = $pdo->query("SELECT DISTINCT unidade_orcamentaria FROM emendas ORDER BY unidade_orcamentaria")->fetchAll(PDO::FETCH_COLUMN);
$programas = $pdo->query("SELECT DISTINCT programa FROM emendas ORDER BY programa")->fetchAll(PDO::FETCH_COLUMN);
$acoes = $pdo->query("SELECT DISTINCT acao FROM emendas ORDER BY acao")->fetchAll(PDO::FETCH_COLUMN);
$categorias_economicas = $pdo->query("SELECT DISTINCT categoria_economica FROM emendas ORDER BY categoria_economica")->fetchAll(PDO::FETCH_COLUMN);
$anos = $pdo->query("SELECT DISTINCT EXTRACT(YEAR FROM criado_em) as ano FROM emendas ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);

/* ----------------------------
   Cores / UI
   ---------------------------- */
$user_colors = [
    'primary' => '#007b5e',
    'secondary' => '#4db6ac',
    'accent' => '#ffc107'
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
<title>Painel do Usuário - CICEF</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
:root { --primary-color: <?= $user_colors['primary'] ?>; --secondary-color: <?= $user_colors['secondary'] ?>; --accent-color: <?= $user_colors['accent'] ?>; --dark-color: #2c3e50; --light-color: #f8f9fa; --border-color: #e0e0e0; --error-color: #e74c3c; --success-color: #2ecc71; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Roboto', sans-serif; background-color: #f5f7fa; color: #333; line-height: 1.6; overflow-x: hidden; }
.user-container { display: flex; min-height: 100vh; }
/* Sidebar */
.user-sidebar { width: 250px; background-color: var(--dark-color); color: white; padding: 1.5rem 0; position: fixed; height: 100vh; transition: all 0.3s; z-index: 100; overflow-y: auto; }
.sidebar-header { padding: 0 1.5rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
.sidebar-header h2 { font-size: 1.25rem; display: flex; align-items: center; gap: 0.75rem; }
.sidebar-menu { padding: 1rem 0; }
.sidebar-menu a { display: flex; align-items: center; padding: 0.75rem 1.5rem; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; gap: 0.75rem; }
.sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgba(255,255,255,0.1); color: white; }
.sidebar-menu i { font-size: 1.25rem; }
/* Main Content */
.user-content { flex: 1; margin-left: 250px; transition: all 0.3s; width: calc(100% - 250px); }
/* Header */
.user-header { background: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 90; }
.user-header h1 { font-size: 1.5rem; color: var(--dark-color); }
.menu-toggle { display: none; background: none; border: none; color: var(--dark-color); font-size: 1.5rem; cursor: pointer; }
.user-area { display: flex; align-items: center; gap: 1rem; }
.user-icon { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.2rem; }
.user-name { font-weight: 500; }
.logout-btn { color: var(--dark-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px; transition: all 0.3s; }
.logout-btn:hover { background-color: var(--light-color); }
/* Content Area */
.content-area { padding: 2rem; max-width: 100%; }
/* Sections */
.export-section, .filters-section, .emendas-section { background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
.export-section h2, .filters-section h2 { font-size: 1.25rem; color: var(--primary-color); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
/* Buttons */
.btn { display: inline-flex; align-items: center; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; text-decoration: none; transition: all 0.3s; border: none; gap: 0.5rem; }
.btn-primary { background-color: var(--primary-color); color: white; }
.btn-primary:hover { opacity: 0.9; transform: translateY(-2px); }
.btn-secondary { background-color: #6c757d; color: white; }
.btn-secondary:hover { background-color: #5a6268; transform: translateY(-2px); }
.btn-success { background-color: var(--success-color); color: white; }
.btn-success:hover { background-color: #27ae60; transform: translateY(-2px); }
.btn-danger { background-color: var(--error-color); color: white; }
.btn-danger:hover { background-color: #c0392b; transform: translateY(-2px); }
/* Export Buttons */
.export-buttons { display: flex; gap: 1rem; flex-wrap: wrap; }
/* Filters */
.filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.filter-group { display: flex; flex-direction: column; }
.filter-group label { font-weight: 500; margin-bottom: 0.5rem; color: var(--dark-color); }
.form-control { padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; transition: border-color 0.3s; }
.form-control:focus { border-color: var(--primary-color); outline: none; }
.filter-actions { display: flex; gap: 1rem; flex-wrap: wrap; }
/* Table Container - Responsivo */
.table-container { width: 100%; overflow-x: auto; margin-bottom: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
.emendas-table { width: 100%; min-width: 1200px; border-collapse: collapse; background: white; }
.emendas-table thead th { background-color: var(--primary-color); color: white; font-weight: 600; padding: 1rem 0.75rem; text-align: left; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
.emendas-table tbody tr { transition: background-color 0.2s; border-bottom: 1px solid var(--border-color); }
.emendas-table tbody tr:hover { background-color: #f8f9fa; }
.emendas-table td { padding: 1rem 0.75rem; vertical-align: top; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.emendas-table td.description { max-width: 300px; white-space: normal; line-height: 1.4; }
.action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }
/* Pagination */
.pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 2rem; flex-wrap: wrap; }
.pagination a, .pagination span { padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; color: var(--dark-color); transition: all 0.3s; }
.pagination a:hover { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }
.pagination .current { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }
/* Messages */
.message { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
.message-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.message-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
/* Mobile Responsiveness */
@media (max-width: 768px) {
  .user-sidebar { transform: translateX(-100%); }
  .user-sidebar.active { transform: translateX(0); }
  .user-content { margin-left: 0; width: 100%; }
  .menu-toggle { display: block; }
  .user-header { padding: 1rem; }
  .content-area { padding: 1rem; }
  .filters-grid { grid-template-columns: 1fr; }
  .export-buttons { flex-direction: column; }
  .filter-actions { flex-direction: column; }
  .emendas-table { min-width: 800px; }
  .action-buttons { flex-direction: column; }
  .user-area { gap: 0.5rem; }
  .user-name { display: none; }
}
@media (max-width: 480px) {
  .user-header h1 { font-size: 1.25rem; }
  .emendas-table { min-width: 600px; }
  .emendas-table th, .emendas-table td { padding: 0.5rem; font-size: 0.875rem; }
}
/* Overlay para mobile */
.sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 99; }
.sidebar-overlay.active { display: block; }
</style>
</head>
<body>
<div class="user-container">
    <!-- Sidebar -->
    <nav class="user-sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>
                <i class="material-icons">account_circle</i> Painel Usuário
            </h2>
        </div>
        <div class="sidebar-menu">
            <a href="user_dashboard.php" class="active">
                <i class="material-icons">dashboard</i> Emendas
            </a>
            <a href="minhas_emendas.php">
                <i class="material-icons">star</i> Minhas Emendas
            </a>
            <!-- lateral "Outros Recursos" removido conforme solicitado -->
        </div>
    </nav>

    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="user-content">
        <!-- Header -->
        <header class="user-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="material-icons">menu</i>
                </button>
                <h1>Emendas Disponíveis</h1>
            </div>
            <div class="user-area">
                <div class="user-icon"> <?= strtoupper(substr($_SESSION["user"]["nome"], 0, 1)) ?> </div>
                <span class="user-name"><?= htmlspecialchars($_SESSION["user"]["nome"]) ?></span>
                <a href="../logout.php" class="logout-btn">
                    <i class="material-icons">logout</i> Sair
                </a>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (isset($_SESSION["message"])): ?>
                <div class="message <?= strpos($_SESSION["message"], 'Erro') !== false ? 'message-error' : 'message-success' ?>">
                    <i class="material-icons"><?= strpos($_SESSION["message"], 'Erro') !== false ? 'error' : 'check_circle' ?></i>
                    <?= htmlspecialchars($_SESSION["message"]) ?>
                </div>
                <?php unset($_SESSION["message"]); ?>
            <?php endif; ?>

            <!-- Export Section -->
            <section class="export-section">
                <h2>
                    <i class="material-icons">file_download</i> Exportar Dados
                </h2>
                <div class="export-buttons">
                    <a href="?export=excel&<?= http_build_query($_GET) ?>" class="btn btn-success">
                        <i class="material-icons">table_chart</i> Exportar Excel
                    </a>
                    <a href="?export=pdf&<?= http_build_query($_GET) ?>" class="btn btn-danger">
                        <i class="material-icons">picture_as_pdf</i> Exportar PDF
                    </a>
                </div>
            </section>

            <!-- Filters Section -->
            <section class="filters-section">
                <h2>
                    <i class="material-icons">filter_list</i> Filtros
                </h2>
                <form method="GET" action="user_dashboard.php">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="tipo_caderno">Tipo de Caderno:</label>
                            <select name="tipo_caderno" id="tipo_caderno" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($tipos_emenda as $tipo): ?>
                                    <option value="<?= htmlspecialchars($tipo) ?>" <?= ($_GET["tipo_caderno"] ?? "") === $tipo ? "selected" : "" ?>>
                                        <?= htmlspecialchars($tipo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="eixo_tematico">Eixo Temático:</label>
                            <select name="eixo_tematico" id="eixo_tematico" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($eixos_tematicos as $eixo): ?>
                                    <option value="<?= htmlspecialchars($eixo) ?>" <?= ($_GET["eixo_tematico"] ?? "") === $eixo ? "selected" : "" ?>>
                                        <?= htmlspecialchars($eixo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="unidade_responsavel">Unidade Responsável:</label>
                            <select name="unidade_responsavel" id="unidade_responsavel" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($unidades as $unidade): ?>
                                    <option value="<?= htmlspecialchars($unidade) ?>" <?= ($_GET["unidade_responsavel"] ?? "") === $unidade ? "selected" : "" ?>>
                                        <?= htmlspecialchars($unidade) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="ods">ODS:</label>
                            <select name="ods" id="ods" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($ods_values as $ods): ?>
                                    <option value="<?= htmlspecialchars($ods) ?>" <?= ($_GET["ods"] ?? "") === $ods ? "selected" : "" ?>>
                                        <?= htmlspecialchars($ods) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="regionalizacao">Regionalização:</label>
                            <select name="regionalizacao" id="regionalizacao" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($regionalizacoes as $reg): ?>
                                    <option value="<?= htmlspecialchars($reg) ?>" <?= ($_GET["regionalizacao"] ?? "") === $reg ? "selected" : "" ?>>
                                        <?= htmlspecialchars($reg) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="unidade_orcamentaria">Unidade Orçamentária:</label>
                            <select name="unidade_orcamentaria" id="unidade_orcamentaria" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($unidades_orcamentarias as $uo): ?>
                                    <option value="<?= htmlspecialchars($uo) ?>" <?= ($_GET["unidade_orcamentaria"] ?? "") === $uo ? "selected" : "" ?>>
                                        <?= htmlspecialchars($uo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="programa">Programa:</label>
                            <select name="programa" id="programa" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($programas as $prog): ?>
                                    <option value="<?= htmlspecialchars($prog) ?>" <?= ($_GET["programa"] ?? "") === $prog ? "selected" : "" ?>>
                                        <?= htmlspecialchars($prog) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="acao">Ação:</label>
                            <select name="acao" id="acao" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($acoes as $acao): ?>
                                    <option value="<?= htmlspecialchars($acao) ?>" <?= ($_GET["acao"] ?? "") === $acao ? "selected" : "" ?>>
                                        <?= htmlspecialchars($acao) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="categoria_economica">Categoria Econômica:</label>
                            <select name="categoria_economica" id="categoria_economica" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($categorias_economicas as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= ($_GET["categoria_economica"] ?? "") === $cat ? "selected" : "" ?>>
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="valor_de">Valor Pretendido (De):</label>
                            <input type="number" step="0.01" name="valor_de" id="valor_de" class="form-control" value="<?= htmlspecialchars($_GET["valor_de"] ?? "") ?>" placeholder="0,00">
                        </div>

                        <div class="filter-group">
                            <label for="valor_ate">Valor Pretendido (Até):</label>
                            <input type="number" step="0.01" name="valor_ate" id="valor_ate" class="form-control" value="<?= htmlspecialchars($_GET["valor_ate"] ?? "") ?>" placeholder="0,00">
                        </div>

                        <div class="filter-group">
                            <label for="ano_projeto">Ano do Projeto:</label>
                            <select name="ano_projeto" id="ano_projeto" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($anos as $ano): ?>
                                    <option value="<?= htmlspecialchars($ano) ?>" <?= ($_GET["ano_projeto"] ?? "") == $ano ? "selected" : "" ?>>
                                        <?= htmlspecialchars($ano) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group" id="outros_recursos_filter" style="display: none;">
                            <label for="outros_recursos">Outros Recursos:</label>
                            <select name="outros_recursos" id="outros_recursos" class="form-control">
                                <option value="">Todos</option>
                                <option value="1" <?= ($_GET["outros_recursos"] ?? "") === "1" ? "selected" : "" ?>>Sim</option>
                                <option value="0" <?= ($_GET["outros_recursos"] ?? "") === "0" ? "selected" : "" ?>>Não</option>
                            </select>
                        </div>

                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons">search</i> Filtrar
                        </button>
                        <a href="user_dashboard.php" class="btn btn-secondary">
                            <i class="material-icons">clear</i> Limpar Filtros
                        </a>
                    </div>
                </form>
            </section>

            <!-- Emendas Section -->
            <section class="emendas-section">
                <h2>
                    <i class="material-icons">list</i> Emendas Disponíveis
                </h2>
                <div class="table-container">
                    <table class="emendas-table">
                        <thead>
                            <tr>
                                <th>Tipo de Emenda</th>
                                <th>Eixo Temático</th>
                                <th>Órgão</th>
                                <th>Objeto de Intervenção Pública</th>
                                <th>ODS</th>
                                <th>Regionalização</th>
                                <th>Unidade Orçamentária Federal</th>
                                <th>Programa</th>
                                <th>Ação</th>
                                <th>Categoria Econômica</th>
                                <th>Valor Pretendido</th>
                                <th>Justificativa</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($emendas)): ?>
                                <tr>
                                    <td colspan="14" style="text-align: center; padding: 2rem;">
                                        <i class="material-icons" style="font-size: 3rem; color: #ccc;">inbox</i>
                                        <p>Nenhuma emenda encontrada com os filtros aplicados.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($emendas as $emenda): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($emenda["tipo_emenda"] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($emenda["eixo_tematico"] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($emenda["orgao"] ?? '-') ?></td>
                                        <td class="description" title="<?= htmlspecialchars($emenda["objeto_intervencao"] ?? '-') ?>">
                                            <?= htmlspecialchars(substr($emenda["objeto_intervencao"] ?? '-', 0, 120)) ?><?= strlen($emenda["objeto_intervencao"] ?? '') > 120 ? "..." : "" ?>
                                        </td>
                                        <td><?= htmlspecialchars($emenda["ods"] ?? "-") ?></td>
                                        <td><?= htmlspecialchars($emenda["regionalizacao"] ?? "-") ?></td>
                                        <td><?= htmlspecialchars($emenda["unidade_orcamentaria"] ?? "-") ?></td>
                                        <td><?= htmlspecialchars($emenda["programa"] ?? "-") ?></td>
                                        <td><?= htmlspecialchars($emenda["acao"] ?? "-") ?></td>
                                        <td><?= htmlspecialchars($emenda["categoria_economica"] ?? "-") ?></td>
                                        <td>R$ <?= isset($emenda["valor"]) ? number_format((float)$emenda["valor"], 2, ",", ".") : "-" ?></td>
                                        <td class="description" title="<?= htmlspecialchars($emenda["justificativa"] ?? '') ?>">
                                            <?= htmlspecialchars(substr($emenda["justificativa"] ?? '-', 0, 100)) ?><?= strlen($emenda["justificativa"] ?? '') > 100 ? "..." : "" ?>
                                        </td>
                                        <td><?= isset($emenda["criado_em"]) ? date('d/m/Y', strtotime($emenda["criado_em"])) : '-' ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="visualizar_emenda.php?id=<?= $emenda["id"] ?>" class="btn btn-primary btn-sm">
                                                    <i class="material-icons">visibility</i> Ver
                                                </a>

                                                <?php if (in_array($emenda["id"], $user_emenda_ids)): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="emenda_id" value="<?= $emenda["id"] ?>">
                                                        <input type="hidden" name="action" value="remove">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="material-icons">remove</i> Remover
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="emenda_id" value="<?= $emenda["id"] ?>">
                                                        <input type="hidden" name="action" value="add">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="material-icons">add</i> Adicionar
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_paginas > 1): ?>
                    <div class="pagination">
                        <?php if ($pagina_atual > 1): ?>
                            <a href="?pagina=<?= $pagina_atual - 1 ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                <i class="material-icons">chevron_left</i> Anterior
                            </a>
                        <?php endif; ?>

                        <?php $start = max(1, $pagina_atual - 2); $end = min($total_paginas, $pagina_atual + 2); for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i == $pagina_atual): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?pagina=<?= $i ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) ?>"> <?= $i ?> </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($pagina_atual < $total_paginas): ?>
                            <a href="?pagina=<?= $pagina_atual + 1 ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) ?>"> Próxima <i class="material-icons">chevron_right</i> </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </section>

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

// Mostrar/ocultar filtro "Outros Recursos" baseado no tipo de caderno
document.addEventListener('DOMContentLoaded', function() {
    const tipoSelect = document.getElementById('tipo_caderno');
    const outrosRecursosFilter = document.getElementById('outros_recursos_filter');
    if (!tipoSelect) return;

    function toggleOutros() {
        const v = tipoSelect.value;
        if (v === 'OPERAÇÃO DE CRÉDITO' || v === 'OUTROS RECURSOS') {
            outrosRecursosFilter.style.display = 'block';
        } else {
            outrosRecursosFilter.style.display = 'none';
            const sel = document.getElementById('outros_recursos');
            if (sel) sel.value = '';
        }
    }

    tipoSelect.addEventListener('change', toggleOutros);
    // Inicial
    toggleOutros();
});

// Fechar sidebar ao clicar fora (mobile)
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    if (window.innerWidth <= 768 && sidebar && menuToggle && !sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('active')) {
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
