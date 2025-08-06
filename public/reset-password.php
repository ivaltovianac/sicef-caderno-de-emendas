<?php
session_start();
require_once __DIR__ . '/../models/User.php';

$error = '';
$sucess = '';

if ($_SERVER[REQUEST_METHOD] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if($password !== $confirm_password) {
        $error = 'As senhas não coincidem.';
    } else {
        $userModel = new User();
        if ($userModel->updatePassword($email, $password)) {
            $sucess = 'Senha redefinida com sucesso. Você pode fazer login agora.';
        } else {
            $error = 'Erro ao redefinir a senha. Tente novamente.';
        }
    }
}
?>

// estrutura do HTML para redefinição de senha
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Caderno de Emendas Federais</title>
    <!-- Importando o Bootstrap para estilização -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .reset-container {
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
        <div class="reset-container">
            <div class="form-header">
                <h1>CADERNO DE EMENDAS FEDERAIS</h1>
                <p>2025</p>
                <p>Redefinição de Senha Após Primeiro Acesso</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="dep.nome@camara.leg.br" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Nova Senha</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirmar Senha</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Redefinir Senha</button>
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