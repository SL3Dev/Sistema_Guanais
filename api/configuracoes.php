<?php
/**
 * configuracoes.php
 * CRUD de configurações do sistema
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        // Listar todas configurações ou buscar por chave
        try {
            if (isset($_GET['chave'])) {
                $stmt = $db->prepare("SELECT * FROM configuracoes WHERE chave = ?");
                $stmt->execute([$_GET['chave']]);
                $config = $stmt->fetch();
                
                if (!$config) {
                    errorResponse('Configuração não encontrada', 404);
                }
                
                successResponse($config, 'Configuração encontrada');
            } else {
                $stmt = $db->query("SELECT * FROM configuracoes ORDER BY chave ASC");
                $configuracoes = $stmt->fetchAll();
                
                successResponse($configuracoes, 'Configurações listadas');
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao listar configurações', 500);
        }
        break;
        
    case 'POST':
        // Criar nova configuração
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        $errors = [];
        if (empty($input['chave'])) $errors[] = validateField($input, 'chave', 'Chave');
        if (!isset($input['valor'])) $errors[] = 'Valor é obrigatório';
        
        if (!empty($errors)) {
            errorResponse('Erro de validação', 400, $errors);
        }
        
        try {
            $id = generateId('CFG');
            $tipo = isset($input['tipo']) ? $input['tipo'] : 'texto';
            
            $stmt = $db->prepare("INSERT INTO configuracoes (id, chave, valor, tipo, descricao) VALUES (?, ?, ?, ?, ?)");
            
            $result = $stmt->execute([
                $id,
                sanitize($input['chave']),
                sanitize($input['valor']),
                $tipo,
                isset($input['descricao']) ? sanitize($input['descricao']) : null
            ]);
            
            if ($result) {
                $stmt = $db->prepare("SELECT * FROM configuracoes WHERE id = ?");
                $stmt->execute([$id]);
                $config = $stmt->fetch();
                
                successResponse($config, 'Configuração criada com sucesso', 201);
            } else {
                errorResponse('Erro ao criar configuração', 500);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                errorResponse('Já existe uma configuração com esta chave', 409);
            }
            errorResponse('Erro ao criar configuração', 500);
        }
        break;
        
    case 'PUT':
        // Atualizar configuração
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;
        
        if (empty($input['chave'])) {
            errorResponse('Chave da configuração é obrigatória', 400);
        }
        
        try {
            $stmt = $db->prepare("SELECT id FROM configuracoes WHERE chave = ?");
            $stmt->execute([$input['chave']]);
            if (!$stmt->fetch()) {
                errorResponse('Configuração não encontrada', 404);
            }
            
            $stmt = $db->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
            
            $result = $stmt->execute([
                sanitize($input['valor']),
                $input['chave']
            ]);
            
            if ($result) {
                $stmt = $db->prepare("SELECT * FROM configuracoes WHERE chave = ?");
                $stmt->execute([$input['chave']]);
                $config = $stmt->fetch();
                
                successResponse($config, 'Configuração atualizada com sucesso');
            } else {
                errorResponse('Erro ao atualizar configuração', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao atualizar configuração', 500);
        }
        break;
        
    case 'DELETE':
        $chave = isset($_GET['chave']) ? $_GET['chave'] : null;
        
        if (empty($chave)) {
            errorResponse('Chave da configuração é obrigatória', 400);
        }
        
        try {
            $stmt = $db->prepare("SELECT id FROM configuracoes WHERE chave = ?");
            $stmt->execute([$chave]);
            if (!$stmt->fetch()) {
                errorResponse('Configuração não encontrada', 404);
            }
            
            $stmt = $db->prepare("DELETE FROM configuracoes WHERE chave = ?");
            $result = $stmt->execute([$chave]);
            
            if ($result) {
                successResponse([], 'Configuração removida com sucesso');
            } else {
                errorResponse('Erro ao remover configuração', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao remover configuração', 500);
        }
        break;
        
    default:
        errorResponse('Método não permitido', 405);
        break;
}