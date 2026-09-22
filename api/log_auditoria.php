<?php
/**
 * log_auditoria.php
 * Consulta do log de auditoria (quem fez o quê, quando)
 * Somente leitura, acesso restrito a administradores.
 */

require_once 'config.php';

startSession();
requireAuth();

if (($_SESSION['tipo'] ?? '') !== 'admin') {
    errorResponse('Acesso restrito a administradores', 403);
}

$method = getRequestMethod();
if ($method !== 'GET') {
    errorResponse('Método não permitido', 405);
}

$db = Database::getInstance()->getConnection();
ensureLogAuditoriaTable($db);

$where = [];
$params = [];

if (!empty($_GET['modulo'])) {
    $where[] = 'modulo = ?';
    $params[] = $_GET['modulo'];
}
if (!empty($_GET['acao'])) {
    $where[] = 'acao = ?';
    $params[] = $_GET['acao'];
}
if (!empty($_GET['usuario_id'])) {
    $where[] = 'usuario_id = ?';
    $params[] = $_GET['usuario_id'];
}
if (!empty($_GET['data_inicio'])) {
    $where[] = 'DATE(criado_em) >= ?';
    $params[] = $_GET['data_inicio'];
}
if (!empty($_GET['data_fim'])) {
    $where[] = 'DATE(criado_em) <= ?';
    $params[] = $_GET['data_fim'];
}
if (!empty($_GET['busca'])) {
    $where[] = '(registro_descricao LIKE ? OR usuario_nome LIKE ?)';
    $buscaParam = '%' . $_GET['busca'] . '%';
    $params[] = $buscaParam;
    $params[] = $buscaParam;
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$porPagina = 50;
$offset = ($pagina - 1) * $porPagina;

try {
    $stmtTotal = $db->prepare("SELECT COUNT(*) AS total FROM log_auditoria $whereSql");
    $stmtTotal->execute($params);
    $total = (int)($stmtTotal->fetch()['total'] ?? 0);

    $sql = "SELECT id, usuario_id, usuario_nome, modulo, acao, registro_id, registro_descricao, criado_em
            FROM log_auditoria $whereSql
            ORDER BY criado_em DESC
            LIMIT $porPagina OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    successResponse([
        'logs' => $logs,
        'total' => $total,
        'pagina' => $pagina,
        'por_pagina' => $porPagina
    ], 'Log de auditoria carregado');
} catch (PDOException $e) {
    errorResponse('Erro ao consultar log de auditoria', 500);
}
