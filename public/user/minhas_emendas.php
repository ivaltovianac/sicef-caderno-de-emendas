<?php
// /public/user/minhas_emendas.php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Funções para ler valor_pretendido da planilha como fallback
function normalizeKey($v)
{
    $v = trim((string) $v);
    $v = mb_strtolower($v, 'UTF-8');
    // remover acentos
    $v = iconv('UTF-8', 'ASCII//TRANSLIT', $v);
    $v = preg_replace('/\s+/', ' ', $v);
    return $v;
}

function loadPlanilhaMapaValores($xlsx_path)
{
    if (!file_exists($xlsx_path)) {
        return [];
    }

    try {
        $spreadsheet = IOFactory::load($xlsx_path);
        $sheet = $spreadsheet->getActiveSheet();
        $map = [];

        // Identificar cabeçalhos
        $headerRow = 1;
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();

        $headers = $sheet->rangeToArray("A{$headerRow}:{$highestColumn}{$headerRow}", NULL, true, true, true)[$headerRow] ?? [];

        $colPrograma = null;
        $colAcao = null;
        $colUO = null;
        $colValor = null;

        foreach ($headers as $col => $h) {
            $h = normalizeKey($h);
            if (strpos($h, 'programa') !== false)
                $colPrograma = $col;
            if (strpos($h, 'acao') !== false)
                $colAcao = $col;
            if (strpos($h, 'unidade orcamentaria') !== false)
                $colUO = $col;
            if (strpos($h, 'valor pretendido') !== false)
                $colValor = $col;
        }

        if (!$colPrograma || !$colAcao || !$colUO || !$colValor) {
            return [];
        }

        // Ler dados
        for ($row = 2; $row <= $highestRow; $row++) {
            $programa = trim((string) $sheet->getCell($colPrograma . $row)->getValue());
            $acao = trim((string) $sheet->getCell($colAcao . $row)->getValue());
            $uo = trim((string) $sheet->getCell($colUO . $row)->getValue());
            $valor_str = trim((string) $sheet->getCell($colValor . $row)->getValue());

            if (empty($programa) || empty($acao) || empty($uo))
                continue;

            // Processar valor
            $valor = processarValor($valor_str);

            if ($valor <= 0)
                continue;

            $key = normalizeKey($programa) . '|' . normalizeKey($acao) . '|' . normalizeKey($uo);
            $map[$key] = $valor;
        }

        return $map;
    } catch (Exception $e) {
        error_log("Erro ao carregar planilha: " . $e->getMessage());
        return [];
    }
}

function processarValor($valor_str)
{
    // Remover R$ e espaços
    $valor_str = str_replace(['R$', ' ', 'R $'], '', $valor_str);

    // Se for um número com vírgula como decimal e ponto como milhar
    if (preg_match('/^\d{1,3}(\.\d{3})*,\d{2}$/', $valor_str)) {
        $valor_str = str_replace(['.', ','], ['', '.'], $valor_str);
    }
    // Se for um número com ponto como decimal
    else if (strpos($valor_str, ',') === false && strpos($valor_str, '.') !== false) {
        // Já está no formato correto
    }
    // Se for um número com vírgula como decimal sem milhar
    else if (preg_match('/^\d+,\d{2}$/', $valor_str)) {
        $valor_str = str_replace(',', '.', $valor_str);
    }

    // Remove qualquer caractere não numérico exceto ponto
    $valor_str = preg_replace('/[^0-9.]/', '', $valor_str);

    // Se estiver vazio ou não for número, definir como 0
    if (empty($valor_str) || !is_numeric($valor_str)) {
        return 0.00;
    }

    return (float) $valor_str;
}

function formatarValor($valor)
{
    return number_format((float) $valor, 2, ',', '.');
}


function getValorPretendidoFromMap($map, $programa, $acao, $uo)
{
    $key = normalizeKey($programa) . '|' . normalizeKey($acao) . '|' . normalizeKey($uo);
    return $map[$key] ?? null;
}

