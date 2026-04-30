<?php
/**
 * arquivos.php
 * Gestão de anexos de pacientes
 */

require_once 'config.php';

startSession();

if (!isAuthenticated()) {
    errorResponse('Não autorizado', 401);
}

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

// Diretório de uploads
$uploadDir = '../uploads/pacientes/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

switch ($method) {
    case 'GET':
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
        if (!isset($_FILES['arquivo']) || !isset($_POST['paciente_id'])) {
            errorResponse('Arquivo e ID do paciente são obrigatórios', 400);
        }
        
        $paciente_id = $_POST['paciente_id'];
        $file = $_FILES['arquivo'];
        
        // Validar tipo de arquivo (PDF, JPG, PNG)
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            errorResponse('Tipo de arquivo não permitido (Apenas PDF, JPG, PNG)', 400);
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
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
                    $file['type'],
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