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
* { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box; 
}

/* Estilos do corpo */
body { 
    font-family: 'Roboto', 
    sans-serif; background-color: #f5f7fa; 
    color: #333; 
    line-height: 1.6; 
    overflow-x: hidden; 
}

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
/* Cabeçalho da Sidebar */
.sidebar-header { 
    padding: 0 1.5rem 1.5rem; /* Define o padding do cabeçalho da sidebar */
    border-bottom: 1px solid rgba(255,255,255,0.1); /* Define a borda inferior do cabeçalho da sidebar */
}
/* Estilos do título do cabeçalho da Sidebar */
.sidebar-header h2 { 
    font-size: 1.25rem; /* Define o tamanho da fonte do título */
    display: flex; /* Define o layout como flexível */
    align-items: center; /* Define o alinhamento vertical como centralizado */
    gap: 0.75rem; /* Define o espaçamento entre os itens */
}
/* Estilos do menu da Sidebar */
.sidebar-menu { 
    padding: 1rem 0; /* Define o padding do menu da sidebar */
}
/* Estilos dos links do menu da Sidebar */
.sidebar-menu a { 
    display: flex; /* Define o layout como flexível */
    align-items: center; /* Define o alinhamento vertical como centralizado */
    padding: 0.75rem 1.5rem; /* Define o padding dos links */
    color: rgba(255,255,255,0.8); /* Define a cor do texto dos links */
    text-decoration: none; /* Remove o sublinhado dos links */
    transition: all 0.3s; /* Define a transição para os links */
    gap: 0.75rem; /* Define o espaçamento entre os itens */
}
/* Estilos do estado hover e ativo dos links do menu da Sidebar */
.sidebar-menu a:hover, .sidebar-menu a.active { 
    background-color: rgba(255,255,255,0.1); /* Define a cor de fundo do estado hover e ativo */
    color: white; /* Define a cor do texto do estado hover e ativo */
}
/* Estilos dos ícones do menu da Sidebar */
.sidebar-menu i { 
    font-size: 1.25rem; /* Define o tamanho da fonte dos ícones */
}
/* Main Content */
.admin-content { 
    flex: 1; /* Define o crescimento do conteúdo principal */
    margin-left: 250px; /* Define a margem esquerda do conteúdo principal */
    transition: all 0.3s; /* Define a transição para o conteúdo principal */
    width: calc(100% - 250px); /* Define a largura do conteúdo principal */
}
/* Header */
.admin-header { 
    background: white; /* Cor de fundo do cabeçalho */
    padding: 1rem 2rem; /* Padding do cabeçalho */
    display: flex; 
    justify-content: space-between; /* Alinhamento do conteúdo do cabeçalho */
    align-items: center; /* Alinhamento vertical do conteúdo do cabeçalho */
    box-shadow: 0 2px 10px rgba(0,0,0,0.1); /* Sombra do cabeçalho */
    position: sticky; /* Posição do cabeçalho */
    top: 0; /* Distância do topo */
    z-index: 90; /* Camada do cabeçalho */
}
/* Título do cabeçalho */
.admin-header h1 { 
    font-size: 1.5rem; 
    color: var(--dark-color); /* Cor do título do cabeçalho */
}
/* Botão de alternância do menu */
.menu-toggle { 
    display: none; /* Oculta o botão de alternância do menu */
    background: none; /* Remove o fundo do botão */
    border: none; /* Remove a borda do botão */
    color: var(--dark-color); /* Cor do botão */
    font-size: 1.5rem; /* Tamanho da fonte do botão */
    cursor: pointer; /* Cursor do botão */
}
/* Área do usuário */
.user-area { 
    display: flex; 
    align-items: center; /* Alinhamento vertical dos itens da área do usuário */
    gap: 1rem; /* Espaçamento entre os itens da área do usuário */
}
/* Ícone do usuário */
.user-icon { 
    width: 40px; /* Largura do ícone do usuário */
    height: 40px; /* Altura do ícone do usuário */
    border-radius: 50%; /* Bordas arredondadas do ícone do usuário */
    background-color: var(--primary-color); /* Cor de fundo do ícone do usuário */
    color: white; /* Cor do ícone do usuário */
    display: flex; /* Define o layout como flexível */
    align-items: center; /* Define o alinhamento vertical como centralizado */
    justify-content: center; /* Define o alinhamento horizontal como centralizado */
    font-weight: 600; /* Define o peso da fonte como 600 */
    font-size: 1.2rem; /* Define o tamanho da fonte */
}
/* Nome do usuário */
.user-name { 
    font-weight: 500; /* Peso da fonte do nome do usuário */
}
/* Botão de logout */
.logout-btn { 
    color: var(--dark-color); /* Cor do botão de logout */
    text-decoration: none; /* Remove o sublinhado do botão de logout */
    display: flex; /* Define o layout como flexível */
    align-items: center; /* Define o alinhamento vertical como centralizado */
    gap: 0.5rem; /* Espaçamento entre os itens do botão de logout */
    padding: 0.5rem 1rem; /* Padding do botão de logout */
    border-radius: 6px; /* Bordas arredondadas do botão de logout */
    transition: all 0.3s; /* Transição suave para o botão de logout */
}
/* Efeito hover do botão de logout */
.logout-btn:hover { 
    background-color: var(--light-color); /* Cor de fundo do botão de logout ao passar o mouse */
}
/** Área de conteúdo */
.content-area { 
    padding: 2rem; 
    max-width: 100%; 
}
/** Seções */
.export-section, .filters-section, .emendas-section, .sync-section, .sugestoes-section { /* Seções do painel administrativo */
    background: white; /* Cor de fundo da seção */
    border-radius: 8px; /* Bordas arredondadas da seção */
    padding: 1.5rem; /* Padding da seção */
    margin-bottom: 2rem; /* Margem inferior da seção */
    box-shadow: 0 2px 10px rgba(0,0,0,0.05); /* Sombra da seção */
}
/** Títulos das seções */
.export-section h2, .filters-section h2, .sync-section h2, .sugestoes-section h2 { 
    font-size: 1.25rem; /* Tamanho da fonte do título da seção */
    color: var(--primary-color); 
    margin-bottom: 1rem; /* Margem inferior do título da seção */
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
}

