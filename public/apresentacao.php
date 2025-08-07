<?php
// sicef-caderno-de-emendas/apresentacao.php

session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Caderno de Emendas Federais 2025</title>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    /* Estilo CSS */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background-color: #009C80; color: #009C80; scroll-behavior: smooth; }
    .floating-login { position: fixed; top: 20px; right: 20px; background: linear-gradient(45deg, #ff9800, #ffc107); color: #fff; padding: 10px 18px; border-radius: 25px; text-decoration: none; font-size: 0.95em; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); z-index: 999; transition: background 0.3s, transform 0.3s; }
    .floating-login:hover { background: linear-gradient(45deg, #ffa726, #ffe082); transform: scale(1.05); }
    /* header { background: linear-gradient(90deg,#00715D, #009C80); padding: 60px 20px; color: white; text-align: center; position: relative; } */
    header { background: linear-gradient(90deg, #007b5e, #4db6ac); padding: 30px 50px; color: white; display: flex; justify-content: space-between; align-items: center; }
        header img { height: 50px; }
        nav { display: flex; gap: 20px; align-items: center; }
        nav a { color: white; text-decoration: none; font-weight: 500; }
    header h1 { display: inline-block; background: rgba(255, 255, 255, 0.1); padding: 25px 40px; font-size: 3em; font-weight: 600; border-radius: 18px; color: #fff; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25); text-shadow: 1px 2px 4px rgba(0, 0, 0, 0.4); animation: slideFadeIn 1s ease-out; }
    @keyframes slideFadeIn { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
    nav { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-top: 25px; }
    nav button { background: linear-gradient(45deg, #f59330, #f0d442); color: #fff; border: none; padding: 10px 20px; font-size: 1em; border-radius: 30px; cursor: pointer; transition: transform 0.3s, background 0.3s; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
    nav button:hover { transform: scale(1.05); background: linear-gradient(45deg, #fcb69f, #ffecd2); }
    section { display: flex; align-items: center; justify-content: space-between; padding: 80px 10%; gap: 40px; opacity: 0; transform: translateY(40px); transition: all 1s ease; }
    section.visible { opacity: 1; transform: translateY(0); }
    section:nth-child(even) { flex-direction: row-reverse; background-color: #eaf4fc; }
    section:nth-child(odd) { background-color: #ffffff; }
    section img { width: 45%; max-width: 500px; border-radius: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); transition: transform 0.4s; }
    section img:hover { transform: scale(1.03); }
    .text-content { width: 55%; background: #ffffff; padding: 30px; border-radius: 16px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); transition: box-shadow 0.3s ease; }
    .text-content:hover { box-shadow: 0 12px 30px rgba(0,0,0,0.12); }
    .text-content h1 { font-size: 2.2em; color: #222; margin-bottom: 10px; position: relative; }
    .text-content h1::after { content: ''; position: absolute; width: 60px; height: 4px; background: #ff7e5f; bottom: -10px; left: 0; border-radius: 10px; transition: width 0.3s ease; }
    .text-content:hover h1::after { width: 100px; }
    .text-content p { font-size: 1.2em; line-height: 1.6; color: #444; margin-top: 15px; }
    footer { background: linear-gradient(90deg, #009C80, #00715D); color: white; text-align: center; padding: 20px; font-size: 0.95em; }
    @media (max-width: 900px) { section { flex-direction: column !important; text-align: center; } section img, .text-content { width: 100%; } nav { flex-direction: column; gap: 10px; } .floating-login { top: 10px; right: 10px; padding: 8px 14px; } .floating-login span { font-size: 20px; } header h1 { font-size: 2em; padding: 20px; } }
  </style>
</head>
<body>
  <a href="login.php" class="floating-login">
    <span class="material-icons">login</span> Login
  </a>

  <header>
    <div>
      <a href="apresentacao.php" title="Ir para página inicial">
        <img src="imagens/logo.png" alt="Logo SICEF" style="border: none;">
      </a>
    </div>
    <h1> Caderno de Emendas Federais 2025 </h1>
    <nav>
      <button onclick="scrollToSection('apresentacao')">
        <span class="material-icons">info</span> Apresentação
      </button>
      <button onclick="scrollToSection('objetivos')">
        <span class="material-icons">flag</span> Objetivos
      </button>
      <button onclick="scrollToSection('oque-e')">
        <span class="material-icons">book</span> O que é
      </button>
      <button onclick="scrollToSection('tipos')">
        <span class="material-icons">layers</span> Tipos de Emendas
      </button>
    </nav>
  </header>

  <section id="apresentacao">
    <img src="imagens/sistema.jpg" alt="Apresentação">
    <div class="text-content">
      <h1>Apresentação do Sistema</h1>
      <p>Este sistema visa centralizar e organizar as propostas de emendas parlamentares, permitindo maior controle, visibilidade e eficiência na alocação de recursos públicos provenientes das emendas federais.</p>
    </div>
  </section>

  <section id="objetivos">
    <img src="imagens/objetivos.jpg" alt="Objetivos">
    <div class="text-content">
      <h1>Objetivos</h1>
      <p>Facilitar a análise das propostas, promover a transparência dos processos e assegurar a conformidade com as prioridades estratégicas definidas pelo governo são os pilares deste sistema.</p>
    </div>
  </section>

  <section id="oque-e">
    <img src="imagens/caderno_emendas.png" alt="O que é o Caderno">
    <div class="text-content">
      <h1>O que é o Caderno de Emendas?</h1>
      <p>Instrumento de gestão pública que reúne demandas para a destinação de recursos via emendas parlamentares. Ele serve como referência tanto para gestores quanto para parlamentares.</p>
    </div>
  </section>

  <section id="tipos">
    <img src="imagens/tipos_emendas.jpg" alt="Tipos de Emendas">
    <div class="text-content">
      <h1>Tipos de Emendas</h1>
      <p>
        <strong>Individuais:</strong> Destinadas por parlamentares.<br>
        <strong>De Bancada:</strong> Coletivas por estado.<br>
        <strong>De Comissão:</strong> Propostas por comissões.<br>
        <strong>Relator-Geral:</strong> Ajustes gerais no orçamento.
      </p>
    </div>
  </section>

  <footer>
    © 2025 - Sistema de Caderno de Emendas Federais | Desenvolvido por COSP.
  </footer>

  <script>
    function scrollToSection(id) {
      const section = document.getElementById(id);
      section.scrollIntoView({ behavior: "smooth" });
    }

    const sections = document.querySelectorAll("section");
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
        }
      });
    }, { threshold: 0.2 });

    sections.forEach(section => {
      observer.observe(section);
    });
  </script>
</body>
</html>