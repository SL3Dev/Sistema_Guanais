<?php
/**
 * configuracoes.php
 * CRUD de configurações do sistema
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';

startSession();
requireAuth();

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        requirePermission('configuracoes', 'visualizar');
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
        // Handle file upload for logos
        if (isset($_FILES['logo'])) {
            requirePermission('configuracoes', 'editar');

            $file = $_FILES['logo'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                errorResponse('Erro no upload da logo', 400);
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                errorResponse('Arquivo excede 5MB', 400);
            }

            // Validar tipo real do arquivo (não confiar na extensão/Content-Type enviados pelo cliente)
            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false || !isset($allowedMimes[$imageInfo['mime']])) {
                errorResponse('Formato inválido. Use JPG, PNG, WEBP ou GIF', 400);
            }
            $fileExtension = $allowedMimes[$imageInfo['mime']];

            $type = isset($_POST['type']) ? $_POST['type'] : 'header';
            $chave = ($type === 'login') ? 'logo_login' : 'logo_path';

            $uploadDir = '../logo/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = $chave . '_' . time() . '.' . $fileExtension; // Add timestamp to avoid cache issues
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $dbValue = 'logo/' . $fileName;

                // Atualizar no banco
                $stmt = $db->prepare("SELECT id FROM configuracoes WHERE chave = ?");
                $stmt->execute([$chave]);
                if ($stmt->fetch()) {
                    $stmt = $db->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
                    $stmt->execute([$dbValue, $chave]);
                } else {
                    $stmt = $db->prepare("INSERT INTO configuracoes (id, chave, valor, tipo) VALUES (?, ?, ?, 'arquivo')");
                    $stmt->execute([generateId('CFG'), $chave, $dbValue]);
                }

                registrarLogAuditoria('configuracoes', 'editar', $chave, 'Logo atualizada');
                successResponse(['path' => $dbValue], 'Logo atualizada com sucesso');
            } else {
                errorResponse('Erro ao fazer upload do arquivo', 500);
            }
            break;
        }

        requirePermission('configuracoes', 'criar');
        // Criar nova configuração
        $input = getJsonInput();
        if (empty($input)) $input = $_POST;

        $errors = [];
        if (empty($input['chave'])) $errors[] = 'Chave é obrigatória';
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
                registrarLogAuditoria('configuracoes', 'criar', $id, $config['chave'] ?? null);
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
        requirePermission('configuracoes', 'editar');
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
                $tipoConfig = (strpos($input['chave'], 'logo_') === 0) ? 'arquivo' : 'texto';
                $stmt = $db->prepare("INSERT INTO configuracoes (id, chave, valor, tipo) VALUES (?, ?, ?, ?)");
                $stmt->execute([generateId('CFG'), $input['chave'], $input['valor'], $tipoConfig]);
            }

            $stmt = $db->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
            $result = $stmt->execute([
                $input['valor'],
                $input['chave']
            ]);

            if ($result) {
                $stmt = $db->prepare("SELECT * FROM configuracoes WHERE chave = ?");
                $stmt->execute([$input['chave']]);
                $config = $stmt->fetch();
                registrarLogAuditoria('configuracoes', 'editar', $input['chave'], $input['chave']);
                successResponse($config, 'Configuração atualizada com sucesso');
            } else {
                errorResponse('Erro ao atualizar configuração', 500);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao atualizar configuração', 500);
        }
        break;
        
    case 'DELETE':
        requirePermission('configuracoes', 'excluir');
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
                registrarLogAuditoria('configuracoes', 'excluir', $chave, $chave);
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