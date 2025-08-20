<?php
/**
 * Dashboard Administrativo - SICEF
 * 
 * Este arquivo é responsável por exibir o painel administrativo do sistema SICEF.
 * Ele permite ao administrador visualizar estatísticas, gerenciar emendas, 
 * responder sugestões e sincronizar dados com a planilha oficial.
 * 
 * Funcionalidades:
 * - Exibição de estatísticas do sistema
 * - Listagem de emendas com filtros e paginação
 * - Exportação de dados para Excel
 * - Resposta a sugestões de usuários
 * - Sincronização com planilha Excel
 * - Gerenciamento de solicitações de acesso
 * 
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */

// Inicia a sessão para verificar se o usuário está logado e é administrador
session_start();

// Verifica se o usuário está logado e se é administrador
if (!isset($_SESSION["user"]) || !$_SESSION["user"]["is_admin"]) {
    // Redireciona para a página de login caso não seja administrador
    header("Location: ../login.php");
    exit;
}

// Inclui os arquivos de configuração do banco de dados e sincronizador
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../config/sincronizador.php";

// Inclui o autoloader do Composer para carregar as bibliotecas
require_once __DIR__ . "/../../vendor/autoload.php";

// Importa as classes necessárias do PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Verifica se a classe TCPDF está disponível
if (!class_exists('TCPDF')) {
    die('TCPDF não está instalado. Por favor, instale via composer: composer require tecnickcom/tcpdf');
}

// Conta o número de solicitações de acesso pendentes
$stmt_solicitacoes = $pdo->query("SELECT COUNT(*) FROM solicitacoes_acesso WHERE status = 'pendente'");
$solicitacoes_pendentes = (int) $stmt_solicitacoes->fetchColumn();

// Conta o número de sugestões pendentes
$stmt_sugestoes = $pdo->query("SELECT COUNT(*) FROM sugestoes_emendas WHERE status = 'pendente'");
$qtde_sugestoes_pendentes = (int) $stmt_sugestoes->fetchColumn();

// Define os campos editáveis para sugestões
$campos_editaveis = [
    'objeto_intervencao' => 'Objeto de Intervenção',
    'valor' => 'Valor',
    'eixo_tematico' => 'Eixo Temático',
    'orgao' => 'Unidade Responsável',
    'ods' => 'ODS',
    'pontuacao' => 'Pontuação'
];

// Processa a sincronização dos dados com a planilha
if (isset($_GET['sincronizar'])) {
    // Cria uma instância do sincronizador
    $sincronizador = new SincronizadorEmendas(
        $pdo,
        __DIR__ . '/../../uploads/Cadernos_DeEmendas_.xlsx'
    );
    // Executa a sincronização
    $resultado = $sincronizador->sincronizar();
    // Armazena a mensagem de resultado na sessão
    $_SESSION['mensagem_sincronizacao'] = $resultado['message'];
    // Redireciona para a página do dashboard
    header('Location: admin_dashboard.php');
    exit;
}

// Processa a resposta a uma sugestão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'responder_sugestao') {
        // Obtém os dados do formulário
        $sugestao_id = $_POST['sugestao_id'];
        $resposta = $_POST['resposta'];
        $status = $_POST['status'];

        try {
            // Atualiza o status e a resposta da sugestão
            $stmt = $pdo->prepare("UPDATE sugestoes_emendas SET status = ?, resposta = ?, respondido_em = NOW(), respondido_por = ? WHERE id = ?");
            $stmt->execute([$status, $resposta, $_SESSION['user']['id'], $sugestao_id]);

            // Insere uma notificação para o usuário que fez a sugestão
            $stmt_notif = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem, referencia_id, criado_em) SELECT usuario_id, 'resposta_sugestao', ?, id, NOW() FROM sugestoes_emendas WHERE id = ?");
            $mensagem = "Sua sugestão #$sugestao_id foi $status";
            $stmt_notif->execute([$mensagem, $sugestao_id]);

            // Armazena uma mensagem de sucesso na sessão
            $_SESSION['message'] = "Resposta enviada com sucesso!";
            // Redireciona para a página do dashboard
            header('Location: admin_dashboard.php');
            exit;
        } catch (PDOException $e) {
            // Armazena uma mensagem de erro na sessão
            $_SESSION['message'] = "Erro ao responder sugestão: " . $e->getMessage();
        }
    }
}

