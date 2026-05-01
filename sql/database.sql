-- SISTEMA DE GESTÃO CLÍNICA ESPAÇO GUANAIS
-- Script completo de criação do banco de dados
-- Compatível com MySQL 5.7+ / MariaDB 10.2+
-- Versão atualizada: 2026-04-29 (Sincronização completa de campos e views)
-- =====================================================

-- Criar banco de dados se não existir
CREATE DATABASE IF NOT EXISTS espaco_guanais 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE espaco_guanais;

-- -----------------------------------------------------
-- Tabela de usuários (autenticação)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    tipo ENUM('admin', 'terapeuta', 'secretaria') DEFAULT 'secretaria',
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir usuário padrão: admin / 0301 (se não existir)
-- Senha hash gerada com password_hash('0301', PASSWORD_DEFAULT)
INSERT IGNORE INTO usuarios (usuario, senha, nome, email, tipo) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin@espacoguanais.com.br', 'admin');

-- -----------------------------------------------------
-- Tabela de permissões
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS permissoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    acao VARCHAR(50) NOT NULL,
    permitido BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_permissao (usuario_id, modulo, acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela de configurações do sistema
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    tipo ENUM('texto', 'numero', 'booleano', 'arquivo') DEFAULT 'texto',
    descricao VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações padrão
INSERT IGNORE INTO configuracoes (chave, valor, tipo, descricao) VALUES
('nome_sistema', 'Espaço Guanais', 'texto', 'Nome do sistema exibido no cabeçalho'),
('subtitulo_sistema', 'Gestão Integrada', 'texto', 'Subtítulo exibido abaixo do nome'),
('logo_path', 'logo/logo.png', 'arquivo', 'Caminho da logo do sistema'),
('logo_login', 'logo/logo.png', 'arquivo', 'Caminho da logo na tela de login'),
('tema_padrao', 'light', 'texto', 'Tema padrão do sistema (light/dark)'),
('permite_cadastro_online', '0', 'booleano', 'Permitir cadastro online de pacientes');

-- -----------------------------------------------------
-- Tabela de pacientes
-- -----------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela de pacotes
-- -----------------------------------------------------
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
    INDEX idx_pacotes_paciente (paciente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela de atendimentos
-- -----------------------------------------------------
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
    evolucao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_atendimentos_data (data_atendimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela financeiro (receitas)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS financeiro (
    id VARCHAR(30) PRIMARY KEY,
    paciente_id VARCHAR(20) NOT NULL,
    paciente_nome VARCHAR(200) NOT NULL,
    clinica ENUM('ANIMO', 'ESPAÇO GUANAIS') NOT NULL,
    tipo_pacote ENUM('Quinzenal', 'Mensal', 'Avulso'),
    data_inicio_pacote DATE,
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
    INDEX idx_financeiro_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela de despesas
-- -----------------------------------------------------
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
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Views
-- -----------------------------------------------------
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
GROUP BY YEAR(f.data), MONTH(f.data), f.clinica;

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
WHERE p.ativo = TRUE;
