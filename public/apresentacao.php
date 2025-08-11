<?php
// cicef-caderno-de-emendas/index.php
// Página de apresentação
// Importa as configurações iniciais
session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>CICEF - Caderno de Emendas Federais</title>
  <!-- Importa fontes do Google Fonts, ícones do Material Icons e estilos do Splide -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css">
  <style>
    /* Estilo para o tema */
    :root {
      --primary-color: #00796B;
      --secondary-color: #009688;
      --accent-color: #FFC107;
      --light-color: #ECEFF1;
      --dark-color: #263238;
    }
    /* Estilo para o contêiner */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    /* Scroll suave */
    html {
      scroll-behavior: smooth;
    }

    /* Transição suave para elementos ancorados */
    section {
      scroll-margin-top: 100px; /* Ajuste conforme a altura do seu header */
      transition: scroll-margin-top 0.3s ease;
    }

    @media (prefers-reduced-motion: reduce) {
      html {
        scroll-behavior: auto;
      }
    }

    /* Estilo para o corpo */
    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--light-color);
      color: var(--dark-color);
      line-height: 1.6;
    }
    /* Estilo para o contêiner */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }
    
    /* Header */
    /* Estilo para o cabeçalho */
    header {
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: white;
      padding: 1rem 0;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    /* Estilo para o conteúdo do cabeçalho */
    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    /* Estilo para a logo */
    .logo {
      display: flex;
      align-items: center;
    }
    /* Estilo para a imagem da logo */
    .logo img {
      height: 50px;
      margin-right: 15px;
    }
    /* Estilo para o texto da logo */
    .logo-text h1 {
      font-size: 1.5rem;
      font-weight: 600;
    }

    /* Estilo para a descrição da logo */
    .logo-text p {
      font-size: 0.9rem;
      opacity: 0.9;
    }
    
    /* Navigation */
    nav ul {
      display: flex;
      list-style: none;
      align-items: center; /* Alinha os itens verticalmente */
      justify-content: center; /* Centraliza os itens horizontalmente */
    }

    /* Estilo para os itens de navegação */
    nav ul li {
      margin-left: 15px; /* Reduz a margem para melhor espaçamento */
    }

    /* Links */

    /* Estilo para os links de navegação */
    nav ul li a {
      color: white;
      text-decoration: none;
      font-weight: 400;
      display: flex;
      flex-direction: column; /* Coloca ícone e texto em coluna */
      align-items: center; /* Centraliza horizontalmente */
      justify-content: center; /* Centraliza verticalmente */
      transition: all 0.3s ease;
      padding: 8px 12px;
      border-radius: 4px;
      min-width: 80px; /* Largura mínima para cada item */
      text-align: center; /* Centraliza o texto */
    }

    /* Estilo para os links de navegação ao passar o mouse */
    nav ul li a:hover {
      background-color: rgba(255,255,255,0.2);
    }

    /* Estilo para os ícones de navegação */
    nav ul li a .material-icons {
      margin-right: 0; /* Remove a margem direita */
      font-size: 1.4rem; /* Aumenta  o tamanho do ícone */
      margin-bottom: 4px; /* Espaço entre ícone e texto */
    }

    /* Estilo para o botão de login */
    .login-btn {
      background-color: var(--accent-color);
      color: var(--dark-color);
      font-weight: 600;
      padding: 8px 20px;
      border-radius: 4px;
      transition: all 0.3s ease;
      min-width: auto; /* Sobrescreve a min-width padrão */
      flex-direction: row; /* Volta para linha no botão de login */
    }

    /* Estilo para o botão de login ao passar o mouse */
    .login-btn:hover {
      background-color: #FFD54F;
      transform: translateY(-2px);
    }

    /* Estilo para os ícones do botão de login */
    .login-btn .material-icons {
      margin-right: 5px; /* Restaura margem apenas para o login */
      margin-bottom: 0; /* Remove margem inferior */
    }

    /* Header Bottom */

    /* Estilo para a parte inferior do cabeçalho */
    .header-bottom {
      display: flex; 
      justify-content: space-between;
      align-items: center;
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); /* Gradiente de fundo */
      padding: 1.5rem 0; /* Espaçamento interno */
      border-top: 1px solid rgba(255,255,255,0.1); /* Borda superior */
    }
    /* Descrição do cabeçalho */
    .header-description {
      text-align: center;
      color: white;
      max-width: 800px; 
      margin: 0 auto;
    }
    /* Título da descrição */
    .header-description h2 {
      text-align: center;
      font-size: 1.8rem;
      margin-bottom: 0.5rem;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    }
    /* Descrição da descrição */
    .header-description p {
      font-size: 1.1rem;
      opacity: 0.9;
    }
    
    /* Hero Section */
    .hero {
    height: 400px; /* Altura da seção hero */
    position: relative; /* Posição relativa para o conteúdo sobreposto */
    color: white;
    overflow: hidden; /* Oculta o conteúdo que transborda */
    }
    /* Efeito de sobreposição */
    .hero::before {
    content: ''; /* Cria um pseudo-elemento para o efeito de sobreposição */
    position: absolute; /* Posição absoluta para cobrir toda a seção */
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(15,46,0,0.2); /* Cor de fundo com opacidade */
    z-index: 1; /* Coloca o efeito de sobreposição acima do fundo */
    }

    /* Estilo para o carrossel */
    .splide {
    height: 100%; /* Altura do carrossel */
    }
    /* Estilo para os slides do carrossel */
    .splide__slide {
    background-size: contain;  /* Ajusta o tamanho do fundo */
    background-repeat: no-repeat;
    background-position: center;
    height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
    }
    
    .slide-content {
      position: relative;
      height: 100%;
      width: 100%;
    }
    .slide-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    z-index: 1;
    }

    /* Estilo para o conteúdo da seção hero */
    .hero-content {
      position: relative; /* Posição relativa para o conteúdo sobreposto */
      z-index: 2; /* Coloca o conteúdo acima do efeito de sobreposição */
      width: 100%; 
      padding: 0 20px;
      text-align: center;
      max-width: 800px; /* Limita a largura máxima */
      margin: 0 auto;
    }
    /* Estilo para o título da seção hero */
    .hero h2 {
      font-size: 2.5rem;
      margin-bottom: 20px;
      text-shadow: 1px 1px 3px rgba(0,0,0,0.5); /* Sombra do texto */
      animation: fadeInUp 10s ease; /* Animação de entrada */
    }

    .hero p {
      font-size: 1.2rem;
      max-width: 700px;
      margin: 0 auto 30px;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.5); /* Sombra do texto */
      animation: fadeInUp 1.2s ease; /* Animação de entrada */
    }

    /* Seção de Downloads */
    .download-section {
      background-color: white;
      padding: 60px 0;
      text-align: center;
    }

    .download-section h2 {
      color: var(--primary-color);
      margin-bottom: 30px;
      font-size: 2rem;
    }

    .download-buttons {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 15px;
      max-width: 800px;
      margin: 0 auto;
    }

    .download-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background-color: var(--accent-color);
      color: var(--dark-color);
      padding: 12px 24px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
      min-width: 150px;
    }

    .download-btn:hover {
      background-color: #FFD54F;
      transform: translateY(-3px);
      box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }

    .download-btn .material-icons {
      margin-right: 8px;
      font-size: 1.2rem;
    }

    /* Responsivo */
    @media (max-width: 768px) {
      .download-buttons {
        flex-direction: column;
        align-items: center;
      }
      
      .download-btn {
        width: 100%;
        max-width: 300px;
      }
    }

  /* Animação para o conteúdo */
  /* Efeito de desvanecimento e movimento para cima */
  @keyframes fadeInUp {
    from {
      opacity: 0; /* Início da opacidade */
      transform: translateY(20px); /* Move para baixo */
    }
    to {
      opacity: 1; /* Finaliza a opacidade */
      transform: translateY(0); /* Finaliza a posição */
    }
  }

  /* Estilo para dispositivos móveis */

  /* Ajustes para telas menores */
