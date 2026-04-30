<?php
/**
 * financeiro.php
 * CRUD de lançamentos financeiros (recebimentos)
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        requirePermission('financeiro', 'visualizar');
        // Listar lançamentos financeiros com filtros
        try {
            $mes = isset($_GET['mes']) ? trim($_GET['mes']) : '';
            $clinica = isset($_GET['clinica']) ? trim($_GET['clinica']) : '';
            $paciente_id = isset($_GET['paciente_id']) ? trim($_GET['paciente_id']) : '';
            $id = isset($_GET['id']) ? trim($_GET['id']) : '';
            
            $where = [];
            $params = [];
            
            if (!empty($id)) {
                $where[] = "f.id = ?";
                $params[] = $id;
            }
            if (!empty($mes)) {
                // Espera formato YYYY-MM
                $where[] = "f.data LIKE ?";
                $params[] = "$mes%";
            }
            if (!empty($clinica)) {
                $where[] = "f.clinica = ?";
                $params[] = $clinica;
            }
            if (!empty($paciente_id)) {
                $where[] = "f.paciente_id = ?";
                $params[] = $paciente_id;
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT f.*, p.cpf, p.telefone as telefone_paciente 
                    FROM financeiro f 
                    LEFT JOIN pacientes p ON f.paciente_id = p.id 
                    $whereClause 
                    ORDER BY f.data DESC, f.id DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $lancamentos = $stmt->fetchAll();
            
            // Calcular totais
            $totalBruto = 0;
            foreach ($lancamentos as &$lancamento) {
                $totalBruto += floatval($lancamento['valor']);
                $lancamento['valor'] = floatval($lancamento['valor']);
                $lancamento['data'] = formatDateToBR($lancamento['data']);
                $lancamento['liquido'] = round($lancamento['valor'] * 0.75, 2);
            }
            
            $custo = round($totalBruto * 0.25, 2);
            $liquido = round($totalBruto - $custo, 2);

            // Buscar despesas pagas no mês (Simplificado)
            $stmtDespesas = $db->prepare("SELECT SUM(valor_total / num_parcelas) as despesas_pagas 
                                        FROM despesas 
                                        WHERE parcelas_pagas > 0 
                                        AND (data_inicio <= ? OR dia_vencimento IS NOT NULL)");
            $stmtDespesas->execute([$mes . '-31']);
            $despesasResumo = $stmtDespesas->fetch();
            $despesasPagas = round(floatval($despesasResumo['despesas_pagas'] ?? 0), 2);
            
            $resumo = [
                'total_bruto' => $totalBruto,
                'custo' => $custo,
                'liquido' => $liquido,
                'despesas_pagas' => $despesasPagas,
                'saldo_real' => round($liquido - $despesasPagas, 2),
                'total_lancamentos' => count($lancamentos)
            ];
            
            if (!empty($id) && count($lancamentos) === 1) {
                successResponse($lancamentos[0], 'Lançamento encontrado');
            } else {
                successResponse([
                    'lancamentos' => $lancamentos,
                    'resumo' => $resumo
                ], 'Lançamentos listados');
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao listar lançamentos financeiros', 500);
        }
        break;
        
    case 'POST':
        requirePermission('financeiro', 'criar');
        // Criar novo lançamento financeiro
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        // Validações
        $errors = [];
        if (empty($input['paciente_id'])) $errors[] = validateField($input, 'paciente_id', 'Paciente');
        if (empty($input['paciente_nome'])) $errors[] = validateField($input, 'paciente_nome', 'Nome do paciente');
        if (empty($input['clinica'])) $errors[] = validateField($input, 'clinica', 'Clínica');
        if (empty($input['data'])) $errors[] = validateField($input, 'data', 'Data');
        if (!isset($input['valor']) || $input['valor'] <= 0) $errors[] = 'Valor deve ser maior que zero';
        if (empty($input['forma_pagamento'])) $errors[] = validateField($input, 'forma_pagamento', 'Forma de pagamento');
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        try {
            // Gerar ID
            $id = 'fin_' . time() . '_' . substr(md5(uniqid()), 0, 6);
            
            // Calcular automaticamente despesas (25%) e receita disponível (75%)
            $valor = floatval($input['valor']);
            $despesaAutomatica = round($valor * 0.25, 2);
            $receitaDisponivel = round($valor - $despesaAutomatica, 2);
            
            $stmt = $db->prepare("INSERT INTO financeiro (
                id, paciente_id, paciente_nome, clinica, tipo_pacote, data_inicio_pacote, data, 
                valor, forma_pagamento, nf_emitida, observacoes,
                despesa_automatica, receita_disponivel
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $id,
                sanitize($input['paciente_id']),
                sanitize($input['paciente_nome']),
                $input['clinica'],
                isset($input['tipo_pacote']) ? $input['tipo_pacote'] : null,
                isset($input['data_inicio_pacote']) ? formatDateToISO($input['data_inicio_pacote']) : null,
                formatDateToISO($input['data']),
                $valor,
                $input['forma_pagamento'],
                isset($input['nf_emitida']) ? ($input['nf_emitida'] ? 1 : 0) : 0,
                isset($input['observacoes']) ? sanitize($input['observacoes']) : null,
                $despesaAutomatica,
                $receitaDisponivel
            ]);
            
            if ($result) {
                // Buscar lançamento criado
                $stmt = $db->prepare("SELECT * FROM financeiro WHERE id = ?");
                $stmt->execute([$id]);
                $lancamento = $stmt->fetch();
                $lancamento['data'] = formatDateToBR($lancamento['data']);
                $lancamento['valor'] = floatval($lancamento['valor']);
                $lancamento['liquido'] = round($lancamento['valor'] * 0.75, 2);
                
                successResponse($lancamento, 'Recebimento registrado com sucesso', 201);
            } else {
                errorResponse('Erro ao registrar recebimento no banco de dados', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro no banco de dados: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'PUT':
        requirePermission('financeiro', 'editar');
        // Atualizar lançamento financeiro
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        if (empty($input['id'])) {
            errorResponse('ID do lançamento é obrigatório', 400);
        }
        
        try {
            // Verificar se lançamento existe
            $stmt = $db->prepare("SELECT id FROM financeiro WHERE id = ?");
            $stmt->execute([$input['id']]);
            if (!$stmt->fetch()) {
                errorResponse('Lançamento não encontrado', 404);
            }
            
            $stmt = $db->prepare("UPDATE financeiro SET 
                paciente_id = ?, paciente_nome = ?, clinica = ?, tipo_pacote = ?, data_inicio_pacote = ?,
                data = ?, valor = ?, forma_pagamento = ?, nf_emitida = ?, observacoes = ?
            WHERE id = ?");
            
            $result = $stmt->execute([
                sanitize($input['paciente_id'] ?? ''),
                sanitize($input['paciente_nome'] ?? ''),
                $input['clinica'] ?? 'ANIMO',
                isset($input['tipo_pacote']) ? $input['tipo_pacote'] : null,
                isset($input['data_inicio_pacote']) ? formatDateToISO($input['data_inicio_pacote']) : null,
                isset($input['data']) ? formatDateToISO($input['data']) : null,
                isset($input['valor']) ? floatval($input['valor']) : 0,
                $input['forma_pagamento'] ?? 'Pix',
                isset($input['nf_emitida']) ? ($input['nf_emitida'] ? 1 : 0) : 0,
                isset($input['observacoes']) ? sanitize($input['observacoes']) : null,
                $input['id']
            ]);
            
            if ($result) {
                $stmt = $db->prepare("SELECT * FROM financeiro WHERE id = ?");
                $stmt->execute([$input['id']]);
                $lancamento = $stmt->fetch();
                $lancamento['data'] = formatDateToBR($lancamento['data']);
                $lancamento['valor'] = floatval($lancamento['valor']);
                $lancamento['liquido'] = round($lancamento['valor'] * 0.75, 2);
                
                successResponse($lancamento, 'Lançamento atualizado com sucesso');
            } else {
                errorResponse('Erro ao atualizar lançamento', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao atualizar lançamento', 500);
        }
        break;
        
    case 'DELETE':
        requirePermission('financeiro', 'excluir');
        // Excluir lançamento financeiro
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if (empty($id)) {
            errorResponse('ID do lançamento é obrigatório', 400);
        }
        
        try {
            // Verificar se lançamento existe
            $stmt = $db->prepare("SELECT id FROM financeiro WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                errorResponse('Lançamento não encontrado', 404);
            }
            
            $stmt = $db->prepare("DELETE FROM financeiro WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                successResponse([], 'Lançamento removido com sucesso');
            } else {
                errorResponse('Erro ao remover lançamento', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao remover lançamento', 500);
        }
        break;
        
    default:
        errorResponse('Método não permitido', 405);
        break;
}