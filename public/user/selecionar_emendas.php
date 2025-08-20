<?php
/**
 * Formulário de Seleção de Emendas - SICEF
 *
 * Este arquivo permite que usuários autenticados visualizem, filtrem e selecionem emendas
 * disponíveis no sistema. Ele também permite adicionar ou remover emendas da lista do usuário.
 *
 * Funcionalidades:
 * - Listagem de emendas com paginação e filtros
 * - Adição/remoção de emendas à lista do usuário
 * - Visualização detalhada de emendas
 * - Proteção CSRF em operações de modificação
 *
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */

// Inicia a sessão para verificar se o usuário está autenticado
session_start();

// Verifica se o usuário está logado. Se não estiver, redireciona para a página de login
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

// Inclui o arquivo de configuração do banco de dados
require_once __DIR__ . '/../../config/db.php';

// Gera um token CSRF se ele ainda não existir na sessão para proteger contra ataques CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Função para normalizar uma string para comparação.
 * Remove espaços extras, converte para minúsculas e remove acentos.
 *
 * @param string $v A string a ser normalizada
 * @return string A string normalizada
 */
function normalizeKey($v)
{
    $v = trim((string) $v); // Remove espaços no início e no fim
    $v = mb_strtolower($v, 'UTF-8'); // Converte para minúsculas
    $v = iconv('UTF-8', 'ASCII//TRANSLIT', $v); // Remove acentos
    $v = preg_replace('/\s+/', ' ', $v); // Substitui múltiplos espaços por um único
    return $v;
}

/**
 * Função para carregar os valores da planilha Excel como um mapa associativo.
 * Usado como fallback para o valor pretendido das emendas.
 *
 * @param string $xlsx_path Caminho para o arquivo Excel
 * @return array Mapa de valores com chave composta por programa, ação e unidade orçamentária
 */
function loadPlanilhaMapaValores($xlsx_path)
{
    // Verifica se o arquivo existe
    if (!file_exists($xlsx_path)) {
        return [];
    }

    // Carrega a biblioteca PhpOffice\PhpSpreadsheet
    require_once __DIR__ . '/../../vendor/autoload.php';
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsx_path);
    $sheet = $spreadsheet->getActiveSheet();
    $map = [];

    // Define a linha do cabeçalho e as dimensões da planilha
    $headerRow = 1;
    $highestColumn = $sheet->getHighestColumn();
    $highestRow = $sheet->getHighestRow();

    // Lê os cabeçalhos da planilha
    $headers = $sheet->rangeToArray("A{$headerRow}:{$highestColumn}{$headerRow}", NULL, true, true, true)[$headerRow] ?? [];

    // Inicializa variáveis para armazenar as colunas relevantes
    $colPrograma = null;
    $colAcao = null;
    $colUO = null;
    $colValor = null;

    // Identifica as colunas relevantes pelos cabeçalhos
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

    // Se alguma coluna não foi encontrada, retorna um array vazio
    if (!$colPrograma || !$colAcao || !$colUO || !$colValor) {
        return [];
    }

    // Itera pelas linhas da planilha para extrair os dados
    for ($row = 2; $row <= $highestRow; $row++) {
        $programa = trim((string) $sheet->getCell($colPrograma . $row)->getValue());
        $acao = trim((string) $sheet->getCell($colAcao . $row)->getValue());
        $uo = trim((string) $sheet->getCell($colUO . $row)->getValue());
        $valor_str = trim((string) $sheet->getCell($colValor . $row)->getValue());

        // Se algum campo obrigatório estiver vazio, pula para a próxima linha
        if (empty($programa) || empty($acao) || empty($uo))
            continue;

        // Processa o valor monetário
        $valor = processarValor($valor_str);
        if ($valor <= 0)
            continue;

        // Cria uma chave única para o mapa
        $key = normalizeKey($programa) . '|' . normalizeKey($acao) . '|' . normalizeKey($uo);
        $map[$key] = $valor;
    }
    return $map;
}

/**
 * Função para processar uma string de valor monetário e convertê-la para float.
 *
 * @param string $valor_str A string do valor monetário
 * @return float O valor convertido para float
 */
