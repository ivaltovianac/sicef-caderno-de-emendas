<?php
// SICEF-caderno-de-emendas/public/solicitar_acesso.php
session_start();
require_once __DIR__ . "/../config/db.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST["nome"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $tipo = $_POST["tipo"] ?? "";

    if (empty($nome) || empty($email) || empty($tipo)) {
        $error = "Por favor, preencha todos os campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Por favor, digite um e-mail válido.";
    } elseif (!in_array($tipo, ["deputado", "senador"])) {
        $error = "Tipo de usuário inválido.";
    } else {
        try {
            // Verifica se já existe solicitação para o e-mail
            $stmt = $pdo->prepare("SELECT id FROM solicitacoes_acesso WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = "Já existe uma solicitação para este e-mail.";
            } else {
                // Insere a nova solicitação
                $stmt = $pdo->prepare("INSERT INTO solicitacoes_acesso (nome, email, tipo, status, data_solicitacao) VALUES (?, ?, ?, 'pendente', NOW())");

                if ($stmt->execute([$nome, $email, $tipo])) {
                    $message = "Solicitação enviada com sucesso! Aguarde a aprovação do administrador.";
                } else {
                    $error = "Erro ao enviar solicitação. Tente novamente.";
                }
            }
        } catch (PDOException $e) {
            error_log("Erro na solicitação de acesso: " . $e->getMessage());
            $error = "Erro interno do servidor. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Solicitar Acesso - SICEF</title>
    <!-- Bootstrap CSS, Google Fonts and Material Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet" />
    <style>
        :root {
            --primary-color: #00796B;
            --secondary-color: #009688;
            --accent-color: #FFC107;
            --light-color: #ECEFF1;
            --dark-color: #263238;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        /* Estilo header */
        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .logo {
            display: flex;
            align-items: center;
        }

        .logo img {
            height: 50px;
            margin-right: 15px;
        }

        .nav-menu {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .nav-menu a:hover {
            background-color: rgba(124, 175, 101, 0.1);
        }

        .nav-menu .btn-primary {
            background-color: #FFB300;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            white-space: nowrap;
            transition: background-color 0.3s, transform 0.3s;
            width: auto;
        }

        .nav-menu .btn-primary:hover {
            background-color: #FFB300;
            color: #263238;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-menu {
                justify-content: center;
                width: 100%;
            }
        }

        /* Main content area to center the form */
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .access-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }

        .access-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .access-header h2 {
            margin: 0;
            font-weight: 600;
        }

        .access-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }

        .access-body {
            padding: 2rem;
        }

        .access-body .btn-primary {
            background: var(--primary-color) !important;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            width: 100%;
            transition: background-color 0.3s, transform 0.3s;
            color: white;
        }

        .access-body .btn-primary:hover {
            background: #00695C !important;
            transform: translateY(-2px);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .access-footer {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
            text-align: center;
            padding: 1rem 2rem 2rem;
            border-top: 1px solid #e0e0e0
        }

        .access-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-control,
        .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 121, 107, 0.25);
        }

        .btn-primary {
            background: linear-gradient(13deg, var(--accent-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            width: 100%;
            transition: transform 0.3s;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #006157, #00796B);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        /* Footer styles */
        footer {
            background: var(--dark-color);
            color: white;
            padding: 3rem 0 1rem;
            margin-top: auto;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto 2rem;
            padding: 0 20px;
        }

        .footer-section h3 {
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .footer-section a {
            color: white;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
            transition: color 0.3s;
        }

        .footer-section a:hover {
            color: var(--accent-color);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            text-align: center;
            opacity: 0.8;
            max-width: 1200px;
            margin: 0 auto;
            padding-left: 20px;
            padding-right: 20px;
        }

        /* Responsividade */
        @media (max-width: 480px) {
            .access-container {
                margin: 10px;
            }

            .access-header,
            .access-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="apresentacao.php"><img src="imagens/logo.svg" alt="SICEF Logo" /></a>
                </div>
                <nav class="nav-menu">
                    <a href="apresentacao.php">Início</a>
                    <a href="#contato">Contatos</a>
                    <a href="login.php" class="btn btn-primary">
                    <span class="material-icons">login</span>
                    Entrar</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main content -->
    <div class="main-content">
        <div class="access-container">
            <div class="access-header">
                <h2><span class="material-icons me-2">person_add</span>Solicitar Acesso</h2>
                <p>Preencha os dados para solicitar acesso ao sistema</p>
            </div>

            <div class="access-body">
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

                <div class="info-box">
                    <h6><span class="material-icons me-1">info</span>Informações Importantes</h6>
                    <p>• Apenas deputados e senadores podem solicitar acesso</p>
                    <p>• Sua solicitação será analisada pela equipe administrativa</p>
                    <p>• Você receberá um e-mail com a resposta em até 48 horas</p>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="nome" class="form-label">
                            <span class="material-icons me-1">person</span>
                            Nome Completo
                        </label>
                        <input type="text" class="form-control" id="nome" name="nome" required
                            placeholder="Digite seu nome completo"
                            value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" />
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">
                            <span class="material-icons me-1">email</span>
                            E-mail
                        </label>
                        <input type="email" class="form-control" id="email" name="email" required
                            placeholder="seu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
                    </div>

                    <div class="form-group">
                        <label for="tipo" class="form-label">
                            <span class="material-icons me-1">how_to_vote</span>
                            Tipo de Parlamentar
                        </label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="">Selecione...</option>
                            <option value="deputado" <?= ($_POST['tipo'] ?? '') === 'deputado' ? 'selected' : '' ?>>
                                Deputado Federal</option>
                            <option value="senador" <?= ($_POST['tipo'] ?? '') === 'senador' ? 'selected' : '' ?>>Senador
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <span class="material-icons me-2">send</span>
                        Enviar Solicitação
                    </button>
                </form>
            </div>

            <div class="access-footer">
                <div class="mb-2">
                    <a href="formulario_para_login.php">
                        <span class="material-icons me-1">login</span>
                        Voltar ao Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer id="contato">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Contato</h3>
                    <p>Secretaria de Estado de Economia</p>
                    <p>Email: <a href="mailto:cosp@economia.gov.df.br">cosp@economia.gov.df.br</a></p>
                    <p>Telefone: <a href="tel:+55(61)3314-6213">(61) 3314-6213</a></p>
                    <p>Anexo do Palácio do Buriti, 5º andar, Brasília/DF | CEP: 70075-900</p>
                    <p>Horário de atendimento: Segunda a Sexta, 8h às 18h</p>
                </div>
                <div class="footer-section">
                    <h3>Links Úteis</h3>
                    <a href="login.php">Entrar no Sistema</a>
                    <a href="solicitar_acesso.php">Solicitar Acesso</a>
                    <a href="reset-password.php">Recuperar Senha</a>
                </div>
                <!-- <div class="footer-section">
          <h3>Suporte</h3>
          <a href="mailto:cosp@economia.gov.df.br">cosp@economia.gov.df.br</a>
          <a href="tel:+55(61)3314-6213">(61) 3314-6213</a>
          <p>Horário: Segunda a Sexta, 8h às 18h</p>
        </div> -->
                <div class="footer-section">
                    <h3>Sobre</h3>
                    <p>Desenvolvido pela equipe técnica da Subsecretária de Captação de Recursos - SUCAP.</p>
                </div>
            </div>
            <!-- Footer padronizado -->
            <div class="footer-bottom">
                <p>&copy; <?php echo date("Y"); ?> SICEF - Sistema de Caderno de Emendas Federais. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>