/* Ajuste para mobile */
@media (max-width: 768px) {
  .hero {
    height: 300px; /* Altura do carrossel da seção hero */
  }
  /* Ajusta o tamanho do texto do título da seção hero */
  .hero h2 {
    font-size: 2rem;
  }
  /* Ajusta o tamanho do texto da descrição da seção hero */
  .hero p {
    font-size: 1rem;
  }
  
  /* Estilo para o cabeçalho */
  .header-content {
    flex-direction: column; /* Alinhamento vertical do cabeçalho */
    text-align: center; /* Alinhamento do texto no cabeçalho ao centro */
  }
  /* Estilo para o logotipo do cabeçalho */
  .logo {
    margin-bottom: 20px;
    justify-content: center;
  }
  /* Estilo para a navegação */
  nav ul {
    flex-direction: column;
    align-items: center;
  }
  /* Estilo para os itens de navegação */
  nav ul li {
    margin: 5px 0; /* Margem vertical dos itens de navegação */
  }
}
    
    /* Main Content */
    main {
      padding: 60px 0; /* Espaçamento superior e inferior do conteúdo principal */
    }
    /* Estilo para o título da seção */
    .section-title {
      text-align: center;
      margin-bottom: 40px;
      color: var(--primary-color); /* Cor do título da seção */
    }
    /* Estilo para o título h2 da seção */
    .section-title h2 {
      font-size: 2rem;
      position: relative; /* Posição relativa para o título */
      display: inline-block; /* Exibição em bloco para o título */
      padding-bottom: 10px; /* Espaçamento inferior para o título */
    }
    /* Estilo para a linha abaixo do título */
    .section-title h2::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%); /* Centraliza a linha abaixo do título */
      width: 80px;
      height: 3px;
      background-color: var(--accent-color); /* Cor da linha abaixo do título */
      transition: width 3s; /* Transição para a largura da linha */
      transition-delay: 0.5s; /* Atraso na transição da linha */
    }
    /* Estilo para o contêiner de cartões */
    .card-container {
      display: grid; /* Exibição em grade para o contêiner de cartões */
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Colunas responsivas */
      gap: 30px; /* Espaçamento entre os cartões */
      margin-top: 40px; /* Espaçamento superior para o contêiner de cartões */
    }
    /* Estilo para os cartões */
    .card {
      background-color: white; /* Cor de fundo dos cartões */
      border-radius: 8px; /* Bordas arredondadas para os cartões */
      overflow: hidden; /* Oculta o conteúdo que transborda */
      box-shadow: 0 5px 15px rgba(0,0,0,0.1); /* Sombra dos cartões */
      transition: transform 0.3s ease, box-shadow 0.3s ease; /* Transição para o efeito de hover */
    }
    /* Efeito de hover para os cartões */
    .card:hover {
      transform: translateY(-10px); /* Move o cartão para cima */
      box-shadow: 0 15px 30px rgba(0,0,0,0.15); /* Aumenta a sombra do cartão */
    }
    /* Estilo para a imagem do cartão */
    .card-img {
      height: 200px; /* Altura da imagem do cartão */
      overflow: hidden; /* Oculta o conteúdo que transborda */
    }
    /* Estilo para a imagem do cartão */
    .card-img img {
      width: 100%; /* Largura da imagem do cartão */
      height: 100%; /* Altura da imagem do cartão */
      object-fit: cover; /* Cobre todo o espaço do cartão */
      transition: transform 0.5s ease; /* Transição para o efeito de zoom */
    }
    /* Efeito de hover para a imagem do cartão */
    .card:hover .card-img img {
      transform: scale(1.1); /* Aumenta a imagem do cartão */
    }
    /* Estilo para o conteúdo do cartão */
    .card-content {
      padding: 20px;
    }
    /* Estilo para o título do conteúdo do cartão */
    .card-content h3 {
      color: var(--primary-color);
      margin-bottom: 10px;
      font-size: 1.3rem;
    }
    
    /* Footer */
    /* Estilo para o rodapé */
    footer {
      background-color: var(--dark-color);
      color: white;
      padding: 40px 0 20px; /* Espaçamento do rodapé */
      text-align: center; /* Alinhamento do texto no rodapé */
    }
    /* Estilo para o logotipo do rodapé */
    .footer-logo img {
      height: 40px; /* Altura do logotipo do rodapé */
      margin-bottom: 20px; /* Margem inferior do logotipo do rodapé */
    }
    /* Estilo para as informações do rodapé */
    .footer-info {
      margin-bottom: 20px; /* Margem inferior das informações do rodapé */
    }
    /* Estilo para os parágrafos das informações do rodapé */
    .footer-info p {
      margin-bottom: 5px;
    }
    /* Estilo para o copyright */
    .copyright {
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid rgba(255,255,255,0.1);
      font-size: 0.9rem;
      opacity: 0.8; /* Opacidade do copyright */
    }
  </style>
