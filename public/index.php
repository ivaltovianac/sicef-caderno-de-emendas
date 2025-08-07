<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Emenda.php';

// Debug - remova depois de funcionar
error_reporting(E_ALL);
ini_set('display_errors', 1);

$request = $_SERVER['REQUEST_URI'];
$request = str_replace('/caderno-de-emendas/public', '', $request); // Ajuste se necessário

// Roteamento simplificado
switch ($request) {
    case '/':
    case '/login':
    case '/login.php':
        require 'login.php';
        break;
    case '/reset-password':
    case '/reset-password.php':
        require 'reset-password.php';
        break;
    case '/formulario':
    case '/formulario_para_login.php':
        require __DIR__ . '/../formulario_para_login.php';
        break;
    default:
        if (file_exists(__DIR__ . $request)) {
            require __DIR__ . $request;
        } else {
            http_response_code(404);
            echo "Página não encontrada: " . htmlspecialchars($request);
        }
        break;
}
?>