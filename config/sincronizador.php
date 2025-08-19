<?php
/**
 * Sincronizador de Emendas - SICEF Caderno de Emendas
 * 
 * Esta classe é responsável por sincronizar os dados de emendas a partir de uma planilha Excel.
 * Ela processa o arquivo, extrai os dados e os insere ou atualiza no banco de dados.
 * 
 * Funcionalidades:
 * - Leitura de planilhas Excel
 * - Processamento e validação de dados
 * - Inserção de novos registros
 * - Atualização de registros existentes
 * - Geração de log de sincronização
 * - Tratamento de erros
 * 
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */

// \sicef-caderno-de-emendas\config\sincronizador.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class SincronizadorEmendas
{
    private $pdo;
    private $arquivo;

    /**
     * Construtor da classe
     * 
     * @param PDO $pdo Conexão com o banco de dados
     * @param string $arquivo Caminho para o arquivo Excel
     */
    public function __construct($pdo, $arquivo)
    {
        $this->pdo = $pdo;
        $this->arquivo = $arquivo;
    }

    /**
     * Sincroniza os dados da planilha com o banco de dados
     * 
     * @return array Resultado da operação com status e mensagem
     */
    public function sincronizar()
    {
        try {
            if (!file_exists($this->arquivo)) {
                throw new Exception("Arquivo não encontrado: " . $this->arquivo);
            }

            $spreadsheet = IOFactory::load($this->arquivo);
            $sheet = $spreadsheet->getActiveSheet();
            $this->pdo->beginTransaction();

            $total = 0;
            $novos = 0;
            $atualizados = 0;

            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $cells[] = $cell->getValue();
                }

                // Verifica se a linha está vazia (pelo menos o primeiro campo)
                if (count($cells) < 12 || empty(trim((string) $cells[0])))
                    continue;

                $total++;

                // Mapear colunas corretamente conforme planilha Excel
                $tipo = isset($cells[0]) ? (string) $cells[0] : '';
                $eixo = isset($cells[1]) ? (string) $cells[1] : '';
                $orgao = isset($cells[2]) ? (string) $cells[2] : '';
                $objeto = isset($cells[3]) ? (string) $cells[3] : '';
                $ods = isset($cells[4]) ? (string) $cells[4] : '';
                $regionalizacao = isset($cells[5]) ? (string) $cells[5] : '';
                $unidade = isset($cells[6]) ? (string) $cells[6] : '';
                $programa = isset($cells[7]) ? (string) $cells[7] : '';
                $acao = isset($cells[8]) ? (string) $cells[8] : '';
                $categoria = isset($cells[9]) ? (string) $cells[9] : '';
                $valor_str = isset($cells[10]) ? (string) $cells[10] : '0';
                $justificativa = isset($cells[11]) ? (string) $cells[11] : '';

                // Processar valor - remover caracteres não numéricos e converter para formato decimal
                $valor = $this->processarValor($valor_str);

                // Calcular hash da linha para identificação única
                $hash = md5($tipo . $eixo . $orgao . $objeto . $ods . $valor . $justificativa . $regionalizacao . $unidade . $programa . $acao . $categoria);

                // Verificar se registro já existe pelo hash
                $stmtCheck = $this->pdo->prepare("SELECT id FROM emendas WHERE hash_linha = ?");
                $stmtCheck->execute([$hash]);

                if ($stmtCheck->fetch()) {
                    // Update existing record
                    $stmtUpdate = $this->pdo->prepare("
                        UPDATE emendas SET 
                            tipo_emenda = ?, eixo_tematico = ?, orgao = ?, objeto_intervencao = ?, 
                            ods = ?, valor = ?, justificativa = ?, regionalizacao = ?, 
                            unidade_orcamentaria = ?, programa = ?, acao = ?, categoria_economica = ?, 
                            valor_pretendido = ?, atualizado_em = NOW() 
                        WHERE hash_linha = ?");
                    $stmtUpdate->execute([
                        $tipo,
                        $eixo,
                        $orgao,
                        $objeto,
                        $ods,
                        $valor,
                        $justificativa,
                        $regionalizacao,
                        $unidade,
                        $programa,
                        $acao,
                        $categoria,
                        $valor,
                        $hash
                    ]);
                    $atualizados++;
                } else {
                    // Insire um novo registro com os tipos de dados
                    $stmtInsert = $this->pdo->prepare("
                        INSERT INTO emendas (
                            tipo_emenda, eixo_tematico, orgao, objeto_intervencao, ods, valor, 
                            justificativa, regionalizacao, unidade_orcamentaria, programa, acao, 
                            categoria_economica, hash_linha, valor_pretendido, criado_em, atualizado_em
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    $stmtInsert->execute([
                        $tipo,
                        $eixo,
                        $orgao,
                        $objeto,
                        $ods,
                        $valor,
                        $justificativa,
                        $regionalizacao,
                        $unidade,
                        $programa,
                        $acao,
                        $categoria,
                        $hash,
                        $valor
                    ]);
                    $novos++;
                }
            }

            // Registra a sincronização na tabela de log
            $stmtLog = $this->pdo->prepare("
                INSERT INTO sincronizacoes (data_hora, total_registros, novos_registros, registros_atualizados, status, mensagem)
                VALUES (NOW(), ?, ?, ?, 'sucesso', ?)");
            $mensagem = "Sincronização concluída com sucesso";
            $stmtLog->execute([$total, $novos, $atualizados, $mensagem]);

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "Sincronização concluída! Total: $total, Novos: $novos, Atualizados: $atualizados"
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erro na sincronização: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Erro na sincronização: " . $e->getMessage()
            ];
        }
    }

    /**
     * Processa o valor monetário da planilha
     * 
     * @param string $valor_str Valor em formato de string
     * @return float Valor convertido para formato decimal
     */
    private function processarValor($valor_str)
    {
        // Remove caracteres não numéricos exceto vírgula e ponto
        $valor_str = preg_replace('/[^\d.,]/', '', $valor_str);

        // Remove os pontos separadores de milhar
        $valor_str = str_replace('.', '', $valor_str);

        // Substitui a vírgula decimal por ponto
        $valor_str = str_replace(',', '.', $valor_str);

        // Converte para float
        return (float) $valor_str;
    }
}