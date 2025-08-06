<?php
session_start();
require_once __DIR__ . '/../models/User.php';

$error = '';
$primeiro_acesso = isset($_GET['primeiro_acesso']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    $userModel = new User();
    $user = $userModel->findByEmail($email);

    if ($user && password_verify($password, $user['senha'])) {
        $_SESSION['user'] = $user;
        header('Location: home.php');
        exit;
    } else {
        $error = 'Credenciais inválidas. Tente novamente.';
    }
} 
?>

<!-- estrutura do HTML para o login -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Caderno de Emendas Federais</title>
    <!-- Importando o Bootstrap para estilização -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .login-container {
            max-width: 500px;
            margin: 5rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .form-header h1 {
            color: #2c3e50;
            font-weight: 700;
        }
        .form-header p {
            color: #7f8c8d;
        }
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="form-header">
                <h1>CADERNO DE EMENDAS FEDERAIS</h1>
                <p>2025</p>
                <?php if($primeiro_acesso): ?>
                    <p class="text-success">Primeiro acesso: Defina sua nova senha</p>
                <?php else: ?>
                    <p>Tela caso seja o primeiro acesso. <a href="login.php?primeiro_acesso=true">Redefinir senha</a></p>
                <?php endif; ?>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="dep.nome@camara.leg.br" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label" for="terms">
                        Concordo com os Termos e a Política de Privacidade
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Acessar</button>
                
                <div class="mt-3 text-center">
                    <p>Não possui cadastro? <a href="#">Solicite</a></p>
                </div>
            </form>
        </div>
        
        <div class="footer">
            <p><strong>GDF - Governo do Distrito Federal</strong><br>
            Anexo do Palácio do Buriti 5º andar, Brasília/DF - CEP: 70075-900<br>
            email@economia.df.gov.br | (61) 3414-9213</p>
            <p>Copyright © 2025. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>