function processarValor($valor_str)
{
    // Remove símbolos de moeda e espaços
    $valor_str = str_replace(['R$', ' ', 'R $'], '', $valor_str);

    // Verifica o formato do valor e o converte para float
    if (preg_match('/^\d{1,3}(\.\d{3})*,\d{2}$/', $valor_str)) {
        // Remove os pontos (separadores de milhar)
        $valor_str = str_replace('.', '', $valor_str);
        // Substitui a vírgula decimal por ponto
        $valor_str = str_replace(',', '.', $valor_str);
    } else if (strpos($valor_str, ',') === false && strpos($valor_str, '.') !== false) {
        // formato correto, não altera
    } else if (preg_match('/^\d+,\d{2}$/', $valor_str)) {
        $valor_str = str_replace(',', '.', $valor_str);
    }

    // Remove caracteres não numéricos
    $valor_str = preg_replace('/[^0-9.]/', '', $valor_str);

    // Se o valor estiver vazio ou não for numérico, retorna 0.00
    if (empty($valor_str) || !is_numeric($valor_str)) {
        return 0.00;
    }

    return (float) $valor_str;
}

/**
 * Função para formatar um valor float para exibição em formato monetário brasileiro.
 *
 * @param float $valor O valor a ser formatado
 * @return string O valor formatado
 */
function formatarValor($valor)
{
    return number_format((float) $valor, 2, ',', '.');
}

// Caminho para o arquivo Excel com os valores das emendas
$xlsx_path = __DIR__ . '/../../uploads/Cadernos_DeEmendas_.xlsx';
$planilhaValores = loadPlanilhaMapaValores($xlsx_path);

