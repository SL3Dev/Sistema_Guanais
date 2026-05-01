<?php
/**
 * update_db.php
 * Script para aplicar atualizações estruturais no banco de dados.
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'config.php';
require_once 'auth.php'; // Para usar requireAuth e hasPermission

// Apenas administradores podem executar este script
requireAuth();
if (!hasPermission('configuracoes', 'editar')) {
    errorResponse('Permissão negada. Apenas administradores podem atualizar o banco de dados.', 403);
}

$db = Database::getInstance()->getConnection();

try {
    $db->beginTransaction();

    $updates = [];

    // 1. Corrigir campo data_nascimento na tabela pacientes
    $stmt = $db->query("SHOW COLUMNS FROM pacientes LIKE 'data_nasc'");
    if ($stmt->rowCount() > 0) {
        $db->exec("ALTER TABLE pacientes CHANGE COLUMN data_nasc data_nascimento DATE NOT NULL");
        $updates[] = "Renomeado campo 'data_nasc' para 'data_nascimento' na tabela 'pacientes'.";
    }

    // 2. Adicionar campos de emergência à tabela pacientes (se não existirem)
    $stmt = $db->query("SHOW COLUMNS FROM pacientes LIKE 'emergencia_parentesco'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE pacientes ADD COLUMN emergencia_parentesco VARCHAR(50) AFTER emergencia_telefone");
        $updates[] = "Adicionado campo 'emergencia_parentesco' à tabela 'pacientes'.";
    }

    $stmt = $db->query("SHOW COLUMNS FROM pacientes LIKE 'emergencia_info_adicionais'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE pacientes ADD COLUMN emergencia_info_adicionais TEXT AFTER emergencia_parentesco");
        $updates[] = "Adicionado campo 'emergencia_info_adicionais' à tabela 'pacientes'.";
    }

    // 2. Criar tabela de configurações (se não existir)
    $stmt = $db->query("SHOW TABLES LIKE 'configuracoes'");
    if ($stmt->rowCount() == 0) {
        $db->exec("
            CREATE TABLE configuracoes (
                id INT PRIMARY KEY AUTO_INCREMENT,
                chave VARCHAR(100) UNIQUE NOT NULL,
                valor TEXT,
                tipo ENUM('texto', 'numero', 'booleano', 'arquivo') DEFAULT 'texto',
                descricao VARCHAR(255),
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $updates[] = "Criada tabela 'configuracoes'.";

        // Inserir configurações padrão se a tabela foi recém-criada
        $db->exec("
            INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES
            ('nome_sistema', 'Espaço Guanais', 'texto', 'Nome do sistema exibido no cabeçalho'),
            ('logo_path', 'logo/logo.png', 'arquivo', 'Caminho da logo do sistema'),
            ('logo_login', 'logo/logo.png', 'arquivo', 'Caminho da logo na tela de login'),
            ('tema_padrao', 'light', 'texto', 'Tema padrão do sistema (light/dark)'),
            ('permite_cadastro_online', '0', 'booleano', 'Permitir cadastro online de pacientes')
            ON DUPLICATE KEY UPDATE valor=VALUES(valor);
        ");
        $updates[] = "Inseridas configurações padrão na tabela 'configuracoes'.";
    } else {
        // Se a tabela já existe, garantir que as configurações padrão estejam presentes
        $defaultConfigs = [
            ['nome_sistema', 'Espaço Guanais', 'texto', 'Nome do sistema exibido no cabeçalho'],
            ['logo_path', 'logo/logo.png', 'arquivo', 'Caminho da logo do sistema'],
            ['logo_login', 'logo/logo.png', 'arquivo', 'Caminho da logo na tela de login'],
            ['tema_padrao', 'light', 'texto', 'Tema padrão do sistema (light/dark)'],
            ['permite_cadastro_online', '0', 'booleano', 'Permitir cadastro online de pacientes']
        ];
        foreach ($defaultConfigs as $config) {
            $stmt = $db->prepare("INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE valor=VALUES(valor), tipo=VALUES(tipo), descricao=VALUES(descricao)");
            $stmt->execute($config);
            if ($stmt->rowCount() > 0) {
                $updates[] = "Configuração '{$config[0]}' verificada/atualizada.";
            }
        }
    }

    // 3. Adicionar colunas despesa_automatica e receita_disponivel à tabela financeiro (se não existirem)
    $stmt = $db->query("SHOW COLUMNS FROM financeiro LIKE 'despesa_automatica'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE financeiro ADD COLUMN despesa_automatica DECIMAL(10,2) DEFAULT 0 AFTER observacoes");
        $updates[] = "Adicionado campo 'despesa_automatica' à tabela 'financeiro'.";
    }

    $stmt = $db->query("SHOW COLUMNS FROM financeiro LIKE 'receita_disponivel'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE financeiro ADD COLUMN receita_disponivel DECIMAL(10,2) DEFAULT 0 AFTER despesa_automatica");
        $updates[] = "Adicionado campo 'receita_disponivel' à tabela 'financeiro'.";
    }

    // 4. Atualizar valores existentes para despesa_automatica e receita_disponivel
    // Apenas se as colunas foram recém-adicionadas ou se houver valores nulos/zero
    if (in_array("Adicionado campo 'despesa_automatica' à tabela 'financeiro'.", $updates) ||
        in_array("Adicionado campo 'receita_disponivel' à tabela 'financeiro'.", $updates)) {
        $db->exec("UPDATE financeiro SET despesa_automatica = valor * 0.25, receita_disponivel = valor * 0.75 WHERE despesa_automatica = 0 OR receita_disponivel = 0");
        $updates[] = "Atualizados valores de 'despesa_automatica' e 'receita_disponivel' na tabela 'financeiro'.";
    }

    // 5. Adicionar colunas data_inicio e dia_vencimento à tabela despesas (se não existirem)
    $stmt = $db->query("SHOW COLUMNS FROM despesas LIKE 'dia_vencimento'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE despesas ADD COLUMN dia_vencimento INT AFTER parcelas_pagas");
        $updates[] = "Adicionado campo 'dia_vencimento' à tabela 'despesas'.";
    }

    $stmt = $db->query("SHOW COLUMNS FROM despesas LIKE 'data_inicio'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE despesas ADD COLUMN data_inicio DATE AFTER dia_vencimento");
        $updates[] = "Adicionado campo 'data_inicio' à tabela 'despesas'.";
    }

    // 6. Adicionar colunas paciente_nome e data_inicio_pacote à tabela atendimentos (se não existirem)
    $stmt = $db->query("SHOW COLUMNS FROM atendimentos LIKE 'paciente_nome'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE atendimentos ADD COLUMN paciente_nome VARCHAR(200) NOT NULL AFTER paciente_id");
        $updates[] = "Adicionado campo 'paciente_nome' à tabela 'atendimentos'.";
        // Tentar preencher com dados existentes
        $db->exec("UPDATE atendimentos a JOIN pacientes p ON a.paciente_id = p.id SET a.paciente_nome = p.nome WHERE a.paciente_nome IS NULL OR a.paciente_nome = ''");
        $updates[] = "Preenchido 'paciente_nome' na tabela 'atendimentos' com dados de 'pacientes'.";
    }

    $stmt = $db->query("SHOW COLUMNS FROM atendimentos LIKE 'data_inicio_pacote'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE atendimentos ADD COLUMN data_inicio_pacote DATE NOT NULL AFTER tipo_pacote");
        $updates[] = "Adicionado campo 'data_inicio_pacote' à tabela 'atendimentos'.";
        // Tentar preencher com dados existentes (pode ser necessário uma lógica mais complexa aqui)
        // Por simplicidade, vamos preencher com a data do atendimento se não houver pacote associado
        $db->exec("UPDATE atendimentos SET data_inicio_pacote = data_atendimento WHERE data_inicio_pacote IS NULL");
        $updates[] = "Preenchido 'data_inicio_pacote' na tabela 'atendimentos' com 'data_atendimento' para registros sem valor.";
    }

    // 6.1 Adicionar campo evolucao na tabela atendimentos (se não existir)
    $stmt = $db->query("SHOW COLUMNS FROM atendimentos LIKE 'evolucao'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE atendimentos ADD COLUMN evolucao TEXT AFTER observacoes");
        $updates[] = "Adicionado campo 'evolucao' à tabela 'atendimentos'.";
    }

    // 7. Adicionar colunas paciente_nome, tipo_pacote e data_inicio_pacote à tabela financeiro (se não existirem)
    $stmt = $db->query("SHOW COLUMNS FROM financeiro LIKE 'paciente_nome'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE financeiro ADD COLUMN paciente_nome VARCHAR(200) NOT NULL AFTER paciente_id");
        $updates[] = "Adicionado campo 'paciente_nome' à tabela 'financeiro'.";
        // Tentar preencher com dados existentes
        $db->exec("UPDATE financeiro f JOIN pacientes p ON f.paciente_id = p.id SET f.paciente_nome = p.nome WHERE f.paciente_nome IS NULL OR f.paciente_nome = ''");
        $updates[] = "Preenchido 'paciente_nome' na tabela 'financeiro' com dados de 'pacientes'.";
    }

    $stmt = $db->query("SHOW COLUMNS FROM financeiro LIKE 'tipo_pacote'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE financeiro ADD COLUMN tipo_pacote ENUM('Quinzenal', 'Mensal', 'Avulso') AFTER clinica");
        $updates[] = "Adicionado campo 'tipo_pacote' à tabela 'financeiro'.";
    }

    $stmt = $db->query("SHOW COLUMNS FROM financeiro LIKE 'data_inicio_pacote'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE financeiro ADD COLUMN data_inicio_pacote DATE AFTER tipo_pacote");
        $updates[] = "Adicionado campo 'data_inicio_pacote' à tabela 'financeiro'.";
    }

    // 8. Criar ou atualizar views
    $db->exec("
        CREATE OR REPLACE VIEW vw_resumo_financeiro AS
        SELECT 
            YEAR(f.data) as ano,
            MONTH(f.data) as mes,
            f.clinica,
            COUNT(*) as total_lancamentos,
            SUM(f.valor) as total_bruto,
            SUM(f.despesa_automatica) as total_despesas,
            SUM(f.receita_disponivel) as total_liquido
        FROM financeiro f
        GROUP BY YEAR(f.data), MONTH(f.data), f.clinica
        ORDER BY ano DESC, mes DESC;
    ");
    $updates[] = "View 'vw_resumo_financeiro' criada/atualizada.";

    $db->exec("
        CREATE OR REPLACE VIEW vw_pacientes_pacotes AS
        SELECT 
            p.id,
            p.nome,
            p.telefone,
            p.email,
            p.ativo as paciente_ativo,
            pa.id as pacote_id,
            pa.tipo_pacote,
            pa.data_inicio,
            pa.data_fim,
            pa.valor_total,
            pa.forma_pagamento,
            pa.status as pacote_status,
            CASE 
                WHEN pa.data_fim IS NULL THEN 'Sem data de fim'
                WHEN pa.data_fim < CURDATE() THEN 'Vencido'
                ELSE 'Vigente'
            END as vigencia_status
        FROM pacientes p
        LEFT JOIN (
            SELECT paciente_id, id, tipo_pacote, data_inicio, data_fim, valor_total, forma_pagamento, status,
                   ROW_NUMBER() OVER (PARTITION BY paciente_id ORDER BY data_inicio DESC) as rn
            FROM pacotes
            WHERE status = 'Ativo'
        ) pa ON p.id = pa.paciente_id AND pa.rn = 1
        WHERE p.ativo = TRUE
        ORDER BY p.nome;
    ");
    $updates[] = "View 'vw_pacientes_pacotes' criada/atualizada.";

    // 9. Atualizar estrutura da tabela usuarios para suportar psicóloga e campos adicionais
    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'tipo'");
    if ($stmt->rowCount() > 0) {
        $db->exec("ALTER TABLE usuarios MODIFY COLUMN tipo ENUM('admin', 'terapeuta', 'secretaria', 'psicologa') DEFAULT 'secretaria'");
        $updates[] = "Campo 'tipo' da tabela 'usuarios' atualizado para incluir 'psicologa'.";
    }

    $novosCamposUsuarios = [
        "abordagem VARCHAR(120) NULL",
        "temas TEXT NULL",
        "formacao_academica TEXT NULL",
        "idiomas TEXT NULL",
        "idade INT NULL",
        "foto_perfil VARCHAR(255) NULL",
        "tipo_psicoterapia VARCHAR(120) NULL"
    ];

    foreach ($novosCamposUsuarios as $colDef) {
        $colName = explode(' ', $colDef)[0];
        $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE '{$colName}'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE usuarios ADD COLUMN {$colDef}");
            $updates[] = "Adicionado campo '{$colName}' à tabela 'usuarios'.";
        }
    }

    // 3. Inserir novas configurações se não existirem
    $configs = [
        ['nome_sistema', 'Espaço Guanais', 'texto', 'Nome do sistema'],
        ['subtitulo_sistema', 'Gestão Integrada', 'texto', 'Subtítulo do sistema'],
        ['tema_padrao', 'light', 'texto', 'Tema padrão'],
        ['logo_path', 'logo/logo.png', 'arquivo', 'Logo do cabeçalho'],
        ['logo_login', 'logo/logo.png', 'arquivo', 'Logo do login']
    ];

    foreach ($configs as $c) {
        $stmt = $db->prepare("SELECT id FROM configuracoes WHERE chave = ?");
        $stmt->execute([$c[0]]);
        if ($stmt->rowCount() == 0) {
            $stmtInsert = $db->prepare("INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES (?, ?, ?, ?)");
            $stmtInsert->execute($c);
            $updates[] = "Adicionada configuração: {$c[0]}";
        }
    }

    $db->commit();
    successResponse(['updates' => $updates], 'Banco de dados atualizado com sucesso!');

} catch (PDOException $e) {
    $db->rollBack();
    errorResponse('Erro ao atualizar o banco de dados: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    $db->rollBack();
    errorResponse('Erro inesperado: ' . $e->getMessage(), 500);
}
