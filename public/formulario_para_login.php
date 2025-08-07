<?php
// sicef-caderno-de-emendas/formulario_para_login.php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: /index.php');
    exit;
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
        nav { display: flex; gap: 35px; align-items: center; }
        nav a { color: white; text-decoration: none; font-weight: 600; }
        .hero { background: linear-gradient(90deg, #007b5e, #4db6ac); color: #ffc107; text-align: center; padding: 60px 20px; }
        .hero h1 { margin: 0; font-size: 40px; font-weight: bold; }
        .hero h2 { margin: 10px 0 0; font-size: 28px; color: #ffc107; }
        .login-section { padding: 40px 20px; max-width: 500px; margin: auto; text-align: center; }
        .login-section h3 { font-size: 30px; margin-bottom: 25px; color: #003366; }
        .note { font-size: 12px; color: #003366; margin-bottom: 30px; }
        .btn-option { display: block; width: 100%; max-width: 400px; margin: 20px auto; padding: 25px; background-color: #003366; color: white; text-decoration: none; font-size: 21px; border-radius: 11px; font-weight: bold; }
        footer { background: linear-gradient(90deg, #007b5e, #4db6ac); color: white; text-align: center; padding: 40px 20px; margin-top: 80px; }
        footer img { height: 30px; vertical-align: middle; }
        footer a { color: white; text-decoration: none; }
        .footer-info { margin-top: 30px; font-size: 20px; }
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
        </nav>
    </header>

    <div class="hero">
        <h1>CADERNO DE EMENDAS FEDERAIS</h1>
        <h2>2025</h2>
    </div>

    <div class="login-section">
        <h3>Seja bem-vindo(a)</h3>
        <p class="note">Escolha uma das opções abaixo:</p>

        <a href="https://hesk.gdfnet.df.gov.br/seec_sucap/index.php?a=add" target="_blank" class="btn-option">Solicitar Cadastro</a>
        <a href="reset-password.php" class="btn-option">Já Tenho Cadastro</a>
        <a href="https://www.economia.df.gov.br/caderno-de-emendas-federal" class="btn-option">Download caderno de emendas</a>
    </div>

    <footer>
        <img src="logo.png" alt="Logo SISEF">
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