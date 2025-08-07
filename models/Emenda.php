<?php
// sicef-caderno-de-emendas/models/Emenda.php
// Classe para manipulação de emendas no banco de dados
define('BASE_PATH', dirname(__DIR__));
require_once(BASE_PATH . '/config/db.php');

class Emenda {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM emendas");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search($termo) {
    $term = "%$termo%";
    $sql = "SELECT * FROM emendas 
            WHERE municipio ILIKE ? 
            OR objeto_intervencao ILIKE ? 
            OR valor_pretendido::TEXT ILIKE ?";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$term, $term, $term]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function updateValor($id, $valor) {
        $stmt = $this->pdo->prepare("UPDATE emendas SET valor_destinado = ? WHERE id = ?");
        return $stmt->execute([$valor, $id]);
    }
}
?>