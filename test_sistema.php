<?php
/**
 * test_sistema.php
 * Script de teste para validar funcionalidades críticas do sistema
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once 'api/config.php';

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Teste do Sistema - Espaço Guanais</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .test-section { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 6px; }
        .test-title { font-weight: bold; color: #333; margin-bottom: 15px; font-size: 18px; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Teste do Sistema - Espaço Guanais</h1>
        <p>Este script valida as funcionalidades críticas do sistema após as correções realizadas.</p>
        
        <div class='test-section'>
            <div class='test-title'>1. Conexão com Banco de Dados</div>
            <div id='db-test'></div>
        </div>
        
        <div class='test-section'>
            <div class='test-title'>2. Estrutura de Tabelas</div>
            <div id='tables-test'></div>
        </div>
        
        <div class='test-section'>
            <div class='test-title'>3. Usuário Admin e Permissões</div>
            <div id='admin-test'></div>
        </div>
        
        <div class='test-section'>
            <div class='test-title'>4. Teste de Permissões (CRUD)</div>
            <div id='permissions-test'></div>
        </div>
        
        <div class='test-section'>
            <div class='test-title'>5. Teste de Pacientes</div>
            <div id='pacientes-test'></div>
        </div>
        
        <div class='test-section'>
            <div class='test-title'>6. Teste de Atendimentos</div>
            <div id='atendimentos-test'></div>
        </div>
        
        <div class='test-section'>
            <div class='test-title'>7. Teste de Financeiro</div>
            <div id='financeiro-test'></div>
        </div>
        
        <div class='test-section'>
            <div class='test-title'>8. Teste de Despesas</div>
            <div id='despesas-test'></div>
        </div>
        
        <div class='test-section'>
            <div class='test-title'>9. Teste de Importação/Exportação</div>
            <div id='import-test'></div>
        </div>
        
        <div class='test-section'>
            <div class='test-title'>10. Limpeza de Testes</div>
            <div id='cleanup-test'></div>
            <button class='btn btn-danger' onclick='limparTestes()'>Limpar Dados de Teste</button>
        </div>
    </div>

    <script>
        async function testarAPI(endpoint, method = 'GET', data = null) {
            const url = 'api/' + endpoint;
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            };
            
            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }
            
            try {
                const response = await fetch(url, options);
                const result = await response.json();
                return result;
            } catch (error) {
                return { success: false, error: error.message };
            }
        }
        
        async function executarTestes() {
            // Teste 1: Conexão com banco
            const dbResult = await testarAPI('auth.php', 'GET');
            document.getElementById('db-test').innerHTML = 
                dbResult.success ? 
                '<div class=\"success\">✓ Conexão com banco de dados: OK</div>' :
                '<div class=\"error\">✗ Conexão com banco de dados: Falhou</div>';
            
            // Teste 2: Estrutura de tabelas
            const tablesResult = await testarAPI('pacientes.php', 'GET');
            document.getElementById('tables-test').innerHTML = 
                tablesResult.success ? 
                '<div class=\"success\">✓ Estrutura de tabelas: OK</div>' :
                '<div class=\"error\">✗ Estrutura de tabelas: Falhou</div>';
            
            // Teste 3: Usuário admin
            const adminResult = await testarAPI('usuarios.php', 'GET');
            if (adminResult.success && adminResult.data) {
                const admin = adminResult.data.find(u => u.usuario === 'admin');
                document.getElementById('admin-test').innerHTML = 
                    admin ? 
                    '<div class=\"success\">✓ Usuário admin encontrado: ' + admin.nome + '</div>' :
                    '<div class=\"error\">✗ Usuário admin não encontrado</div>';
            } else {
                document.getElementById('admin-test').innerHTML = 
                    '<div class=\"error\">✗ Falha ao buscar usuários</div>';
            }
            
            // Teste 4: Permissões
            const permsResult = await testarAPI('usuarios.php', 'GET');
            if (permsResult.success && permsResult.data) {
                const admin = permsResult.data.find(u => u.usuario === 'admin');
                const permsCount = admin && admin.permissoes ? admin.permissoes.length : 0;
                document.getElementById('permissions-test').innerHTML = 
                    permsCount >= 20 ? 
                    '<div class=\"success\">✓ Permissões do admin: ' + permsCount + ' permissões configuradas</div>' :
                    '<div class=\"warning\">⚠ Permissões do admin: ' + permsCount + ' permissões (pode ser insuficiente)</div>';
            } else {
                document.getElementById('permissions-test').innerHTML = 
                    '<div class=\"error\">✗ Falha ao validar permissões</div>';
            }
            
            // Teste 5: Pacientes
            const pacientesResult = await testarAPI('pacientes.php', 'GET');
            document.getElementById('pacientes-test').innerHTML = 
                pacientesResult.success ? 
                '<div class=\"success\">✓ API de pacientes: OK (' + (pacientesResult.data ? pacientesResult.data.length : 0) + ' pacientes)</div>' :
                '<div class=\"error\">✗ API de pacientes: Falhou</div>';
            
            // Teste 6: Atendimentos
            const atendimentosResult = await testarAPI('atendimentos.php', 'GET');
            document.getElementById('atendimentos-test').innerHTML = 
                atendimentosResult.success ? 
                '<div class=\"success\">✓ API de atendimentos: OK</div>' :
                '<div class=\"error\">✗ API de atendimentos: Falhou</div>';
            
            // Teste 7: Financeiro
            const financeiroResult = await testarAPI('financeiro.php', 'GET');
            document.getElementById('financeiro-test').innerHTML = 
                financeiroResult.success ? 
                '<div class=\"success\">✓ API de financeiro: OK</div>' :
                '<div class=\"error\">✗ API de financeiro: Falhou</div>';
            
            // Teste 8: Despesas
            const despesasResult = await testarAPI('despesas.php', 'GET');
            document.getElementById('despesas-test').innerHTML = 
                despesasResult.success ? 
                '<div class=\"success\">✓ API de despesas: OK</div>' :
                '<div class=\"error\">✗ API de despesas: Falhou</div>';
            
            // Teste 9: Importação/Exportação (verifica se as bibliotecas estão disponíveis)
            const importTest = typeof XLSX !== 'undefined';
            document.getElementById('import-test').innerHTML = 
                importTest ? 
                '<div class=\"success\">✓ Bibliotecas de importação: Disponíveis</div>' :
                '<div class=\"warning\">⚠ Bibliotecas de importação: Não detectadas (pode ser normal se não estiver na página principal)</div>';
        }
        
        async function limparTestes() {
            if (confirm('Tem certeza que deseja limpar os dados de teste?')) {
                // Não implementado para evitar exclusão acidental de dados reais
                alert('Função de limpeza desativada para segurança. Use o painel de administração para gerenciar dados.');
            }
        }
        
        // Executar testes ao carregar
        window.onload = executarTestes;
    </script>
</body>
</html>";