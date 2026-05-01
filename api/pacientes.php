<?php
/**
 * pacientes.php
 * CRUD de pacientes
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

function selectPacienteComPsicologa($db, $whereSql, $params = [], $single = false) {
    $queries = [
        "SELECT p.*, u.nome AS psicologa_nome, u.email AS psicologa_email, u.foto_perfil AS psicologa_foto, u.abordagem AS psicologa_abordagem, u.tipo_psicoterapia AS psicologa_tipo_psicoterapia FROM pacientes p LEFT JOIN usuarios u ON u.id = p.psicologa_responsavel_id $whereSql",
        "SELECT p.*, u.nome AS psicologa_nome, u.email AS psicologa_email, u.foto_perfil AS psicologa_foto FROM pacientes p LEFT JOIN usuarios u ON u.id = p.psicologa_responsavel_id $whereSql",
        "SELECT p.*, u.nome AS psicologa_nome, u.email AS psicologa_email FROM pacientes p LEFT JOIN usuarios u ON u.id = p.psicologa_responsavel_id $whereSql",
        "SELECT p.* FROM pacientes p $whereSql"
    ];

    $lastError = null;
    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $single ? $stmt->fetch() : $stmt->fetchAll();
        } catch (PDOException $e) {
            $lastError = $e;
        }
    }

    if ($lastError) {
        throw $lastError;
    }

    return $single ? null : [];
}

function gerarProximoIdPaciente($db) {
    $stmt = $db->query("SELECT MAX(CAST(id AS UNSIGNED)) AS max_id FROM pacientes WHERE id REGEXP '^[0-9]+$'");
    $row = $stmt->fetch();
    $max = isset($row['max_id']) ? intval($row['max_id']) : 0;
    return strval($max + 1);
}

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
                    // Buscar paciente específico com dados da psicóloga responsável
                    $paciente = selectPacienteComPsicologa($db, "WHERE p.id = ? AND p.ativo = 1", [$_GET['id']], true);
                    
                    if (!$paciente) {
                        errorResponse('Paciente não encontrado', 404);
                    }
                    
                    // Formatar dados básicos
                    $paciente['data_nascimento'] = formatDateToBR($paciente['data_nascimento']);
                    
                    // Inicializar valores padrão
                    $paciente['pacote'] = null;
                    $paciente['total_atendimentos'] = 0;
                    $paciente['total_faltas'] = 0;
                    
                    // Buscar pacote ativo mais recente (fallback seguro caso tabela/coluna não exista)
                    $pacote = null;
                    try {
                        $stmt = $db->prepare("SELECT * FROM pacotes WHERE paciente_id = ? AND status = 'Ativo' ORDER BY data_inicio DESC LIMIT 1");
                        $stmt->execute([$_GET['id']]);
                        $pacote = $stmt->fetch();
                    } catch (PDOException $e) {
                        $pacote = null;
                    }
                    
                    if ($pacote) {
                        // Converter data ISO para BR se necessário
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pacote['data_inicio'])) {
                            $pacote['data_inicio'] = formatDateToBR($pacote['data_inicio']);
                        }
                        if ($pacote['data_fim'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', $pacote['data_fim'])) {
                            $pacote['data_fim'] = formatDateToBR($pacote['data_fim']);
                        }
                        
                        // Calcular sessões realizadas no pacote atual
                        try {
                            $stmt = $db->prepare("SELECT COUNT(*) as total FROM atendimentos WHERE paciente_id = ? AND data_atendimento >= ? AND status = 'Confirmado'");
                            $stmt->execute([$_GET['id'], formatDateToISO($pacote['data_inicio'])]);
                            $sessoes = $stmt->fetch();
                            $pacote['sessoes_realizadas'] = intval($sessoes['total']);
                        } catch (PDOException $e) {
                            $pacote['sessoes_realizadas'] = 0;
                        }
                        
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
                    $stmt = $db->prepare("SELECT tipo_pacote, data_atendimento, data_inicio_pacote, unidade FROM atendimentos WHERE paciente_id = ? ORDER BY data_atendimento DESC, id_atendimento DESC LIMIT 1");
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
                    $paciente = selectPacienteComPsicologa($db, "WHERE p.id = ? AND p.ativo = 1", [$_GET['id']], true);
                    
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
                    $buscaParam = "%$busca%";
                    $pacientes = selectPacienteComPsicologa($db, "WHERE p.ativo = 1 AND (p.nome LIKE ? OR p.cpf LIKE ? OR p.telefone LIKE ?) ORDER BY p.nome ASC", [$buscaParam, $buscaParam, $buscaParam], false);
                } else {
                    $pacientes = selectPacienteComPsicologa($db, "WHERE p.ativo = 1 ORDER BY p.nome ASC", [], false);
                }
                
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
        if (empty($input['data_nascimento']) && empty($input['data_nasc'])) $errors[] = validateField($input, 'data_nascimento', 'Data de nascimento');
        if (empty($input['telefone'])) $errors[] = validateField($input, 'telefone', 'Telefone');
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        try {
            // Sempre gerar ID sequencial numérico (1,2,3...)
            $id = gerarProximoIdPaciente($db);
            
            // Aceitar data_nascimento ou data_nasc (legado)
            $dataNasc = !empty($input['data_nascimento']) ? $input['data_nascimento'] : $input['data_nasc'];
            
            $stmt = $db->prepare("INSERT INTO pacientes (
                id, nome, cpf, data_nascimento, telefone, email, endereco,
                responsavel_nome, responsavel_telefone, emergencia_nome, emergencia_telefone,
                emergencia_parentesco, emergencia_info_adicionais, psicologa_responsavel_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $id,
                sanitize($input['nome']),
                isset($input['cpf']) ? sanitize($input['cpf']) : null,
                formatDateToISO($dataNasc),
                sanitize($input['telefone']),
                isset($input['email']) ? sanitize($input['email']) : null,
                isset($input['endereco']) ? sanitize($input['endereco']) : null,
                isset($input['responsavel_nome']) ? sanitize($input['responsavel_nome']) : null,
                isset($input['responsavel_telefone']) ? sanitize($input['responsavel_telefone']) : null,
                isset($input['emergencia_nome']) ? sanitize($input['emergencia_nome']) : null,
                isset($input['emergencia_telefone']) ? sanitize($input['emergencia_telefone']) : null,
                isset($input['emergencia_parentesco']) ? sanitize($input['emergencia_parentesco']) : null,
                isset($input['emergencia_info_adicionais']) ? sanitize($input['emergencia_info_adicionais']) : null,
                isset($input['psicologa_responsavel_id']) && $input['psicologa_responsavel_id'] !== '' ? intval($input['psicologa_responsavel_id']) : null
            ]);
            
            if ($result) {
                // Buscar paciente criado
                $paciente = selectPacienteComPsicologa($db, "WHERE p.id = ?", [$id], true);
                $paciente['data_nascimento'] = formatDateToBR($paciente['data_nascimento']);
                
                successResponse($paciente, 'Paciente cadastrado com sucesso', 201);
            } else {
                errorResponse('Erro ao cadastrar paciente', 500);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                // Se já existir, talvez seja um update disfarçado de importação
                errorResponse('Paciente já cadastrado (ID ou CPF duplicado)', 409);
            } else {
                errorResponse('Erro ao cadastrar paciente: ' . $e->getMessage(), 500);
            }
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
                emergencia_parentesco = ?, emergencia_info_adicionais = ?, psicologa_responsavel_id = ?
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
                isset($input['psicologa_responsavel_id']) && $input['psicologa_responsavel_id'] !== '' ? intval($input['psicologa_responsavel_id']) : null,
                $input['id']
            ]);
            
            if ($result) {
                $paciente = selectPacienteComPsicologa($db, "WHERE p.id = ?", [$input['id']], true);
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