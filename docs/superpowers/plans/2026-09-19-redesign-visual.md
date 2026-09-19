# Redesign Visual do Sistema Guanais — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Modernizar 100% do visual do Sistema Guanais (paleta Slate & Índigo, tipografia Inter, sidebar de ícones, sem tela de seleção de módulos) sem alterar nenhuma lógica de negócio, API ou schema de banco.

**Architecture:** O visual de quase todas as telas do sistema já deriva de um pequeno conjunto de CSS custom properties (`--verde`, `--verde-escuro`, `--ouro`, `--cinza-txt`, `--crema`, `--sombra-*`, `--radius-*`, `--font-body`/`--font-display`) definidas em `:root` e `body.dark` em `css/style.css`, consumidas via `var(--x)` tanto no CSS quanto em dezenas de `style="color: var(--verde-escuro)"` inline espalhados em `index.html`. Remapear essas variáveis para os novos valores re-estiliza automaticamente ~80% do sistema sem tocar em HTML. O trabalho estrutural (sidebar de ícones, remoção da tela de seleção de módulos) é separado e localizado.

**Tech Stack:** HTML/CSS/JS vanilla, Bootstrap 5 (mantido só como camada de comportamento — modais/abas/dropdowns), Phosphor Icons (já carregado).

**Spec:** `docs/superpowers/specs/2026-09-19-redesign-visual-design.md`

## Global Constraints

- Nenhuma mudança em `api/*.php`, banco de dados, ou payloads de requisição.
- Bootstrap 5 e Phosphor Icons continuam como dependências — nenhum componente JS do Bootstrap (`data-bs-toggle`, `data-bs-target`) é removido ou reescrito.
- Paleta: Slate & Índigo (`#4F46E5` accent). Tipografia: Inter 100% sans-serif. Dark mode: mecanismo existente (`body.dark` via `toggleTheme()` em `js/script.js:2782`), preservado.
- Todo teste é manual/visual no XAMPP local já configurado (`http://localhost/Sistema_Guanais/`, login `admin`/`0301`). Não há suíte automatizada no projeto — não criar uma nova só para isto.

---

### Task 1: Remapear tokens de cor e tipografia

**Files:**
- Modify: `css/style.css:11-47` (bloco `:root`)
- Modify: `css/style.css:50-73` (bloco `body.dark`)
- Modify: `index.html` (`<head>`, link de fontes Google)

**Interfaces:**
- Produces: novos valores para as CSS custom properties `--verde`, `--verde-escuro`, `--verde-pale`, `--ouro`, `--ouro-light`, `--ouro-pale`, `--crema`, `--cinza-txt`, `--cinza-muted`, `--sombra-sm/md/lg`, `--radius-sm/md/lg`, `--font-body`, `--font-display`, mais a nova `--color-warning`. Todas as tasks seguintes e todo CSS/HTML existente que usa `var(--x)` passam a herdar os novos valores automaticamente — nenhuma outra task precisa remapear cor manualmente.

- [x] **Step 1: Editar o bloco `:root` em `css/style.css`**

Substituir (linhas 11-47):

```css
:root {
    /* Colors - Legacy Palette */
    --verde:       #89b2a2;
    --verde-escuro:#6a9585;
    --verde-pale:  #e8f0ed;
    --ouro:        #c9852a;
    --ouro-light:  #f9c593;
    --ouro-pale:   #fdf6ec;
    --crema:       #fbf9f6;
    --cinza-txt:   #2e3b35;
    --cinza-muted: #7a8c86;
    --branco:      #ffffff;

    /* Shadows */
    --sombra-sm: 0 2px 8px rgba(46,59,53,.06);
    --sombra-md: 0 8px 24px rgba(46,59,53,.08);
    --sombra-lg: 0 20px 48px rgba(137,178,162,.18);

    /* Radii */
    --radius-sm: 10px;
    --radius-md: 16px;
    --radius-lg: 24px;

    /* Fonts */
    --font-body: 'DM Sans', sans-serif;
    --font-display: 'DM Sans', sans-serif;

    /* Transitions */
    --transition: all .25s cubic-bezier(.4,0,.2,1);

    /* Mapped Variables */
    --bg-body: var(--crema);
    --bg-surface: var(--branco);
    --text-primary: var(--cinza-txt);
    --text-secondary: var(--cinza-muted);
    --border-light: #dce5e1;
}
```

