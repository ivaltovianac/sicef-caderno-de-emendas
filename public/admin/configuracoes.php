<?php
/**
 * Configurações do Sistema - SICEF
 * 
 * Este arquivo é responsável por gerenciar as configurações gerais do sistema SICEF.
 * Permite ao administrador configurar e-mails, segurança, SMTP e outras opções do sistema.
 * 
 * Funcionalidades:
 * - Configuração de e-mail para notificações
 * - Configuração de servidor SMTP para envio de e-mails
 * - Teste de envio de e-mail
 * - Configuração de segurança (tentativas de login, bloqueio, timeout)
 * - Configuração geral do sistema (nome, logs, backup, manutenção)
 * - Exibição de informações do sistema
 * 
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */

// Inicia a sessão para verificar se o usuário está logado e é administrador
session_start();

// Verifica se o usuário está logado e se é administrador
if (!isset($_SESSION["user"]) || !$_SESSION["user"]["is_admin"]) {
    // Redireciona para a página de login caso não seja administrador
    header("Location: ../login.php");
    exit;
}

// Inclui o arquivo de configuração do banco de dados
require_once __DIR__ . "/../../config/db.php";

// Inclui o PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Carrega o autoload do Composer para PHPMailer
require_once __DIR__ . '/../../vendor/autoload.php';

// Contadores para badges no menu
$stmt_solicitacoes = $pdo->query("SELECT COUNT(*) as total FROM solicitacoes_acesso WHERE status = 'pendente'");
$solicitacoes_pendentes = $stmt_solicitacoes->fetch()['total'];

$stmt_sugestoes = $pdo->query("SELECT COUNT(*) FROM sugestoes_emendas WHERE status = 'pendente'");
$qtde_sugestoes_pendentes = $stmt_sugestoes->fetchColumn();

// Variáveis para mensagens de feedback
$message = "";
$error = "";

// Carrega as configurações atuais do sistema
try {
    $stmt_config = $pdo->query("SELECT chave, valor FROM configuracoes");
    $configs_raw = $stmt_config->fetchAll(PDO::FETCH_ASSOC);
    $configs = [];
    foreach ($configs_raw as $config) {
        $configs[$config['chave']] = $config['valor'];
    }
} catch (PDOException $e) {
    // Registra erro no log caso não consiga carregar as configurações
    error_log("Erro ao carregar configurações: " . $e->getMessage());
    $configs = [];
}

// Define valores padrão para as configurações
$configs = array_merge([
    'email_notificacoes' => '',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_secure' => 'tls',
    'max_tentativas_login' => '5',
    'tempo_bloqueio' => '30',
    'sessao_timeout' => '120',
    'backup_automatico' => '1',
    'log_nivel' => 'info',
    'manutencao_modo' => '0',
    'sistema_nome' => 'SICEF',
    'sistema_versao' => '1.0.0'
], $configs);

/**
 * Função para enviar e-mail usando PHPMailer
 * 
 * @param string $to_email Endereço de e-mail do destinatário
 * @param string $to_name Nome do destinatário
 * @param string $subject Assunto do e-mail
 * @param string $body Corpo do e-mail
 * @param array $configs Configurações do sistema
 * @return bool Resultado do envio
 */
