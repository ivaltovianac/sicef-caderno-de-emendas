<?php
/**
 * Redefinição de Senha - SICEF
 *
 * Este arquivo permite que usuários redefinam sua senha no sistema.
 * Ele valida os dados do formulário, verifica se o email existe, atualiza a senha
 * e exibe mensagens de sucesso ou erro.
 *
 * Funcionalidades:
 * - Validação dos campos do formulário
 * - Verificação de correspondência das senhas
 * - Atualização da senha no banco de dados
 * - Exibição de mensagens de feedback
 * - Validação de senha em tempo real via JavaScript
 *
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */

// Inicia a sessão para armazenar mensagens e dados do usuário
session_start();

// Inclui arquivos para conexão com banco e manipulação de usuários
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/User.php";

// Inicializa variáveis para mensagens de feedback
$message = "";
$error = "";

// Verifica se o formulário foi enviado via POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Obtém e limpa os dados do formulário
    $email = trim($_POST["email"] ?? "");
    $nova_senha = $_POST["nova_senha"] ?? "";
    $confirmar_senha = $_POST["confirmar_senha"] ?? "";

    // Valida os campos obrigatórios
    if (empty($email) || empty($nova_senha) || empty($confirmar_senha)) {
        $error = "Por favor, preencha todos os campos.";
    }
    // Verifica se as senhas coincidem
    elseif ($nova_senha !== $confirmar_senha) {
        $error = "As senhas não coincidem.";
    }
    // Verifica o tamanho mínimo da senha
    elseif (strlen($nova_senha) < 6) {
        $error = "A senha deve ter pelo menos 6 caracteres.";
    } else {
        try {
            // Cria instância do modelo User para manipulação dos dados
            $userModel = new User($pdo);
            // Busca usuário pelo email informado
            $user = $userModel->findByEmail($email);

            // Verifica se o usuário existe
            if (!$user) {
                $error = "E-mail não encontrado no sistema.";
            } else {
                // Atualiza a senha do usuário no banco de dados
                if ($userModel->updatePassword($email, $nova_senha)) {
                    $message = "Senha alterada com sucesso! Você pode fazer login agora.";
                } else {
                    $error = "Erro ao alterar a senha. Tente novamente.";
                }
            }
        } catch (PDOException $e) {
            // Registra erro no log do servidor e exibe mensagem
            error_log("Erro no reset de senha: " . $e->getMessage());
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
    <title>Redefinir Senha - SICEF</title>
    <!-- Inclusão de CSS e fontes externas para estilo e ícones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet" />
    <style>
        /* Definição de variáveis de cores para identidade visual */
        :root {
            --primary-color: #00796B;
            --secondary-color: #009688;
            --accent-color: #FFC107;
            --light-color: #ECEFF1;
            --dark-color: #263238;
        }

        /* Estilização geral do corpo da página */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        /* Estilização do cabeçalho */
        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Container centralizado com largura máxima */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Layout flexível para conteúdo do cabeçalho */
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Estilização da logo */
        .logo {
            display: flex;
            align-items: center;
        }

        .logo img {
            height: 50px;
            margin-right: 15px;
        }

        /* Menu de navegação */
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
            background-color: rgba(255, 255, 255, 0.1);
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

        /* Botão primário */
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

        /* Responsividade para telas menores */
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

        /* Área principal para centralizar o formulário */
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* Container do formulário de redefinição */
        .reset-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 450px;
            width: 100%;
            overflow: hidden;
        }

        /* Cabeçalho do formulário */
        .reset-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .reset-header h2 {
            margin: 0;
            font-weight: 600;
        }

        .reset-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }

        /* Corpo do formulário */
        .reset-body {
            padding: 2rem;
        }

        /* Espaçamento entre grupos de formulário */
        .form-group {
            margin-bottom: 1.5rem;
        }

        /* Rodapé do formulário */
        .reset-footer {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
            text-align: center;
            padding: 1rem 2rem 2rem;
            border-top: 1px solid #e0e0e0
        }

        .reset-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        /* Estilização dos rótulos */
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        /* Estilização dos campos de formulário */
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        /* Efeito de foco nos campos */
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 121, 107, 0.25);
        }

        /* Botão primário de envio */
        .btn-primary-login {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            width: 100%;
            transition: transform 0.3s;
            color: white;
        }

        .btn-primary-login:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #006157, #00796B);
        }

        /* Estilização dos alertas */
        .alert {
            border-radius: 10px;
            border: none;
        }

        /* Estilização do rodapé */
        footer {
            background: var(--dark-color);
            color: white;
            padding: 3rem 0 1rem;
            margin-top: auto;
        }

        /* Conteúdo do rodapé com grid responsivo */
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto 2rem;
            padding: 0 20px;
        }

        /* Seções do rodapé */
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

        /* Rodapé inferior */
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

        /* Responsividade para telas pequenas */
        @media (max-width: 480px) {
            .reset-container {
                margin: 10px;
            }

            .reset-header,
            .reset-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Cabeçalho da página -->
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <!-- Logo com link para página inicial -->
                    <a href="apresentacao.php"><img src="imagens/logo.svg" alt="SICEF Logo" /></a>
                </div>
                <!-- Menu de navegação -->
                <nav class="nav-menu">
                    <a href="apresentacao.php">Início</a>
                    <a href="#contato">Contatos</a>
                    <a href="login.php" class="btn btn-primary">
                        <span class="material-icons">login</span>
                        Entrar
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Conteúdo principal -->
    <div class="main-content">
        <div class="reset-container">
            <!-- Cabeçalho do formulário -->
            <div class="reset-header">
                <h2><span class="material-icons me-2">lock_reset</span>Redefinir Senha</h2>
                <p>Digite seu e-mail e nova senha</p>
            </div>

            <!-- Corpo do formulário -->
            <div class="reset-body">
                <!-- Mensagem de sucesso -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <span class="material-icons me-2">check_circle</span>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Mensagem de erro -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <span class="material-icons me-2">error</span>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Formulário de redefinição de senha -->
                <form method="POST">
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <span class="material-icons me-1">email</span>
                            E-mail
                        </label>
                        <input type="email" class="form-control" id="email" name="email" required
                            placeholder="seu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
                    </div>

                    <div class="form-group">
                        <label for="nova_senha" class="form-label">
                            <span class="material-icons me-1">lock</span>
                            Nova Senha
                        </label>
                        <input type="password" class="form-control" id="nova_senha" name="nova_senha" required
                            placeholder="Digite a nova senha" minlength="6" />
                    </div>

                    <div class="form-group">
                        <label for="confirmar_senha" class="form-label">
                            <span class="material-icons me-1">lock</span>
                            Confirmar Nova Senha
                        </label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required
                            placeholder="Confirme a nova senha" minlength="6" />
                    </div>

                    <button type="submit" class="btn-primary-login">
                        <span class="material-icons me-2">save</span>
                        Alterar Senha
                    </button>
                </form>
            </div>

            <!-- Rodapé do formulário com links úteis -->
            <div class="reset-footer">
                <div class="mb-2">
                    <a href="formulario_para_login.php">
                        <span class="material-icons me-1">login</span>
                        Voltar ao login
                    </a>
                </div>
                <div class="mb-2">
                    <a href="solicitar_acesso.php">
                        <span class="material-icons me-1">person_add</span>
                        Solicitar acesso
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Rodapé da página com informações de contato e links -->
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
                <div class="footer-section">
                    <h3>Sobre</h3>
                    <p>Desenvolvido pela equipe técnica da Subsecretária de Captação de Recursos - SUCAP.</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date("Y"); ?> SICEF - Sistema de Caderno de Emendas Federais. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Inclusão do Bootstrap JS para funcionalidades -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validação em tempo real para confirmar se as senhas coincidem
        document.getElementById('confirmar_senha').addEventListener('input', function () {
            const senha = document.getElementById('nova_senha').value;
            const confirmar = this.value;

            if (senha !== confirmar) {
                this.setCustomValidity('As senhas não coincidem');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>

</html>