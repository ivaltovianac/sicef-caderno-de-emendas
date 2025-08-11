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

// Configurações de paginação
$itens_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Função para exportar para Excel
function exportToExcel($data) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Cabeçalhos
    $sheet->setCellValue("A1", "Tipo")
          ->setCellValue("B1", "Eixo Temático")
          ->setCellValue("C1", "Unidade")
          ->setCellValue("D1", "Objeto")
          ->setCellValue("E1", "Valor Total")
          ->setCellValue("F1", "Valor Destinado")
          ->setCellValue("G1", "Valor Disponível")
          ->setCellValue("H1", "ODS")
          ->setCellValue("I1", "Ano")
          ->setCellValue("J1", "Data Criação");
    
    // Dados
    $row = 2;
    foreach ($data as $emenda) {
        $valor_destinado = $emenda["valor_destinado"] ?? 0;
        $valor_disponivel = $emenda["valor"] - $valor_destinado;
        
        $sheet->setCellValue("A". $row, $emenda["tipo_emenda"])
              ->setCellValue("B". $row, $emenda["eixo_tematico"])
              ->setCellValue("C". $row, $emenda["orgao"])
              ->setCellValue("D". $row, $emenda["objeto_intervencao"])
              ->setCellValue("E". $row, $emenda["valor"])
              ->setCellValue("F". $row, $valor_destinado)
              ->setCellValue("G". $row, $valor_disponivel)
              ->setCellValue("H". $row, $emenda["ods"] ?? "-")
              ->setCellValue("I". $row, date("Y", strtotime($emenda["criado_em"])))
              ->setCellValue("J". $row, date("d/m/Y H:i", strtotime($emenda["criado_em"])));
        $row++;
    }
    
    // Formatar
    $sheet->getStyle("A1:J1")->getFont()->setBold(true);
    foreach(range("A","J") as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Configurar download
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment;filename=\"minhas_emendas.xlsx\"");
    header("Cache-Control: max-age=0");
    
    $writer = new Xlsx($spreadsheet);
    $writer->save("php://output");
    exit;
}

// Função para exportar para PDF
function exportToPDF($data) {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, "UTF-8", false);
    
    $pdf->SetCreator("Sistema CICEF");
    $pdf->SetAuthor("Minhas Emendas");
    $pdf->SetTitle("Relatório de Minhas Emendas");
    
    $pdf->SetHeaderData("", 0, "Relatório de Minhas Emendas", "Gerado em " . date("d/m/Y H:i"));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, "", PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, "", PDF_FONT_SIZE_DATA));
    
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, 15, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->SetFont("helvetica", "", 10);
    $pdf->AddPage();
    
    // HTML content
    $html = "<h2>Relatório de Minhas Emendas</h2>
            <table border=\"1\" cellpadding=\"4\">
                <tr style=\"background-color:#007b5e;color:white;\">
                    <th width=\"12%\">Tipo</th>
                    <th width=\"12%\">Eixo</th>
                    <th width=\"12%\">Unidade</th>
                    <th width=\"18%\">Objeto</th>
                    <th width=\"10%\">Valor Total</th>
                    <th width=\"10%\">Destinado</th>
                    <th width=\"10%\">Disponível</th>
                    <th width=\"8%\">ODS</th>
                    <th width=\"8%\">Data</th>
                </tr>";
    
    foreach ($data as $emenda) {
        $valor_destinado = $emenda["valor_destinado"] ?? 0;
        $valor_disponivel = $emenda["valor"] - $valor_destinado;
        
        $html .= "<tr>
                    <td>". $emenda["tipo_emenda"]. "</td>
                    <td>". $emenda["eixo_tematico"]. "</td>
                    <td>". $emenda["orgao"]. "</td>
                    <td>". substr($emenda["objeto_intervencao"], 0, 50). "...</td>
                    <td>R$ ". number_format($emenda["valor"], 2, ",", "."). "</td>
                    <td>R$ ". number_format($valor_destinado, 2, ",", "."). "</td>
                    <td>R$ ". number_format($valor_disponivel, 2, ",", "."). "</td>
                    <td>". ($emenda["ods"] ?? "-"). "</td>
                    <td>". date("d/m/Y", strtotime($emenda["criado_em"])). "</td>
                </tr>";
    }
    
    $html .= "</table>";
    
    $pdf->writeHTML($html, true, false, true, false, "");
    $pdf->Output("minhas_emendas.pdf", "D");
    exit;
}

