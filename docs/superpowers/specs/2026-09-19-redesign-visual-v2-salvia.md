# Repaginação visual v2 — paleta Sálvia Acolhedora

Status: aprovado no brainstorming (paleta, sidebar colapsável e padrões de componente validados via mockups em `Guanais-todas-as-imagens/`). Implementação em fases, cada uma testada e commitada separadamente.

## 1. Motivação

Repaginação total do visual do sistema, usando como referência exata 6 pranchas de mockup fornecidas pelo usuário (`Guanais-todas-as-imagens/01` a `06`). Substitui a paleta "Slate & Índigo" (redesign anterior) por uma identidade mais quente e orgânica ("Sálvia Acolhedora") no tema claro, mantendo o tema escuro ("Noturno Suave") já muito próximo do que existe hoje.

Fora de escopo: mudança de regra de negócio, API ou schema de banco. É puramente visual/estrutural de navegação.

## 2. Direção de design (validada)

- **Paleta clara: Sálvia Acolhedora** — fundo creme quente, verde-sálvia como destaque, terracota como acento secundário (avatares/decoração).
- **Paleta escura: Noturno Suave** — mantém a base indigo/navy já implementada (`body.dark`), só com pequenos ajustes de tom.
- **Sidebar colapsável**: rail de ícones (76px) por padrão, expansível (~220px, ícone+rótulo) via botão ☰ no topo da própria sidebar. Estado persistido em `localStorage`. Comportamento mobile (barra inferior) inalterado.
- **Cabeçalho**: avatar do usuário redesenhado (círculo colorido com iniciais). Sino de notificação conectado ao recurso já existente de Alertas da Agenda (contagem como badge, clique navega ao Dashboard).
- **Padrão de página**: rótulo pequeno maiúsculo na cor de destaque + título grande + subtítulo cinza, em todas as abas.
- **Componentes recorrentes**: cards de métrica com ícone-em-círculo + chevron; avatares com iniciais coloridas em tabelas; badges de status (verde/vermelho/âmbar); timeline do Prontuário com ajuste de cor.

## 3. Tokens de cor (tema claro — Sálvia)

Baseado nos hex exatos usados para gerar os mockups (`Guanais-todas-as-imagens/LEIA-ME.md`):

```css
:root {
  --verde:        #638677;   /* accent sage principal */
  --verde-escuro: #4F6D60;   /* hover/dark */
  --verde-pale:   #E7EEE9;   /* fundos suaves de ícone/badge */
  --ouro:         #C08A5C;   /* terracota — acento secundário */
  --ouro-pale:    #F3E4D8;
  --crema:        #F6F5F0;   /* fundo da aplicação */
  --cinza-txt:    #243C35;   /* texto principal, quase-preto verde */
  --cinza-muted:  #6B7A72;
  --branco:       #FFFFFF;
  --color-warning:#C08A5C;
  --border-light: #E7E2D6;
}
```

Tema escuro (`body.dark`) mantém os valores atuais (já é uma variante indigo/navy próxima do mockup "Noturno Suave"); nenhuma mudança estrutural nesta fase, só ajuste pontual se necessário durante a implementação.

Sidebar continua com fundo escuro fixo (`#0F172A`), independente do tema — não muda.

## 4. Rollout (fases, commit a cada uma)

1. **Base**: tokens de cor Sálvia + sidebar colapsável + cabeçalho (avatar + sino de alertas)
2. **Dashboard**: cards com ícone+chevron, alertas, gráficos
3. **Agenda + Novo Atendimento**
4. **Pacientes + Prontuário**
5. **Financeiro + Despesas**
6. **Relatórios + Configurações + Ajuda**

Cada fase testada no navegador com dados reais (login real, screenshots, interação) antes de commitar. Suite `testador_completo.php` roda antes de cada commit (baseline 57/57).
