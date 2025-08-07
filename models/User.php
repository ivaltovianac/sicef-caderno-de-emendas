<?php
// sicef-caderno-de-emendas/models/User.php
class User {
    private $pdo;

    // Recebe PDO via construtor
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    // Método para buscar usuário por e-mail
    // Retorna id, email, senha, nome e is_admin
    public function findByEmail($email) {
    $stmt = $this->pdo->prepare("SELECT id, email, senha, nome, is_admin FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

    public function updatePassword($email, $password) {
        $hashedPassword = password_hash($password, PASSWORD_ARGON2I);
        $stmt = $this->pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
        return $stmt->execute([$hashedPassword, $email]);
    }
}
?>