$usuario_id = $_SESSION["user"]["id"];

// Processar ações do usuário (remover emendas ou destinar valores)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && isset($_POST["emenda_id"])) {
    $emenda_id = $_POST["emenda_id"];
    $action = $_POST["action"];

    if ($action === "remove") {
        try {
            $stmt = $pdo->prepare("DELETE FROM usuario_emendas WHERE usuario_id = ? AND emenda_id = ?");
            $stmt->execute([$usuario_id, $emenda_id]);
            $_SESSION["message"] = "Emenda removida das suas emendas!";
        } catch (PDOException $e) {
            $_SESSION["message"] = "Erro ao remover emenda: " . $e->getMessage();
        }
    } elseif ($action === "destinar_valor") {
        $valor_destinar = floatval(str_replace([".", ","], ["", "."], $_POST["valor_destinar"]));
        
        // Obter o valor total da emenda
        $stmt_emenda = $pdo->prepare("SELECT valor FROM emendas WHERE id = ?");
        $stmt_emenda->execute([$emenda_id]);
        $emenda_info = $stmt_emenda->fetch(PDO::FETCH_ASSOC);
        $valor_emenda = $emenda_info["valor"];

        // Obter o valor já destinado pelo usuário para esta emenda
        $stmt_valor_existente = $pdo->prepare("SELECT COALESCE(SUM(valor_destinado), 0) FROM valores_destinados WHERE usuario_id = ? AND emenda_id = ?");
        $stmt_valor_existente->execute([$usuario_id, $emenda_id]);
        $valor_ja_destinado = $stmt_valor_existente->fetchColumn();
        
        $total_destinar = $valor_ja_destinado + $valor_destinar;

        if ($total_destinar > $valor_emenda) {
            $_SESSION["message"] = "Erro: O valor total destinado (R$ " . number_format($total_destinar, 2, ",", ".") . ") excede o Valor da Emenda (R$ " . number_format($valor_emenda, 2, ",", ".") . ").";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO valores_destinados (emenda_id, usuario_id, valor_destinado) VALUES (?, ?, ?)");
                $stmt->execute([$emenda_id, $usuario_id, $valor_destinar]);
                $_SESSION["message"] = "Valor de R$ " . number_format($valor_destinar, 2, ",", ".") . " destinado com sucesso!";
            } catch (PDOException $e) {
                $_SESSION["message"] = "Erro ao destinar valor: " . $e->getMessage();
            }
        }
    }
    header("Location: minhas_emendas.php");
    exit;
}

