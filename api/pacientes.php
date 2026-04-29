<?php
/**
 * pacientes.php
 * CRUD de pacientes
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        requirePermission('pacientes', 'visualizar');
        // Listar todos os pacientes ou buscar por ID
        try {
            if (isset($_GET['id'])) {
                // Verificar se é para buscar informações completas (com pacote e atendimentos)
                if (isset($_GET['completo']) && $_GET['completo'] == 'true') {
                    // Buscar paciente específico
                    $stmt = $db->prepare("SELECT * FROM pacientes WHERE id = ? AND ativo = 1");
                    $stmt->execute([$_GET['id']]);
                    $paciente = $stmt->fetch();
                    
                    if (!$paciente) {
                        errorResponse('Paciente não encontrado', 404);
                    }
                    
                    // Formatar dados básicos
                    $paciente['data_nascimento'] = formatDateToBR($paciente['data_nascimento']);
                    
                    // Inicializar valores padrão
                    $paciente['pacote'] = null;
                    $paciente['total_atendimentos'] = 0;
                    $paciente['total_faltas'] = 0;
                    
                    // Buscar pacote ativo mais recente
                    $stmt = $db->prepare("SELECT * FROM pacotes WHERE paciente_id = ? AND status = 'Ativo' ORDER BY data_inicio DESC LIMIT 1");
                    $stmt->execute([$_GET['id']]);
                    $pacote = $stmt->fetch();
                    
                    if ($pacote) {
                        // Converter data ISO para BR se necessário
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pacote['data_inicio'])) {
                            $pacote['data_inicio'] = formatDateToBR($pacote['data_inicio']);
                        }
                        if ($pacote['data_fim'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', $pacote['data_fim'])) {
                            $pacote['data_fim'] = formatDateToBR($pacote['data_fim']);
                        }
                        
                        // Calcular sessões realizadas no pacote atual
                        $stmt = $db->prepare("SELECT COUNT(*) as total FROM atendimentos WHERE paciente_id = ? AND data_atendimento >= ? AND status = 'Confirmado'");
                        $stmt->execute([$_GET['id'], formatDateToISO($pacote['data_inicio'])]);
                        $sessoes = $stmt->fetch();
                        $pacote['sessoes_realizadas'] = intval($sessoes['total']);
                        
                        // Calcular sessões estimadas e restantes
                        if ($pacote['tipo_pacote'] === 'Mensal') {
                            $pacote['sessoes_estimadas'] = 4;
                        } elseif ($pacote['tipo_pacote'] === 'Quinzenal') {
                            $pacote['sessoes_estimadas'] = 2;
                        } else {
                            $pacote['sessoes_estimadas'] = 1;
                        }
                        $pacote['sessoes_restantes'] = max(0, $pacote['sessoes_estimadas'] - $pacote['sessoes_realizadas']);
                        
                        $paciente['pacote'] = $pacote;
                    }
                    
                    // Contar total de atendimentos (independente do pacote)
                    $stmt = $db->prepare("SELECT COUNT(*) as total FROM atendimentos WHERE paciente_id = ? AND status = 'Confirmado'");
                    $stmt->execute([$_GET['id']]);
                    $atendimentos = $stmt->fetch();
                    $paciente['total_atendimentos'] = intval($atendimentos['total']);
                    
                    // Contar faltas
                    $stmt = $db->prepare("SELECT COUNT(*) as total FROM atendimentos WHERE paciente_id = ? AND status = 'Falta'");
                    $stmt->execute([$_GET['id']]);
                    $faltas = $stmt->fetch();
                    $paciente['total_faltas'] = intval($faltas['total']);
                    
                    // Buscar último atendimento para preenchimento automático
                    $stmt = $db->prepare("SELECT tipo_pacote, data_atendimento, data_inicio_pacote, unidade FROM atendimentos WHERE paciente_id = ? ORDER BY data_atendimento DESC, criado_em DESC LIMIT 1");
                    $stmt->execute([$_GET['id']]);
                    $ultimoAtendimento = $stmt->fetch();
                    
                    if ($ultimoAtendimento) {
                        $ultimoAtendimento['data_atendimento'] = formatDateToBR($ultimoAtendimento['data_atendimento']);
                        $ultimoAtendimento['data_inicio_pacote'] = formatDateToBR($ultimoAtendimento['data_inicio_pacote']);
                        $paciente['ultimo_atendimento'] = $ultimoAtendimento;
                    } else {
                        $paciente['ultimo_atendimento'] = null;
                    }
                    
                    successResponse($paciente, 'Paciente encontrado com informações completas');
                } else {
                    // Busca simples (comportamento original)
                    $stmt = $db->prepare("SELECT * FROM pacientes WHERE id = ? AND ativo = 1");
                    $stmt->execute([$_GET['id']]);
                    $paciente = $stmt->fetch();
                    
                    if (!$paciente) {
                        errorResponse('Paciente não encontrado', 404);
                    }
                    
                    // Formatar dados
                    $paciente['data_nascimento'] = formatDateToBR($paciente['data_nascimento']);
                    
                    successResponse($paciente, 'Paciente encontrado');
                }
            } else {
                // Listar todos com busca opcional
                $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
                
                if (!empty($busca)) {
                    $stmt = $db->prepare("SELECT * FROM pacientes WHERE ativo = 1 AND (nome LIKE ? OR cpf LIKE ? OR telefone LIKE ?) ORDER BY nome ASC");
                    $buscaParam = "%$busca%";
                    $stmt->execute([$buscaParam, $buscaParam, $buscaParam]);
                } else {
                    $stmt = $db->query("SELECT * FROM pacientes WHERE ativo = 1 ORDER BY nome ASC");
                }
                
                $pacientes = $stmt->fetchAll();
                
                // Formatar dados
                foreach ($pacientes as &$paciente) {
                    $paciente['data_nascimento'] = formatDateToBR($paciente['data_nascimento']);
                }
                
                successResponse($pacientes, 'Pacientes listados');
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao listar pacientes', 500);
        }
        break;
        
    case 'POST':
        requirePermission('pacientes', 'criar');
        // Criar novo paciente
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        // Validações
        $errors = [];
        if (empty($input['nome'])) $errors[] = validateField($input, 'nome', 'Nome');
        if (empty($input['data_nascimento'])) $errors[] = validateField($input, 'data_nascimento', 'Data de nascimento');
        if (empty($input['telefone'])) $errors[] = validateField($input, 'telefone', 'Telefone');
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        try {
            // Gerar ID
            $id = generateId('P');
            
            $stmt = $db->prepare("INSERT INTO pacientes (
                id, nome, cpf, data_nascimento, telefone, email, endereco,
                responsavel_nome, responsavel_telefone, emergencia_nome, emergencia_telefone,
                emergencia_parentesco, emergencia_info_adicionais
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $id,
                sanitize($input['nome']),
                isset($input['cpf']) ? sanitize($input['cpf']) : null,
                formatDateToISO($input['data_nascimento']),
                sanitize($input['telefone']),
                isset($input['email']) ? sanitize($input['email']) : null,
                isset($input['endereco']) ? sanitize($input['endereco']) : null,
                isset($input['responsavel_nome']) ? sanitize($input['responsavel_nome']) : null,
                isset($input['responsavel_telefone']) ? sanitize($input['responsavel_telefone']) : null,
                isset($input['emergencia_nome']) ? sanitize($input['emergencia_nome']) : null,
                isset($input['emergencia_telefone']) ? sanitize($input['emergencia_telefone']) : null,
                isset($input['emergencia_parentesco']) ? sanitize($input['emergencia_parentesco']) : null,
                isset($input['emergencia_info_adicionais']) ? sanitize($input['emergencia_info_adicionais']) : null
            ]);
            
            if ($result) {
                // Buscar paciente criado
                $stmt = $db->prepare("SELECT * FROM pacientes WHERE id = ?");
                $stmt->execute([$id]);
                $paciente = $stmt->fetch();
                $paciente['data_nascimento'] = formatDateToBR($paciente['data_nascimento']);
                
                successResponse($paciente, 'Paciente cadastrado com sucesso', 201);
            } else {
                errorResponse('Erro ao cadastrar paciente', 500);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                errorResponse('Já existe um paciente com este CPF', 409);
            }
            errorResponse('Erro ao cadastrar paciente', 500);
        }
        break;
        
    case 'PUT':
        requirePermission('pacientes', 'editar');
        // Atualizar paciente
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        if (empty($input['id'])) {
            errorResponse('ID do paciente é obrigatório', 400);
        }
        
        // Validações
        $errors = [];
        if (empty($input['nome'])) $errors[] = validateField($input, 'nome', 'Nome');
        if (empty($input['data_nascimento'])) $errors[] = validateField($input, 'data_nascimento', 'Data de nascimento');
        if (empty($input['telefone'])) $errors[] = validateField($input, 'telefone', 'Telefone');
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        try {
            // Verificar se paciente existe
            $stmt = $db->prepare("SELECT id FROM pacientes WHERE id = ?");
            $stmt->execute([$input['id']]);
            if (!$stmt->fetch()) {
                errorResponse('Paciente não encontrado', 404);
            }
            
            $stmt = $db->prepare("UPDATE pacientes SET 
                nome = ?, cpf = ?, data_nascimento = ?, telefone = ?, email = ?, endereco = ?,
                responsavel_nome = ?, responsavel_telefone = ?, emergencia_nome = ?, emergencia_telefone = ?,
                emergencia_parentesco = ?, emergencia_info_adicionais = ?
            WHERE id = ?");
            
            $result = $stmt->execute([
                sanitize($input['nome']),
                isset($input['cpf']) ? sanitize($input['cpf']) : null,
                formatDateToISO($input['data_nascimento']),
                sanitize($input['telefone']),
                isset($input['email']) ? sanitize($input['email']) : null,
                isset($input['endereco']) ? sanitize($input['endereco']) : null,
                isset($input['responsavel_nome']) ? sanitize($input['responsavel_nome']) : null,
                isset($input['responsavel_telefone']) ? sanitize($input['responsavel_telefone']) : null,
                isset($input['emergencia_nome']) ? sanitize($input['emergencia_nome']) : null,
                isset($input['emergencia_telefone']) ? sanitize($input['emergencia_telefone']) : null,
                isset($input['emergencia_parentesco']) ? sanitize($input['emergencia_parentesco']) : null,
                isset($input['emergencia_info_adicionais']) ? sanitize($input['emergencia_info_adicionais']) : null,
                $input['id']
            ]);
            
            if ($result) {
                $stmt = $db->prepare("SELECT * FROM pacientes WHERE id = ?");
                $stmt->execute([$input['id']]);
                $paciente = $stmt->fetch();
                $paciente['data_nascimento'] = formatDateToBR($paciente['data_nascimento']);
                
                successResponse($paciente, 'Paciente atualizado com sucesso');
            } else {
                errorResponse('Erro ao atualizar paciente', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao atualizar paciente', 500);
        }
        break;
        
    case 'DELETE':
        requirePermission('pacientes', 'excluir');
        // Excluir paciente (soft delete)
        $id = isset($_GET['id']) ? $_GET['id'] : (isset($input['id']) ? $input['id'] : null);
        
        if (empty($id)) {
            errorResponse('ID do paciente é obrigatório', 400);
        }
        
        try {
            // Verificar se paciente existe
            $stmt = $db->prepare("SELECT id FROM pacientes WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                errorResponse('Paciente não encontrado', 404);
            }
            
            // Soft delete
            $stmt = $db->prepare("UPDATE pacientes SET ativo = 0 WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                successResponse([], 'Paciente removido com sucesso');
            } else {
                errorResponse('Erro ao remover paciente', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao remover paciente', 500);
        }
        break;
        
    default:
        errorResponse('Método não permitido', 405);
        break;
}