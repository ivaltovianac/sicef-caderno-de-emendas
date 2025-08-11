<?php
// sicef-caderno-de-emendas/public/reset-password.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (isset($_SESSION['user'])) {
    header('Location: home.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';

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
                $_SESSION['success'] = 'Senha redefinida com sucesso!';
                header('Location: login.php');
                exit;
            } else {
                $error = 'Erro ao redefinir senha. Verifique o e-mail informado.';
            }
        } catch (PDOException $e) {
            $error = 'Erro no banco de dados: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Caderno de Emendas Federais 2025</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007b5e;
            --secondary-color: #4db6ac;
            --accent-color: #ffc107;
            --dark-color: #003366;
            --light-color: #f8f9fa;
            --error-color: #dc3545;
            --success-color: #28a745;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-color);
            color: #333;
            line-height: 1.6;
        }
        
        header {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            padding: 1rem 2rem;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo img {
            height: 50px;
            transition: transform 0.3s;
        }
        
        .logo img:hover {
            transform: scale(1.05);
        }
        
        nav {
            display: flex;
            gap: 1.5rem;
        }
        
        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: opacity 0.3s;
        }
        
        nav a:hover {
            opacity: 0.9;
        }
        
        .reset-container {
            display: flex;
            min-height: calc(100vh - 180px);
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
            margin: 2rem 0;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-header h2 {
            color: var(--dark-color);
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .reset-header p {
            color: #666;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(77, 182, 172, 0.2);
            outline: none;
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #777;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            background: var(--dark-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            background: #002244;
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .reset-footer {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .reset-footer a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .reset-footer a:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
        
        .error {
            color: var(--error-color);
            background-color: rgba(220, 53, 69, 0.1);
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }
        
        .success {
            color: var(--success-color);
            background-color: rgba(40, 167, 69, 0.1);
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }
        
        footer {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: white;
            text-align: center;
            padding: 2rem 1rem;
        }
        
        .footer-logo img {
            height: 40px;
            margin-bottom: 1rem;
        }
        
        .footer-info {
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .footer-info p {
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                padding: 1rem;
                gap: 1rem;
            }
            
            nav {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .reset-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <a href="apresentacao.php" title="Ir para página inicial">
                <img src="imagens/logo.png" alt="Logo SICEF">
            </a>
        </div>
        <nav>
            <a href="apresentacao.php"><span class="material-icons">info</span> Início</a>
            <a href="mailto:gab.sucap@economia.gov.df.br"><span class="material-icons">email</span> Contato</a>
            <a href="login.php"><span class="material-icons">arrow_back</span> Voltar ao Login</a>
        </nav>
    </header>

    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <h2>Redefinir Senha</h2>
                <p>Informe seu e-mail e a nova senha</p>
            </div>

            <?php if($error): ?>
                <div class="error">
                    <span class="material-icons" style="vertical-align: middle;">error</span>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['success'])): ?>
                <div class="success">
                    <span class="material-icons" style="vertical-align: middle;">check_circle</span>
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <form method="POST" action="reset-password.php">
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Seu e-mail cadastrado" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Nova Senha</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Nova Senha" required minlength="8">
                        <span class="toggle-password material-icons" onclick="togglePassword('password')">visibility</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar Nova Senha</label>
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirme a Nova Senha" required minlength="8">
                        <span class="toggle-password material-icons" onclick="togglePassword('confirm_password')">visibility</span>
                    </div>
                </div>

                <button type="submit" class="btn">
                    <span class="material-icons">lock_reset</span>
                    Redefinir Senha
                </button>
            </form>
        </div>
    </div>

    <footer>
        <div class="footer-logo">
            <img src="imagens/logo-branco.png" alt="Logo SICEF">
        </div>
        <h3>SICEF - Caderno de Emendas Federais</h3>
        <p>GDF - Governo do Distrito Federal</p>
        <div class="footer-info">
            <p>Anexo do Palácio do Buriti 5º andar, Brasília/DF - CEP: 70075-900</p>
            <p>📧 gab.sucap@economia.gov.df.br | (61) 3314-6213</p>
            <p>&copy; 2025. SEEC/SEFIN/SUCAP/COSP.</p>
        </div>
    </footer>

    <script>
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = passwordInput.nextElementSibling;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = 'visibility';
            }
        }
    </script>
</body>
</html>