// Função para ler a planilha Excel
// function lerPlanilhaEmendas($caminhoArquivo)
// {
//     try {
//         // Verifica se o arquivo existe
//         if (!file_exists($caminhoArquivo)) {
//             throw new Exception("Arquivo não encontrado: " . $caminhoArquivo);
//         }
//         // Carrega a planilha
//         $spreadsheet = IOFactory::load($caminhoArquivo);
//         $sheet = $spreadsheet->getActiveSheet();
//         $emendas = [];

//         // Itera pelas linhas da planilha
//         foreach ($sheet->getRowIterator(2) as $row) {
//             $cells = [];
//             foreach ($row->getCellIterator() as $cell) {
//                 $cells[] = $cell->getValue();
//             }

//             // Verifica se a linha não está vazia
//             if (!empty($cells[0])) {
//                 $valor = $cells[10] ?? '0';
//                 if (is_string($valor)) {
//                     // Remove o símbolo R$ e espaços
//                     $valor = str_replace(['R$', ' '], '', $valor);
//                     // Remove os pontos separadores de milhar
//                     $valor = str_replace('.', '', $valor);
//                     // Substitui a vírgula decimal por ponto
//                     $valor = str_replace(',', '.', $valor);
//                     // Remove qualquer caractere que não seja número ou ponto
//                     $valor = preg_replace('/[^0-9.]/', '', $valor);
//                 }

//                 // Adiciona os dados da emenda ao array
//                 $emendas[] = [
//                     'tipo_emenda' => $cells[0] ?? '',
//                     'eixo_tematico' => $cells[1] ?? '',
//                     'orgao' => $cells[2] ?? '',
//                     'objeto_intervencao' => $cells[3] ?? '',
//                     'ods' => $cells[4] ?? '',
//                     'regionalizacao' => $cells[5] ?? '',
//                     'unidade_orcamentaria' => $cells[6] ?? '',
//                     'programa' => $cells[7] ?? '',
//                     'acao' => $cells[8] ?? '',
//                     'categoria_economica' => $cells[9] ?? '',
//                     'valor' => (float) $valor,
//                     'justificativa' => $cells[11] ?? ''
//                 ];
//             }
//         }
//         return $emendas;
//     } catch (Exception $e) {
//         // Registra o erro no log
//         error_log("Erro ao ler planilha: " . $e->getMessage());
//         return [];
//     }
// }

