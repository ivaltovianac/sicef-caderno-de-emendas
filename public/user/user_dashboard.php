<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

// Conexão com o banco de dados
require_once __DIR__ . '/../../config/db.php';

// Processar filtros (usuário comum tem menos opções)
$filters = [];
$where = [];
$params = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['tipo_emenda'])) {
        $where[] = "tipo_emenda = ?";
        $params[] = $_GET['tipo_emenda'];
    }
    if (!empty($_GET['eixo_tematico'])) {
        $where[] = "eixo_tematico = ?";
        $params[] = $_GET['eixo_tematico'];
    }
}

$sql = "SELECT * FROM emendas";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY criado_em DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);   

// Obter valores distintos para filtros
$tipos_emenda = $pdo->query("SELECT DISTINCT tipo_emenda FROM emendas ORDER BY tipo_emenda")->fetchAll(PDO::FETCH_COLUMN);
$eixos_tematicos = $pdo->query("SELECT DISTINCT eixo_tematico FROM emendas ORDER BY eixo_tematico")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Minhas Emendas</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; }
        .header { background: #4db6ac; color: white; padding: 1rem; display: flex; justify-content: space-between; }
        .content { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .filters { background: #e9ecef; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .filter-group { margin-bottom: 1rem; }
        .filter-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background-color: #4db6ac; color: white; }
        tr:hover { background-color: #f8f9fa; }
        .btn { padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; }
        .btn-primary { background-color: #4db6ac; color: white; border: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Minhas Emendas</h1>
        <a href="../logout.php" style="color: white;">Sair</a>
    </div>
    
    <div class="content">
        <h2>Olá, <?= htmlspecialchars($_SESSION['user']['nome']) ?>!</h2>
        
        <div class="filters">
            <h3>Filtrar Emendas</h3>
            <form method="GET">
                <div class="filter-group">
                    <label for="tipo_emenda">Tipo de Emenda</label>
                    <select name="tipo_emenda" id="tipo_emenda">
                        <option value="">Todos</option>
                        <?php foreach ($tipos_emenda as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo) ?>" <?= isset($_GET['tipo_emenda']) && $_GET['tipo_emenda'] === $tipo ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tipo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="eixo_tematico">Eixo Temático</label>
                    <select name="eixo_tematico" id="eixo_tematico">
                        <option value="">Todos</option>
                        <?php foreach ($eixos_tematicos as $eixo): ?>
                            <option value="<?= htmlspecialchars($eixo) ?>" <?= isset($_GET['eixo_tematico']) && $_GET['eixo_tematico'] === $eixo ? 'selected' : '' ?>>
                                <?= htmlspecialchars($eixo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                <a href="user_dashboard.php" class="btn">Limpar Filtros</a>
            </form>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Eixo Temático</th>
                    <th>Órgão</th>
                    <th>Valor</th>
                    <th>Data</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emendas as $emenda): ?>
                <tr>
                    <td><?= htmlspecialchars($emenda['tipo_emenda']) ?></td>
                    <td><?= htmlspecialchars($emenda['eixo_tematico']) ?></td>
                    <td><?= htmlspecialchars($emenda['orgao']) ?></td>
                    <td>R$ <?= number_format($emenda['valor_pretendido'], 2, ',', '.') ?></td>
                    <td><?= date('d/m/Y', strtotime($emenda['criado_em'])) ?></td>
                    <td>
                        <a href="visualizar_emenda.php?id=<?= $emenda['id'] ?>" class="btn btn-primary">Visualizar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>