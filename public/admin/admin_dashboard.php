<?php
// admin_dashboard.php
// Painel de Controle do Administrador
// Início da sessão
session_start();
// Verifica se o usuário está autenticado e é um administrador
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/sincronizador.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Bibliotecas para manipulação de planilhas
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Verifica se a classe TCPDF está disponível
if (!class_exists('TCPDF')) {
    die('TCPDF não está instalado. Por favor, instale via composer: composer require tecnickcom/tcpdf');
}

// Campos editáveis para sugestões. Campos que podem ser editados pelos usuários
$campos_editaveis = [
    'objeto_intervencao' => 'Objeto de Intervenção', // 
    'valor' => 'Valor',
    'eixo_tematico' => 'Eixo Temático',
    'orgao' => 'Unidade Responsável',
    'ods' => 'ODS',
    'pontuacao' => 'Pontuação',
    'outros_recursos' => 'Outros Recursos'
];

// Processar sincronização (se solicitado)
if (isset($_GET['sincronizar'])) {
    // Iniciar sincronização
    $sincronizador = new SincronizadorEmendas(
        $pdo, // Instância do PDO
        __DIR__ . '/../../uploads/Cadernos_DeEmendas_.xlsx' // Caminho para o arquivo Excel
    );
    // Executar sincronização
    $resultado = $sincronizador->sincronizar();
    // Armazenar mensagem de sincronização na sessão
    $_SESSION['mensagem_sincronizacao'] = $resultado['message'];
    // Redirecionar para o painel de controle
    header('Location: admin_dashboard.php');
    exit; // Finaliza a execução do script
}

// Processa ação de resposta à sugestão. Verifica se a ação é responder_sugestao.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'responder_sugestao') {
        $sugestao_id = $_POST['sugestao_id'];
        $resposta = $_POST['resposta'];
        $status = $_POST['status'];
        // Atualiza a sugestão no banco de dados
        try {
            $stmt = $pdo->prepare("UPDATE sugestoes_emendas SET status = ?, resposta = ?, respondido_em = NOW(), respondido_por = ? WHERE id = ?");
            $stmt->execute([$status, $resposta, $_SESSION['user']['id'], $sugestao_id]);

            // Envia notificação para o usuário
            $stmt_notif = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem, referencia_id) SELECT usuario_id, 'resposta_sugestao', ?, id FROM sugestoes_emendas WHERE id = ?"); // Notificação de resposta à sugestão
            $mensagem = "Sua sugestão #$sugestao_id foi $status"; // Mensagem da notificação
            $stmt_notif->execute([$mensagem, $sugestao_id]); // Executa a notificação

            $_SESSION['message'] = "Resposta enviada com sucesso!"; // Mensagem de sucesso
            header('Location: admin_dashboard.php'); // Redireciona para o painel de controle
            exit; // Finaliza a execução do script
        } catch (PDOException $e) { // Captura exceções do PDO
            $_SESSION['message'] = "Erro ao responder sugestão: " . $e->getMessage(); // Mensagem de erro
        }// Fim do bloco catch
    } // Fim do bloco if
} // Fim do bloco principal

// Função para ler a planilha Excel
// Retorna um array de emendas
function lerPlanilhaEmendas($caminhoArquivo) {
    // Lê a planilha e retorna os dados
    try {
        // Verifica se o arquivo existe
        if (!file_exists($caminhoArquivo)) {
            throw new Exception("Arquivo não encontrado: " . $caminhoArquivo);
        }
        $spreadsheet = IOFactory::load($caminhoArquivo); // Carrega a planilha
        $sheet = $spreadsheet->getActiveSheet(); // Obtém a aba ativa
        $emendas = []; // Inicializa o array de emendas
        // Itera sobre as linhas da planilha
        foreach ($sheet->getRowIterator(2) as $row) {
            $cells = []; // Inicializa o array de células
            // Itera sobre as células da linha
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();// Adiciona o valor da célula ao array
            }
            // Verifica se a linha contém dados
            if (!empty($cells[0])) {
                // Converter valor monetário para float
                $valor = $cells[10] ?? '0';
                // Remove caracteres não numéricos
                if (is_string($valor)) {
                    $valor = str_replace(['.', ','], ['', '.'], $valor);
                }
                // Formata o valor como moeda
                $valorFormatado = number_format((float)$valor, 2, ',', '.');
                // Adiciona a emenda ao array
                $emendas[] = [
                    'tipo' => $cells[0] ?? '',// Tipo da emenda
                    'eixo' => $cells[1] ?? '',// Eixo temático
                    'orgao' => $cells[2] ?? '',// Órgão
                    'objeto' => $cells[3] ?? '',
                    'ods' => $cells[4] ?? '',
                    'valor' => $valorFormatado,
                    'justificativa' => $cells[11] ?? ''
                ];
            }
        }
        return $emendas;// Retorna as emendas lidas
    } catch (Exception $e) {// Captura exceções
        error_log("Erro ao ler planilha: " . $e->getMessage());// Registra erro no log
        return [];// Retorna array vazio em caso de erro
    }
}

