<?php
/**
 * Formulário de Login - SICEF
 *
 * Este arquivo contém o formulário de autenticação do Sistema de Caderno de Emendas Federais (SICEF).
 * Ele permite que usuários registrados acessem o sistema com email e senha.
 *
 * Funcionalidades:
 * - Validação de credenciais de usuário
 * - Exibição de mensagens de sucesso/erro
 * - Links para recuperação de senha e solicitação de acesso
 *
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */

// Inicia a sessão para gerenciar dados do usuário entre páginas
session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - SICEF</title>

    <!-- 
    Inclusão de folhas de estilo externas:
    - Bootstrap CSS para componentes responsivos e estilização
    - Material Icons para ícones vetoriais
    - Google Fonts para tipografia personalizada (Poppins)
    -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet" />
    <style>
        /* 
        Definição de variáveis CSS para cores da marca:
        - primary-color: Cor principal (verde turquesa)
        - secondary-color: Cor secundária (verde água)
        - accent-color: Cor de destaque (amarelo)
        - light-color: Cor clara para fundos
        - dark-color: Cor escura para textos
        */
        :root {
            --primary-color: #00796B;
            --secondary-color: #009688;
            --accent-color: #FFC107;
            --light-color: #ECEFF1;
            --dark-color: #263238;
        }

        /* 
        Estilização do corpo da página:
        - Define a fonte Poppins como padrão
        - Aplica um gradiente de cores como fundo
        - Configura o layout flexível para centralizar conteúdo
        */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        /* 
        Estilização do cabeçalho:
        - Mantém o gradiente de cores da marca
        - Define cor do texto como branco
        - Adiciona sombra para destaque visual
        */
        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* 
        Container principal do cabeçalho:
        - Define largura máxima para conteúdo
        - Centraliza conteúdo horizontalmente
        - Adiciona padding para espaçamento
        */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* 
        Conteúdo do cabeçalho:
        - Usa flexbox para alinhar elementos
        - Distribui espaço entre logo e menu
        - Permite quebra de linha em telas pequenas
        */
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        /* 
        Estilização da logo:
        - Usa flexbox para alinhar itens
        */
        .logo {
            display: flex;
            align-items: center;
        }

        /* 
        Estilização da imagem da logo:
        - Define altura fixa
        - Adiciona margem à direita
        */
        .logo img {
            height: 50px;
            margin-right: 15px;
        }

        /* 
        Menu de navegação:
        - Usa flexbox para layout horizontal
        - Adiciona espaçamento entre itens
        - Permite quebra de linha em telas pequenas
        */
        .nav-menu {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        /* 
        Links do menu:
        - Define cor branca
        - Remove sublinhado padrão
        - Adiciona padding e bordas arredondadas
        - Adiciona transição suave para hover
        */
        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        /* 
        Efeito hover nos links do menu:
        - Muda cor de fundo ao passar o mouse
        */
        .nav-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* 
        Botão primário do menu:
        - Define cor de fundo amarela
        - Define cor do texto como branca
        - Remove borda padrão
        - Adiciona bordas arredondadas
        - Adiciona padding e peso de fonte
        - Adiciona transição suave para hover
        */
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

        /* 
        Efeito hover no botão primário:
        - Muda cor de fundo e texto
        - Adiciona efeito de elevação
        */
        .nav-menu .btn-primary:hover {
            background-color: #FFB300;
            color: #263238;
            transform: translateY(-2px);
        }

        /* 
        Botão primário genérico:
        - Define gradiente de cores como fundo
        - Remove borda padrão
        - Adiciona bordas arredondadas
        - Adiciona padding e peso de fonte
        - Adiciona transição suave
        - Define cor do texto como branca
        */
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

        /* 
        Efeito hover no botão primário:
        - Adiciona efeito de elevação
        - Muda gradiente de cores
        */
        .btn-primary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #006157, #00796B);
        }

        /* 
        Estilos responsivos para telas menores:
        - Altera direção do conteúdo do cabeçalho para coluna
        - Centraliza menu
        - Faz menu ocupar 100% da largura
        */
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

        /* 
        Área de conteúdo principal:
        - Usa flexbox para centralizar vertical e horizontalmente
        - Adiciona padding para espaçamento
        */
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* 
        Container do formulário de login:
        - Define fundo branco
        - Adiciona bordas arredondadas
        - Adiciona sombra para destaque
        - Define largura máxima e responsiva
        - Esconde overflow
        */
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            overflow: hidden;
        }

        /* 
        Cabeçalho do formulário de login:
        - Mantém gradiente de cores da marca
        - Define cor do texto como branca
        - Adiciona padding
        - Centraliza texto
        */
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        /* 
        Título do cabeçalho:
        - Remove margem padrão
        - Adiciona peso de fonte
        */
        .login-header h2 {
            margin: 0;
            font-weight: 600;
        }

        /* 
        Parágrafo do cabeçalho:
        - Remove margem superior
        - Adiciona opacidade para destaque menor
        */
        .login-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }

        /* 
        Corpo do formulário de login:
        - Adiciona padding
        */
        .login-body {
            padding: 2rem;
        }

        /* 
        Grupo de formulário:
        - Adiciona margem inferior
        */
        .form-group {
            margin-bottom: 1.5rem;
        }

        /* 
        Rodapé do formulário de login:
        - Usa flexbox para layout vertical
        - Adiciona espaçamento entre itens
        - Adiciona margem superior
        - Centraliza texto
        - Adiciona padding
        - Adiciona borda superior
        */
        .login-footer {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
            text-align: center;
            padding: 1rem 2rem 2rem;
            border-top: 1px solid #e0e0e0
        }

        /* 
        Links do rodapé do formulário:
        - Define cor primária
        - Remove sublinhado padrão
        - Adiciona peso de fonte
        */
        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        /* 
        Rótulo de formulário:
        - Adiciona peso de fonte
        - Define cor escura
        - Adiciona margem inferior
        */
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        /* 
        Campo de formulário:
        - Define borda padrão
        - Adiciona bordas arredondadas
        - Adiciona padding
        - Define tamanho de fonte
        - Adiciona transição suave
        */
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        /* 
        Efeito focus no campo de formulário:
        - Muda cor da borda
        - Adiciona sombra de destaque
        */
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 121, 107, 0.25);
        }

        /* 
        Botão primário de login:
        - Define gradiente de cores como fundo
        - Remove borda padrão
        - Adiciona bordas arredondadas
        - Adiciona padding e peso de fonte
        - Adiciona transição suave
        - Define cor do texto como branca
        */
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

        /* 
        Efeito hover no botão primário de login:
        - Adiciona efeito de elevação
        - Muda gradiente de cores
        */
        .btn-primary-login:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #006157, #00796B);
        }

        /* 
        Alertas:
        - Adiciona bordas arredondadas
        - Remove borda padrão
        */
        .alert {
            border-radius: 10px;
            border: none;
        }

        /* 
        Rodapé da página:
        - Define cor escura como fundo
        - Define cor do texto como branca
        - Adiciona padding
        - Força margem para baixo
        */
        footer {
            background: var(--dark-color);
            color: white;
            padding: 3rem 0 1rem;
            margin-top: auto;
        }

        /* 
        Conteúdo do rodapé:
        - Usa grid para layout responsivo
        - Define espaçamento entre colunas
        - Define largura máxima
        - Centraliza horizontalmente
        - Adiciona padding lateral
        */
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto 2rem;
            padding: 0 20px;
        }

        /* 
        Seção do rodapé:
        - Define estilo para cada coluna
        */
        .footer-section h3 {
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        /* 
        Links das seções do rodapé:
        - Define cor branca
        - Remove sublinhado padrão
        - Define como bloco
        - Adiciona margem inferior
        - Adiciona transição suave
        */
        .footer-section a {
            color: white;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
            transition: color 0.3s;
        }

        /* 
        Efeito hover nos links do rodapé:
        - Muda cor para amarela
        */
        .footer-section a:hover {
            color: var(--accent-color);
        }

        /* 
        Rodapé inferior:
        - Adiciona borda superior
        - Adiciona padding superior
        - Centraliza texto
        - Adiciona opacidade
        - Define largura máxima
        - Centraliza horizontalmente
        - Adiciona padding lateral
        */
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

        /* Responsividade para telas muito pequenas:
        - Reduz margens do container
        - Ajusta padding do cabeçalho e corpo
        */
        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
            }

            .login-header,
            .login-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Cabeçalho da página:
    - Contém logo e menu de navegação
    - Links para página inicial, contatos e login
    -->
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                <!-- Logo do sistema com link para página inicial apresentacao.php
                -->
                    <a href="apresentacao.php"><img src="imagens/logo.svg" alt="SICEF Logo" /></a>
                </div>
                <nav class="nav-menu">
                    <!-- Menu de navegação:
                    - Link para página inicial
                    - Link para seção de contatos
                    - Botão de login com ícone
                    -->
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

    <!-- Conteúdo principal:
    - Contém o formulário de login centralizado
    -->
    <div class="main-content">
        <div class="login-container">
            <!-- Cabeçalho do formulário:
            - Exibe nome do sistema
            - Exibe descrição do sistema
            -->
            <div class="login-header">
                <h2><span class="material-icons me-2">account_circle</span>SICEF</h2>
                <p>Sistema de Caderno de Emendas Federais</p>
            </div>

            <!-- Corpo do formulário:
            - Contém campos de email e senha
            - Exibe mensagens de feedback
            -->
            <div class="login-body">
                <?php
                /**
                 * Exibe mensagens de feedback para o usuário
                 * Verifica se há mensagens de sucesso ou erro na sessão e as exibe
                 * As mensagens são removidas da sessão após serem exibidas
                 */
                // Verifica se existe mensagem de informação na sessão
                if (isset($_SESSION['message'])): ?>
                    <!-- 
                    Alerta de informação:
                    - Exibe mensagem de sucesso ou informação
                    - Inclui botão para fechar o alerta
                    -->
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <span class="material-icons me-2">info</span>
                        <?= htmlspecialchars($_SESSION['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <?php
                // Verifica se existe mensagem de erro na sessão
                if (isset($_SESSION['error'])): ?>
                    <!-- 
                    Alerta de erro:
                    - Exibe mensagem de erro
                    - Inclui botão para fechar o alerta
                    -->
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <span class="material-icons me-2">error</span>
                        <?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Formulário de login:
                - Envia dados via POST para login.php
                - Contém campos de email e senha
                -->
                <form method="POST" action="login.php">
                    <!-- Campo de email:
                    - Define tipo como email para validação automática
                    - Define como obrigatório
                    -->
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <span class="material-icons me-1">email</span>
                            E-mail
                        </label>
                        <input type="email" class="form-control" id="email" name="email" required
                            placeholder="seu@email.com" />
                    </div>

                    <!-- Campo de senha:
                    - Define tipo como password para ocultar caracteres
                    - Define como obrigatório
                    -->
                    <div class="form-group">
                        <label for="senha" class="form-label">
                            <span class="material-icons me-1">lock</span>
                            Senha
                        </label>
                        <input type="password" class="form-control" id="senha" name="senha" required
                            placeholder="Digite sua senha" />
                    </div>

                    <!-- Botão de submit:
                    - Envia formulário para processamento
                    -->
                    <button type="submit" class="btn-primary-login">
                        <span class="material-icons me-2">login</span>
                        Entrar
                    </button>
                </form>
            </div>

            <!-- 
    Rodapé do formulário:
    - Contém links para recuperação de senha e solicitação de acesso
    -->
            <div class="login-footer">
                <div class="mb-2">
                    <!-- 
    Link para recuperação de senha:
    - Direciona para reset-password.php
    -->
                    <a href="reset-password.php">
                        <span class="material-icons me-1">help</span>
                        Esqueci minha senha
                    </a>
                </div>
                <div class="mb-2">
                    <!-- 
    Link para solicitação de acesso:
    - Direciona para solicitar_acesso.php
    -->
                    <a href="solicitar_acesso.php">
                        <span class="material-icons me-1">person_add</span>
                        Solicitar acesso
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 
    Rodapé da página:
    - Contém informações de contato
    - Links úteis
    - Informações sobre o sistema
    - Direitos autorais
    -->
    <footer id="contato">
        <div class="container">
            <div class="footer-content">
                <!-- 
    Seção de contato:
    - Exibe informações de contato da secretaria
    - Inclui links para email e telefone
    -->
                <div class="footer-section">
                    <h3>Contato</h3>
                    <p>Secretaria de Estado de Economia</p>
                    <p>Email: <a href="mailto:cosp@economia.gov.df.br">cosp@economia.gov.df.br</a></p>
                    <p>Telefone: <a href="tel:+55(61)3314-6213">(61) 3314-6213</a></p>
                    <p>Anexo do Palácio do Buriti, 5º andar, Brasília/DF | CEP: 70075-900</p>
                    <p>Horário de atendimento: Segunda a Sexta, 8h às 18h</p>
                </div>

                <!-- 
    Seção de links úteis:
    - Exibe links para funcionalidades principais
    -->
                <div class="footer-section">
                    <h3>Links Úteis</h3>
                    <a href="login.php">Entrar no Sistema</a>
                    <a href="solicitar_acesso.php">Solicitar Acesso</a>
                    <a href="reset-password.php">Recuperar Senha</a>
                </div>

                <!-- 
    Seção sobre:
    - Exibe informações sobre o desenvolvimento
    -->
                <div class="footer-section">
                    <h3>Sobre</h3>
                    <p>Desenvolvido pela equipe técnica da Subsecretária de Captação de Recursos - SUCAP.</p>
                </div>
            </div>

            <!-- 
    Rodapé inferior:
    - Exibe direitos autorais com ano atual
    -->
            <div class="footer-bottom">
                <p>&copy; <?php echo date("Y"); ?> SICEF - Sistema de Caderno de Emendas Federais. Todos os direitos
                    reservados.</p>
            </div>
        </div>
    </footer>

    <!-- 
    Scripts JavaScript:
    - Inclui Bootstrap JS para funcionalidades de componentes
    -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>