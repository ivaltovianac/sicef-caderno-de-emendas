<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class SincronizadorEmendas {
    private $pdo;
    private $arquivo;
    
    public function __construct($pdo, $arquivo) {
        $this->pdo = $pdo;
        $this->arquivo = $arquivo;
    }
    
    public function sincronizar() {
        try {
            if (!file_exists($this->arquivo)) {
                throw new Exception("Arquivo não encontrado: " . $this->arquivo);
            }
            
            $spreadsheet = IOFactory::load($this->arquivo);
            $sheet = $spreadsheet->getActiveSheet();
            
            $this->pdo->beginTransaction();
            
            $stmtInsert = $this->pdo->prepare("
                INSERT INTO emendas (
                    tipo_emenda, eixo_tematico, orgao, objeto_intervencao, ods, valor,
                    justificativa, regionalizacao, unidade_orcamentaria, programa, 
                    acao, categoria_economica, hash_linha
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmtUpdate = $this->pdo->prepare("
                UPDATE emendas SET
                    tipo_emenda = ?, eixo_tematico = ?, orgao = ?, objeto_intervencao = ?, 
                    ods = ?, valor = ?, justificativa = ?, regionalizacao = ?,
                    unidade_orcamentaria = ?, programa = ?, acao = ?, categoria_economica = ?,
                    atualizado_em = CURRENT_TIMESTAMP
                WHERE hash_linha = ?
            ");
            
            $stmtCheck = $this->pdo->prepare("SELECT id FROM emendas WHERE hash_linha = ?");
            
            $total = 0;
            $novos = 0;
            $atualizados = 0;
            
            foreach ($sheet->getRowIterator(2) as $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $cells[] = $cell->getValue();
                }
                
                if (empty($cells[0])) continue;
                
                $total++;
                
                $tipo = $cells[0] ?? '';
                $eixo = $cells[1] ?? '';
                $orgao = $cells[2] ?? '';
                $objeto = $cells[3] ?? '';
                $ods = $cells[4] ?? '';
                $valor = str_replace(['.', ','], ['', '.'], $cells[10] ?? '0');
                $justificativa = $cells[11] ?? '';
                $regionalizacao = $cells[5] ?? '';
                $unidade = $cells[6] ?? '';
                $programa = $cells[7] ?? '';
                $acao = $cells[8] ?? '';
                $categoria = $cells[9] ?? '';
                
                $hash = hash('sha256', implode('|', [
                    $tipo, $eixo, $orgao, $objeto, $ods, $valor, $justificativa,
                    $regionalizacao, $unidade, $programa, $acao, $categoria
                ]));
                
                $stmtCheck->execute([$hash]);
                $existente = $stmtCheck->fetch();
                
                if ($existente) {
                    $stmtUpdate->execute([
                        $tipo, $eixo, $orgao, $objeto, $ods, $valor, $justificativa,
                        $regionalizacao, $unidade, $programa, $acao, $categoria, $hash
                    ]);
                    $atualizados++;
                } else {
                    $stmtInsert->execute([
                        $tipo, $eixo, $orgao, $objeto, $ods, $valor, $justificativa,
                        $regionalizacao, $unidade, $programa, $acao, $categoria, $hash
                    ]);
                    $novos++;
                }
            }
            
            $this->registrarSincronizacao($total, $novos, $atualizados, 'sucesso');
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => "Sincronização concluída. Total: $total, Novos: $novos, Atualizados: $atualizados"
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->registrarSincronizacao($total ?? 0, $novos ?? 0, $atualizados ?? 0, 'erro', $e->getMessage());
            
            return [
                'success' => false,
                'message' => "Erro na sincronização: " . $e->getMessage()
            ];
        }
    }
    
    private function registrarSincronizacao($total, $novos, $atualizados, $status, $mensagem = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO sincronizacoes 
            (total_registros, novos_registros, registros_atualizados, status, mensagem)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$total, $novos, $atualizados, $status, $mensagem]);
    }
}
?>