function exportToPDF($data)
{
    // Limpa qualquer saída anterior
    if (ob_get_level()) {
        ob_end_clean();
    }

    require_once __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('SICEF');
    $pdf->SetTitle('Relatório de Minhas Emendas');
    $pdf->SetSubject('Minhas Emendas Selecionadas');
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 15, 'Relatório de Minhas Emendas', 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 8);

    foreach ($data as $e) {
        $pdf->Cell(0, 6, 'Tipo: ' . ($e['tipo_emenda'] ?? ''), 0, 1);
        $pdf->Cell(0, 6, 'Eixo: ' . ($e['eixo_tematico'] ?? ''), 0, 1);
        $pdf->Cell(0, 6, 'Objeto: ' . substr($e['objeto_intervencao'] ?? '', 0, 100), 0, 1);
        $pdf->Cell(0, 6, 'Valor Pretendido: R$ ' . (isset($e['valor_pretendido']) ? number_format((float) $e['valor_pretendido'], 2, ',', '.') : '0,00'), 0, 1);
        $pdf->Cell(0, 6, 'Valor Destinado: R$ ' . (isset($e['total_alocado']) ? number_format((float) $e['total_alocado'], 2, ',', '.') : '0,00'), 0, 1);
        $pdf->Cell(0, 6, 'Disponível: R$ ' . (isset($e['valor_disponivel']) ? number_format((float) $e['valor_disponivel'], 2, ',', '.') : '0,00'), 0, 1);
        $pdf->Ln(3);
    }

    // Define cabeçalhos para PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="minhas_emendas_' . date('Y-m-d') . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    $pdf->Output('minhas_emendas_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

// Carrega planilha uma vez por request (antes do processamento de ações)
$xlsx_path = __DIR__ . '/../../uploads/Cadernos_DeEmendas_.xlsx';
$planilhaValores = loadPlanilhaMapaValores($xlsx_path);