</head>
<body>
  <!-- Cabeçalho -->
  <header>
    <div class="container header-content">
      <!-- Logotipo cabeçalho -->
      <div class="logo">
        <a href="apresentacao.php">
          <img src="imagens/logo.png" alt="Logo CICEF">
        </a>
        <div class="logo-text">
          <!-- <h1>CICEF</h1> -->
          <!-- <p>Caderno de Emendas Federais</p> -->
        </div>
      </div>
      <!-- Navegação -->
      <nav>
        <!-- Menu de navegação -->
        <ul>
          <!-- Item de navegação -->
          <li><a href="#downloads"><span class="material-icons">download</span> Download dos Cadernos</a></li>
          <li><a href="#apresentacao"><span class="material-icons">info</span> Apresentação</a></li>
          <li><a href="#objetivos"><span class="material-icons">flag</span> Objetivos</a></li>
          <li><a href="#oque-e"><span class="material-icons">book</span> O que é o Caderno de Emendas</a></li>
          <li><a href="#tipos"><span class="material-icons">layers</span> Tipos de Emendas</a></li>
          <li><a href="formulario_para_login.php" class="login-btn"><span class="material-icons">login</span> Login</a></li>
        </ul>
      </nav>
    </div>

    <!-- Parte inferior do cabeçalho -->
    <div class="header-bottom">
      <!-- Descrição do cabeçalho -->
      <div class="container">
        <!-- Texto descritivo do cabeçalho -->
        <div class="header-description">
          <!-- Título do cabeçalho -->
          <h2>Sistema de Gestão de Emendas Federais</h2>
          <p>Centralize, organize e gerencie as propostas de emendas parlamentares de forma eficiente e transparente</p>
        </div>
      </div>
    </div>
  </header>

  <!-- Seção Hero -->