por:

```css
:root {
    /* Colors - Slate & Índigo */
    --verde:       #4F46E5;
    --verde-escuro:#4338CA;
    --verde-pale:  #EEF2FF;
    --ouro:        #6366F1;
    --ouro-light:  #818CF8;
    --ouro-pale:   #EEF2FF;
    --crema:       #F8FAFC;
    --cinza-txt:   #0F172A;
    --cinza-muted: #64748B;
    --branco:      #ffffff;
    --color-warning: #D97706;

    /* Shadows */
    --sombra-sm: 0 1px 2px rgba(15,23,42,.04);
    --sombra-md: 0 4px 12px rgba(15,23,42,.08);
    --sombra-lg: 0 8px 30px rgba(15,23,42,.10);

    /* Radii */
    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 16px;

    /* Fonts */
    --font-body: 'Inter', system-ui, -apple-system, sans-serif;
    --font-display: 'Inter', system-ui, -apple-system, sans-serif;

    /* Transitions */
    --transition: all .2s ease;

    /* Mapped Variables */
    --bg-body: var(--crema);
    --bg-surface: var(--branco);
    --text-primary: var(--cinza-txt);
    --text-secondary: var(--cinza-muted);
    --border-light: #E2E8F0;
}
```

- [x] **Step 2: Editar o bloco `body.dark` em `css/style.css`**

Substituir (linhas 50-73):

```css
body.dark {
    --bg-body: #0C1418;
    --bg-surface: #122027;
    --text-primary: #F5F7F8;
    --text-secondary: #C7D2D7;
    --border-light: #31424A;
    --verde: #5EEAD4;
    --verde-escuro: #2DD4BF;
    --verde-pale: rgba(94, 234, 212, 0.12);
    --ouro: #FBBF24;
    --ouro-pale: rgba(251, 191, 36, 0.14);
    --crema: #0C1418;
    --cinza-txt: #F5F7F8;
    --cinza-muted: #A7B3B7;
    --branco: #1A2B33;
    --borda: #31424A;
    --sombra-sm: 0 2px 10px rgba(0, 0, 0, 0.35);
    --sombra-md: 0 12px 30px rgba(0, 0, 0, 0.42);
    --sombra-lg: 0 24px 50px rgba(0, 0, 0, 0.55);

    background:
      radial-gradient(circle at 8% 10%, rgba(46, 211, 191, 0.16), transparent 35%),
      radial-gradient(circle at 92% 0%, rgba(125, 211, 252, 0.13), transparent 30%),
      linear-gradient(160deg, #081015 0%, #0C1418 48%, #0A1116 100%);
}
```

por:

```css
body.dark {
    --bg-body: #0B1220;
    --bg-surface: #131B2C;
    --text-primary: #F1F5F9;
    --text-secondary: #C7D2D7;
    --border-light: #24304A;
    --verde: #6366F1;
    --verde-escuro: #818CF8;
    --verde-pale: rgba(99, 102, 241, 0.16);
    --ouro: #A5B4FC;
    --ouro-pale: rgba(165, 180, 252, 0.14);
    --crema: #0B1220;
    --cinza-txt: #F1F5F9;
    --cinza-muted: #A7B3B7;
    --branco: #1A2338;
    --borda: #24304A;
    --sombra-sm: 0 2px 10px rgba(0, 0, 0, 0.35);
    --sombra-md: 0 12px 30px rgba(0, 0, 0, 0.42);
    --sombra-lg: 0 24px 50px rgba(0, 0, 0, 0.55);
    --color-warning: #FBBF24;

    background:
      radial-gradient(circle at 8% 10%, rgba(99, 102, 241, 0.16), transparent 35%),
      radial-gradient(circle at 92% 0%, rgba(129, 140, 248, 0.13), transparent 30%),
      linear-gradient(160deg, #070B14 0%, #0B1220 48%, #090D18 100%);
}
```

