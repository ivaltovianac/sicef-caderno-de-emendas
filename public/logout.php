<?php
/**
 * Logout do usuário - SICEF
 *
 * Este arquivo é responsável por encerrar a sessão do usuário no sistema.
 * Ele destrói todas as variáveis de sessão, apaga o cookie de sessão e redireciona para a página inicial.
 *
 * Funcionalidades:
 * - Destruição da sessão
 * - Limpeza do cookie de sessão
 * - Redirecionamento para a página de apresentação
 *
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */
session_start(); 

// Limpa todas as variáveis da sessão para garantir que não restem dados
$_SESSION = array();

// Verifica se a sessão utiliza cookies para também removê-los
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
    );
}

// Destrói a sessão completamente
session_destroy();

// Redirecionar para a página de apresentação
header("Location: apresentacao.php");
exit;