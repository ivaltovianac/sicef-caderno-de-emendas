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

$campos_editaveis = [
    'objeto_intervencao' => 'Objeto de Intervenção',
    'valor' => 'Valor',
    'eixo_tematico' => 'Eixo Temático',
    'orgao' => 'Unidade Responsável',
    'ods' => 'ODS',
    'pontuacao' => 'Pontuação',
    'outros_recursos' => 'Outros Recursos'
];

if ($emenda_id) {
    $stmt = $pdo->prepare("SELECT * FROM emendas WHERE id = ?");
    $stmt->execute([$emenda_id]);
    $emenda = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emenda) {
        $_SESSION["message"] = "Emenda não encontrada.";
        header("Location: minhas_emendas.php");
        exit;
    }
} else {
    $_SESSION["message"] = "ID da emenda não fornecido.";
    header("Location: minhas_emendas.php");
    exit;
}

$stmt_sugestoes = $pdo->prepare("SELECT * FROM sugestoes_emendas WHERE emenda_id = ? AND usuario_id = ? ORDER BY criado_em DESC");
$stmt_sugestoes->execute([$emenda_id, $usuario_id]);
$sugestoes = $stmt_sugestoes->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $campo_sugerido = $_POST["campo_sugerido"] ?? "";
    $sugestao = $_POST["sugestao"] ?? "";

    if (empty($campo_sugerido) || empty($sugestao)) {
        $_SESSION["message"] = "Erro: Todos os campos são obrigatórios.";
    } elseif (!array_key_exists($campo_sugerido, $campos_editaveis)) {
        $_SESSION["message"] = "Erro: Campo inválido selecionado.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO sugestoes_emendas (emenda_id, usuario_id, campo_sugerido, valor_sugerido, status) VALUES (?, ?, ?, ?, 'pendente')");
            $stmt->execute([$emenda_id, $usuario_id, $campo_sugerido, $sugestao]);
            
            $stmt_notificacao = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem, referencia_id) VALUES (?, 'sugestao_emenda', ?, ?)");
            $admin_id = 1;
            $mensagem = "Nova sugestão para a emenda ID: " . $emenda_id;
            $stmt_notificacao->execute([$admin_id, $mensagem, $emenda_id]);
            
            $_SESSION["message"] = "Sugestão enviada com sucesso!";
            header("Location: minhas_emendas.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION["message"] = "Erro ao enviar sugestão: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sugerir Alteração - CICEF</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            width: 90%;
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #007b5e;
            margin-top: 0;
        }
        .emenda-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #007b5e;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
        }
        select.form-control {
            height: 40px;
        }
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        .btn-primary {
            background-color: #007b5e;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .sugestoes-list {
            margin-top: 40px;
        }
        .sugestoes-list h3 {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .sugestao-item {
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background: #fafafa;
        }
        .sugestao-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .sugestao-campo {
            font-weight: bold;
            color: #007b5e;
        }
        .sugestao-status {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-aprovado {
            background-color: #d4edda;
            color: #155724;
        }
        .status-rejeitado {
            background-color: #f8d7da;
            color: #721c24;
        }
        .sugestao-resposta {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 4px;
            border: 1px solid #eee;
        }
        .sugestao-data {
            font-size: 0.8rem;
            color: #666;
            margin-top: 10px;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sugerir Alteração para Emenda</h1>
        
        <?php if (isset($_SESSION["message"])): ?>
            <div class="message <?= strpos($_SESSION["message"], 'Erro') !== false ? 'message-error' : 'message-success' ?>">
                <?= htmlspecialchars($_SESSION["message"]) ?>
            </div>
            <?php unset($_SESSION["message"]); ?>
        <?php endif; ?>
        
        <div class="emenda-info">
            <p><strong>Emenda ID:</strong> <?= htmlspecialchars($emenda["id"]) ?></p>
            <p><strong>Objeto de Intervenção:</strong> <?= htmlspecialchars($emenda["objeto_intervencao"]) ?></p>
            <p><strong>Valor:</strong> R$ <?= number_format($emenda["valor"], 2, ",", ".") ?></p>
        </div>

        <form method="POST" action="suggest_emenda.php?id=<?= $emenda["id"] ?>" class="sugestao-form">
            <div class="form-group">
                <label for="campo_sugerido">Campo a Sugerir Alteração:</label>
                <select id="campo_sugerido" name="campo_sugerido" class="form-control" required>
                    <option value="">Selecione um campo</option>
                    <?php foreach ($campos_editaveis as $campo => $label): ?>
                        <option value="<?= htmlspecialchars($campo) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="sugestao">Sugestão:</label>
                <textarea id="sugestao" name="sugestao" class="form-control" required 
                          placeholder="Descreva sua sugestão de alteração..."></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="material-icons" style="font-size: 18px;">send</i>
                    Enviar Sugestão
                </button>
                <a href="minhas_emendas.php" class="btn btn-secondary">
                    <i class="material-icons" style="font-size: 18px;">cancel</i>
                    Cancelar
                </a>
            </div>
        </form>

        <?php if (!empty($sugestoes)): ?>
        <div class="sugestoes-list">
            <h3>Histórico de Sugestões</h3>
            
            <?php foreach ($sugestoes as $sugestao): ?>
                <div class="sugestao-item">
                    <div class="sugestao-header">
                        <span class="sugestao-campo"><?= htmlspecialchars($campos_editaveis[$sugestao['campo_sugerido']] ?? $sugestao['campo_sugerido']) ?></span>
                        <span class="sugestao-status status-<?= htmlspecialchars($sugestao['status'] ?? 'pendente') ?>">
                            <?= htmlspecialchars(ucfirst($sugestao['status'] ?? 'pendente')) ?>
                        </span>
                    </div>
                    <p><strong>Sugestão:</strong> <?= htmlspecialchars($sugestao['valor_sugerido']) ?></p>
                    
                    <?php if (!empty($sugestao['resposta'])): ?>
                        <div class="sugestao-resposta">
                            <strong>Resposta do Administrador:</strong> <?= htmlspecialchars($sugestao['resposta']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="sugestao-data">
                        <small>Enviado em: <?= date('d/m/Y H:i', strtotime($sugestao['criado_em'])) ?></small>
                        <?php if (!empty($sugestao['respondido_em'])): ?>
                            <small> | Respondido em: <?= date('d/m/Y H:i', strtotime($sugestao['respondido_em'])) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>