// Processamento de ações
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario_id = $_SESSION["user"]["id"];

    if ($_POST["action"] === "remove_emenda") {
        $emenda_id = (int) $_POST["emenda_id"];
        $stmt = $pdo->prepare("DELETE FROM usuario_emendas WHERE usuario_id = ? AND emenda_id = ?");
        $_SESSION["message"] = $stmt->execute([$usuario_id, $emenda_id]) ? "Emenda removida das suas emendas com sucesso!" : "Erro ao remover emenda.";
    } elseif ($_POST["action"] === "destinar_valor") {
        $emenda_id = (int) ($_POST["emenda_id"] ?? 0);
        // Processar valor corretamente
        $valor_destinar_str = $_POST["valor_destinar"] ?? '0';
        $valor_destinar_str = str_replace(['.', ' '], '', $valor_destinar_str);
        $valor_destinar_str = str_replace(',', '.', $valor_destinar_str);
        $valor_destinar = (float) $valor_destinar_str;

        if ($emenda_id > 0 && $valor_destinar > 0) {
            try {
                // Verifica valores para a emenda
                $stmt = $pdo->prepare("SELECT
    COALESCE(e.valor_pretendido, e.valor, 0) AS valor_pretendido,
    COALESCE((SELECT SUM(valor_destinado) FROM valores_destinados WHERE emenda_id = e.id), 0) AS total_alocado,
    e.programa, e.acao, e.unidade_orcamentaria
    FROM emendas e WHERE e.id = ?");
                $stmt->execute([$emenda_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $valor_pretendido = (float) $row['valor_pretendido'];
                    $total_alocado = (float) $row['total_alocado'];

                    // Aplica fallback na planilha
                    if ($valor_pretendido <= 0) {
                        $key = normalizeKey($row['programa']) . '|' . normalizeKey($row['acao']) . '|' . normalizeKey($row['unidade_orcamentaria']);
                        if (isset($planilhaValores[$key])) {
                            $valor_pretendido = $planilhaValores[$key];
                        }
                    }

                    $valor_disponivel = $valor_pretendido - $total_alocado;

                    if ($valor_destinar > $valor_disponivel) {
                        $_SESSION["message"] = "Valor a destinar (R$ " . number_format($valor_destinar, 2, ',', '.') . ") excede o valor disponível (R$ " . number_format($valor_disponivel, 2, ',', '.') . ").";
                    } else {
                        $ins = $pdo->prepare("INSERT INTO valores_destinados (emenda_id, usuario_id, valor_destinado, data_destinacao) VALUES (?, ?, ?, NOW())");
                        if ($ins->execute([$emenda_id, $usuario_id, $valor_destinar])) {
                            $_SESSION["message"] = "Valor de R$ " . number_format($valor_destinar, 2, ',', '.') . " destinado com sucesso!";
                        } else {
                            $_SESSION["message"] = "Erro ao destinar valor.";
                        }
                    }
                } else {
                    $_SESSION["message"] = "Emenda não encontrada.";
                }
            } catch (PDOException $e) {
                error_log("Erro ao destinar valor: " . $e->getMessage());
                $_SESSION["message"] = "Erro interno do servidor.";
            }
        } else {
            $_SESSION["message"] = "Dados inválidos para destinação.";
        }
    }

    header("Location: minhas_emendas.php");
    exit;
}

$usuario_id = $_SESSION["user"]["id"];

// Filtros com prepared statements e tipos
$filtros = [];
$where_conditions = [];
$params = [$usuario_id]; // Primeiro parâmetro sempre será o usuario_id

$base_from = "FROM usuario_emendas ue JOIN emendas e ON ue.emenda_id = e.id";
$where_clause = "WHERE ue.usuario_id = ?";

if (!empty($_GET['tipo_emenda'])) {
    $where_conditions[] = "e.tipo_emenda ILIKE ?";
    $params[] = '%' . $_GET['tipo_emenda'] . '%';
    $filtros['tipo_emenda'] = $_GET['tipo_emenda'];
}

if (!empty($_GET['eixo_tematico'])) {
    $where_conditions[] = "e.eixo_tematico ILIKE ?";
    $params[] = '%' . $_GET['eixo_tematico'] . '%';
    $filtros['eixo_tematico'] = $_GET['eixo_tematico'];
}

if (!empty($_GET['orgao'])) {
    $where_conditions[] = "e.orgao ILIKE ?";
    $params[] = '%' . $_GET['orgao'] . '%';
    $filtros['orgao'] = $_GET['orgao'];
}

if (!empty($_GET['programa'])) {
    $where_conditions[] = "e.programa ILIKE ?";
    $params[] = '%' . $_GET['programa'] . '%';
    $filtros['programa'] = $_GET['programa'];
}

if (!empty($_GET['ods'])) {
    $where_conditions[] = "e.ods ILIKE ?";
    $params[] = '%' . $_GET['ods'] . '%';
    $filtros['ods'] = $_GET['ods'];
}

if (!empty($_GET['regionalizacao'])) {
    $where_conditions[] = "e.regionalizacao ILIKE ?";
    $params[] = '%' . $_GET['regionalizacao'] . '%';
    $filtros['regionalizacao'] = $_GET['regionalizacao'];
}

if (!empty($_GET['unidade_orcamentaria'])) {
    $where_conditions[] = "e.unidade_orcamentaria ILIKE ?";
    $params[] = '%' . $_GET['unidade_orcamentaria'] . '%';
    $filtros['unidade_orcamentaria'] = $_GET['unidade_orcamentaria'];
}

if (!empty($_GET['acao'])) {
    $where_conditions[] = "e.acao ILIKE ?";
    $params[] = '%' . $_GET['acao'] . '%';
    $filtros['acao'] = $_GET['acao'];
}

if (!empty($_GET['categoria_economica'])) {
    $where_conditions[] = "e.categoria_economica ILIKE ?";
    $params[] = '%' . $_GET['categoria_economica'] . '%';
    $filtros['categoria_economica'] = $_GET['categoria_economica'];
}

if (!empty($_GET['ano'])) {
    $where_conditions[] = "EXTRACT(YEAR FROM e.criado_em) = ?";
    $params[] = (int) $_GET['ano'];
    $filtros['ano'] = $_GET['ano'];
}

if (!empty($where_conditions)) {
    $where_clause .= " AND " . implode(' AND ', $where_conditions);
}

// Aplica a lógica de busca por valor_pretendido usando planilha no filtro de busca (quando valor_pretendido é informado)
if (!empty($_GET['valor_pretendido_min']) || !empty($_GET['valor_pretendido_max'])) {
    $min = isset($_GET['valor_pretendido_min']) ? (float) str_replace([',', '.'], ['.', ''], $_GET['valor_pretendido_min']) : null;
    $max = isset($_GET['valor_pretendido_max']) ? (float) str_replace([',', '.'], ['.', ''], $_GET['valor_pretendido_max']) : null;
    // Nota: como alguns valores podem vir da planilha, é aplicado o filtro depois de recuperar os resultados (após fallback)
    $filtros['valor_pretendido_min'] = $_GET['valor_pretendido_min'] ?? '';
    $filtros['valor_pretendido_max'] = $_GET['valor_pretendido_max'] ?? '';
}

// Paginação
$itens_por_pagina = 20;
$pagina_atual = max(1, (int) ($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Count query
$sql_count = "SELECT COUNT(DISTINCT e.id) as total $base_from $where_clause";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_emendas = $stmt_count->fetchColumn();

/* Se o filtro por valor_pretendido estiver definido, faz filtragem após o fallback da planilha. 
Para fins de paginação, é limitado pelo total encontrado inicialmente e depois recalcula se necessário.*/
$total_paginas = ceil($total_emendas / $itens_por_pagina);

// Exportação
if (isset($_GET['export'])) {
    $export_sql = "SELECT e.*,
    COALESCE(e.valor_pretendido, e.valor, 0) as valor_pretendido,
    COALESCE((SELECT SUM(valor_destinado) FROM valores_destinados WHERE emenda_id = e.id AND usuario_id = ?), 0) as total_alocado,
    ue.observacoes_usuario
    $base_from $where_clause
    ORDER BY ue.criado_em DESC";

    $stmt_export = $pdo->prepare($export_sql);
    // Add parâmetro usuario_id para a subquery
    $export_params = array_merge([$usuario_id], $params);
    $stmt_export->execute($export_params);
    $dados_export = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

    // Aplica fallback da planilha nos dados de exportação
    foreach ($dados_export as &$emenda) {
        if ($emenda['valor_pretendido'] <= 0) {
            $key = normalizeKey($emenda['programa']) . '|' . normalizeKey($emenda['acao']) . '|' . normalizeKey($emenda['unidade_orcamentaria']);
            if (isset($planilhaValores[$key])) {
                $emenda['valor_pretendido'] = $planilhaValores[$key];
            }
        }
        $emenda['valor_disponivel'] = $emenda['valor_pretendido'] - $emenda['total_alocado'];
    }

    if ($_GET['export'] === 'pdf') {
        exportToPDF($dados_export);
    } elseif ($_GET['export'] === 'excel') {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Cabeçalhos
        $headers = ['ID', 'Tipo', 'Eixo Temático', 'Órgão', 'Objeto', 'Valor Pretendido', 'Valor Destinado', 'Valor Disponível', 'Observações'];
        $sheet->fromArray($headers, null, 'A1');

        // Dados
        $row = 2;
        foreach ($dados_export as $emenda) {
            $sheet->fromArray([
                $emenda['id'],
                $emenda['tipo_emenda'],
                $emenda['eixo_tematico'],
                $emenda['orgao'],
                $emenda['objeto_intervencao'],
                $emenda['valor_pretendido'],
                $emenda['total_alocado'],
                $emenda['valor_disponivel'],
                $emenda['observacoes_usuario']
            ], null, "A$row");
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="minhas_emendas_' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }
}

// Query principal com fallback de valores
$sql = "SELECT e.*,
    COALESCE(e.valor_pretendido, e.valor, 0) as valor_pretendido,
    COALESCE((SELECT SUM(valor_destinado) FROM valores_destinados WHERE emenda_id = e.id AND usuario_id = ?), 0) as total_alocado,
    ue.observacoes_usuario, ue.criado_em as data_selecao
    $base_from $where_clause
    ORDER BY ue.criado_em DESC
    LIMIT ? OFFSET ?";

$params_main = array_merge([$usuario_id], $params, [$itens_por_pagina, $offset]);
$stmt = $pdo->prepare($sql);
$stmt->execute($params_main);
$emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aplica fallback da planilha
foreach ($emendas as &$emenda) {
    if ($emenda['valor_pretendido'] <= 0) {
        $key = normalizeKey($emenda['programa']) . '|' . normalizeKey($emenda['acao']) . '|' . normalizeKey($emenda['unidade_orcamentaria']);
        if (isset($planilhaValores[$key])) {
            $emenda['valor_pretendido'] = $planilhaValores[$key];
        }
    }
    $emenda['valor_disponivel'] = $emenda['valor_pretendido'] - $emenda['total_alocado'];
}

// Carrega opções para filtros
$filter_base = "FROM usuario_emendas ue JOIN emendas e ON ue.emenda_id = e.id WHERE ue.usuario_id = ?";

$tipos_emenda = $pdo->prepare("SELECT DISTINCT e.tipo_emenda $filter_base AND e.tipo_emenda IS NOT NULL ORDER BY e.tipo_emenda");
$tipos_emenda->execute([$usuario_id]);
$tipos_emenda = $tipos_emenda->fetchAll(PDO::FETCH_COLUMN);

$eixos_tematicos = $pdo->prepare("SELECT DISTINCT e.eixo_tematico $filter_base AND e.eixo_tematico IS NOT NULL ORDER BY e.eixo_tematico");
$eixos_tematicos->execute([$usuario_id]);
$eixos_tematicos = $eixos_tematicos->fetchAll(PDO::FETCH_COLUMN);

$orgaos = $pdo->prepare("SELECT DISTINCT e.orgao $filter_base AND e.orgao IS NOT NULL ORDER BY e.orgao");
$orgaos->execute([$usuario_id]);
$orgaos = $orgaos->fetchAll(PDO::FETCH_COLUMN);

$ods = $pdo->prepare("SELECT DISTINCT e.ods $filter_base AND e.ods IS NOT NULL ORDER BY e.ods");
$ods->execute([$usuario_id]);
$ods = $ods->fetchAll(PDO::FETCH_COLUMN);

$regionalizacoes = $pdo->prepare("SELECT DISTINCT e.regionalizacao $filter_base AND e.regionalizacao IS NOT NULL ORDER BY e.regionalizacao");
$regionalizacoes->execute([$usuario_id]);
$regionalizacoes = $regionalizacoes->fetchAll(PDO::FETCH_COLUMN);

$unidades_orcamentarias = $pdo->prepare("SELECT DISTINCT e.unidade_orcamentaria $filter_base AND e.unidade_orcamentaria IS NOT NULL ORDER BY e.unidade_orcamentaria");
$unidades_orcamentarias->execute([$usuario_id]);
$unidades_orcamentarias = $unidades_orcamentarias->fetchAll(PDO::FETCH_COLUMN);

$programas = $pdo->prepare("SELECT DISTINCT e.programa $filter_base AND e.programa IS NOT NULL ORDER BY e.programa");
$programas->execute([$usuario_id]);
$programas = $programas->fetchAll(PDO::FETCH_COLUMN);

$acoes = $pdo->prepare("SELECT DISTINCT e.acao $filter_base AND e.acao IS NOT NULL ORDER BY e.acao");
$acoes->execute([$usuario_id]);
$acoes = $acoes->fetchAll(PDO::FETCH_COLUMN);

$categorias_economicas = $pdo->prepare("SELECT DISTINCT e.categoria_economica $filter_base AND e.categoria_economica IS NOT NULL ORDER BY e.categoria_economica");
$categorias_economicas->execute([$usuario_id]);
$categorias_economicas = $categorias_economicas->fetchAll(PDO::FETCH_COLUMN);

$anos = $pdo->prepare("SELECT DISTINCT EXTRACT(YEAR FROM e.criado_em) AS ano $filter_base ORDER BY ano DESC");
$anos->execute([$usuario_id]);
$anos = $anos->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Emendas - SICEF</title>
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

        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--dark-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .pagination .page-link {
            color: var(--primary-color);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
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

            .table-responsive {
                font-size: 0.875rem;
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

        .filters-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .valor-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .valor-pretendido {
            background-color: #e8f5e8;
            color: #155724;
        }

        .valor-destinado {
            background-color: #fff3cd;
            color: #856404;
        }

        .valor-disponivel {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>

<body>
    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4>SICEF</h4>
            <p><?= htmlspecialchars($_SESSION['user']['nome']) ?></p>
        </div>
        <nav class="sidebar-menu">
            <a href="user_dashboard.php">
                <span class="material-icons">dashboard</span>
                Dashboard
            </a>
            <a href="selecionar_emendas.php">
                <span class="material-icons">search</span>
                Buscar Emendas
            </a>
            <a href="minhas_emendas.php" class="active">
                <span class="material-icons">bookmark</span>
                Minhas Emendas
                <?php if ($total_emendas > 0): ?>
                    <span class="badge bg-primary"><?= $total_emendas ?></span>
                <?php endif; ?>
            </a>
            <a href="sugestoes_emenda.php">
                <span class="material-icons">lightbulb</span>
                Sugestões
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
            <h2>Minhas Emendas</h2>
            <div class="user-info">
                <span class="material-icons">account_circle</span>
                <span>Olá, <?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <span class="material-icons me-2">check_circle</span>
                    <?= htmlspecialchars($_SESSION['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="filters-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><span class="material-icons me-2">filter_list</span>Filtros</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="?export=excel<?= !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>"
                            class="btn btn-success btn-sm">
                            <span class="material-icons me-1">download</span>
                            Excel
                        </a>
                        <a href="?export=pdf<?= !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>"
                            class="btn btn-danger btn-sm">
                            <span class="material-icons me-1">picture_as_pdf</span>
                            PDF
                        </a>
                    </div>
                </div>
                <form method="GET" class="filters-form">
                    <div class="filters-grid">
                        <div>
                            <label class="form-label">Tipo de Emenda</label>
                            <select name="tipo_emenda" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($tipos_emenda as $tipo): ?>
                                    <option value="<?= htmlspecialchars($tipo) ?>" <?= ($filtros['tipo_emenda'] ?? '') === $tipo ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Eixo Temático</label>
                            <select name="eixo_tematico" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($eixos_tematicos as $eixo): ?>
                                    <option value="<?= htmlspecialchars($eixo) ?>" <?= ($filtros['eixo_tematico'] ?? '') === $eixo ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($eixo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Órgão</label>
                            <select name="orgao" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($orgaos as $orgao): ?>
                                    <option value="<?= htmlspecialchars($orgao) ?>" <?= ($filtros['orgao'] ?? '') === $orgao ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($orgao) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">ODS</label>
                            <select name="ods" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($ods as $ods_item): ?>
                                    <option value="<?= htmlspecialchars($ods_item) ?>" <?= ($filtros['ods'] ?? '') === $ods_item ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ods_item) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Regionalização</label>
                            <select name="regionalizacao" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($regionalizacoes as $regionalizacao): ?>
                                    <option value="<?= htmlspecialchars($regionalizacao) ?>" <?= ($filtros['regionalizacao'] ?? '') === $regionalizacao ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($regionalizacao) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Unidade Orçamentária</label>
                            <select name="unidade_orcamentaria" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($unidades_orcamentarias as $unidade): ?>
                                    <option value="<?= htmlspecialchars($unidade) ?>" <?= ($filtros['unidade_orcamentaria'] ?? '') === $unidade ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($unidade) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Programa</label>
                            <select name="programa" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($programas as $programa): ?>
                                    <option value="<?= htmlspecialchars($programa) ?>" <?= ($filtros['programa'] ?? '') === $programa ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($programa) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Ação</label>
                            <select name="acao" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach ($acoes as $acao_item): ?>
                                    <option value="<?= htmlspecialchars($acao_item) ?>" <?= ($filtros['acao'] ?? '') === $acao_item ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($acao_item) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Categoria Econômica</label>
                            <select name="categoria_economica" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach ($categorias_economicas as $categoria): ?>
                                    <option value="<?= htmlspecialchars($categoria) ?>" <?= ($filtros['categoria_economica'] ?? '') === $categoria ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($categoria) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Ano</label>
                            <select name="ano" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($anos as $ano): ?>
                                    <option value="<?= htmlspecialchars($ano) ?>" <?= ($filtros['ano'] ?? '') == $ano ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ano) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons me-1">search</span>
                            Filtrar
                        </button>
                        <a href="minhas_emendas.php" class="btn btn-outline-secondary">
                            <span class="material-icons me-1">clear</span>
                            Limpar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Lista de Emendas -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <span class="material-icons me-2">bookmark</span>
                        Minhas Emendas (<?= $total_emendas ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($emendas)): ?>
                        <div class="text-center py-5">
                            <span class="material-icons" style="font-size: 4rem; color: #ccc;">bookmark_border</span>
                            <h5 class="mt-3 text-muted">Nenhuma emenda selecionada</h5>
                            <p class="text-muted">Vá para <a href="selecionar_emendas.php">Buscar Emendas</a> para adicionar
                                emendas à sua lista.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ações</th>
                                        <th>Tipo</th>
                                        <th>Eixo Temático</th>
                                        <th>Órgão</th>
                                        <th>Objeto de Intervenção</th>
                                        <th>ODS</th>
                                        <th>Regionalização</th>
                                        <th>Unidade Orçamentária</th>
                                        <th>Programa</th>
                                        <th>Ação</th>
                                        <th>Categoria Econômica</th>
                                        <th>Valor Pretendido (R$)</th>
                                        <th>Valor Destinado (R$)</th>
                                        <th>Progresso</th>
                                        <th>Disponível (R$)</th>
                                        <th>Justificativa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($emendas as $emenda): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a class="btn btn-outline-primary btn-sm"
                                                        href="visualizar_emenda.php?id=<?= $emenda['id'] ?>" title="Ver">
                                                        <span class="material-icons">visibility</span>
                                                    </a>
                                                    <?php if (($emenda['valor_disponivel'] ?? 0) > 0): ?>
                                                        <button type="button" class="btn btn-success btn-sm" title="Destinar Valor"
                                                            onclick="destinarValor(<?= $emenda['id'] ?>, <?= (float) $emenda['valor_disponivel'] ?>)">
                                                            <span class="material-icons">attach_money</span>
                                                        </button>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display: inline;"
                                                        onsubmit="return confirm('Tem certeza que deseja remover esta emenda?')">
                                                        <input type="hidden" name="action" value="remove_emenda">
                                                        <input type="hidden" name="emenda_id" value="<?= $emenda['id'] ?>">
                                                        <input type="hidden" name="csrf_token"
                                                            value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Remover">
                                                            <span class="material-icons">delete</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($emenda["tipo_emenda"] ?? "-") ?></td>
                                            <td><?= htmlspecialchars($emenda["eixo_tematico"] ?? "-") ?></td>
                                            <td><?= htmlspecialchars($emenda["orgao"] ?? "-") ?></td>
                                            <td><?= htmlspecialchars(substr($emenda["objeto_intervencao"] ?? '', 0, 80)) . (strlen($emenda["objeto_intervencao"] ?? '') > 80 ? "..." : "") ?>
                                            </td>
                                            <td><?= htmlspecialchars($emenda["ods"] ?? "-") ?></td>
                                            <td><?= htmlspecialchars($emenda["regionalizacao"] ?? "-") ?></td>
                                            <td><?= htmlspecialchars($emenda["unidade_orcamentaria"] ?? "-") ?></td>
                                            <td><?= htmlspecialchars($emenda["programa"] ?? "-") ?></td>
                                            <td><?= htmlspecialchars($emenda["acao"] ?? "-") ?></td>
                                            <td><?= htmlspecialchars($emenda["categoria_economica"] ?? "-") ?></td>
                                            <td>R$
                                                <?= isset($emenda["valor_pretendido"]) ? formatarValor($emenda["valor_pretendido"]) : "-" ?>
                                            </td>
                                            <td>R$ <?= formatarValor($emenda["total_alocado"] ?? 0) ?></td>
                                            <td>
                                                <div class="progress-wrap" title="<?= $pct ?>% alocado">
                                                    <?php
                                                    $vp = (float) ($emenda['valor_pretendido'] ?? 0);
                                                    $ta = (float) ($emenda['total_alocado'] ?? 0);
                                                    $pct = $vp > 0 ? min(100, max(0, round(($ta / $vp) * 100))) : 0;
                                                    $barColor = $pct >= 100 ? '#2ecc71' : ($pct >= 75 ? '#f39c12' : '#3498db');
                                                    ?>
                                                    <div class="progress-bar"
                                                        style="width: <?= $pct ?>%; background: <?= $barColor ?>;"></div>
                                                </div>
                                                <small><?= $pct ?>%</small>
                                            </td>
                                            <td>R$ <?= formatarValor($emenda["valor_disponivel"] ?? 0) ?></td>
                                            <td class="description"
                                                title="<?= htmlspecialchars($emenda["justificativa"] ?? '') ?>">
                                                <?= htmlspecialchars(substr($emenda["justificativa"] ?? '-', 0, 80)) ?>
                                                <?= strlen($emenda["justificativa"] ?? '') > 80 ? "..." : "" ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <?php if ($total_paginas > 1): ?>
                            <div class="d-flex justify-content-center p-3">
                                <nav>
                                    <ul class="pagination">
                                        <?php if ($pagina_atual > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link"
                                                    href="?pagina=<?= $pagina_atual - 1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/&?pagina=\d+/', '', $_SERVER['QUERY_STRING']) : '' ?>">
                                                    Anterior
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                                            <li class="page-item <?= $i === $pagina_atual ? 'active' : '' ?>">
                                                <a class="page-link"
                                                    href="?pagina=<?= $i ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/&?pagina=\d+/', '', $_SERVER['QUERY_STRING']) : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($pagina_atual < $total_paginas): ?>
                                            <li class="page-item">
                                                <a class="page-link"
                                                    href="?pagina=<?= $pagina_atual + 1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . preg_replace('/&?pagina=\d+/', '', $_SERVER['QUERY_STRING']) : '' ?>">
                                                    Próxima
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Destinar Valor -->
    <div class="modal fade" id="destinarValorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Destinar Valor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="destinar_valor">
                        <input type="hidden" name="emenda_id" id="destinar_emenda_id">

                        <div class="mb-3">
                            <label class="form-label">Valor Máximo Disponível</label>
                            <div class="form-control-plaintext fw-bold text-success" id="valor_maximo">R$ 0,00</div>
                        </div>

                        <div class="mb-3">
                            <label for="valor_destinar" class="form-label">Valor a Destinar</label>
                            <input type="text" class="form-control" id="valor_destinar" name="valor_destinar"
                                placeholder="0,00" required>
                            <div class="form-text">Digite o valor no formato: 1.000,00</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Destinar Valor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Menu toggle responsivo
        document.getElementById('menuToggle').addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        // Fecha sidebar ao clicar no overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });

        // Funções auxiliares
        function confirmDelete(message) {
            return confirm(message);
        }

        function destinarValor(emendaId, valorDisponivel) {
            document.getElementById('destinar_emenda_id').value = emendaId;
            document.getElementById('valor_maximo').textContent = valorDisponivel.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            new bootstrap.Modal(document.getElementById('destinarValorModal')).show();
        }

        // Máscara para valor monetário
        document.getElementById('valor_destinar').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            value = (value / 100).toFixed(2) + '';
            value = value.replace(".", ",");
            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
            e.target.value = value;
        });
    </script>
</body>

</html>