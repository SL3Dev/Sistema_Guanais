<?php
/**
 * pacotes.php
 * CRUD de pacotes de pacientes
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        // Listar pacotes por paciente ou todos
        try {
            $pacienteId = isset($_GET['paciente_id']) ? $_GET['paciente_id'] : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            
            $where = [];
            $params = [];
            
            if ($pacienteId) {
                $where[] = "paciente_id = ?";
                $params[] = $pacienteId;
            }
            if ($status) {
                $where[] = "status = ?";
                $params[] = $status;
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT * FROM pacotes $whereClause ORDER BY data_inicio DESC, criado_em DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $pacotes = $stmt->fetchAll();
            
            // Calcular status e sessões restantes
            foreach ($pacotes as &$pacote) {
                $pacote['valor_total'] = floatval($pacote['valor_total']);
                $pacote['num_parcelas'] = intval($pacote['num_parcelas']);
                
                // Calcular sessões restantes (aproximado)
                if ($pacote['tipo_pacote'] === 'Mensal') {
                    $pacote['sessoes_estimadas'] = 4; // 4 sessões por mês
                } elseif ($pacote['tipo_pacote'] === 'Quinzenal') {
                    $pacote['sessoes_estimadas'] = 2; // 2 sessões por quinzena
                } else {
                    $pacote['sessoes_estimadas'] = 1; // Avulso
                }
                
                // Contar sessões já realizadas
                $stmt = $db->prepare("SELECT COUNT(*) as sessoes_realizadas FROM atendimentos WHERE paciente_id = ? AND data_atendimento >= ? AND status = 'Confirmado'");
                $stmt->execute([$pacote['paciente_id'], $pacote['data_inicio']]);
                $sessoes = $stmt->fetch();
                $pacote['sessoes_realizadas'] = $sessoes['sessoes_realizadas'];
                
                // Calcular sessões restantes
                $pacote['sessoes_restantes'] = max(0, $pacote['sessoes_estimadas'] - $pacote['sessoes_realizadas']);
                
                // Definir status detalhado
                if ($pacote['status'] === 'Ativo') {
                    if ($pacote['sessoes_restantes'] === 0) {
                        $pacote['status_detalhado'] = 'Pacote concluído';
                    } else {
                        $pacote['status_detalhado'] = $pacote['sessoes_restantes'] . ' sessões restantes';
                    }
                } else {
                    $pacote['status_detalhado'] = $pacote['status'];
                }
            }
            
            successResponse($pacotes, 'Pacotes listados');
        } catch (PDOException $e) {
            errorResponse('Erro ao listar pacotes', 500);
        }
        break;
        
    case 'POST':
        // Criar novo pacote
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        // Validações
        $errors = [];
        if (empty($input['paciente_id'])) $errors[] = validateField($input, 'paciente_id', 'Paciente');
        if (empty($input['tipo_pacote'])) $errors[] = validateField($input, 'tipo_pacote', 'Tipo de pacote');
        if (empty($input['data_inicio'])) $errors[] = validateField($input, 'data_inicio', 'Data de início');
        if (!isset($input['valor_total']) || $input['valor_total'] <= 0) $errors[] = 'Valor total deve ser maior que zero';
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        try {
            // Gerar ID
            $id = 'pac_' . time() . '_' . substr(md5(uniqid()), 0, 6);
            
            // Calcular data de fim (aproximada)
            $dataInicio = new DateTime($input['data_inicio']);
            $dataFim = clone $dataInicio;
            
            if ($input['tipo_pacote'] === 'Mensal') {
                $dataFim->modify('+1 month');
            } elseif ($input['tipo_pacote'] === 'Quinzenal') {
                $dataFim->modify('+15 days');
            }
            
            $stmt = $db->prepare("INSERT INTO pacotes (
                id, paciente_id, tipo_pacote, data_inicio, data_fim, valor_total, forma_pagamento, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $id,
                sanitize($input['paciente_id']),
                $input['tipo_pacote'],
                formatDateToISO($input['data_inicio']),
                $dataFim->format('Y-m-d'),
                floatval($input['valor_total']),
                $input['forma_pagamento'] ?? 'Pix',
                'Ativo'
            ]);
            
            if ($result) {
                // Buscar pacote criado
                $stmt = $db->prepare("SELECT * FROM pacotes WHERE id = ?");
                $stmt->execute([$id]);
                $pacote = $stmt->fetch();
                $pacote['valor_total'] = floatval($pacote['valor_total']);
                
                successResponse($pacote, 'Pacote cadastrado com sucesso', 201);
            } else {
                errorResponse('Erro ao cadastrar pacote', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao cadastrar pacote', 500);
        }
        break;
        
    case 'PUT':
        // Atualizar pacote
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        if (empty($input['id'])) {
            errorResponse('ID do pacote é obrigatório', 400);
        }
        
        try {
            // Verificar se pacote existe
            $stmt = $db->prepare("SELECT id FROM pacotes WHERE id = ?");
            $stmt->execute([$input['id']]);
            if (!$stmt->fetch()) {
                errorResponse('Pacote não encontrado', 404);
            }
            
            $stmt = $db->prepare("UPDATE pacotes SET 
                tipo_pacote = ?, data_inicio = ?, data_fim = ?, valor_total = ?, forma_pagamento = ?, status = ?
            WHERE id = ?");
            
            $result = $stmt->execute([
                $input['tipo_pacote'] ?? 'Avulso',
                isset($input['data_inicio']) ? formatDateToISO($input['data_inicio']) : null,
                isset($input['data_fim']) ? formatDateToISO($input['data_fim']) : null,
                isset($input['valor_total']) ? floatval($input['valor_total']) : 0,
                $input['forma_pagamento'] ?? 'Pix',
                $input['status'] ?? 'Ativo',
                $input['id']
            ]);
            
            if ($result) {
                $stmt = $db->prepare("SELECT * FROM pacotes WHERE id = ?");
                $stmt->execute([$input['id']]);
                $pacote = $stmt->fetch();
                $pacote['valor_total'] = floatval($pacote['valor_total']);
                
                successResponse($pacote, 'Pacote atualizado com sucesso');
            } else {
                errorResponse('Erro ao atualizar pacote', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao atualizar pacote', 500);
        }
        break;
        
    case 'DELETE':
        // Excluir pacote
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if (empty($id)) {
            errorResponse('ID do pacote é obrigatório', 400);
        }
        
        try {
            // Verificar se pacote existe
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
        break;
}