<?php
/**
 * install.php
 * Script de instalação/inicialização do banco de dados
 * Espaço Guanais - Sistema de Gestão Clínica
 * 
 * Execute este arquivo uma vez para configurar o banco de dados corretamente.
 * Acesse: http://localhost/PROJETO/api/install.php
 */

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'espaco_guanais');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Headers
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Espaço Guanais</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f9ff; padding: 40px 20px; }
        .install-box { max-width: 800px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .success { color: #059669; }
        .error { color: #dc2626; }
        .info { color: #0284c7; }
        pre { background: #f1f5f9; padding: 15px; border-radius: 8px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="install-box">
        <h1 class="mb-4">🏥 Instalação - Espaço Guanais</h1>
        <p class="text-muted mb-4">Este script vai configurar o banco de dados e criar o usuário administrador.</p>
        
        <?php
        try {
            // Conexão
            $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            echo "<div class='alert alert-info'>✅ Conexão com MySQL estabelecida.</div>";
            
            // Criar banco de dados
            $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE " . DB_NAME);
            echo "<div class='alert alert-success'>✅ Banco de dados '" . DB_NAME . "' criado/selecionado.</div>";
            
            // Tabela de usuários
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS usuarios (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    usuario VARCHAR(50) UNIQUE NOT NULL,
                    senha VARCHAR(255) NOT NULL,
                    nome VARCHAR(100) NOT NULL,
                    email VARCHAR(100),
                    tipo ENUM('admin', 'terapeuta', 'secretaria', 'psicologa') DEFAULT 'secretaria',
                    abordagem VARCHAR(120) NULL,
                    temas TEXT NULL,
                    formacao_academica TEXT NULL,
                    idiomas TEXT NULL,
                    idade INT NULL,
                    foto_perfil VARCHAR(255) NULL,
                    tipo_psicoterapia VARCHAR(120) NULL,
                    ativo BOOLEAN DEFAULT TRUE,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            echo "<div class='alert alert-success'>✅ Tabela 'usuarios' criada.</div>";
            
            // Tabela de permissões
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS permissoes (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    usuario_id INT NOT NULL,
                    modulo VARCHAR(50) NOT NULL,
                    acao VARCHAR(50) NOT NULL,
                    permitido BOOLEAN DEFAULT TRUE,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
                    UNIQUE KEY unique_permissao (usuario_id, modulo, acao)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            echo "<div class='alert alert-success'>✅ Tabela 'permissoes' criada.</div>";
            
            // Tabela de pacientes
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS pacientes (
                    id VARCHAR(20) PRIMARY KEY,
                    nome VARCHAR(200) NOT NULL,
                    cpf VARCHAR(14),
                    data_nascimento DATE NOT NULL,
                    telefone VARCHAR(20) NOT NULL,
                    email VARCHAR(100),
                    endereco TEXT,
                    responsavel_nome VARCHAR(200),
                    responsavel_telefone VARCHAR(20),
                    emergencia_nome VARCHAR(200),
                    emergencia_telefone VARCHAR(20),
                    emergencia_parentesco VARCHAR(50),
                    emergencia_info_adicionais TEXT,
                    ativo BOOLEAN DEFAULT TRUE,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_pacientes_nome (nome)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            echo "<div class='alert alert-success'>✅ Tabela 'pacientes' criada/atualizada.</div>";
            
            // Tabela de configurações
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS configuracoes (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    chave VARCHAR(100) UNIQUE NOT NULL,
                    valor TEXT,
                    tipo ENUM('texto', 'numero', 'booleano', 'arquivo') DEFAULT 'texto',
                    descricao VARCHAR(255),
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            echo "<div class='alert alert-success'>✅ Tabela 'configuracoes' criada.</div>";

            // Inserir configurações padrão
            $pdo->exec("
                INSERT IGNORE INTO configuracoes (chave, valor, tipo, descricao) VALUES
                ('nome_sistema', 'Espaço Guanais', 'texto', 'Nome do sistema exibido no cabeçalho'),
                ('subtitulo_sistema', 'Gestão Integrada', 'texto', 'Subtítulo exibido abaixo do nome'),
                ('logo_path', 'logo/logo.png', 'arquivo', 'Caminho da logo do sistema'),
                ('logo_login', 'logo/logo.png', 'arquivo', 'Caminho da logo na tela de login'),
                ('tema_padrao', 'light', 'texto', 'Tema padrão do sistema (light/dark)'),
                ('permite_cadastro_online', '0', 'booleano', 'Permitir cadastro online de pacientes')
            ");
            echo "<div class='alert alert-success'>✅ Configurações padrão inseridas.</div>";
            
            // Tabela de pacotes
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS pacotes (
                    id VARCHAR(20) PRIMARY KEY,
                    paciente_id VARCHAR(20) NOT NULL,
                    tipo_pacote ENUM('Quinzenal', 'Mensal', 'Avulso') NOT NULL,
                    data_inicio DATE NOT NULL,
                    data_fim DATE,
                    valor_total DECIMAL(10,2) NOT NULL,
                    forma_pagamento ENUM('Pix', 'Dinheiro', 'Cartão Crédito', 'Cartão Débito') NOT NULL,
                    status ENUM('Ativo', 'Vencido', 'Cancelado') DEFAULT 'Ativo',
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON UPDATE CASCADE ON DELETE CASCADE,
                    INDEX idx_pacotes_paciente (paciente_id),
                    INDEX idx_pacotes_status (status),
                    INDEX idx_pacotes_data (data_inicio, data_fim)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            echo "<div class='alert alert-success'>✅ Tabela 'pacotes' criada.</div>";
            
            // Tabela de atendimentos
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS atendimentos (
                    id_atendimento VARCHAR(20) PRIMARY KEY,
                    paciente_id VARCHAR(20) NOT NULL,
                    paciente_nome VARCHAR(200) NOT NULL,
                    data_atendimento DATE NOT NULL,
                    tipo_pacote ENUM('Quinzenal', 'Mensal', 'Avulso') NOT NULL,
                    data_inicio_pacote DATE NOT NULL,
                    status ENUM('Confirmado', 'Falta', 'Reagendado', 'Exceção Justificada') DEFAULT 'Confirmado',
                    unidade ENUM('ANIMO', 'ESPAÇO GUANAIS') NOT NULL,
                    observacoes TEXT,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON UPDATE CASCADE ON DELETE CASCADE,
                    INDEX idx_atendimentos_data (data_atendimento),
                    INDEX idx_atendimentos_paciente (paciente_id),
                    INDEX idx_atendimentos_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            echo "<div class='alert alert-success'>✅ Tabela 'atendimentos' criada.</div>";
            
            // Tabela financeiro
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS financeiro (
                    id VARCHAR(30) PRIMARY KEY,
                    paciente_id VARCHAR(20) NOT NULL,
                    paciente_nome VARCHAR(200) NOT NULL,
                    clinica ENUM('ANIMO', 'ESPAÇO GUANAIS') NOT NULL,
                    tipo_pacote ENUM('Quinzenal', 'Mensal', 'Avulso'),
                    data DATE NOT NULL,
                    valor DECIMAL(10,2) NOT NULL,
                    forma_pagamento ENUM('Pix', 'Dinheiro', 'Cartão Crédito', 'Cartão Débito') NOT NULL,
                    nf_emitida BOOLEAN DEFAULT FALSE,
                    observacoes TEXT,
                    despesa_automatica DECIMAL(10,2) DEFAULT 0,
                    receita_disponivel DECIMAL(10,2) DEFAULT 0,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON UPDATE CASCADE ON DELETE CASCADE,
                    INDEX idx_financeiro_data (data),
                    INDEX idx_financeiro_paciente (paciente_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            echo "<div class='alert alert-success'>✅ Tabela 'financeiro' criada.</div>";
            
            // Tabela de despesas
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS despesas (
                    id VARCHAR(30) PRIMARY KEY,
                    descricao VARCHAR(200) NOT NULL,
                    categoria ENUM('Fixa', 'Extra/Investimento') NOT NULL,
                    valor_total DECIMAL(10,2) NOT NULL,
                    num_parcelas INT DEFAULT 1,
                    parcelas_pagas INT DEFAULT 0,
                    dia_vencimento INT,
                    data_inicio DATE,
                    ativo BOOLEAN DEFAULT TRUE,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_despesas_categoria (categoria)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            echo "<div class='alert alert-success'>✅ Tabela 'despesas' criada.</div>";
            
            // Criar usuário admin com senha hash correta
            $senhaAdmin = '0301';
            $senhaHash = password_hash($senhaAdmin, PASSWORD_DEFAULT);
            
            // Verificar se admin já existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = 'admin'");
            $stmt->execute();
            $adminExiste = $stmt->fetch();
            
            if (!$adminExiste) {
                $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, senha, nome, email, tipo) VALUES ('admin', ?, 'Administrador', 'admin@espacoguanais.com.br', 'admin')");
                $stmt->execute([$senhaHash]);
                $adminId = $pdo->lastInsertId();
                echo "<div class='alert alert-success'>✅ Usuário 'admin' criado com senha hash correta.</div>";
                
                // Inserir permissões do admin
                $modulos = ['pacientes', 'atendimentos', 'financeiro', 'despesas', 'configuracoes'];
                $acoes = ['visualizar', 'criar', 'editar', 'excluir'];
                
                $stmtPerm = $pdo->prepare("INSERT INTO permissoes (usuario_id, modulo, acao, permitido) VALUES (?, ?, ?, 1)");
                foreach ($modulos as $modulo) {
                    foreach ($acoes as $acao) {
                        $stmtPerm->execute([$adminId, $modulo, $acao]);
                    }
                }
                echo "<div class='alert alert-success'>✅ Permissões do admin configuradas.</div>";
            } else {
                // Atualizar senha do admin existente
                $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE usuario = 'admin'");
                $stmt->execute([$senhaHash]);
                echo "<div class='alert alert-info'>ℹ️ Usuário 'admin' já existia. Senha atualizada.</div>";
            }
            
            // View resumo financeiro
            $pdo->exec("
                CREATE OR REPLACE VIEW vw_resumo_financeiro AS
                SELECT 
                    YEAR(f.data) as ano,
                    MONTH(f.data) as mes,
                    f.clinica,
                    COUNT(*) as total_lancamentos,
                    SUM(f.valor) as total_bruto,
                    SUM(f.despesa_automatica) as total_despesas,
                    SUM(f.receita_disponivel) as total_liquido,
                    SUM(f.valor * 0.25) as total_custos
                FROM financeiro f
                GROUP BY YEAR(f.data), MONTH(f.data), f.clinica
                ORDER BY ano DESC, mes DESC
            ");
            echo "<div class='alert alert-success'>✅ View 'vw_resumo_financeiro' criada.</div>";
            
            // View pacientes e pacotes
            $pdo->exec("
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
                ORDER BY p.nome
            ");
            echo "<div class='alert alert-success'>✅ View 'vw_pacientes_pacotes' criada.</div>";
            
            echo "<hr>";
            echo "<div class='alert alert-success'>";
            echo "<h4>🎉 Instalação concluída com sucesso!</h4>";
            echo "<p><strong>Credenciais de acesso:</strong></p>";
            echo "<p>Usuário: <code>admin</code></p>";
            echo "<p>Senha: <code>0301</code></p>";
            echo "<p><a href='../index.html' class='btn btn-primary mt-2'>Ir para o Sistema</a></p>";
            echo "</div>";
            
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>";
            echo "<h4>❌ Erro na instalação</h4>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
        ?>
        
        <hr class="my-4">
        <h5>📋 Instruções:</h5>
        <ol>
            <li>Após a instalação, acesse <code>index.html</code> no navegador.</li>
            <li>Use as credenciais <strong>admin / 0301</strong> para login.</li>
            <li>Este arquivo pode ser excluído após a instalação por segurança.</li>
        </ol>
    </div>
</body>
</html>