<?php
/**
 * atualizar_banco.php
 * Script de atualização para bancos de dados existentes
 * Espaço Guanais - Sistema de Gestão Clínica
 */

require_once '../api/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Atualização do Banco de Dados - Espaço Guanais</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .step { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 6px; }
        .step-title { font-weight: bold; color: #333; margin-bottom: 15px; font-size: 18px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Atualização do Banco de Dados</h1>
        <p>Este script atualiza bancos de dados existentes para a nova versão do sistema.</p>
        
        <div class='step'>
            <div class='step-title'>1. Verificação Inicial</div>
            <div id='verificacao'></div>
        </div>
        
        <div class='step'>
            <div class='step-title'>2. Atualizações Estruturais</div>
            <div id='atualizacoes'></div>
        </div>
        
        <div class='step'>
            <div class='step-title'>3. Dados de Sistema</div>
            <div id='dados-sistema'></div>
        </div>
        
        <div class='step'>
            <div class='step-title'>4. Validação Final</div>
            <div id='validacao'></div>
        </div>
        
        <div class='step'>
            <div class='step-title'>5. Ações</div>
            <button class='btn btn-success' onclick='executarAtualizacao()'>Executar Atualização</button>
            <button class='btn btn-danger' onclick='confirmarRecriacao()'>Recriar Banco (CUIDADO)</button>
        </div>
    </div>

    <script>
        let passos = [];
        
        function log(msg, tipo = 'info') {
            const div = document.createElement('div');
            div.className = tipo;
            div.innerHTML = msg;
            document.getElementById('atualizacoes').appendChild(div);
            passos.push({ msg, tipo });
        }
        
        async function executarAtualizacao() {
            if (!confirm('Este processo irá atualizar seu banco de dados. Recomenda-se fazer backup antes.\\n\\nDeseja continuar?')) {
                return;
            }
            
            document.getElementById('atualizacoes').innerHTML = '';
            log('Iniciando atualização do banco de dados...', 'info');
            
            try {
                const response = await fetch('../api/update_db.php', { method: 'POST' });
                const result = await response.json();

                if (result.success) {
                    result.data.updates.forEach(msg => log('✓ ' + msg, 'success'));
                    log('Atualização concluída com sucesso!', 'success');
                    document.getElementById('validacao').innerHTML = '<div class="success">✓ Banco de dados atualizado. Recomenda-se reiniciar o sistema.</div>';
                } else {
                    throw new Error(result.error || 'Erro desconhecido na API de atualização.');
                }
            } catch (error) {
                log('Erro durante atualização: ' + error.message, 'error');
                document.getElementById('validacao').innerHTML = '<div class="error">✗ Falha na atualização. Verifique os logs.</div>';
            }
        }
        
        function confirmarRecriacao() {
            if (confirm('ATENÇÃO: Esta ação irá APAGAR TODO O BANCO DE DADOS EXISTENTE e criar um novo banco vazio.\\n\\nEsta ação é IRREVERSÍVEL.\\n\\nTem certeza que deseja continuar?')) {
                alert('Para recriar o banco, execute o script sql/database.sql manualmente no seu MySQL/MariaDB.');
            }
        }
        
        // Verificação inicial
        window.onload = async function() {
            try {
                const response = await fetch('../api/auth.php', { method: 'GET' });
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('verificacao').innerHTML = '<div class=\"success\">✓ Conexão com banco de dados: OK</div>';
                } else {
                    document.getElementById('verificacao').innerHTML = '<div class=\"error\">✗ Conexão com banco de dados: Falhou</div>';
                }
            } catch (error) {
                document.getElementById('verificacao').innerHTML = '<div class=\"error\">✗ Erro de conexão: ' + error.message + '</div>';
            }
        };
    </script>
</body>
</html>";