// Processar filtros
$where = ["ue.usuario_id = ?"];
$params = [$usuario_id];

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    if (!empty($_GET["tipo_caderno"])) {
        $where[] = "e.tipo_emenda = ?";
        $params[] = $_GET["tipo_caderno"];
    }
    if (!empty($_GET["eixo_tematico"]) && $_GET["eixo_tematico"] !== "Selecione") {
        $where[] = "e.eixo_tematico = ?";
        $params[] = $_GET["eixo_tematico"];
    }
    if (!empty($_GET["unidade_responsavel"]) && $_GET["unidade_responsavel"] !== "Selecione") {
        $where[] = "e.orgao = ?";
        $params[] = $_GET["unidade_responsavel"];
    }
    if (!empty($_GET["ods"]) && $_GET["ods"] !== "Selecione") {
        $where[] = "e.ods = ?";
        $params[] = $_GET["ods"];
    }
    if (!empty($_GET["regionalizacao"]) && $_GET["regionalizacao"] !== "Selecione") {
        $where[] = "e.regionalizacao = ?";
        $params[] = $_GET["regionalizacao"];
    }
    if (!empty($_GET["unidade_orcamentaria"]) && $_GET["unidade_orcamentaria"] !== "Selecione") {
        $where[] = "e.unidade_orcamentaria = ?";
        $params[] = $_GET["unidade_orcamentaria"];
    }
    if (!empty($_GET["programa"]) && $_GET["programa"] !== "Selecione") {
        $where[] = "e.programa = ?";
        $params[] = $_GET["programa"];
    }
    if (!empty($_GET["acao"]) && $_GET["acao"] !== "Selecione") {
        $where[] = "e.acao = ?";
        $params[] = $_GET["acao"];
    }
    if (!empty($_GET["categoria_economica"]) && $_GET["categoria_economica"] !== "Selecione") {
        $where[] = "e.categoria_economica = ?";
        $params[] = $_GET["categoria_economica"];
    }
    if (!empty($_GET["pontuacao_de"])) {
        $where[] = "e.pontuacao >= ?";
        $params[] = $_GET["pontuacao_de"];
    }
    if (!empty($_GET["pontuacao_ate"])) {
        $where[] = "e.pontuacao <= ?";
        $params[] = $_GET["pontuacao_ate"];
    }
    if (!empty($_GET["valor_de"])) {
        $where[] = "e.valor >= ?";
        $params[] = $_GET["valor_de"];
    }
    if (!empty($_GET["valor_ate"])) {
        $where[] = "e.valor <= ?";
        $params[] = $_GET["valor_ate"];
    }
    if (!empty($_GET["ano_projeto"])) {
        $where[] = "EXTRACT(YEAR FROM e.criado_em) = ?";
        $params[] = $_GET["ano_projeto"];
    }
    if (!empty($_GET["outros_recursos"]) && isset($_GET["tipo_caderno"]) && $_GET["tipo_caderno"] === "OPERAÇÃO DE CRÉDITO") {
        $where[] = "e.outros_recursos = ?";
        $params[] = 1;
    }

    // Processar exportação
    if (isset($_GET["export"])) {
        $export_type = $_GET["export"];
        $sql = "SELECT e.*, 
                (SELECT COALESCE(SUM(vd.valor_destinado), 0) FROM valores_destinados vd 
                 WHERE vd.emenda_id = e.id AND vd.usuario_id = ?) as valor_destinado
                FROM emendas e 
                JOIN usuario_emendas ue ON e.id = ue.emenda_id 
                WHERE " . implode(" AND ", $where) . " 
                ORDER BY e.criado_em DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$usuario_id], $params));
        $emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($export_type === "excel") {
            exportToExcel($emendas);
        } elseif ($export_type === "pdf") {
            exportToPDF($emendas);
        }
        exit;
    }
}

// Consulta para contar o total de itens
$sql_count = "SELECT COUNT(*) as total 
              FROM emendas e 
              JOIN usuario_emendas ue ON e.id = ue.emenda_id 
              WHERE " . implode(" AND ", $where);

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_itens = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

// Calcular total de páginas
$total_paginas = ceil($total_itens / $itens_por_pagina);

// Ajustar página atual se for maior que o total de páginas
if ($pagina_atual > $total_paginas && $total_paginas > 0) {
    $pagina_atual = $total_paginas;
}

// Consulta principal com todos os parâmetros posicionais
$sql = "SELECT e.*, ue.observacoes_usuario, 
        (SELECT COALESCE(SUM(vd.valor_destinado), 0) FROM valores_destinados vd 
         WHERE vd.emenda_id = e.id AND vd.usuario_id = ?) as valor_destinado
        FROM emendas e 
        JOIN usuario_emendas ue ON e.id = ue.emenda_id 
        WHERE " . implode(" AND ", $where) . " 
        ORDER BY e.criado_em DESC
        LIMIT ? OFFSET ?";

// Preparar a consulta
$stmt = $pdo->prepare($sql);

// Construir array de parâmetros
$query_params = array_merge([$usuario_id], $params);
$query_params[] = $itens_por_pagina;
$query_params[] = $offset;

// Verificação de debug (pode remover após testes)
$placeholders = substr_count($sql, '?');
if ($placeholders !== count($query_params)) {
    die("Erro: Número de placeholders ($placeholders) não corresponde ao número de parâmetros (" . count($query_params) . ")");
}

// Executar a consulta
$stmt->execute($query_params);
$emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter valores distintos para filtros (apenas das emendas do usuário)
$tipos_emenda = $pdo->prepare("SELECT DISTINCT e.tipo_emenda FROM emendas e JOIN usuario_emendas ue ON e.id = ue.emenda_id WHERE ue.usuario_id = ? ORDER BY e.tipo_emenda");
$tipos_emenda->execute([$usuario_id]);
$tipos_emenda = $tipos_emenda->fetchAll(PDO::FETCH_COLUMN);

