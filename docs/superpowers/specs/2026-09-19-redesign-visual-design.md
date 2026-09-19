# Redesign visual do Sistema Guanais

Status: aprovado no brainstorming (paleta, layout, tipografia e abordagem técnica validados via mockups). Pendente: revisão final deste documento pelo usuário antes do plano de implementação.

## 1. Motivação

O usuário considera o visual atual (Bootstrap 5 "de fábrica", sidebar verde escura fixa, mistura Inter/Cormorant Garamond, tela de seleção de módulos redundante) ultrapassado. Objetivo: modernizar a aparência de **todo o sistema** numa entrega única e coerente, sem alterar comportamento/lógica de negócio já existente.

Fora de escopo: qualquer mudança de regra de negócio, de API, ou de schema de banco. Isso é puramente visual/estrutural de navegação.

## 2. Direção de design (validada via mockups)

- **Paleta: "Slate & Índigo"** — neutros cinza-azulados + índigo como cor de destaque única. Referência: dashboards SaaS modernos (Linear, Stripe).
- **Tipografia: Inter 100% sans-serif** em todo o sistema (títulos, corpo, formulários). Remove a mistura com Cormorant Garamond.
- **Navegação: sidebar compacta de ícones**, fixa à esquerda, substituindo a sidebar verde larga com texto atual.
- **Fluxo: login vai direto para o Dashboard.** A tela intermediária de "seleção de módulos" (cards Agenda/Pacientes/Financeiro/...) é **removida** — ela duplicava a navegação que a sidebar já oferece.
- **Abordagem técnica: Bootstrap 5 permanece como camada de comportamento** (modais, abas, dropdowns, collapse) — só a aparência é substituída via novo sistema de variáveis CSS. Nenhum componente JS do Bootstrap é reescrito.

## 3. Design tokens

Novo arquivo `css/tokens.css`, carregado antes de `css/style.css`, definindo custom properties que `style.css` (e overrides do Bootstrap) passam a consumir:

```css
:root {
  /* Neutros */
  --color-bg: #F8FAFC;
  --color-surface: #FFFFFF;
  --color-border: #E2E8F0;
  --color-text-primary: #0F172A;
  --color-text-secondary: #64748B;
  --color-text-muted: #94A3B8;

  /* Marca / destaque */
  --color-accent: #4F46E5;
  --color-accent-hover: #4338CA;
  --color-accent-soft: #EEF2FF;   /* fundos de badge/estado ativo */

  /* Semânticos (mantêm significado, tom alinhado à paleta) */
  --color-success: #16A34A;
  --color-danger: #DC2626;
  --color-warning: #D97706;

  /* Sidebar (fundo escuro fixo, não muda com o tema) */
  --sidebar-bg: #0F172A;
  --sidebar-icon-idle: #94A3B8;
  --sidebar-icon-active-bg: #1E293B;

  /* Forma */
  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --shadow-card: 0 1px 2px rgba(15, 23, 42, .04);
  --shadow-elevated: 0 8px 30px rgba(15, 23, 42, .08);

  /* Tipografia */
  --font-base: 'Inter', system-ui, -apple-system, sans-serif;
}

body.dark {
  --color-bg: #0B1220;
  --color-surface: #131B2C;
  --color-border: #24304A;
  --color-text-primary: #F1F5F9;
  --color-text-secondary: #94A3B8;
  --color-text-muted: #64748B;
  --color-accent: #6366F1;
  --color-accent-hover: #818CF8;
  --color-accent-soft: #1E1B4B;
}
```

O sistema já tem um toggle claro/escuro funcional (`toggleTheme()` em `js/script.js:2782`, que alterna a classe `dark` em `document.body` e persiste em `localStorage.theme`) — o dark mode é preservado como está, só migra pra usar esses tokens em vez das cores hardcoded atuais em `style.css` (que hoje já usa o seletor `body.dark`).

## 4. Estrutura de navegação (app shell)

Novo shell único, usado por todas as telas pós-login:

