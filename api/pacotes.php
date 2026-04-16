<?php
/**
 * pacotes.php
 * CRUD de pacotes de pacientes
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

startSession();
if (!isAuthenticated()) {
    errorResponse('Não autorizado', 401);
}

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        try {
            $paciente_id = $_GET['paciente_id'] ?? '';
            $status = $_GET['status'] ?? '';
            $id = $_GET['id'] ?? '';

            $where = [];
            $params = [];

            if (!empty($id)) {
                $where[] = "p.id = ?";
                $params[] = $id;
            }
            if (!empty($paciente_id)) {
                $where[] = "p.paciente_id = ?";
                $params[] = $paciente_id;
            }
            if (!empty($status)) {
                $where[] = "p.status = ?";
                $params[] = $status;
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT p.*, pa.nome as paciente_nome 
                    FROM pacotes p 
                    LEFT JOIN pacientes pa ON p.paciente_id = pa.id 
                    $whereClause 
                    ORDER BY p.data_inicio DESC, p.id DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $pacotes = $stmt->fetchAll();

            foreach ($pacotes as &$pacote) {
                $pacote['valor_total'] = floatval($pacote['valor_total']);
                $pacote['data_inicio'] = formatDateToBR($pacote['data_inicio']);
                $pacote['data_fim'] = $pacote['data_fim'] ? formatDateToBR($pacote['data_fim']) : null;
            }

            if (!empty($id) && count($pacotes) === 1) {
                successResponse($pacotes[0], 'Pacote encontrado');
            } else {
                // ⚠️ ALTERAÇÃO IMPORTANTE: Retornar um objeto com 'pacotes' para compatibilidade com frontend
                successResponse(['pacotes' => $pacotes], 'Pacotes listados');
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao listar pacotes', 500);
        }
        break;

    case 'POST':
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;

        $errors = [];
        if (empty($input['paciente_id'])) $errors[] = 'Paciente é obrigatório';
        if (empty($input['tipo_pacote'])) $errors[] = 'Tipo de pacote é obrigatório';
        if (empty($input['data_inicio'])) $errors[] = 'Data de início é obrigatória';
        if (!isset($input['valor_total']) || $input['valor_total'] <= 0) $errors[] = 'Valor total deve ser maior que zero';
        if (empty($input['forma_pagamento'])) $errors[] = 'Forma de pagamento é obrigatória';

        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }

        try {
            $stmt = $db->prepare("SELECT id FROM pacientes WHERE id = ?");
            $stmt->execute([$input['paciente_id']]);
            if (!$stmt->fetch()) {
                errorResponse('Paciente não encontrado', 404);
            }

            // Verificar se já existe pacote ativo (data_fim nula ou maior que hoje)
            $stmt = $db->prepare("SELECT id FROM pacotes WHERE paciente_id = ? AND status = 'Ativo' AND (data_fim IS NULL OR data_fim >= CURDATE())");
            $stmt->execute([$input['paciente_id']]);
            if ($stmt->fetch()) {
                errorResponse('Paciente já possui um pacote ativo', 400);
            }

            $id = 'pac_' . time() . '_' . substr(md5(uniqid()), 0, 6);

            $stmt = $db->prepare("INSERT INTO pacotes (
                id, paciente_id, tipo_pacote, data_inicio, data_fim, 
                valor_total, forma_pagamento, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $result = $stmt->execute([
                $id,
                sanitize($input['paciente_id']),
                $input['tipo_pacote'],
                formatDateToISO($input['data_inicio']),
                isset($input['data_fim']) ? formatDateToISO($input['data_fim']) : null,
                floatval($input['valor_total']),
                $input['forma_pagamento'],
                'Ativo'
            ]);

            if ($result) {
                $stmt = $db->prepare("SELECT p.*, pa.nome as paciente_nome FROM pacotes p LEFT JOIN pacientes pa ON p.paciente_id = pa.id WHERE p.id = ?");
                $stmt->execute([$id]);
                $pacote = $stmt->fetch();
                $pacote['valor_total'] = floatval($pacote['valor_total']);
                $pacote['data_inicio'] = formatDateToBR($pacote['data_inicio']);
                $pacote['data_fim'] = $pacote['data_fim'] ? formatDateToBR($pacote['data_fim']) : null;

                successResponse($pacote, 'Pacote criado com sucesso', 201);
            } else {
                errorResponse('Erro ao criar pacote', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao criar pacote', 500);
        }
        break;

    case 'PUT':
        $input = getJsonInput();
        if (empty($input['id'])) {
            errorResponse('ID do pacote é obrigatório', 400);
        }

        try {
            $stmt = $db->prepare("SELECT id FROM pacotes WHERE id = ?");
            $stmt->execute([$input['id']]);
            if (!$stmt->fetch()) {
                errorResponse('Pacote não encontrado', 404);
            }

            $stmt = $db->prepare("UPDATE pacotes SET 
                tipo_pacote = ?, data_inicio = ?, data_fim = ?, 
                valor_total = ?, forma_pagamento = ?, status = ?
            WHERE id = ?");

            $result = $stmt->execute([
                $input['tipo_pacote'] ?? 'Mensal',
                formatDateToISO($input['data_inicio'] ?? ''),
                isset($input['data_fim']) ? formatDateToISO($input['data_fim']) : null,
                floatval($input['valor_total'] ?? 0),
                $input['forma_pagamento'] ?? 'Pix',
                $input['status'] ?? 'Ativo',
                $input['id']
            ]);

            if ($result) {
                $stmt = $db->prepare("SELECT p.*, pa.nome as paciente_nome FROM pacotes p LEFT JOIN pacientes pa ON p.paciente_id = pa.id WHERE p.id = ?");
                $stmt->execute([$input['id']]);
                $pacote = $stmt->fetch();
                $pacote['valor_total'] = floatval($pacote['valor_total']);
                $pacote['data_inicio'] = formatDateToBR($pacote['data_inicio']);
                $pacote['data_fim'] = $pacote['data_fim'] ? formatDateToBR($pacote['data_fim']) : null;

                successResponse($pacote, 'Pacote atualizado com sucesso');
            } else {
                errorResponse('Erro ao atualizar pacote', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao atualizar pacote', 500);
        }
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if (empty($id)) {
            errorResponse('ID do pacote é obrigatório', 400);
        }

        try {
            $stmt = $db->prepare("SELECT id FROM pacotes WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                errorResponse('Pacote não encontrado', 404);
            }

            $stmt = $db->prepare("DELETE FROM pacotes WHERE id = ?");
            $result = $stmt->execute([$id]);

            if ($result) {
                successResponse([], 'Pacote removido com sucesso');
            } else {
                errorResponse('Erro ao remover pacote', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao remover pacote', 500);
        }
        break;

    default:
        errorResponse('Método não permitido', 405);
}