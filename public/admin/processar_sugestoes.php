<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sugestao_id = $_POST["sugestao_id"] ?? null;
    $resposta = $_POST["resposta"] ?? "";
    $status = $_POST["status"] ?? "rejeitado";
    
    try {
        // Atualizar a sugestão com a resposta do admin
        $stmt = $pdo->prepare("UPDATE sugestoes_emendas 
                              SET status = ?, resposta = ?, respondido_em = NOW(), respondido_por = ?
                              WHERE id = ?");
        $stmt->execute([$status, $resposta, $_SESSION["admin"]["id"], $sugestao_id]);
        
        // Se aprovada, aplicar a alteração à emenda original
        if ($status === 'aprovado') {
            // Primeiro obtemos os dados da sugestão
            $stmt_sugestao = $pdo->prepare("SELECT emenda_id, campo_sugerido, valor_sugerido 
                                           FROM sugestoes_emendas 
                                           WHERE id = ?");
            $stmt_sugestao->execute([$sugestao_id]);
            $sugestao = $stmt_sugestao->fetch(PDO::FETCH_ASSOC);
            
            if ($sugestao) {
                // Atualizar o campo na emenda original
                $stmt_update = $pdo->prepare("UPDATE emendas 
                                             SET {$sugestao['campo_sugerido']} = ? 
                                             WHERE id = ?");
                $stmt_update->execute([$sugestao['valor_sugerido'], $sugestao['emenda_id']]);
            }
        }
        
        // Enviar notificação para o usuário
        $stmt_notif = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem, referencia_id) 
                                    SELECT usuario_id, 'resposta_sugestao', ?, id 
                                    FROM sugestoes_emendas WHERE id = ?");
        $mensagem = "Sua sugestão #$sugestao_id foi $status. Resposta: " . substr($resposta, 0, 100);
        $stmt_notif->execute([$mensagem, $sugestao_id]);
        
        $_SESSION["message"] = "Sugestão processada com sucesso!";
    } catch (PDOException $e) {
        $_SESSION["message"] = "Erro ao processar sugestão: " . $e->getMessage();
    }
    
    header("Location: admin_dashboard.php");
    exit;
}
?>