/** Estilos dos botões */
.btn { 
    display: inline-flex; /* Define o layout como flexível */
    align-items: center; /* Define o alinhamento vertical como centralizado */
    padding: 0.75rem 1.5rem; 
    border-radius: 6px; /* Define as bordas como arredondadas */
    font-weight: 500; /* Define o peso da fonte como 500 */
    cursor: pointer; /* Define o cursor como pointer */
    text-decoration: none; /* Remove o sublinhado do texto */
    transition: all 0.3s; /* Transição suave para todos os estados */
    border: none; /* Remove a borda */
    gap: 0.5rem; /* Espaçamento entre os itens do botão */
}
/** Botão primário */
.btn-primary { 
    background-color: var(--primary-color); 
    color: white; 
}
/** Efeito hover do botão primário */
.btn-primary:hover { 
    opacity: 0.9; /* Define a opacidade como 0.9 */
    transform: translateY(-2px); /* Move o botão para cima ao passar o mouse */
}
/** Botão secundário */
.btn-secondary { 
    background-color: #6c757d; /* Cor de fundo do botão secundário */
    color: white; /* Cor do texto do botão secundário */
}
/** Efeito hover do botão secundário */
.btn-secondary:hover { 
    background-color: #5a6268; /* Cor de fundo do botão secundário ao passar o mouse */
    transform: translateY(-2px); /* Move o botão para cima ao passar o mouse */
}
/** Botão de sucesso */
.btn-success { 
    background-color: var(--success-color); /* Cor de fundo do botão de sucesso */
    color: white; /* Cor do texto do botão de sucesso */
}
/** Efeito hover do botão de sucesso */
.btn-success:hover { 
    background-color: #27ae60; /* Cor de fundo do botão de sucesso ao passar o mouse */
    transform: translateY(-2px); /* Move o botão para cima ao passar o mouse */
}
/** Botão de erro */
.btn-danger { 
    background-color: var(--error-color); /* Cor de fundo do botão de erro */
    color: white; /* Cor do texto do botão de erro */
}
/** Efeito hover do botão de erro */
.btn-danger:hover { 
    background-color: #c0392b; /* Cor de fundo do botão de erro ao passar o mouse */
    transform: translateY(-2px); /* Move o botão para cima ao passar o mouse */
}
/** Estilos dos botões de exportação */
.export-buttons { 
    display: flex; /** Define o layout como flexível */
    gap: 1rem; /** Espaçamento entre os botões de exportação */
    flex-wrap: wrap; /** Permite que os botões se movam para a próxima linha, se necessário */
}
/** Estilos dos filtros */
.filters-grid { 
    display: grid; /** Define o layout como grid */
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /** Define as colunas do grid. */
    gap: 1rem; /** Espaçamento entre os itens do grid */
    margin-bottom: 1.5rem; /** Margem inferior do grid */
}
/** Estilos dos grupos de filtros */
.filter-group { 
    display: flex; /** Define o layout como flexível */
    flex-direction: column; /** Define a direção dos itens como coluna */
}
/** Estilos dos rótulos dos filtros */
.filter-group label { 
    font-weight: 500; 
    margin-bottom: 0.5rem; 
    color: var(--dark-color); /** Cor do texto dos rótulos dos filtros */
}
/** Estilos dos campos de formulário */
.form-control { 
    padding: 0.75rem; 
    border: 1px solid var(--border-color); /** Define a borda do campo de formulário */
    border-radius: 6px; 
    font-family: inherit; /** Define a família da fonte como a fonte padrão */
    transition: border-color 0.3s; /** Define a transição da cor da borda */
}
/** Estilos do campo de foco */
.form-control:focus { 
    border-color: var(--primary-color); /** Define a cor da borda do campo de formulário ao focar */
    outline: none; /** Remove o contorno padrão */
}
/** Estilos das ações dos filtros */
.filter-actions { 
    display: flex; /** Define o layout como flexível */
    gap: 1rem; /** Espaçamento entre os itens das ações dos filtros */
    flex-wrap: wrap; /** Permite que os itens se movam para a próxima linha, se necessário */
}
/** Estilos do contêiner da tabela */
.table-container { 
    width: 100%; 
    overflow-x: auto; /** Permite rolagem horizontal se necessário */
    margin-bottom: 2rem; 
    border-radius: 8px; 
    box-shadow: 0 2px 10px rgba(0,0,0,0.05); /** Sombra sutil ao redor do contêiner da tabela */
}
/** Estilos da tabela de emendas */
.emendas-table { 
    width: 100%; /** Largura total da tabela de emendas */
    min-width: 1400px; /** Largura mínima da tabela de emendas */
    border-collapse: collapse; /** Remove espaçamento entre as células */
    background: white; /** Cor de fundo da tabela de emendas */
}
/** Estilos do cabeçalho da tabela de emendas */
.emendas-table thead th { 
    background-color: var(--primary-color); /** Cor de fundo do cabeçalho da tabela de emendas */
    color: white; /** Cor do texto do cabeçalho da tabela de emendas */
    font-weight: 600; /** Define o peso da fonte como 600 */
    padding: 1rem 0.75rem; /** Espaçamento interno do cabeçalho da tabela de emendas */
    text-align: left; /** Alinha o texto à esquerda */
    position: sticky; /** Mantém o cabeçalho visível ao rolar */
    top: 0; /** Posiciona o cabeçalho no topo */
    z-index: 10; /** Mantém o cabeçalho acima do conteúdo da tabela */
    white-space: nowrap; /** Impede quebra de linha no cabeçalho */
}
/** Estilos do corpo da tabela de emendas */
.emendas-table tbody tr { 
    transition: background-color 0.2s; /** Transição suave da cor de fundo */
    border-bottom: 1px solid var(--border-color); /** Borda inferior das linhas do corpo da tabela de emendas */
}
/** Estilos das linhas do corpo da tabela de emendas ao passar o mouse */
.emendas-table tbody tr:hover { 
    background-color: #f8f9fa; 
}
/** Estilos das células da tabela de emendas */
.emendas-table td { 
    padding: 1rem 0.75rem; /** Espaçamento interno das células da tabela de emendas */
    vertical-align: top; /** Alinhamento vertical das células da tabela de emendas */
    max-width: 220px; /** Largura máxima das células da tabela de emendas */
    overflow: hidden; /** Oculta o conteúdo que excede a largura máxima das células da tabela de emendas */
    text-overflow: ellipsis; /** Adiciona reticências (...) ao final do texto que excede a largura máxima das células da tabela de emendas */
    white-space: nowrap; /** Impede quebra de linha nas células da tabela de emendas */
}
/** Estilos das células da tabela de emendas para descrições */
.emendas-table td.description { 
    max-width: 400px; /** Largura máxima das células de descrição */
    white-space: normal; /** Permite quebra de linha nas células de descrição */
    line-height: 1.4; /** Altura da linha das células de descrição */
}
/** Estilos dos botões de ação */
.action-buttons { 
    display: flex; /** Exibe os botões de ação em linha */
    gap: 0.5rem; /** Espaçamento entre os botões de ação */
    flex-wrap: wrap; /** Permite quebra de linha nos botões de ação */
}
/** Estilos dos botões de ação pequenos */
.btn-sm { 
    padding: 0.5rem 1rem; /** Espaçamento interno dos botões de ação pequenos */
    font-size: 0.875rem; /** Tamanho da fonte dos botões de ação pequenos */
}
/** Estilos da paginação */
.pagination { 
    display: flex; /** Exibe os itens da paginação em linha */
    justify-content: center; /** Alinha os itens da paginação ao centro */
    align-items: center; /** Alinha os itens da paginação verticalmente ao centro */
    gap: 0.5rem; /** Espaçamento entre os itens da paginação */
    margin-top: 2rem; /** Margem superior da paginação */
    flex-wrap: wrap; /** Permite quebra de linha nos itens da paginação */
}
/** Estilos dos links e spans da paginação */
.pagination a, .pagination span { 
    padding: 0.5rem 1rem; /** Espaçamento interno dos links e spans */
    border: 1px solid var(--border-color); 
    border-radius: 6px; /** Arredondamento dos cantos dos links e spans */
    text-decoration: none; /** Remove underline dos links */
    color: var(--dark-color); /** Cor do texto dos links e spans */
    transition: all 0.3s; /** Transição suave para os links e spans */
}
/** Estilos dos links e spans da paginação ao passar o mouse */
.pagination a:hover { 
    background-color: var(--primary-color); /** Cor de fundo ao passar o mouse */
    color: white; /** Cor do texto ao passar o mouse */
    border-color: var(--primary-color); /** Cor da borda ao passar o mouse */
}
/** Estilos do item atual da paginação */
.pagination .current { 
    background-color: var(--primary-color); 
    color: white; 
    border-color: var(--primary-color); 
}
/** Estilos das mensagens */
.message { 
    padding: 1rem; /** Espaçamento interno das mensagens */
    border-radius: 6px; /** Arredondamento dos cantos das mensagens */
    margin-bottom: 1.5rem; 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
}
/** Estilos das mensagens de sucesso */
.message-success { 
    background-color: #d4edda; 
    color: #155724; 
    border: 1px solid #c3e6cb; 
}
/** Estilos das mensagens de erro */
.message-error { 
    background-color: #f8d7da; 
    color: #721c24; 
    border: 1px solid #f5c6cb; 
}
/* Sugestões */
.sugestao-item { 
    border: 1px solid var(--border-color); /** Borda das sugestões */
    border-radius: 8px; /** Arredondamento dos cantos das sugestões */
    padding: 1rem; /** Espaçamento interno das sugestões */
    margin-bottom: 1rem; /** Espaçamento inferior das sugestões */
    background: #f8f9fa; /** Cor de fundo das sugestões */
}
/** Estilos do cabeçalho das sugestões */
.sugestao-header { 
    display: flex; 
    justify-content: space-between; /** Alinhamento entre os itens do cabeçalho das sugestões */
    align-items: center; /** Alinhamento vertical dos itens do cabeçalho das sugestões */
    margin-bottom: 0.5rem; /** Espaçamento inferior do cabeçalho das sugestões */
}
/** Estilos dos campos das sugestões */
.sugestao-campo { 
    font-weight: 600; 
    color: var(--primary-color); 
}
/** Estilos do formulário das sugestões */
.sugestao-form { 
    margin-top: 1rem; 
    padding-top: 1rem; 
    border-top: 1px solid var(--border-color); 
}
/** Estilos dos campos de texto das sugestões */
.sugestao-form textarea { 
    width: 100%; /** Largura total */
    min-height: 80px; /** Altura mínima */
    margin-bottom: 1rem; /** Espaçamento inferior */
}
/** Estilos dos campos das sugestões */
.sugestao-form select { 
    margin-bottom: 1rem; /** Espaçamento inferior */
    margin-right: 1rem; /** Espaçamento à direita */
}

