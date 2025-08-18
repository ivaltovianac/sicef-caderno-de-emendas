
<?php
// SICEF-caderno-de-emendas/public/login.php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/User.php";

// Se já estiver logado, redirecionar
if (isset($_SESSION["user"])) {
    if ($_SESSION["user"]["is_admin"]) {
        header("Location: admin/admin_dashboard.php");
    } else {
        header("Location: user/user_dashboard.php");
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if (empty($email) || empty($senha)) {
        $_SESSION["error"] = "Por favor, preencha todos os campos.";
        header("Location: formulario_para_login.php");
        exit;
    }

    try {
        $userModel = new User($pdo);
        $user = $userModel->findByEmail($email);

        if ($user && password_verify($senha, $user["senha"])) {
            // FIX: Login bem-sucedido - registrar no log
            $stmt = $pdo->prepare("INSERT INTO log_login (email, ip, sucesso, data_tentativa) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown', true]);

            // Definir sessão
            $_SESSION["user"] = [
                "id" => $user["id"],
                "nome" => $user["nome"],
                "email" => $user["email"],
                "tipo" => $user["tipo"],
                "is_admin" => (bool)$user["is_admin"],
                "is_user" => (bool)$user["is_user"]
            ];

            // Redirecionar baseado no tipo de usuário
            if ($user["is_admin"]) {
                header("Location: admin/admin_dashboard.php");
            } else {
                header("Location: user/user_dashboard.php");
            }
            exit;
        } else {
            // FIX: Login falhou - registrar no log
            $stmt = $pdo->prepare("INSERT INTO log_login (email, ip, sucesso, data_tentativa) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown', false]);

            $_SESSION["error"] = "E-mail ou senha incorretos.";
            header("Location: formulario_para_login.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erro no login: " . $e->getMessage());
        $_SESSION["error"] = "Erro interno do servidor. Tente novamente.";
        header("Location: formulario_para_login.php");
        exit;
    }
} else {
    // Se não for POST, redirecionar para o formulário
    header("Location: formulario_para_login.php");
    exit;
}