function lerPlanilhaEmendas($caminhoArquivo)
{
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
                $valor = $cells[10] ?? '0';
                if (is_string($valor)) {
                    $valor = str_replace(['R$', ' '], '', $valor);
                    $valor = str_replace('.', '', $valor);
                    $valor = str_replace(',', '.', $valor);
                    $valor = preg_replace('/[^0-9.]/', '', $valor);
                }

                $emendas[] = [
                    'tipo_emenda' => $cells[0] ?? '',
                    'eixo_tematico' => $cells[1] ?? '',
                    'orgao' => $cells[2] ?? '',
                    'objeto_intervencao' => $cells[3] ?? '',
                    'ods' => $cells[4] ?? '',
                    'regionalizacao' => $cells[5] ?? '',
                    'unidade_orcamentaria' => $cells[6] ?? '',
                    'programa' => $cells[7] ?? '',
                    'acao' => $cells[8] ?? '',
                    'categoria_economica' => $cells[9] ?? '',
                    'valor' => (float) $valor,
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

// Filtros com prepared statements
$where_conditions = [];
$params = [];

// Adiciona condições de filtro com base nos parâmetros GET
if (!empty($_GET['tipo_caderno'])) {
    $where_conditions[] = "tipo_emenda ILIKE ?";
    $params[] = '%' . $_GET['tipo_caderno'] . '%';
}

if (!empty($_GET['eixo_tematico'])) {
    $where_conditions[] = "eixo_tematico ILIKE ?";
    $params[] = '%' . $_GET['eixo_tematico'] . '%';
}

if (!empty($_GET['unidade_responsavel'])) {
    $where_conditions[] = "orgao ILIKE ?";
    $params[] = '%' . $_GET['unidade_responsavel'] . '%';
}

if (!empty($_GET['ods'])) {
    $where_conditions[] = "ods ILIKE ?";
    $params[] = '%' . $_GET['ods'] . '%';
}

if (!empty($_GET['programa'])) {
    $where_conditions[] = "programa ILIKE ?";
    $params[] = '%' . $_GET['programa'] . '%';
}

if (!empty($_GET['ano_projeto'])) {
    $where_conditions[] = "EXTRACT(YEAR FROM criado_em) = ?";
    $params[] = (int) $_GET['ano_projeto'];
}

// Monta a cláusula WHERE
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Paginação
$itens_por_pagina = 10;
$pagina_atual = max(1, (int) ($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Query para contar o número total de emendas
$count_query = "SELECT COUNT(*) FROM emendas $where_clause";
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_emendas = $stmt_count->fetchColumn();
$total_paginas = ceil($total_emendas / $itens_por_pagina);

// Query principal para buscar as emendas
$query = "SELECT * FROM emendas $where_clause ORDER BY criado_em DESC LIMIT ? OFFSET ?";
$params[] = $itens_por_pagina;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Carrega as opções para os filtros
try {
    $tipos_emenda = $pdo->query("SELECT DISTINCT tipo_emenda FROM emendas WHERE tipo_emenda IS NOT NULL ORDER BY tipo_emenda")->fetchAll(PDO::FETCH_COLUMN);
    $eixos_tematicos = $pdo->query("SELECT DISTINCT eixo_tematico FROM emendas WHERE eixo_tematico IS NOT NULL ORDER BY eixo_tematico")->fetchAll(PDO::FETCH_COLUMN);
    $unidades = $pdo->query("SELECT DISTINCT orgao FROM emendas WHERE orgao IS NOT NULL ORDER BY orgao")->fetchAll(PDO::FETCH_COLUMN);
    $ods_values = $pdo->query("SELECT DISTINCT ods FROM emendas WHERE ods IS NOT NULL ORDER BY ods")->fetchAll(PDO::FETCH_COLUMN);
    $regionalizacoes = $pdo->query("SELECT DISTINCT regionalizacao FROM emendas WHERE regionalizacao IS NOT NULL ORDER BY regionalizacao")->fetchAll(PDO::FETCH_COLUMN);
    $unidades_orcamentarias = $pdo->query("SELECT DISTINCT unidade_orcamentaria FROM emendas WHERE unidade_orcamentaria IS NOT NULL ORDER BY unidade_orcamentaria")->fetchAll(PDO::FETCH_COLUMN);
    $programas = $pdo->query("SELECT DISTINCT programa FROM emendas WHERE programa IS NOT NULL ORDER BY programa")->fetchAll(PDO::FETCH_COLUMN);
    $acoes = $pdo->query("SELECT DISTINCT acao FROM emendas WHERE acao IS NOT NULL ORDER BY acao")->fetchAll(PDO::FETCH_COLUMN);
    $categorias_economicas = $pdo->query("SELECT DISTINCT categoria_economica FROM emendas WHERE categoria_economica IS NOT NULL ORDER BY categoria_economica")->fetchAll(PDO::FETCH_COLUMN);
    $anos = $pdo->query("SELECT DISTINCT EXTRACT(YEAR FROM criado_em) AS ano FROM emendas ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Registra o erro no log
    error_log("Erro ao carregar filtros: " . $e->getMessage());
    $tipos_emenda = $eixos_tematicos = $unidades = $ods_values = $regionalizacoes = $unidades_orcamentarias = $programas = $acoes = $categorias_economicas = $anos = [];
}

// Carrega as sugestões pendentes para exibição
try {
    $stmt_sugestoes_detalhes = $pdo->prepare("
        SELECT s.*, u.nome as usuario_nome, e.objeto_intervencao 
        FROM sugestoes_emendas s 
        JOIN usuarios u ON s.usuario_id = u.id 
        JOIN emendas e ON s.emenda_id = e.id 
        WHERE s.status = 'pendente' 
        ORDER BY s.criado_em DESC 
        LIMIT 2
    ");
    $stmt_sugestoes_detalhes->execute();
    $sugestoes_pendentes = $stmt_sugestoes_detalhes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Registra o erro no log
    error_log("Erro ao carregar sugestões: " . $e->getMessage());
    $sugestoes_pendentes = [];
}

// Exporta os dados para Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Evita qualquer saída anterior que possa corromper o arquivo
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Executa a consulta
    $export_query = "SELECT * FROM emendas $where_clause ORDER BY criado_em DESC";
    $stmt_export = $pdo->prepare($export_query);
    $stmt_export->execute(array_slice($params, 0, -2)); // Remove LIMIT e OFFSET
    $dados_export = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

    // Cria a planilha
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Cabeçalhos
    $headers = [
        'ID',
        'Tipo',
        'Eixo Temático',
        'Órgão',
        'Objeto',
        'ODS',
        'Valor',
        'Justificativa',
        'Regionalização',
        'Unidade Orçamentária',
        'Programa',
        'Ação',
        'Categoria Econômica',
        'Criado em'
    ];
    $sheet->fromArray($headers, null, 'A1');

    // Estilo para cabeçalhos
    $estiloCabecalho = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 12,
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4F81BD'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ];
    $sheet->getStyle('A1:O1')->applyFromArray($estiloCabecalho);

    // Função para truncar texto longo
    function limitarTexto($texto, $limite = 500)
    {
        return mb_substr(trim((string) $texto), 0, $limite);
    }

    // Preenche os dados
    $row = 2;
    foreach ($dados_export as $emenda) {
        $sheet->fromArray([
            $emenda['id'],
            limitarTexto($emenda['tipo_emenda'], 150),
            limitarTexto($emenda['eixo_tematico'], 200),
            limitarTexto($emenda['orgao'], 255),
            limitarTexto($emenda['objeto_intervencao'], 1000),
            limitarTexto($emenda['ods'], 100),
            $emenda['valor'],
            limitarTexto($emenda['justificativa'], 1000),
            limitarTexto($emenda['regionalizacao'], 255),
            limitarTexto($emenda['unidade_orcamentaria'], 200),
            limitarTexto($emenda['programa'], 100),
            limitarTexto($emenda['acao'], 200),
            limitarTexto($emenda['categoria_economica'], 200),
            $emenda['criado_em']
        ], null, "A$row");
        $row++;
    }

    // Estilo para dados
    $estiloDados = [
        'alignment' => [
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC'],
            ],
        ],
    ];
    $sheet->getStyle("A2:O$row")->applyFromArray($estiloDados);

    // Ajusta largura das colunas
    foreach (range('A', 'O') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Congela a primeira linha
    $sheet->freezePane('A2');

    // Adiciona filtro automático
    $sheet->setAutoFilter("A1:O1");

    // Gera e envia o arquivo Excel
    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="emendas_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo - SICEF</title>
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
            justify-content: between;
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

        .material-icons {
            font-family: 'Material Icons';
            font-weight: normal;
            font-style: normal;
            font-size: 24px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            vertical-align: middle;
            font-size: 1rem;
            -webkit-font-feature-settings: 'liga';
            -webkit-font-smoothing: antialiased;
        }

        .btn-light {
            color: #000;
        }

        .btn .btn-light,
        .btn-sm:hover {
            background: #c3d762;
            /* background-color: var(--secondary-color); */
            /* border-color: var(--secondary-color); */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.5s ease;
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

        .stat-icon.warning {
            background: #1987543d;
            color: #ffc107;
        }

        .stat-icon.info {
            background: #1987543d;
            color: var(--accent-color);
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

            .stats-grid {
                grid-template-columns: 1fr;
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

        /* Filtros */
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

        .sync-info {
            background: #e8f5e8;
            border: 1px solid #c3e6c3;
            border-radius: 5px;
            padding: 1rem;
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
            <a href="admin_dashboard.php" class="active">
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
            <a href="relatorios.php">
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
            <h2>Dashboard Administrativo</h2>
            <div class="user-info">
                <span class="material-icons">account_circle</span>
                <span>Olá, <?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['mensagem_sincronizacao'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <span class="material-icons me-2">sync</span>
                    <?= htmlspecialchars($_SESSION['mensagem_sincronizacao']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['mensagem_sincronizacao']); ?>
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
                        <?php
                        $stmt_users = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE is_user = true");
                        $total_users = $stmt_users->fetchColumn();
                        ?>
                        <h3><?= $total_users ?></h3>
                        <p>Usuários Ativos</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <span class="material-icons">person_add</span>
                    </div>
                    <div>
                        <h3><?= $solicitacoes_pendentes ?></h3>
                        <p>Solicitações Pendentes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <span class="material-icons">lightbulb</span>
                    </div>
                    <div>
                        <h3><?= $qtde_sugestoes_pendentes ?></h3>
                        <p>Sugestões Pendentes</p>
                    </div>
                </div>
            </div>

            <!-- Sincronização -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><span class="material-icons me-2">sync</span>Sincronização de Dados</span>
                            <a href="?sincronizar=1" class="btn btn-light btn-sm">
                                <span class="material-icons me-1">refresh</span>
                                Sincronizar Agora
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="sync-info">
                                <?php
                                $stmt_last_sync = $pdo->query("SELECT data_hora FROM sincronizacoes ORDER BY data_hora DESC LIMIT 1");
                                $last_sync = $stmt_last_sync->fetchColumn();
                                ?>
                                <strong>Última sincronização:</strong>
                                <?= $last_sync ? date('d/m/Y H:i:s', strtotime($last_sync)) : 'Nunca' ?>
                            </div>
                            <p class="mb-0">
                                <span class="material-icons me-1">info</span>
                                A sincronização atualiza os dados das emendas com base na planilha Excel oficial.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-card">
                <h5><span class="material-icons me-2">filter_list</span>Filtros</h5>
                <form method="GET" class="filters-form">
                    <div class="filters-grid">
                        <div>
                            <label class="form-label">Tipo de Emenda</label>
                            <select name="tipo_caderno" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($tipos_emenda as $tipo): ?>
                                    <option value="<?= htmlspecialchars($tipo) ?>" <?= ($_GET['tipo_caderno'] ?? '') === $tipo ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Eixo Temático</label>
                            <select name="eixo_tematico" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($eixos_tematicos as $eixo): ?>
                                    <option value="<?= htmlspecialchars($eixo) ?>" <?= ($_GET['eixo_tematico'] ?? '') === $eixo ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($eixo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Unidade Responsável</label>
                            <select name="unidade_responsavel" class="form-control">
                                <option value="">Todas</option>
                                <?php foreach ($unidades as $unidade): ?>
                                    <option value="<?= htmlspecialchars($unidade) ?>" <?= ($_GET['unidade_responsavel'] ?? '') === $unidade ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($unidade) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">ODS</label>
                            <select name="ods" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($ods_values as $ods_val): ?>
                                    <option value="<?= htmlspecialchars($ods_val) ?>" <?= ($_GET['ods'] ?? '') === $ods_val ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ods_val) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Programa</label>
                            <select name="programa" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($programas as $programa): ?>
                                    <option value="<?= htmlspecialchars($programa) ?>" <?= ($_GET['programa'] ?? '') === $programa ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($programa) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Ano</label>
                            <select name="ano_projeto" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($anos as $ano): ?>
                                    <option value="<?= $ano ?>" <?= ($_GET['ano_projeto'] ?? '') == $ano ? 'selected' : '' ?>>
                                        <?= $ano ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons me-1">search</span>
                            Filtrar
                        </button>
                        <a href="admin_dashboard.php" class="btn btn-secondary">
                            <span class="material-icons me-1">clear</span>
                            Limpar
                        </a>
                        <a href="?export=excel<?= !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>"
                            class="btn btn-success">
                            <span class="material-icons me-1">download</span>
                            Exportar Excel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabela de Emendas -->
            <div class="card">
                <div class="card-header">
                    <span class="material-icons me-2">list</span>
                    Emendas Cadastradas (<?= number_format($total_emendas) ?> total)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Eixo Temático</th>
                                    <th>Órgão</th>
                                    <th>Valor</th>
                                    <th>Criado em</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($emendas)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <span class="material-icons me-2">info</span>
                                            Nenhuma emenda encontrada
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($emendas as $emenda): ?>
                                        <tr>
                                            <td><?= $emenda['id'] ?></td>
                                            <td><?= htmlspecialchars($emenda['tipo_emenda'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($emenda['eixo_tematico'] ?? '') ?></td>
                                            <td><?= htmlspecialchars(substr($emenda['orgao'] ?? '', 0, 50)) ?>...</td>
                                            <td>R$ <?= number_format($emenda['valor'] ?? 0, 2, ',', '.') ?></td>
                                            <td><?= date('d/m/Y', strtotime($emenda['criado_em'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($pagina_atual > 1): ?>
                            <li class="page-item">
                                <a class="page-link"
                                    href="?pagina=<?= $pagina_atual - 1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : '' ?>">
                                    Anterior
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                            <li class="page-item <?= $i === $pagina_atual ? 'active' : '' ?>">
                                <a class="page-link"
                                    href="?pagina=<?= $i ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($pagina_atual < $total_paginas): ?>
                            <li class="page-item">
                                <a class="page-link"
                                    href="?pagina=<?= $pagina_atual + 1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : '' ?>">
                                    Próxima
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

            <!-- Sugestões Pendentes -->
            <?php if (!empty($sugestoes_pendentes)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <span class="material-icons me-2">lightbulb</span>
                        Sugestões Pendentes
                    </div>
                    <div class="card-body">
                        <?php foreach ($sugestoes_pendentes as $sugestao): ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong><?= htmlspecialchars($sugestao['usuario_nome']) ?></strong>
                                        <small class="text-muted">-
                                            <?= date('d/m/Y H:i', strtotime($sugestao['criado_em'])) ?></small>
                                    </div>
                                    <span class="badge bg-warning">Pendente</span>
                                </div>
                                <p class="mb-2">
                                    <strong>Campo:</strong>
                                    <?= htmlspecialchars($campos_editaveis[$sugestao['campo_sugerido']] ?? $sugestao['campo_sugerido']) ?><br>
                                    <strong>Valor sugerido:</strong> <?= htmlspecialchars($sugestao['valor_sugerido']) ?>
                                </p>
                                <form method="POST" class="d-flex gap-2 align-items-end">
                                    <input type="hidden" name="action" value="responder_sugestao">
                                    <input type="hidden" name="sugestao_id" value="<?= $sugestao['id'] ?>">
                                    <div class="flex-grow-1">
                                        <textarea name="resposta" class="form-control" placeholder="Resposta..."
                                            rows="2"></textarea>
                                    </div>
                                    <select name="status" class="form-control" required style="min-width: 150px;">
                                        <option value="aprovado">Aprovar</option>
                                        <option value="rejeitado">Rejeitar</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary">
                                        <span class="material-icons">send</span>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center">
                            <a href="sugestoes.php" class="btn btn-outline-primary">
                                Ver todas as sugestões
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Menu toggle responsivo
        document.addEventListener('DOMContentLoaded', function () {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
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
        });
    </script>
</body>

</html>