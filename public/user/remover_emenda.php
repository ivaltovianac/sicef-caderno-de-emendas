<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emenda_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM usuario_emendas WHERE usuario_id = ? AND emenda_id = ?");
        $stmt->execute([$_SESSION['user']['id'], $_POST['emenda_id']]);
        
        $_SESSION['success'] = "Emenda removida com sucesso!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erro ao remover emenda: " . $e->getMessage();
    }
}

header('Location: user_dashboard.php');
exit;
?>