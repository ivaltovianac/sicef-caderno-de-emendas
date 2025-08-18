
<?php
// sicef-caderno-de-emendas/con:$_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';

try {
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

// Função para executar queries com log de erro
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

// Função para sanitizar entrada
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