- [x] **Step 3: Trocar a fonte carregada em `index.html`**

Encontrar em `index.html` (dentro de `<head>`):

```html
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
```

Substituir por:

```html
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
```

(Inter já estava sendo carregado — só remove o peso morto do Cormorant Garamond, que não é mais usado em lugar nenhum depois do Step 1.)

- [x] **Step 4: Verificar visualmente**

Com o XAMPP já rodando (Apache/MySQL ativos, projeto linkado em `C:\xampp\htdocs\Sistema_Guanais`), recarregar `http://localhost/Sistema_Guanais/` no navegador e confirmar: botões, cards, tabela e o resumo financeiro já aparecem em tons de índigo/cinza-azulado em vez de verde/dourado. Testar toggle de tema escuro (ícone sol/lua no header) — cores dark também devem ter mudado para o mesmo tom.

- [x] **Step 5: Commit**

```bash
git add css/style.css index.html
git commit -m "style: remap design tokens para paleta Slate & Indigo + Inter"
```

---

### Task 2: Sidebar de ícones (consolidar CSS duplicado + redesenhar navegação)

**Files:**
- Modify: `css/style.css` (remove bloco duplicado ~linhas 480-666; substitui bloco ~linhas 1285-1423)
- Modify: `index.html:195-246` (lista `#mainTab`)

**Interfaces:**
- Consumes: tokens de `Task 1` (`--verde`, `--cinza-txt`, `--radius-*`, `--sombra-*`).
- Produces: sidebar fixa de 72px com ícones + tooltip nativo (`title`), mesma estrutura de `data-bs-target` que todas as outras telas continuam usando sem alteração.

- [x] **Step 1: Remover o primeiro bloco de CSS duplicado (dead code)**

Em `css/style.css`, localizar e **apagar inteiramente** o comentário e bloco que começa em `/* ===== Layout dashboard com sidebar à esquerda (sem quebrar JS) ===== */` (contém as regras `#appScreen`, `#appScreen .container-fluid`, `#mainTab`, `#mainTab::before`, `#mainTab .nav-item`, `#mainTab .nav-link` e variantes, até a regra `#mainTab .nav-link.active i { color: var(--verde); opacity: 1; }` inclusive) — esse bloco é sobrescrito pelo bloco posterior "TEMA VISUAL COPIA.HTML" (ambos usam `!important` nas mesmas propriedades; o que vier depois no arquivo vence, então o primeiro é código morto). Não apagar `.psicologa-card`, `.psicologa-card-foto`, `.paciente-highlight-*` nem `#mainTab .nav-link.active::after { display: none; }` / `#mainTabContent` / `#appScreen .app-header ...` / `.user-pill` que vêm logo depois — esses continuam em uso.

- [x] **Step 2: Substituir o segundo bloco (`TEMA VISUAL COPIA.HTML`) pela sidebar de ícones**

Localizar o bloco que começa em `/* ====================== TEMA VISUAL COPIA.HTML (SISTEMA REAL) ====================== */` e vai até o fechamento do `@media (max-width: 992px) { ... }` logo abaixo dele (inclui `#appScreen`, `#appScreen .container-fluid`, `#mainTab`, `#mainTab::before`, `#mainTab .nav-item`, `#mainTab .nav-link`, `#mainTabContent .tab-pane`, `.app-header`, `.dash-card`/`.caixa-guanais`/`.table-wrap`/`.card`/`.card-modern`, `.dash-card`, `.dash-icon`, `.btn-verde`/`.btn-primary`, `.table-custom th`/`td`, e o `@media`). Substituir esse bloco inteiro por:

