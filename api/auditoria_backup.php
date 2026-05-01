<?php
/**
 * auditoria_backup.php
 * Registro e consulta de auditoria de importação/backup
 */

require_once 'config.php';

startSession();
requireAuth();

$method = getRequestMethod();
$db = Database::getInstance()->getConnection();

function ensureAuditoriaBackupTable($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS auditoria_backup (
        id INT PRIMARY KEY AUTO_INCREMENT,
        usuario_id INT NOT NULL,
        usuario_nome VARCHAR(120) NOT NULL,
        acao ENUM('importacao','backup') NOT NULL,
        arquivo VARCHAR(255) NULL,
        detalhes TEXT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_auditoria_data (criado_em),
        INDEX idx_auditoria_acao (acao),
        INDEX idx_auditoria_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function validarSenhaUsuarioAtual($db, $senha) {
    $stmt = $db->prepare("SELECT id, senha FROM usuarios WHERE id = ? AND ativo = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        return false;
    }

    if (password_verify($senha, $user['senha'])) {
        return true;
    }

    if (hash_equals((string)$user['senha'], (string)$senha)) {
        $novoHash = password_hash($senha, PASSWORD_BCRYPT);
        $stmtUpdate = $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmtUpdate->execute([$novoHash, $user['id']]);
        return true;
    }

    return false;
}

switch ($method) {
    case 'GET':
        requirePermission('configuracoes', 'visualizar');
        ensureAuditoriaBackupTable($db);

        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM auditoria_backup WHERE acao='importacao' AND DATE(criado_em)=CURDATE()");
        $stmt->execute();
        $totalImportacoesHoje = (int)($stmt->fetch()['total'] ?? 0);

        $stmt = $db->prepare("SELECT DISTINCT usuario_nome FROM auditoria_backup WHERE acao='importacao' AND DATE(criado_em)=CURDATE() ORDER BY usuario_nome");
        $stmt->execute();
        $usuariosHoje = array_map(function($r){ return $r['usuario_nome']; }, $stmt->fetchAll());

        $stmt = $db->prepare("SELECT id, usuario_id, usuario_nome, acao, arquivo, detalhes, criado_em FROM auditoria_backup WHERE DATE(criado_em)=CURDATE() ORDER BY criado_em DESC LIMIT 100");
        $stmt->execute();
        $logsHoje = $stmt->fetchAll();

        successResponse([
            'importacoes_hoje' => $totalImportacoesHoje,
            'usuarios_importacao_hoje' => $usuariosHoje,
            'logs_hoje' => $logsHoje
        ], 'Auditoria carregada com sucesso');
        break;

    case 'POST':
        requirePermission('configuracoes', 'visualizar');
        ensureAuditoriaBackupTable($db);

        $input = getJsonInput();
        if (empty($input)) $input = $_POST;

        $acao = isset($input['acao']) ? trim($input['acao']) : '';
        $senha = isset($input['senha']) ? (string)$input['senha'] : '';
        $arquivo = isset($input['arquivo']) ? trim($input['arquivo']) : null;
        $detalhes = isset($input['detalhes']) ? $input['detalhes'] : null;

        if (!in_array($acao, ['importacao', 'backup'])) {
            errorResponse('Ação inválida', 400);
        }
        if ($senha === '') {
            errorResponse('Senha é obrigatória', 400);
        }
        if (!validarSenhaUsuarioAtual($db, $senha)) {
            errorResponse('Senha inválida', 401);
        }

        $usuarioNome = isset($_SESSION['nome']) ? $_SESSION['nome'] : ($_SESSION['usuario'] ?? 'Usuário');
        $detalhesJson = $detalhes ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $db->prepare("INSERT INTO auditoria_backup (usuario_id, usuario_nome, acao, arquivo, detalhes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $usuarioNome, $acao, $arquivo, $detalhesJson]);

        successResponse(['registrado' => true], 'Ação auditada com sucesso');
        break;

    default:
        errorResponse('Método não permitido', 405);
}
