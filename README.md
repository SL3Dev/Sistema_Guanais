# Sistema de Gestão Clínica - Espaço Guanais

Sistema web para gestão de clínica de psicologia, com foco em controle de pacientes, atendimentos, financeiro e despesas.

## Funcionalidades Principais

- **Gestão de Pacientes**: Cadastro, edição e histórico de pacientes
- **Agenda de Atendimentos**: Controle de sessões, status e pacotes
- **Financeiro**: Registro de recebimentos, relatórios e controle de NF
- **Despesas**: Controle de despesas fixas e parceladas
- **Usuários e Permissões**: Sistema de autenticação e controle de acesso
- **Importação/Exportação**: Suporte a Excel para migração de dados

## Requisitos Técnicos

- **Servidor Web**: Apache 2.4+ ou Nginx
- **PHP**: 7.4+ (recomendado 8.0+)
- **Banco de Dados**: MySQL 5.7+ ou MariaDB 10.2+
- **Extensões PHP**: pdo_mysql, json, session, openssl
- **Armazenamento**: Mínimo 100MB (dependendo do volume de dados)

## Instalação

### 1. Configuração do Servidor

#### XAMPP (Windows/Linux)
```bash
# 1. Instalar XAMPP
# 2. Iniciar Apache e MySQL
# 3. Copiar arquivos para: c:/xampp/htdocs/Sistema_Guanais/
```

#### Linux (Apache)
```bash
# 1. Instalar Apache, MySQL, PHP
sudo apt update
sudo apt install apache2 mysql-server php php-mysql php-json

# 2. Copiar arquivos para /var/www/html/
sudo cp -r Sistema_Guanais /var/www/html/

# 3. Configurar permissões
sudo chown -R www-data:www-data /var/www/html/Sistema_Guanais
```

### 2. Banco de Dados

#### Opção A: Banco Novo (Recomendado)
1. Acesse o phpMyAdmin: `http://localhost/phpmyadmin`
2. Crie um novo banco de dados: `espaco_guanais`
3. Importe o script: `sql/database.sql`

#### Opção B: Atualizar Banco Existente
1. Execute o script: `sql/atualizar_banco.php`
2. Ou execute manualmente as alterações do `sql/database.sql`

### 3. Configuração do Sistema

1. **Ajustar configurações** em `api/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'espaco_guanais');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

2. **Verificar permissões** de arquivos:
   - Pasta `api/`: Leitura e escrita
   - Arquivos `.php`: Leitura
   - Pasta `sql/`: Leitura

### 4. Primeiro Acesso

1. Acesse: `http://localhost/Sistema_Guanais/`
2. Login: `admin`
3. Senha: `0301`
4. Recomenda-se alterar a senha após o primeiro login

## Estrutura de Arquivos

```
Sistema_Guanais/
├── api/                    # APIs RESTful
│   ├── config.php         # Configuração do banco
│   ├── auth.php           # Autenticação
│   ├── pacientes.php      # CRUD pacientes
│   ├── atendimentos.php   # CRUD atendimentos
│   ├── financeiro.php     # CRUD financeiro
│   ├── despesas.php       # CRUD despesas
│   └── usuarios.php       # CRUD usuários e permissões
├── sql/                   # Scripts de banco de dados
│   ├── database.sql       # Criação completa do banco
│   └── atualizar_banco.php # Atualização de bancos existentes
├── js/                    # JavaScript do frontend
│   └── script.js          # Lógica principal da aplicação
├── css/                   # Estilos
│   └── style.css          # Estilos principais
├── views/                 # Páginas HTML
│   └── login.html         # Tela de login
├── logo/                  # Logotipos
├── index.html             # Página principal
├── test_sistema.php       # Teste de funcionalidades
└── README.md             # Este arquivo
```

## Problemas Comuns e Soluções

