<?php
// sicef-caderno-de-emendas/config/db.php
// Configurações de conexão com o banco de dados PostgreSQL
$host = 'localhost';
$db   = 'caderno_emendas';
$user = 'sucap_admin';
$pass = 'nova_senha';
$port = '5432';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>