/** Estilos responsivos para dispositivos móveis */
@media (max-width: 768px) {/* Estilos para telas menores que 768px */
    /** Esconde a sidebar */
    .admin-sidebar { 
        transform: translateX(-100%); /* Move a sidebar para fora da tela */
    }
    /** Exibe a sidebar */
    .admin-sidebar.active { 
        transform: translateX(0); /** Move a sidebar para dentro da tela */
    }
    /** Estilos do conteúdo principal */
    .admin-content { 
        margin-left: 0; /** Remove a margem esquerda */
        width: 100%; 
    }
    /** Estilos do botão de menu */
    .menu-toggle { 
        display: block; /** Exibe o botão de menu */
    }
    /** Estilos do cabeçalho */
    .admin-header { 
        padding: 1rem; /** Espaçamento interno do cabeçalho */
    }
    /** Estilos da área de conteúdo */
    .content-area { 
        padding: 1rem; /** Espaçamento interno da área de conteúdo */
    }
    /** Estilos da grade de filtros */
    .filters-grid { 
        grid-template-columns: 1fr; /** Define a grade de filtros como uma coluna */
    }
    /** Estilos dos botões de exportação */
    .export-buttons { 
        flex-direction: column; /** Alinhamento vertical dos botões de exportação */
    }
    /** Estilos das ações de filtro */
    .filter-actions { 
        flex-direction: column; /** Alinhamento vertical das ações de filtro */
    }
    /** Estilos da tabela de emendas */
    .emendas-table { 
        min-width: 1000px; /** Largura mínima da tabela de emendas */
    }
    /** Estilos dos botões de ação */
    .action-buttons { 
        flex-direction: column; /** Alinhamento vertical dos botões de ação */
    }
    /** Estilos da área do usuário */
    .user-area { 
        gap: 0.5rem; /** Espaçamento entre os elementos da área do usuário */
    }
    /** Estilos do nome do usuário */
    .user-name { 
        display: none; /** Esconde o nome do usuário */
    }
}
/** Estilos para telas menores que 480px */
@media (max-width: 480px) {
    /** Estilos do cabeçalho */
    .admin-header h1 { 
        font-size: 1.25rem; /** Tamanho da fonte do cabeçalho */
    }
    /** Estilos da tabela de emendas */
    .emendas-table { 
        min-width: 800px; /** Largura mínima da tabela de emendas */
    }
    /** Estilos das células da tabela de emendas */
    .emendas-table th, .emendas-table td { 
        padding: 0.5rem; /** Espaçamento interno das células */
        font-size: 0.875rem; /** Tamanho da fonte das células */
    }
}
/** Estilos do overlay da sidebar */
.sidebar-overlay { 
    display: none; /** Esconde o overlay da sidebar */
    position: fixed; /** Posiciona o overlay de forma fixa */
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%;
    background-color: rgba(0,0,0,0.5); 
    z-index: 99; /** Define a ordem do overlay da sidebar */
}
/** Estilos do overlay da sidebar quando ativo */
.sidebar-overlay.active { 
    display: block; /** Exibe o overlay da sidebar quando ativo */
}
</style>
</head>
<body>
    <!-- Container principal -->
