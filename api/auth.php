<?php
/**
 * auth.php
 * Autenticação e validação de login
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

// Iniciar sessão
startSession();

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'POST':
        // Login
        $input = getJsonInput();
        
        // Se os dados não vierem como JSON, tentar do $_POST
        if (empty($input)) {
            $input = $_POST;
        }
        
        $usuario = isset($input['usuario']) ? trim($input['usuario']) : '';
        $senha = isset($input['senha']) ? $input['senha'] : '';
        
        // Validações
        $errors = [];
        if (empty($usuario)) $errors[] = 'Usuário é obrigatório';
        if (empty($senha)) $errors[] = 'Senha é obrigatória';
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        try {
            // Buscar usuário no banco
            $stmt = $db->prepare("SELECT id, usuario, senha, nome, email, tipo FROM usuarios WHERE usuario = ? AND ativo = 1");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();
            
            if (!$user) {
                errorResponse('Usuário ou senha inválidos', 401);
            }
            
            // Validar senha (compatível com hash moderno e legado em texto puro)
            $senhaValida = false;

            // Hash bcrypt/argon etc.
            if (password_verify($senha, $user['senha'])) {
                $senhaValida = true;

                // Se hash precisar rehash, atualiza automaticamente
                if (password_needs_rehash($user['senha'], PASSWORD_BCRYPT)) {
                    $novoHash = password_hash($senha, PASSWORD_BCRYPT);
                    $stmtUpdate = $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                    $stmtUpdate->execute([$novoHash, $user['id']]);
                }
            }

            // Compatibilidade com senhas antigas armazenadas em texto puro
            if (!$senhaValida && hash_equals((string)$user['senha'], (string)$senha)) {
                $senhaValida = true;

                // Migração transparente para hash seguro
                $novoHash = password_hash($senha, PASSWORD_BCRYPT);
                $stmtUpdate = $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmtUpdate->execute([$novoHash, $user['id']]);
            }
            
            if (!$senhaValida) {
                errorResponse('Usuário ou senha inválidos', 401);
            }
            
            // Criar sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['nome'] = $user['nome'];
            $_SESSION['tipo'] = $user['tipo'];
            $_SESSION['logged_in'] = true;
            
            // Buscar permissões
            $stmt = $db->prepare("SELECT modulo, acao, permitido FROM permissoes WHERE usuario_id = ?");
            $stmt->execute([$user['id']]);
            $permissoes = $stmt->fetchAll();
            
            // Gerar token (opcional, para autenticação via API)
            $token = bin2hex(random_bytes(32));
            $_SESSION['api_token'] = $token;
            
            successResponse([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'usuario' => $user['usuario'],
                    'nome' => $user['nome'],
                    'tipo' => $user['tipo'],
                    'permissoes' => $permissoes
                ],
                'token' => $token
            ], 'Login realizado com sucesso');
            
        } catch (PDOException $e) {
            errorResponse('Erro ao processar login', 500);
        }
        break;
        
    case 'GET':
        // Verificar se está autenticado
        if (isAuthenticated()) {
            // Buscar permissões
            $stmt = $db->prepare("SELECT modulo, acao, permitido FROM permissoes WHERE usuario_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $permissoes = $stmt->fetchAll();

            successResponse([
                'success' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'usuario' => $_SESSION['usuario'],
                    'nome' => $_SESSION['nome'],
                    'tipo' => $_SESSION['tipo'] ?? 'secretaria',
                    'permissoes' => $permissoes
                ],
                'logged' => true
            ], 'Usuário autenticado');
        } else {
            successResponse([
                'logged' => false
            ], 'Usuário não autenticado');
        }
        break;
        
    case 'DELETE':
        // Logout
        session_destroy();
        successResponse([], 'Logout realizado com sucesso');
        break;

    case 'PATCH':
        // Validar senha do usuário logado (confirmação sensível)
        if (!isAuthenticated()) {
            errorResponse('Usuário não autenticado', 401);
        }

        $input = getJsonInput();
        if (empty($input)) {
            $input = $_POST;
        }

        $senha = isset($input['senha']) ? (string)$input['senha'] : '';
        if ($senha === '') {
            errorResponse('Senha é obrigatória', 400);
        }

        try {
            $stmt = $db->prepare("SELECT id, senha FROM usuarios WHERE id = ? AND ativo = 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user) {
                errorResponse('Usuário não encontrado', 404);
            }

            $senhaValida = false;
            if (password_verify($senha, $user['senha'])) {
                $senhaValida = true;
            } elseif (hash_equals((string)$user['senha'], $senha)) {
                $senhaValida = true;
                $novoHash = password_hash($senha, PASSWORD_BCRYPT);
                $stmtUpdate = $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmtUpdate->execute([$novoHash, $user['id']]);
            }

            if (!$senhaValida) {
                errorResponse('Senha inválida', 401);
            }

            successResponse(['validado' => true], 'Senha confirmada com sucesso');
        } catch (PDOException $e) {
            errorResponse('Erro ao validar senha', 500);
        }
        break;
        
    default:
        errorResponse('Método não permitido', 405);
        break;
}