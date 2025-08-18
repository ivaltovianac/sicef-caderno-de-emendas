
<?php
// SICEF-caderno-de-emendas/public/user/sugestoes_emenda.php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION["user"]["is_admin"]) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";

$usuario_id = $_SESSION["user"]["id"];
$message = "";
$error = "";

// Processar envio de sugestão
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $emenda_id = (int)$_POST['emenda_id'];
        $campo_sugerido = $_POST['campo_sugerido'];
        $valor_sugerido = trim($_POST['valor_sugerido']);

        if (empty($emenda_id) || empty($campo_sugerido) || empty($valor_sugerido)) {
            throw new Exception("Todos os campos são obrigatórios.");
        }

        $stmt = $pdo->prepare("INSERT INTO sugestoes_emendas (emenda_id, usuario_id, campo_sugerido, valor_sugerido, criado_em) VALUES (?, ?, ?, ?, NOW())");
        
        if ($stmt->execute([$emenda_id, $usuario_id, $campo_sugerido, $valor_sugerido])) {
            $message = "Sugestão enviada com sucesso!";
        } else {
            throw new Exception("Erro ao enviar sugestão.");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        error_log("Erro ao enviar sugestão: " . $e->getMessage());
        $error = "Erro interno do servidor.";
    }
}

// Carregar minhas emendas para sugestão
$stmt = $pdo->prepare("SELECT e.* FROM usuario_emendas ue JOIN emendas e ON ue.emenda_id = e.id WHERE ue.usuario_id = ? ORDER BY e.objeto_intervencao");
$stmt->execute([$usuario_id]);
$minhas_emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Carregar minhas sugestões
$stmt = $pdo->prepare("SELECT s.*, e.objeto_intervencao FROM sugestoes_emendas s JOIN emendas e ON s.emenda_id = e.id WHERE s.usuario_id = ? ORDER BY s.criado_em DESC");
$stmt->execute([$usuario_id]);
$minhas_sugestoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$campos_editaveis = [
    'objeto_intervencao' => 'Objeto de Intervenção',
    'valor' => 'Valor',
    'eixo_tematico' => 'Eixo Temático',
    'orgao' => 'Unidade Responsável',
    'ods' => 'ODS',
    'justificativa' => 'Justificativa'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sugestões - SICEF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root { --primary-color: #00796B; --secondary-color: #009688; }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, var(--primary-color), var(--secondary-color)); color: white; z-index: 1000; overflow-y: auto; }
        .main-content { margin-left: 280px; min-height: 100vh; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card-header { background: var(--primary-color); color: white; border-radius: 10px 10px 0 0 !important; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="padding: 1.5rem;">
            <h4>SICEF</h4>
            <p><?= htmlspecialchars($_SESSION['user']['nome']) ?></p>
        </div>
        <nav style="padding: 1rem 0;">
            <a href="user_dashboard.php" style="display: flex; align-items: center; padding: 0.75rem 1.5rem; color: white; text-decoration: none;">
                <span class="material-icons" style="margin-right: 0.75rem;">dashboard</span>Dashboard
            </a>
            <a href="minhas_emendas.php" style="display: flex; align-items: center; padding: 0.75rem 1.5rem; color: white; text-decoration: none;">
                <span class="material-icons" style="margin-right: 0.75rem;">bookmark</span>Minhas Emendas
            </a>
            <a href="sugestoes_emenda.php" style="display: flex; align-items: center; padding: 0.75rem 1.5rem; color: white; text-decoration: none; background-color: rgba(255,255,255,0.1);">
                <span class="material-icons" style="margin-right: 0.75rem;">lightbulb</span>Sugestões
            </a>
            <a href="../logout.php" style="display: flex; align-items: center; padding: 0.75rem 1.5rem; color: white; text-decoration: none;">
                <span class="material-icons" style="margin-right: 0.75rem;">logout</span>Sair
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div style="background: white; padding: 1rem 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2>Sugestões de Emendas</h2>
        </div>

        <div style="padding: 2rem;">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">Nova Sugestão</div>
                <div class="card-body">
                    <?php if (empty($minhas_emendas)): ?>
                        <p>Você precisa selecionar emendas primeiro para poder enviar sugestões.</p>
                        <a href="selecionar_emendas.php" class="btn btn-primary">Selecionar Emendas</a>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Emenda</label>
                                <select name="emenda_id" class="form-control" required>
                                    <option value="">Selecione uma emenda...</option>
                                    <?php foreach ($minhas_emendas as $emenda): ?>
                                        <option value="<?= $emenda['id'] ?>">
                                            <?= htmlspecialchars(substr($emenda['objeto_intervencao'], 0, 100)) ?>...
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Campo a Sugerir Alteração</label>
                                <select name="campo_sugerido" class="form-control" required>
                                    <option value="">Selecione um campo...</option>
                                    <?php foreach ($campos_editaveis as $campo => $nome): ?>
                                        <option value="<?= $campo ?>"><?= $nome ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Valor Sugerido</label>
                                <textarea name="valor_sugerido" class="form-control" rows="3" required placeholder="Digite sua sugestão..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Enviar Sugestão</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Minhas Sugestões</div>
                <div class="card-body">
                    <?php if (empty($minhas_sugestoes)): ?>
                        <p>Você ainda não enviou nenhuma sugestão.</p>
                    <?php else: ?>
                        <?php foreach ($minhas_sugestoes as $sugestao): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?= htmlspecialchars(substr($sugestao['objeto_intervencao'], 0, 100)) ?>...</strong>
                                            <br><small>Campo: <?= htmlspecialchars($campos_editaveis[$sugestao['campo_sugerido']] ?? $sugestao['campo_sugerido']) ?></small>
                                        </div>
                                        <span class="badge bg-<?= $sugestao['status'] === 'pendente' ? 'warning' : ($sugestao['status'] === 'aprovado' ? 'success' : 'danger') ?>">
                                            <?= ucfirst($sugestao['status']) ?>
                                        </span>
                                    </div>
                                    <p class="mt-2"><?= htmlspecialchars($sugestao['valor_sugerido']) ?></p>
                                    <?php if ($sugestao['resposta']): ?>
                                        <div class="alert alert-info mt-2">
                                            <strong>Resposta:</strong> <?= htmlspecialchars($sugestao['resposta']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <small class="text-muted">Enviado em <?= date('d/m/Y H:i', strtotime($sugestao['criado_em'])) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
