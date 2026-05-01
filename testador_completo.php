<?php
/**
 * Testador automatizado completo (PHP) - Sistema Guanais
 *
 * Uso:
 *   php testador_completo.php
 *   php testador_completo.php --base-url="http://localhost/Sistema_Guanais/api" --usuario=admin --senha=0301
 */

date_default_timezone_set('America/Sao_Paulo');

$opts = getopt('', ['base-url::', 'usuario::', 'senha::']);
$baseUrl = rtrim($opts['base-url'] ?? 'http://localhost/Sistema_Guanais/api', '/');
$usuario = $opts['usuario'] ?? 'admin';
$senha = $opts['senha'] ?? '0301';

$cookieJar = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'guanais_test_cookie_' . uniqid() . '.txt';
$tmpFiles = [];
$ctx = [
    'paciente_base_id' => null,
    'created' => [
        'pacientes' => [],
        'pacotes' => [],
        'atendimentos' => [],
        'financeiro' => [],
        'despesas' => [],
        'configs' => [],
        'arquivos' => [],
        'usuarios' => [],
    ]
];

$results = [];

function out($msg) { echo $msg . PHP_EOL; }

function req(string $method, string $url, array $opt = []): array {
    global $cookieJar;
    $ch = curl_init($url);
    $headers = $opt['headers'] ?? [];
    $payload = $opt['json'] ?? null;
    $form = $opt['form'] ?? null;

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    } elseif ($form !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
    }

    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode((string)$raw, true);
    return ['http' => $http, 'json' => $json, 'raw' => $raw, 'curl_error' => $err];
}

function ok(array $r): bool {
    return isset($r['json']['success']) && $r['json']['success'] === true;
}

function addResult(string $mod, string $step, bool $success, array $r): void {
    global $results;
    $results[] = [
        'modulo' => $mod,
        'etapa' => $step,
        'ok' => $success,
        'http' => $r['http'] ?? 0,
        'mensagem' => $r['json']['message'] ?? ($r['curl_error'] ?: 'sem mensagem')
    ];
    $icon = $success ? '✅' : '❌';
    out("$icon [$mod] $step (HTTP " . ($r['http'] ?? '?') . ") - " . ($r['json']['message'] ?? '')); 
}

function createTempPng(string $name): string {
    global $tmpFiles;
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wm6Lz4AAAAASUVORK5CYII=');
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '_' . uniqid() . '.png';
    file_put_contents($path, $png);
    $tmpFiles[] = $path;
    return $path;
}

function endpoint(string $file, string $query = ''): string {
    global $baseUrl;
    return $baseUrl . '/' . $file . ($query ? ('?' . $query) : '');
}

function ensurePacienteBase(): ?string {
    global $ctx;
    if (!empty($ctx['paciente_base_id'])) return $ctx['paciente_base_id'];

    $r = req('POST', endpoint('pacientes.php'), ['json' => [
        'nome' => 'Paciente Base Teste ' . date('His'),
        'data_nascimento' => '01/01/2000',
        'telefone' => '11999990000'
    ]]);
    if (!ok($r)) {
        addResult('base', 'Criar paciente base', false, $r);
        return null;
    }
    $id = $r['json']['data']['id'] ?? null;
    $ctx['paciente_base_id'] = $id;
    $ctx['created']['pacientes'][] = $id;
    addResult('base', 'Criar paciente base', true, $r);
    return $id;
}

// 1) AUTH
$r = req('POST', endpoint('auth.php'), ['json' => ['usuario' => $usuario, 'senha' => $senha]]);
addResult('auth', 'Login', ok($r), $r);

$r = req('GET', endpoint('auth.php'));
addResult('auth', 'Sessão ativa', ok($r), $r);

// 2) PACIENTES
$r = req('GET', endpoint('pacientes.php'));
addResult('pacientes', 'Listar', ok($r), $r);

$r = req('POST', endpoint('pacientes.php'), ['json' => [
    'nome' => 'Paciente CRUD ' . date('His'),
    'data_nascimento' => '10/10/2000',
    'telefone' => '11988887777',
    'email' => 'paciente.teste@example.com'
]]);
addResult('pacientes', 'Criar', ok($r), $r);
$pacienteId = $r['json']['data']['id'] ?? null;
if ($pacienteId) $ctx['created']['pacientes'][] = $pacienteId;

