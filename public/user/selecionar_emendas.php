<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    // Obter todas as emendas disponíveis
    $emendas = $pdo->query("SELECT * FROM emendas ORDER BY criado_em DESC")->fetchAll(PDO::FETCH_ASSOC);

    // Obter emendas já selecionadas pelo usuário
    $usuario_id = $_SESSION['user']['id'];
    $minhas_emendas = $pdo->prepare("SELECT emenda_id FROM usuario_emendas WHERE usuario_id = ?");
    $minhas_emendas->execute([$usuario_id]);
    $minhas_emendas_ids = $minhas_emendas->fetchAll(PDO::FETCH_COLUMN);

    // Processar seleção
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emendas_selecionadas'])) {
        $pdo->beginTransaction();
        
        // Primeiro remove todas as seleções existentes
        $pdo->prepare("DELETE FROM usuario_emendas WHERE usuario_id = ?")->execute([$usuario_id]);
        
        // Adiciona as novas seleções
        $stmt = $pdo->prepare("INSERT INTO usuario_emendas (usuario_id, emenda_id) VALUES (?, ?)");
        foreach ($_POST['emendas_selecionadas'] as $emenda_id) {
            $stmt->execute([$usuario_id, $emenda_id]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Seleções atualizadas com sucesso!";
        header('Location: user_dashboard.php');
        exit;
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "Erro: " . $e->getMessage();
    header('Location: user_dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <!-- Cabeçalho similar ao user_dashboard.php -->
</head>
<body>
    <!-- Sidebar similar -->
    
    <div class="main-content">
        <!-- Header similar -->
        
        <div class="content">
            <section class="welcome-section">
                <h2>Selecionar Emendas</h2>
                <p>Marque as emendas que deseja acompanhar</p>
            </section>

            <form method="POST">
                <table class="emendas-table">
                    <thead>
                        <tr>
                            <th width="5%">Selecionar</th>
                            <th>Tipo</th>
                            <th>Eixo Temático</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emendas as $emenda): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="emendas_selecionadas[]" 
                                       value="<?= $emenda['id'] ?>"
                                       <?= in_array($emenda['id'], $minhas_emendas_ids) ? 'checked' : '' ?>>
                            </td>
                            <td><?= htmlspecialchars($emenda['tipo_emenda']) ?></td>
                            <td><?= htmlspecialchars($emenda['eixo_tematico']) ?></td>
                            <td><?= htmlspecialchars(substr($emenda['objeto_intervencao'], 0, 100)) ?>...</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-icons">save</span>
                        Salvar Seleções
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>