```css
/* ====================== APP SHELL — SIDEBAR DE ÍCONES ====================== */
#appScreen {
    background: var(--crema);
    min-height: 100vh;
}

#appScreen .container-fluid {
    display: grid;
    grid-template-columns: 76px minmax(0, 1fr);
    gap: 24px;
}

#mainTab {
    display: flex !important;
    flex-direction: column;
    align-items: center;
    gap: 8px !important;
    align-self: start;
    position: sticky;
    top: 24px;
    max-height: calc(100vh - 48px);
    overflow-y: auto;
    background: #0F172A !important;
    border: none !important;
    border-radius: var(--radius-lg) !important;
    padding: 16px 10px !important;
    box-shadow: var(--sombra-md);
    scrollbar-width: none;
}

#mainTab::-webkit-scrollbar { display: none; }

#mainTab .nav-item { width: 100%; display: flex; justify-content: center; }

#mainTab .nav-link {
    width: 48px;
    height: 48px;
    display: flex !important;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.55) !important;
    border-radius: var(--radius-sm) !important;
    background: transparent !important;
    border: none !important;
    transition: var(--transition) !important;
    font-size: 1.25rem;
}

#mainTab .nav-link .nav-label { display: none; }

#mainTab .nav-link:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    color: #ffffff !important;
}

#mainTab .nav-link.active {
    background: var(--verde) !important;
    color: #ffffff !important;
}

#mainTabContent {
    min-width: 0;
}

#mainTabContent .tab-pane {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 20px;
    box-shadow: var(--sombra-sm);
}

.app-header {
    background: var(--bg-surface) !important;
    border-bottom: 1px solid var(--border-light) !important;
    box-shadow: none !important;
}

.dash-card,
.caixa-guanais,
.table-wrap,
.card,
.card-modern {
    background: var(--bg-surface) !important;
    border: 1px solid var(--border-light) !important;
    border-radius: var(--radius-md) !important;
    box-shadow: var(--sombra-sm) !important;
}

.dash-card {
    border-left: 3px solid var(--verde) !important;
    padding: 20px 15px;
}

.dash-icon {
    background: var(--verde-pale) !important;
    color: var(--verde) !important;
}

.btn-verde,
.btn-primary {
    background: var(--verde) !important;
    color: #fff !important;
    border: none !important;
}

.btn-verde:hover,
.btn-primary:hover {
    background: var(--verde-escuro) !important;
    transform: none;
}

.table-custom th,
.table thead th {
    background: #F1F5F9 !important;
    color: var(--cinza-muted) !important;
}

.table-custom td,
.table tbody td {
    border-bottom-color: var(--border-light) !important;
}

@media (max-width: 992px) {
    #appScreen .container-fluid {
        grid-template-columns: 1fr;
        grid-template-rows: minmax(0, 1fr) 64px;
    }

    #mainTab {
        order: 2;
        flex-direction: row;
        justify-content: space-around;
        align-items: center;
        position: sticky;
        top: auto;
        bottom: 0;
        max-height: none;
        width: 100%;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0 !important;
        padding: 8px !important;
    }

    #mainTabContent { order: 1; }
}
```

- [x] **Step 3: Adicionar tooltip (`title`) e `<span class="nav-label">` em cada item da sidebar**

Em `index.html:195-246`, dentro de `<ul class="nav nav-tabs ..." id="mainTab" ...>`, cada `<button class="nav-link ...">` recebe um atributo `title` com o nome do módulo e o texto do label passa a ficar dentro de `<span class="nav-label">`. Substituir o bloco inteiro (as 10 `<li class="nav-item">`) por:

```html
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab" title="Dashboard">
                    <i class="ph ph-house"></i><span class="nav-label"> Dashboard</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#agenda" type="button" role="tab" title="Agenda">
                    <i class="ph ph-calendar"></i><span class="nav-label"> Agenda</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pacientes" type="button" role="tab" title="Pacientes">
                    <i class="ph ph-users"></i><span class="nav-label"> Pacientes</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#financeiro" type="button" role="tab" title="Financeiro">
                    <i class="ph ph-currency-dollar"></i><span class="nav-label"> Financeiro</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#despesas" type="button" role="tab" title="Despesas">
                    <i class="ph ph-receipt"></i><span class="nav-label"> Despesas</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#novoAtendimento" type="button" role="tab" title="Novo Atendimento">
                    <i class="ph ph-plus-circle"></i><span class="nav-label"> Novo Atend.</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#relatorios" type="button" role="tab" title="Relatórios">
                    <i class="ph ph-chart-line-up"></i><span class="nav-label"> Relatórios</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#prontuario" type="button" role="tab" title="Prontuário">
                    <i class="ph ph-clipboard-text"></i><span class="nav-label"> Prontuário</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#configuracoes" type="button" role="tab" title="Configurações">
                    <i class="ph ph-gear"></i><span class="nav-label"> Configurações</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#ajuda" type="button" role="tab" title="Ajuda">
                    <i class="ph ph-question"></i><span class="nav-label"> Ajuda</span>
                </button>
            </li>
```

- [x] **Step 4: Verificar visualmente**

Recarregar o sistema logado. Confirmar: sidebar agora é uma barra escura estreita só com ícones à esquerda, item ativo destacado em índigo, hover funciona, clicar em cada ícone troca de aba normalmente (abas do Bootstrap continuam funcionando). Redimensionar a janela do navegador abaixo de 992px e confirmar que a sidebar vira uma barra de ícones na parte inferior da tela.

- [x] **Step 5: Commit**

```bash
git add css/style.css index.html
git commit -m "style: substituir sidebar larga por barra de icones compacta"
```

---

### Task 3: Simplificar tela de login

**Files:**
- Modify: `css/style.css` (`.login-container`, `.login-card`, `.petal*`)
- Modify: `index.html` (`#loginScreen`)
- Modify: `views/login.html`

**Interfaces:**
- Consumes: tokens de `Task 1`.

- [x] **Step 1: Simplificar CSS do login em `css/style.css`**

Substituir o bloco que vai de `/* ====================== LOGIN SCREEN ====================== */` até o fim do `@keyframes floatPetal { ... }` (inclui `.login-container`, `.login-card`, `@keyframes cardSlideUp`, `.petal`, `.petal-1`..`.petal-4`, `@keyframes floatPetal`) por:

```css
/* ====================== LOGIN SCREEN ====================== */
.login-container {
    min-height: 100vh;
    background: var(--crema) !important;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-card {
    max-width: 400px;
    width: 100%;
    border-radius: var(--radius-lg) !important;
    box-shadow: var(--sombra-lg) !important;
    padding: 2.5rem !important;
    background: var(--bg-surface) !important;
    border: 1px solid var(--border-light) !important;
    animation: cardSlideUp .4s ease forwards;
}

@keyframes cardSlideUp {
    from { opacity:0; transform: translateY(12px); }
    to { opacity:1; transform: translateY(0); }
}
```

- [x] **Step 2: Remover os `<div class="petal petal-N">` do `index.html`**

Em `index.html`, dentro de `#loginScreen`, remover as 4 linhas:

```html
    <div class="petal petal-1"></div>
    <div class="petal petal-2"></div>
    <div class="petal petal-3"></div>
    <div class="petal petal-4"></div>
```

- [x] **Step 3: Remover os mesmos `<div class="petal petal-N">` do `views/login.html`**

Mesma remoção das 4 linhas em `views/login.html` (esse arquivo é uma cópia standalone da tela de login).

- [x] **Step 4: Verificar visualmente**