$eixos_tematicos = $pdo->prepare("SELECT DISTINCT e.eixo_tematico FROM emendas e JOIN usuario_emendas ue ON e.id = ue.emenda_id WHERE ue.usuario_id = ? ORDER BY e.eixo_tematico");
$eixos_tematicos->execute([$usuario_id]);
$eixos_tematicos = $eixos_tematicos->fetchAll(PDO::FETCH_COLUMN);

$unidades = $pdo->prepare("SELECT DISTINCT e.orgao FROM emendas e JOIN usuario_emendas ue ON e.id = ue.emenda_id WHERE ue.usuario_id = ? ORDER BY e.orgao");
$unidades->execute([$usuario_id]);
$unidades = $unidades->fetchAll(PDO::FETCH_COLUMN);

$ods_values = $pdo->prepare("SELECT DISTINCT e.ods FROM emendas e JOIN usuario_emendas ue ON e.id = ue.emenda_id WHERE ue.usuario_id = ? ORDER BY e.ods");
$ods_values->execute([$usuario_id]);
$ods_values = $ods_values->fetchAll(PDO::FETCH_COLUMN);

$regionalizacoes = $pdo->prepare("SELECT DISTINCT e.regionalizacao FROM emendas e JOIN usuario_emendas ue ON e.id = ue.emenda_id WHERE ue.usuario_id = ? ORDER BY e.regionalizacao");
$regionalizacoes->execute([$usuario_id]);
$regionalizacoes = $regionalizacoes->fetchAll(PDO::FETCH_COLUMN);

$unidades_orcamentarias = $pdo->prepare("SELECT DISTINCT e.unidade_orcamentaria FROM emendas e JOIN usuario_emendas ue ON e.id = ue.emenda_id WHERE ue.usuario_id = ? ORDER BY e.unidade_orcamentaria");
$unidades_orcamentarias->execute([$usuario_id]);
$unidades_orcamentarias = $unidades_orcamentarias->fetchAll(PDO::FETCH_COLUMN);

$programas = $pdo->prepare("SELECT DISTINCT e.programa FROM emendas e JOIN usuario_emendas ue ON e.id = ue.emenda_id WHERE ue.usuario_id = ? ORDER BY e.programa");
$programas->execute([$usuario_id]);
$programas = $programas->fetchAll(PDO::FETCH_COLUMN);

$acoes = $pdo->prepare("SELECT DISTINCT e.acao FROM emendas e JOIN usuario_emendas ue ON e.id = ue.emenda_id WHERE ue.usuario_id = ? ORDER BY e.acao");
$acoes->execute([$usuario_id]);
$acoes = $acoes->fetchAll(PDO::FETCH_COLUMN);

$categorias_economicas = $pdo->prepare("SELECT DISTINCT e.categoria_economica FROM emendas e JOIN usuario_emendas ue ON e.id = ue.emenda_id WHERE ue.usuario_id = ? ORDER BY e.categoria_economica");
$categorias_economicas->execute([$usuario_id]);
$categorias_economicas = $categorias_economicas->fetchAll(PDO::FETCH_COLUMN);

$anos = $pdo->prepare("SELECT DISTINCT EXTRACT(YEAR FROM e.criado_em) as ano FROM emendas e JOIN usuario_emendas ue ON e.id = ue.emenda_id WHERE ue.usuario_id = ? ORDER BY ano DESC");
$anos->execute([$usuario_id]);
$anos = $anos->fetchAll(PDO::FETCH_COLUMN);