### 1. Erro de Conexão com Banco
```php
// Verifique em api/config.php:
define('DB_HOST', 'localhost');  // Ou IP do servidor
define('DB_NAME', 'espaco_guanais');
define('DB_USER', 'root');       // Usuário MySQL
define('DB_PASS', '');           // Senha MySQL
```

**Solução**: Verifique se o MySQL está rodando e as credenciais estão corretas.

### 2. Permissões de Arquivo
```bash
# Linux/Mac - Corrigir permissões:
sudo chmod -R 755 /var/www/html/Sistema_Guanais
sudo chown -R www-data:www-data /var/www/html/Sistema_Guanais
```

### 3. Erro de Importação Excel
- **Problema**: Biblioteca XLSX não carregada
- **Solução**: Verifique se o arquivo `js/xlsx.full.min.js` está presente
- **Alternativa**: Use o importador interno do sistema

### 4. Pacientes Não Aparecem na Seleção
- **Problema**: Dados não sincronizados
- **Solução**: Atualize a página (F5) ou limpe cache do navegador
- **Verificação**: Confira se os pacientes foram importados corretamente

### 5. Erro 404 nas APIs
- **Problema**: URL incorreta ou .htaccess mal configurado
- **Solução**: Verifique se o mod_rewrite está habilitado no Apache
- **Teste**: Acesse `http://localhost/Sistema_Guanais/api/pacientes.php`

## Segurança

### Recomendações
1. **Alterar senha padrão** do usuário admin
2. **Atualizar PHP** para versão mais recente
3. **Configurar firewall** para restringir acesso ao MySQL
4. **Fazer backups** regulares do banco de dados
5. **Usar HTTPS** em ambiente de produção

### Configuração de Segurança
```apache
# .htaccess - Restringir acesso a pastas sensíveis
<Directory "api">
    Require all denied
</Directory>
```

## Backup e Restauração

### Backup do Banco de Dados
```bash
# MySQL Dump
mysqldump -u root -p espaco_guanais > backup_guanais_$(date +%Y%m%d).sql

# phpMyAdmin
# 1. Selecione o banco
# 2. Clique em Exportar
# 3. Escolha formato SQL
```

### Backup de Arquivos
```bash
# Linux/Mac
tar -czf backup_sistema_$(date +%Y%m%d).tar.gz Sistema_Guanais/

# Windows
# Use WinRAR ou 7-Zip para compactar a pasta
```

## Atualizações

### Atualizar para Nova Versão
1. Faça backup do sistema atual
2. Substitua os arquivos (exceto config.php)
3. Execute script de atualização: `sql/atualizar_banco.php`
4. Teste todas as funcionalidades

### Verificar Versão
- Acesse `test_sistema.php` para validar funcionalidades
- Verifique logs de erro em caso de problemas

## Suporte Técnico

### Logs de Erro
- **Apache**: `/var/log/apache2/error.log` (Linux) ou `xampp/apache/logs/error.log` (Windows)
- **PHP**: Configure em `php.ini` com `log_errors = On`

### Testes de Sistema
- Acesse: `http://localhost/Sistema_Guanais/test_sistema.php`
- Verifique status de todas as funcionalidades

### Contato
Para suporte técnico, consulte:
- Documentação no repositório
- Logs de erro do servidor
- Comunidade de desenvolvedores PHP/MySQL

## Licença

Este projeto é de código aberto e está disponível sob a licença MIT.

## Contribuição

Contribuições são bem-vindas! Para contribuir:

1. Faça um fork do projeto
2. Crie uma branch para sua feature: `git checkout -b minha-feature`
3. Commit suas mudanças: `git commit -m 'Adiciona nova feature'`
4. Push para a branch: `git push origin minha-feature`
5. Abra um Pull Request

---

**Importante**: Este sistema foi desenvolvido para uso interno da clínica Espaço Guanais. Qualquer modificação deve ser testada em ambiente de desenvolvimento antes de ser aplicada em produção.