Recarregar `http://localhost/Sistema_Guanais/` deslogado. Confirmar: card de login centralizado, branco, sem pétalas animadas, sombra suave, cantos arredondados — visual igual ao mockup aprovado.

- [x] **Step 5: Commit**

```bash
git add css/style.css index.html views/login.html
git commit -m "style: simplificar tela de login (remove decoracao de petalas)"
```

---

### Task 4: Remover tela de seleção de módulos — login vai direto pro Dashboard

**Files:**
- Modify: `index.html` (remove `#moduleSelectionScreen`)
- Modify: `js/script.js:503-520` (`selectModule`, `voltarAosModulos`)
- Modify: `js/script.js` (handler de submit do login, ~linha 2816-2832)
- Modify: `js/script.js` (`verificarLoginSalvo`, ~linha 2834-2847)
- Modify: `css/style.css` (remover CSS órfão de `.module-selection-*`, `.module-bg*`, `.menu-card*`)

**Interfaces:**
- Consumes: `selectModule('dashboard')` já existente (`js/script.js:503`) — não é reescrita, só passa a ser chamada direto do fluxo de login em vez de esperar clique num card.
- Produces: nenhuma função nova exposta; `voltarAosModulos` é removida (não tinha nenhum botão chamando ela no HTML — confirmado por busca no repositório).

- [x] **Step 1: Remover a tela de seleção de módulos do `index.html`**

Remover o bloco inteiro de `<!-- TELA DE SELEÇÃO DE MÓDULOS -->` até o `</div>` que fecha `#moduleSelectionScreen`, logo antes do comentário `<!-- SISTEMA PRINCIPAL -->` (bloco com o `<div id="moduleSelectionScreen" class="module-selection-container" ...>`, os 3 `<div class="module-bg ...">`, o `<div class="module-selection-card ...">` com título/subtítulo/7 `<div class="menu-card">` de Agenda/Pacientes/Financeiro/Despesas/Relatórios/Prontuário/Configurações, e o botão "Sair do Sistema").

- [x] **Step 2: Ajustar `selectModule` e remover `voltarAosModulos` em `js/script.js`**

Substituir (linhas 502-520):

```javascript
// Nova função para selecionar módulo e navegar
function selectModule(moduleId) {
    document.getElementById('moduleSelectionScreen').style.display = 'none';
    document.getElementById('appScreen').style.display = 'block';
    if (!sistemaInicializado) {
        inicializarSistema(moduleId);
        sistemaInicializado = true;
    } else {
        irParaAba(moduleId);
        renderDashboardSummaries();
        initCharts();
    }
    mostrarToast(`Módulo ${moduleId.charAt(0).toUpperCase() + moduleId.slice(1)} selecionado!`, 'info');
}

function voltarAosModulos() {
    document.getElementById('appScreen').style.display = 'none';
    document.getElementById('moduleSelectionScreen').style.display = 'flex';
}
```

por:

```javascript
// Exibe o sistema principal direto (sem tela intermediária de seleção de módulo)
function selectModule(moduleId) {
    document.getElementById('appScreen').style.display = 'block';
    if (!sistemaInicializado) {
        inicializarSistema(moduleId);
        sistemaInicializado = true;
    } else {
        irParaAba(moduleId);
        renderDashboardSummaries();
        initCharts();
    }
}
```

(A função `selectModule` é mantida — com esse nome e assinatura — porque é ela quem decide entre inicializar o sistema pela primeira vez ou só trocar de aba; login e restauração de sessão passam a chamá-la diretamente com `'dashboard'`.)

- [x] **Step 3: Login vai direto pro dashboard**

Localizar em `js/script.js` (dentro do listener de submit de `#loginForm`, por volta da linha 2822):

