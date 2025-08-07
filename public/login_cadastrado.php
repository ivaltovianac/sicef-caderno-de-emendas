<?php
// sicef-caderno-de-emendas/public/login.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';

// Inicializa o modelo de usuário com a conexão PDO
$userModel = new User($pdo);
$error = null;

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    $user = $userModel->findByEmail($email);

    if ($user && password_verify($password, $user['senha'])) {
        $_SESSION['user'] = $user;
        
        // Redireciona baseado no tipo de usuário
        if ($user['is_admin']) {
            header('Location: admin/admin_dashboard.php');
        } else {
            header('Location: user/user_dashboard.php');
        }
        exit;
    } else {
        $error = 'Credenciais inválidas';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Caderno de Emendas Federais 2025</title>
    <style>
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
        .form-group input[type="checkbox"] { margin-right: 5px; }
        .btn { background-color: #003366; color: white; border: none; padding: 15px; width: 100%; font-size: 18px; border-radius: 10px; cursor: pointer; }
        .note { font-size: 12px; color: #003366; margin-bottom: 20px; }
        footer { background: linear-gradient(90deg, #007b5e, #4db6ac); color: white; text-align: center; padding: 40px 20px; margin-top: 50px; }
        footer img { height: 30px; vertical-align: middle; }
        footer a { color: white; text-decoration: none; }
        .footer-info { margin-top: 20px; font-size: 14px; }
        .error { color: red; margin-bottom: 15px; text-align: center; }
    </style>
</head>
<body>
    <header>
        <div>
            <img src="logo.png" alt="Logo SISEF"> SISEF
        </div>
        <nav>
            <a href="apresentacao.php">Sobre</a>
            <a href="mailto:gab.sucap@economia.gov.df.br">Contato</a>
            <a href="formulario_para_login.php">Voltar</a>
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

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="dep.nome@camara.leg.br" required>
            </div>

            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" placeholder="Senha" required>
                <a href="reset-password.php" class="btn-option">Redefinir senha</a>
            </div>

            <button type="submit" class="btn">Acessar</button>
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