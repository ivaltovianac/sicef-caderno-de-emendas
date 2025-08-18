
<?php
// SICEF-caderno-de-emendas/public/admin/processar_sugestoes.php
session_start();
if (!isset($_SESSION["user"]) || !$_SESSION["user"]["is_admin"]) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sugestao_id = (int)($_POST['sugestao_id'] ?? 0);
        $acao = $_POST['acao'] ?? '';
        $resposta = trim($_POST['resposta'] ?? '');
        $aplicar_mudanca = isset($_POST['aplicar_mudanca']) && $_POST['aplicar_mudanca'] === '1';

        if (empty($sugestao_id) || empty($acao) || !in_array($acao, ['aprovar', 'rejeitar'])) {
            throw new Exception('Dados inválidos');
        }

        $pdo->beginTransaction();

        // Busca dados da sugestão
        $stmt_sugestao = $pdo->prepare("
            SELECT s.*, e.* 
            FROM sugestoes_emendas s 
            JOIN emendas e ON s.emenda_id = e.id 
            WHERE s.id = ? AND s.status = 'pendente'
        ");
        $stmt_sugestao->execute([$sugestao_id]);
        $sugestao = $stmt_sugestao->fetch(PDO::FETCH_ASSOC);

        if (!$sugestao) {
            throw new Exception('Sugestão não encontrada ou já processada');
        }

        $status = ($acao === 'aprovar') ? 'aprovado' : 'rejeitado';

        // Atualiza status da sugestão
        $stmt = $pdo->prepare("UPDATE sugestoes_emendas 
                              SET status = ?, resposta = ?, respondido_em = NOW(), respondido_por = ? 
                              WHERE id = ?");
        $stmt->execute([$status, $resposta, $_SESSION['user']['id'], $sugestao_id]);

        // Se aprovado e deve aplicar mudança, atualizar a emenda
        if ($acao === 'aprovar' && $aplicar_mudanca) {
            $campo_permitido = in_array($sugestao['campo_sugerido'], [
                'objeto_intervencao', 'valor', 'eixo_tematico', 'orgao', 'ods', 
                'justificativa', 'regionalizacao', 'unidade_orcamentaria', 
                'programa', 'acao', 'categoria_economica'
            ]);

            if ($campo_permitido) {
                $stmt_update = $pdo->prepare("UPDATE emendas 
                                            SET {$sugestao['campo_sugerido']} = ?, atualizado_em = NOW() 
                                            WHERE id = ?");
                $stmt_update->execute([$sugestao['valor_sugerido'], $sugestao['emenda_id']]);
            }
        }

        // Cria notificação para o usuário
        $mensagem_notif = $acao === 'aprovar' 
            ? "Sua sugestão foi aprovada" . ($aplicar_mudanca ? " e aplicada" : "") 
            : "Sua sugestão foi rejeitada";
        
        if (!empty($resposta)) {
            $mensagem_notif .= ": " . $resposta;
        }

        $stmt_notif = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem, referencia_id, criado_em) 
                                    SELECT usuario_id, 'resposta_sugestao', ?, id, NOW() 
                                    FROM sugestoes_emendas WHERE id = ?");
        $stmt_notif->execute([$mensagem_notif, $sugestao_id]);

        $pdo->commit();

        $response['success'] = true;
        $response['message'] = 'Sugestão processada com sucesso!';

    } catch (Exception $e) {
        $pdo->rollBack();
        $response['message'] = $e->getMessage();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao processar sugestão: " . $e->getMessage());
        $response['message'] = 'Erro interno do servidor';
    }
}

// Retornar JSON para requisições AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Redireciona com mensagem para requisições normais
$_SESSION['message'] = $response['message'];
$redirect = $_POST['redirect'] ?? 'sugestoes.php';
header("Location: $redirect");
exit;
