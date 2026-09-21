# Log de auditoria completo

Status: aprovado no brainstorming (escopo, nível de detalhe, local da tela e permissão de acesso validados com o usuário via perguntas diretas).

## 1. Motivação

O sistema guarda dados sensíveis de pacientes (prontuário, contatos de emergência) e movimentação financeira da clínica. Hoje só existe auditoria para importação/backup (`auditoria_backup.php`). Não há registro de quem criou, editou ou excluiu um paciente, atendimento, lançamento financeiro, despesa, usuário, pacote ou configuração — o que é relevante tanto para LGPD (dados de saúde) quanto para credibilidade na hora de vender o sistema para novas clínicas.

Fora de escopo: guardar o valor antes/depois de cada edição (diff completo). Guarda-se apenas quem fez o quê, quando, e em qual registro — decisão explícita do usuário para manter simples e leve.

## 2. Escopo (validado)

- **Módulos auditados**: Pacientes (incluindo upload/exclusão de arquivos), Atendimentos (Agenda/Prontuário), Financeiro, Despesas, Usuários, Configurações (identidade visual), Pacotes.
- **Ações registradas**: criar, editar, excluir (mesmas 3 ações em todos os módulos — os módulos usam soft-delete ou delete físico, mas do ponto de vista do log ambos contam como "excluir").
- **Dado guardado por evento**: usuário (id + nome), módulo, ação, id do registro afetado, uma descrição curta legível (ex.: nome do paciente, descrição da despesa) e data/hora.
- **Visualização**: nova sub-aba "Auditoria" em Configurações, ao lado de Usuários / Design Visual / Dados e Backup.
- **Acesso**: somente usuários com `tipo === 'admin'` veem a sub-aba e conseguem consultar o endpoint.

## 3. Banco de dados

Nova tabela `log_auditoria`, criada sob demanda (padrão `ensureXTable($db)` já usado em `auditoria_backup.php`) para não exigir migração manual:

```sql
CREATE TABLE IF NOT EXISTS log_auditoria (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    usuario_nome VARCHAR(120) NOT NULL,
    modulo VARCHAR(30) NOT NULL,
    acao ENUM('criar','editar','excluir') NOT NULL,
    registro_id VARCHAR(50) NULL,
    registro_descricao VARCHAR(255) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_data (criado_em),
    INDEX idx_log_modulo (modulo),
    INDEX idx_log_usuario (usuario_id),
    INDEX idx_log_acao (acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

`modulo` é uma string livre (não ENUM) para não exigir alteração de schema se um módulo novo for auditado no futuro; os valores usados inicialmente são: `pacientes`, `atendimentos`, `financeiro`, `despesas`, `usuarios`, `configuracoes`, `pacotes`.

## 4. Backend

### 4.1 Helper compartilhado (`api/config.php`)

```php
function registrarLogAuditoria($db, $modulo, $acao, $registroId = null, $descricao = null) {
    $db->exec("CREATE TABLE IF NOT EXISTS log_auditoria (...)"); // mesmo DDL acima
    $usuarioId = $_SESSION['user_id'] ?? null;
    if (!$usuarioId) return; // nunca bloqueia a operação principal por falta de sessão
    $usuarioNome = $_SESSION['nome'] ?? ($_SESSION['usuario'] ?? 'Usuário');
    try {
        $stmt = $db->prepare("INSERT INTO log_auditoria (usuario_id, usuario_nome, modulo, acao, registro_id, registro_descricao) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$usuarioId, $usuarioNome, $modulo, $acao, $registroId, $descricao]);
    } catch (Exception $e) {
        // auditoria nunca derruba a operação principal
    }
}
```

Chamada **depois** de cada operação de escrita ter sido confirmada com sucesso (nunca antes, para não logar ações que falharam), envolvida em try/catch para nunca quebrar a resposta da API principal caso a gravação do log falhe por qualquer motivo.

### 4.2 Pontos de chamada

Adicionar `registrarLogAuditoria(...)` logo após cada `successResponse(...)` de escrita (POST/PUT/DELETE) em:

| Arquivo | Módulo | Descrição usada no log |
|---|---|---|
| `pacientes.php` | `pacientes` | nome do paciente |
| `arquivos.php` | `pacientes` | nome do arquivo + nome do paciente |
| `atendimentos.php` | `atendimentos` | nome do paciente + data |
| `financeiro.php` | `financeiro` | nome do paciente + valor |
| `despesas.php` | `despesas` | descrição da despesa |
| `usuarios.php` | `usuarios` | nome do usuário afetado |
| `configuracoes.php` | `configuracoes` | chave configurada |
| `pacotes.php` | `pacotes` | nome do paciente + tipo de pacote |

### 4.3 Novo endpoint `api/log_auditoria.php`

Só `GET`, admin-only (`$_SESSION['tipo'] !== 'admin'` → 403). Filtros via query string: `modulo`, `acao`, `usuario_id`, `data_inicio`, `data_fim`, `busca` (texto livre sobre `registro_descricao`/`usuario_nome`). Paginado (`LIMIT`/`OFFSET`, página de 50), ordenado por `criado_em DESC`.

## 5. Frontend

- Nova sub-aba "Auditoria" em `#configSubTab` (após "Dados e Backup"), com o mesmo padrão visual das outras (`.nav-pills-custom`).
- Sub-aba oculta via JS (mesmo padrão de `aplicarPermissoesUI()`) quando `usuarioLogado.tipo !== 'admin'`.
- Conteúdo: filtros (módulo, ação, usuário, período) + tabela (Data/Hora, Usuário com avatar-chip, Módulo, Ação como badge-custom colorido — criar=success, editar=info, excluir=danger —, Registro/Descrição).
- Carregamento sob demanda (só busca ao abrir a sub-aba pela primeira vez), com paginação simples ("Carregar mais").

## 6. Testes

Adicionar ao `testador_completo.php`: após cada operação de escrita já testada (paciente, atendimento, financeiro, despesa, usuário, configuração, pacote), consultar `log_auditoria.php` e confirmar que existe um evento correspondente. Adicionar teste de acesso negado (403) para usuário não-admin.