if ($pacienteId) {
    $r = req('GET', endpoint('pacientes.php', 'id=' . urlencode($pacienteId)));
    addResult('pacientes', 'Buscar por ID', ok($r), $r);

    $r = req('GET', endpoint('pacientes.php', 'id=' . urlencode($pacienteId) . '&completo=true'));
    addResult('pacientes', 'Buscar completo', ok($r), $r);

    $r = req('PUT', endpoint('pacientes.php'), ['json' => [
        'id' => $pacienteId,
        'nome' => 'Paciente Editado ' . date('His'),
        'data_nascimento' => '10/10/2000',
        'telefone' => '11988887777'
    ]]);
    addResult('pacientes', 'Editar', ok($r), $r);
}

// 3) PACOTES
$basePaciente = ensurePacienteBase();
if ($basePaciente) {
    $r = req('GET', endpoint('pacotes.php', 'paciente_id=' . urlencode($basePaciente)));
    addResult('pacotes', 'Listar por paciente', ok($r), $r);

    $r = req('POST', endpoint('pacotes.php'), ['json' => [
        'paciente_id' => $basePaciente,
        'tipo_pacote' => 'Mensal',
        'data_inicio' => '01/05/2026',
        'valor_total' => 320,
        'forma_pagamento' => 'Pix'
    ]]);
    addResult('pacotes', 'Criar', ok($r), $r);
    $pacoteId = $r['json']['data']['id'] ?? null;
    if ($pacoteId) $ctx['created']['pacotes'][] = $pacoteId;

    if ($pacoteId) {
        $r = req('PUT', endpoint('pacotes.php'), ['json' => [
            'id' => $pacoteId,
            'tipo_pacote' => 'Quinzenal',
            'data_inicio' => '01/05/2026',
            'data_fim' => '16/05/2026',
            'valor_total' => 220,
            'forma_pagamento' => 'Dinheiro',
            'status' => 'Ativo'
        ]]);
        addResult('pacotes', 'Editar', ok($r), $r);
    }
}

// 4) ATENDIMENTOS
if ($basePaciente) {
    $atId = 'AT_' . time();
    $r = req('POST', endpoint('atendimentos.php'), ['json' => [
        'id_atendimento' => $atId,
        'paciente_id' => $basePaciente,
        'paciente_nome' => 'Paciente Base',
        'data_atendimento' => '01/05/2026',
        'tipo_pacote' => 'Mensal',
        'data_inicio_pacote' => '01/05/2026',
        'status' => 'Confirmado',
        'unidade' => 'ANIMO',
        'evolucao' => 'teste'
    ]]);
    addResult('atendimentos', 'Criar', ok($r), $r);
    $atendimentoId = $r['json']['data']['id_atendimento'] ?? $atId;
    if (ok($r)) $ctx['created']['atendimentos'][] = $atendimentoId;

    $r = req('GET', endpoint('atendimentos.php', 'id=' . urlencode($atendimentoId)));
    addResult('atendimentos', 'Buscar por ID', ok($r), $r);

    $r = req('PATCH', endpoint('atendimentos.php'), ['json' => ['id_atendimento' => $atendimentoId, 'status' => 'Falta', 'evolucao' => 'patch ok']]);
    addResult('atendimentos', 'PATCH status/evolução', ok($r), $r);

    $r = req('PUT', endpoint('atendimentos.php'), ['json' => [
        'id_atendimento' => $atendimentoId,
        'paciente_id' => $basePaciente,
        'paciente_nome' => 'Paciente Base',
        'data_atendimento' => '02/05/2026',
        'tipo_pacote' => 'Quinzenal',
        'data_inicio_pacote' => '01/05/2026',
        'status' => 'Confirmado',
        'unidade' => 'ESPAÇO GUANAIS'
    ]]);
    addResult('atendimentos', 'Editar', ok($r), $r);
}

// 5) FINANCEIRO
if ($basePaciente) {
    $r = req('POST', endpoint('financeiro.php'), ['json' => [
        'paciente_id' => $basePaciente,
        'paciente_nome' => 'Paciente Base',
        'clinica' => 'ANIMO',
        'data' => '01/05/2026',
        'valor' => 200,
        'forma_pagamento' => 'Pix'
    ]]);
    addResult('financeiro', 'Criar', ok($r), $r);
    $finId = $r['json']['data']['id'] ?? null;
    if ($finId) $ctx['created']['financeiro'][] = $finId;

    if ($finId) {
        $r = req('GET', endpoint('financeiro.php', 'id=' . urlencode($finId)));
        addResult('financeiro', 'Buscar por ID', ok($r), $r);

        $r = req('PUT', endpoint('financeiro.php'), ['json' => [
            'id' => $finId,
            'paciente_id' => $basePaciente,
            'paciente_nome' => 'Paciente Base',
            'clinica' => 'ESPAÇO GUANAIS',
            'data' => '01/05/2026',
            'valor' => 250,
            'forma_pagamento' => 'Dinheiro'
        ]]);
        addResult('financeiro', 'Editar', ok($r), $r);
    }

    $r = req('GET', endpoint('financeiro.php', 'paciente_id=' . urlencode($basePaciente) . '&mes=2026-05'));
    addResult('financeiro', 'Listar com filtros', ok($r), $r);
}

