<?php
class User {
    private $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($nome, $email, $senha, $tipo) {
        $hashedPassword = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
        return $stmt->execute([$hashedPassword, $email]);
    }
}