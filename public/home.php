<?php
session_start();
require_once __DIR__ . '/../models/Emenda.php';

// Verificar autenticação
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$emendaModel = new Emenda();
$emendas = $emendaModel->getAll();

// Processar busca
$search_results = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $search_term = $_GET['search'];
    $search_results = $emendaModel->search($search_term);
}

// Processar atualização de valor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valor_destinado'])) {
    $id = $_POST['id'];
    $valor = (float)$_POST['valor_destinado'];
    if ($emendaModel->updateValor($id, $valor)) {
        $success = "Valor destinado atualizado com sucesso!";
    } else {
        $error = "Erro ao atualizar valor destinado.";
    }
    // Recarregar dados
    $emendas = $emendaModel->getAll();
}
?>

<!-- estrutura do HTML para a página inicial -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Caderno de Emendas Federais</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .header {
            background: linear-gradient(135deg, #1a2a6c, #2a4a7c);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stats-container {
            display: flex;
            justify-content: space-around;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            width: 22%;
            min-width: 200px;
            margin: 0.5rem;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1a2a6c;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .content-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .search-form {
            margin-bottom: 2rem;
        }
        .emenda-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: #fff;
            transition: transform 0.2s;
        }
        .emenda-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
        .footer {
            text-align: center;
            margin-top: 3rem;
            padding: 2rem 0;
            background: #2c3e50;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>CADERNO DE EMENDAS FEDERAIS</h1>
                    <p class="mb-0">2025 | Bem-vindo, <?= $_SESSION['user']['nome'] ?></p>
                </div>
                <div>
                    <a href="login.php?logout=true" class="btn btn-outline-light">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Stats Section -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number">7</div>
                <div class="stat-label">Eixo Desenvolvimento Econômico</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">5</div>
                <div class="stat-label">Eixo Saúde</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">13</div>
                <div class="stat-label">Eixo Segurança Territorial</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">3</div>
                <div class="stat-label">Eixo Educação</div>
            </div>
        </div>

        <!-- About Section -->
        <div class="content-section">
            <h2>O que são Emendas?</h2>
            <p>Emendas são instrumentos previstos na Constituição de 1988 para que os representantes do Poder Legislativo possam alterar programações presentes nos projetos de leis orçamentárias anuais, os quais são elaborados e apresentados pelo Poder Executivo.</p>
            <p>Tais Emendas podem ser de cunho individual, de Bancada ou de Relatoria.</p>
            
            <h3>Emendas Individuais</h3>
            <p>As emendas individuals poderão alocar recursos a Estados, ao Distrito Federal e a Municípios por meio de:</p>
            <p>I – transferência especial – recursos repassados sem destinação específica e sem a necessidade de ser instrumentalizado por meio de acordos de transferências voluntárias, a título de convênios ou contratos de repasses.</p>
            <p>II – transferência com finalidade definida – recursos repassados com destinação específica e com necessidade de ser instrumentalizado por meio de acordos de transferências voluntárias, a título de convênios ou contratos de repasses, via Sistema de Gestão de Convênios e Contratos de Repasses – SCONV.</p>
            
            <h3>Emendas de Bancada</h3>
            <p>Quanto às emendas de bancadas são as alocações de recursos derivados da engajamento de interesses de diversos parlamentares para a destinação conjunta de dotação a determinada programação orçamentária.</p>
            
            <h3>Faces da peça orçamentária</h3>
            <p>A peça orçamentária possui duas faces: técnica e política.</p>
            <p>A técnica demonstra-se em síntese com a estimativa de receita e a fixação da despensa e ser executada em determinado exercício financeiro, envolvendo uma série de aspectos econômicos e de formulação de políticas públicas.</p>
            <p>Já a face política se reverbera com a própria elaboração, discussão, alterações e realocações de recursos dos projetos de lei de orçamento anual, em que sinalizam a sociedade o direcionamento da república e a intenção de fomento a determinadas políticas públicas, via a alocação de recursos.</p>
            
            <h3>Caderno de Emendas</h3>
            <p>O Caderno de Emendas Federal (elaborado e apresentado pelo Governo do Distrito Federal aos Congressistas) se insere no intuito de sugerir aos representantes de mandato do Congresso Nacional os objetos de política públicas de ensino da população do Distrito Federal – DF e de áreas de influência, para que reslocam partecias dos recursos de emendas parlamentares as programações orçamentárias dos Orçamentos Fiscais e da Seguridade Social da União para aplicação em políticas pública de interesses recíprocas no DF.</p>
        </div>

        <!-- Emendas Section -->
        <div class="content-section">
            <h2 class="mb-4">Emendas Disponíveis</h2>
            
            <!-- Search Form -->
            <form class="search-form" method="GET">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Buscar por cidade, valor ou descrição...">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>

            <!-- Results -->
            <?php if(!empty($search_results)): ?>
                <h4>Resultados da Busca:</h4>
                <div class="row">
                    <?php foreach($search_results as $emenda): ?>
                        <div class="col-md-6">
                            <div class="emenda-card">
                                <h5><?= htmlspecialchars($emenda['objeto_intervencao']) ?></h5>
                                <p><strong>Cidade:</strong> <?= htmlspecialchars($emenda['municipio']) ?></p>
                                <p><strong>Valor Pretendido:</strong> R$ <?= number_format($emenda['valor_pretendido'], 2, ',', '.') ?></p>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label"><strong>Valor Destinado:</strong></label>
                                        <input type="number" step="0.01" class="form-control" name="valor_destinado" 
                                               value="<?= $emenda['valor_destinado'] ?>" required>
                                    </div>
                                    <input type="hidden" name="id" value="<?= $emenda['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Atualizar Valor</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($emendas as $emenda): ?>
                        <div class="col-md-6">
                            <div class="emenda-card">
                                <h5><?= htmlspecialchars($emenda['objeto_intervencao']) ?></h5>
                                <p><strong>Tipo:</strong> <?= htmlspecialchars($emenda['tipo_emenda']) ?></p>
                                <p><strong>Cidade:</strong> <?= htmlspecialchars($emenda['municipio']) ?></p>
                                <p><strong>Valor Pretendido:</strong> R$ <?= number_format($emenda['valor_pretendido'], 2, ',', '.') ?></p>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label"><strong>Valor Destinado:</strong></label>
                                        <input type="number" step="0.01" class="form-control" name="valor_destinado" 
                                               value="<?= $emenda['valor_destinado'] ?>" required>
                                    </div>
                                    <input type="hidden" name="id" value="<?= $emenda['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Atualizar Valor</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="container">
            <p><strong>GDF - Governo do Distrito Federal</strong></p>
            <p>Anexo do Palácio do Buriti 5º andar, Brasília/DF - CEP: 70075-900</p>
            <p>email@economia.df.gov.br | (61) 3414-9213</p>
            <p class="mb-0">Copyright © 2025. Todos os direitos reservados.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>