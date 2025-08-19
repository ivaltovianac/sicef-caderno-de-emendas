
<?php
// SICEF-caderno-de-emendas/public/user/remover_emenda.php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION["user"]["is_admin"]) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";

$usuario_id = $_SESSION["user"]["id"];
$emenda_id = (int)($_GET['id'] ?? 0);

if (empty($emenda_id)) {
    $_SESSION['error'] = "ID da emenda não fornecido.";
    header("Location: minhas_emendas.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verifica se a emenda pertence ao usuário
        $stmt = $pdo->prepare("SELECT 1 FROM usuario_emendas WHERE usuario_id = ? AND emenda_id = ?");
        $stmt->execute([$usuario_id, $emenda_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Emenda não encontrada ou você não tem permissão para removê-la.");
        }

        // Remover a emenda
        $stmt = $pdo->prepare("DELETE FROM usuario_emendas WHERE usuario_id = ? AND emenda_id = ?");
        
        if ($stmt->execute([$usuario_id, $emenda_id])) {
            $_SESSION['message'] = "Emenda removida com sucesso!";
        } else {
            throw new Exception("Erro ao remover emenda.");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    } catch (PDOException $e) {
        error_log("Erro ao remover emenda: " . $e->getMessage());
        $_SESSION['error'] = "Erro interno do servidor.";
    }
    
    header("Location: minhas_emendas.php");
    exit;
}

// Se não for POST, redirecionar
header("Location: minhas_emendas.php");
exit;
