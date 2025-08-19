<?php
/**
 * Configuração de Conexão com Banco de Dados - SICEF
 *
 * Este arquivo contém as configurações de conexão com o banco de dados PostgreSQL
 * e funções auxiliares para operações comuns.
 *
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */

// sicef-caderno-de-emendas/config/db.php

// Carrega configurações do ambiente ou usa valores padrão
$config = [
    'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost',
    'db'   => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'caderno_emendas',
    'user' => $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'sucap_admin',
    'pass' => $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'nova_senha',
    'port' => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '5432',
];

$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';

try {
    // Configuração da conexão PDO com PostgreSQL
    // Removido "charset" do DSN (não suportado em PDO pgsql)
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['db']}";

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT    => false,
    ]);

    // Definir timezone e codificação do cliente
    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
    $pdo->exec("SET client_encoding TO 'UTF8'");

} catch (PDOException $e) {
    error_log("Erro de conexão com banco de dados: " . $e->getMessage());

    // Em produção, não mostrar detalhes do erro
    if ($appEnv === 'production') {
        die("Erro interno do servidor. Tente novamente mais tarde.");
    } else {
        die("Erro de conexão: " . $e->getMessage());
    }
}

/**
 * Executa uma query SQL com tratamento de erros
 *
 * @param PDO $pdo Instância da conexão PDO
 * @param string $sql Comando SQL a ser executado
 * @param array $params Parâmetros para prepared statements
 * @return PDOStatement
 * @throws PDOException
 */
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Erro na query: " . $e->getMessage() . " | SQL: " . $sql);
        throw $e;
    }
}

/**
 * Sanitiza entrada de usuário para prevenir XSS
 *
 * @param string $input Texto a ser sanitizado
 * @return string Texto sanitizado
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Função para validar CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Função para gerar CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}