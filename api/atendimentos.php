<?php
/**
 * atendimentos.php
 * CRUD de atendimentos
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

/**
 * Aplica a regra de exceção nos atendimentos
 * Se um atendimento Confirmado ultrapassar o limite de dias do pacote,
 * muda o status para "Exceção Justificada"
 */
function aplicarRegraExcecao($db) {
    try {
        $stmt = $db->query("SELECT id_atendimento, status, tipo_pacote, data_atendimento, data_inicio_pacote FROM atendimentos WHERE status = 'Confirmado' AND tipo_pacote != 'Avulso'");
        $atendimentos = $stmt->fetchAll();
        
        $alterados = 0;
        foreach ($atendimentos as $atendimento) {
            $dataAtual = new DateTime($atendimento['data_atendimento']);
            $dataInicio = new DateTime($atendimento['data_inicio_pacote']);
            $diff = $dataAtual->diff($dataInicio)->days;
            
            $limite = $atendimento['tipo_pacote'] === 'Mensal' ? 35 : 20;
            
            if ($diff > $limite) {
                $update = $db->prepare("UPDATE atendimentos SET status = 'Exceção Justificada' WHERE id_atendimento = ?");
                $update->execute([$atendimento['id_atendimento']]);
                $alterados++;
            }
        }
        
        return $alterados;
    } catch (PDOException $e) {
        return 0;
    }
}

