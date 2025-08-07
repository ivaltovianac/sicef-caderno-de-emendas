<?php
// sicef-caderno-de-emendas/public/reset-password.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (isset($_SESSION['user'])) {
    header('Location: home.php');
    exit;
}

// 1. Inclua primeiro o arquivo de configuração do banco
require_once __DIR__ . '/../config/db.php';

// 2. Depois inclua o User.php
require_once __DIR__ . '/../models/User.php';

// 3. Crie a instância do User com a conexão PDO
$userModel = new User($pdo);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validações
    if (empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Todos os campos são obrigatórios';
    } elseif (strlen($password) < 8) {
        $error = 'A senha deve ter no mínimo 8 caracteres';
    } elseif ($password !== $confirm_password) {
        $error = 'As senhas não coincidem';
    } else {
        try {
            if ($userModel->updatePassword($email, $password)) {
                $success = 'Senha redefinida com sucesso!';
            } else {
                $error = 'Erro ao redefinir senha. Verifique o e-mail informado.';
            }
        } catch (PDOException $e) {
            $error = 'Erro no banco de dados: ' . $e->getMessage();
        }
    }
    // Se não houver erro, atualiza a senha
    if ($userModel->updatePassword($email, $password)) {
    $_SESSION['success'] = 'Senha redefinida com sucesso!';
    header('Location: login.php'); // Redireciona para login
    exit;
}
}
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Caderno de Emendas Federais 2025</title>
    <style>
        /* Estilo mantido conforme o arquivo original */
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #fff; }
        header { background: linear-gradient(90deg, #007b5e, #4db6ac); padding: 30px 50px; color: white; display: flex; justify-content: space-between; align-items: center; }
        header img { height: 50px; }
        nav { display: flex; gap: 20px; align-items: center; }
        nav a { color: white; text-decoration: none; font-weight: 500; }
        .hero { background: linear-gradient(90deg, #007b5e, #4db6ac); color: #ffc107; text-align: center; padding: 60px 20px; }
        .hero h1 { margin: 0; font-size: 40px; font-weight: bold; }
        .hero h2 { margin: 10px 0 0; font-size: 28px; color: #ffc107; }
        .login-section { padding: 40px 20px; max-width: 500px; margin: auto; }
        .login-section h3 { font-size: 24px; margin-bottom: 10px; color: #003366; }
        .login-section a { color: #007bff; text-decoration: none; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #007bff; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"] { width: 100%; padding: 10px; border: 2px solid #007bff; border-radius: 10px; font-size: 16px; }
        .btn { background-color: #003366; color: white; border: none; padding: 15px; width: 100%; font-size: 18px; border-radius: 10px; cursor: pointer; }
        footer { background: linear-gradient(90deg, #007b5e, #4db6ac); color: white; text-align: center; padding: 40px 20px; margin-top: 50px; }
        footer img { height: 30px; vertical-align: middle; }
        footer a { color: white; text-decoration: none; }
        .footer-info { margin-top: 20px; font-size: 14px; }
        .error { color: red; margin-bottom: 15px; text-align: center; }
        .success { color: green; margin-bottom: 15px; text-align: center; }
    </style>
</head>
<body>
    <header>
        <div>
            <img src="logo.png" alt="Logo SISEF"> SISEF
        </div>
        <nav>
            <a href="apresentacao.php">Início</a>
            <a href="mailto:gab.sucap@economia.gov.df.br">Contato</a>
        </nav>
    </header>

    <div class="hero">
        <h1>CADERNO DE EMENDAS FEDERAIS</h1>
        <h2>2025</h2>
    </div>

    <div class="login-section">
        <?php if($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
        
        <form method="POST" action="reset-password.php">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="Seu e-mail cadastrado" required>
            </div>
            
            <div class="form-group">
                <label for="password">Nova Senha</label>
                <input type="password" id="password" name="password" placeholder="Nova Senha" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Nova Senha</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirme a Nova Senha" required>
            </div>

            <button type="submit" class="btn">Redefinir Senha</button>
        </form>
    </div>

    <footer>
        <img src="logo.png" alt="Logo SICEF">
        <h3>SICEF</h3>
        <p>GDF - Governo do Distrito Federal</p>
        <div class="footer-info">
            <p>Anexo do Palácio do Buriti 5º andar, Brasília/DF - CEP: 70075-900</p>
            <p>📧 gab.sucap@economia.gov.df.br | (61) 3314-6213</p>
            <p>&copy; 2025. SEEC/SEFIN/SUCAP/COSP.</p>
        </div>
    </footer>
</body>
</html>