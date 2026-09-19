<?php
/**
 * seed_demo.php
 * Popula o banco com dados de demonstração realistas: usuários,
 * pacientes, pacotes, atendimentos (últimos 3 meses), financeiro e
 * despesas. Idempotente-ish: pode rodar de novo, mas vai duplicar
 * pacientes/atendimentos se rodado mais de uma vez (é um seed, não
 * uma migração) - pensado para banco recém-instalado.
 *
 * Uso: php seed_demo.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script so pode ser executado via linha de comando (php seed_demo.php).');
}

require_once __DIR__ . '/api/config.php';

$db = Database::getInstance()->getConnection();

function out($msg) { echo $msg . PHP_EOL; }

function gerarId($prefixo = '') {
    return $prefixo . uniqid() . substr(md5(uniqid(mt_rand(), true)), 0, 6);
}

function telefoneFake() {
    return '119' . mt_rand(60000000, 99999999);
}

function cpfFake() {
    return sprintf('%03d.%03d.%03d-%02d', mt_rand(100,999), mt_rand(100,999), mt_rand(100,999), mt_rand(10,99));
}

// ============ 1) USUÁRIOS (psicóloga + secretaria) ============
out('--- Criando usuários de demonstração ---');

function garantirUsuario($db, $usuario, $senha, $nome, $email, $tipo, $extra = []) {
    $stmt = $db->prepare('SELECT id FROM usuarios WHERE usuario = ?');
    $stmt->execute([$usuario]);
    $existente = $stmt->fetch();
    if ($existente) {
        out("Usuário '$usuario' já existe (id {$existente['id']}), pulando.");
        return $existente['id'];
    }

    $hash = password_hash($senha, PASSWORD_BCRYPT);
    try {
        $stmt = $db->prepare('INSERT INTO usuarios (usuario, senha, nome, email, tipo, abordagem, temas, formacao_academica, idiomas, idade, tipo_psicoterapia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $usuario, $hash, $nome, $email, $tipo,
            $extra['abordagem'] ?? null,
            $extra['temas'] ?? null,
            $extra['formacao'] ?? null,
            $extra['idiomas'] ?? null,
            $extra['idade'] ?? null,
            $extra['tipo_psicoterapia'] ?? null,
        ]);
    } catch (PDOException $e) {
        // colunas extras podem não existir em instalações antigas
        $stmt = $db->prepare('INSERT INTO usuarios (usuario, senha, nome, email, tipo) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$usuario, $hash, $nome, $email, $tipo]);
    }
    $id = $db->lastInsertId();

    $modulos = ['pacientes', 'atendimentos', 'financeiro', 'despesas', 'configuracoes'];
    $permissoesPorTipo = [
        'psicologa' => [['pacientes','visualizar'],['pacientes','editar'],['atendimentos','visualizar'],['atendimentos','editar'],['atendimentos','criar'],['configuracoes','visualizar']],
        'secretaria' => [['pacientes','visualizar'],['pacientes','criar'],['atendimentos','visualizar'],['atendimentos','editar'],['atendimentos','criar'],['financeiro','visualizar'],['financeiro','criar'],['despesas','visualizar'],['despesas','criar']],
    ];
    $perms = $permissoesPorTipo[$tipo] ?? [];
    $stmtPerm = $db->prepare('INSERT IGNORE INTO permissoes (usuario_id, modulo, acao, permitido) VALUES (?, ?, ?, 1)');
    foreach ($perms as [$modulo, $acao]) {
        $stmtPerm->execute([$id, $modulo, $acao]);
    }

    out("Usuário '$usuario' criado (id $id, senha: $senha).");
    return $id;
}

$psicologaId = garantirUsuario($db, 'dra.camila', 'demo123', 'Camila Rezende', 'camila.rezende@espacoguanais.com.br', 'psicologa', [
    'abordagem' => 'Terapia Cognitivo-Comportamental',
    'temas' => 'Ansiedade, autoestima, relacionamentos',
    'formacao' => 'Psicologia - PUC-SP, especialização em TCC',
    'idiomas' => 'Português, Inglês',
    'idade' => 34,
    'tipo_psicoterapia' => 'Individual e casal',
]);
$secretariaId = garantirUsuario($db, 'ana.secretaria', 'demo123', 'Ana Beatriz Souza', 'ana.souza@espacoguanais.com.br', 'secretaria');

// ============ 2) PACIENTES ============
out(PHP_EOL . '--- Criando pacientes de demonstração ---');

$nomesPacientes = [
    'Maria Eduarda Santos', 'João Pedro Almeida', 'Beatriz Oliveira Lima',
    'Lucas Gabriel Ferreira', 'Sophia Costa Rodrigues', 'Miguel Henrique Silva',
    'Isabela Martins Carvalho', 'Arthur Nascimento Pereira', 'Laura Cristina Barbosa',
    'Bernardo Vieira Gomes', 'Valentina Rocha Teixeira', 'Heitor Mendes Araújo',
    'Alice Fernandes Cardoso', 'Davi Ribeiro Monteiro', 'Manuela Dias Correia',
];

$stmtMaxId = $db->query("SELECT MAX(CAST(id AS UNSIGNED)) AS max_id FROM pacientes WHERE id REGEXP '^[0-9]+$'");
$proximoId = intval($stmtMaxId->fetch()['max_id'] ?? 0) + 1;

$pacientesCriados = [];
foreach ($nomesPacientes as $i => $nome) {
    $id = strval($proximoId++);
    $idade = mt_rand(18, 62);
    $nascimento = (new DateTime())->modify("-$idade years")->modify('-' . mt_rand(0, 365) . ' days')->format('Y-m-d');
    $vinculaPsicologa = ($i % 2 === 0); // metade vinculada à Dra. Camila

    $stmt = $db->prepare('INSERT INTO pacientes (id, nome, cpf, data_nascimento, telefone, email, endereco, psicologa_responsavel_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $id, $nome, cpfFake(), $nascimento, telefoneFake(),
        strtolower(str_replace(' ', '.', preg_replace('/[^A-Za-z ]/', '', iconv('UTF-8','ASCII//TRANSLIT',$nome)))) . '@exemplo.com',
        'Rua Exemplo, ' . mt_rand(10, 999) . ' - São Paulo/SP',
        $vinculaPsicologa ? $psicologaId : null,
    ]);
    $pacientesCriados[] = ['id' => $id, 'nome' => $nome, 'psicologa' => $vinculaPsicologa];
}
out(count($pacientesCriados) . ' pacientes criados.');

// ============ 3) PACOTES + ATENDIMENTOS + FINANCEIRO (últimos 3 meses) ============
out(PHP_EOL . '--- Gerando pacotes, atendimentos e financeiro (últimos 3 meses) ---');

$tiposPacote = ['Mensal', 'Mensal', 'Mensal', 'Quinzenal', 'Quinzenal', 'Avulso'];
$statusPossiveis = ['Confirmado', 'Confirmado', 'Confirmado', 'Confirmado', 'Confirmado', 'Confirmado', 'Confirmado', 'Falta', 'Reagendado'];
$unidades = ['ANIMO', 'ESPAÇO GUANAIS'];
$formasPagamento = ['Pix', 'Pix', 'Cartão Crédito', 'Dinheiro', 'Cartão Débito'];
$evolucoesExemplo = [
    'Paciente relatou melhora no padrão de sono e redução de crises de ansiedade ao longo da semana.',
    'Sessão focada em técnicas de reestruturação cognitiva. Bom engajamento e adesão às tarefas propostas.',
    'Retomada de conteúdo da sessão anterior. Paciente trouxe situação de conflito familiar para trabalhar.',
    'Evolução positiva no quadro geral. Discutidas estratégias de enfrentamento para o ambiente de trabalho.',
    'Sessão de acolhimento após período de instabilidade emocional relatado pelo paciente.',
];

$totalAtendimentos = 0;
$totalFinanceiro = 0;
$hoje = new DateTime();

foreach ($pacientesCriados as $paciente) {
    $tipoPacote = $tiposPacote[array_rand($tiposPacote)];
    $dataInicioPacote = (clone $hoje)->modify('-' . mt_rand(60, 90) . ' days');
    $valorPacote = $tipoPacote === 'Mensal' ? 800 : ($tipoPacote === 'Quinzenal' ? 450 : 200);

    $dataFimPacote = clone $dataInicioPacote;
    if ($tipoPacote === 'Mensal') $dataFimPacote->modify('+1 month');
    elseif ($tipoPacote === 'Quinzenal') $dataFimPacote->modify('+15 days');

    $pacoteId = gerarId('pac_');
    $stmt = $db->prepare('INSERT INTO pacotes (id, paciente_id, tipo_pacote, data_inicio, data_fim, valor_total, forma_pagamento, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $pacoteId, $paciente['id'], $tipoPacote,
        $dataInicioPacote->format('Y-m-d'), $dataFimPacote->format('Y-m-d'),
        $valorPacote, $formasPagamento[array_rand($formasPagamento)], 'Ativo',
    ]);

    // Frequência: semanal (Mensal), quinzenal (Quinzenal), 1x só (Avulso)
    $intervaloDias = $tipoPacote === 'Mensal' ? 7 : ($tipoPacote === 'Quinzenal' ? 14 : 30);
    $dataAtendimento = clone $dataInicioPacote;
    $unidade = $unidades[array_rand($unidades)];

    while ($dataAtendimento <= $hoje) {
        $status = $statusPossiveis[array_rand($statusPossiveis)];
        $valorSessao = $tipoPacote === 'Mensal' ? 200 : ($tipoPacote === 'Quinzenal' ? 225 : 200);

        $atendId = gerarId('A');
        $evolucao = ($status === 'Confirmado' && $paciente['psicologa'] && mt_rand(0, 1))
            ? $evolucoesExemplo[array_rand($evolucoesExemplo)]
            : null;

        $stmt = $db->prepare('INSERT INTO atendimentos (id_atendimento, paciente_id, paciente_nome, data_atendimento, tipo_pacote, data_inicio_pacote, status, unidade, evolucao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $atendId, $paciente['id'], $paciente['nome'],
            $dataAtendimento->format('Y-m-d'), $tipoPacote, $dataInicioPacote->format('Y-m-d'),
            $status, $unidade, $evolucao,
        ]);
        $totalAtendimentos++;

        if ($status === 'Confirmado') {
            $despesaAuto = round($valorSessao * 0.25, 2);
            $receitaDisp = round($valorSessao - $despesaAuto, 2);
            $finId = gerarId('fin_');
            $stmt = $db->prepare('INSERT INTO financeiro (id, paciente_id, paciente_nome, clinica, tipo_pacote, data_inicio_pacote, data, valor, forma_pagamento, nf_emitida, despesa_automatica, receita_disponivel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $finId, $paciente['id'], $paciente['nome'], $unidade, $tipoPacote,
                $dataInicioPacote->format('Y-m-d'), $dataAtendimento->format('Y-m-d'),
                $valorSessao, $formasPagamento[array_rand($formasPagamento)],
                mt_rand(0, 1), $despesaAuto, $receitaDisp,
            ]);
            $totalFinanceiro++;
        }

        $dataAtendimento->modify("+$intervaloDias days");
        if ($tipoPacote === 'Avulso') break; // só uma sessão
    }
}
out("$totalAtendimentos atendimentos criados, $totalFinanceiro lançamentos financeiros criados.");

// ============ 4) DESPESAS ============
out(PHP_EOL . '--- Criando despesas de demonstração ---');

$despesasDemo = [
    ['Aluguel da sala - Espaço Guanais', 'Fixa', 2200.00, 1, 1, 5],
    ['Aluguel da sala - Anima', 'Fixa', 1800.00, 1, 1, 10],
    ['Internet e telefonia', 'Fixa', 189.90, 1, 1, 15],
    ['Material de escritório', 'Fixa', 340.00, 1, 0, 20],
    ['Notebook novo (parcelado)', 'Extra/Investimento', 4200.00, 10, 3, 8],
    ['Curso de formação continuada', 'Extra/Investimento', 1500.00, 3, 1, 12],
    ['Marketing e divulgação', 'Extra/Investimento', 600.00, 2, 2, 25],
    ['Reforma da recepção', 'Extra/Investimento', 3800.00, 6, 2, 18],
];

$hojeStr = (new DateTime())->modify('-45 days')->format('Y-m-d');
foreach ($despesasDemo as [$descricao, $categoria, $valor, $parcelas, $parcelasPagas, $diaVenc]) {
    $despId = gerarId('desp_');
    $stmt = $db->prepare('INSERT INTO despesas (id, descricao, categoria, valor_total, num_parcelas, parcelas_pagas, dia_vencimento, data_inicio) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$despId, $descricao, $categoria, $valor, $parcelas, $parcelasPagas, $diaVenc, $hojeStr]);
}
out(count($despesasDemo) . ' despesas criadas.');

out(PHP_EOL . '===== Seed concluído com sucesso! =====');
out('Login demo: dra.camila / demo123 (psicóloga) ou ana.secretaria / demo123 (secretaria)');