- **Sidebar fixa de 64px**, fundo `--sidebar-bg`, ícones (Phosphor Icons, já carregado no projeto) para: Dashboard, Agenda, Pacientes, Financeiro, Despesas, Relatórios, Prontuário, Configurações. Item ativo com fundo `--sidebar-icon-active-bg` e ícone na cor de destaque. Tooltip com o nome do módulo ao passar o mouse (`title` attribute é suficiente, sem lib nova).
- **Topbar**: nome do sistema à esquerda (some depois do login? Não — mantém contexto), busca de paciente central, avatar do usuário + toggle de tema + sair à direita — reaproveita o que já existe em `index.html`, só restilizado.
- **Dashboard vira a rota padrão pós-login** (já existe como item de sidebar hoje; passa a ser carregado automaticamente por `inicializarSistema()` em vez de mostrar a tela de seleção de módulos).

## 5. Inventário de telas afetadas (todas recebem os mesmos tokens/padrões — não há tela "fora" do redesign)

| Tela | Arquivo(s) | Observação |
|---|---|---|
| Login | `index.html` (`#loginScreen`), `views/login.html` | Card centralizado, sombra suave, sem a tela de seleção de módulos depois |
| ~~Seleção de módulos~~ | `index.html` (`#moduleSelectionScreen`) | **Removida** — login vai direto pro dashboard |
| App shell (sidebar+topbar) | `index.html`, `js/script.js` | Novo shell compacto descrito acima |
| Dashboard | `index.html` (`#dashboard`) | Stat cards + próximos atendimentos, como no mockup aprovado |
| Agenda / Atendimentos | `index.html` (`#agenda`) | Tabela/lista restilizada com tokens |
| Pacientes (lista + ficha) | `index.html` (`#pacientes`) | Cards e formulários restilizados |
| Financeiro | `index.html` (`#financeiro`) | Idem |
| Despesas | `index.html` (`#despesas`) | Idem |
| Novo Atendimento | `index.html` | Formulário restilizado |
| Relatórios | `index.html` (`#relatorios`) | Idem |
| Prontuário | `index.html` (`#prontuario`) | Idem |
| Configurações (Usuários / Design Visual / Dados e Backup) | `index.html` (`#configuracoes`) | Idem |
| Modais (novo/editar paciente, confirmações, etc.) | `index.html`, `js/script.js` | Continuam sendo `.modal` do Bootstrap, só reestilizados |
| Toasts | `js/script.js` (`mostrarToast`) | Reestilizado para a paleta nova |

Componentes-base a padronizar uma vez e reutilizar em todas as telas acima: botão primário/secundário/perigo, input/select/textarea, card, badge de status, tabela, tab, modal, toast, avatar.

## 6. O que NÃO muda

- Nenhuma URL de API, payload, ou lógica em `js/script.js` além do necessário para remover a tela de seleção de módulos e ajustar seletores de classe/estilo.
- Nenhuma mudança em `api/*.php` ou banco de dados.
- Bootstrap 5 e Phosphor Icons continuam como dependências.
- O botão/fluxo de logout, permissões por tipo de usuário e toda regra de negócio existente permanecem idênticos.

## 7. Testes / verificação

Como é um redesign visual sobre um sistema já funcional, a verificação é manual/visual, tela por tela, no navegador (XAMPP local já configurado):
1. Login (claro e escuro)
2. Dashboard logo após login (sem passar pela tela removida)
3. Cada módulo da sidebar abre e mantém a funcionalidade (dados carregam, CRUD funciona)
4. Modais abrem/fecham normalmente (Bootstrap JS intacto)
5. Toggle de tema claro/escuro em pelo menos 3 telas diferentes
6. Responsividade: abaixo de 768px a sidebar de ícones vira uma barra de ícones fixa na parte inferior da tela (padrão de app mobile), em vez de lateral — evita menu hambúrguer extra e mantém acesso a um toque a qualquer módulo

Não há testes automatizados no projeto hoje (é PHP+JS vanilla sem suíte de testes) — não será criada suíte nova só para isto, fora de escopo.
