<?php
/**
 * usuarios.php
 * CRUD de usuários e gestão de permissões
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

// Iniciar sessão
startSession();

// Verificar autenticação
if (!isAuthenticated()) {
    errorResponse('Não autorizado', 401);
}

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

// Módulos e ações disponíveis
$modulosDisponiveis = ['pacientes', 'atendimentos', 'financeiro', 'despesas', 'configuracoes'];
$acoesDisponiveis = ['visualizar', 'editar', 'excluir', 'criar'];

switch ($method) {
    case 'GET':
        // Listar usuários (apenas admin)
        if (!hasPermission('configuracoes', 'visualizar')) {
            errorResponse('Permissão negada', 403);
        }
        
        $stmt = $db->prepare("SELECT id, usuario, nome, email, tipo, ativo, criado_em FROM usuarios ORDER BY id");
        $stmt->execute();
        $usuarios = $stmt->fetchAll();
        
        // Buscar permissões de cada usuário
        foreach ($usuarios as &$usuario) {
            $stmt = $db->prepare("SELECT modulo, acao, permitido FROM permissoes WHERE usuario_id = ?");
            $stmt->execute([$usuario['id']]);
            $usuario['permissoes'] = $stmt->fetchAll();
        }
        
        successResponse($usuarios, 'Usuários listados com sucesso');
        break;
        
    case 'POST':
        // Criar novo usuário (apenas admin)
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
        
        // Validações
        $errors = [];
        if (empty($usuario)) $errors[] = 'Usuário é obrigatório';
        if (empty($senha)) $errors[] = 'Senha é obrigatória';
        if (empty($nome)) $errors[] = 'Nome é obrigatório';
        if (!in_array($tipo, ['admin', 'terapeuta', 'secretaria'])) $errors[] = 'Tipo inválido';
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        // Verificar se usuário já existe
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $stmt->execute([$usuario]);
        if ($stmt->fetch()) {
            errorResponse('Usuário já existe', 409);
        }
        
        try {
            // Criar usuário
            $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO usuarios (usuario, senha, nome, email, tipo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$usuario, $senhaHash, $nome, $email, $tipo]);
            $usuarioId = $db->lastInsertId();
            
            // Criar permissões
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
            errorResponse('Erro ao criar usuário', 500);
        }
        break;