// Aplicar filtros à consulta. Inicializa as variáveis para os filtros.
$where = [];// Condições da consulta
$params = [];// Parâmetros da consulta

// Processar filtros
if ($_SERVER['REQUEST_METHOD'] === 'GET') {// Verifica se a requisição é do tipo GET
    // Tipo de caderno
    if (!empty($_GET['tipo_caderno'])) {// Verifica se o filtro 'tipo_caderno' está presente
        $where[] = "tipo_emenda = ?";// Adiciona condição à consulta
        $params[] = $_GET['tipo_caderno'];// Adiciona o valor do filtro aos parâmetros
    }
    // Eixo temático
    if (!empty($_GET['eixo_tematico']) && $_GET['eixo_tematico'] !== 'Selecione') {// Verifica se o filtro 'eixo_tematico' está presente
        $where[] = "eixo_tematico = ?";// Adiciona condição à consulta
        $params[] = $_GET['eixo_tematico'];// Adiciona o valor do filtro aos parâmetros
    }
    // Unidade responsável
    if (!empty($_GET['unidade_responsavel']) && $_GET['unidade_responsavel'] !== 'Selecione') {// Verifica se o filtro 'unidade_responsavel' está presente
        $where[] = "orgao = ?";// Adiciona condição à consulta
        $params[] = $_GET['unidade_responsavel'];// Adiciona o valor do filtro aos parâmetros
    }
    // ODS
    if (!empty($_GET['ods']) && $_GET['ods'] !== 'Selecione') {// Verifica se o filtro 'ods' está presente
        $where[] = "ods = ?";// Adiciona condição à consulta
        $params[] = $_GET['ods'];// Adiciona o valor do filtro aos parâmetros
    }
    // Regionalização
    if (!empty($_GET['regionalizacao']) && $_GET['regionalizacao'] !== 'Selecione') {// Verifica se o filtro 'regionalizacao' está presente
        $where[] = "regionalizacao = ?";// Adiciona condição à consulta
        $params[] = $_GET['regionalizacao'];// Adiciona o valor do filtro aos parâmetros
    }
    // Unidade orçamentária
    if (!empty($_GET['unidade_orcamentaria']) && $_GET['unidade_orcamentaria'] !== 'Selecione') {// Verifica se o filtro 'unidade_orcamentaria' está presente
        $where[] = "unidade_orcamentaria = ?";// Adiciona condição à consulta
        $params[] = $_GET['unidade_orcamentaria'];// Adiciona o valor do filtro aos parâmetros
    }
    // Programa
    if (!empty($_GET['programa']) && $_GET['programa'] !== 'Selecione') {// Verifica se o filtro 'programa' está presente
        $where[] = "programa = ?";// Adiciona condição à consulta
        $params[] = $_GET['programa'];// Adiciona o valor do filtro aos parâmetros
    }
    // Ação
    if (!empty($_GET['acao']) && $_GET['acao'] !== 'Selecione') {// Verifica se o filtro 'acao' está presente
        $where[] = "acao = ?";// Adiciona condição à consulta
        $params[] = $_GET['acao'];// Adiciona o valor do filtro aos parâmetros
    }
    // Categoria econômica
    if (!empty($_GET['categoria_economica']) && $_GET['categoria_economica'] !== 'Selecione') {// Verifica se o filtro 'categoria_economica' está presente
        $where[] = "categoria_economica = ?";// Adiciona condição à consulta
        $params[] = $_GET['categoria_economica'];// Adiciona o valor do filtro aos parâmetros
    }
    // Pontuação
    if (!empty($_GET['pontuacao_de'])) {// Verifica se o filtro 'pontuacao_de' está presente
        $where[] = "pontuacao >= ?";// Adiciona condição à consulta
        $params[] = $_GET['pontuacao_de'];// Adiciona o valor do filtro aos parâmetros
    }
    // Pontuação até
    if (!empty($_GET['pontuacao_ate'])) {// Verifica se o filtro 'pontuacao_ate' está presente
        $where[] = "pontuacao <= ?";// Adiciona condição à consulta
        $params[] = $_GET['pontuacao_ate'];// Adiciona o valor do filtro aos parâmetros
    }
    // Valor
    if (!empty($_GET['valor_de'])) {// Verifica se o filtro 'valor_de' está presente
        $where[] = "valor >= ?";// Adiciona condição à consulta
        $params[] = $_GET['valor_de'];// Adiciona o valor do filtro aos parâmetros
    }
    // Valor até
    if (!empty($_GET['valor_ate'])) {// Verifica se o filtro 'valor_ate' está presente
        $where[] = "valor <= ?";// Adiciona condição à consulta
        $params[] = $_GET['valor_ate'];// Adiciona o valor do filtro aos parâmetros
    }
    // Ano do projeto
    if (!empty($_GET['ano_projeto'])) {// Verifica se o filtro 'ano_projeto' está presente
        $where[] = "EXTRACT(YEAR FROM criado_em) = ?";// Adiciona condição à consulta
        $params[] = $_GET['ano_projeto'];// Adiciona o valor do filtro aos parâmetros
    }
    // Outros recursos
    if (isset($_GET['outros_recursos']) && $_GET['outros_recursos'] !== '') {// Verifica se o filtro 'outros_recursos' está presente
        $where[] = "outros_recursos = ?";// Adiciona condição à consulta
        $params[] = (int)$_GET['outros_recursos'];// Adiciona o valor do filtro aos parâmetros
    }

    // Processar exportação
    if (isset($_GET['export'])) {// Verifica se o parâmetro 'export' está presente
        $export_type = $_GET['export'];// Adiciona o valor do parâmetro à variável
        $sql = "SELECT * FROM emendas" . (!empty($where) ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY criado_em DESC"; // Monta a consulta SQL
        $stmt = $pdo->prepare($sql);// Prepara a consulta
        $stmt->execute($params);// Executa a consulta
        $emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);// Obtém os resultados
        if ($export_type === 'excel') {
            // Função para exportar para Excel
            exportToExcel($emendas);// Exporta os resultados para Excel
        } elseif ($export_type === 'pdf') {// Verifica se o tipo de exportação é PDF
            exportToPDF($emendas);// Exporta os resultados para PDF
        }
        exit;// Encerra a execução após a exportação
    }
}