<div class="admin-container">
    <!-- Sidebar -->
    <nav class="admin-sidebar" id="sidebar">
        <div class="sidebar-header">
            <!-- Logo do Painel Admin -->
            <h2>
                <i class="material-icons">admin_panel_settings</i> Painel Admin
            </h2>
        </div>
        <!-- Menu da Sidebar -->
        <div class="sidebar-menu">
            <!-- Itens do Menu -->
            <a href="admin_dashboard.php" class="active">
                <!-- Ícone do Menu -->
                <i class="material-icons">dashboard</i> Dashboard
            </a>
            <a href="gerenciar_usuarios.php">
                <!-- Ícone do Menu -->
                <i class="material-icons">people</i> Usuários
            </a>
            <a href="relatorios.php">
                <!-- Ícone do Menu -->
                <i class="material-icons">assessment</i> Relatórios
            </a>
            <a href="configuracoes.php">
                <!-- Ícone do Menu -->
                <i class="material-icons">settings</i> Configurações
            </a>
        </div>
    </nav>

    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div><!-- Overlay da Sidebar -->

    <!-- Conteúdo Principal -->
    <main class="admin-content">
        <!-- Cabeçalho -->
        <header class="admin-header">
            <!-- Logo do Painel Admin -->
            <div style="display: flex; align-items: center; gap: 1rem;">
                <!-- Ícone do Painel Admin -->
                <i class="material-icons">admin_panel_settings</i>
                <!-- Título do Painel Admin -->
                <h1>Painel Administrativo</h1>
            </div>
            <!-- Área do Usuário -->
            <div class="user-area">
                <!-- Ícone do Usuário -->
                <div class="user-icon"> <?= strtoupper(substr($_SESSION["user"]["nome"], 0, 1)) ?> </div>
                <!-- Nome do Usuário -->
                <span class="user-name"><?= htmlspecialchars($_SESSION["user"]["nome"]) ?></span>
                <!-- Botão de Logout -->
                <a href="../logout.php" class="logout-btn">
                    <!-- Ícone do Botão de Logout -->
                    <i class="material-icons">logout</i> Sair
                </a>
            </div>
        </header>

        <!-- Área de Conteúdo -->
        <div class="content-area">
            <!-- Mensagens de Sucesso ou Erro -->
            <?php if (isset($_SESSION["message"])): ?><!-- Mensagem -->
                <!-- Ícone da Mensagem -->
                <div class="message <?= strpos($_SESSION["message"], 'Erro') !== false ? 'message-error' : 'message-success' ?>">
                    <!-- Ícone -->
                    <i class="material-icons"><?= strpos($_SESSION["message"], 'Erro') !== false ? 'error' : 'check_circle' ?></i>
                    <!-- Mensagem -->
                    <?= htmlspecialchars($_SESSION["message"]) ?>
                </div>
                <?php unset($_SESSION["message"]); ?><!-- Limpa a mensagem após exibição -->
            <?php endif; ?> <!-- Fim da Mensagem -->

            <!-- Seção de Sincronização das Emendas -->
            <section class="sync-section">
                <h2>
                    <!-- Ícone da Sincronização -->
                    <i class="material-icons">sync</i> Sincronização
                </h2>
                <p>Sincronize os dados das emendas com a planilha Excel.</p>
                <!-- Botão de Sincronização -->
                <a href="?sincronizar=1" class="btn btn-warning">
                    <!-- Ícone do Botão de Sincronização -->
                    <i class="material-icons">sync</i> Sincronizar Dados
                </a>
            </section>

            <!-- Seção de Sugestões -->
            <section class="export-section">
                <!-- Ícone da Seção de Sugestões -->
                <h2>
                    <i class="material-icons">file_download</i> Exportar Dados
                </h2>
                <!-- Botões de Exportação -->
                <div class="export-buttons">
                    <!-- Botão de Exportação para Excel -->
                    <a href="?export=excel&<?= http_build_query($_GET) ?>" class="btn btn-success">
                        <!-- Ícone do Botão de Exportação para Excel -->
                        <i class="material-icons">table_chart</i> Exportar Excel
                    </a>
                    <!-- Botão de Exportação para PDF -->
                    <a href="?export=pdf&<?= http_build_query($_GET) ?>" class="btn btn-danger">
                        <!-- Ícone do Botão de Exportação para PDF -->
                        <i class="material-icons">picture_as_pdf</i> Exportar PDF
                    </a>
                </div>
            </section>

            <!-- Seção de Filtros -->
            <section class="filters-section">
                <!-- Ícone da Seção de Filtros -->
                <h2>
                    <i class="material-icons">filter_list</i> Filtros
                </h2>
                <!-- Formulário de Filtros -->
                <form method="GET" action="admin_dashboard.php"><!-- Ação do Formulário -->
                    <!-- Grade de Filtros -->
                    <div class="filters-grid">
                        <!-- Filtros -->
                        <div class="filter-group">
                            <!-- Rótulo do Filtro -->
                            <label for="tipo_caderno">Tipo de Caderno:</label>
                            <!-- Campo de Seleção do Filtro -->
                            <select name="tipo_caderno" id="tipo_caderno" class="form-control">
                                <!-- Opção Padrão -->
                                <option value="">Todos</option>
                                <!-- Opções de Tipos de Caderno -->
                                <?php foreach ($tipos_emenda as $tipo): ?>
                                    <!-- Opção de Tipo de Caderno -->
                                    <option value="<?= htmlspecialchars($tipo) ?>" <?= ($_GET["tipo_caderno"] ?? "") === $tipo ? "selected" : "" ?>><!-- Verifica se o Tipo de Caderno está selecionado -->
                                        <?= htmlspecialchars($tipo) ?> <!-- Exibe o Tipo de Caderno -->
                                    </option>
                                <?php endforeach; ?><!-- Fim das Opções de Tipos de Caderno -->
                            </select>
                        </div>
                        <!-- Filtro de Eixo Temático -->
                        <div class="filter-group">
                            <!-- Rótulo do Filtro -->
                            <label for="eixo_tematico">Eixo Temático:</label>
                            <!-- Campo de Seleção do Filtro -->
                            <select name="eixo_tematico" id="eixo_tematico" class="form-control"><!-- Ação do Formulário -->
                                <!-- Opção Padrão -->
                                <option value="">Selecione</option>
                                <!-- Opções de Eixos Temáticos -->
                                <?php foreach ($eixos_tematicos as $eixo): ?>
                                    <!-- Opção de Eixo Temático -->
                                    <option value="<?= htmlspecialchars($eixo) ?>" <?= ($_GET["eixo_tematico"] ?? "") === $eixo ? "selected" : "" ?>><!-- Verifica se o Eixo Temático está selecionado -->
                                        <!-- Exibe o Eixo Temático -->
                                        <?= htmlspecialchars($eixo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Filtro de Unidade Responsável -->
                        <div class="filter-group">
                            <label for="unidade_responsavel">Unidade Responsável:</label>
                            <select name="unidade_responsavel" id="unidade_responsavel" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($unidades as $unidade): ?><!-- Opção de Unidade Responsável -->
                                    <option value="<?= htmlspecialchars($unidade) ?>" <?= ($_GET["unidade_responsavel"] ?? "") === $unidade ? "selected" : "" ?>><!-- Verifica se a Unidade Responsável está selecionada -->
                                        <!-- Exibe a Unidade Responsável -->
                                        <?= htmlspecialchars($unidade) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Filtro de ODS -->
                        <div class="filter-group">
                            <label for="ods">ODS:</label>
                            <select name="ods" id="ods" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($ods_values as $ods): ?>
                                    <option value="<?= htmlspecialchars($ods) ?>" <?= ($_GET["ods"] ?? "") === $ods ? "selected" : "" ?>><!-- Verifica se o ODS está selecionado -->
                                        <!-- Exibe o ODS -->
                                        <?= htmlspecialchars($ods) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Filtro de Regionalização -->
                        <div class="filter-group">
                            <label for="regionalizacao">Regionalização:</label>
                            <select name="regionalizacao" id="regionalizacao" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($regionalizacoes as $reg): ?>
                                    <option value="<?= htmlspecialchars($reg) ?>" <?= ($_GET["regionalizacao"] ?? "") === $reg ? "selected" : "" ?>><!-- Verifica se a Regionalização está selecionada -->
                                        <!-- Exibe a Regionalização -->
                                        <?= htmlspecialchars($reg) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Filtro de Unidade Orçamentária -->
                        <div class="filter-group">
                            <label for="unidade_orcamentaria">Unidade Orçamentária:</label>
                            <select name="unidade_orcamentaria" id="unidade_orcamentaria" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($unidades_orcamentarias as $uo): ?>
                                    <option value="<?= htmlspecialchars($uo) ?>" <?= ($_GET["unidade_orcamentaria"] ?? "") === $uo ? "selected" : "" ?>><!-- Verifica se a Unidade Orçamentária está selecionada -->
                                        <!-- Exibe a Unidade Orçamentária -->
                                        <?= htmlspecialchars($uo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Filtro de Programa -->
                        <div class="filter-group">
                            <label for="programa">Programa:</label>
                            <select name="programa" id="programa" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($programas as $prog): ?>
                                    <option value="<?= htmlspecialchars($prog) ?>" <?= ($_GET["programa"] ?? "") === $prog ? "selected" : "" ?>><!-- Verifica se o Programa está selecionado -->
                                        <!-- Exibe o Programa -->
                                        <?= htmlspecialchars($prog) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Filtro de Ação -->
                        <div class="filter-group">
                            <label for="acao">Ação:</label>
                            <select name="acao" id="acao" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($acoes as $acao): ?>
                                    <option value="<?= htmlspecialchars($acao) ?>" <?= ($_GET["acao"] ?? "") === $acao ? "selected" : "" ?>><!-- Verifica se a Ação está selecionada -->
                                        <?= htmlspecialchars($acao) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Filtro de Categoria Econômica -->
                        <div class="filter-group">
                            <label for="categoria_economica">Categoria Econômica:</label>
                            <select name="categoria_economica" id="categoria_economica" class="form-control">
                                <option value="">Selecione</option>
                                <?php foreach ($categorias_economicas as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= ($_GET["categoria_economica"] ?? "") === $cat ? "selected" : "" ?>><!-- Verifica se a Categoria Econômica está selecionada -->
                                        <!-- Exibe a Categoria Econômica -->
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Filtro de Pontuação (De) -->
                        <div class="filter-group">
                            <!-- Rótulo do Filtro -->
                            <label for="pontuacao_de">Pontuação (De):</label>
                            <!-- Campo de Entrada do Filtro
                            step="0.01" permite valores decimais com duas casas após a vírgula
                            name="pontuacao_de" é o nome do campo que será enviado no formulário
                            id="pontuacao_de" é o identificador do campo para uso em JavaScript
                            value="<?= htmlspecialchars($_GET["pontuacao_de"] ?? "") ?>" preenche o campo com o valor atual, se existir
                            placeholder="0,00" é o texto de espaço reservado que aparece quando o campo está vazio                            va 
                            -->
                            <input type="number" step="0.01" name="pontuacao_de" id="pontuacao_de" class="form-control" value="<?= htmlspecialchars($_GET["pontuacao_de"] ?? "") ?>" placeholder="0,00"> <!-- Verifica se o campo de Pontuação (De) está preenchido -->
                        </div>
                        <!-- Filtro de Pontuação (Até) -->
                        <div class="filter-group">
                            <label for="pontuacao_ate">Pontuação (Até):</label>
                            <input type="number" step="0.01" name="pontuacao_ate" id="pontuacao_ate" class="form-control" value="<?= htmlspecialchars($_GET["pontuacao_ate"] ?? "") ?>" placeholder="0,00"><!-- Verifica se o campo de Pontuação (Até) está preenchido -->
                        </div>
                        <!-- Filtro de Valor Pretendido -->
                        <div class="filter-group">
                            <label for="valor_de">Valor Pretendido (De):</label>
                            <!-- Campo de Entrada do Filtro
                            step="0.01" permite valores decimais com duas casas após a vírgula
                            name="valor_de" é o nome do campo que será enviado no formulário
                            id="valor_de" é o identificador do campo para uso em JavaScript
                            value="<?= htmlspecialchars($_GET["valor_de"] ?? "") ?>" preenche o campo com o valor atual, se existir
                            placeholder="0,00" é o texto de espaço reservado que aparece quando o campo está vazio
                            -->
                            <input type="number" step="0.01" name="valor_de" id="valor_de" class="form-control" value="<?= htmlspecialchars($_GET["valor_de"] ?? "") ?>" placeholder="0,00">
                        </div>
                        <!-- Filtro de Valor Pretendido (Até) -->
                        <div class="filter-group">
                            <label for="valor_ate">Valor Pretendido (Até):</label>
                            <input type="number" step="0.01" name="valor_ate" id="valor_ate" class="form-control" value="<?= htmlspecialchars($_GET["valor_ate"] ?? "") ?>" placeholder="0,00">
                        </div>
                        <!-- Filtro de Ano do Projeto -->
                        <div class="filter-group">
                            <label for="ano_projeto">Ano do Projeto:</label>
                            <!-- Campo de Seleção do Filtro -->
                            <select name="ano_projeto" id="ano_projeto" class="form-control">
                                <!-- Opção Padrão -->
                                <option value="">Todos</option>
                                <?php foreach ($anos as $ano): ?>
                                    <option value="<?= htmlspecialchars($ano) ?>" <?= ($_GET["ano_projeto"] ?? "") == $ano ? "selected" : "" ?>><!-- Verifica se o Ano do Projeto está selecionado -->
                                        <!-- Exibe o Ano do Projeto -->
                                        <?= htmlspecialchars($ano) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Filtro de Outros Recursos -->
                        <div class="filter-group" id="outros_recursos_filter" style="display: none;">
                            <label for="outros_recursos">Outros Recursos:</label>
                            <!-- Campo de Seleção do Filtro
                            name="outros_recursos" é o nome do campo que será enviado no formulário
                            -->
                            <select name="outros_recursos" id="outros_recursos" class="form-control">
                                <!-- Opção Padrão -->
                                <option value="">Todos</option>
                                <!-- Opções de Outros Recursos -->
                                <option value="1" <?= ($_GET["outros_recursos"] ?? "") === "1" ? "selected" : "" ?>>Sim</option><!-- Verifica se Outros Recursos está selecionado como Sim -->
                                <option value="0" <?= ($_GET["outros_recursos"] ?? "") === "0" ? "selected" : "" ?>>Não</option><!-- Verifica se Outros Recursos está selecionado como Não -->
                            </select>
                        </div>

                    </div>
                    <!-- Ações dos Filtros -->
                    <div class="filter-actions">
                        <!-- Botão de Filtrar -->
                        <button type="submit" class="btn btn-primary">
                            <!-- Ícone do Botão de Filtrar -->
                            <i class="material-icons">search</i> Filtrar
                        </button>
                        <!-- Botão de Limpar Filtros -->
                        <a href="admin_dashboard.php" class="btn btn-secondary">
                            <!-- Ícone do Botão de Limpar Filtros -->
                            <i class="material-icons">clear</i> Limpar Filtros
                        </a>
                    </div>
                </form>
            </section>

            <!-- Seção de Sugestões Pendentes -->
            <?php if (!empty($sugestoes_pendentes)): ?>
                <!-- Ícone da Seção de Sugestões Pendentes -->
                <section class="sugestoes-section">
                    <!-- Título da Seção de Sugestões Pendentes -->
                    <h2>
                        <!-- Ícone da Seção de Sugestões Pendentes -->
                        <i class="material-icons">lightbulb</i> Sugestões Pendentes
                    </h2>
                    <!-- Lista de Sugestões Pendentes -->
                    <?php foreach ($sugestoes_pendentes as $sugestao): ?>
                        <!-- Item da Sugestão -->
                        <div class="sugestao-item">
                            <!-- Cabeçalho da Sugestão -->
                            <div class="sugestao-header">
                                <!-- Campo Sugerido -->
                                <span class="sugestao-campo"><?= htmlspecialchars($campos_editaveis[$sugestao['campo_sugerido']] ?? $sugestao['campo_sugerido']) ?></span><!-- Exibe o campo sugerido -->
                                <!-- Data de Criação da Sugestão -->
                                <small>Por: <?= htmlspecialchars($sugestao['usuario_nome']) ?> em <?= date('d/m/Y H:i', strtotime($sugestao['criado_em'])) ?></small><!-- Exibe o nome do usuário e a data de criação da sugestão -->
                            </div>
                            <!-- Conteúdo da Sugestão -->
                            <p><strong>Emenda:</strong> <?= htmlspecialchars(substr($sugestao['objeto_intervencao'], 0, 100)) ?>...</p><!-- Exibe o objeto de intervenção da emenda sugerida -->
                            <p><strong>Sugestão:</strong> <?= htmlspecialchars($sugestao['valor_sugerido']) ?></p><!-- Exibe o valor sugerido na sugestão -->
                            <!-- Formulário de Resposta à Sugestão -->
                            <form method="POST" class="sugestao-form"><!-- Ação do Formulário -->
                                <!-- Campos Ocultos do Formulário 
                                type="hidden" é usado para enviar dados que não são visíveis para o usuário
                                name="action" é o nome do campo que será enviado no formulário
                                value="responder_sugestao" é o valor do campo que indica a ação a ser executada
                                -->
                                <input type="hidden" name="action" value="responder_sugestao"><!-- Ação do Formulário -->
                                <input type="hidden" name="sugestao_id" value="<?= $sugestao['id'] ?>"><!-- ID da Sugestão -->
                                <textarea name="resposta" placeholder="Digite sua resposta..." required></textarea><!-- Campo de Texto para Resposta -->
                                <!-- Campo de Seleção de Status -->
                                <select name="status" required>
                                    <!-- Opções de Status -->
                                    <option value="">Selecione uma ação</option><!-- Opção Padrão -->
                                    <option value="aprovado">Aprovar</option><!-- Opção de Aprovação -->
                                    <option value="rejeitado">Rejeitar</option><!-- Opção de Rejeição -->
                                </select>
                                <!-- Botão de Envio do Formulário -->
                                <button type="submit" class="btn btn-primary">
                                    <!-- Ícone do Botão de Envio -->
                                    <i class="material-icons">send</i> Responder
                                </button>
                            </form><!-- Fim do Formulário de Resposta -->
                        </div><!-- Fim do Item da Sugestão -->
                    <?php endforeach; ?><!-- Fim da Lista de Sugestões Pendentes -->
                </section><!-- Fim da Seção de Sugestões Pendentes -->
            <?php endif; ?><!-- Fim da Verificação de Sugestões Pendentes -->

            <!-- Seção de Emendas Cadastradas -->
            <section class="emendas-section">
                <!-- Ícone da Seção de Emendas Cadastradas -->
                <h2>
                    <i class="material-icons">list</i> Emendas Cadastradas
                </h2>
                <!-- Tabela de Emendas -->
                <div class="table-container">
                    <table class="emendas-table">
                        <!-- Cabeçalho da Tabela -->
                        <thead>
                            <!-- Linhas do Cabeçalho da Tabela -->
                            <tr>
                                <!-- Colunas do Cabeçalho da Tabela -->
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
                        <!-- Corpo da Tabela -->
                        <tbody>
                            <!-- Verifica se há emendas -->
                            <?php if (empty($emendas)): ?>
                                <tr>
                                    <td colspan="14" style="text-align: center; padding: 2rem;">
                                        <i class="material-icons" style="font-size: 3rem; color: #ccc;">inbox</i>
                                        <p>Nenhuma emenda encontrada com os filtros aplicados.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <!-- Loop para exibir cada emenda -->
                                <?php foreach ($emendas as $emenda): ?>
                                    <tr>
                                        <!-- Colunas da Emenda -->
                                        <!-- Tipo de Emenda 
                                            htmlspecialchars() é usado para evitar XSS
                                            ?? '-' usado para exibir '-' caso o valor seja nulo
                                        -->
                                        <td><?= htmlspecialchars($emenda["tipo_emenda"] ?? '-') ?></td>
                                        <!-- Eixo Temático -->
                                        <td><?= htmlspecialchars($emenda["eixo_tematico"] ?? '-') ?></td>
                                        <!-- Órgão -->
                                        <td><?= htmlspecialchars($emenda["orgao"] ?? '-') ?></td>
                                        <!-- Objeto de Intervenção Pública 
                                        title="<?= htmlspecialchars($emenda["objeto_intervencao"] ?? '-') ?>" exibe o texto completo ao passar o mouse sobre a célula (tooltip)
                                        $emenda["objeto_intervencao"] ?? '-' exibe '-' caso o valor seja nulo
                                        -->
                                        <td class="description" title="<?= htmlspecialchars($emenda["objeto_intervencao"] ?? '-') ?>">
                                            <!-- Exibe os primeiros 140 caracteres do Objeto de Intervenção Pública 
                                            htmlspecialchars() é usado para evitar XSS
                                            substr() limita o texto a 140 caracteres
                                            strlen() verifica o comprimento do texto para adicionar "..." se for maior que 140 caracteres
                                            -->
                                            <?= htmlspecialchars(substr($emenda["objeto_intervencao"] ?? '-', 0, 140)) ?><?= strlen($emenda["objeto_intervencao"] ?? '') > 140 ? "..." : "" ?>
                                        </td>
                                        <!-- ODS -->
                                        <td><?= htmlspecialchars($emenda["ods"] ?? "-") ?></td>
                                        <!-- Pontuação -->
                                        <td><?= htmlspecialchars($emenda["pontuacao"] ?? "-") ?></td>
                                        <!-- Regionalização -->
                                        <td><?= htmlspecialchars($emenda["regionalizacao"] ?? "-") ?></td>
                                        <!-- Unidade Orçamentária Federal -->
                                        <td><?= htmlspecialchars($emenda["unidade_orcamentaria"] ?? "-") ?></td>
                                        <!-- Programa -->
                                        <td><?= htmlspecialchars($emenda["programa"] ?? "-") ?></td>
                                        <!-- Ação -->
                                        <td><?= htmlspecialchars($emenda["acao"] ?? "-") ?></td>
                                        <!-- Categoria Econômica da Despesa -->
                                        <td><?= htmlspecialchars($emenda["categoria_economica"] ?? "-") ?></td>
                                        <!-- Valor Pretendido 
                                        ?= isset($emenda["valor"]) ? number_format((float)$emenda["valor"], 2, ",", ".") : "-" formata o valor para o formato brasileiro (Ex.: R$ 1.234,56)
                                        -->
                                        <td>R$ <?= isset($emenda["valor"]) ? number_format((float)$emenda["valor"], 2, ",", ".") : "-" ?></td>
                                        <!-- Justificativa 
                                        title="<?= htmlspecialchars($emenda["justificativa"] ?? '') ?>" exibe o texto completo ao passar o mouse sobre a célula (tooltip)
                                        -->
                                        <td class="description" title="<?= htmlspecialchars($emenda["justificativa"] ?? '') ?>">
                                            <!-- Exibe os primeiros 120 caracteres da Justificativa 
                                            htmlspecialchars() é usado para evitar XSS
                                            substr() limita o texto a 120 caracteres
                                            strlen() verifica o comprimento do texto para adicionar "..." se for maior que 120 caracteres -->
                                            <?= htmlspecialchars(substr($emenda["justificativa"] ?? '-', 0, 120)) ?><?= strlen($emenda["justificativa"] ?? '') > 120 ? "..." : "" ?>
                                        </td>
                                        <!-- Data 
                                        ?= isset($emenda["criado_em"]) ? date('d/m/Y', strtotime($emenda["criado_em"])) : '-' formata a data para o formato brasileiro (Ex.: 31/12/2023)
                                        -->
                                        <td><?= isset($emenda["criado_em"]) ? date('d/m/Y', strtotime($emenda["criado_em"])) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?><!
                            <?php endif; ?>
                        </tbody>
                    </table><!-- Fim da Tabela de Emendas -->
                </div><!-- Fim do Container da Tabela -->

                <!-- Paginação -->
                <?php if ($total_paginas > 1): ?><!-- Verifica se há mais de uma página -->
                    <!-- Divisão de Paginação -->
                    <div class="pagination">
                        <!-- Ícone de Paginação -->
                        <?php if ($pagina_atual > 1): ?><!-- Verifica se não é a primeira página -->
                            <!-- Link para a Página Anterior 
                            ?pagina=<?= $pagina_atual - 1 ?> é o link para a página anterior
                            http_build_query() constrói a query string com os parâmetros atuais, exceto 'pagina'
                            array_filter() remove o parâmetro 'pagina' da query string
                            $_GET é usado para manter os outros parâmetros da URL
                            function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY) filtra os parâmetros
                            -->
                            <a href="?pagina=<?= $pagina_atual - 1 ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                <!-- Ícone de Anterior -->
                                <i class="material-icons">chevron_left</i> Anterior
                            </a>
                        <?php endif; ?>

                        <!-- Exibe os números das páginas -->
                        <!-- Loop para exibir os números das páginas 
                        $start = max(1, $pagina_atual - 2) define o início do loop
                        $end = min($total_paginas, $pagina_atual + 2) define o fim do loop
                        for ($i = $start; $i <= $end; $i++): inicia o loop
                        -->
                        <?php $start = max(1, $pagina_atual - 2); $end = min($total_paginas, $pagina_atual + 2); for ($i = $start; $i <= $end; $i++): ?><!-- Início do Loop -->
                            <!-- Verifica se é a página atual -->
                            <?php if ($i == $pagina_atual): ?><!-- Se for a página atual, exibe como ativo -->
                                <!-- Exibe a Página Atual -->
                                <span class="current"><?= $i ?></span><!-- Exibe o número da página atual -->
                            <?php else: ?>
                                <!-- Link para a Página -->
                                <!-- ?pagina=<?= $i ?> é o link para a página atual 
                                http_build_query() constrói a query string com os parâmetros atuais, exceto 'pagina'
                                $_GET é usado para manter os outros parâmetros da URL
                                function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)
                                array_filter() remove o parâmetro 'pagina' da query string
                                -->
                                <a href="?pagina=<?= $i ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) ?>"> <?= $i ?> </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <!-- Link para a Próxima Página -->
                        <!-- Verifica se não é a última página -->
                        <?php if ($pagina_atual < $total_paginas): ?><!-- Se não for a última página, exibe o link para a próxima -->
                            <!-- Link para a Próxima Página -->
                            <!-- 
                            ?pagina=<?= $pagina_atual + 1 ?> é o link para a próxima página
                            http_build_query() constrói a query string com os parâmetros atuais, exceto 'pagina 
                            -->
                            <a href="?pagina=<?= $pagina_atual + 1 ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'pagina'; }, ARRAY_FILTER_USE_KEY)) ?>"> Próxima <i class="material-icons">chevron_right</i> </a>
                        <?php endif; ?><!-- Fim do Link para a Próxima Página -->
                    </div><!-- Fim da Divisão de Paginação -->
                <?php endif; ?><!-- Fim da Verificação de Paginação -->

            </section><!-- Fim da Seção de Emendas Cadastradas -->

        </div><!-- Fim do Container Principal -->
    </main><!-- Fim do Conteúdo Principal -->
</div><!-- Fim do Container Principal -->

<script>
// Função para alternar a visibilidade da sidebar (mobile)
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');// Obtém o elemento da sidebar que contém os links de navegação, especialmente útil em dispositivos móveis
    const overlay = document.getElementById('sidebarOverlay');// Obtém o elemento do overlay, que escurece o fundo quando a sidebar está aberta
    sidebar.classList.toggle('active');// Alterna a classe 'active' na sidebar, que a mostra ou esconde dependendo do estado atual, especialmente em dispositivos móveis onde a sidebar pode ser ocultada para economizar espaço na tela
    overlay.classList.toggle('active');// Alterna a classe 'active' no overlay, que escurece o fundo indicando que a sidebar está aberta, focando a atenção do usuário na navegação
}