try {
    // Obtém o ID do usuário logado
    $usuario_id = $_SESSION['user']['id'];

    // Inicializa arrays para filtros e condições WHERE
    $filtros = [];
    $where_conditions = [];
    $params = [];

    // Verifica e aplica filtros baseados nos parâmetros GET
    if (!empty($_GET['tipo_emenda'])) {
        $where_conditions[] = "tipo_emenda ILIKE ?";
        $params[] = '%' . $_GET['tipo_emenda'] . '%';
        $filtros['tipo_emenda'] = $_GET['tipo_emenda'];
    }
    if (!empty($_GET['eixo_tematico'])) {
        $where_conditions[] = "eixo_tematico ILIKE ?";
        $params[] = '%' . $_GET['eixo_tematico'] . '%';
        $filtros['eixo_tematico'] = $_GET['eixo_tematico'];
    }
    if (!empty($_GET['orgao'])) {
        $where_conditions[] = "orgao ILIKE ?";
        $params[] = '%' . $_GET['orgao'] . '%';
        $filtros['orgao'] = $_GET['orgao'];
    }
    if (!empty($_GET['ods'])) {
        $where_conditions[] = "ods ILIKE ?";
        $params[] = '%' . $_GET['ods'] . '%';
        $filtros['ods'] = $_GET['ods'];
    }
    if (!empty($_GET['regionalizacao'])) {
        $where_conditions[] = "regionalizacao ILIKE ?";
        $params[] = '%' . $_GET['regionalizacao'] . '%';
        $filtros['regionalizacao'] = $_GET['regionalizacao'];
    }
    if (!empty($_GET['unidade_orcamentaria'])) {
        $where_conditions[] = "unidade_orcamentaria ILIKE ?";
        $params[] = '%' . $_GET['unidade_orcamentaria'] . '%';
        $filtros['unidade_orcamentaria'] = $_GET['unidade_orcamentaria'];
    }
    if (!empty($_GET['programa'])) {
        $where_conditions[] = "programa ILIKE ?";
        $params[] = '%' . $_GET['programa'] . '%';
        $filtros['programa'] = $_GET['programa'];
    }
    if (!empty($_GET['acao'])) {
        $where_conditions[] = "acao ILIKE ?";
        $params[] = '%' . $_GET['acao'] . '%';
        $filtros['acao'] = $_GET['acao'];
    }
    if (!empty($_GET['categoria_economica'])) {
        $where_conditions[] = "categoria_economica ILIKE ?";
        $params[] = '%' . $_GET['categoria_economica'] . '%';
        $filtros['categoria_economica'] = $_GET['categoria_economica'];
    }
    if (!empty($_GET['ano'])) {
        $where_conditions[] = "EXTRACT(YEAR FROM criado_em) = ?";
        $params[] = (int) $_GET['ano'];
        $filtros['ano'] = $_GET['ano'];
    }

    // Monta a cláusula WHERE com as condições aplicadas
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    // Busca as emendas no banco de dados com os filtros aplicados
    $sql = "SELECT * FROM emendas $where_clause ORDER BY criado_em DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Busca as emendas já selecionadas pelo usuário
    $minhas_emendas = $pdo->prepare("SELECT emenda_id FROM usuario_emendas WHERE usuario_id = ?");
    $minhas_emendas->execute([$usuario_id]);
    $minhas_emendas_ids = $minhas_emendas->fetchAll(PDO::FETCH_COLUMN);

    // Para cada emenda, aplica o valor pretendido e calcula o valor disponível
    foreach ($emendas as &$emenda) {
        $valorPretendido = $emenda['valor_pretendido'] ?? $emenda['valor'] ?? 0;
        if ($valorPretendido <= 0) {
            $key = normalizeKey($emenda['programa']) . '|' . normalizeKey($emenda['acao']) . '|' . normalizeKey($emenda['unidade_orcamentaria']);
            if (isset($planilhaValores[$key])) {
                $valorPretendido = $planilhaValores[$key];
            }
        }
        $emenda['valor_pretendido'] = $valorPretendido;

        // Calcula o valor já alocado para a emenda
        $stmt_valor_destinado = $pdo->prepare("SELECT COALESCE(SUM(valor_destinado), 0) FROM valores_destinados WHERE emenda_id = ?");
        $stmt_valor_destinado->execute([$emenda['id']]);
        $total_alocado = (float) $stmt_valor_destinado->fetchColumn();

        $emenda['total_alocado'] = $total_alocado;
        $emenda['valor_disponivel'] = max(0, $valorPretendido - $total_alocado);
    }
    unset($emenda);

    // Trata requisições POST para salvar seleções ou adicionar/remover emendas
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emendas_selecionadas'])) {
        // Verifica o token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            header('Location: user_dashboard.php');
            exit;
        }

        // Inicia uma transação para garantir a integridade dos dados
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM usuario_emendas WHERE usuario_id = ?")->execute([$usuario_id]);
        if (is_array($_POST['emendas_selecionadas'])) {
            $stmt = $pdo->prepare("INSERT INTO usuario_emendas (usuario_id, emenda_id) VALUES (?, ?)");
            foreach ($_POST['emendas_selecionadas'] as $emenda_id) {
                $stmt->execute([$usuario_id, $emenda_id]);
            }
        }
        $pdo->commit();
        $_SESSION['success'] = "Seleções atualizadas com sucesso!";
        session_regenerate_id(true);
        header('Location: user_dashboard.php');
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // Verifica o token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            header('Location: selecionar_emendas.php');
            exit;
        }

        // Adiciona ou remove uma emenda da lista do usuário
        if ($_POST['action'] === 'add_emenda' && isset($_POST['emenda_id'])) {
            $stmt = $pdo->prepare("INSERT INTO usuario_emendas (usuario_id, emenda_id) VALUES (?, ?)");
            $stmt->execute([$usuario_id, $_POST['emenda_id']]);
            $_SESSION['success'] = "Emenda adicionada com sucesso!";
            session_regenerate_id(true);
        } elseif ($_POST['action'] === 'remove_emenda' && isset($_POST['emenda_id'])) {
            $stmt = $pdo->prepare("DELETE FROM usuario_emendas WHERE usuario_id = ? AND emenda_id = ?");
            $stmt->execute([$usuario_id, $_POST['emenda_id']]);
            $_SESSION['success'] = "Emenda removida com sucesso!";
            session_regenerate_id(true);
        }
        header('Location: selecionar_emendas.php');
        exit;
    }
} catch (PDOException $e) {
    // Em caso de erro, faz rollback da transação e registra o erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro em selecionar_emendas: " . $e->getMessage());
    $_SESSION['error'] = "Erro ao processar seleção.";
    header('Location: user_dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Selecionar Emendas - SICEF</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
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
            padding: 2rem 1rem;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 1rem;
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

        /* Filters */
        .filters-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filters-card label {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            display: block;
            color: var(--dark-color);
        }

        .filters-card select {
            width: 100%;
            padding: 0.4rem 0.5rem;
            border-radius: 5px;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
        }

        .filters-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
            grid-column: 1 / -1;
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--dark-color);
        }

        .emendas-table {
            width: 100%;
            border-collapse: collapse;
        }

        .emendas-table th,
        .emendas-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #dee2e6;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .emendas-table th {
            background-color: var(--light-color);
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--dark-color);
            position: sticky;
            top: 0;
            z-index: 2;
        }

        /* Estilos para tornar a tabela responsiva em dispositivos móveis */
        @media (max-width: 768px) {

            .emendas-table,
            .emendas-table thead,
            .emendas-table tbody,
            .emendas-table th,
            .emendas-table td,
            .emendas-table tr {
                display: block;
            }

            .emendas-table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }

            .emendas-table tr {
                border: 1px solid #dee2e6;
                margin-bottom: 10px;
                padding: 10px;
                border-radius: 8px;
                background-color: #fff;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .emendas-table td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
                white-space: normal;
            }

            .emendas-table td:before {
                content: attr(data-label) ": ";
                position: absolute;
                left: 6px;
                width: 45%;
                text-align: left;
                font-weight: 600;
                color: var(--dark-color);
            }

            .emendas-table td .text-truncate {
                display: inline-block;
                width: 100%;
                text-align: right;
            }
        }

        .form-actions {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            gap: 0.3rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-outline-secondary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-outline-secondary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }

        .btn-info {
            background-color: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background-color: #138496;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1050;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
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

            .filters-card {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                max-height: 400px;
            }
        }

        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }
    </style>