// Paginação
$itens_por_pagina = 10;// Define o número de itens por página
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;// Define a página atual
$offset = ($pagina_atual - 1) * $itens_por_pagina;// Calcula o deslocamento

// Total para paginação
$sql_count = "SELECT COUNT(*) as total FROM emendas" . (!empty($where) ? " WHERE " . implode(" AND ", $where) : "");// Monta a consulta SQL
$stmt_count = $pdo->prepare($sql_count);// Prepara a consulta
$stmt_count->execute($params);// Executa a consulta
$total_emendas = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];// Obtém o total de emendas
$total_paginas = ($total_emendas > 0) ? (int)ceil($total_emendas / $itens_por_pagina) : 1;// Calcula o total de páginas

// Consulta principal
$sql = "SELECT * FROM emendas" . (!empty($where) ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY criado_em DESC LIMIT :limit OFFSET :offset";// Monta a consulta SQL
$stmt = $pdo->prepare($sql);// Prepara a consulta
// Vincular parâmetros
if (!empty($params)) {// Verifica se existem parâmetros a serem vinculados
    foreach ($params as $key => $value) {// Itera sobre os parâmetros
        $stmt->bindValue($key + 1, $value);// Vincula o valor do parâmetro
    }
}
// Vincular limites de paginação
$stmt->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);// Vincula o limite de itens por página
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);// Vincula o deslocamento
$stmt->execute();// Executa a consulta
$emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);// Obtém os resultados

