<?php
// sicef-caderno-de-emendas/public/logout.php
// Inicia a sessão
// Destrói a sessão e limpa os dados do usuário
// Redireciona para a página de login
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>