// Mostrar/ocultar filtro "Outros Recursos" baseado no tipo de caderno
// Manipula o DOM após o carregamento completo da página
document.addEventListener('DOMContentLoaded', function() {// Aguarda o carregamento completo do DOM antes de executar o código
    // Seleciona o elemento do tipo de caderno e o filtro de outros recursos
    const tipoSelect = document.getElementById('tipo_caderno');
    // Pega o elemento do filtro "Outros Recursos", que será mostrado ou escondido com base na seleção do tipo de caderno
    const outrosRecursosFilter = document.getElementById('outros_recursos_filter');
    // Se o elemento do tipo de caderno não existir, sai da função
    if (!tipoSelect) return;

    // Função para mostrar ou esconder o filtro "Outros Recursos"
    function toggleOutros() {
        // Pega o valor selecionado no campo de tipo de caderno
        const v = tipoSelect.value;
        // mostra o filtro se o tipo for OPERAÇÃO DE CRÉDITO ou OUTROS RECURSOS (ajustável)
        if (v === 'OPERAÇÃO DE CRÉDITO' || v === 'OUTROS RECURSOS') {// Verifica se o valor selecionado é "OPERAÇÃO DE CRÉDITO" ou "OUTROS RECURSOS"
            outrosRecursosFilter.style.display = 'block';// Mostra o filtro "Outros Recursos" se a condição for atendida
        } else {// Caso contrário, esconde o filtro e limpa a seleção
            // Limpa a seleção e esconde o filtro se o tipo for diferente
            outrosRecursosFilter.style.display = 'none';
            const sel = document.getElementById('outros_recursos');// Obtém o elemento do filtro "Outros Recursos"
            if (sel) sel.value = '';// Limpa a seleção do filtro "Outros Recursos" se o elemento existir
        }
    }

    // Adiciona um ouvinte de evento que chama a função toggleOutros sempre que o valor do campo de tipo de caderno mudar
    tipoSelect.addEventListener('change', toggleOutros);
    // Chama a função inicialmente para definir o estado correto ao carregar a página
    toggleOutros();
});