<section class="hero">
  <!-- Carrossel da seção hero -->
  <div class="splide" id="hero-carousel">
    <div class="splide__track">
      <ul class="splide__list">
        <!-- Slide 1 - 2025 -->
        <li class="splide__slide">
          <div class="slide-content">
            <div class="slide-image" style="background-image: url('imagens/caderno_emendas_2025.png')"></div>
          </div>
        </li>
        
        <!-- Slide 2 - 2024 -->
        <li class="splide__slide">
          <div class="slide-content">
            <div class="slide-image" style="background-image: url('imagens/caderno_emendas_2024.png')"></div>
          </div>
        </li>
        
        <!-- Slide 3 - 2023 -->
        <li class="splide__slide">
          <div class="slide-content">
            <div class="slide-image" style="background-image: url('imagens/caderno_emendas_2023.png')"></div>
          </div>
        </li>
        <!-- Slide 4 - 2022 -->
        <li class="splide__slide">
          <div class="slide-content">
            <div class="slide-image" style="background-image: url('imagens/caderno_emendas_2022.png')"></div>
          </div>
        </li>
        <!-- Slide 5 - 2021 -->
        <li class="splide__slide">
          <div class="slide-content">
            <div class="slide-image" style="background-image: url('imagens/caderno_emendas_2021.png')"></div>
          </div>
        </li>
        <!-- Slide 6 - 2020 -->
        <li class="splide__slide">
          <div class="slide-content">
            <div class="slide-image" style="background-image: url('imagens/caderno_emendas_2020.png')"></div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</section>

