<?php
/**
 *  SICEF-caderno-de-emendas/index.php
 * Página de apresentação do sistema SICEF - Caderno de Emendas Federais
 * Inicia a sessão para controle de usuário e estado da aplicação
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */
session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>SICEF - Caderno de Emendas Federais</title>
  <!-- Importação de fontes Google Fonts para ícones e tipografia -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <!-- Estilos do carrossel Splide -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css">
  <!-- Bootstrap CSS para responsividade e componentes -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Definição das cores principais do tema para fácil manutenção */
    :root {
      --primary-color: #00796B;
      --secondary-color: #009688;
      --accent-color: #FFC107;
      --light-color: #ECEFF1;
      --dark-color: #263238;
    }

    /* Reset básico de margens, padding e box-sizing para consistência */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    /* Scroll suave para navegação interna */
    html {
      scroll-behavior: smooth;
    }

    /* Ajuste da margem superior para seções ancoradas, considerando altura do header */
    section {
      scroll-margin-top: 100px;
      transition: scroll-margin-top 0.3s ease;
    }

    /* Respeita preferência do usuário por redução de animações */
    @media (prefers-reduced-motion: reduce) {
      html {
        scroll-behavior: auto;
      }
    }

    /* Estilo base do corpo da página */
    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--light-color);
      color: var(--dark-color);
      line-height: 1.6;
    }

    /* Container centralizado com largura máxima e padding lateral */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    /* Estilo do cabeçalho com gradiente e sombra para destaque */
    header {
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: white;
      padding: 1rem 0;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    /* Layout flexível para conteúdo do header, com responsividade */
    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
    }

    /* Logo alinhada verticalmente */
    .logo {
      display: flex;
      align-items: center;
    }

    /* Tamanho e espaçamento da imagem da logo */
    .logo img {
      height: 50px;
      margin-right: 15px;
    }

    /* Menu de navegação com espaçamento e alinhamento */
    .nav-menu {
      display: flex;
      gap: 1rem;
      align-items: center;
      flex-wrap: wrap;
    }

    /* Estilo dos links do menu */
    .nav-menu a {
      color: white;
      text-decoration: none;
      padding: 0.5rem 1rem;
      border-radius: 5px;
      transition: background-color 0.3s;
    }

    /* Efeito hover nos links do menu */
    .nav-menu a:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }

    /* Estilo específico para botão primário no menu */
    .nav-menu .btn-primary {
      background-color: #FFB300;
      color: white;
      border: none;
      border-radius: 10px;
      padding: 0.5rem 1.5rem;
      font-weight: 600;
      white-space: nowrap;
      transition: background-color 0.3s, transform 0.3s;
      width: auto;
    }

    /* Efeito hover para o botão primário */
    .nav-menu .btn-primary:hover {
      background-color: #FFB300;
      color: #263238;
      transform: translateY(-2px);
    }

    /* Estilo base para botões primários */
    .btn-primary {
      background-color: var(--accent-color);
      border: none;
      color: var(--dark-color);
      font-weight: 600;
    }

    /* Hover para botões primários */
    .btn-primary:hover {
      background-color: #FFB300;
      color: var(--dark-color);
    }

    /* Responsividade para telas menores que 768px */
    @media (max-width: 768px) {
      .header-content {
        flex-direction: column;
        gap: 1rem;
      }

      .nav-menu {
        justify-content: center;
        width: 100%;
      }
    }

    /* Estilo da seção hero/início com gradiente e texto centralizado */
    .hero {
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: white;
      padding: 4rem 0;
      text-align: center;
    }

    /* Título principal da hero */
    .hero h2 {
      font-size: 2.5rem;
      margin-bottom: 1rem;
      font-weight: 600;
    }

    /* Parágrafo da hero com opacidade para suavizar */
    .hero p {
      font-size: 1.2rem;
      margin-bottom: 2rem;
      opacity: 0.9;
    }

    /* Parágrafo dentro dos itens do carrossel */
    .carousel-item p {
      text-align: center;
      padding: 0.2rem 0;
      font-size: 1.5rem;
    }

    /* Container para botões de chamada para ação */
    .cta-buttons {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    /* Estilo base para botões */
    .btn {
      padding: 0.75rem 2rem;
      border-radius: 25px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    /* Botões com borda branca e fundo transparente */
    .btn-outline {
      border: 2px solid white;
      color: white;
      background: transparent;
    }

    /* Hover para botões outline */
    .btn-outline:hover {
      background: white;
      color: var(--primary-color);
    }

    /* Seção de recursos com padding e fundo branco */
    .features {
      padding: 4rem 0;
      background: white;
    }

    /* Título da seção recursos */
    .features h2 {
      text-align: center;
      margin-bottom: 3rem;
      color: var(--primary-color);
      font-size: 2rem;
    }

    /* Grid responsivo para os recursos */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
    }

    /* Estilo individual para cada recurso */
    .feature {
      text-align: center;
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s;
      animation: fadeInUp 0.6s ease-out;
    }

    /* Efeito hover para levantar o card */
    .feature:hover {
      transform: translateY(-5px);
    }

    /* Ícone do recurso com tamanho e cor */
    .feature-icon {
      font-size: 3rem;
      color: var(--secondary-color);
      margin-bottom: 1rem;
    }

    /* Título do recurso */
    .feature h3 {
      color: var(--primary-color);
      margin-bottom: 1rem;
    }

    /* Seção de estatísticas com fundo claro */
    .stats {
      background: var(--light-color);
      padding: 4rem 0;
    }

    /* Grid para estatísticas */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 2rem;
      text-align: center;
    }

    /* Card individual de estatística */
    .stat {
      background: white;
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    /* Número da estatística com destaque */
    .stat-number {
      font-size: 2.5rem;
      font-weight: 600;
      color: var(--secondary-color);
      display: block;
    }

    /* Label da estatística */
    .stat-label {
      color: var(--dark-color);
      margin-top: 0.5rem;
    }

    /* Estilo do rodapé com fundo escuro */
    footer {
      background: var(--dark-color);
      color: white;
      padding: 3rem 0 1rem;
    }

    /* Conteúdo do footer em grid responsivo */
    .footer-content {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 2rem;
      margin-bottom: 2rem;
    }

    /* Títulos das seções do footer */
    .footer-section h3 {
      color: var(--accent-color);
      margin-bottom: 1rem;
    }

    /* Links do footer com estilo e hover */
    .footer-section a {
      color: white;
      text-decoration: none;
      display: block;
      margin-bottom: 0.5rem;
      transition: color 0.3s;
    }

    .footer-section a:hover {
      color: var(--accent-color);
    }

    /* Rodapé inferior com borda e alinhamento central */
    .footer-bottom {
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      padding-top: 1rem;
      text-align: center;
      opacity: 0.8;
    }

    /* Estilo do modal com bordas arredondadas */
    .modal-content {
      border-radius: 15px;
      border: none;
    }

    /* Cabeçalho do modal com gradiente e cor branca */
    .modal-header {
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: white;
      border-radius: 15px 15px 0 0;
    }

    /* Botão de fechar modal com filtro invertido para visibilidade */
    .modal-header .btn-close {
      filter: invert(1);
    }

    /* Animação para fade in e subida dos elementos */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Responsividade para telas menores */
    @media (max-width: 768px) {
      .hero h2 {
        font-size: 2rem;
      }

      .hero p {
        font-size: 1rem;
      }

      .cta-buttons {
        flex-direction: column;
        align-items: center;
      }

      .features-grid {
        grid-template-columns: 1fr;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 480px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <!-- Cabeçalho principal com logo e menu de navegação -->
  <header>
    <div class="container">
      <div class="header-content">
        <div class="logo">
          <a href="apresentacao.php"><img src="imagens/logo.svg" alt="SICEF Logo" /></a>
        </div>
        <!-- Menu de navegação com links para seções da página e login -->
        <nav class="nav-menu">
          <a href="#inicio">Início</a>
          <a href="#recursos">Recursos</a>
          <a href="#adicional_info">Informações</a>
          <a href="#emendas"
            style="color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 5px; transition: background-color 0.3s;">Sobre
            Emendas</a>
          <a href="#contato">Contato</a>
          <a href="login.php" class="btn btn-primary">
            <span class="material-icons">login</span>
            Entrar</a>
        </nav>
      </div>
    </div>
  </header>

  <!-- Seção principal de apresentação do sistema -->
  <section id="inicio" class="hero">
    <div class="container">
      <h2>Sistema de Caderno de Emendas Federais</h2>
      <p>
        <span>Gerencie Emendas Federais com eficiência</span><br>
        Sistema completo para deputados e senadores organizarem e acompanharem suas emendas parlamentares
      </p>
      <div class="cta-buttons">
        <!-- Botão para solicitar acesso ao sistema -->
        <a href="solicitar_acesso.php" class="btn btn-primary">
          <span class="material-icons">person_add</span>
          Solicitar Acesso
        </a>
        <!-- Botão para abrir modal com informações do sistema -->
        <button type="button" class="btn btn-outline" data-bs-toggle="modal" data-bs-target="#infoModal">
          <span class="material-icons">help</span>
          Informações
        </button>

        <!-- Botão para abrir modal de download dos cadernos de emendas -->
        <button type="button" class="btn btn-outline" data-bs-toggle="modal" data-bs-target="#downloadModal">
          <span class="material-icons">download</span>
          Download de Emendas
        </button>

        <!-- Modal para download dos cadernos de emendas federais -->
        <div class="modal fade" id="downloadModal" tabindex="-1" aria-labelledby="downloadModalLabel"
          aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="downloadModalLabel">
                  <span class="material-icons me-2">download</span>
                  Download dos Cadernos de Emendas
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="d-flex flex-wrap gap-3 justify-content-center">
                  <!-- Links para download dos cadernos de diferentes anos -->
                  <a href="https://www.economia.df.gov.br/documents/d/seec/index-pdf-5" class="btn btn-primary"
                    target="_blank">Caderno 2025</a>
                  <a href="https://www.economia.df.gov.br/documents/d/seec/controlador-php_-1-pdf-7"
                    class="btn btn-primary" target="_blank">Caderno 2024</a>
                  <a href="https://www.economia.df.gov.br/documents/d/seec/emendas-federais-2023-pdf"
                    class="btn btn-primary" target="_blank">Caderno 2023</a>
                  <a href="https://www.economia.df.gov.br/documents/d/seec/caderno-de-emendas-federais-2022-com-capa-pdf"
                    class="btn btn-primary" target="_blank">Caderno 2022</a>
                  <a href="https://www.economia.df.gov.br/documents/d/seec/caderno-de-emendas-federais-2021-8-pdf"
                    class="btn btn-primary" target="_blank">Caderno 2021</a>
                  <a href="https://www.economia.df.gov.br/documents/d/seec/caderno-de-emendas-federal-2020-pdf"
                    class="btn btn-primary" target="_blank">Caderno 2020</a>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Seção do carrossel de imagens dos cadernos -->
  <section id="carrossel" class="container-fluid my-5">
    <div id="courserlIndicators" class="carousel slide" data-bs-ride="carousel">
      <!-- Indicadores para navegação entre slides -->
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#courserlIndicators" data-bs-slide-to="0" class="active"
          aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#courserlIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#courserlIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
        <button type="button" data-bs-target="#courserlIndicators" data-bs-slide-to="3" aria-label="Slide 4"></button>
        <button type="button" data-bs-target="#courserlIndicators" data-bs-slide-to="4" aria-label="Slide 5"></button>
        <button type="button" data-bs-target="#courserlIndicators" data-bs-slide-to="5" aria-label="Slide 5"></button>
      </div>
      <!-- Conteúdo dos slides -->
      <div class="carousel-inner">
        <div class="carousel-item active">
          <p>Caderno 2025</p>
          <img src="imagens/caderno_emendas_2025.png" class="d-block w-100" alt="Slide 1"
            style="max-height: 500px; object-fit: cover; width: 10%;">
        </div>
        <div class="carousel-item">
          <p>Caderno 2024</p>
          <img src="imagens/caderno_emendas_2024.png" class="d-block w-100" alt="Slide 2"
            style="max-height: 500px; object-fit: cover; width: 100%;">
        </div>
        <div class="carousel-item">
          <p>Caderno 2023</p>
          <img src="imagens/caderno_emendas_2023.png" class="d-block w-100" alt="Slide 3"
            style="max-height: 500px; object-fit: cover; width: 100%;">
        </div>
        <div class="carousel-item">
          <p>Caderno 2022</p>
          <img src="imagens/caderno_emendas_2022.png" class="d-block w-100" alt="Slide 3"
            style="max-height: 500px; object-fit: cover; width: 100%;">
        </div>
        <div class="carousel-item">
          <p>Caderno 2021</p>
          <img src="imagens/caderno_emendas_2021.png" class="d-block w-100" alt="Slide 3"
            style="max-height: 500px; object-fit: cover; width: 100%;">
        </div>
        <div class="carousel-item">
          <p>Caderno 2020</p>
          <img src="imagens/caderno_emendas_2020.png" class="d-block w-100" alt="Slide 3"
            style="max-height: 500px; object-fit: cover; width: 100%;">
        </div>
      </div>
      <!-- Controles para navegação anterior/próximo -->
      <button class="carousel-control-prev" type="button" data-bs-target="#courserlIndicators" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Anterior</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#courserlIndicators" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Próximo</span>
      </button>
    </div>
  </section>

  <!-- Seção de recursos do sistema -->
  <section id="recursos" class="features">
    <div class="container">
      <h2>Recursos do Sistema</h2>
      <div class="features-grid">
        <!-- Recurso: Dashboard Intuitivo -->
        <div class="feature">
          <div class="feature-icon">
            <span class="material-icons">dashboard</span>
          </div>
          <h3>Dashboard Intuitivo</h3>
          <p>Visualize todas as suas emendas de forma organizada e clara, com filtros avançados e relatórios detalhados.
          </p>
        </div>

        <!-- Recurso: Busca Avançada -->
        <div class="feature">
          <div class="feature-icon">
            <span class="material-icons">search</span>
          </div>
          <h3>Busca Avançada</h3>
          <p>Encontre rapidamente emendas por tipo, eixo temático, órgão, valor ou qualquer outro critério.</p>
        </div>

        <!-- Recurso: Sincronização Automática -->
        <div class="feature">
          <div class="feature-icon">
            <span class="material-icons">sync</span>
          </div>
          <h3>Sincronização Automática</h3>
          <p>Dados sempre atualizados com sincronização automática das planilhas oficiais do governo.</p>
        </div>
      </div>
  </section>

  <!-- Seção de objetivos e explicações sobre o caderno de emendas -->
  <section id="emendas" class="features">
    <div class="container">
      <h2 class="text-center" style="color: var(--primary-color);">Objetivos</h2>
      <div class="features-grid">
        <!-- Objetivo: Transparência -->
        <div class="feature text-center">
          <img src="imagens/transparencia.jpg" alt="Transparência" class="mb-3" style="width: 80px; height: 80px;">
          <h3>Transparência</h3>
          <p>Garantir visibilidade total do processo de alocação de recursos através de emendas parlamentares.</p>
        </div>
        <!-- Objetivo: Eficiência -->
        <div class="feature text-center">
          <img src="imagens/eficiencia.jpg" alt="Eficiência" class="mb-3" style="width: 80px; height: 80px;">
          <h3>Eficiência</h3>
          <p>Otimizar o processo de análise e aprovação de emendas, reduzindo burocracia e tempo de resposta.</p>
        </div>
        <!-- Objetivo: Controle -->
        <div class="feature text-center">
          <img src="imagens/controle.jpg" alt="Controle" class="mb-3" style="width: 80px; height: 80px;">
          <h3>Controle</h3>
          <p>Oferecer ferramentas para monitoramento e acompanhamento da execução das emendas aprovadas.</p>
        </div>
      </div>

      <!-- Explicação sobre o que é o caderno de emendas -->
      <h2 class="text-center my-4" style="color: var(--primary-color);">O que é o Caderno de Emendas?</h2>
      <p class="text-center">Instrumento de gestão pública que consolida todas as demandas para destinação de recursos
        via emendas parlamentares, servindo como referência para gestores e parlamentares.</p>

      <!-- Tipos de emendas com imagens e descrições -->
      <h2 class="text-center my-4" style="color: var(--primary-color);">Tipos de Emendas</h2>
      <div class="features-grid">
        <!-- Emendas Individuais -->
        <div class="feature text-center">
          <img src="imagens/emenda_individual.png" alt="Individuais" class="mb-3" style="width: 80px; height: 80px;">
          <h3>Individuais</h3>
          <p>Individuais - transferências com finalidade definida: propostas por cada parlamentar, possuem recursos
            vinculados à programação estabelecida na emenda parlamentar e aplicados nas áreas de competência
            constitucional da União.</p>
          <p>Individuais - transferências especiais: aquelas que alocam recursos orçamentários para estados, municípios
            e Distrito Federal (sem a necessidade de celebração de convênio ou instrumento congênere).
          </p>
        </div>
        <!-- Emendas de Bancada -->
        <div class="feature text-center">
          <img src="imagens/emenda_de_bancada.png" alt="De Bancada" class="mb-3" style="width: 80px; height: 80px;">
          <h3>De Bancada</h3>
          <p>De autoria das bancadas estaduais no Congresso Nacional relativa a matérias de interesse de cada
            Estado ou do Distrito Federal.</p>
        </div>
        <!-- Emendas de Comissão -->
        <div class="feature text-center">
          <img src="imagens/emenda_de_comissao.png" alt="De Comissão" class="mb-3" style="width: 80px; height: 80px;">
          <h3>De Comissão</h3>
          <p>Comissão: apresentadas pelas comissões técnicas da Câmara e do Senado, bem como as propostas pelas Mesas
            Diretoras das duas Casas.
          </p>
        </div>
        <!-- Emendas de Relator -->
        <div class="feature text-center">
          <img src="imagens/emenda_de_comissao.png" alt="De Comissão" class="mb-3" style="width: 80px; height: 80px;">
          <h3>De Relator</h3>
          <p>De autoria do deputado ou senador que, naquele determinado ano, foi escolhido para produzir o
            parecer final (relatório geral) sobre o Orçamento. Há ainda as emendas dos relatores setoriais, destacados
            para dar parecer sobre assuntos específicos divididos em dez áreas temáticas do orçamento.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Seção com informações adicionais sobre o sistema -->
  <section id="adicional_info" class="features">
    <div class="container">
      <h2>Informações Adicionais</h2>
      <div class="features-grid">
        <div class="feature">
          <p>Este sistema foi desenvolvido para facilitar o acompanhamento e a gestão das emendas parlamentares,
            garantindo maior transparência e eficiência no processo.</p>
          <p>Ele oferece funcionalidades avançadas como controle detalhado, exportação de relatórios, alertas
            personalizados e integração com sistemas oficiais.</p>
          <p>Além disso, proporciona benefícios diretos para parlamentares, como interface amigável e ferramentas que
            facilitam a gestão das emendas.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Seção de estatísticas com números animados -->
  <section class="stats">
    <div class="container">
      <div class="stats-grid">
        <div class="stat">
          <span class="stat-number">1000+</span>
          <div class="stat-label">Emendas Cadastradas</div>
        </div>
        <div class="stat">
          <span class="stat-number">50+</span>
          <div class="stat-label">Parlamentares Ativos</div>
        </div>
        <div class="stat">
          <span class="stat-number">R$ 2B+</span>
          <div class="stat-label">Valor Total Gerenciado</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Modal informativo com detalhes para parlamentares e administradores -->
  <div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="infoModalLabel">
            <span class="material-icons me-2">info</span>
            Informações do Sistema SICEF
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <h6><span class="material-icons me-1">account_circle</span> Para Parlamentares</h6>
              <ul class="list-unstyled ms-3">
                <li>• Acesso completo às suas emendas</li>
                <li>• Relatórios personalizados</li>
                <li>• Acompanhamento de valores</li>
                <li>• Sistema de sugestões</li>
              </ul>
            </div>
            <div class="col-md-6">
              <h6><span class="material-icons me-1">admin_panel_settings</span> Para Administradores</h6>
              <ul class="list-unstyled ms-3">
                <li>• Gestão completa do sistema</li>
                <li>• Controle de usuários</li>
                <li>• Sincronização de dados</li>
                <li>• Relatórios gerenciais</li>
              </ul>
            </div>
          </div>
          <hr>
          <div class="text-center">
            <h6><span class="material-icons me-1">security</span> Segurança e Privacidade</h6>
            <p class="text-muted">
              Todos os dados são protegidos com criptografia de ponta e armazenados em servidores seguros.
              O acesso é controlado e auditado para garantir a integridade das informações parlamentares.
            </p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          <a href="solicitar_acesso.php" class="btn btn-primary">Solicitar Acesso</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Rodapé com informações de contato, links úteis e sobre o sistema -->
  <footer id="contato">
    <div class="container">
      <div class="footer-content">
        <div class="footer-section">
          <h3>Contato</h3>
          <p>Secretaria de Estado de Economia</p>
          <p>Email: <a href="mailto:cosp@economia.gov.df.br">cosp@economia.gov.df.br</a></p>
          <p>Telefone: <a href="tel:+55(61)3314-6213">(61) 3314-6213</a></p>
          <p>Anexo do Palácio do Buriti, 5º andar, Brasília/DF | CEP: 70075-900</p>
          <p>Horário de atendimento: Segunda a Sexta, 8h às 18h</p>
        </div>
        <div class="footer-section">
          <h3>Links Úteis</h3>
          <a href="login.php">Entrar no Sistema</a>
          <a href="solicitar_acesso.php">Solicitar Acesso</a>
          <a href="reset-password.php">Recuperar Senha</a>
        </div>
        <div class="footer-section">
          <h3>Sobre</h3>
          <p>Desenvolvido pela equipe técnica da Subsecretária de Captação de Recursos - SUCAP.</p>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; <?php echo date("Y"); ?> SICEF - Sistema de Caderno de Emendas Federais. Todos os direitos reservados.</p>
      </div>
    </div>
  </footer>

  <!-- Scripts externos para carrossel e Bootstrap -->
  <script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Script para animações e interatividade da página
    document.addEventListener('DOMContentLoaded', function () {
      // Implementa scroll suave para links internos
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
          e.preventDefault();
          const target = document.querySelector(this.getAttribute('href'));
          if (target) {
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          }
        });
      });

      // Observer para animar cards de recursos quando entram na viewport
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
          }
        });
      });

      // Inicializa os cards com opacidade 0 e deslocamento para animação
      const features = document.querySelectorAll('.feature');
      features.forEach(feature => {
        feature.style.opacity = '0';
        feature.style.transform = 'translateY(30px)';
        feature.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(feature);
      });

      // Função para animar os números das estatísticas
      const animateCounters = () => {
        const counters = document.querySelectorAll('.stat-number');
        counters.forEach(counter => {
          const target = counter.textContent;
          const numericValue = parseInt(target.replace(/\D/g, ''));
          const suffix = target.replace(/[\d,]/g, '');

          let current = 0;
          const increment = numericValue / 50;
          const timer = setInterval(() => {
            current += increment;
            if (current >= numericValue) {
              counter.textContent = target;
              clearInterval(timer);
            } else {
              counter.textContent = Math.floor(current).toLocaleString() + suffix;
            }
          }, 30);
        });
      };

      // Observer para iniciar animação das estatísticas quando visíveis
      const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            animateCounters();
            statsObserver.unobserve(entry.target);
          }
        });
      });

      // Observa a seção de estatísticas para ativar animação
      const statsSection = document.querySelector('.stats');
      if (statsSection) {
        statsObserver.observe(statsSection);
      }
    });
  </script>
</body>

</html>