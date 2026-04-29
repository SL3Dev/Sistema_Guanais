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
            $stmt = $db->prepare("SELECT id, usuario, senha, nome, email FROM usuarios WHERE usuario = ? AND ativo = 1");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();
            
            if (!$user) {
                errorResponse('Usuário ou senha inválidos', 401);
            }
            
            // Verificar senha usando password_verify (compatível com bcrypt do database.sql)
            // Também aceita MD5 para compatibilidade com hashes antigos
            $senhaValida = false;
            
            // Primeiro tenta password_verify (bcrypt)
            if (password_verify($senha, $user['senha'])) {
                $senhaValida = true;
            } 
            // Se não, tenta MD5 (para compatibilidade)
            else if (md5($senha) === $user['senha']) {
                $senhaValida = true;
            }
            // Verifica se a senha é exatamente igual (para senhas simples em teste)
            else if ($senha === $user['senha']) {
                $senhaValida = true;
            }
            
            if (!$senhaValida) {
                errorResponse('Usuário ou senha inválidos', 401);
            }
            
            // Criar sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['nome'] = $user['nome'];
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
        
    default:
        errorResponse('Método não permitido', 405);
        break;
}