switch ($method) {
    case 'GET':
        requirePermission('atendimentos', 'visualizar');
        // Listar atendimentos com filtros opcionais
        try {
            $nome = isset($_GET['nome']) ? trim($_GET['nome']) : '';
            $pacote = isset($_GET['pacote']) ? trim($_GET['pacote']) : '';
            $status = isset($_GET['status']) ? trim($_GET['status']) : '';
            $unidade = isset($_GET['unidade']) ? trim($_GET['unidade']) : '';
            $id = isset($_GET['id']) ? trim($_GET['id']) : '';
            
            $where = [];
            $params = [];
            
            if (!empty($id)) {
                $where[] = "a.id_atendimento = ?";
                $params[] = $id;
            }
            if (!empty($nome)) {
                $where[] = "a.paciente_nome LIKE ?";
                $params[] = "%$nome%";
            }
            if (!empty($pacote)) {
                $where[] = "a.tipo_pacote = ?";
                $params[] = $pacote;
            }
            if (!empty($status)) {
                $where[] = "a.status = ?";
                $params[] = $status;
            }
            if (!empty($unidade)) {
                $where[] = "a.unidade = ?";
                $params[] = $unidade;
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT a.*, p.cpf, p.telefone as telefone_paciente, p.email as email_paciente 
                    FROM atendimentos a 
                    LEFT JOIN pacientes p ON a.paciente_id = p.id 
                    $whereClause 
                    ORDER BY a.data_atendimento DESC, a.id_atendimento DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $atendimentos = $stmt->fetchAll();
            
            // Formatar dados
            foreach ($atendimentos as &$atendimento) {
                $atendimento['data_atendimento'] = formatDateToBR($atendimento['data_atendimento']);
                $atendimento['data_inicio_pacote'] = formatDateToBR($atendimento['data_inicio_pacote']);
            }
            
            // Calcular resumo
            $totalAtendimentos = count($atendimentos);
            $totalFaltas = 0;
            foreach ($atendimentos as $a) {
                if ($a['status'] === 'Falta') {
                    $totalFaltas++;
                }
            }
            
            $resumo = [
                'total_atendimentos' => $totalAtendimentos,
                'total_faltas' => $totalFaltas
            ];
            
            if (!empty($id) && count($atendimentos) === 1) {
                successResponse([
                    'atendimento' => $atendimentos[0],
                    'resumo' => $resumo
                ], 'Atendimento encontrado');
            } else {
                successResponse([
                    'atendimentos' => $atendimentos,
                    'resumo' => $resumo
                ], 'Atendimentos listados');
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao listar atendimentos', 500);
        }
        break;
        
    case 'POST':
        requirePermission('atendimentos', 'criar');
        // Criar novo atendimento
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        // Validações
        $errors = [];
        if (empty($input['paciente_id'])) $errors[] = validateField($input, 'paciente_id', 'Paciente');
        if (empty($input['paciente_nome'])) $errors[] = validateField($input, 'paciente_nome', 'Nome do paciente');
        if (empty($input['data_atendimento'])) $errors[] = validateField($input, 'data_atendimento', 'Data do atendimento');
        if (empty($input['tipo_pacote'])) $errors[] = validateField($input, 'tipo_pacote', 'Tipo de pacote');
        if (empty($input['data_inicio_pacote'])) $errors[] = validateField($input, 'data_inicio_pacote', 'Data de início do pacote');
        if (empty($input['status'])) $errors[] = validateField($input, 'status', 'Status');
        if (empty($input['unidade'])) $errors[] = validateField($input, 'unidade', 'Unidade');
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        try {
            // Gerar ID
            $id = generateId('A');
            
            $stmt = $db->prepare("INSERT INTO atendimentos (
                id_atendimento, paciente_id, paciente_nome, data_atendimento, 
                tipo_pacote, data_inicio_pacote, status, unidade, observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $id,
                sanitize($input['paciente_id']),
                sanitize($input['paciente_nome']),
                formatDateToISO($input['data_atendimento']),
                $input['tipo_pacote'],
                formatDateToISO($input['data_inicio_pacote']),
                $input['status'],
                $input['unidade'],
                isset($input['observacoes']) ? sanitize($input['observacoes']) : null
            ]);
            
            if ($result) {
                // Aplicar regra de exceção
                aplicarRegraExcecao($db);
                
                // Buscar atendimento criado
                $stmt = $db->prepare("SELECT * FROM atendimentos WHERE id_atendimento = ?");
                $stmt->execute([$id]);
                $atendimento = $stmt->fetch();
                $atendimento['data_atendimento'] = formatDateToBR($atendimento['data_atendimento']);
                $atendimento['data_inicio_pacote'] = formatDateToBR($atendimento['data_inicio_pacote']);
                
                successResponse($atendimento, 'Atendimento registrado com sucesso', 201);
            } else {
                errorResponse('Erro ao registrar atendimento', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao registrar atendimento', 500);
        }
        break;
        
    case 'PUT':
        requirePermission('atendimentos', 'editar');
        // Atualizar atendimento
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        if (empty($input['id_atendimento'])) {
            errorResponse('ID do atendimento é obrigatório', 400);
        }
        
        try {
            // Verificar se atendimento existe
            $stmt = $db->prepare("SELECT id_atendimento FROM atendimentos WHERE id_atendimento = ?");
            $stmt->execute([$input['id_atendimento']]);
            if (!$stmt->fetch()) {
                errorResponse('Atendimento não encontrado', 404);
            }
            
            $stmt = $db->prepare("UPDATE atendimentos SET 
                paciente_id = ?, paciente_nome = ?, data_atendimento = ?, 
                tipo_pacote = ?, data_inicio_pacote = ?, status = ?, unidade = ?, observacoes = ?
            WHERE id_atendimento = ?");
            
            $result = $stmt->execute([
                sanitize($input['paciente_id'] ?? ''),
                sanitize($input['paciente_nome'] ?? ''),
                isset($input['data_atendimento']) ? formatDateToISO($input['data_atendimento']) : null,
                $input['tipo_pacote'] ?? 'Avulso',
                isset($input['data_inicio_pacote']) ? formatDateToISO($input['data_inicio_pacote']) : null,
                $input['status'] ?? 'Confirmado',
                $input['unidade'] ?? 'ANIMO',
                isset($input['observacoes']) ? sanitize($input['observacoes']) : null,
                $input['id_atendimento']
            ]);
            
            if ($result) {
                // Aplicar regra de exceção
                aplicarRegraExcecao($db);
                
                $stmt = $db->prepare("SELECT * FROM atendimentos WHERE id_atendimento = ?");
                $stmt->execute([$input['id_atendimento']]);
                $atendimento = $stmt->fetch();
                $atendimento['data_atendimento'] = formatDateToBR($atendimento['data_atendimento']);
                $atendimento['data_inicio_pacote'] = formatDateToBR($atendimento['data_inicio_pacote']);
                
                successResponse($atendimento, 'Atendimento atualizado com sucesso');
            } else {
                errorResponse('Erro ao atualizar atendimento', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao atualizar atendimento', 500);
        }
        break;
        
    case 'DELETE':
        requirePermission('atendimentos', 'excluir');
        // Excluir atendimento
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if (empty($id)) {
            errorResponse('ID do atendimento é obrigatório', 400);
        }
        
        try {
            // Verificar se atendimento existe
            $stmt = $db->prepare("SELECT id_atendimento FROM atendimentos WHERE id_atendimento = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                errorResponse('Atendimento não encontrado', 404);
            }
            
            $stmt = $db->prepare("DELETE FROM atendimentos WHERE id_atendimento = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                successResponse([], 'Atendimento removido com sucesso');
            } else {
                errorResponse('Erro ao remover atendimento', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao remover atendimento', 500);
        }
        break;
        
    default:
        errorResponse('Método não permitido', 405);
        break;
}