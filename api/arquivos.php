<?php
/**
 * arquivos.php
 * Gestão de anexos de pacientes
 */

require_once 'config.php';

startSession();
requireAuth();

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

// Diretório de uploads
$uploadDir = '../uploads/pacientes/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

switch ($method) {
    case 'GET':
        requirePermission('pacientes', 'visualizar');
        $paciente_id = $_GET['paciente_id'] ?? null;
        if (!$paciente_id) {
            errorResponse('ID do paciente é obrigatório', 400);
        }
        
        try {
            $stmt = $db->prepare("SELECT * FROM pacientes_arquivos WHERE paciente_id = ? ORDER BY criado_em DESC");
            $stmt->execute([$paciente_id]);
            $arquivos = $stmt->fetchAll();
            successResponse($arquivos);
        } catch (PDOException $e) {
            errorResponse('Erro ao listar arquivos', 500);
        }
        break;

    case 'POST':
        requirePermission('pacientes', 'criar');

        if (!isset($_FILES['arquivo']) || !isset($_POST['paciente_id'])) {
            errorResponse('Arquivo e ID do paciente são obrigatórios', 400);
        }

        $paciente_id = $_POST['paciente_id'];
        $file = $_FILES['arquivo'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            errorResponse('Erro no upload do arquivo', 400);
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            errorResponse('Arquivo excede 10MB', 400);
        }

        // Validar tipo real do arquivo pelo conteúdo (não confiar no Content-Type enviado pelo cliente)
        $allowedExtensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $tipoReal = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowedExtensions[$tipoReal])) {
            errorResponse('Tipo de arquivo não permitido (Apenas PDF, JPG, PNG, WEBP)', 400);
        }
        $ext = $allowedExtensions[$tipoReal];

        $newName = $paciente_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $uploadDir . $newName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            try {
                $stmt = $db->prepare("INSERT INTO pacientes_arquivos (paciente_id, nome_original, nome_arquivo, caminho, tipo_arquivo, tamanho) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $paciente_id,
                    $file['name'],
                    $newName,
                    'uploads/pacientes/' . $newName,
                    $tipoReal,
                    $file['size']
                ]);
                successResponse(['id' => $db->lastInsertId()], 'Arquivo enviado com sucesso');
            } catch (PDOException $e) {
                unlink($targetPath); // Remove arquivo se falhar no banco
                errorResponse('Erro ao registrar arquivo no banco', 500);
            }
        } else {
            errorResponse('Erro ao mover arquivo para o diretório de destino', 500);
        }
        break;

    case 'DELETE':
        requirePermission('pacientes', 'excluir');
        $id = $_GET['id'] ?? null;
        if (!$id) {
            errorResponse('ID do arquivo é obrigatório', 400);
        }
        
        try {
            $stmt = $db->prepare("SELECT caminho FROM pacientes_arquivos WHERE id = ?");
            $stmt->execute([$id]);
            $arquivo = $stmt->fetch();
            
            if ($arquivo) {
                $filePath = '../' . $arquivo['caminho'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                $stmt = $db->prepare("DELETE FROM pacientes_arquivos WHERE id = ?");
                $stmt->execute([$id]);
                successResponse([], 'Arquivo removido com sucesso');
            } else {
                errorResponse('Arquivo não encontrado', 404);
            }
        } catch (PDOException $e) {
            errorResponse('Erro ao remover arquivo', 500);
        }
        break;

    default:
        errorResponse('Método não permitido', 405);
}