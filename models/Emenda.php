<?php
/**
 * Gerenciamento de Emendas - SICEF Caderno de Emendas
 * 
 * Esta classe é responsável por gerenciar todas as operações relacionadas às emendas
 * do sistema SICEF Caderno de Emendas. Inclui funcionalidades para criação, consulta,
 * atualização e exclusão de emendas, além de filtragem e contagem.
 * 
 * Funcionalidades:
 * - Criação e registro de novas emendas
 * - Consulta e filtragem de emendas
 * - Atualização de dados de emendas
 * - Exclusão de emendas
 * - Verificação de uso de emendas
 * - Contagem de emendas com filtros
 * 
 * @package SICEF
 * @author Equipe SICEF
 * @version 1.0
 */

// sicef-caderno-de-emendas/models/Emenda.php

class Emenda
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca emenda por ID
     * 
     * @param int $id ID da emenda a ser buscada
     * @return array|false Retorna os dados da emenda ou false se não encontrada
     */
    public function findById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM emendas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cria uma nova emenda no sistema
     * 
     * @param array $data Dados da emenda a ser criada
     * @return bool Resultado da operação
     */
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

    /**
     * Obtém todas as emendas com filtros opcionais
     * 
     * @param array $filtros Filtros para a consulta
     * @param int|null $limite Limite de registros retornados
     * @param int $offset Deslocamento para paginação
     * @return array Lista de emendas encontradas
     */
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
            $params[] = (int) $filtros['ano'];
        }

        if (!empty($filtros['valor_min'])) {
            $where_conditions[] = "valor >= ?";
            $params[] = (float) $filtros['valor_min'];
        }

        if (!empty($filtros['valor_max'])) {
            $where_conditions[] = "valor <= ?";
            $params[] = (float) $filtros['valor_max'];
        }

        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }

        $sql .= " ORDER BY criado_em DESC";

        if ($limite) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limite;
            $params[] = (int) $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta o número de emendas com filtros opcionais
     * 
     * @param array $filtros Filtros para a contagem
     * @return int Número de emendas encontradas
     */
    public function count($filtros = [])
    {
        $sql = "SELECT COUNT(*) FROM emendas";
        $params = [];
        $where_conditions = [];

        // Aplica mesmos filtros do getAll
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

    /**
     * Atualiza uma emenda existente
     * 
     * @param int $id ID da emenda a ser atualizada
     * @param array $data Dados a serem atualizados
     * @return bool Resultado da operação
     */
    public function update($id, $data)
    {
        // Atualização dinâmica com tratamento do tipo de dados
        $campos = [];
        $valores = [];

        $campos_permitidos = [
            'tipo_emenda',
            'eixo_tematico',
            'orgao',
            'objeto_intervencao',
            'ods',
            'valor',
            'justificativa',
            'regionalizacao',
            'unidade_orcamentaria',
            'programa',
            'acao',
            'categoria_economica'
        ];

        foreach ($campos_permitidos as $campo) {
            if (isset($data[$campo])) {
                $campos[] = "$campo = ?";
                // Converte valor para float se for campo numérico
                if ($campo === 'valor') {
                    $valores[] = (float) $data[$campo];
                } else {
                    $valores[] = $data[$campo];
                }
            }
        }

        if (empty($campos)) {
            return false;
        }

        $campos[] = "atualizado_em = NOW()";
        $valores[] = (int) $id;

        $sql = "UPDATE emendas SET " . implode(', ', $campos) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($valores);
    }

    /**
     * Verifica se uma emenda está sendo usada
     * 
     * @param int $id ID da emenda a ser verificada
     * @return bool True se a emenda está em uso, false caso contrário
     */
    public function isUsed($id)
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM usuario_emendas WHERE emenda_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    }

    /**
     * Exclui uma emenda
     * 
     * @param int $id ID da emenda a ser excluída
     * @return bool Resultado da operação
     */
    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM emendas WHERE id = ?");
        return $stmt->execute([$id]);
    }
}