<!-- Seção de Downloads -->
<section id="downloads" class="download-section">
  <div class="container">
    <h2>Downloads dos Cadernos de Emendas</h2>
    <div class="download-buttons">
      <a href="https://www.economia.df.gov.br/documents/d/seec/index-pdf-5" target="_blank" class="download-btn" download>
        <span class="material-icons">download</span>
        Caderno 2025
      </a>
      <a href="https://www.economia.df.gov.br/documents/d/seec/controlador-php_-1-pdf-7" target="_blank" class="download-btn" download>
        <span class="material-icons">download</span>
        Caderno 2024
      </a>
      <a href="https://www.economia.df.gov.br/documents/d/seec/emendas-federais-2023-pdf" target="_blank" class="download-btn" download>
        <span class="material-icons">download</span>
        Caderno 2023
      </a>
      <a href="https://www.economia.df.gov.br/documents/d/seec/caderno-de-emendas-federais-2022-com-capa-pdf" target="_blank" class="download-btn" download>
        <span class="material-icons">download</span>
        Caderno 2022
      </a>
      <a href="https://www.economia.df.gov.br/documents/d/seec/caderno-de-emendas-federais-2021-8-pdf" target="_blank" class="download-btn" download>
        <span class="material-icons">download</span>
        Caderno 2021
      </a>
      <a href="https://www.economia.df.gov.br/documents/d/seec/caderno-de-emendas-federal-2020-pdf" target="_blank"class="download-btn" download>
        <span class="material-icons">download</span>
        Caderno 2020
      </a>
    </div>
  </div>
