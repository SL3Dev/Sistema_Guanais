<?php
/**
 * usuarios.php
 * CRUD de usuários e gestão de permissões
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

startSession();

if (!isAuthenticated()) {
    errorResponse('Não autorizado', 401);
}

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

$modulosDisponiveis = ['pacientes', 'atendimentos', 'financeiro', 'despesas', 'configuracoes'];
$acoesDisponiveis = ['visualizar', 'editar', 'excluir', 'criar'];

switch ($method) {
    case 'GET':
        if (!hasPermission('configuracoes', 'visualizar')) {
            errorResponse('Permissão negada', 403);
        }
        
        $stmt = $db->prepare("SELECT id, usuario, nome, email, tipo, ativo, criado_em FROM usuarios ORDER BY id");
        $stmt->execute();
        $usuarios = $stmt->fetchAll();
        
        foreach ($usuarios as &$usuario) {
            $stmt = $db->prepare("SELECT modulo, acao, permitido FROM permissoes WHERE usuario_id = ?");
            $stmt->execute([$usuario['id']]);
            $usuario['permissoes'] = $stmt->fetchAll();
        }
        
        successResponse($usuarios, 'Usuários listados com sucesso');
        break;
        
    case 'POST':
        if (!hasPermission('configuracoes', 'criar')) {
            errorResponse('Permissão negada', 403);
        }
        
        $input = getJsonInput();
        $usuario = isset($input['usuario']) ? trim($input['usuario']) : '';
        $senha = isset($input['senha']) ? $input['senha'] : '';
        $nome = isset($input['nome']) ? trim($input['nome']) : '';
        $email = isset($input['email']) ? trim($input['email']) : '';
        $tipo = isset($input['tipo']) ? $input['tipo'] : 'secretaria';
        $permissoes = isset($input['permissoes']) ? $input['permissoes'] : [];
        
        $errors = [];
        if (empty($usuario)) $errors[] = 'Usuário é obrigatório';
        if (empty($senha)) $errors[] = 'Senha é obrigatória';
        if (empty($nome)) $errors[] = 'Nome é obrigatório';
        if (!in_array($tipo, ['admin', 'terapeuta', 'secretaria'])) $errors[] = 'Tipo inválido';
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $stmt->execute([$usuario]);
        if ($stmt->fetch()) {
            errorResponse('Usuário já existe', 409);
        }
        
        try {
            $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO usuarios (usuario, senha, nome, email, tipo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$usuario, $senhaHash, $nome, $email, $tipo]);
            $usuarioId = $db->lastInsertId();
            
            if (!empty($permissoes)) {
                $stmt = $db->prepare("INSERT INTO permissoes (usuario_id, modulo, acao, permitido) VALUES (?, ?, ?, ?)");
                foreach ($permissoes as $p) {
                    if (in_array($p['modulo'], $modulosDisponiveis) && in_array($p['acao'], $acoesDisponiveis)) {
                        $stmt->execute([$usuarioId, $p['modulo'], $p['acao'], $p['permitido'] ? 1 : 0]);
                    }
                }
            } else {
                // Permissões padrão baseadas no tipo
                $stmt = $db->prepare("INSERT INTO permissoes (usuario_id, modulo, acao, permitido) VALUES (?, ?, ?, ?)");
                if ($tipo === 'admin') {
                    foreach ($modulosDisponiveis as $modulo) {
                        foreach ($acoesDisponiveis as $acao) {
                            $stmt->execute([$usuarioId, $modulo, $acao, 1]);
                        }
                    }
                } elseif ($tipo === 'terapeuta') {
                    $permissoesTerapeuta = [
                        ['pacientes', 'visualizar'], ['pacientes', 'editar'],
                        ['atendimentos', 'visualizar'], ['atendimentos', 'editar'], ['atendimentos', 'criar'],
                        ['configuracoes', 'visualizar']
                    ];
                    foreach ($permissoesTerapeuta as $p) {
                        $stmt->execute([$usuarioId, $p[0], $p[1], 1]);
                    }
                } else {
                    $permissoesSecretaria = [
                        ['pacientes', 'visualizar'], ['pacientes', 'criar'],
                        ['atendimentos', 'visualizar'], ['atendimentos', 'editar'], ['atendimentos', 'criar'],
                        ['financeiro', 'visualizar'], ['financeiro', 'criar'],
                        ['despesas', 'visualizar'], ['despesas', 'criar']
                    ];
                    foreach ($permissoesSecretaria as $p) {
                        $stmt->execute([$usuarioId, $p[0], $p[1], 1]);
                    }
                }
            }
            
            successResponse(['id' => $usuarioId], 'Usuário criado com sucesso');
            
        } catch (PDOException $e) {
            errorResponse('Erro no banco de dados: ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            errorResponse('Erro inesperado: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'PUT':
        // Atualizar usuário
        if (!hasPermission('configuracoes', 'editar')) {
            errorResponse('Permissão negada', 403);
        }
        
        $input = getJsonInput();
        $id = isset($_GET['id']) ? $_GET['id'] : (isset($input['id']) ? $input['id'] : null);
        
        if (empty($id)) {
            errorResponse('ID do usuário é obrigatório', 400);
        }
        
        // Validações
        $errors = [];
        if (isset($input['usuario']) && empty($input['usuario'])) $errors[] = 'Usuário não pode ser vazio';
        if (isset($input['nome']) && empty($input['nome'])) $errors[] = 'Nome é obrigatório';
        if (isset($input['tipo']) && !in_array($input['tipo'], ['admin', 'terapeuta', 'secretaria'])) $errors[] = 'Tipo inválido';
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        try {
            // Verificar se usuário existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                errorResponse('Usuário não encontrado', 404);
            }
            
            // Atualizar dados
            $fields = [];
            $params = [];
            
            if (isset($input['usuario'])) {
                // Verificar se já existe usuário com este nome (exceto o atual)
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
                $stmt->execute([$input['usuario'], $id]);
                if ($stmt->fetch()) {
                    errorResponse('Nome de usuário já existe', 409);
                }
                $fields[] = "usuario = ?";
                $params[] = sanitize($input['usuario']);
            }
            
            if (isset($input['nome'])) {
                $fields[] = "nome = ?";
                $params[] = sanitize($input['nome']);
            }
            
            if (isset($input['email'])) {
                $fields[] = "email = ?";
                $params[] = sanitize($input['email']);
            }
            
            if (isset($input['tipo'])) {
                $fields[] = "tipo = ?";
                $params[] = $input['tipo'];
            }
            
            if (isset($input['ativo'])) {
                $fields[] = "ativo = ?";
                $params[] = $input['ativo'] ? 1 : 0;
            }
            
            if (isset($input['senha']) && !empty($input['senha'])) {
                $fields[] = "senha = ?";
                $params[] = password_hash($input['senha'], PASSWORD_BCRYPT);
            }
            
            if (!empty($fields)) {
                $params[] = $id;
                $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute($params);
                
                if ($result) {
                    successResponse(['id' => $id], 'Usuário atualizado com sucesso');
                } else {
                    errorResponse('Erro ao atualizar usuário', 500);
                }
            } else {
                errorResponse('Nenhum campo para atualizar', 400);
            }
        } catch (PDOException $e) {
            errorResponse('Erro no banco de dados: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'PATCH':
        // Atualizar permissões do usuário
        if (!hasPermission('configuracoes', 'editar')) {
            errorResponse('Permissão negada', 403);
        }
        
        $input = getJsonInput();
        $usuarioId = isset($_GET['id']) ? $_GET['id'] : (isset($input['usuario_id']) ? $input['usuario_id'] : null);
        
        if (empty($usuarioId)) {
            errorResponse('ID do usuário é obrigatório', 400);
        }
        
        // Verificar se usuário existe
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        if (!$stmt->fetch()) {
            errorResponse('Usuário não encontrado', 404);
        }
        
        if (!isset($input['permissoes']) || !is_array($input['permissoes'])) {
            errorResponse('Permissões são obrigatórias', 400);
        }
        
        try {
            // Deletar permissões existentes
            $stmt = $db->prepare("DELETE FROM permissoes WHERE usuario_id = ?");
            $stmt->execute([$usuarioId]);
            
            // Inserir novas permissões
            $stmt = $db->prepare("INSERT INTO permissoes (usuario_id, modulo, acao, permitido) VALUES (?, ?, ?, ?)");
            foreach ($input['permissoes'] as $p) {
                if (in_array($p['modulo'], $modulosDisponiveis) && in_array($p['acao'], $acoesDisponiveis)) {
                    $permitido = isset($p['permitido']) ? ($p['permitido'] ? 1 : 0) : 0;
                    $stmt->execute([$usuarioId, $p['modulo'], $p['acao'], $permitido]);
                }
            }
            
            successResponse(['usuario_id' => $usuarioId], 'Permissões atualizadas com sucesso');
        } catch (PDOException $e) {
            errorResponse('Erro no banco de dados: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'DELETE':
        // Excluir usuário
        if (!hasPermission('configuracoes', 'excluir')) {
            errorResponse('Permissão negada', 403);
        }
        
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if (empty($id)) {
            errorResponse('ID do usuário é obrigatório', 400);
        }
        
        // Não permitir exclusão do usuário admin principal (id=1)
        if ($id == 1) {
            errorResponse('Não é possível excluir o usuário administrador principal', 403);
        }
        
        try {
            // Verificar se usuário existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                errorResponse('Usuário não encontrado', 404);
            }
            
            // Excluir usuário (as permissões serão excluídas em cascata)
            $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                successResponse([], 'Usuário excluído com sucesso');
            } else {
                errorResponse('Erro ao excluir usuário', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro no banco de dados: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        errorResponse('Método não permitido', 405);
}