// Determinar cores do usuário baseado no tipo
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
    <title>Minhas Emendas - CICEF</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
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
        
        .user-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .user-sidebar {
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
        .user-content {
            flex: 1;
            margin-left: 250px;
            transition: all 0.3s;
            width: calc(100% - 250px);
        }
        
        /* Header */
        .user-header {
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
        
        .user-header h1 {
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
        
        /* Sections */
        .export-section, .filters-section, .emendas-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .export-section h2, .filters-section h2 {
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
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background-color: var(--error-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background-color: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
            transform: translateY(-2px);
        }
        
        /* Export Buttons */
        .export-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        /* Filters */
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }
        
        .form-control {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .filter-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        /* Table Container - Responsivo */
        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .emendas-table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
            background: white;
        }
        
        .emendas-table thead th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 1rem 0.75rem;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
        }
        
        .emendas-table tbody tr {
            transition: background-color 0.2s;
            border-bottom: 1px solid var(--border-color);
        }
        
        .emendas-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .emendas-table td {
            padding: 1rem 0.75rem;
            vertical-align: top;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .emendas-table td.description {
            max-width: 300px;
            white-space: normal;
            line-height: 1.4;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark-color);
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .pagination .current {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .user-sidebar {
                transform: translateX(-100%);
            }
            
            .user-sidebar.active {
                transform: translateX(0);
            }
            
            .user-content {
                margin-left: 0;
                width: 100%;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .user-header {
                padding: 1rem;
            }
            
            .content-area {
                padding: 1rem;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .export-buttons {
                flex-direction: column;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .emendas-table {
                min-width: 800px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .user-area {
                gap: 0.5rem;
            }
            
            .user-name {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .user-header h1 {
                font-size: 1.25rem;
            }
            
            .emendas-table {
                min-width: 600px;
            }
            
            .emendas-table th,
            .emendas-table td {
                padding: 0.5rem;
                font-size: 0.875rem;
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
    <div class="user-container">
        <!-- Sidebar -->
        <nav class="user-sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>
                    <i class="material-icons">account_circle</i>
                    Painel Usuário
                </h2>
            </div>
            <div class="sidebar-menu">
                <a href="user_dashboard.php">
                    <i class="material-icons">dashboard</i>
                    Emendas
                </a>
                <a href="minhas_emendas.php" class="active">
                    <i class="material-icons">star</i>
                    Minhas Emendas
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
        <main class="user-content">
            <!-- Header -->
            <header class="user-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="material-icons">menu</i>
                    </button>
                    <h1>Minhas Emendas</h1>
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
                        <i class="material-icons">file_download</i>
                        Exportar Dados
                    </h2>
                    <div class="export-buttons">
                        <a href="?export=excel&<?= http_build_query($_GET) ?>" class="btn btn-success">
                            <i class="material-icons">table_chart</i>
                            Exportar Excel
                        </a>
                        <a href="?export=pdf&<?= http_build_query($_GET) ?>" class="btn btn-danger">
                            <i class="material-icons">picture_as_pdf</i>
                            Exportar PDF
                        </a>
                    </div>
                </section>

                <!-- Filters Section -->
                <section class="filters-section">
                    <h2>
                        <i class="material-icons">filter_list</i>
                        Filtros
                    </h2>
                    <form method="GET" action="minhas_emendas.php">
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
                                <label for="pontuacao_de">Pontuação (De):</label>
                                <input type="number" step="0.01" name="pontuacao_de" id="pontuacao_de" class="form-control" 
                                       value="<?= htmlspecialchars($_GET["pontuacao_de"] ?? "") ?>" placeholder="0,00">
                            </div>

                            <div class="filter-group">
                                <label for="pontuacao_ate">Pontuação (Até):</label>
                                <input type="number" step="0.01" name="pontuacao_ate" id="pontuacao_ate" class="form-control" 
                                       value="<?= htmlspecialchars($_GET["pontuacao_ate"] ?? "") ?>" placeholder="0,00">
                            </div>

                            <div class="filter-group">
                                <label for="valor_de">Valor Pretendido (De):</label>
                                <input type="number" step="0.01" name="valor_de" id="valor_de" class="form-control" 
                                       value="<?= htmlspecialchars($_GET["valor_de"] ?? "") ?>" placeholder="0,00">
                            </div>

                            <div class="filter-group">
                                <label for="valor_ate">Valor Pretendido (Até):</label>
                                <input type="number" step="0.01" name="valor_ate" id="valor_ate" class="form-control" 
                                       value="<?= htmlspecialchars($_GET["valor_ate"] ?? "") ?>" placeholder="0,00">
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
                                <i class="material-icons">search</i>
                                Filtrar
                            </button>
                            <a href="minhas_emendas.php" class="btn btn-secondary">
                                <i class="material-icons">clear</i>
                                Limpar Filtros
                            </a>
                        </div>
                    </form>
                </section>

                <!-- Emendas Section -->
                <section class="emendas-section">
                    <h2>
                        <i class="material-icons">star</i>
                        Minhas Emendas Selecionadas
                    </h2>
                    
                    <div class="table-container">
                        <table class="emendas-table">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Eixo Temático</th>
                                    <th>Órgão</th>
                                    <th>Objeto de Intervenção</th>
                                    <th>Valor Total</th>
                                    <th>Valor Destinado</th>
                                    <th>Valor Disponível</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($emendas)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 2rem;">
                                            <i class="material-icons" style="font-size: 3rem; color: #ccc;">inbox</i>
                                            <p>Você ainda não selecionou nenhuma emenda.</p>
                                            <a href="user_dashboard.php" class="btn btn-primary" style="margin-top: 1rem;">
                                                <i class="material-icons">add</i>
                                                Adicionar Emendas
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($emendas as $emenda): ?>
                                        <?php
                                        $valor_destinado = $emenda["valor_destinado"] ?? 0;
                                        $valor_disponivel = $emenda["valor"] - $valor_destinado;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($emenda["tipo_emenda"]) ?></td>
                                            <td><?= htmlspecialchars($emenda["eixo_tematico"]) ?></td>
                                            <td><?= htmlspecialchars($emenda["orgao"]) ?></td>
                                            <td class="description" title="<?= htmlspecialchars($emenda["objeto_intervencao"]) ?>">
                                                <?= htmlspecialchars(substr($emenda["objeto_intervencao"], 0, 100)) ?>
                                                <?= strlen($emenda["objeto_intervencao"]) > 100 ? "..." : "" ?>
                                            </td>
                                            <td>R$ <?= number_format($emenda["valor"], 2, ",", ".") ?></td>
                                            <td>R$ <?= number_format($valor_destinado, 2, ",", ".") ?></td>
                                            <td>R$ <?= number_format($valor_disponivel, 2, ",", ".") ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="visualizar_emenda.php?id=<?= $emenda["id"] ?>" class="btn btn-primary btn-sm">
                                                        <i class="material-icons">visibility</i>
                                                        Ver
                                                    </a>
                                                    <a href="edit_emenda.php?id=<?= $emenda["id"] ?>" class="btn btn-warning btn-sm">
                                                        <i class="material-icons">edit</i>
                                                        Editar
                                                    </a>
                                                    <a href="suggest_emenda.php?id=<?= $emenda["id"] ?>" class="btn btn-secondary btn-sm">
                                                        <i class="material-icons">lightbulb</i>
                                                        Sugerir
                                                    </a>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="emenda_id" value="<?= $emenda["id"] ?>">
                                                        <input type="hidden" name="action" value="remove">
                                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja remover esta emenda?')">
                                                            <i class="material-icons">remove</i>
                                                            Remover
                                                        </button>
                                                    </form>
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
                                    <i class="material-icons">chevron_left</i>
                                    Anterior
                                </a>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $pagina_atual - 2);
                            $end = min($total_paginas, $pagina_atual + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <?php if ($i == $pagina_atual): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?pagina=<?= $i ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($pagina_atual < $total_paginas): ?>
                                <a href="?pagina=<?= $pagina_atual + 1 ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                    Próxima
                                    <i class="material-icons">chevron_right</i>
                                </a>
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

        function showOtherResources() {
            alert('Funcionalidade "Outros Recursos" em desenvolvimento.');
        }

        // Mostrar/ocultar filtro "Outros Recursos" baseado no tipo de caderno
        document.getElementById('tipo_caderno').addEventListener('change', function() {
            const outrosRecursosFilter = document.getElementById('outros_recursos_filter');
            if (this.value === 'OPERAÇÃO DE CRÉDITO') {
                outrosRecursosFilter.style.display = 'block';
            } else {
                outrosRecursosFilter.style.display = 'none';
                document.getElementById('outros_recursos').value = '';
            }
        });

        // Inicializar filtro "Outros Recursos" se já estiver selecionado
        document.addEventListener('DOMContentLoaded', function() {
            const tipoCaderno = document.getElementById('tipo_caderno').value;
            const outrosRecursosFilter = document.getElementById('outros_recursos_filter');
            
            if (tipoCaderno === 'OPERAÇÃO DE CRÉDITO') {
                outrosRecursosFilter.style.display = 'block';
            }
        });

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

