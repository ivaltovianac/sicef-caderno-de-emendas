
<?php
// SICEF-caderno-de-emendas/public/user/visualizar_emenda.php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION["user"]["is_admin"]) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";

$emenda_id = (int)($_GET['id'] ?? 0);
if (empty($emenda_id)) {
    header("Location: minhas_emendas.php");
    exit;
}

// Carregar dados da emenda
try {
    $stmt = $pdo->prepare("SELECT * FROM emendas WHERE id = ?");
    $stmt->execute([$emenda_id]);
    $emenda = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emenda) {
        $_SESSION['error'] = "Emenda não encontrada.";
        header("Location: minhas_emendas.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Erro ao carregar emenda: " . $e->getMessage());
    $_SESSION['error'] = "Erro interno do servidor.";
    header("Location: minhas_emendas.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Emenda - SICEF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root { --primary-color: #00796B; --secondary-color: #009688; }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, var(--primary-color), var(--secondary-color)); color: white; z-index: 1000; overflow-y: auto; }
        .main-content { margin-left: 280px; min-height: 100vh; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card-header { background: var(--primary-color); color: white; border-radius: 10px 10px 0 0 !important; }
        .info-row { margin-bottom: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; }
        .info-label { font-weight: 600; color: var(--primary-color); }
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
            <a href="minhas_emendas.php" style="display: flex; align-items: center; padding: 0.75rem 1.5rem; color: white; text-decoration: none; background-color: rgba(255,255,255,0.1);">
                <span class="material-icons" style="margin-right: 0.75rem;">bookmark</span>Minhas Emendas
            </a>
            <a href="../logout.php" style="display: flex; align-items: center; padding: 0.75rem 1.5rem; color: white; text-decoration: none;">
                <span class="material-icons" style="margin-right: 0.75rem;">logout</span>Sair
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div style="background: white; padding: 1rem 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2>Visualizar Emenda</h2>
        </div>

        <div style="padding: 2rem;">
            <div class="card">
                <div class="card-header">Detalhes da Emenda #<?= $emenda['id'] ?></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-row">
                                <div class="info-label">Tipo de Emenda</div>
                                <div><?= htmlspecialchars($emenda['tipo_emenda'] ?? 'Não informado') ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-row">
                                <div class="info-label">Eixo Temático</div>
                                <div><?= htmlspecialchars($emenda['eixo_tematico'] ?? 'Não informado') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Órgão</div>
                        <div><?= htmlspecialchars($emenda['orgao'] ?? 'Não informado') ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Objeto de Intervenção</div>
                        <div><?= htmlspecialchars($emenda['objeto_intervencao'] ?? 'Não informado') ?></div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-row">
                                <div class="info-label">ODS</div>
                                <div><?= htmlspecialchars($emenda['ods'] ?? 'Não informado') ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-row">
                                <div class="info-label">Valor</div>
                                <div>R$ <?= number_format($emenda['valor'] ?? 0, 2, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-row">
                                <div class="info-label">Regionalização</div>
                                <div><?= htmlspecialchars($emenda['regionalizacao'] ?? 'Não informado') ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($emenda['justificativa'])): ?>
                        <div class="info-row">
                            <div class="info-label">Justificativa</div>
                            <div><?= htmlspecialchars($emenda['justificativa']) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <a href="minhas_emendas.php" class="btn btn-secondary">
                            <span class="material-icons me-1">arrow_back</span>Voltar
                        </a>
                        <a href="edit_emenda.php?id=<?= $emenda['id'] ?>" class="btn btn-primary">
                            <span class="material-icons me-1">edit</span>Editar
                        </a>
                        <a href="sugestoes_emenda.php?emenda_id=<?= $emenda['id'] ?>" class="btn btn-warning">
                            <span class="material-icons me-1">lightbulb</span>Sugerir Alteração
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
