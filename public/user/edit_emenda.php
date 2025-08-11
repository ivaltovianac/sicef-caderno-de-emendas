<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";

$emenda_id = $_GET["id"] ?? null;
$usuario_id = $_SESSION["user"]["id"];
$emenda = null;
$observacoes_usuario = "";

if ($emenda_id) {
    // Buscar a emenda e as observações do usuário para ela
    $stmt = $pdo->prepare("SELECT e.*, ue.observacoes_usuario FROM emendas e JOIN usuario_emendas ue ON e.id = ue.emenda_id WHERE e.id = ? AND ue.usuario_id = ?");
    $stmt->execute([$emenda_id, $usuario_id]);
    $emenda = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($emenda) {
        $observacoes_usuario = $emenda["observacoes_usuario"] ?? "";
    } else {
        $_SESSION["message"] = "Emenda não encontrada ou não associada ao seu usuário.";
        header("Location: minhas_emendas.php");
        exit;
    }
} else {
    $_SESSION["message"] = "ID da emenda não fornecido.";
    header("Location: minhas_emendas.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nova_observacao = $_POST["observacoes_usuario"] ?? "";

    try {
        $stmt = $pdo->prepare("UPDATE usuario_emendas SET observacoes_usuario = ? WHERE emenda_id = ? AND usuario_id = ?");
        $stmt->execute([$nova_observacao, $emenda_id, $usuario_id]);
        $_SESSION["message"] = "Observações da emenda atualizadas com sucesso!";
        header("Location: minhas_emendas.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION["message"] = "Erro ao atualizar observações: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Emenda - CICEF</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007b5e;
            --secondary-color: #4db6ac;
            --accent-color: #ffc107;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
            --border-color: #e0e0e0;
            --error-color: #e74c3c;
            --success-color: #2ecc71;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        h1 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #006a50;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Editar Observações da Emenda</h1>
        
        <p><strong>Emenda ID:</strong> <?= htmlspecialchars($emenda["id"]) ?></p>
        <p><strong>Objeto de Intervenção:</strong> <?= htmlspecialchars($emenda["objeto_intervencao"]) ?></p>
        <p><strong>Valor Pretendido:</strong> R$ <?= number_format($emenda["valor_pretendido"], 2, ",", ".") ?></p>

        <form method="POST" action="edit_emenda.php?id=<?= $emenda["id"] ?>">
            <div class="form-group">
                <label for="observacoes_usuario">Suas Observações:</label>
                <textarea id="observacoes_usuario" name="observacoes_usuario" class="form-control"><?= htmlspecialchars($observacoes_usuario) ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="minhas_emendas.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>