```javascript
        const success = await login(user, pass);
    if (success) {
        document.getElementById('loginScreen').style.display = 'none';
        document.getElementById('moduleSelectionScreen').style.display = 'flex';
        document.getElementById('moduleSelectionUserName').textContent = usuarioLogado.nome.split(' ')[0];
        atualizarHeaderUsuario();
        aplicarPermissoesUI(); // Aplicar permissões aos cards da tela de seleção
        mostrarToast('Bem-vindo ao sistema!');
    } else {
        mostrarToast('Usuário ou senha inválidos', 'danger');
    }
});
```

Substituir por:

```javascript
        const success = await login(user, pass);
    if (success) {
        document.getElementById('loginScreen').style.display = 'none';
        atualizarHeaderUsuario();
        selectModule('dashboard');
        mostrarToast('Bem-vindo ao sistema!');
    } else {
        mostrarToast('Usuário ou senha inválidos', 'danger');
    }
});
```

- [x] **Step 4: `verificarLoginSalvo` também vai direto pro dashboard**

Localizar (por volta da linha 2834):

```javascript
async function verificarLoginSalvo() {
    if (sessionStorage.getItem('logged') === 'true') {
        const authValid = await verificarAuth();
        if (authValid) {
            document.getElementById('loginScreen').style.display = 'none';
            document.getElementById('moduleSelectionScreen').style.display = 'flex';
            document.getElementById('moduleSelectionUserName').textContent = usuarioLogado.nome.split(' ')[0];
            atualizarHeaderUsuario();
            aplicarPermissoesUI(); // Aplicar permissões aos cards da tela de seleção
        } else {
            sessionStorage.clear();
        }
    }
}
```

Substituir por:

```javascript
async function verificarLoginSalvo() {
    if (sessionStorage.getItem('logged') === 'true') {
        const authValid = await verificarAuth();
        if (authValid) {
            document.getElementById('loginScreen').style.display = 'none';
            atualizarHeaderUsuario();
            selectModule('dashboard');
        } else {
            sessionStorage.clear();
        }
    }
}
```

- [x] **Step 5: `sairSistema` não referencia mais a tela removida**

Localizar (por volta da linha 2851 — já não referencia `moduleSelectionScreen`, só confirmar):

```javascript
async function sairSistema() {
    await logout();
    document.getElementById('appScreen').style.display = 'none';
    document.getElementById('moduleSelectionScreen').style.display = 'none'; // Ocultar tela de seleção
    document.getElementById('loginScreen').style.display = 'flex';
    sessionStorage.clear();
}
```

Substituir por (remove a linha que mexe no elemento apagado):

```javascript
async function sairSistema() {
    await logout();
    document.getElementById('appScreen').style.display = 'none';
    document.getElementById('loginScreen').style.display = 'flex';
    sessionStorage.clear();
}
```

- [x] **Step 6: Remover CSS órfão de `css/style.css`**

Remover os blocos `.module-selection-container`, `.module-bg`, `.module-bg-1`, `.module-bg-2`, `.module-bg-3`, `@keyframes moduleFloat`, `.module-selection-card`, `.module-selection-title`, `@keyframes titleFlow`, `.module-selection-subtitle`, `.module-selection-subtitle #moduleSelectionUserName`, `@keyframes subtitleBreath`, `@keyframes userGlow`, e (mais abaixo no arquivo) `.menu-card`, `.menu-card:hover`, `.menu-card-icon` e variantes de cor (`bg-info-pale`, `bg-verde-pale`, `bg-ouro-pale`, `bg-danger-pale`, `bg-primary-pale`, `bg-secondary-pale`, se existirem só para os cards de módulo — confirmar com uma busca por `menu-card` no arquivo antes de apagar, para não remover uma classe usada em outro lugar). Também remover as referências a `.module-selection-container`/`.module-selection-card`/`.module-selection-subtitle` dentro dos blocos `body.dark` (linhas que citam esses seletores).

- [x] **Step 7: Verificar visualmente**

Fazer logout e login de novo (`admin`/`0301`). Confirmar: vai direto para o Dashboard, sem tela intermediária. Dar F5 com sessão ativa — também deve ir direto pro Dashboard (via `verificarLoginSalvo`). Clicar em "Sair" — volta pro login corretamente.