// Fechar sidebar ao clicar fora (mobile)
document.addEventListener('click', function(event) {// Adiciona um ouvinte de evento para cliques em qualquer lugar do documento
    // Fecha a sidebar se o clique for fora dela e do botão de menu
    const sidebar = document.getElementById('sidebar');// Obtém o elemento da sidebar
    // Obtém o elemento do botão de menu, que é usado para abrir/fechar a sidebar em dispositivos móveis
    const menuToggle = document.querySelector('.menu-toggle');

    /* Verifica se a largura da janela é menor ou igual a 768px (tamanho típico de dispositivos móveis). window.innerWidth obtém a largura atual da janela do navegador, 
        !sidebar.contains(event.target) verifica se o clique foi fora da sidebar, 
        !menuToggle.contains(event.target) verifica se o clique foi fora do botão de menu, 
        e sidebar.classList.contains('active') verifica se a sidebar está atualmente aberta. 
        Se todas essas condições forem verdadeiras, a função toggleSidebar() é chamada para fechar a sidebar.
    */
    if (window.innerWidth <= 768 && sidebar && menuToggle && !sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('active')) {
        toggleSidebar();
    }
});

// Ajusta a sidebar ao redimensionar a janela
window.addEventListener('resize', function() {// Adiciona um ouvinte de evento para redimensionamento da janela
    // Fecha a sidebar se a janela for redimensionada para um tamanho maior que 768
    const sidebar = document.getElementById('sidebar');// Obtém o elemento da sidebar, que contém os links de navegação, especialmente útil em dispositivos móveis
    // Obtém o elemento do overlay, que escurece o fundo quando a sidebar está aberta
    const overlay = document.getElementById('sidebarOverlay');
    // Se a largura da janela for maior que 768px, remove a classe 'active' da sidebar e do overlay para garantir que ambos estejam ocultos em telas maiores
    if (window.innerWidth > 768) {
        sidebar.classList.remove('active');// Remove a classe 'active' da sidebar para escondê-la em telas maiores, onde a sidebar deve estar sempre visível
        overlay.classList.remove('active');// Remove a classe 'active' do overlay para garantir que o fundo não fique escurecido em telas maiores
    }
});
</script>
</body>
</html>
