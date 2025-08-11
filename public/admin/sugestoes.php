<?php
session_start();

// só admins podem acessar sugestões
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // caso precise de libs

// Funções relacionadas a sugestões

/**
 * Retorna sugestões pendentes com dados do usuário e da emenda.
 * @param PDO $pdo
 * @return array
 */
function get_pending_suggestions(PDO $pdo): array
{
    $sql = "
        SELECT se.*, u.nome as usuario_nome, e.objeto_intervencao
        FROM sugestoes_emendas se
        JOIN usuarios u ON se.usuario_id = u.id
        JOIN emendas e ON se.emenda_id = e.id
        WHERE se.status = 'pendente'
        ORDER BY se.criado_em DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Responde uma sugestão (faz update + cria notificação).
 * @param PDO $pdo
 * @param int $sugestao_id
 * @param string $resposta
 * @param string $status (ex: 'aprovado'|'rejeitado')
 * @param int $respondido_por (id do admin)
 * @return array ['success' => bool, 'message' => string]
 */
function respond_to_suggestion(PDO $pdo, int $sugestao_id, string $resposta, string $status, int $respondido_por): array
{
    try {
        $pdo->beginTransaction();

        // Atualiza sugestão
        $stmt = $pdo->prepare("
            UPDATE sugestoes_emendas
            SET status = ?, resposta = ?, respondido_em = NOW(), respondido_por = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $resposta, $respondido_por, $sugestao_id]);

        // Inserir notificação (pegando usuario_id da própria sugestão)
        $stmt_notif = $pdo->prepare("
            INSERT INTO notificacoes (usuario_id, tipo, mensagem, referencia_id, criado_em)
            SELECT usuario_id, 'resposta_sugestao', ?, id, NOW()
            FROM sugestoes_emendas
            WHERE id = ?
        ");
        $mensagem = "Sua sugestão #{$sugestao_id} foi {$status}.";
        $stmt_notif->execute([$mensagem, $sugestao_id]);

        $pdo->commit();
        return ['success' => true, 'message' => 'Resposta enviada com sucesso.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'Erro ao responder sugestão: ' . $e->getMessage()];
    }
}

// Processar resposta enviada pelo admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'responder_sugestao') {
    $sugestao_id = isset($_POST['sugestao_id']) ? (int)$_POST['sugestao_id'] : 0;
    $resposta = trim($_POST['resposta'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if ($sugestao_id <= 0 || $resposta === '' || ($status !== 'aprovado' && $status !== 'rejeitado')) {
        $_SESSION['message'] = 'Dados inválidos para responder a sugestão.';
    } else {
        $res = respond_to_suggestion($pdo, $sugestao_id, $resposta, $status, (int)$_SESSION['user']['id']);
        $_SESSION['message'] = $res['message'];
    }

    header('Location: sugestoes.php');
    exit;
}

// Obter sugestões pendentes e contagem
$sugestoes_pendentes = get_pending_suggestions($pdo);
$pendentes_count = count($sugestoes_pendentes);

// Para a barra lateral / cabeçalho: contar sugestões pendentes (consulta direta alternativa)
// $stmt_count = $pdo->query("SELECT COUNT(*) FROM sugestoes_emendas WHERE status = 'pendente'");
// $pendentes_count = (int)$stmt_count->fetchColumn();


// Cores para tipos de usuários
$user_colors = [
    'primary' => '#6f42c1',
    'secondary' => '#e83e8c',
    'accent' => '#fd7e14'
];
if (isset($_SESSION["user"]["tipo"])) {
    switch ($_SESSION["user"]["tipo"]) {
        case 'Deputado':
            $user_colors = ['primary' => '#018bd2', 'secondary' => '#51ae32', 'accent' => '#fdfefe'];
            break;
        case 'Senador':
            $user_colors = ['primary' => '#51b949', 'secondary' => '#0094db', 'accent' => '#fefefe'];
            break;
        case 'Administrador':
            $user_colors = ['primary' => '#6f42c1', 'secondary' => '#e83e8c', 'accent' => '#fd7e14'];
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sugestões - Painel Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary: <?= $user_colors['primary'] ?>;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --border: #e0e0e0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Roboto, Arial, Helvetica, sans-serif
        }

        .admin-container {
            display: flex;
            min-height: 100vh
        }

        .admin-sidebar {
            width: 250px;
            background: var(--dark);
            color: #fff;
            padding: 1.5rem 0;
            position: fixed;
            height: 100vh;
            overflow: auto
        }

        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08)
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: .75rem
        }

        .sidebar-menu {
            padding: 1rem 0
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: .75rem 1.5rem;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            gap: .75rem
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.06)
        }

        .badge {
            background: red;
            color: white;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 0.8rem;
            margin-left: 8px
        }

        /* content */
        .admin-content {
            flex: 1;
            margin-left: 250px;
            width: calc(100% - 250px)
        }

        .admin-header {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 90
        }

        .user-area {
            display: flex;
            align-items: center;
            gap: 1rem
        }

        .user-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .content-area {
            padding: 2rem
        }

        /* sugestoes */
        .sugestao-item {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #fff
        }

        .sugestao-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: .5rem
        }

        .sugestao-campo {
            font-weight: 600;
            color: var(--primary)
        }

        .sugestao-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border)
        }

        .sugestao-form textarea {
            width: 100%;
            min-height: 100px;
            padding: .5rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-bottom: .75rem
        }

        .sugestao-form select {
            padding: .5rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-right: .5rem
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: .6rem .9rem;
            border-radius: 6px;
            border: none;
            cursor: pointer
        }

        .btn-primary {
            background: var(--primary);
            color: #fff
        }

        .btn-secondary {
            background: #6c757d;
            color: #fff
        }

        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem
        }

        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb
        }

        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb
        }

        /* responsive */
        @media (max-width:768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                position: fixed
            }

            .admin-sidebar.active {
                transform: translateX(0)
            }

            .admin-content {
                margin-left: 0;
                width: 100%
            }
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <nav class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="material-icons">admin_panel_settings</i> Painel Admin</h2>
            </div>
            <div class="sidebar-menu">
                <a href="admin_dashboard.php"><i class="material-icons">dashboard</i> Dashboard</a>
                <a href="gerenciar_usuarios.php"><i class="material-icons">people</i> Usuários</a>
                <a href="relatorios.php"><i class="material-icons">assessment</i> Relatórios</a>
                <a href="configuracoes.php"><i class="material-icons">settings</i> Configurações</a>
                <a href="sugestoes.php" class="active"><i class="material-icons">lightbulb</i> Sugestões
                    <?php if ($pendentes_count > 0): ?>
                        <span class="badge"><?= $pendentes_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </nav>

        <main class="admin-content">
            <header class="admin-header">
                <div style="display:flex;align-items:center;gap:1rem">
                    <h1>Sugestões</h1>
                </div>
                <div class="user-area">
                    <div class="user-icon"><?= strtoupper(substr($_SESSION['user']['nome'], 0, 1)) ?></div>
                    <div><?= htmlspecialchars($_SESSION['user']['nome']) ?></div>
                    <a href="../logout.php" style="text-decoration:none;color:#333;margin-left:8px">Sair</a>
                </div>
            </header>

            <div class="content-area">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="message <?= strpos($_SESSION['message'], 'Erro') !== false ? 'message-error' : 'message-success' ?>">
                        <?= htmlspecialchars($_SESSION['message']) ?>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <p>Lista de sugestões pendentes (<?= $pendentes_count ?>)</p>

                <?php if (empty($sugestoes_pendentes)): ?>
                    <div style="padding:2rem;border:1px dashed var(--border);border-radius:8px;background:#fff">
                        <p style="color:#666">Nenhuma sugestão pendente no momento.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sugestoes_pendentes as $s): ?>
                        <div class="sugestao-item">
                            <div class="sugestao-header">
                                <div>
                                    <span class="sugestao-campo"><?= htmlspecialchars($s['campo_sugerido']) ?></span>
                                    <div style="font-size:.9rem;color:#666">Por: <?= htmlspecialchars($s['usuario_nome']) ?> • <?= date('d/m/Y H:i', strtotime($s['criado_em'])) ?></div>
                                </div>
                                <div style="font-size:.9rem;color:#333"><?= htmlspecialchars($s['emenda_id']) ? 'Emenda #' . (int)$s['emenda_id'] : '' ?></div>
                            </div>

                            <p><strong>Emenda (trecho):</strong> <?= htmlspecialchars(substr($s['objeto_intervencao'], 0, 200)) ?><?= strlen($s['objeto_intervencao']) > 200 ? '...' : '' ?></p>
                            <p><strong>Valor sugerido / alteração:</strong> <?= htmlspecialchars($s['valor_sugerido'] ?? '-') ?></p>

                            <form method="POST" class="sugestao-form" onsubmit="return confirm('Confirma a ação para esta sugestão?');">
                                <input type="hidden" name="action" value="responder_sugestao">
                                <input type="hidden" name="sugestao_id" value="<?= (int)$s['id'] ?>">
                                <textarea name="resposta" placeholder="Digite sua resposta..." required></textarea>
                                <div style="margin-top:.5rem;display:flex;gap:.5rem;align-items:center">
                                    <select name="status" required>
                                        <option value="">Selecione</option>
                                        <option value="aprovado">Aprovar</option>
                                        <option value="rejeitado">Rejeitar</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary">Responder</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </main>
    </div>
</body>

</html>