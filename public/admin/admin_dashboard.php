<?php
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: ../login.php');
    exit;
}

// Conexão com o banco de dados
require_once __DIR__ . '/../../config/db.php';

// Processar filtros
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
    if (!empty($_GET['valor_min'])) {
        $where[] = "valor_pretendido >= ?";
        $params[] = $_GET['valor_min'];
    }
    if (!empty($_GET['valor_max'])) {
        $where[] = "valor_pretendido <= ?";
        $params[] = $_GET['valor_max'];
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
    <title>Painel Administrativo - Emendas</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; }
        .header { background: #007b5e; color: white; padding: 1rem; display: flex; justify-content: space-between; }
        .sidebar { background: #f8f9fa; width: 250px; height: 100vh; position: fixed; padding: 1rem; }
        .content { margin-left: 250px; padding: 2rem; }
        .menu-item { padding: 0.5rem; margin: 0.5rem 0; border-radius: 4px; }
        .menu-item:hover { background: #e9ecef; }
        .filters { background: #e9ecef; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
        .filter-group { margin-bottom: 1rem; }
        .filter-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background-color: #007b5e; color: white; }
        tr:hover { background-color: #f8f9fa; }
        .btn { padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; }
        .btn-primary { background-color: #007bff; color: white; border: none; }
        .btn-danger { background-color: #dc3545; color: white; border: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Painel Administrativo - Emendas</h1>
        <a href="../logout.php" style="color: white;">Sair</a>
    </div>
    
    <div class="sidebar">
        <h3>Menu</h3>
        <div class="menu-item"><a href="admin_dashboard.php">Emendas</a></div>
        <div class="menu-item"><a href="gerenciar_usuarios.php">Gerenciar Usuários</a></div>
        <div class="menu-item"><a href="relatorios.php">Relatórios</a></div>
    </div>
    
    <div class="content">
        <h2>Bem-vindo, <?= htmlspecialchars($_SESSION['user']['nome']) ?>!</h2>
        
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
                
                <div class="filter-group">
                    <label for="valor_min">Valor Mínimo</label>
                    <input type="number" name="valor_min" id="valor_min" step="0.01" value="<?= htmlspecialchars($_GET['valor_min'] ?? '') ?>">
                </div>
                
                <div class="filter-group">
                    <label for="valor_max">Valor Máximo</label>
                    <input type="number" name="valor_max" id="valor_max" step="0.01" value="<?= htmlspecialchars($_GET['valor_max'] ?? '') ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                <a href="admin_dashboard.php" class="btn">Limpar Filtros</a>
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
                        <a href="editar_emenda.php?id=<?= $emenda['id'] ?>" class="btn btn-primary">Editar</a>
                        <a href="excluir_emenda.php?id=<?= $emenda['id'] ?>" class="btn btn-danger" onclick="return confirm('Tem certeza?')">Excluir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>