</head>

<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

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
            <a href="selecionar_emendas.php" class="active">
                <span class="material-icons">search</span>
                Buscar Emendas
            </a>
            <a href="minhas_emendas.php">
                <span class="material-icons">bookmark</span>
                Minhas Emendas
                <?php if (!empty($minhas_emendas_ids)): ?>
                    <span class="badge bg-primary"><?= count($minhas_emendas_ids) ?></span>
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

    <div class="main-content" id="mainContent">
        <div class="top-bar">
            <button class="menu-toggle" id="menuToggle">
                <span class="material-icons">menu</span>
            </button>
            <h2>Selecionar Emendas</h2>
            <div class="user-info">
                <span class="material-icons">account_circle</span>
                <span>Olá, <?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" class="filters-card" onsubmit="return true;">
            <div>
                <label for="tipo_emenda">Tipo de Emenda</label>
                <select name="tipo_emenda" id="tipo_emenda">
                    <option value="">Todos</option>
                    <?php
                    $tipos = $pdo->query("SELECT DISTINCT tipo_emenda FROM emendas WHERE tipo_emenda IS NOT NULL ORDER BY tipo_emenda")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($tipos as $tipo) {
                        $selected = (isset($filtros['tipo_emenda']) && $filtros['tipo_emenda'] === $tipo) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($tipo) . "\" $selected>" . htmlspecialchars($tipo) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="eixo_tematico">Eixo Temático</label>
                <select name="eixo_tematico" id="eixo_tematico">
                    <option value="">Todos</option>
                    <?php
                    $eixos = $pdo->query("SELECT DISTINCT eixo_tematico FROM emendas WHERE eixo_tematico IS NOT NULL ORDER BY eixo_tematico")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($eixos as $eixo) {
                        $selected = (isset($filtros['eixo_tematico']) && $filtros['eixo_tematico'] === $eixo) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($eixo) . "\" $selected>" . htmlspecialchars($eixo) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="orgao">Órgão</label>
                <select name="orgao" id="orgao">
                    <option value="">Todos</option>
                    <?php
                    $orgaos = $pdo->query("SELECT DISTINCT orgao FROM emendas WHERE orgao IS NOT NULL ORDER BY orgao")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($orgaos as $orgao) {
                        $selected = (isset($filtros['orgao']) && $filtros['orgao'] === $orgao) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($orgao) . "\" $selected>" . htmlspecialchars($orgao) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="ods">ODS</label>
                <select name="ods" id="ods">
                    <option value="">Todos</option>
                    <?php
                    $ods = $pdo->query("SELECT DISTINCT ods FROM emendas WHERE ods IS NOT NULL ORDER BY ods")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($ods as $item) {
                        $selected = (isset($filtros['ods']) && $filtros['ods'] === $item) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($item) . "\" $selected>" . htmlspecialchars($item) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="regionalizacao">Regionalização</label>
                <select name="regionalizacao" id="regionalizacao">
                    <option value="">Todos</option>
                    <?php
                    $regionalizacoes = $pdo->query("SELECT DISTINCT regionalizacao FROM emendas WHERE regionalizacao IS NOT NULL ORDER BY regionalizacao")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($regionalizacoes as $item) {
                        $selected = (isset($filtros['regionalizacao']) && $filtros['regionalizacao'] === $item) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($item) . "\" $selected>" . htmlspecialchars($item) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="unidade_orcamentaria">Unidade Orçamentária</label>
                <select name="unidade_orcamentaria" id="unidade_orcamentaria">
                    <option value="">Todos</option>
                    <?php
                    $uos = $pdo->query("SELECT DISTINCT unidade_orcamentaria FROM emendas WHERE unidade_orcamentaria IS NOT NULL ORDER BY unidade_orcamentaria")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($uos as $uo) {
                        $selected = (isset($filtros['unidade_orcamentaria']) && $filtros['unidade_orcamentaria'] === $uo) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($uo) . "\" $selected>" . htmlspecialchars($uo) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="programa">Programa</label>
                <select name="programa" id="programa">
                    <option value="">Todos</option>
                    <?php
                    $programas = $pdo->query("SELECT DISTINCT programa FROM emendas WHERE programa IS NOT NULL ORDER BY programa")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($programas as $programa) {
                        $selected = (isset($filtros['programa']) && $filtros['programa'] === $programa) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($programa) . "\" $selected>" . htmlspecialchars($programa) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="acao">Ação</label>
                <select name="acao" id="acao">
                    <option value="">Todas</option>
                    <?php
                    $acoes = $pdo->query("SELECT DISTINCT acao FROM emendas WHERE acao IS NOT NULL ORDER BY acao")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($acoes as $acao) {
                        $selected = (isset($filtros['acao']) && $filtros['acao'] === $acao) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($acao) . "\" $selected>" . htmlspecialchars($acao) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="categoria_economica">Categoria Econômica</label>
                <select name="categoria_economica" id="categoria_economica">
                    <option value="">Todas</option>
                    <?php
                    $categorias = $pdo->query("SELECT DISTINCT categoria_economica FROM emendas WHERE categoria_economica IS NOT NULL ORDER BY categoria_economica")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($categorias as $cat) {
                        $selected = (isset($filtros['categoria_economica']) && $filtros['categoria_economica'] === $cat) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($cat) . "\" $selected>" . htmlspecialchars($cat) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="ano">Ano</label>
                <select name="ano" id="ano">
                    <option value="">Todos</option>
                    <?php
                    $anos = $pdo->query("SELECT DISTINCT EXTRACT(YEAR FROM criado_em) AS ano FROM emendas ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($anos as $ano) {
                        $selected = (isset($filtros['ano']) && $filtros['ano'] == $ano) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($ano) . "\" $selected>" . htmlspecialchars($ano) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="filters-actions">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons">search</span>
                    Filtrar
                </button>
                <a href="selecionar_emendas.php" class="btn btn-outline-secondary">
                    <span class="material-icons">clear</span>
                    Limpar
                </a>
            </div>
        </form>

        <!--Tabela  -->
        <div class="table-responsive">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <table class="table emendas-table">
                    <thead>
                        <tr>
                            <th>Ações</th>
                            <th>Tipo de Emenda</th>
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
                            <th>Valor Disponível (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emendas as $emenda): ?>
                            <tr>
                                <td data-label="Ações">
                                    <?php if (!in_array($emenda['id'], $minhas_emendas_ids)): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="add_emenda">
                                            <input type="hidden" name="emenda_id" value="<?= $emenda['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm" title="Selecionar Emenda">
                                                <span class="material-icons">add</span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="remove_emenda">
                                            <input type="hidden" name="emenda_id" value="<?= $emenda['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Remover Emenda">
                                                <span class="material-icons">delete</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="visualizar_emenda.php?id=<?= $emenda['id'] ?>" class="btn btn-info btn-sm"
                                        title="Visualizar Emenda">
                                        <span class="material-icons">visibility</span>
                                    </a>
                                </td>
                                <td data-label="Tipo de Emenda"><?= htmlspecialchars($emenda['tipo_emenda']) ?></td>
                                <td data-label="Eixo Temático"><?= htmlspecialchars($emenda['eixo_tematico']) ?></td>
                                <td data-label="Órgão">
                                    <div class="text-truncate" style="max-width: 150px;"
                                        title="<?= htmlspecialchars($emenda['orgao']) ?>">
                                        <?= htmlspecialchars(substr($emenda['orgao'], 0, 100)) ?>
                                        <?= strlen($emenda['orgao']) > 100 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td data-label="Objeto de Intervenção">
                                    <div class="text-truncate" style="max-width: 100px;"
                                        title="<?= htmlspecialchars($emenda['objeto_intervencao']) ?>">
                                        <?= htmlspecialchars(substr($emenda['objeto_intervencao'], 0, 100)) ?>
                                        <?= strlen($emenda['objeto_intervencao']) > 100 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td data-label="ODS">
                                    <div class="text-truncate" style="max-width: 150px;"
                                        title="<?= htmlspecialchars($emenda['ods']) ?>">
                                        <?= htmlspecialchars(substr($emenda['ods'], 0, 100)) ?>
                                        <?= strlen($emenda['ods']) > 100 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td data-label="Regionalização">
                                    <div class="text-truncate" style="max-width: 100px;"
                                        title="<?= htmlspecialchars($emenda['regionalizacao']) ?>">
                                        <?= htmlspecialchars(substr($emenda['regionalizacao'], 0, 100)) ?>
                                        <?= strlen($emenda['regionalizacao']) > 100 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td data-label="Unidade Orçamentária">
                                    <div class="text-truncate" style="max-width: 100px;"
                                        title="<?= htmlspecialchars($emenda['unidade_orcamentaria']) ?>">
                                        <?= htmlspecialchars(substr($emenda['unidade_orcamentaria'], 0, 100)) ?>
                                        <?= strlen($emenda['unidade_orcamentaria']) > 100 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td data-label="Programa">
                                    <div class="text-truncate" style="max-width: 150px;"
                                        title="<?= htmlspecialchars($emenda['programa']) ?>">
                                        <?= htmlspecialchars(substr($emenda['programa'], 0, 100)) ?>
                                        <?= strlen($emenda['programa']) > 100 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td data-label="Ação">
                                    <div class="text-truncate" style="max-width: 100px;"
                                        title="<?= htmlspecialchars($emenda['acao']) ?>">
                                        <?= htmlspecialchars(substr($emenda['acao'], 0, 100)) ?>
                                        <?= strlen($emenda['acao']) > 100 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td data-label="Categoria Econômica">
                                    <div class="text-truncate" style="max-width: 100px;"
                                        title="<?= htmlspecialchars($emenda['categoria_economica']) ?>">
                                        <?= htmlspecialchars(substr($emenda['categoria_economica'], 0, 100)) ?>
                                        <?= strlen($emenda['categoria_economica']) > 100 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td data-label="Valor Pretendido (R$)"><?= formatarValor($emenda['valor_pretendido']) ?>
                                </td>
                                <td data-label="Valor Disponível (R$)"><?= formatarValor($emenda['valor_disponivel']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-icons">save</span>
                        Salvar Seleções
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Menu toggle para mobile
        document.getElementById('menuToggle').addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        // Fecha a barra lateral ao clicar na sobreposição
        document.getElementById('sidebarOverlay').addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    </script>
</body>

</html>