// Sugestões pendentes
$stmt_sugestoes = $pdo->prepare("SELECT se.*, u.nome as usuario_nome, e.objeto_intervencao FROM sugestoes_emendas se JOIN usuarios u ON se.usuario_id = u.id JOIN emendas e ON se.emenda_id = e.id WHERE se.status = 'pendente' ORDER BY se.criado_em DESC");// Prepara a consulta
$stmt_sugestoes->execute();// Executa a consulta
$sugestoes_pendentes = $stmt_sugestoes->fetchAll(PDO::FETCH_ASSOC);// Obtém os resultados

// Valores distintos para filtros
$tipos_emenda = ['EMENDA PARLAMENTAR FEDERAL', 'OPERAÇÃO DE CRÉDITO', 'OUTROS RECURSOS'];// Tipos de emenda
$eixos_tematicos = $pdo->query("SELECT DISTINCT eixo_tematico FROM emendas ORDER BY eixo_tematico")->fetchAll(PDO::FETCH_COLUMN);// Eixos temáticos
$unidades = $pdo->query("SELECT DISTINCT orgao FROM emendas ORDER BY orgao")->fetchAll(PDO::FETCH_COLUMN);// Unidades
$ods_values = $pdo->query("SELECT DISTINCT ods FROM emendas ORDER BY ods")->fetchAll(PDO::FETCH_COLUMN);// ODS
$regionalizacoes = $pdo->query("SELECT DISTINCT regionalizacao FROM emendas ORDER BY regionalizacao")->fetchAll(PDO::FETCH_COLUMN);// Regionalizações
$unidades_orcamentarias = $pdo->query("SELECT DISTINCT unidade_orcamentaria FROM emendas ORDER BY unidade_orcamentaria")->fetchAll(PDO::FETCH_COLUMN);// Unidades orçamentárias
$programas = $pdo->query("SELECT DISTINCT programa FROM emendas ORDER BY programa")->fetchAll(PDO::FETCH_COLUMN);// Programas
$acoes = $pdo->query("SELECT DISTINCT acao FROM emendas ORDER BY acao")->fetchAll(PDO::FETCH_COLUMN);// Ações
$categorias_economicas = $pdo->query("SELECT DISTINCT categoria_economica FROM emendas ORDER BY categoria_economica")->fetchAll(PDO::FETCH_COLUMN);// Categorias econômicas
$anos = $pdo->query("SELECT DISTINCT EXTRACT(YEAR FROM criado_em) as ano FROM emendas ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);// Anos

// Carregar emendas da planilha Excel
$emendas_excel = lerPlanilhaEmendas(__DIR__ . '/../../uploads/Cadernos_DeEmendas_.xlsx');

// Função exportToExcel
function exportToExcel($data) {// Função para exportar dados para Excel
    $spreadsheet = new Spreadsheet();// Cria uma nova planilha
    $sheet = $spreadsheet->getActiveSheet();// Obtém a folha ativa

    // Cabeçalhos
    $sheet->setCellValue('A1', 'Tipo de Emenda')
        ->setCellValue('B1', 'Eixo Temático')
        ->setCellValue('C1', 'Órgão')
        ->setCellValue('D1', 'Objeto de Intervenção Pública')
        ->setCellValue('E1', 'ODS')
        ->setCellValue('F1', 'Regionalização')
        ->setCellValue('G1', 'Unidade Orçamentária Federal')
        ->setCellValue('H1', 'Programa')
        ->setCellValue('I1', 'Ação')
        ->setCellValue('J1', 'Categoria Econômica da Despesa')
        ->setCellValue('K1', 'Valor Pretendido (R$)')
        ->setCellValue('L1', 'Justificativa')
        ->setCellValue('M1', 'Ano')
        ->setCellValue('N1', 'Data Criação');

    $row = 2;// Inicia a contagem de linhas
    // Preenche os dados
    foreach ($data as $emenda) {// Itera sobre as emendas
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
        $row++;// Incrementa a contagem de linhas
    }
    // Estilos
    $sheet->getStyle('A1:N1')->getFont()->setBold(true);// Define o estilo de fonte em negrito para os cabeçalhos
    // Ajusta a largura das colunas
    foreach(range('A','N') as $col) {// Itera sobre as colunas
        $sheet->getColumnDimension($col)->setAutoSize(true);// Ajusta a largura automaticamente
    }

    // Gera o arquivo Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    // Define o nome do arquivo
    header('Content-Disposition: attachment;filename="relatorio_emendas_admin.xlsx"');
    // Limpa o buffer de saída
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);// Cria um escritor Xlsx
    $writer->save('php://output');// Envia o arquivo para o navegador
    exit;// Encerra a execução do script
}