</section>

  <!-- Main Content -->
  <main class="container">
    <!-- Seção de Apresentação -->
    <section id="apresentacao">
      <div class="section-title">
        <h2>Apresentação</h2>
      </div>
      <!-- Descrição da seção de apresentação -->
      <div class="card-container">
        <div class="card">
          <!-- Imagem do cartão -->
          <div class="card-img">
            <img src="imagens/sistema.jpg" alt="Sistema CICEF">
          </div>
          <!-- Conteúdo do cartão -->
          <div class="card-content">
            <h3>Sobre o Sistema</h3>
            <p>O CICEF é uma plataforma desenvolvida para centralizar e organizar as propostas de emendas parlamentares, proporcionando maior controle e transparência na alocação de recursos públicos.</p>
          </div>
        </div>
      </div>
    </section>
    <!-- Seção de Objetivos -->
    <section id="objetivos">
      <div class="section-title">
        <h2>Objetivos</h2>
      </div>
      <!-- Descrição da seção de objetivos -->
      <div class="card-container">
        <!-- Card 1 -->
        <div class="card">
          <!-- Imagem do cartão -->
          <div class="card-img">
            <img src="imagens/transparencia.jpg" alt="Transparência">
          </div>
          <!-- Conteúdo do cartão -->
          <div class="card-content">
            <h3>Transparência</h3>
            <p>Garantir visibilidade total do processo de alocação de recursos através de emendas parlamentares.</p>
          </div>
        </div>
        <!-- Card 2 -->
        <div class="card">
          <div class="card-img">
            <img src="imagens/eficiencia.jpg" alt="Eficiência">
          </div>
          <div class="card-content">
            <h3>Eficiência</h3>
            <p>Otimizar o processo de análise e aprovação de emendas, reduzindo burocracia e tempo de resposta.</p>
          </div>
        </div>
        <!-- Card 3 -->
        <div class="card">
          <div class="card-img">
            <img src="imagens/controle.jpg" alt="Controle">
          </div>
          <div class="card-content">
            <h3>Controle</h3>
            <p>Oferecer ferramentas para monitoramento e acompanhamento da execução das emendas aprovadas.</p>
          </div>
        </div>
      </div>
    </section>
    <!-- Seção de O que é -->
    <section id="oque-e">
      <div class="section-title">
        <h2>O que é o Caderno de Emendas</h2>
      </div>
      <!-- Descrição da seção de O que é -->
      <div class="card-container">
        <div class="card">
          <div class="card-img">
            <img src="imagens/caderno-emendas.jpg" alt="Caderno de Emendas">
          </div>
          <!-- Conteúdo do cartão -->
          <div class="card-content">
            <h3>Caderno de Emendas</h3>
            <p>Instrumento de gestão pública que consolida todas as demandas para destinação de recursos via emendas parlamentares, servindo como referência para gestores e parlamentares.</p>
          </div>
        </div>
      </div>
    </section>
    <!-- Seção de Tipos -->
    <section id="tipos">
      <div class="section-title">
        <h2>Tipos de Emendas</h2>
      </div>
      <!-- Descrição da seção de Tipos -->
      <div class="card-container">
        <!-- Card 1 -->
        <div class="card">
          <div class="card-img">
            <img src="imagens/emenda_individual.png" alt="Emendas Individuais">
          </div>
          <div class="card-content">
            <h3>Individuais</h3>
            <p>Propostas por parlamentares individualmente, destinadas a projetos específicos de sua escolha.</p>
          </div>
        </div>
        <!-- Card 2 -->
        <div class="card">
          <div class="card-img">
            <img src="imagens/emenda_de_bancada.png" alt="Emendas de Bancada">
          </div>
          <div class="card-content">
            <h3>De Bancada</h3>
            <p>Propostas coletivas por estado, representando as prioridades conjuntas dos parlamentares.</p>
          </div>
        </div>
        <!-- Card 3 -->
        <div class="card">
          <div class="card-img">
            <img src="imagens/emenda_de_comissao.png" alt="Emendas de Comissão">
          </div>
          <div class="card-content">
            <h3>De Comissão</h3>
            <p>Elaboradas pelas comissões técnicas do Congresso, com foco em áreas específicas de atuação.</p>
          </div>
        </div>
      </div>
    </section>
  </main>
  <!-- Seção de Contato -->
  <footer>
    <div class="container">
      <!-- Logo -->
      <div class="footer-logo">
        <img src="imagens/logo.png" alt="Logo CICEF">
      </div>
      <!-- Informações de contato -->
      <div class="footer-info">
        <p>Secretaria de Estado de Economia</p>
        <p>Anexo do Palácio do Buriti, 5º andar, Brasília/DF</p>
        <p>CEP: 70075-900 | Telefone: (61) 3314-6213</p>
        <p>E-mail: cicef@economia.gov.df.br</p>
      </div>
      <!-- Direitos autorais -->
      <div class="copyright">
        <p>&copy; 2025 CICEF - Caderno de Emendas Federais. Todos os direitos reservados.</p>
      </div>
    </div>
  </footer>
</body>
<!-- Scripts -->

<!-- Importa o Splide.js -->
<script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js"></script>
<!-- Inicializa o carrossel -->
<script>
  // Aguarda o carregamento do DOM
  document.addEventListener('DOMContentLoaded', function() {
    new Splide('#hero-carousel', {
      type: 'fade', // Efeito de fade entre slides
      rewind: true, // Volta ao primeiro slide após o último
      autoplay: true, // Reprodução automática
      interval: 5000, // Intervalo de 5 segundos
      speed: 1000, // Tempo de transição
      pauseOnHover: false, // Não pausa ao passar o mouse
      arrows: false, // Remove as setas de navegação
      pagination: false // Remove os pontos de navegação
    }).mount(); // Monta o carrossel

        // Adiciona offset para o scroll suave (considerando o header fixo)
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
          const headerHeight = document.querySelector('header').offsetHeight;
          const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight;
          
          window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
          });
          
          // Atualiza a URL sem recarregar a página
          history.pushState(null, null, targetId);
        }
      });
    });
  });
</script>
</html>