- [x] **Step 8: Commit**

```bash
git add index.html js/script.js css/style.css
git commit -m "refactor: remover tela de selecao de modulos, login vai direto ao dashboard"
```

---

### Task 5: Remover excesso decorativo remanescente (shimmer, gradientes de texto)

**Files:**
- Modify: `css/style.css` (`.btn-verde::after`, `@keyframes shimmer`)

**Interfaces:**
- Consumes: nenhuma nova.

- [x] **Step 1: Remover o efeito de brilho (shimmer) dos botões**

Remover o bloco:

```css
/* Shimmer Effect */
.btn-verde::after, .btn-primary::after {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 60%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.25), transparent);
    transform: skewX(-20deg);
}
.btn-verde:hover::after, .btn-primary:hover::after {
    animation: shimmer .6s ease forwards;
}

@keyframes shimmer {
    to { left: 150%; }
}
```

(Efeito de brilho animado não combina com a linha clean/minimalista aprovada; os botões já ficam com hover sólido definido na Task 2.)

- [x] **Step 2: Verificar visualmente**

Passar o mouse sobre qualquer botão primário (ex: "Entrar no Sistema", "Cadastrar Paciente") e confirmar que o hover é sólido (muda pra `--verde-escuro`), sem a faixa de brilho animada atravessando o botão.

- [x] **Step 3: Commit**

```bash
git add css/style.css
git commit -m "style: remover efeito de brilho animado dos botoes (linha minimalista)"
```

---

### Task 6: Verificação visual completa (QA manual)

**Files:** nenhum (task só de verificação; qualquer bug encontrado gera um fix pontual antes de marcar como concluída).

- [x] **Step 1: Percorrer todas as telas logado como admin**

No XAMPP local (`http://localhost/Sistema_Guanais/`, `admin`/`0301`), clicar em cada ícone da sidebar e confirmar visual consistente (índigo/cinza, Inter, cards com borda sutil) em: Dashboard, Agenda, Pacientes (lista e uma ficha de paciente), Financeiro, Despesas, Novo Atendimento, Relatórios, Prontuário, Configurações (as 3 sub-abas: Usuários, Design Visual, Dados e Backup).

- [x] **Step 2: Testar modais e formulários**

Abrir pelo menos um modal (ex: editar paciente, ou confirmação de exclusão) e confirmar que abre/fecha normalmente e o visual do modal já reflete os novos tokens (`--radius-md`, `--sombra-*`).

- [x] **Step 3: Testar dark mode**

Clicar no toggle de tema no header em 3 telas diferentes (Dashboard, Pacientes, Configurações) e confirmar que todas mudam para a paleta escura correspondente sem texto ilegível (contraste) ou cores verdes/douradas remanescentes.

- [x] **Step 4: Testar responsividade**

Redimensionar para <992px (ou usar `resize_window` do navegador de preview) e confirmar que a sidebar vira barra inferior de ícones e o conteúdo continua acessível e utilizável.

- [x] **Step 5: Confirmar que nenhuma funcionalidade quebrou**

Criar um paciente de teste, lançar um recebimento financeiro de teste, confirmar que aparecem nas listas — depois excluir os dois (esse é o mesmo tipo de fluxo que `test_sistema.php` testa manualmente, mas feito à mão aqui). Isso confirma que a Task 4 (mudança de fluxo de login/JS) não quebrou nenhuma chamada de API.

- [x] **Step 6: Reportar quaisquer inconsistências encontradas**

Se algo ficou com cor antiga (verde/dourado) ou quebrado, anotar o seletor/tela exata e corrigir com um fix pontual (provavelmente um `var(--ouro)`/`var(--verde)` hardcoded que escapou do remapeamento da Task 1, ou uma referência residual a `moduleSelectionScreen`).