// Função exportToPDF
// Gera um arquivo PDF com os dados das emendas
function exportToPDF($data) {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);// Cria um novo documento PDF
    $pdf->SetCreator('Sistema CICEF');// Define o criador do documento
    $pdf->SetAuthor('Painel Administrativo');// Define o autor do documento
    $pdf->SetTitle('Relatório de Emendas');// Define o título do documento
    $pdf->SetHeaderData('', 0, 'Relatório de Emendas', 'Gerado em ' . date('d/m/Y H:i'));// Define os dados do cabeçalho
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));// Define a fonte do cabeçalho
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));// Define a fonte do rodapé
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);// Define a fonte monoespaçada padrão
    $pdf->SetMargins(PDF_MARGIN_LEFT, 15, PDF_MARGIN_RIGHT);// Define as margens
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);// Define a margem do cabeçalho
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);// Define a margem do rodapé
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);// Define a quebra automática de página
    $pdf->SetFont('helvetica', '', 9); // Define a fonte
    $pdf->AddPage();// Adiciona uma nova página

    // Conteúdo do PDF
    // Tabela de dados
    $html = '<h2>Relatório de Emendas</h2> <table border="1" cellpadding="4">';
    // Cabeçalho da tabela
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
        . '</tr>';// Define o rodapé da tabela

    // Corpo da tabela
    foreach ($data as $emenda) {// Itera sobre os dados das emendas
        // Adiciona uma nova linha para cada emenda
        $html .= '<tr>'// Define a nova linha da tabela
            . '<td>'.htmlspecialchars($emenda['tipo_emenda'] ?? '-').'</td>'// Define a célula da tabela
            . '<td>'.htmlspecialchars($emenda['eixo_tematico'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['orgao'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars(substr($emenda['objeto_intervencao'] ?? '-', 0, 50)).'...</td>'
            . '<td>'.htmlspecialchars($emenda['ods'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['regionalizacao'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['unidade_orcamentaria'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['programa'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['acao'] ?? '-').'</td>'
            . '<td>'.htmlspecialchars($emenda['categoria_economica'] ?? '-').'</td>'
            . '<td>'.(isset($emenda['valor']) ? number_format((float)$emenda['valor'], 2, ',', '.') : '-').'</td>'// Define a célula da tabela
            . '<td>'.htmlspecialchars(substr($emenda['justificativa'] ?? '-', 0, 80)).'...</td>'
            . '<td>'.(isset($emenda['criado_em']) ? date('d/m/Y', strtotime($emenda['criado_em'])) : '-').'</td>'
            . '</tr>';// Define o rodapé da tabela
    }

    // Rodapé da tabela
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');// Escreve o conteúdo HTML no PDF
    $pdf->Output('relatorio_emendas_admin.pdf', 'D');// Faz o download do PDF
    exit;// Encerra a execução do script
}

// Determina as cores do usuário baseado no tipo de cada um
$user_colors = [
    'primary' => '#6f42c1',// Define a cor primária
    'secondary' => '#e83e8c',// Define a cor secundária
    'accent' => '#fd7e14'// Define a cor de destaque
];
// Determina as cores do usuário baseado no tipo
if (isset($_SESSION["user"]["tipo"])) {// Verifica se o tipo de usuário está definido
    switch ($_SESSION["user"]["tipo"]) {// Verifica o tipo de usuário
        case 'Deputado':// Define as cores para o tipo 'Deputado'
            $user_colors = [
                'primary' => '#018bd2',
                'secondary' => '#51ae32',
                'accent' => '#fdfefe'
            ];
            break;
        case 'Senador':// Define as cores para o tipo 'Senador'
            $user_colors = [
                'primary' => '#51b949',
                'secondary' => '#0094db',
                'accent' => '#fefefe'
            ];
            break;
        case 'Administrador':// Define as cores para o tipo 'Administrador'
            $user_colors = [
                'primary' => '#6f42c1',
                'secondary' => '#e83e8c',
                'accent' => '#fd7e14'
            ];
            break;
    }// Fim da verificação do tipo de usuário
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
/* Estilos do tema */
:root {
    --primary-color: <?= $user_colors['primary'] ?>;/* Define a cor primária */
    --secondary-color: <?= $user_colors['secondary'] ?>;/* Define a cor secundária */
    --accent-color: <?= $user_colors['accent'] ?>;/* Define a cor de destaque */
    --dark-color: #2c3e50;/* Define a cor escura */
    --light-color: #f8f9fa;/* Define a cor clara */
    --border-color: #e0e0e0;/* Define a cor da borda */
    --error-color: #e74c3c;/* Define a cor de erro */
    --success-color: #2ecc71;/* Define a cor de sucesso */
}
/* Estilos globais */
* { margin: 0; padding: 0; box-sizing: border-box; }
/* Estilos do corpo */
body { font-family: 'Roboto', sans-serif; background-color: #f5f7fa; color: #333; line-height: 1.6; overflow-x: hidden; }
/* Container principal */
.admin-container { 
    display: flex; /* Define o layout como flexível */
    min-height: 100vh; /* Define a altura mínima como 100% da viewport */
}
/* Sidebar */
.admin-sidebar { 
    width: 250px; /* Define a largura da sidebar */
    background-color: var(--dark-color); /* Define a cor de fundo da sidebar */
    color: white; padding: 1.5rem 0; /* Define a cor do texto e o padding da sidebar */
    position: fixed; /* Define a posição da sidebar como fixa */
    height: 100vh; /* Define a altura da sidebar como 100% da viewport */
    transition: all 0.3s; /* Define a transição para a sidebar */
    z-index: 100; /* Define o z-index da sidebar */
    overflow-y: auto; /* Adiciona rolagem vertical à sidebar */
}
.sidebar-header { padding: 0 1.5rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
.sidebar-header h2 { font-size: 1.25rem; display: flex; align-items: center; gap: 0.75rem; }
.sidebar-menu { padding: 1rem 0; }
.sidebar-menu a { display: flex; align-items: center; padding: 0.75rem 1.5rem; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; gap: 0.75rem; }
.sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgba(255,255,255,0.1); color: white; }
.sidebar-menu i { font-size: 1.25rem; }
/* Main Content */
.admin-content { flex: 1; margin-left: 250px; transition: all 0.3s; width: calc(100% - 250px); }
/* Header */
.admin-header { background: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 90; }
.admin-header h1 { font-size: 1.5rem; color: var(--dark-color); }
.menu-toggle { display: none; background: none; border: none; color: var(--dark-color); font-size: 1.5rem; cursor: pointer; }
.user-area { display: flex; align-items: center; gap: 1rem; }
.user-icon { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.2rem; }
.user-name { font-weight: 500; }
.logout-btn { color: var(--dark-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px; transition: all 0.3s; }
.logout-btn:hover { background-color: var(--light-color); }
/* Content Area */
.content-area { padding: 2rem; max-width: 100%; }
/* Sections */
.export-section, .filters-section, .emendas-section, .sync-section, .sugestoes-section { background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
.export-section h2, .filters-section h2, .sync-section h2, .sugestoes-section h2 { font-size: 1.25rem; color: var(--primary-color); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
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
.emendas-table { width: 100%; min-width: 1400px; border-collapse: collapse; background: white; }
.emendas-table thead th { background-color: var(--primary-color); color: white; font-weight: 600; padding: 1rem 0.75rem; text-align: left; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
.emendas-table tbody tr { transition: background-color 0.2s; border-bottom: 1px solid var(--border-color); }
.emendas-table tbody tr:hover { background-color: #f8f9fa; }
.emendas-table td { padding: 1rem 0.75rem; vertical-align: top; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.emendas-table td.description { max-width: 400px; white-space: normal; line-height: 1.4; }
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
/* Sugestões */
.sugestao-item { border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; background: #f8f9fa; }
.sugestao-header { display: flex; justify-content: between; align-items: center; margin-bottom: 0.5rem; }
.sugestao-campo { font-weight: 600; color: var(--primary-color); }
.sugestao-form { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color); }
.sugestao-form textarea { width: 100%; min-height: 80px; margin-bottom: 1rem; }
.sugestao-form select { margin-bottom: 1rem; margin-right: 1rem; }
/* Mobile Responsiveness */
@media (max-width: 768px) {
    .admin-sidebar { transform: translateX(-100%); }
    .admin-sidebar.active { transform: translateX(0); }
    .admin-content { margin-left: 0; width: 100%; }
    .menu-toggle { display: block; }
    .admin-header { padding: 1rem; }
    .content-area { padding: 1rem; }
    .filters-grid { grid-template-columns: 1fr; }
    .export-buttons { flex-direction: column; }
    .filter-actions { flex-direction: column; }
    .emendas-table { min-width: 1000px; }
    .action-buttons { flex-direction: column; }
    .user-area { gap: 0.5rem; }
    .user-name { display: none; }
}
@media (max-width: 480px) {
    .admin-header h1 { font-size: 1.25rem; }
    .emendas-table { min-width: 800px; }
    .emendas-table th, .emendas-table td { padding: 0.5rem; font-size: 0.875rem; }
}
/* Overlay para mobile */
.sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 99; }
.sidebar-overlay.active { display: block; }
</style>
</head>
<body>
<div class="admin-container">
    <!-- Sidebar -->
    <nav class="admin-sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>
                <i class="material-icons">admin_panel_settings</i> Painel Admin
            </h2>
        </div>
        <div class="sidebar-menu">
            <a href="admin_dashboard.php" class="active">
                <i class="material-icons">dashboard</i> Dashboard
            </a>
            <a href="gerenciar_usuarios.php">
                <i class="material-icons">people</i> Usuários
            </a>
            <a href="relatorios.php">
                <i class="material-icons">assessment</i> Relatórios
            </a>
            <a href="configuracoes.php">
                <i class="material-icons">settings</i> Configurações
            </a>
        </div>
    </nav>

    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Header -->
        <header class="admin-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="material-icons">menu</i>
                </button>
                <h1>Painel Administrativo</h1>
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

            <!-- Sync Section -->
            <section class="sync-section">
                <h2>
                    <i class="material-icons">sync</i> Sincronização
                </h2>
                <p>Sincronize os dados das emendas com a planilha Excel.</p>
                <a href="?sincronizar=1" class="btn btn-warning">
                    <i class="material-icons">sync</i> Sincronizar Dados
                </a>
            </section>

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
                <form method="GET" action="admin_dashboard.php">
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
                            <input type="number" step="0.01" name="pontuacao_de" id="pontuacao_de" class="form-control" value="<?= htmlspecialchars($_GET["pontuacao_de"] ?? "") ?>" placeholder="0,00">
                        </div>

                        <div class="filter-group">
                            <label for="pontuacao_ate">Pontuação (Até):</label>
                            <input type="number" step="0.01" name="pontuacao_ate" id="pontuacao_ate" class="form-control" value="<?= htmlspecialchars($_GET["pontuacao_ate"] ?? "") ?>" placeholder="0,00">
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
                        <a href="admin_dashboard.php" class="btn btn-secondary">
                            <i class="material-icons">clear</i> Limpar Filtros
                        </a>
                    </div>
                </form>
            </section>

            <!-- Sugestões Pendentes -->
            <?php if (!empty($sugestoes_pendentes)): ?>
                <section class="sugestoes-section">
                    <h2>
                        <i class="material-icons">lightbulb</i> Sugestões Pendentes
                    </h2>
                    <?php foreach ($sugestoes_pendentes as $sugestao): ?>
                        <div class="sugestao-item">
                            <div class="sugestao-header">
                                <span class="sugestao-campo"><?= htmlspecialchars($campos_editaveis[$sugestao['campo_sugerido']] ?? $sugestao['campo_sugerido']) ?></span>
                                <small>Por: <?= htmlspecialchars($sugestao['usuario_nome']) ?> em <?= date('d/m/Y H:i', strtotime($sugestao['criado_em'])) ?></small>
                            </div>
                            <p><strong>Emenda:</strong> <?= htmlspecialchars(substr($sugestao['objeto_intervencao'], 0, 100)) ?>...</p>
                            <p><strong>Sugestão:</strong> <?= htmlspecialchars($sugestao['valor_sugerido']) ?></p>
                            <form method="POST" class="sugestao-form">
                                <input type="hidden" name="action" value="responder_sugestao">
                                <input type="hidden" name="sugestao_id" value="<?= $sugestao['id'] ?>">
                                <textarea name="resposta" placeholder="Digite sua resposta..." required></textarea>
                                <select name="status" required>
                                    <option value="">Selecione uma ação</option>
                                    <option value="aprovado">Aprovar</option>
                                    <option value="rejeitado">Rejeitar</option>
                                </select>
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons">send</i> Responder
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <!-- Emendas Section -->
            <section class="emendas-section">
                <h2>
                    <i class="material-icons">list</i> Emendas Cadastradas
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
                                <th>Pontuação</th>
                                <th>Regionalização</th>
                                <th>Unidade Orçamentária Federal</th>
                                <th>Programa</th>
                                <th>Ação</th>
                                <th>Categoria Econômica da Despesa</th>
                                <th>Valor Pretendido</th>
                                <th>Justificativa</th>
                                <th>Data</th>
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
                                            <?= htmlspecialchars(substr($emenda["objeto_intervencao"] ?? '-', 0, 140)) ?><?= strlen($emenda["objeto_intervencao"] ?? '') > 140 ? "..." : "" ?>
                                        </td>
                                        <td><?= htmlspecialchars($emenda["ods"] ?? "-") ?></td>
                                        <td><?= htmlspecialchars($emenda["pontuacao"] ?? "-") ?></td>
                                        <td><?= htmlspecialchars($emenda["regionalizacao"] ?? "-") ?></td>
                                        <td><?= htmlspecialchars($emenda["unidade_orcamentaria"] ?? "-") ?></td>
                                        <td><?= htmlspecialchars($emenda["programa"] ?? "-") ?></td>
                                        <td><?= htmlspecialchars($emenda["acao"] ?? "-") ?></td>
                                        <td><?= htmlspecialchars($emenda["categoria_economica"] ?? "-") ?></td>
                                        <td>R$ <?= isset($emenda["valor"]) ? number_format((float)$emenda["valor"], 2, ",", ".") : "-" ?></td>
                                        <td class="description" title="<?= htmlspecialchars($emenda["justificativa"] ?? '') ?>">
                                            <?= htmlspecialchars(substr($emenda["justificativa"] ?? '-', 0, 120)) ?><?= strlen($emenda["justificativa"] ?? '') > 120 ? "..." : "" ?>
                                        </td>
                                        <td><?= isset($emenda["criado_em"]) ? date('d/m/Y', strtotime($emenda["criado_em"])) : '-' ?></td>
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
        // mostra o filtro se o tipo for OPERAÇÃO DE CRÉDITO ou OUTROS RECURSOS (ajustável)
        if (v === 'OPERAÇÃO DE CRÉDITO' || v === 'OUTROS RECURSOS') {
            outrosRecursosFilter.style.display = 'block';
        } else {
            outrosRecursosFilter.style.display = 'none';
            const sel = document.getElementById('outros_recursos');
            if (sel) sel.value = '';
        }
    }

    tipoSelect.addEventListener('change', toggleOutros);
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
