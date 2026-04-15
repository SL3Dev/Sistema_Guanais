-- =====================================================
-- SISTEMA DE GESTÃO CLÍNICA ESPAÇO GUANAIS
-- Script de criação do banco de dados
-- Compatível com MySQL 5.7+ / MariaDB 10.2+
-- =====================================================

-- Criar banco de dados (se não existir)
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

-- Inserir usuário padrão: admin / 0301
-- O hash abaixo é gerado com password_hash('0301', PASSWORD_DEFAULT)
-- Em produção, use password_hash() do PHP para gerar um hash único
INSERT INTO usuarios (usuario, senha, nome, email, tipo) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin@espacoguanais.com.br', 'admin')
ON DUPLICATE KEY UPDATE senha = VALUES(senha);

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
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_permissao (usuario_id, modulo, acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir permissões padrão para admin (acesso total)
INSERT INTO permissoes (usuario_id, modulo, acao, permitido) VALUES
(1, 'pacientes', 'visualizar', TRUE),
(1, 'pacientes', 'editar', TRUE),
(1, 'pacientes', 'excluir', TRUE),
(1, 'pacientes', 'criar', TRUE),
(1, 'atendimentos', 'visualizar', TRUE),
(1, 'atendimentos', 'editar', TRUE),
(1, 'atendimentos', 'excluir', TRUE),
(1, 'atendimentos', 'criar', TRUE),
(1, 'financeiro', 'visualizar', TRUE),
(1, 'financeiro', 'editar', TRUE),
(1, 'financeiro', 'excluir', TRUE),
(1, 'financeiro', 'criar', TRUE),
(1, 'despesas', 'visualizar', TRUE),
(1, 'despesas', 'editar', TRUE),
(1, 'despesas', 'excluir', TRUE),
(1, 'despesas', 'criar', TRUE),
(1, 'configuracoes', 'visualizar', TRUE),
(1, 'configuracoes', 'editar', TRUE),
(1, 'configuracoes', 'excluir', TRUE),
(1, 'configuracoes', 'criar', TRUE)
ON DUPLICATE KEY UPDATE permitido = VALUES(permitido);

-- -----------------------------------------------------
-- Tabela de pacientes
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS pacientes (
    id VARCHAR(20) PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    cpf VARCHAR(14),
    data_nasc DATE NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    endereco TEXT,
    responsavel_nome VARCHAR(200),
    responsavel_telefone VARCHAR(20),
    emergencia_nome VARCHAR(200),
    emergencia_telefone VARCHAR(20),
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
    INDEX idx_pacotes_paciente (paciente_id),
    INDEX idx_pacotes_status (status),
    INDEX idx_pacotes_data (data_inicio, data_fim)
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
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_atendimentos_data (data_atendimento),
    INDEX idx_atendimentos_paciente (paciente_id),
    INDEX idx_atendimentos_status (status)
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
    data DATE NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    forma_pagamento ENUM('Pix', 'Dinheiro', 'Cartão Crédito', 'Cartão Débito') NOT NULL,
    nf_emitida BOOLEAN DEFAULT FALSE,
    observacoes TEXT,
    despesa_automatica DECIMAL(10,2) DEFAULT 0, -- 25% do valor
    receita_disponivel DECIMAL(10,2) DEFAULT 0, -- 75% do valor
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_financeiro_data (data),
    INDEX idx_financeiro_paciente (paciente_id)
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
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_despesas_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Tabela de histórico/auditoria (opcional)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS historico (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tabela VARCHAR(50) NOT NULL,
    registro_id VARCHAR(50) NOT NULL,
    acao ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    dados_antigos JSON,
    dados_novos JSON,
    usuario VARCHAR(50),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- View para resumo financeiro mensal
-- -----------------------------------------------------
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
ORDER BY ano DESC, mes DESC;

-- -----------------------------------------------------
-- View para relatórios de pacientes e pacotes
-- -----------------------------------------------------
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

-- Fim do script