// 6) DESPESAS
$r = req('POST', endpoint('despesas.php'), ['json' => [
    'descricao' => 'Despesa teste ' . date('His'),
    'categoria' => 'Fixa',
    'valor_total' => 180,
    'num_parcelas' => 2,
    'parcelas_pagas' => 0,
    'dia_vencimento' => 10,
    'data_inicio' => '01/05/2026'
]]);
addResult('despesas', 'Criar', ok($r), $r);
$despId = $r['json']['data']['id'] ?? null;
if ($despId) $ctx['created']['despesas'][] = $despId;

if ($despId) {
    $r = req('GET', endpoint('despesas.php', 'id=' . urlencode($despId)));
    addResult('despesas', 'Buscar por ID', ok($r), $r);

    $r = req('PATCH', endpoint('despesas.php'), ['json' => ['id' => $despId]]);
    addResult('despesas', 'PATCH pagar parcela', ok($r), $r);

    $r = req('PUT', endpoint('despesas.php'), ['json' => [
        'id' => $despId,
        'descricao' => 'Despesa editada',
        'categoria' => 'Extra/Investimento',
        'valor_total' => 200,
        'num_parcelas' => 3,
        'parcelas_pagas' => 1,
        'dia_vencimento' => 12,
        'data_inicio' => '01/05/2026'
    ]]);
    addResult('despesas', 'Editar', ok($r), $r);
}

$r = req('GET', endpoint('despesas.php', 'categoria=Fixa&ativa=true'));
addResult('despesas', 'Listar com filtros', ok($r), $r);

// 7) CONFIGURACOES + upload logos
$cfgKey = 'cfg_teste_' . date('His') . '_' . rand(100, 999);
$r = req('GET', endpoint('configuracoes.php'));
addResult('configuracoes', 'Listar', ok($r), $r);

$r = req('POST', endpoint('configuracoes.php'), ['json' => ['chave' => $cfgKey, 'valor' => 'abc', 'tipo' => 'texto']]);
addResult('configuracoes', 'Criar', ok($r), $r);
if (ok($r)) $ctx['created']['configs'][] = $cfgKey;

$r = req('GET', endpoint('configuracoes.php', 'chave=' . urlencode($cfgKey)));
addResult('configuracoes', 'Buscar por chave', ok($r), $r);

$r = req('PUT', endpoint('configuracoes.php'), ['json' => ['chave' => $cfgKey, 'valor' => 'abc-editado']]);
addResult('configuracoes', 'Editar', ok($r), $r);

$logo1 = createTempPng('logo_login_teste');
$r = req('POST', endpoint('configuracoes.php'), ['form' => [
    'type' => 'login',
    'logo' => new CURLFile($logo1, 'image/png', 'logo_login_teste.png')
]]);
addResult('configuracoes', 'Upload foto/logo login', ok($r), $r);

$logo2 = createTempPng('logo_header_teste');
$r = req('POST', endpoint('configuracoes.php'), ['form' => [
    'type' => 'header',
    'logo' => new CURLFile($logo2, 'image/png', 'logo_header_teste.png')
]]);
addResult('configuracoes', 'Upload foto/logo header', ok($r), $r);

// 8) ARQUIVOS (upload foto paciente)
if ($basePaciente) {
    $foto = createTempPng('foto_paciente_teste');
    $r = req('POST', endpoint('arquivos.php'), ['form' => [
        'paciente_id' => $basePaciente,
        'arquivo' => new CURLFile($foto, 'image/png', 'foto_teste.png')
    ]]);
    addResult('arquivos', 'Upload foto paciente', ok($r), $r);
    $arqId = $r['json']['data']['id'] ?? null;
    if ($arqId) $ctx['created']['arquivos'][] = $arqId;

    $r = req('GET', endpoint('arquivos.php', 'paciente_id=' . urlencode($basePaciente)));
    addResult('arquivos', 'Listar arquivos', ok($r), $r);
}

