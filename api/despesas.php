<?php
/**
 * despesas.php
 * CRUD de despesas
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        requirePermission('despesas', 'visualizar');
        // Listar despesas com filtros
        try {
            $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
            $ativa = isset($_GET['ativa']) ? ($_GET['ativa'] === 'true' || $_GET['ativa'] === '1') : null;
            $id = isset($_GET['id']) ? trim($_GET['id']) : '';
            
            $where = [];
            $params = [];
            
            if (!empty($id)) {
                $where[] = "id = ?";
                $params[] = $id;
            }
            if (!empty($categoria)) {
                $where[] = "categoria = ?";
                $params[] = $categoria;
            }
            if ($ativa !== null) {
                $where[] = "ativo = ?";
                $params[] = $ativa ? 1 : 0;
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT * FROM despesas $whereClause ORDER BY criado_em DESC, id DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $despesas = $stmt->fetchAll();
            
            // Calcular status e formatar dados
            foreach ($despesas as &$despesa) {
                $despesa['valor_total'] = floatval($despesa['valor_total']);
                $despesa['num_parcelas'] = intval($despesa['num_parcelas']);
                $despesa['parcelas_pagas'] = intval($despesa['parcelas_pagas']);
                
                // Calcular valor da parcela
                $despesa['valor_parcela'] = $despesa['num_parcelas'] > 0 
                    ? round($despesa['valor_total'] / $despesa['num_parcelas'], 2) 
                    : 0;
                
                // Calcular parcelas restantes
                $despesa['parcelas_restantes'] = $despesa['num_parcelas'] - $despesa['parcelas_pagas'];
                
                // Definir status
                if ($despesa['parcelas_pagas'] >= $despesa['num_parcelas']) {
                    $despesa['status'] = 'Paga';
                } else {
                    $despesa['status'] = $despesa['parcelas_pagas'] . '/' . $despesa['num_parcelas'];
                }
                
                // Formatando data de início
                if (!empty($despesa['data_inicio'])) {
                    $despesa['data_inicio'] = formatDateToBR($despesa['data_inicio']);
                }
            }
            
            if (!empty($id) && count($despesas) === 1) {
                successResponse($despesas[0], 'Despesa encontrada');
            } else {
                successResponse($despesas, 'Despesas listadas');
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao listar despesas', 500);
        }
        break;
        
    case 'POST':
        requirePermission('despesas', 'criar');
        // Criar nova despesa
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        // Validações
        $errors = [];
        if (empty($input['descricao'])) $errors[] = validateField($input, 'descricao', 'Descrição');
        if (empty($input['categoria'])) $errors[] = validateField($input, 'categoria', 'Categoria');
        if (!isset($input['valor_total']) || $input['valor_total'] <= 0) $errors[] = 'Valor total deve ser maior que zero';
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        try {
            // Gerar ID
            $id = 'desp_' . time() . '_' . substr(md5(uniqid()), 0, 6);
            
            $numParcelas = isset($input['num_parcelas']) ? max(1, intval($input['num_parcelas'])) : 1;
            $parcelasPagas = isset($input['parcelas_pagas']) ? max(0, intval($input['parcelas_pagas'])) : 0;
            
            $stmt = $db->prepare("INSERT INTO despesas (
                id, descricao, categoria, valor_total, num_parcelas, parcelas_pagas,
                dia_vencimento, data_inicio
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $id,
                sanitize($input['descricao']),
                $input['categoria'],
                floatval($input['valor_total']),
                $numParcelas,
                $parcelasPagas,
                isset($input['dia_vencimento']) ? intval($input['dia_vencimento']) : null,
                isset($input['data_inicio']) ? formatDateToISO($input['data_inicio']) : null
            ]);
            
            if ($result) {
                // Buscar despesa criada
                $stmt = $db->prepare("SELECT * FROM despesas WHERE id = ?");
                $stmt->execute([$id]);
                $despesa = $stmt->fetch();
                $despesa['valor_total'] = floatval($despesa['valor_total']);
                $despesa['num_parcelas'] = intval($despesa['num_parcelas']);
                $despesa['parcelas_pagas'] = intval($despesa['parcelas_pagas']);
                $despesa['valor_parcela'] = round($despesa['valor_total'] / $despesa['num_parcelas'], 2);
                $despesa['parcelas_restantes'] = $despesa['num_parcelas'] - $despesa['parcelas_pagas'];
                $despesa['status'] = $despesa['parcelas_pagas'] >= $despesa['num_parcelas'] 
                    ? 'Paga' 
                    : $despesa['parcelas_pagas'] . '/' . $despesa['num_parcelas'];
                
                if (!empty($despesa['data_inicio'])) {
                    $despesa['data_inicio'] = formatDateToBR($despesa['data_inicio']);
                }
                
                successResponse($despesa, 'Despesa cadastrada com sucesso', 201);
            } else {
                errorResponse('Erro ao cadastrar despesa', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao cadastrar despesa', 500);
        }
        break;
        
    case 'PUT':
        requirePermission('despesas', 'editar');
        // Atualizar despesa
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        if (empty($input['id'])) {
            errorResponse('ID do despesa é obrigatório', 400);
        }
        
        try {
            // Verificar se despesa existe
            $stmt = $db->prepare("SELECT id FROM despesas WHERE id = ?");
            $stmt->execute([$input['id']]);
            if (!$stmt->fetch()) {
                errorResponse('Despesa não encontrada', 404);
            }
            
            $stmt = $db->prepare("UPDATE despesas SET 
                descricao = ?, categoria = ?, valor_total = ?, num_parcelas = ?, 
                parcelas_pagas = ?, dia_vencimento = ?, data_inicio = ?
            WHERE id = ?");
            
            $numParcelas = isset($input['num_parcelas']) ? max(1, intval($input['num_parcelas'])) : 1;
            $parcelasPagas = isset($input['parcelas_pagas']) ? max(0, intval($input['parcelas_pagas'])) : 0;
            
            $result = $stmt->execute([
                sanitize($input['descricao'] ?? ''),
                $input['categoria'] ?? 'Fixa',
                isset($input['valor_total']) ? floatval($input['valor_total']) : 0,
                $numParcelas,
                $parcelasPagas,
                isset($input['dia_vencimento']) ? intval($input['dia_vencimento']) : null,
                isset($input['data_inicio']) ? formatDateToISO($input['data_inicio']) : null,
                $input['id']
            ]);
            
            if ($result) {
                $stmt = $db->prepare("SELECT * FROM despesas WHERE id = ?");
                $stmt->execute([$input['id']]);
                $despesa = $stmt->fetch();
                $despesa['valor_total'] = floatval($despesa['valor_total']);
                $despesa['num_parcelas'] = intval($despesa['num_parcelas']);
                $despesa['parcelas_pagas'] = intval($despesa['parcelas_pagas']);
                $despesa['valor_parcela'] = round($despesa['valor_total'] / $despesa['num_parcelas'], 2);
                $despesa['parcelas_restantes'] = $despesa['num_parcelas'] - $despesa['parcelas_pagas'];
                $despesa['status'] = $despesa['parcelas_pagas'] >= $despesa['num_parcelas'] 
                    ? 'Paga' 
                    : $despesa['parcelas_pagas'] . '/' . $despesa['num_parcelas'];
                
                if (!empty($despesa['data_inicio'])) {
                    $despesa['data_inicio'] = formatDateToBR($despesa['data_inicio']);
                }
                
                successResponse($despesa, 'Despesa atualizada com sucesso');
            } else {
                errorResponse('Erro ao atualizar despesa', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao atualizar despesa', 500);
        }
        break;
        
    case 'DELETE':
        requirePermission('despesas', 'excluir');
        // Excluir despesa
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if (empty($id)) {
            errorResponse('ID do despesa é obrigatório', 400);
        }
        
        try {
            // Verificar se despesa existe
            $stmt = $db->prepare("SELECT id FROM despesas WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                errorResponse('Despesa não encontrada', 404);
            }
            
            $stmt = $db->prepare("DELETE FROM despesas WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                successResponse([], 'Despesa removida com sucesso');
            } else {
                errorResponse('Erro ao remover despesa', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao remover despesa', 500);
        }
        break;
        
    case 'PATCH':
        requirePermission('despesas', 'editar');
        // Pagar parcela (endpoint específico)
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        if (empty($input['id'])) {
            errorResponse('ID do despesa é obrigatório', 400);
        }
        
        try {
            // Verificar se despesa existe
            $stmt = $db->prepare("SELECT id, parcelas_pagas, num_parcelas FROM despesas WHERE id = ?");
            $stmt->execute([$input['id']]);
            $despesa = $stmt->fetch();
            
            if (!$despesa) {
                errorResponse('Despesa não encontrada', 404);
            }
            
            if ($despesa['parcelas_pagas'] >= $despesa['num_parcelas']) {
                errorResponse('Todas as parcelas já estão pagas', 400);
            }
            
            // Incrementar parcelas pagas
            $stmt = $db->prepare("UPDATE despesas SET parcelas_pagas = parcelas_pagas + 1 WHERE id = ?");
            $result = $stmt->execute([$input['id']]);
            
            if ($result) {
                $stmt = $db->prepare("SELECT * FROM despesas WHERE id = ?");
                $stmt->execute([$input['id']]);
                $despesa = $stmt->fetch();
                $despesa['valor_total'] = floatval($despesa['valor_total']);
                $despesa['num_parcelas'] = intval($despesa['num_parcelas']);
                $despesa['parcelas_pagas'] = intval($despesa['parcelas_pagas']);
                $despesa['valor_parcela'] = round($despesa['valor_total'] / $despesa['num_parcelas'], 2);
                $despesa['parcelas_restantes'] = $despesa['num_parcelas'] - $despesa['parcelas_pagas'];
                $despesa['status'] = $despesa['parcelas_pagas'] >= $despesa['num_parcelas'] 
                    ? 'Paga' 
                    : $despesa['parcelas_pagas'] . '/' . $despesa['num_parcelas'];
                
                successResponse($despesa, 'Parcela paga com sucesso');
            } else {
                errorResponse('Erro ao registrar pagamento', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao registrar pagamento', 500);
        }
        break;
        
    default:
        errorResponse('Método não permitido', 405);
        break;
}