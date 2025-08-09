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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo - Caderno de Emendas Federais 2025</title>
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
        
        .hero {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: var(--accent-color);
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .hero h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .hero h2 {
            margin: 0.5rem 0 0;
            font-size: 1.8rem;
            font-weight: 500;
        }
        
        .options-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 3rem 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .options-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .options-header h3 {
            color: var(--dark-color);
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .options-header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .btn-option {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            width: 100%;
            max-width: 500px;
            padding: 1.2rem;
            margin: 1rem 0;
            background: var(--dark-color);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: 500;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-option:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .btn-option:active {
            transform: translateY(0);
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
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero h2 {
                font-size: 1.5rem;
            }
            
            .options-header h3 {
                font-size: 1.5rem;
            }
            
            .btn-option {
                font-size: 1rem;
                padding: 1rem;
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
            <a href="apresentacao.php"><span class="material-icons">info</span> Sobre</a>
            <a href="mailto:gab.sucap@economia.gov.df.br"><span class="material-icons">email</span> Contato</a>
        </nav>
    </header>

    <div class="hero">
        <h1>CADERNO DE EMENDAS FEDERAIS</h1>
        <h2>2025</h2>
    </div>

    <div class="options-container">
        <div class="options-header">
            <h3>Seja bem-vindo(a)</h3>
            <p>Escolha uma das opções abaixo:</p>
        </div>

        <a href="https://hesk.gdfnet.df.gov.br/seec_sucap/index.php?a=add" target="_blank" class="btn-option">
            <span class="material-icons">person_add</span>
            Solicitar Cadastro
        </a>
        
        <a href="login.php" class="btn-option">
            <span class="material-icons">login</span>
            Já Tenho Cadastro
        </a>
        
        <a href="https://www.economia.df.gov.br/caderno-de-emendas-federal" class="btn-option">
            <span class="material-icons">download</span>
            Download caderno de emendas
        </a>
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
</body>
</html>