// 9) USUARIOS
$novoUser = 'user_teste_' . date('His') . rand(100, 999);
$r = req('GET', endpoint('usuarios.php'));
addResult('usuarios', 'Listar', ok($r), $r);

$r = req('POST', endpoint('usuarios.php'), ['json' => [
    'usuario' => $novoUser,
    'senha' => '123456',
    'nome' => 'Usuário Teste',
    'email' => 'u@teste.com',
    'tipo' => 'secretaria'
]]);
addResult('usuarios', 'Criar', ok($r), $r);
$usrId = $r['json']['data']['id'] ?? null;
if ($usrId) $ctx['created']['usuarios'][] = $usrId;

if ($usrId) {
    $r = req('PATCH', endpoint('usuarios.php', 'id=' . urlencode((string)$usrId)), ['json' => [
        'permissoes' => [
            ['modulo' => 'pacientes', 'acao' => 'visualizar', 'permitido' => true],
            ['modulo' => 'atendimentos', 'acao' => 'criar', 'permitido' => true],
        ]
    ]]);
    addResult('usuarios', 'PATCH permissões', ok($r), $r);

    $r = req('PUT', endpoint('usuarios.php', 'id=' . urlencode((string)$usrId)), ['json' => [
        'nome' => 'Usuário Editado',
        'tipo' => 'terapeuta',
        'ativo' => true
    ]]);
    addResult('usuarios', 'Editar', ok($r), $r);
}

// ROLLBACK
out(PHP_EOL . '--- Iniciando rollback automático ---');

foreach ($ctx['created']['arquivos'] as $id) {
    $r = req('DELETE', endpoint('arquivos.php', 'id=' . urlencode((string)$id)));
    addResult('rollback', 'Excluir arquivo ' . $id, ok($r), $r);
}
foreach ($ctx['created']['atendimentos'] as $id) {
    $r = req('DELETE', endpoint('atendimentos.php', 'id=' . urlencode((string)$id)));
    addResult('rollback', 'Excluir atendimento ' . $id, ok($r), $r);
}
foreach ($ctx['created']['financeiro'] as $id) {
    $r = req('DELETE', endpoint('financeiro.php', 'id=' . urlencode((string)$id)));
    addResult('rollback', 'Excluir financeiro ' . $id, ok($r), $r);
}
foreach ($ctx['created']['pacotes'] as $id) {
    $r = req('DELETE', endpoint('pacotes.php', 'id=' . urlencode((string)$id)));
    addResult('rollback', 'Excluir pacote ' . $id, ok($r), $r);
}
foreach ($ctx['created']['despesas'] as $id) {
    $r = req('DELETE', endpoint('despesas.php', 'id=' . urlencode((string)$id)));
    addResult('rollback', 'Excluir despesa ' . $id, ok($r), $r);
}
foreach ($ctx['created']['usuarios'] as $id) {
    $r = req('DELETE', endpoint('usuarios.php', 'id=' . urlencode((string)$id)));
    addResult('rollback', 'Excluir usuário ' . $id, ok($r), $r);
}
foreach ($ctx['created']['configs'] as $key) {
    $r = req('DELETE', endpoint('configuracoes.php', 'chave=' . urlencode((string)$key)));
    addResult('rollback', 'Excluir config ' . $key, ok($r), $r);
}
foreach (array_reverse($ctx['created']['pacientes']) as $id) {
    if ($id) {
        $r = req('DELETE', endpoint('pacientes.php', 'id=' . urlencode((string)$id)));
        addResult('rollback', 'Excluir paciente ' . $id, ok($r), $r);
    }
}

$r = req('DELETE', endpoint('auth.php'));
addResult('auth', 'Logout final', ok($r), $r);

// LIMPEZA
if (file_exists($cookieJar)) @unlink($cookieJar);
foreach ($tmpFiles as $f) if (file_exists($f)) @unlink($f);

// RESUMO
$okCount = count(array_filter($results, fn($x) => $x['ok']));
$total = count($results);
$failCount = $total - $okCount;

out(PHP_EOL . '===== RESUMO =====');
out("Total: $total | OK: $okCount | Falhas: $failCount");

if ($failCount > 0) {
    out(PHP_EOL . 'Falhas:');
    foreach ($results as $r) {
        if (!$r['ok']) {
            out("- [{$r['modulo']}] {$r['etapa']} | HTTP {$r['http']} | {$r['mensagem']}");
        }
    }
    exit(1);
}

exit(0);
