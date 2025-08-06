<?php
// public/index.php

// Verifica qual script deve ser executado
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove o base path se necessário
$base = '/caderno-de-emendas/public';
if (strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}

// Roteamento básico
switch ($path) {
    case '/':
    case '/login':
    case '':
        require 'login.php';
        break;
    case '/reset-password':
        require 'reset-password.php';
        break;
    case '/home':
        require 'home.php';
        break;
    default:
        http_response_code(404);
        echo "Página não encontrada";
        break;
}
?>