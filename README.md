# Espaço Guanais - Sistema de Gestão Clínica

Sistema completo de gestão para clínicas, com frontend moderno e API PHP/MySQL.

## 📋 Funcionalidades

- **Agenda**: Visualização e gestão de atendimentos com filtros
- **Pacientes**: Cadastro completo com dados pessoais e contatos de emergência
- **Financeiro**: Controle de recebimentos com cálculo automático de líquido (75%) e custos (25%)
- **Despesas**: Gestão de despesas fixas e extras com controle de parcelas
- **Relatórios**: Dashboard com indicadores em tempo real
- **Importação/Exportação**: Backup em Excel
- **Tema**: Modo claro/escuro

## 🚀 Instalação no XAMPP

### 1. Pré-requisitos
- XAMPP instalado (PHP 7.4+ e MySQL)
- Navegador web moderno

### 2. Configurar Banco de Dados

1. Inicie o Apache e MySQL no XAMPP
2. Acesse o phpMyAdmin em `http://localhost/phpmyadmin`
3. Execute o script SQL:
   - Clique em "SQL" na barra superior
   - Cole o conteúdo do arquivo `sql/database.sql`
   - Clique em "Executar"

### 3. Configurar Arquivos

1. Copie todos os arquivos para a pasta do XAMPP:
   ```
   C:\xampp\htdocs\guanais\
   ```

2. A estrutura deve ficar assim:
   ```
   htdocs/guanais/
   ├── index.html
   ├── style.css
   ├── script.js
   ├── README.md
   ├── api/
   │   ├── config.php
   │   ├── auth.php
   │   ├── pacientes.php
   │   ├── atendimentos.php
   │   ├── financeiro.php
   │   └── despesas.php
   ├── sql/
   │   └── database.sql
   └── logo/
       └── Gemini_Generated_Image_mt9rkpmt9rkpmt9r.png
   ```

### 4. Ajustar Credenciais (se necessário)

Edite o arquivo `api/config.php` se suas credenciais do MySQL forem diferentes:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'espaco_guanais');
define('DB_USER', 'root');
define('DB_PASS', ''); // Senha do MySQL (padrão XAMPP é vazio)
```

### 5. Acessar o Sistema

1. Abra o navegador e acesse: `http://localhost/guanais`
2. Faça login com as credenciais padrão:
   - **Usuário**: `admin`
   - **Senha**: `0301`

## 🔧 Solução de Problemas

### Erro de conexão com banco de dados
- Verifique se o MySQL está rodando no XAMPP
- Confirme as credenciais no `api/config.php`
- Certifique-se de que o banco `espaco_guanais` foi criado

### API não responde
- Verifique se o Apache está rodando
- Confirme se os arquivos estão na pasta correta
- Verifique os logs de erro do Apache

### CORS Error no navegador
- Os headers CORS já estão configurados no `config.php`
- Se estiver acessando por domínio diferente, ajuste o `Access-Control-Allow-Origin`

## 📁 Estrutura do Projeto

```
├── index.html          # Página principal (frontend)
├── style.css           # Estilos CSS modernos
├── script.js           # Lógica JavaScript com fetch API
├── README.md           # Este arquivo
│
├── api/                # Backend PHP
│   ├── config.php      # Configuração e conexão MySQL
│   ├── auth.php        # Autenticação (login/logout)
│   ├── pacientes.php   # CRUD de pacientes
│   ├── atendimentos.php # CRUD de atendimentos
│   ├── financeiro.php  # CRUD financeiro
│   └── despesas.php    # CRUD de despesas
│
├── sql/                # Scripts de banco
│   └── database.sql    # Criação do banco e tabelas
│
└── logo/               # Imagens
    └── logo.png        # Logo do sistema
```

## 🔐 Segurança

- Senhas armazenadas com hash MD5 (para demonstração - em produção use `password_hash`)
- Session management com validação no servidor
- API com CORS configurado
- Dados sanitizados antes de salvar no banco
- Proteção contra SQL Injection com PDO Prepared Statements

## 📱 Responsividade

O sistema é totalmente responsivo e funciona em:
- Desktop (1920x1080+)
- Tablets (768x1024)
- Smartphones (375x667+)

## 🔄 Backup e Restauração

### Exportar Dados
1. Clique no botão "Backup" no cabeçalho
2. Um arquivo Excel será baixado com todos os dados

### Importar Dados
1. Clique no botão "Importar" no cabeçalho
2. Selecione um arquivo Excel (.xlsx, .xls, .csv)
3. Os dados serão adicionados ao sistema

## 📊 API Endpoints

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `api/auth.php` | POST | Login |
| `api/auth.php` | GET | Verificar autenticação |
| `api/auth.php` | DELETE | Logout |
| `api/pacientes.php` | GET | Listar pacientes |
| `api/pacientes.php` | POST | Criar paciente |
| `api/pacientes.php` | PUT | Atualizar paciente |
| `api/pacientes.php` | DELETE | Excluir paciente |
| `api/atendimentos.php` | GET | Listar atendimentos |
| `api/atendimentos.php` | POST | Criar atendimento |
| `api/atendimentos.php` | PUT | Atualizar atendimento |
| `api/atendimentos.php` | DELETE | Excluir atendimento |
| `api/financeiro.php` | GET | Listar lançamentos |
| `api/financeiro.php` | POST | Criar lançamento |
| `api/financeiro.php` | DELETE | Excluir lançamento |
| `api/despesas.php` | GET | Listar despesas |
| `api/despesas.php` | POST | Criar despesa |
| `api/despesas.php` | PUT | Atualizar despesa |
| `api/despesas.php` | DELETE | Excluir despesa |
| `api/despesas.php` | PATCH | Pagar parcela |

## 🛠️ Tecnologias Utilizadas

- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Estilos**: Bootstrap 5, CSS Custom Properties
- **Backend**: PHP 7.4+, PDO
- **Banco de Dados**: MySQL/MariaDB
- **Bibliotecas**: Bootstrap Icons, SheetJS (XLSX), Chart.js

## 📝 Licença

Sistema desenvolvido para uso interno do Espaço Guanais.

---

**Desenvolvido com ❤️ para Gestão Clínica**