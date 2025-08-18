
<?php
// sicef-caderno-de-emendas/models/Emenda.php

class Emenda
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM emendas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $sql = "INSERT INTO emendas (tipo_emenda, eixo_tematico, orgao, objeto_intervencao, ods, valor, justificativa, regionalizacao, unidade_orcamentaria, programa, acao, categoria_economica, criado_em, atualizado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['tipo_emenda'],
            $data['eixo_tematico'],
            $data['orgao'],
            $data['objeto_intervencao'],
            $data['ods'],
            $data['valor'],
            $data['justificativa'],
            $data['regionalizacao'],
            $data['unidade_orcamentaria'],
            $data['programa'],
            $data['acao'],
            $data['categoria_economica']
        ]);
    }

    public function getAll($filtros = [], $limite = null, $offset = 0)
    {
        $sql = "SELECT * FROM emendas";
        $params = [];
        $where_conditions = [];

        // Aplicar filtros com prepared statements
        if (!empty($filtros['tipo_emenda'])) {
            $where_conditions[] = "tipo_emenda ILIKE ?";
            $params[] = '%' . $filtros['tipo_emenda'] . '%';
        }

        if (!empty($filtros['eixo_tematico'])) {
            $where_conditions[] = "eixo_tematico ILIKE ?";
            $params[] = '%' . $filtros['eixo_tematico'] . '%';
        }

        if (!empty($filtros['orgao'])) {
            $where_conditions[] = "orgao ILIKE ?";
            $params[] = '%' . $filtros['orgao'] . '%';
        }

        if (!empty($filtros['ods'])) {
            $where_conditions[] = "ods ILIKE ?";
            $params[] = '%' . $filtros['ods'] . '%';
        }

        if (!empty($filtros['programa'])) {
            $where_conditions[] = "programa ILIKE ?";
            $params[] = '%' . $filtros['programa'] . '%';
        }

        if (!empty($filtros['ano'])) {
            $where_conditions[] = "EXTRACT(YEAR FROM criado_em) = ?";
            $params[] = (int)$filtros['ano'];
        }

        if (!empty($filtros['valor_min'])) {
            $where_conditions[] = "valor >= ?";
            $params[] = (float)$filtros['valor_min'];
        }

        if (!empty($filtros['valor_max'])) {
            $where_conditions[] = "valor <= ?";
            $params[] = (float)$filtros['valor_max'];
        }

        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }

        $sql .= " ORDER BY criado_em DESC";

        if ($limite) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limite;
            $params[] = (int)$offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count($filtros = [])
    {
        $sql = "SELECT COUNT(*) FROM emendas";
        $params = [];
        $where_conditions = [];

        // Aplicar mesmos filtros do getAll
        if (!empty($filtros['tipo_emenda'])) {
            $where_conditions[] = "tipo_emenda ILIKE ?";
            $params[] = '%' . $filtros['tipo_emenda'] . '%';
        }

        if (!empty($filtros['eixo_tematico'])) {
            $where_conditions[] = "eixo_tematico ILIKE ?";
            $params[] = '%' . $filtros['eixo_tematico'] . '%';
        }

        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function update($id, $data)
    {
        // Dynamic update with proper data type handling
        $campos = [];
        $valores = [];
        
        $campos_permitidos = [
            'tipo_emenda', 'eixo_tematico', 'orgao', 'objeto_intervencao', 
            'ods', 'valor', 'justificativa', 'regionalizacao', 
            'unidade_orcamentaria', 'programa', 'acao', 'categoria_economica'
        ];

        foreach ($campos_permitidos as $campo) {
            if (isset($data[$campo])) {
                $campos[] = "$campo = ?";
                // Converter valor para float se for campo numérico
                if ($campo === 'valor') {
                    $valores[] = (float)$data[$campo];
                } else {
                    $valores[] = $data[$campo];
                }
            }
        }

        if (empty($campos)) {
            return false;
        }

        $campos[] = "atualizado_em = NOW()";
        $valores[] = (int)$id;

        $sql = "UPDATE emendas SET " . implode(', ', $campos) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($valores);
    }

    public function isUsed($id)
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM usuario_emendas WHERE emenda_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM emendas WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