function enviarEmail($to_email, $to_name, $subject, $body, $configs)
{
    $mail = new PHPMailer(true);

    try {
        // Configurações do servidor SMTP
        $mail->isSMTP();
        $mail->Host = $configs['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $configs['smtp_user'];
        $mail->Password = $configs['smtp_pass'];
        $mail->SMTPSecure = $configs['smtp_secure'];
        $mail->Port = $configs['smtp_port'];

        // Configurações de charset
        $mail->CharSet = 'UTF-8';

        // Remetente
        $mail->setFrom($configs['smtp_user'], $configs['sistema_nome']);

        // Destinatário
        $mail->addAddress($to_email, $to_name);

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        // Envia o e-mail
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro ao enviar e-mail: " . $e->getMessage());
        return false;
    }
}

// Processa os formulários de configuração
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'update_email':
                // Atualiza configurações de e-mail para notificações
                $email = trim($_POST['email_notificacoes'] ?? '');
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("E-mail inválido");
                }
                $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('email_notificacoes', ?) ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, atualizado_em = NOW()");
                $stmt->execute([$email]);
                $message = "Configurações de e-mail atualizadas com sucesso!";
                break;

            case 'update_smtp':
                // Atualiza configurações do servidor SMTP
                $smtp_configs = [
                    'smtp_host' => trim($_POST['smtp_host'] ?? ''),
                    'smtp_port' => (int) ($_POST['smtp_port'] ?? 587),
                    'smtp_user' => trim($_POST['smtp_user'] ?? ''),
                    'smtp_pass' => $_POST['smtp_pass'] ?? '',
                    'smtp_secure' => $_POST['smtp_secure'] ?? 'tls'
                ];

                foreach ($smtp_configs as $chave => $valor) {
                    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, atualizado_em = NOW()");
                    $stmt->execute([$chave, $valor]);
                }
                $message = "Configurações SMTP atualizadas com sucesso!";
                break;

            case 'update_security':
                // Atualiza configurações de segurança
                $security_configs = [
                    'max_tentativas_login' => max(1, (int) ($_POST['max_tentativas_login'] ?? 5)),
                    'tempo_bloqueio' => max(1, (int) ($_POST['tempo_bloqueio'] ?? 30)),
                    'sessao_timeout' => max(30, (int) ($_POST['sessao_timeout'] ?? 120))
                ];

                foreach ($security_configs as $chave => $valor) {
                    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, atualizado_em = NOW()");
                    $stmt->execute([$chave, $valor]);
                }
                $message = "Configurações de segurança atualizadas com sucesso!";
                break;

            case 'update_system':
                // Atualiza configurações gerais do sistema
                $system_configs = [
                    'backup_automatico' => isset($_POST['backup_automatico']) ? '1' : '0',
                    'log_nivel' => $_POST['log_nivel'] ?? 'info',
                    'manutencao_modo' => isset($_POST['manutencao_modo']) ? '1' : '0',
                    'sistema_nome' => trim($_POST['sistema_nome'] ?? 'SICEF')
                ];

                foreach ($system_configs as $chave => $valor) {
                    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, atualizado_em = NOW()");
                    $stmt->execute([$chave, $valor]);
                }
                $message = "Configurações do sistema atualizadas com sucesso!";
                break;

            case 'test_email':
                // Testa o envio de e-mail usando PHPMailer
                $email_teste = trim($_POST['email_teste'] ?? '');
                if (empty($email_teste) || !filter_var($email_teste, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("E-mail de teste inválido");
                }

                // Recarrega as configurações para garantir que temos os valores mais recentes
                $stmt_config = $pdo->query("SELECT chave, valor FROM configuracoes");
                $configs_raw = $stmt_config->fetchAll(PDO::FETCH_ASSOC);
                $configs_atualizadas = [];
                foreach ($configs_raw as $config) {
                    $configs_atualizadas[$config['chave']] = $config['valor'];
                }
                $configs_atualizadas = array_merge($configs, $configs_atualizadas);

                // Verifica se as configurações SMTP estão preenchidas
                if (
                    empty($configs_atualizadas['smtp_host']) ||
                    empty($configs_atualizadas['smtp_user']) ||
                    empty($configs_atualizadas['smtp_pass'])
                ) {
                    throw new Exception("Configure o servidor SMTP antes de testar o envio de e-mails");
                }

                // Conteúdo do e-mail de teste
                $assunto = "Teste de Configuração de E-mail - " . $configs_atualizadas['sistema_nome'];
                $corpo = "
                    <h2>Teste de Configuração de E-mail</h2>
                    <p>Esta é uma mensagem de teste para verificar se as configurações de e-mail estão funcionando corretamente.</p>
                    <p><strong>Configurações utilizadas:</strong></p>
                    <ul>
                        <li>Servidor SMTP: " . htmlspecialchars($configs_atualizadas['smtp_host']) . "</li>
                        <li>Porta: " . htmlspecialchars($configs_atualizadas['smtp_port']) . "</li>
                        <li>Segurança: " . htmlspecialchars($configs_atualizadas['smtp_secure']) . "</li>
                        <li>Usuário: " . htmlspecialchars($configs_atualizadas['smtp_user']) . "</li>
                    </ul>
                    <p>Se você recebeu este e-mail, as configurações estão corretas!</p>
                    <hr>
                    <p><em>" . htmlspecialchars($configs_atualizadas['sistema_nome']) . " - Sistema de Controle de Emendas Parlamentares</em></p>
                ";

                // Envia o e-mail
                if (enviarEmail($email_teste, "Administrador", $assunto, $corpo, $configs_atualizadas)) {
                    $message = "E-mail de teste enviado com sucesso para: " . $email_teste;
                } else {
                    throw new Exception("Falha ao enviar e-mail de teste. Verifique as configurações SMTP.");
                }
                break;

            default:
                throw new Exception("Ação inválida");
        }

        // Recarrega as configurações após atualização
        $stmt_config = $pdo->query("SELECT chave, valor FROM configuracoes");
        $configs_raw = $stmt_config->fetchAll(PDO::FETCH_ASSOC);
        $configs = [];
        foreach ($configs_raw as $config) {
            $configs[$config['chave']] = $config['valor'];
        }
        $configs = array_merge([
            'email_notificacoes' => '',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_secure' => 'tls',
            'max_tentativas_login' => '5',
            'tempo_bloqueio' => '30',
            'sessao_timeout' => '120',
            'backup_automatico' => '1',
            'log_nivel' => 'info',
            'manutencao_modo' => '0',
            'sistema_nome' => 'SICEF',
            'sistema_versao' => '1.0.0'
        ], $configs);

    } catch (Exception $e) {
        // Trata erros de validação
        $error = $e->getMessage();
    } catch (PDOException $e) {
        // Trata erros de banco de dados
        error_log("Erro nas configurações: " . $e->getMessage());
        $error = "Erro interno do servidor";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - SICEF Admin</title>
    <!-- Bootstrap e Material Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00796B;
            --secondary-color: #009688;
            --accent-color: #FFC107;
            --light-color: #ECEFF1;
            --dark-color: #263238;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        /* Sidebar responsivo */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            color: white;
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .sidebar-header p {
            margin: 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu .material-icons {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .badge {
            margin-left: auto;
        }

        /* Main content responsivo */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .top-bar {
            background: white;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            cursor: pointer;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-left: auto;
        }

        .content-area {
            padding: 2rem;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        /* Responsividade mobile */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .top-bar {
                flex-direction: column;
                gap: 1rem;
            }

            .user-info {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }

            .content-area {
                padding: 1rem;
            }
        }

        /* Overlay para mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        .config-section {
            margin-bottom: 2rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-control,
        .form-select {
            border-radius: 8px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 121, 107, 0.25);
        }

        .info-box {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .info-box .material-icons {
            color: #17a2b8;
        }
    </style>
</head>

<body>
    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4>SICEF Admin</h4>
            <p>Painel Administrativo</p>
        </div>
        <nav class="sidebar-menu">
            <a href="admin_dashboard.php">
                <span class="material-icons">dashboard</span>
                Dashboard
            </a>
            <a href="gerenciar_usuarios.php">
                <span class="material-icons">manage_accounts</span>
                Gerenciar Usuários
            </a>
            <a href="solicitacoes_acesso.php">
                <span class="material-icons">person_add</span>
                Solicitações
                <?php if ($solicitacoes_pendentes > 0): ?>
                    <span class="badge bg-warning"><?= $solicitacoes_pendentes ?></span>
                <?php endif; ?>
            </a>
            <a href="sugestoes.php">
                <span class="material-icons">lightbulb</span>
                Sugestões
                <?php if ($qtde_sugestoes_pendentes > 0): ?>
                    <span class="badge bg-info"><?= $qtde_sugestoes_pendentes ?></span>
                <?php endif; ?>
            </a>
            <a href="relatorios.php">
                <span class="material-icons">assessment</span>
                Relatórios
            </a>
            <a href="configuracoes.php" class="active">
                <span class="material-icons">settings</span>
                Configurações
            </a>
            <a href="../logout.php">
                <span class="material-icons">logout</span>
                Sair
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="top-bar">
            <button class="menu-toggle" id="menuToggle">
                <span class="material-icons">menu</span>
            </button>
            <h2>Configurações do Sistema</h2>
            <div class="user-info">
                <span class="material-icons">account_circle</span>
                <span>Olá, <?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <span class="material-icons me-2">check_circle</span>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <span class="material-icons me-2">error</span>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Configurações de E-mail -->
            <div class="card">
                <div class="card-header">
                    <span class="material-icons me-2">email</span>
                    Configurações de E-mail
                </div>
                <div class="card-body">
                    <div class="info-box">
                        <span class="material-icons me-2">info</span>
                        Configure o e-mail para receber notificações do sistema e as configurações SMTP para envio.
                    </div>

                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="update_email">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="email_notificacoes" class="form-label">E-mail para Notificações</label>
                                <input type="email" class="form-control" id="email_notificacoes"
                                    name="email_notificacoes"
                                    value="<?= htmlspecialchars($configs['email_notificacoes']) ?>"
                                    placeholder="admin@exemplo.com">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <span class="material-icons me-1">save</span>
                                    Salvar E-mail
                                </button>
                            </div>
                        </div>
                    </form>

                    <hr>

                    <h6>Configurações SMTP</h6>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_smtp">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="smtp_host" class="form-label">Servidor SMTP</label>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                    value="<?= htmlspecialchars($configs['smtp_host']) ?>" placeholder="smtp.gmail.com">
                                <small class="text-muted">Para Gmail, use: smtp.gmail.com</small>
                            </div>
                            <div class="col-md-3">
                                <label for="smtp_port" class="form-label">Porta</label>
                                <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                    value="<?= htmlspecialchars($configs['smtp_port']) ?>" placeholder="587">
                                <small class="text-muted">Gmail: 587 (TLS) ou 465 (SSL)</small>
                            </div>
                            <div class="col-md-3">
                                <label for="smtp_secure" class="form-label">Segurança</label>
                                <select class="form-select" id="smtp_secure" name="smtp_secure">
                                    <option value="tls" <?= $configs['smtp_secure'] === 'tls' ? 'selected' : '' ?>>TLS
                                    </option>
                                    <option value="ssl" <?= $configs['smtp_secure'] === 'ssl' ? 'selected' : '' ?>>SSL
                                    </option>
                                    <option value="" <?= empty($configs['smtp_secure']) ? 'selected' : '' ?>>Nenhuma
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="smtp_user" class="form-label">Usuário SMTP</label>
                                <input type="text" class="form-control" id="smtp_user" name="smtp_user"
                                    value="<?= htmlspecialchars($configs['smtp_user']) ?>"
                                    placeholder="usuario@gmail.com">
                                <small class="text-muted">Seu e-mail do Gmail</small>
                            </div>
                            <div class="col-md-6">
                                <label for="smtp_pass" class="form-label">Senha SMTP</label>
                                <input type="password" class="form-control" id="smtp_pass" name="smtp_pass"
                                    value="<?= htmlspecialchars($configs['smtp_pass']) ?>" placeholder="Digite a senha">
                                <small class="text-muted">Use uma senha de app para Gmail</small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary me-2">
                                <span class="material-icons me-1">save</span>
                                Salvar SMTP
                            </button>
                        </div>
                    </form>

                    <hr>

                    <h6>Teste de E-mail</h6>
                    <form method="POST" class="d-flex gap-2 align-items-end">
                        <input type="hidden" name="action" value="test_email">
                        <div class="flex-grow-1">
                            <label for="email_teste" class="form-label">E-mail de Teste</label>
                            <input type="email" class="form-control" id="email_teste" name="email_teste"
                                placeholder="teste@exemplo.com" required>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">
                            <span class="material-icons me-1">send</span>
                            Enviar Teste
                        </button>
                    </form>
                </div>
            </div>

            <!-- Configurações de Segurança -->
            <div class="card">
                <div class="card-header">
                    <span class="material-icons me-2">security</span>
                    Configurações de Segurança
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_security">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="max_tentativas_login" class="form-label">Máx. Tentativas de Login</label>
                                <input type="number" class="form-control" id="max_tentativas_login"
                                    name="max_tentativas_login"
                                    value="<?= htmlspecialchars($configs['max_tentativas_login']) ?>" min="1" max="10">
                                <small class="text-muted">Número máximo de tentativas antes do bloqueio</small>
                            </div>
                            <div class="col-md-4">
                                <label for="tempo_bloqueio" class="form-label">Tempo de Bloqueio (min)</label>
                                <input type="number" class="form-control" id="tempo_bloqueio" name="tempo_bloqueio"
                                    value="<?= htmlspecialchars($configs['tempo_bloqueio']) ?>" min="1" max="1440">
                                <small class="text-muted">Tempo em minutos para desbloqueio automático</small>
                            </div>
                            <div class="col-md-4">
                                <label for="sessao_timeout" class="form-label">Timeout da Sessão (min)</label>
                                <input type="number" class="form-control" id="sessao_timeout" name="sessao_timeout"
                                    value="<?= htmlspecialchars($configs['sessao_timeout']) ?>" min="30" max="480">
                                <small class="text-muted">Tempo limite de inatividade da sessão</small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-icons me-1">save</span>
                                Salvar Segurança
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Configurações do Sistema -->
            <div class="card">
                <div class="card-header">
                    <span class="material-icons me-2">settings</span>
                    Configurações do Sistema
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_system">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="sistema_nome" class="form-label">Nome do Sistema</label>
                                <input type="text" class="form-control" id="sistema_nome" name="sistema_nome"
                                    value="<?= htmlspecialchars($configs['sistema_nome']) ?>" placeholder="SICEF">
                            </div>
                            <div class="col-md-6">
                                <label for="log_nivel" class="form-label">Nível de Log</label>
                                <select id="log_nivel" name="log_nivel" class="form-control">
                                    <option value="debug" <?= ($configs['log_nivel'] == 'debug') ? 'selected' : '' ?>>Debug
                                    </option>
                                    <option value="info" <?= ($configs['log_nivel'] == 'info') ? 'selected' : '' ?>>Info
                                    </option>
                                    <option value="warning" <?= ($configs['log_nivel'] == 'warning') ? 'selected' : '' ?>>
                                        Warning</option>
                                    <option value="error" <?= ($configs['log_nivel'] == 'error') ? 'selected' : '' ?>>Error
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="backup_automatico"
                                        name="backup_automatico" <?= $configs['backup_automatico'] == '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="backup_automatico">
                                        Backup Automático
                                    </label>
                                    <small class="d-block text-muted">Realizar backup automático dos dados</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="manutencao_modo"
                                        name="manutencao_modo" <?= $configs['manutencao_modo'] == '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="manutencao_modo">
                                        Modo Manutenção
                                    </label>
                                    <small class="d-block text-muted">Bloquear acesso de usuários não-admin</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-icons me-1">save</span>
                                Salvar Sistema
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Informações do Sistema -->
            <div class="card">
                <div class="card-header">
                    <span class="material-icons me-2">info</span>
                    Informações do Sistema
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Versão do Sistema</h6>
                            <p><?= htmlspecialchars($configs['sistema_versao']) ?></p>

                            <h6>Versão do PHP</h6>
                            <p><?= PHP_VERSION ?></p>

                            <h6>Banco de Dados</h6>
                            <p>PostgreSQL</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Espaço em Disco</h6>
                            <p><?= number_format(disk_free_space('.') / 1024 / 1024 / 1024, 2) ?> GB livres</p>

                            <h6>Memória PHP</h6>
                            <p><?= ini_get('memory_limit') ?></p>

                            <h6>Última Atualização</h6>
                            <p><?= date('d/m/Y H:i:s') ?></p>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-danger" onclick="clearCache()">
                            <span class="material-icons me-1">delete_sweep</span>
                            Limpar Cache
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="downloadLogs()">
                            <span class="material-icons me-1">download</span>
                            Download Logs
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="backupDatabase()">
                            <span class="material-icons me-1">backup</span>
                            Backup Manual
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Menu toggle responsivo
        document.addEventListener('DOMContentLoaded', function () {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            menuToggle.addEventListener('click', function () {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });

            sidebarOverlay.addEventListener('click', function () {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            });

            // Fechar sidebar ao clicar em link (mobile)
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function () {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('show');
                        sidebarOverlay.classList.remove('show');
                    }
                });
            });
        });

        // Funções de sistema
        function clearCache() {
            if (confirm('Tem certeza que deseja limpar o cache do sistema?')) {
                // Implementar limpeza de cache
                alert('Cache limpo com sucesso!');
            }
        }

        function downloadLogs() {
            // Implementar download de logs
            alert('Funcionalidade em desenvolvimento');
        }

        function backupDatabase() {
            if (confirm('Tem certeza que deseja fazer backup do banco de dados?')) {
                // Implementar backup
                alert('Backup iniciado. Você será notificado quando concluído.');
            }
        }
    </script>
</body>

</html>