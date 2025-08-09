<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Função melhorada para verificar tabelas
function verificarTabela($pdo, $tabela) {
    try {
        $stmt = $pdo->query("SELECT 1 FROM $tabela LIMIT 1");
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao verificar tabela $tabela: " . $e->getMessage());
        return false;
    }
}

// Lista de tabelas necessárias
$tabelasNecessarias = ['usuarios', 'emendas', 'usuario_emendas'];
$tabelasFaltantes = [];

foreach ($tabelasNecessarias as $tabela) {
    if (!verificarTabela($pdo, $tabela)) {
        $tabelasFaltantes[] = $tabela;
    }
}

if (!empty($tabelasFaltantes)) {
    $tabelasList = implode(', ', $tabelasFaltantes);
    die("<div style='padding: 20px; background: #f8d7da; color: #721c24; border-radius: 5px;'>
        <h3>Erro no Banco de Dados</h3>
        <p>As seguintes tabelas não foram encontradas ou estão inacessíveis: <strong>$tabelasList</strong></p>
        <p>Por favor, verifique:</p>
        <ol>
            <li>Se as tabelas existem no banco de dados</li>
            <li>Se as permissões estão corretas</li>
            <li>Se os nomes das tabelas estão exatamente como: $tabelasList</li>
        </ol>
    </div>");
}

try {
    $usuario_id = $_SESSION['user']['id'];
    
    // Consulta principal
    $sql = "SELECT e.* FROM emendas e
            JOIN usuario_emendas ue ON e.id = ue.emenda_id
            WHERE ue.usuario_id = ?
            ORDER BY e.criado_em DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $emendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<div style='padding: 20px; background: #f8d7da; color: #721c24; border-radius: 5px;'>
        <h3>Erro na Consulta</h3>
        <p>Ocorreu um erro ao acessar os dados.</p>
        <p>Detalhes técnicos: " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Consulta executada: <code>" . htmlspecialchars(str_replace('?', "'{$usuario_id}'", $sql)) . "</code></p>
    </div>");
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Emendas - CICEF</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin_styles.css">
    <style>
        /* Estilos específicos para o user dashboard */
        .user-content {
            margin-left: 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }
        
        .stat-card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-description {
            color: #666;
            font-size: 0.9rem;
        }
        
        .tab-container {
            margin-bottom: 2rem;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-color);
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Sidebar simplificada para usuários comuns -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>
                <span class="material-icons">account_circle</span>
                Meu Painel
            </h2>
        </div>
        <div class="sidebar-menu">
            <a href="user_dashboard.php" class="menu-item active">
                <span class="material-icons">list_alt</span>
                Minhas Emendas
            </a>
            <a href="selecionar_emendas.php" class="menu-item">
                <span class="material-icons">add_circle</span>
                Selecionar Emendas
            </a>
            <a href="meu_perfil.php" class="menu-item">
                <span class="material-icons">person</span>
                Meu Perfil
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content user-content">
        <!-- Header -->
        <div class="header">
            <button class="menu-toggle" id="menuToggle">
                <span class="material-icons">menu</span>
            </button>
            <h1>Minhas Emendas</h1>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['user']['nome'], 0, 1)) ?>
                    </div>
                    <span><?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
                </div>
                <a href="../logout.php">
                    <span class="material-icons">logout</span>
                    Sair
                </a>
            </div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <section class="welcome-section">
                <h2>Olá, <?= htmlspecialchars($_SESSION['user']['nome']) ?>!</h2>
                <p>Aqui você pode visualizar e gerenciar suas emendas selecionadas</p>
            </section>
            
            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><span class="material-icons">list_alt</span> Total de Emendas</h3>
                    <div class="stat-value"><?= count($minhas_emendas) ?></div>
                    <div class="stat-description">Emendas selecionadas</div>
                </div>
                
                <div class="stat-card">
                    <h3><span class="material-icons">category</span> Tipos Diferentes</h3>
                    <div class="stat-value"><?= count($tipos_emenda) ?></div>
                    <div class="stat-description">Tipos de emendas</div>
                </div>
                
                <div class="stat-card">
                    <h3><span class="material-icons">assessment</span> Eixos Diferentes</h3>
                    <div class="stat-value"><?= count($eixos_tematicos) ?></div>
                    <div class="stat-description">Eixos temáticos</div>
                </div>
            </div>
            
            <!-- Abas para navegação -->
            <div class="tab-container">
                <div class="tabs">
                    <div class="tab active" data-tab="todas">Todas as Emendas</div>
                    <div class="tab" data-tab="filtros">Filtrar Emendas</div>
                </div>
                
                <!-- Conteúdo da aba Todas as Emendas -->
                <div class="tab-content active" id="todas">
                    <?php if (count($minhas_emendas) > 0): ?>
                    <div class="export-section">
                        <div class="export-title">
                            <span class="material-icons">description</span>
                            Exportar Minhas Emendas
                        </div>
                        <div class="export-actions">
                            <a href="?export=excel" class="export-btn export-excel">
                                <span class="material-icons">description</span>
                                Exportar para Excel
                            </a>
                            <a href="?export=pdf" class="export-btn export-pdf">
                                <span class="material-icons">picture_as_pdf</span>
                                Exportar para PDF
                            </a>
                        </div>
                    </div>
                    
                    <table class="emendas-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Eixo Temático</th>
                                <th>Unidade</th>
                                <th>Objeto</th>
                                <th>ODS</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($minhas_emendas as $emenda): ?>
                            <tr>
                                <td><?= htmlspecialchars($emenda['tipo_emenda']) ?></td>
                                <td><?= htmlspecialchars($emenda['eixo_tematico']) ?></td>
                                <td><?= htmlspecialchars($emenda['orgao']) ?></td>
                                <td><?= htmlspecialchars(substr($emenda['objeto_intervencao'], 0, 50)) ?><?= strlen($emenda['objeto_intervencao']) > 50 ? '...' : '' ?></td>
                                <td><?= htmlspecialchars($emenda['ods'] ?? '-') ?></td>
                                <td><?= date('d/m/Y', strtotime($emenda['criado_em'])) ?></td>
                                <td class="actions-cell">
                                    <a href="visualizar_emenda.php?id=<?= $emenda['id'] ?>" class="action-link">
                                        <span class="material-icons" style="font-size: 1.1rem;">visibility</span>
                                        Visualizar
                                    </a>
                                    <form method="POST" action="remover_emenda.php" style="display: inline;">
                                        <input type="hidden" name="emenda_id" value="<?= $emenda['id'] ?>">
                                        <button type="submit" class="action-link danger" onclick="return confirm('Tem certeza que deseja remover esta emenda da sua lista?')">
                                            <span class="material-icons" style="font-size: 1.1rem;">delete</span>
                                            Remover
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <h3>Nenhuma emenda selecionada</h3>
                        <p>Você ainda não selecionou nenhuma emenda. <a href="selecionar_emendas.php">Clique aqui</a> para começar.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Conteúdo da aba Filtrar Emendas -->
                <div class="tab-content" id="filtros">
                    <div class="filters-card">
                        <form method="GET" action="user_dashboard.php">
                            <div class="filter-section">
                                <h3><span class="material-icons">filter_alt</span> Tipo de Caderno</h3>
                                <div class="radio-group">
                                    <?php foreach ($tipos_emenda as $tipo): ?>
                                    <label class="radio-option">
                                        <input type="radio" name="tipo_caderno" value="<?= htmlspecialchars($tipo) ?>" 
                                               <?= (isset($_GET['tipo_caderno']) && $_GET['tipo_caderno'] === $tipo) ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($tipo) ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="filter-section">
                                <h3><span class="material-icons">tune</span> Filtros Avançados</h3>
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label for="eixo_tematico">Eixo Temático</label>
                                        <select id="eixo_tematico" name="eixo_tematico" class="filter-control">
                                            <option value="Selecione">Selecione</option>
                                            <?php foreach ($eixos_tematicos as $eixo): ?>
                                                <option value="<?= htmlspecialchars($eixo) ?>" <?= isset($_GET['eixo_tematico']) && $_GET['eixo_tematico'] === $eixo ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($eixo) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="ods">ODS - Objetivo de Desenv. Sustentável</label>
                                        <select id="ods" name="ods" class="filter-control">
                                            <option value="Selecione">Selecione</option>
                                            <?php foreach ($ods_values as $ods): ?>
                                                <option value="<?= htmlspecialchars($ods) ?>" <?= isset($_GET['ods']) && $_GET['ods'] === $ods ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($ods) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="unidade_responsavel">Unidade Responsável</label>
                                        <select id="unidade_responsavel" name="unidade_responsavel" class="filter-control">
                                            <option value="Selecione">Selecione</option>
                                            <?php foreach ($unidades as $unidade): ?>
                                                <option value="<?= htmlspecialchars($unidade) ?>" <?= isset($_GET['unidade_responsavel']) && $_GET['unidade_responsavel'] === $unidade ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($unidade) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    <span class="material-icons">filter_alt</span>
                                    Aplicar Filtros
                                </button>
                                <a href="user_dashboard.php" class="btn btn-secondary">
                                    <span class="material-icons">clear</span>
                                    Limpar Filtros
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <?php if (count($emendas) > 0): ?>
                    <table class="emendas-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Eixo Temático</th>
                                <th>Unidade</th>
                                <th>Objeto</th>
                                <th>ODS</th>
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
                                <td><?= htmlspecialchars(substr($emenda['objeto_intervencao'], 0, 50)) ?><?= strlen($emenda['objeto_intervencao']) > 50 ? '...' : '' ?></td>
                                <td><?= htmlspecialchars($emenda['ods'] ?? '-') ?></td>
                                <td><?= date('d/m/Y', strtotime($emenda['criado_em'])) ?></td>
                                <td class="actions-cell">
                                    <a href="visualizar_emenda.php?id=<?= $emenda['id'] ?>" class="action-link">
                                        <span class="material-icons" style="font-size: 1.1rem;">visibility</span>
                                        Visualizar
                                    </a>
                                    <form method="POST" action="remover_emenda.php" style="display: inline;">
                                        <input type="hidden" name="emenda_id" value="<?= $emenda['id'] ?>">
                                        <button type="submit" class="action-link danger" onclick="return confirm('Tem certeza que deseja remover esta emenda da sua lista?')">
                                            <span class="material-icons" style="font-size: 1.1rem;">delete</span>
                                            Remover
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <h3>Nenhuma emenda encontrada</h3>
                        <p>Não há resultados com os filtros selecionados.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Menu Toggle para mobile
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
        
        // Fechar menu ao clicar fora (para mobile)
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && !sidebar.contains(e.target) && e.target !== menuToggle) {
                sidebar.classList.remove('active');
            }
        });
        
        // Controle das abas
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remover classe active de todas as abas e conteúdos
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Adicionar classe active à aba clicada e ao conteúdo correspondente
                tab.classList.add('active');
                const tabId = tab.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
    </script>
</body>
</html>