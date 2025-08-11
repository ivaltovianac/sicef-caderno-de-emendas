<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../../config/db.php";

$emenda_id = $_GET["id"] ?? null;
$emenda = null;

if ($emenda_id) {
    $stmt = $pdo->prepare("SELECT * FROM emendas WHERE id = ?");
    $stmt->execute([$emenda_id]);
    $emenda = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emenda) {
        $_SESSION["message"] = "Emenda não encontrada.";
        header("Location: user_dashboard.php");
        exit;
    }
} else {
    $_SESSION["message"] = "ID da emenda não fornecido.";
    header("Location: user_dashboard.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Emenda - SICEF</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007b5e;
            --secondary-color: #4db6ac;
            --accent-color: #ffc107;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
            --border-color: #e0e0e0;
            --error-color: #e74c3c;
            --success-color: #2ecc71;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 900px;
            margin: 2rem auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        h1 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }
        
        .detail-item {
            margin-bottom: 1rem;
        }
        
        .detail-item strong {
            color: var(--dark-color);
            display: inline-block;
            width: 180px; /* Adjust as needed */
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            gap: 0.5rem;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        .btn-edit, .btn-suggest, .btn-allocate {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            margin-left: 0.5rem;
        }

        .btn-edit:hover, .btn-suggest:hover, .btn-allocate:hover {
            background-color: #429e94;
            transform: scale(1.1);
        }

        /* Modal Styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
            padding-top: 60px;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
            position: relative;
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-content h2 {
            margin-top: 0;
            color: var(--primary-color);
        }

        .modal-content label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .modal-content input[type="number"],
        .modal-content textarea {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }

        .modal-content button[type="submit"] {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }

        .modal-content button[type="submit"]:hover {
            background-color: #006a50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Detalhes da Emenda</h1>
        
        <div class="detail-item">
            <strong>ID:</strong> <?= htmlspecialchars($emenda["id"]) ?>
        </div>
        <div class="detail-item">
            <strong>Tipo de Emenda:</strong> <?= htmlspecialchars($emenda["tipo_emenda"]) ?>
        </div>
        <div class="detail-item">
            <strong>Eixo Temático:</strong> <?= htmlspecialchars($emenda["eixo_tematico"]) ?>
        </div>
        <div class="detail-item">
            <strong>Órgão:</strong> <?= htmlspecialchars($emenda["orgao"]) ?>
        </div>
        <div class="detail-item">
            <strong>Objeto de Intervenção:</strong> <?= htmlspecialchars($emenda["objeto_intervencao"]) ?>
        </div>
        <div class="detail-item">
            <strong>ODS:</strong> <?= htmlspecialchars($emenda["ods"]) ?>
        </div>
        <div class="detail-item">
            <strong>Valor Pretendido:</strong> R$ <?= number_format($emenda["valor"], 2, ",", ".") ?>
        </div>
        <div class="detail-item">
            <strong>Justificativa:</strong> <?= htmlspecialchars($emenda["justificativa"]) ?>
        </div>
        <div class="detail-item">
            <strong>Regionalização:</strong> <?= htmlspecialchars($emenda["regionalizacao"]) ?>
        </div>
        <div class="detail-item">
            <strong>Unidade Orçamentária:</strong> <?= htmlspecialchars($emenda["unidade_orcamentaria"]) ?>
        </div>
        <div class="detail-item">
            <strong>Programa:</strong> <?= htmlspecialchars($emenda["programa"]) ?>
        </div>
        <div class="detail-item">
            <strong>Ação:</strong> <?= htmlspecialchars($emenda["acao"]) ?>
        </div>
        <div class="detail-item">
            <strong>Categoria Econômica:</strong> <?= htmlspecialchars($emenda["categoria_economica"]) ?>
        </div>
        <div class="detail-item">
            <strong>Criado em:</strong> <?= htmlspecialchars($emenda["criado_em"]) ?>
        </div>
        <div class="detail-item">
            <strong>Atualizado em:</strong> <?= htmlspecialchars($emenda["atualizado_em"]) ?>
        </div>

        <div class="form-actions">
            <a href="minhas_emendas.php" class="btn btn-secondary">Voltar para Minhas Emendas</a>
            <a href="edit_emenda.php?id=<?= $emenda["id"] ?>" class="btn btn-edit" title="Editar"><i class="material-icons">edit</i></a>
            <a href="suggest_emenda.php?id=<?= $emenda["id"] ?>" class="btn btn-suggest" title="Sugerir Alteração"><i class="material-icons">lightbulb</i></a>
            <button class="btn btn-allocate" title="Destinar Valor" onclick="openAllocateModal(<?= $emenda["id"] ?>, <?= $emenda["valor"] ?>)"><i class="material-icons">attach_money</i></button>
        </div>
    </div>

    <!-- Modal para Destinar Valor -->
    <div id="allocateModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeAllocateModal()">&times;</span>
            <h2>Destinar Valor à Emenda</h2>
            <form method="POST" action="minhas_emendas.php">
                <input type="hidden" name="action" value="destinar_valor">
                <input type="hidden" name="emenda_id" id="modal_emenda_id">
                <label for="valor_destinar">Valor a Destinar:</label>
                <input type="number" step="0.01" name="valor_destinar" id="valor_destinar" required>
                <p>Valor Pretendido: <span id="modal_valor_pretendido"></span></p>
                <button type="submit">Destinar</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const allocateModal = document.getElementById("allocateModal");
            const modalEmendaId = document.getElementById("modal_emenda_id");
            const modalValorPretendido = document.getElementById("modal_valor_pretendido");

            window.openAllocateModal = function(emendaId, valorPretendido) {
                modalEmendaId.value = emendaId;
                modalValorPretendido.textContent = "R$ " + parseFloat(valorPretendido).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                allocateModal.style.display = "block";
            };

            window.closeAllocateModal = function() {
                allocateModal.style.display = "none";
            };

            window.onclick = function(event) {
                if (event.target == allocateModal) {
                    allocateModal.style.display = "none";
                }
            };
        });
    </script>
</body>
</html>


