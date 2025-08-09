<?php
// admin_header.php
?>
<!-- Header -->
<div class="header">
    <button class="menu-toggle" id="menuToggle">
        <span class="material-icons">menu</span>
    </button>
    <h1>Gerenciar Usuários</h1>
    <div class="user-menu">
        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['user']['nome'], 0, 1)) ?>
            </div>
            <span><?= htmlspecialchars($_SESSION['user']['nome']) ?></span>
        </div>
        <a href="../logout.php">
            <span class="material-icons">logout</span>
            Sair
        </a>
    </div>
</div>