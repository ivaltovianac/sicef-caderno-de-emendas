<?php
// sicef-caderno-de-emendas/models/User.php

class User
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Busca usuário por e-mail (case-insensitive)
    public function findByEmail($email)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE LOWER(email) = LOWER(?)");
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('User::findByEmail erro: ' . $e->getMessage());
            return null;
        }
    }

    public function findById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($userData)
    {
        // Validar dados obrigatórios
        $required = ['nome', 'email', 'senha', 'tipo'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                return ['success' => false, 'message' => "Campo '$field' é obrigatório."];
            }
        }

        // Validar tipo de usuário
        $validTypes = ['deputado', 'senador', 'admin'];
        if (!in_array(strtolower($userData['tipo']), $validTypes, true)) {
            return ['success' => false, 'message' => 'Tipo de usuário inválido.'];
        }

        // Verificar se email já existe
        if ($this->findByEmail($userData['email'])) {
            return ['success' => false, 'message' => 'Email já cadastrado.'];
        }

        try {
            $sql = "SELECT id, nome, email, tipo, is_admin, is_user, criado_em FROM usuarios";
            $params = [];
            $where_conditions = [];

            // Aplicar filtros corretamente
            if (!empty($filtros['nome'])) {
                $where_conditions[] = "nome ILIKE ?";
                $params[] = '%' . $filtros['nome'] . '%';
            }

            if (!empty($filtros['email'])) {
                $where_conditions[] = "email ILIKE ?";
                $params[] = '%' . $filtros['email'] . '%';
            }

            if (!empty($filtros['tipo'])) {
                $where_conditions[] = "tipo = ?";
                $params[] = $filtros['tipo'];
            }

            if (isset($filtros['is_admin']) && $filtros['is_admin'] !== '') {
                $where_conditions[] = "is_admin = ?";
                $params[] = (bool) $filtros['is_admin'];
            }

            if (!empty($where_conditions)) {
                $sql .= " WHERE " . implode(' AND ', $where_conditions);
            }

            $sql .= " ORDER BY nome";

            if ($limite) {
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = (int) $limite;
                $params[] = (int) $offset;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log('User::getAll erro: ' . $e->getMessage());
            return [];
        }
    }

    public function getAll($filtros = [], $limite = null, $offset = 0)
    {
        try {
            // Hash da senha antes de inserir
            $hashedPassword = password_hash($userData['senha'], PASSWORD_DEFAULT);

            $stmt = $this->pdo->prepare("
                INSERT INTO usuarios (nome, email, senha, tipo, is_admin, is_user, criado_em) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $isAdmin = ($userData['tipo'] === 'admin') ? true : false;
            $isUser = true;

            if (
                $stmt->execute([
                    $userData['nome'],
                    $userData['email'],
                    $hashedPassword,
                    $userData['tipo'],
                    $isAdmin,
                    $isUser
                ])
            ) {
                return ['success' => true, 'message' => 'Usuário criado com sucesso', 'id' => $this->pdo->lastInsertId()];
            } else {
                return ['success' => false, 'message' => 'Erro ao criar usuário.'];
            }
        } catch (PDOException $e) {
            error_log('User::create erro: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }

    public function count($filtros = [])
    {
        try {
            $sql = "SELECT COUNT(*) FROM usuarios";
            $params = [];
            $where_conditions = [];

            // Aplicar mesmos filtros do getAll
            if (!empty($filtros['nome'])) {
                $where_conditions[] = "nome ILIKE ?";
                $params[] = '%' . $filtros['nome'] . '%';
            }

            if (!empty($filtros['tipo'])) {
                $where_conditions[] = "tipo = ?";
                $params[] = $filtros['tipo'];
            }

            if (!empty($where_conditions)) {
                $sql .= " WHERE " . implode(' AND ', $where_conditions);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('User::count erro: ' . $e->getMessage());
            return 0;
        }
    }

    public function updatePassword($email, $newPassword)
    {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE usuarios SET senha = ? WHERE LOWER(email) = LOWER(?)");
            return $stmt->execute([$hashedPassword, $email]);
        } catch (PDOException $e) {
            error_log('User::updatePassword erro: ' . $e->getMessage());
            return false;
        }
    }

    public function update($id, $data)
    {
        try {
            $campos = [];
            $valores = [];

            $campos_permitidos = ['nome', 'email', 'tipo', 'is_admin', 'is_user'];

            // Check for email duplicates on update
            if (isset($data['email'])) {
                $existing = $this->pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?) AND id != ?");
                $existing->execute([$data['email'], $id]);
                if ($existing->fetch()) {
                    return ['success' => false, 'message' => 'Email já está em uso por outro usuário.'];
                }
            }

            foreach ($campos_permitidos as $campo) {
                if (isset($data[$campo])) {
                    $campos[] = "$campo = ?";
                    $valores[] = $data[$campo];
                }
            }

            if (isset($data['senha']) && !empty($data['senha'])) {
                $campos[] = "senha = ?";
                $valores[] = password_hash($data['senha'], PASSWORD_DEFAULT);
            }

            if (empty($campos)) {
                return ['success' => false, 'message' => 'Nenhum campo para atualizar.'];
            }

            $valores[] = (int) $id;
            $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);

            if ($stmt->execute($valores)) {
                return ['success' => true, 'message' => 'Usuário atualizado com sucesso.'];
            } else {
                return ['success' => false, 'message' => 'Erro ao atualizar usuário.'];
            }
        } catch (PDOException $e) {
            error_log('User::update erro: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }

    public function toggleStatus($id, $status)
    {
        $stmt = $this->pdo->prepare("UPDATE usuarios SET is_user = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function delete($id)
    {
        try {
            // Não permitir deletar o próprio usuário logado
            if (isset($_SESSION['user']) && $_SESSION['user']['id'] == $id) {
                return ['success' => false, 'message' => 'Não é possível deletar seu próprio usuário.'];
            }

            $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            if ($stmt->execute([$id])) {
                return ['success' => true, 'message' => 'Usuário deletado com sucesso.'];
            } else {
                return ['success' => false, 'message' => 'Erro ao deletar usuário.'];
            }
        } catch (PDOException $e) {
            error_log('User::delete erro: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }
}
