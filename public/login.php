<?php
/**
 * Processamento de Login - SICEF
 * 
 * Este arquivo é responsável por processar as tentativas de login dos usuários no sistema.
 * Ele verifica as credenciais fornecidas, autentica o usuário e o redireciona conforme
 * seu tipo de acesso (administrador ou usuário comum).
 * 
 * Funcionalidades:
 * - Verificação de credenciais de usuário
 * - Registro de tentativas de login (sucesso/falha)
 * - Criação de sessão para usuário autenticado
 * - Redirecionamento por tipo de usuário
 * - Tratamento de erros
 * 
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */

// Inicia a sessão para gerenciar dados do usuário
session_start();

// Inclui os arquivos necessários para conexão com o banco de dados e manipulação de usuários
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/User.php";

/**
 * Verifica se o usuário já está logado
 * Se estiver, redireciona automaticamente para o dashboard apropriado
 */
if (isset($_SESSION["user"])) {
    // Redireciona para o painel administrativo se for administrador
    if ($_SESSION["user"]["is_admin"]) {
        header("Location: admin/admin_dashboard.php");
    } 
    // Redireciona para o painel do usuário comum caso contrário
    else {
        header("Location: user/user_dashboard.php");
    }
    exit;
}

/**
 * Processa o formulário de login quando enviado via método POST
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Obtém e limpa os dados enviados pelo formulário
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    /**
     * Validação dos campos obrigatórios
     * Verifica se email e senha foram preenchidos
     */
    if (empty($email) || empty($senha)) {
        $_SESSION["error"] = "Por favor, preencha todos os campos.";
        header("Location: formulario_para_login.php");
        exit;
    }

    try {
        /**
         * Busca o usuário no banco de dados pelo email fornecido
         * Utiliza o modelo User para interagir com a tabela de usuários
         */
        $userModel = new User($pdo);
        $user = $userModel->findByEmail($email);

        /**
         * Verifica se o usuário existe e se a senha está correta
         * Utiliza password_verify para comparar a senha criptografada
         */
        if ($user && password_verify($senha, $user["senha"])) {
            /**
             * Login bem-sucedido - registra a tentativa no log de acessos
             * Armazena informações como email, IP e status da tentativa
             */
            $stmt = $pdo->prepare("INSERT INTO log_login (email, ip, sucesso, data_tentativa) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown', true]);

            /**
             * Cria a sessão do usuário com seus dados
             * Armazena informações essenciais para uso no sistema
             */
            $_SESSION["user"] = [
                "id" => $user["id"],
                "nome" => $user["nome"],
                "email" => $user["email"],
                "tipo" => $user["tipo"],
                "is_admin" => (bool)$user["is_admin"],
                "is_user" => (bool)$user["is_user"]
            ];

            /**
             * Redireciona o usuário para o dashboard apropriado
             * Baseado no tipo de usuário (administrador ou comum)
             */
            if ($user["is_admin"]) {
                header("Location: admin/admin_dashboard.php");
            } else {
                header("Location: user/user_dashboard.php");
            }
            exit;
        } else {
            /**
             * Login falhou - registra a tentativa no log de acessos
             * Armazena informações sobre a tentativa malsucedida
             */
            $stmt = $pdo->prepare("INSERT INTO log_login (email, ip, sucesso, data_tentativa) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown', false]);

            // Define mensagem de erro para exibição no formulário
            $_SESSION["error"] = "E-mail ou senha incorretos.";
            header("Location: formulario_para_login.php");
            exit;
        }
    } catch (PDOException $e) {
        /**
         * Tratamento de exceções do banco de dados
         * Registra o erro no log do servidor e exibe mensagem
         */
        error_log("Erro no login: " . $e->getMessage());
        $_SESSION["error"] = "Erro interno do servidor. Tente novamente.";
        header("Location: formulario_para_login.php");
        exit;
    }
} else {
    /**
     * Se o método de requisição não for POST, redireciona para o formulário de login
     * Isso previne acesso direto ao script sem envio do formulário
     */
    header("Location: formulario_para_login.php");
    exit;
}
?>