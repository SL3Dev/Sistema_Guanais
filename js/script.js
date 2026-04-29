// script.js
// Sistema de Gestão Clínica Espaço Guanais - Versão com API

// ====================== CONFIGURAÇÃO DA API ======================
const API_BASE_URL = 'api/';

// ====================== DADOS GLOBAIS ======================
let dados = { pacientes: [], atendimentos: [], financeiro: [], despesas: [] };
let usuarioLogado = null;

// ====================== FUNÇÕES DE API ======================
async function apiRequest(endpoint, method = 'GET', data = null) {
    const url = API_BASE_URL + endpoint;
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
        
        if (!response.ok) {
            throw new Error(result.error || 'Erro na requisição');
        }

        // Feedback visual para mutações
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method.toUpperCase())) {
            mostrarSalvo();
        }
        
        return result;
    } catch (error) {
        console.error('Erro na API:', error);
        mostrarToast('Erro: ' + error.message, 'danger');
        throw error;
    }
}

// ====================== AUTENTICAÇÃO ======================
async function login(usuario, senha) {
    try {
        const result = await apiRequest('auth.php', 'POST', { usuario, senha });
        if (result.success) {
            usuarioLogado = result.data.user;
            sessionStorage.setItem('logged', 'true');
            sessionStorage.setItem('usuario', JSON.stringify(usuarioLogado));
            return true;
        }
        return false;
    } catch (error) {
        return false;
    }
}

async function verificarAuth() {
    try {
        const result = await apiRequest('auth.php', 'GET');
        if (result.data && result.data.logged) {
            usuarioLogado = result.data.user;
            sessionStorage.setItem('usuario', JSON.stringify(usuarioLogado));
            return true;
        }
        return false;
    } catch (error) {
        return false;
    }
}

function userHasPermission(modulo, acao) {
    if (!usuarioLogado) return false;
    
    // Admin tem acesso total
    if (usuarioLogado.usuario === 'admin') return true;
    
    if (!usuarioLogado.permissoes) return false;
    
    return usuarioLogado.permissoes.some(p => 
        p.modulo === modulo && p.acao === acao && parseInt(p.permitido) === 1
    );
}

function aplicarPermissoesUI() {
    if (!usuarioLogado) return;

    const modulosConfig = {
        'agenda': 'atendimentos',
        'pacientes': 'pacientes',
        'financeiro': 'financeiro',
        'despesas': 'despesas',
        'novoAtendimento': 'atendimentos',
        'relatorios': 'financeiro',
        'configuracoes': 'configuracoes',
        'ajuda': 'configuracoes' // Ajuda pode ter permissão de visualização geral ou de configurações
    };

    // Aplicar permissões aos cards da tela de seleção de módulos
    Object.keys(modulosConfig).forEach(moduloId => {
        const card = document.querySelector(`#moduleSelectionScreen .menu-card[onclick*="${moduloId}"]`);
        if (card) {
            if (!userHasPermission(modulosConfig[moduloId], 'visualizar')) {
                card.parentElement.style.display = 'none';
            } else {
                card.parentElement.style.display = 'block';
            }
        }
    });

    // Aplicar permissões às abas principais (mantido do código original)
    const abas = {
        '#dashboard': 'dashboard', // Dashboard é sempre visível, mas pode ter elementos internos restritos
        '#agenda': 'atendimentos',
        '#pacientes': 'pacientes',
        '#financeiro': 'financeiro',
        '#despesas': 'despesas',
        '#novoAtendimento': 'atendimentos',
        '#relatorios': 'financeiro',
        '#configuracoes': 'configuracoes',
        '#ajuda': 'configuracoes'
    };

    Object.keys(abas).forEach(id => {
        const tabBtn = document.querySelector(`[data-bs-target="${id}"]`);
        if (tabBtn) {
            // O dashboard é um caso especial, sempre visível, mas seus cards internos são controlados
            if (id === '#dashboard') {
                tabBtn.parentElement.style.display = 'block';
            } else if (!userHasPermission(modulosConfig[id.replace('#', '')], 'visualizar')) {
                tabBtn.parentElement.style.display = 'none';
            } else {
                tabBtn.parentElement.style.display = 'block';
            }
        }
    });

    // 2. Botões de ação globais (Criar) - Mantido do código original
    if (!userHasPermission('pacientes', 'criar')) {
        const pacForm = document.getElementById('pacienteForm');
        if (pacForm) pacForm.querySelector('button[type="submit"]').disabled = true;
    }

    if (!userHasPermission('atendimentos', 'criar')) {
        const atendForm = document.getElementById('atendimentoForm');
        if (atendForm) atendForm.querySelector('button[type="submit"]').disabled = true;
    }

    if (!userHasPermission('financeiro', 'criar')) {
        const finForm = document.getElementById('financeiroForm');
        if (finForm) finForm.querySelector('button[type="submit"]').disabled = true;
    }

    if (!userHasPermission('despesas', 'criar')) {
        const despForm = document.getElementById('despesaForm');
        if (despForm) despForm.querySelector('button[type="submit"]').disabled = true;
    }

    if (!userHasPermission('configuracoes', 'criar')) {
        const usuForm = document.getElementById('usuarioForm');
        if (usuForm) usuForm.querySelector('button[type="submit"]').disabled = true;
    }
}

async function logout() {
    try {
        await apiRequest('auth.php', 'DELETE');
    } catch (error) {
        // Ignora erro no logout
    }
    sessionStorage.clear();
    usuarioLogado = null;
}

// Nova função para selecionar módulo e navegar
function selectModule(moduleId) {
    document.getElementById('moduleSelectionScreen').style.display = 'none';
    document.getElementById('appScreen').style.display = 'block';
    irParaAba(moduleId);
    mostrarToast(`Módulo ${moduleId.charAt(0).toUpperCase() + moduleId.slice(1)} selecionado!`, 'info');
}

function voltarAosModulos() {
    document.getElementById('appScreen').style.display = 'none';
    document.getElementById('moduleSelectionScreen').style.display = 'flex';
}

// ====================== PACIENTES ======================
async function carregarPacientes() {
    try {
        const result = await apiRequest('pacientes.php');
        if (result.success) {
            dados.pacientes = result.data || [];
        }
    } catch (error) {
        dados.pacientes = [];
    }
}

async function salvarPaciente(paciente) {
    const method = paciente.id ? 'PUT' : 'POST';
    const endpoint = 'pacientes.php';
    
    try {
        const result = await apiRequest(endpoint, method, paciente);
        if (result.success) {
            mostrarToast('Paciente salvo com sucesso');
            await carregarPacientes();
            return true;
        }
    } catch (error) {
        return false;
    }
}

async function excluirPacienteAPI(id) {
    try {
        const result = await apiRequest('pacientes.php?id=' + id, 'DELETE');
        if (result.success) {
            mostrarToast('Paciente removido');
            await carregarPacientes();
            return true;
        }
    } catch (error) {
        return false;
    }
}

// ====================== ATENDIMENTOS ======================
async function carregarAtendimentos() {
    try {
        const result = await apiRequest('atendimentos.php');
        if (result.success) {
            dados.atendimentos = result.data.atendimentos || [];
        }
    } catch (error) {
        dados.atendimentos = [];
    }
}

async function salvarAtendimento(atendimento) {
    try {
        const result = await apiRequest('atendimentos.php', 'POST', atendimento);
        if (result.success) {
            mostrarToast('Atendimento registrado com sucesso');
            await carregarAtendimentos();
            return true;
        }
    } catch (error) {
        return false;
    }
}

async function excluirAtendimentoAPI(id) {
    try {
        const result = await apiRequest('atendimentos.php?id=' + id, 'DELETE');
        if (result.success) {
            mostrarToast('Atendimento removido');
            await carregarAtendimentos();
            return true;
        }
    } catch (error) {
        return false;
    }
}

// ====================== FINANCEIRO ======================
async function carregarFinanceiro(mes = null, clinica = null) {
    let params = '';
    if (mes) params += '&mes=' + mes;
    if (clinica) params += '&clinica=' + clinica;
    
    try {
        const result = await apiRequest('financeiro.php?' + params);
        if (result.success) {
            dados.financeiro = result.data.lancamentos || [];
            return result.data.resumo || null;
        }
    } catch (error) {
        dados.financeiro = [];
    }
    return null;
}

async function salvarFinanceiro(lancamento) {
    try {
        const result = await apiRequest('financeiro.php', 'POST', lancamento);
        if (result.success) {
            mostrarToast('Recebimento registrado com sucesso');
            await carregarFinanceiro();
            return true;
        }
    } catch (error) {
        return false;
    }
}

async function excluirFinanceiroAPI(id) {
    try {
        const result = await apiRequest('financeiro.php?id=' + id, 'DELETE');
        if (result.success) {
            mostrarToast('Lançamento removido');
            await carregarFinanceiro();
            return true;
        }
    } catch (error) {
        return false;
    }
}

// ====================== DESPESAS ======================
async function carregarDespesas() {
    try {
        const result = await apiRequest('despesas.php');
        if (result.success) {
            dados.despesas = result.data || [];
        }
    } catch (error) {
        dados.despesas = [];
    }
}

async function salvarDespesa(despesa) {
    try {
        const result = await apiRequest('despesas.php', 'POST', despesa);
        if (result.success) {
            mostrarToast('Despesa cadastrada com sucesso');
            await carregarDespesas();
            return true;
        }
    } catch (error) {
        return false;
    }
}

async function pagarParcelaAPI(id) {
    try {
        const result = await apiRequest('despesas.php', 'PATCH', { id });
        if (result.success) {
            mostrarToast('Parcela paga com sucesso');
            await carregarDespesas();
            return true;
        }
    } catch (error) {
        return false;
    }
}

async function excluirDespesaAPI(id) {
    try {
        const result = await apiRequest('despesas.php?id=' + id, 'DELETE');
        if (result.success) {
            mostrarToast('Despesa removida');
            await carregarDespesas();
            return true;
        }
    } catch (error) {
        return false;
    }
}

// ====================== USUÁRIOS E PERMISSÕES ======================
let usuariosData = [];

async function carregarUsuarios() {
    try {
        const result = await apiRequest('usuarios.php');
        if (result.success) {
            usuariosData = result.data || [];
            renderUsuarios();
        }
    } catch (error) {
        console.error('Erro ao carregar usuários:', error);
    }
}

function renderUsuarios() {
    let html = '';
    
    const podeEditar = userHasPermission('configuracoes', 'editar');
    const podeExcluir = userHasPermission('configuracoes', 'excluir');

    usuariosData.forEach(u => {
        const tipoLabel = u.tipo === 'admin' ? 'Administrador' : u.tipo === 'terapeuta' ? 'Terapeuta' : 'Secretaria';
        const statusClass = u.ativo ? 'badge-success' : 'badge-danger';
        const statusLabel = u.ativo ? 'Ativo' : 'Inativo';
        
        html += `<tr>
            <td>${u.id}</td>
            <td>${u.usuario}</td>
            <td>${u.nome}</td>
            <td><span class="badge-custom ${u.tipo === 'admin' ? 'badge-warning' : 'badge-info'}">${tipoLabel}</span></td>
            <td><span class="badge-custom ${statusClass}">${statusLabel}</span></td>
            <td class="text-center">
                ${podeEditar ? `
                    <button class="btn btn-sm btn-outline" onclick="gerenciarPermissoes(${u.id})" title="Permissões">
                        <i class="bi bi-shield-lock"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="editarUsuario(${u.id})" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                ` : ''}
                ${podeExcluir ? `
                    <button class="btn btn-sm btn-outline text-danger" onclick="excluirUsuario(${u.id})" title="Excluir" ${u.id === 1 ? 'disabled' : ''}>
                        <i class="bi bi-trash"></i>
                    </button>
                ` : ''}
            </td>
        </tr>`;
    });
    document.getElementById('usuariosTbody').innerHTML = html || '<tr><td colspan="6" class="text-center py-3 text-muted">Nenhum usuário encontrado</td></tr>';
}

document.getElementById('usuarioForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const usuario = document.getElementById('usuUsuario').value.trim();
    const senha = document.getElementById('usuSenha').value;
    const nome = document.getElementById('usuNome').value.trim();
    const email = document.getElementById('usuEmail').value.trim();
    const tipo = document.getElementById('usuTipo').value;
    
    if (!usuario) { mostrarToast('Usuário é obrigatório', 'danger'); return; }
    if (!senha || senha.length < 4) { mostrarToast('Senha deve ter pelo menos 4 caracteres', 'danger'); return; }
    if (!nome) { mostrarToast('Nome é obrigatório', 'danger'); return; }
    if (email && !email.includes('@')) { mostrarToast('Email inválido', 'danger'); return; }
    
    const novoUsuario = { usuario, senha, nome, email, tipo };
    
    try {
        const result = await apiRequest('usuarios.php', 'POST', novoUsuario);
        if (result.success) {
            mostrarToast('Usuário criado com sucesso');
            document.getElementById('usuarioForm').reset();
            await carregarUsuarios();
        } else {
            mostrarToast(result.error || 'Erro ao criar usuário', 'danger');
        }
    } catch (error) {
        console.error('Erro ao criar usuário:', error);
        mostrarToast('Erro ao criar usuário: ' + error.message, 'danger');
    }
});

async function editarUsuario(id) {
    try {
        const response = await fetch(`api/usuarios.php?id=${id}`, { credentials: 'same-origin' });
        const result = await response.json();
        if (result.success && result.data) {
            const usuario = result.data;
            document.getElementById('editUsuId').value = usuario.id;
            document.getElementById('editUsuNome').value = usuario.nome || '';
            document.getElementById('editUsuEmail').value = usuario.email || '';
            document.getElementById('editUsuTipo').value = usuario.tipo || 'secretaria';
            document.getElementById('editUsuAtivo').checked = usuario.ativo == 1;
            document.getElementById('editUsuSenha').value = '';

            new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
        } else {
            mostrarToast('Erro ao carregar dados do usuário', 'danger');
        }
    } catch (error) {
        console.error(error);
        mostrarToast('Erro de conexão', 'danger');
    }
}

async function atualizarUsuario() {
    const id = document.getElementById('editUsuId').value;
    const nome = document.getElementById('editUsuNome').value;
    const email = document.getElementById('editUsuEmail').value;
    const tipo = document.getElementById('editUsuTipo').value;
    const ativo = document.getElementById('editUsuAtivo').checked ? 1 : 0;
    const senha = document.getElementById('editUsuSenha').value;

    const payload = { nome, email, tipo, ativo };
    if (senha.trim() !== '') payload.senha = senha;

    try {
        const response = await fetch(`api/usuarios.php?id=${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalEditarUsuario')).hide();
            carregarUsuarios();
            mostrarToast('Usuário atualizado com sucesso');
        } else {
            mostrarToast('Erro: ' + (result.error || 'Não foi possível atualizar'), 'danger');
        }
    } catch (error) {
        console.error(error);
        mostrarToast('Erro de conexão', 'danger');
    }
}

async function excluirUsuario(id) {
    if (id === 1) {
        mostrarToast('Não é possível excluir o usuário administrador principal', 'danger');
        return;
    }
    if (!confirm('Tem certeza que deseja excluir este usuário?')) return;
    
    try {
        const result = await apiRequest('usuarios.php?id=' + id, 'DELETE');
        if (result.success) {
            mostrarToast('Usuário excluído com sucesso');
            await carregarUsuarios();
        }
    } catch (error) {
        console.error('Erro ao excluir usuário:', error);
    }
}

async function gerenciarPermissoes(usuarioId) {
    try {
        const response = await fetch(`api/usuarios.php?id=${usuarioId}`, { credentials: 'same-origin' });
        const result = await response.json();
        if (result.success && result.data) {
            const usuario = result.data;
            document.getElementById('permUsuarioId').value = usuarioId;

            const tbody = document.getElementById('permissoesBody');
            if (tbody) {
                const modulos = ['pacientes', 'atendimentos', 'financeiro', 'despesas', 'configuracoes'];
                const acoes = ['visualizar', 'criar', 'editar', 'excluir'];

                tbody.innerHTML = '';
                modulos.forEach(modulo => {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td><strong>${modulo.charAt(0).toUpperCase() + modulo.slice(1)}</strong></td>`;
                    acoes.forEach(acao => {
                        const permissao = usuario.permissoes?.find(p => p.modulo === modulo && p.acao === acao);
                        const checked = permissao && permissao.permitido == 1 ? 'checked' : '';
                        row.innerHTML += `
                            <td class="text-center">
                                <input type="checkbox" class="perm-checkbox" data-modulo="${modulo}" data-acao="${acao}" ${checked}>
                            </td>
                        `;
                    });
                    tbody.appendChild(row);
                });
            }
            new bootstrap.Modal(document.getElementById('modalPermissoes')).show();
        } else {
            mostrarToast('Erro ao carregar permissões', 'danger');
        }
    } catch (error) {
        console.error(error);
        mostrarToast('Erro de conexão', 'danger');
    }
}

async function salvarPermissoes() {
    const usuarioId = document.getElementById('permUsuarioId').value;
    const checkboxes = document.querySelectorAll('#permissoesBody .perm-checkbox');
    const permissoes = [];

    checkboxes.forEach(cb => {
        permissoes.push({
            modulo: cb.getAttribute('data-modulo'),
            acao: cb.getAttribute('data-acao'),
            permitido: cb.checked ? 1 : 0
        });
    });

    try {
        const response = await fetch(`api/usuarios.php?id=${usuarioId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ permissoes })
        });
        const result = await response.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalPermissoes')).hide();
            mostrarToast('Permissões salvas com sucesso');
            await carregarUsuarios();
        } else {
            mostrarToast('Erro: ' + (result.error || 'Não foi possível salvar'), 'danger');
        }
    } catch (error) {
        console.error(error);
        mostrarToast('Erro de conexão', 'danger');
    }
}

function initUsuariosTab() {
    const configTab = document.querySelector('#mainTab button[data-bs-target="#configuracoes"]');
    if (configTab) {
        configTab.addEventListener('shown.bs.tab', function() {
            carregarUsuarios();
        });
    }
    if (document.querySelector('#configuracoes').classList.contains('show')) {
        carregarUsuarios();
    }
}

// ====================== FUNÇÕES AUXILIARES ======================
function mostrarToast(mensagem, tipo = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) {
        // Fallback se o container não existir
        let toastDiv = document.createElement('div');
        toastDiv.className = `toast-msg toast-${tipo}`;
        toastDiv.innerHTML = `<i class="bi bi-info-circle"></i> ${mensagem}`;
        document.body.appendChild(toastDiv);
        setTimeout(() => toastDiv.remove(), 3000);
        return;
    }
    const toast = document.createElement('div');
    toast.className = `toast-msg toast-${tipo}`;
    const icones = { success: 'check-circle-fill', danger: 'x-circle-fill', warning: 'exclamation-triangle-fill' };
    toast.innerHTML = `<i class="bi bi-${icones[tipo] || 'info-circle-fill'}"></i>${mensagem}`;
    container.appendChild(toast);
    setTimeout(() => { if (toast.parentNode) toast.remove(); }, 3200);
}

function mostrarSalvo() {
    const ind = document.getElementById('status-salvo');
    if (!ind) return;
    ind.style.display = 'inline-flex';
    clearTimeout(ind._timer);
    ind._timer = setTimeout(() => { ind.style.display = 'none'; }, 2500);
}

function gerarId(prefixo) {
    return prefixo + String(Date.now() % 10000 + Math.floor(Math.random() * 1000)).padStart(4, '0');
}

function formataDataBR(iso) {
    if (!iso) return '';
    let [a, m, d] = iso.split('-');
    return `${d}/${m}/${a}`;
}

function formataDataISO(br) {
    if (br && br.includes('/')) {
        let [d, m, a] = br.split('/');
        return `${a}-${m}-${d}`;
    }
    return br;
}

function calcularIdade(dataNasc) {
    if (!dataNasc) return 0;
    let hoje = new Date(), nasc = new Date(dataNasc);
    let idade = hoje.getFullYear() - nasc.getFullYear();
    if (hoje.getMonth() < nasc.getMonth() || (hoje.getMonth() === nasc.getMonth() && hoje.getDate() < nasc.getDate())) idade--;
    return idade;
}

// ====================== DASHBOARD INTERACTIVITY ======================
function initDashboardInteractivity() {
    const cardAtivos = document.getElementById('cardAtivos');
    const cardFaltas = document.getElementById('cardFaltas');
    const cardExcecoes = document.getElementById('cardExcecoes');
    const cardProximo = document.getElementById('cardProximo');

    if (cardAtivos) {
        cardAtivos.addEventListener('click', () => {
            const tabTrigger = new bootstrap.Tab(document.querySelector('[data-bs-target="#pacientes"]'));
            tabTrigger.show();
            mostrarToast('Listando pacientes ativos', 'info');
        });
    }

    if (cardFaltas) {
        cardFaltas.addEventListener('click', () => {
            limparFiltrosAgenda();
            document.getElementById('filtroStatus').value = 'Falta';
            renderAgenda();
            mostrarToast('Filtrando faltas registradas no mês', 'info');
        });
    }

    if (cardExcecoes) {
        cardExcecoes.addEventListener('click', () => {
            limparFiltrosAgenda();
            document.getElementById('filtroStatus').value = 'Exceção Justificada';
            renderAgenda();
            mostrarToast('Filtrando exceções justificadas', 'info');
        });
    }

    if (cardProximo) {
        cardProximo.addEventListener('click', () => {
            limparFiltrosAgenda();
            const hojeStr = new Date().toISOString().slice(0, 10);
            let proximos = dados.atendimentos.filter(a => formataDataISO(a.data_atendimento) >= hojeStr);
            
            if (proximos.length > 0) {
                // Ordenar para garantir que o mais próximo apareça primeiro
                proximos.sort((a, b) => new Date(formataDataISO(a.data_atendimento)) - new Date(formataDataISO(b.data_atendimento)));
                const proximo = proximos[0];
                
                // Mostrar apenas os próximos e destacar o primeiro
                renderAgenda(proximos);
                mostrarToast(`Localizado próximo atendimento: ${proximo.paciente_nome} em ${proximo.data_atendimento}`, 'info');
            } else {
                mostrarToast('Nenhum atendimento futuro encontrado', 'warning');
            }
        });
    }
}

// ====================== CONFIGURAÇÕES E LOGOS ======================
async function carregarConfiguracoes() {
    try {
        const result = await apiRequest('configuracoes.php');
        if (result.success && result.data) {
            result.data.forEach(config => {
                if (config.chave === 'logo_path') {
                    atualizarLogoUI('header', config.valor);
                } else if (config.chave === 'logo_login') {
                    atualizarLogoUI('login', config.valor);
                } else if (config.chave === 'nome_sistema') {
                    aplicarNomeSistema(config.valor);
                } else if (config.chave === 'subtitulo_sistema') {
                    aplicarSubtituloSistema(config.valor);
                }
            });
        }
    } catch (error) {
        console.error('Erro ao carregar configurações:', error);
    }
}

function aplicarNomeSistema(nome) {
    if (!nome) return;
    
    // Atualizar no Login
    const loginTitle = document.getElementById('loginSystemName');
    if (loginTitle) loginTitle.textContent = nome;
    
    // Atualizar na Seleção de Módulos
    const selectionTitle = document.getElementById('selectionSystemName');
    if (selectionTitle) selectionTitle.textContent = nome;
    
    // Atualizar no Cabeçalho
    const headerTitle = document.getElementById('headerSystemName');
    if (headerTitle) headerTitle.textContent = nome;
    
    // Atualizar no input das configurações
    const configInput = document.getElementById('configNomeSistema');
    if (configInput) configInput.value = nome;
    
    // Título da aba do navegador
    document.title = `${nome} — Sistema de Gestão Clínica`;
}

function aplicarSubtituloSistema(subtitulo) {
    if (!subtitulo) return;
    
    // Atualizar no Login
    const loginSub = document.getElementById('loginSystemSubtitle');
    if (loginSub) loginSub.textContent = subtitulo;
    
    // Atualizar no Cabeçalho
    const headerSub = document.getElementById('headerSystemSubtitle');
    if (headerSub) headerSub.textContent = subtitulo;
    
    // Atualizar no input das configurações
    const configInput = document.getElementById('configSubtituloSistema');
    if (configInput) configInput.value = subtitulo;
}

async function salvarIdentidadeSistema() {
    const nome = document.getElementById('configNomeSistema').value.trim();
    const subtitulo = document.getElementById('configSubtituloSistema').value.trim();
    
    if (!nome) {
        mostrarToast('O nome do sistema não pode ficar vazio', 'warning');
        return;
    }
    
    try {
        // Salvar nome
        await apiRequest('configuracoes.php', 'PUT', { chave: 'nome_sistema', valor: nome });
        aplicarNomeSistema(nome);
        
        // Salvar subtitulo
        await apiRequest('configuracoes.php', 'PUT', { chave: 'subtitulo_sistema', valor: subtitulo });
        aplicarSubtituloSistema(subtitulo);
        
        mostrarToast('Identidade visual atualizada com sucesso!');
    } catch (error) {
        mostrarToast('Erro ao salvar configurações: ' + error.message, 'danger');
    }
}

function atualizarLogoUI(tipo, path) {
    if (!path) return;
    
    // Adicionar timestamp para evitar cache
    const timestampedPath = path + '?t=' + new Date().getTime();
    
    if (tipo === 'header') {
        const img = document.getElementById('headerLogoImg');
        const icon = document.getElementById('headerLogoIcon');
        const preview = document.getElementById('previewHeaderLogo');
        
        if (img) {
            img.src = timestampedPath;
            img.style.display = 'block';
            if (icon) icon.style.display = 'none';
        }
        if (preview) preview.src = timestampedPath;
    } else if (tipo === 'login') {
        const preview = document.getElementById('previewLoginLogo');
        if (preview) preview.src = timestampedPath;
        
        // Se quisermos mostrar a logo na tela de login também
        const loginHeader = document.querySelector('.login-header-group');
        let loginLogoImg = document.getElementById('loginLogoImg');
        
        if (loginHeader) {
            if (!loginLogoImg) {
                loginLogoImg = document.createElement('img');
                loginLogoImg.id = 'loginLogoImg';
                loginLogoImg.style.maxWidth = '200px';
                loginLogoImg.style.marginBottom = '1.5rem';
                loginHeader.prepend(loginLogoImg);
            }
            loginLogoImg.src = timestampedPath;
            // Ocultar títulos se houver logo? O usuário pediu para mexer no nome...
            // Vamos manter os títulos por enquanto.
        }
    }
}

async function uploadLogo(tipo) {
    const inputId = tipo === 'login' ? 'inputLoginLogo' : 'inputHeaderLogo';
    const fileInput = document.getElementById(inputId);
    
    if (!fileInput || !fileInput.files[0]) {
        mostrarToast('Selecione um arquivo primeiro', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('logo', fileInput.files[0]);
    formData.append('type', tipo);
    
    try {
        const response = await fetch('api/configuracoes.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        if (result.success) {
            mostrarToast('Logo atualizada com sucesso!');
            atualizarLogoUI(tipo, result.data.path);
            fileInput.value = '';
        } else {
            mostrarToast(result.error || 'Erro no upload', 'danger');
        }
    } catch (error) {
        console.error('Erro no upload:', error);
        mostrarToast('Erro de conexão ao enviar logo', 'danger');
    }
}

// ====================== NAVEGAÇÃO ======================
function irParaAba(tabId) {
    const tabEl = document.querySelector(`button[data-bs-target="#${tabId}"]`);
    if (tabEl) {
        const tab = new bootstrap.Tab(tabEl);
        tab.show();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// ====================== INICIALIZAÇÃO ======================
async function inicializarSistema(moduloInicial = 'dashboard') {
    // Carregar configurações primeiro para as logos
    await carregarConfiguracoes();
    
    // Carregar todos os dados da API
    await Promise.all([
        carregarPacientes(),
        carregarAtendimentos(),
        carregarFinanceiro(),
        carregarDespesas()
    ]);

    // Atualizar nome do usuário no dashboard (se estiver no dashboard)
    if (usuarioLogado) {
        const userNameDisplay = document.getElementById('userNameDisplay');
        if (userNameDisplay) userNameDisplay.textContent = usuarioLogado.nome.split(' ')[0];
    }
    
    // Aplicar restrições de permissão na UI (já feito no login/selectModule)
    // aplicarPermissoesUI(); 

    // Popular Dashboard
    renderDashboardSummaries();

    initDashboardInteractivity();

    renderPacientes();
    renderAgenda();
    renderFinanceiro();
    renderDespesas();
    atualizarSelectPacientes();
    
    // Setar valor padrão do mês atual
    const hoje = new Date();
    const mesAtual = hoje.toISOString().slice(0, 7);
    const finMesFiltro = document.getElementById('finMesFiltro');
    const relatorioMes = document.getElementById('relatorioMes');

    if (finMesFiltro) finMesFiltro.value = mesAtual;
    if (relatorioMes) relatorioMes.value = mesAtual;
    
    const finMesFiltroElement = document.getElementById('finMesFiltro');
    if (finMesFiltroElement) {
        finMesFiltroElement.addEventListener('change', () => {
            carregarFinanceiro(finMesFiltroElement.value, document.getElementById('finClinicaFiltro').value)
                .then(() => renderFinanceiro());
        });
    }

    const finClinicaFiltroElement = document.getElementById('finClinicaFiltro');
    if (finClinicaFiltroElement) {
        finClinicaFiltroElement.addEventListener('change', () => {
            carregarFinanceiro(document.getElementById('finMesFiltro').value, finClinicaFiltroElement.value)
                .then(() => renderFinanceiro());
        });
    }
    
    const filtroNomeElement = document.getElementById('filtroNome');
    if (filtroNomeElement) filtroNomeElement.addEventListener('input', renderAgenda);
    
    const filtroPacoteElement = document.getElementById('filtroPacote');
    if (filtroPacoteElement) filtroPacoteElement.addEventListener('change', renderAgenda);
    
    const filtroStatusElement = document.getElementById('filtroStatus');
    if (filtroStatusElement) filtroStatusElement.addEventListener('change', renderAgenda);
    
    const filtroUnidadeElement = document.getElementById('filtroUnidade');
    if (filtroUnidadeElement) filtroUnidadeElement.addEventListener('change', renderAgenda);
    
    const buscaPacienteElement = document.getElementById('buscaPaciente');
    if (buscaPacienteElement) buscaPacienteElement.addEventListener('input', renderPacientes);
    
    const pacNascElement = document.getElementById('pacNasc');
    if (pacNascElement) {
        pacNascElement.addEventListener('change', function() {
            let idade = calcularIdade(this.value);
            document.getElementById('respSec').classList.toggle('d-none', idade >= 18);
            document.getElementById('emergSec').classList.toggle('d-none', idade < 18);
        });
    }
    
    aplicarRegraExcecao();

    // Ativar a aba inicial após a inicialização completa
    irParaAba(moduloInicial);
}

function renderDashboardSummaries() {
    // 1. Próximos Atendimentos
    const proximosDiv = document.getElementById('dashboardProximos');
    if (proximosDiv) {
        const hoje = new Date().toISOString().slice(0, 10);
        const proximos = (dados.atendimentos || [])
            .filter(a => a.data_atendimento >= hoje && a.status === 'Confirmado')
            .sort((a, b) => a.data_atendimento.localeCompare(b.data_atendimento))
            .slice(0, 5);

        if (proximos.length === 0) {
            proximosDiv.innerHTML = '<div class="text-center py-4 text-muted">Nenhum atendimento agendado para hoje ou próximos dias.</div>';
        } else {
            proximosDiv.innerHTML = proximos.map(a => `
                <div class="list-group-item-custom" onclick="selectModule('agenda')" style="cursor: pointer;">
                    <div class="time-badge">${formataDataBR(a.data_atendimento).slice(0, 5)}</div>
                    <div class="flex-grow-1">
                        <div class="fw-bold mb-0" style="font-size: 0.9rem;">${a.paciente_nome}</div>
                        <small class="text-muted" style="font-size: 0.75rem;">${a.unidade} • ${a.tipo_pacote}</small>
                    </div>
                    <i class="bi bi-chevron-right text-muted" style="font-size: 0.8rem;"></i>
                </div>
            `).join('');
        }
    }

    // 2. Resumo Financeiro no Dashboard
    const finResumoDiv = document.getElementById('dashboardFinResumo');
    if (finResumoDiv) {
        const mesAtual = new Date().toISOString().slice(0, 7);
        const lancamentosMes = (dados.financeiro || []).filter(f => f.data && f.data.startsWith(mesAtual));
        
        const totalBruto = lancamentosMes.reduce((acc, curr) => acc + parseFloat(curr.valor || 0), 0);
        const totalLiquido = lancamentosMes.reduce((acc, curr) => acc + parseFloat(curr.receita_disponivel || 0), 0);

        finResumoDiv.innerHTML = `
            <div class="row g-2">
                <div class="col-6">
                    <div class="p-3 bg-light rounded shadow-sm">
                        <small class="text-muted d-block text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Bruto</small>
                        <strong class="text-verde" style="font-size: 1.2rem;">R$ ${totalBruto.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 bg-light rounded shadow-sm">
                        <small class="text-muted d-block text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Líquido</small>
                        <strong class="text-ouro" style="font-size: 1.2rem;">R$ ${totalLiquido.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</strong>
                    </div>
                </div>
            </div>
            <button class="btn btn-sm btn-outline-secondary w-100 mt-3" onclick="selectModule('financeiro')">
                Ver Detalhes Financeiros <i class="bi bi-arrow-right ms-1"></i>
            </button>
        `;
    }
}

function formataDataBR(data) {
    if (!data) return '';
    const partes = data.split('-');
    if (partes.length === 3) {
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }
    return data;
}

// ====================== INICIALIZAÇÃO ======================
async function inicializarSistema(moduloInicial = 'dashboard') {
    // Carregar configurações primeiro para as logos
    await carregarConfiguracoes();
    
    // Carregar todos os dados da API
    await Promise.all([
        carregarPacientes(),
        carregarAtendimentos(),
        carregarFinanceiro(),
        carregarDespesas()
    ]);
    
    // Aplicar restrições de permissão na UI
    aplicarPermissoesUI();

    initDashboardInteractivity();

    renderPacientes();
    renderAgenda();
    renderFinanceiro();
    renderDespesas();
    atualizarSelectPacientes();
    
    // Setar valor padrão do mês atual
    const hoje = new Date();
    const mesAtual = hoje.toISOString().slice(0, 7);
    const finMesFiltro = document.getElementById('finMesFiltro');
    const relatorioMes = document.getElementById('relatorioMes');

    if (finMesFiltro) finMesFiltro.value = mesAtual;
    if (relatorioMes) relatorioMes.value = mesAtual;
    
    const finMesFiltroElement = document.getElementById('finMesFiltro');
    if (finMesFiltroElement) {
        finMesFiltroElement.addEventListener('change', () => {
            carregarFinanceiro(finMesFiltroElement.value, document.getElementById('finClinicaFiltro').value)
                .then(() => renderFinanceiro());
        });
    }

    const finClinicaFiltroElement = document.getElementById('finClinicaFiltro');
    if (finClinicaFiltroElement) {
        finClinicaFiltroElement.addEventListener('change', () => {
            carregarFinanceiro(document.getElementById('finMesFiltro').value, finClinicaFiltroElement.value)
                .then(() => renderFinanceiro());
        });
    }
    
    const filtroNomeElement = document.getElementById('filtroNome');
    if (filtroNomeElement) filtroNomeElement.addEventListener('input', renderAgenda);
    
    const filtroPacoteElement = document.getElementById('filtroPacote');
    if (filtroPacoteElement) filtroPacoteElement.addEventListener('change', renderAgenda);
    
    const filtroStatusElement = document.getElementById('filtroStatus');
    if (filtroStatusElement) filtroStatusElement.addEventListener('change', renderAgenda);
    
    const filtroUnidadeElement = document.getElementById('filtroUnidade');
    if (filtroUnidadeElement) filtroUnidadeElement.addEventListener('change', renderAgenda);
    
    const buscaPacienteElement = document.getElementById('buscaPaciente');
    if (buscaPacienteElement) buscaPacienteElement.addEventListener('input', renderPacientes);
    
    const pacNascElement = document.getElementById('pacNasc');
    if (pacNascElement) {
        pacNascElement.addEventListener('change', function() {
            let idade = calcularIdade(this.value);
            document.getElementById('respSec').classList.toggle('d-none', idade >= 18);
            document.getElementById('emergSec').classList.toggle('d-none', idade < 18);
        });
    }
    
    aplicarRegraExcecao();

    // Ativar a aba inicial após a inicialização completa
    irParaAba(moduloInicial);
}

function atualizarSelectPacientes() {
    let opts = '<option value="">Selecione</option>';
    dados.pacientes.forEach(p => opts += `<option value="${p.id}">${p.nome}</option>`);
    
    const finPaciente = document.getElementById('finPaciente');
    const atendPaciente = document.getElementById('atendPaciente');
    const relatorioPaciente = document.getElementById('relatorioPaciente');
    
    if (finPaciente) finPaciente.innerHTML = opts;
    if (atendPaciente) atendPaciente.innerHTML = opts;
    if (relatorioPaciente) relatorioPaciente.innerHTML = '<option value="">Todos os pacientes</option>' + opts.replace('<option value="">Selecione</option>', '');
}

// ========== PACIENTES ==========
function renderPacientes() {
    let busca = document.getElementById('buscaPaciente')?.value.toLowerCase() || '';
    let filtered = dados.pacientes.filter(p => p.nome.toLowerCase().includes(busca));
    let html = '';
    
    const podeEditar = userHasPermission('pacientes', 'editar');
    const podeExcluir = userHasPermission('pacientes', 'excluir');

    filtered.forEach(p => {
        html += `<tr>
            <td>${p.id}</td>
            <td>${p.nome}</td>
            <td>${p.telefone}</td>
            <td>${calcularIdade(p.data_nascimento)}</td>
            <td>
                ${podeEditar ? `<button class="btn btn-sm btn-outline" onclick="editarPaciente('${p.id}')"><i class="bi bi-pencil"></i></button>` : ''}
                ${podeExcluir ? `<button class="btn btn-sm btn-outline text-danger" onclick="excluirPaciente('${p.id}')"><i class="bi bi-trash"></i></button>` : ''}
            </td>
        </tr>`;
    });
    document.getElementById('pacientesTbody').innerHTML = html || '<tr><td colspan="5" class="text-center py-3 text-muted">Nenhum paciente encontrado</td></tr>';
    document.getElementById('pacientesCount').innerText = filtered.length;
    document.getElementById('dashAtivos').innerText = dados.pacientes.length;
}

async function editarPaciente(id) {
    let p = dados.pacientes.find(x => x.id === id);
    if (!p) return;
    document.getElementById('pacId').value = p.id;
    document.getElementById('pacNome').value = p.nome;
    document.getElementById('pacCpf').value = p.cpf || '';
    document.getElementById('pacNasc').value = p.data_nascimento;
    document.getElementById('pacTel').value = p.telefone;
    document.getElementById('pacEmail').value = p.email || '';
    document.getElementById('pacEnd').value = p.endereco || '';
    if (p.responsavel_nome) {
        document.getElementById('respNome').value = p.responsavel_nome;
        document.getElementById('respTel').value = p.responsavel_telefone;
    }
    if (p.emergencia_nome) {
        document.getElementById('emergNome').value = p.emergencia_nome;
        document.getElementById('emergTel').value = p.emergencia_telefone || '';
        document.getElementById('emergParentesco').value = p.emergencia_parentesco || '';
        document.getElementById('emergInfoAdicionais').value = p.emergencia_info_adicionais || '';
    }
    let idade = calcularIdade(p.data_nascimento);
    document.getElementById('respSec').classList.toggle('d-none', idade >= 18);
    document.getElementById('emergSec').classList.toggle('d-none', idade < 18);
    
    // Rolar para o formulário
    document.getElementById('pacientes').scrollIntoView({ behavior: 'smooth' });
}

async function excluirPaciente(id) {
    if (confirm('Excluir paciente?')) {
        const success = await excluirPacienteAPI(id);
        if (success) {
            renderPacientes();
            atualizarSelectPacientes();
        }
    }
}

function resetPacienteForm() {
    document.getElementById('pacienteForm').reset();
    document.getElementById('pacId').value = '';
    document.getElementById('respSec').classList.add('d-none');
    document.getElementById('emergSec').classList.remove('d-none');
}

document.getElementById('pacienteForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    let id = document.getElementById('pacId').value || null;
    let idade = calcularIdade(document.getElementById('pacNasc').value);
    
    let paciente = {
        id: id,
        nome: document.getElementById('pacNome').value,
        cpf: document.getElementById('pacCpf').value,
        data_nascimento: document.getElementById('pacNasc').value,
        telefone: document.getElementById('pacTel').value,
        email: document.getElementById('pacEmail').value,
        endereco: document.getElementById('pacEnd').value
    };
    
    if (idade < 18) {
        paciente.responsavel_nome = document.getElementById('respNome').value;
        paciente.responsavel_telefone = document.getElementById('respTel').value;
    } else {
        paciente.emergencia_nome = document.getElementById('emergNome').value;
        paciente.emergencia_telefone = document.getElementById('emergTel').value;
        paciente.emergencia_parentesco = document.getElementById('emergParentesco').value;
        paciente.emergencia_info_adicionais = document.getElementById('emergInfoAdicionais').value;
    }
    
    const success = await salvarPaciente(paciente);
    if (success) {
        renderPacientes();
        atualizarSelectPacientes();
        resetPacienteForm();
    }
});

// ========== AGENDA ==========
function aplicarRegraExcecao() {
    if (!dados.atendimentos.length) return;
    
    let hoje = new Date();
    dados.atendimentos.forEach(a => {
        if (a.status !== 'Confirmado' || a.tipo_pacote === 'Avulso') return;
        let dataAtual = new Date(formataDataISO(a.data_atendimento));
        let dataInicio = new Date(formataDataISO(a.data_inicio_pacote));
        let diff = (dataAtual - dataInicio) / 86400000;
        let limite = a.tipo_pacote === 'Mensal' ? 35 : 20;
    });
}

function filtrarHoje() {
    limparFiltrosAgenda();
    const hoje = new Date().toISOString().slice(0, 10);
    const filtroData = document.getElementById('filtroData');
    if (filtroData) filtroData.value = hoje;
    
    // Agora o renderAgenda filtrará pela data do picker
    renderAgenda();
    mostrarToast('Exibindo atendimentos de hoje', 'info');
}

// Modificar renderAgenda para aceitar dados opcionais
function renderAgenda(dadosCustom = null) {
    let nome = document.getElementById('filtroNome')?.value.toLowerCase() || '';
    let pacote = document.getElementById('filtroPacote')?.value || '';
    let status = document.getElementById('filtroStatus')?.value || '';
    let unidade = document.getElementById('filtroUnidade')?.value || '';
    let dataFiltro = document.getElementById('filtroData')?.value || '';
    
    let base = dadosCustom || dados.atendimentos;
    
    let filtrados = base.filter(a => {
        let nomePaciente = (a.paciente_nome || '').toLowerCase();
        let tipoPacote = a.tipo_pacote || '';
        let statusAtend = a.status || '';
        let unidadeAtend = a.unidade || '';
        let dataAtend = formataDataISO(a.data_atendimento);
        
        return nomePaciente.includes(nome) &&
            (!pacote || tipoPacote === pacote) &&
            (!status || statusAtend === status) &&
            (!unidade || unidadeAtend === unidade) &&
            (!dataFiltro || dataAtend === dataFiltro);
    });
    
    // Ordenar por data decrescente
    filtrados.sort((a, b) => {
        let da = a.data_atendimento || '';
        let db = b.data_atendimento || '';
        return new Date(formataDataISO(db)) - new Date(formataDataISO(da));
    });
    
    let faltasMes = 0, excecoes = 0, hoje = new Date(), proxData = null, proxNome = '';
    filtrados.forEach(a => {
        let dataAtend = a.data_atendimento || '';
        let dt = new Date(formataDataISO(dataAtend));
        let statusAtend = a.status || '';
        let nomePaciente = a.paciente_nome || '';
        
        if (statusAtend === 'Falta' && dt.getMonth() === hoje.getMonth() && dt.getFullYear() === hoje.getFullYear()) faltasMes++;
        if (statusAtend === 'Exceção Justificada' || statusAtend === 'Excecao Justificada') excecoes++;
        if (dt >= hoje && (!proxData || dt < proxData)) { proxData = dt; proxNome = nomePaciente; }
    });
    
    document.getElementById('dashFaltas').innerText = faltasMes;
    document.getElementById('dashExcecoes').innerText = excecoes;
    document.getElementById('dashProximo').innerHTML = proxData ? `${formataDataBR(proxData.toISOString().slice(0, 10))}<br><small>${proxNome}</small>` : '—';
    
    let html = '';
    const podeExcluir = userHasPermission('atendimentos', 'excluir');

    filtrados.forEach(a => {
        let idAtend = a.id_atendimento || '';
        let nomePaciente = a.paciente_nome || '';
        let unidadeAtend = a.unidade || '';
        let dataAtend = a.data_atendimento || '';
        let tipoPacote = a.tipo_pacote || '';
        let dataInicioPacote = a.data_inicio_pacote || '';
        let statusAtend = a.status || '';
        
        let badgeClass = statusAtend === 'Confirmado' ? 'badge-success' :
            statusAtend === 'Falta' ? 'badge-danger' :
                statusAtend === 'Exceção Justificada' || statusAtend === 'Excecao Justificada' ? 'badge-warning' : 'badge-info';
        
        html += `<tr>
            <td>${idAtend}</td>
            <td>${nomePaciente}</td>
            <td>${unidadeAtend}</td>
            <td>${dataAtend}</td>
            <td>${tipoPacote}</td>
            <td>${dataInicioPacote}</td>
            <td><span class="badge-custom ${badgeClass}">${statusAtend}</span></td>
            <td>
                ${podeExcluir ? `<button class="btn btn-sm btn-outline text-danger" onclick="excluirAtendimento('${idAtend}')"><i class="bi bi-trash"></i></button>` : ''}
            </td>
        </tr>`;
    });
    document.getElementById('agendaTbody').innerHTML = html || '<tr><td colspan="8" class="text-center py-3 text-muted">Nenhum atendimento encontrado</td></tr>';
}

async function excluirAtendimento(id) {
    if (confirm('Excluir atendimento?')) {
        const success = await excluirAtendimentoAPI(id);
        if (success) {
            renderAgenda();
        }
    }
}

function limparFiltrosAgenda() {
    document.getElementById('filtroNome').value = '';
    document.getElementById('filtroPacote').value = '';
    document.getElementById('filtroStatus').value = '';
    document.getElementById('filtroUnidade').value = '';
    renderAgenda();
}

document.getElementById('atendimentoForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    let pacienteId = document.getElementById('atendPaciente').value;
    let pacienteNome = document.getElementById('atendPaciente').options[document.getElementById('atendPaciente').selectedIndex]?.text || '';
    let dataAtendimento = document.getElementById('atendData').value;
    let tipoPacote = document.getElementById('atendPacoteTipo').value;
    let dataInicioPacote = document.getElementById('atendInicioPacote').value;
    let status = document.getElementById('atendStatus').value;
    let unidade = document.getElementById('atendUnidade').value;
    
    // Validações
    if (!pacienteId) {
        mostrarToast('Selecione um paciente', 'danger');
        return;
    }
    if (!dataAtendimento) {
        mostrarToast('Informe a data do atendimento', 'danger');
        return;
    }
    if (!dataInicioPacote) {
        mostrarToast('Informe a data de início do pacote', 'danger');
        return;
    }
    
    let novo = {
        paciente_id: pacienteId,
        paciente_nome: pacienteNome,
        data_atendimento: formataDataISO(dataAtendimento),
        tipo_pacote: tipoPacote,
        data_inicio_pacote: formataDataISO(dataInicioPacote),
        status: status,
        unidade: unidade
    };
    
    const success = await salvarAtendimento(novo);
    if (success) {
        renderAgenda();
        e.target.reset();
        document.getElementById('atendData').value = new Date().toISOString().slice(0, 10);
        resetarInfoAtendimento();
    }
});

// ========== FINANCEIRO ==========
async function renderFinanceiro() {
    let mes = document.getElementById('finMesFiltro')?.value || new Date().toISOString().slice(0, 7);
    let clinicaFiltro = document.getElementById('finClinicaFiltro')?.value || '';
    
    await carregarFinanceiro(mes, clinicaFiltro);
    
    let html = '';
    let totalBruto = 0;
    let totalCusto = 0;
    let totalLiquido = 0;
    
    const podeEditar = userHasPermission('financeiro', 'editar');
    const podeExcluir = userHasPermission('financeiro', 'excluir');

    dados.financeiro.forEach(f => {
        let valor = parseFloat(f.valor) || 0;
        let despesa = parseFloat(f.despesa_automatica) || (valor * 0.25);
        let liquido = parseFloat(f.receita_disponivel) || (valor * 0.75);
        let dataFormatada = f.data || '';
        
        totalBruto += valor;
        totalCusto += despesa;
        totalLiquido += liquido;
        
        html += `<tr>
            <td>${dataFormatada}</td>
            <td>${f.paciente_nome || ''}</td>
            <td>${f.clinica || ''}</td>
            <td>R$ ${valor.toFixed(2)}</td>
            <td>R$ ${despesa.toFixed(2)}</td>
            <td>R$ ${liquido.toFixed(2)}</td>
            <td>${f.forma_pagamento || ''}</td>
            <td>${f.nf_emitida == 1 || f.nf_emitida === true ? 'Sim' : 'Não'}</td>
            <td class="text-center">
                ${podeEditar ? `<button class="btn btn-sm btn-outline" onclick="editarFinanceiro('${f.id}')" title="Editar"><i class="bi bi-pencil"></i></button>` : ''}
                ${podeExcluir ? `<button class="btn btn-sm btn-outline text-danger" onclick="excluirFinanceiro('${f.id}')" title="Excluir"><i class="bi bi-trash"></i></button>` : ''}
            </td>
        </tr>`;
    });
    
    document.getElementById('finTbody').innerHTML = html || '<tr><td colspan="9" class="text-center py-3 text-muted">Nenhum lançamento encontrado</td></tr>';
    
    document.getElementById('finTotalBruto').innerHTML = `R$ ${totalBruto.toFixed(2)}`;
    document.getElementById('finCusto').innerHTML = `R$ ${totalCusto.toFixed(2)}`;
    document.getElementById('finLiquido').innerHTML = `R$ ${totalLiquido.toFixed(2)}`;
}

async function editarFinanceiro(id) {
    const lancamento = dados.financeiro.find(f => f.id === id);
    if (!lancamento) return;
    
    document.getElementById('finPaciente').value = lancamento.paciente_id || '';
    document.getElementById('finClinica').value = lancamento.clinica || 'ANIMO';
    document.getElementById('finTipoPacote').value = lancamento.tipo_pacote || '';
    document.getElementById('finDataInicio').value = lancamento.data_inicio_pacote || '';
    document.getElementById('finData').value = lancamento.data || '';
    document.getElementById('finValor').value = lancamento.valor || 0;
    document.getElementById('finForma').value = lancamento.forma_pagamento || 'Pix';
    document.getElementById('finNf').checked = lancamento.nf_emitida == 1 || lancamento.nf_emitida === true;
    
    document.getElementById('finEditId').value = id;
    
    const submitBtn = document.querySelector('#financeiroForm button[type="submit"]');
    submitBtn.innerHTML = '<i class="bi bi-pencil me-2"></i>Atualizar Recebimento';
    
    document.getElementById('financeiro').scrollIntoView({ behavior: 'smooth' });
    
    mostrarToast('Dados carregados para edição', 'info');
}

async function excluirFinanceiro(id) {
    if (confirm('Excluir lançamento?')) {
        const success = await excluirFinanceiroAPI(id);
        if (success) {
            renderFinanceiro();
        }
    }
}

document.getElementById('financeiroForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    let editId = document.getElementById('finEditId').value;
    let pacienteId = document.getElementById('finPaciente').value;
    let pacienteNome = document.getElementById('finPaciente').options[document.getElementById('finPaciente').selectedIndex]?.text || '';
    
    let lancamento = {
        paciente_id: pacienteId,
        paciente_nome: pacienteNome,
        clinica: document.getElementById('finClinica').value,
        tipo_pacote: document.getElementById('finTipoPacote').value,
        data_inicio_pacote: document.getElementById('finDataInicio').value,
        data: document.getElementById('finData').value,
        valor: parseFloat(document.getElementById('finValor').value),
        forma_pagamento: document.getElementById('finForma').value,
        nf_emitida: document.getElementById('finNf').checked
    };
    
    let success;
    if (editId) {
        lancamento.id = editId;
        success = await atualizarFinanceiro(lancamento);
        if (success) {
            document.getElementById('finEditId').value = '';
            const submitBtn = document.querySelector('#financeiroForm button[type="submit"]');
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Registrar Recebimento';
        }
    } else {
        success = await salvarFinanceiro(lancamento);
    }
    
    if (success) {
        renderFinanceiro();
        e.target.reset();
        document.getElementById('finData').value = new Date().toISOString().slice(0, 10);
    }
});

async function atualizarFinanceiro(lancamento) {
    try {
        const result = await apiRequest('financeiro.php', 'PUT', lancamento);
        if (result.success) {
            mostrarToast('Recebimento atualizado com sucesso');
            await carregarFinanceiro();
            return true;
        }
    } catch (error) {
        return false;
    }
}

// ========== DESPESAS ==========
function renderDespesas() {
    let html = '';
    
    const podeEditar = userHasPermission('despesas', 'editar');
    const podeExcluir = userHasPermission('despesas', 'excluir');

    dados.despesas.forEach(d => {
        let status = d.parcelas_pagas >= d.num_parcelas ? 'Paga' : (d.parcelas_pagas > 0 ? 'Parcial' : 'Pendente');
        let valor = d.valor_total || 0;
        let numParcelas = d.num_parcelas || 1;
        
        let badgeClass = status === 'Paga' ? 'badge-success' : (status === 'Parcial' ? 'badge-warning' : 'badge-danger');
        
        html += `<tr>
            <td>${d.descricao || ''}</td>
            <td>${d.categoria || ''}</td>
            <td>R$ ${valor.toFixed(2)}</td>
            <td>${d.parcelas_pagas}/${numParcelas}</td>
            <td><span class="badge-custom ${badgeClass}">${status}</span></td>
            <td class="text-center">
                ${podeEditar ? `
                    <button class="btn btn-sm btn-outline" onclick="editarDespesa('${d.id}')" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="pagarParcela('${d.id}')" ${status === 'Paga' ? 'disabled' : ''} title="Pagar parcela">
                        <i class="bi bi-check2"></i>
                    </button>
                ` : ''}
                ${podeExcluir ? `
                    <button class="btn btn-sm btn-outline text-danger" onclick="excluirDespesa('${d.id}')" title="Excluir">
                        <i class="bi bi-trash"></i>
                    </button>
                ` : ''}
            </td>
        </tr>`;
    });
    document.getElementById('despesasTbody').innerHTML = html || '<tr><td colspan="6" class="text-center py-3 text-muted">Nenhuma despesa cadastrada</td></tr>';
}

async function editarDespesa(id) {
    const despesa = dados.despesas.find(d => d.id === id);
    if (!despesa) return;
    
    document.getElementById('despDesc').value = despesa.descricao || '';
    document.getElementById('despCat').value = despesa.categoria || 'Fixa';
    document.getElementById('despValor').value = despesa.valor_total || 0;
    document.getElementById('despParcelas').value = despesa.num_parcelas || 1;
    document.getElementById('despPagas').value = despesa.parcelas_pagas || 0;
    document.getElementById('despDiaVenc').value = despesa.dia_vencimento || '';
    document.getElementById('despDataInicio').value = despesa.data_inicio || '';
    
    document.getElementById('despesaEditId').value = id;
    
    const submitBtn = document.querySelector('#despesaForm button[type="submit"]');
    submitBtn.innerHTML = '<i class="bi bi-pencil me-2"></i>Atualizar Despesa';
    
    document.getElementById('despesas').scrollIntoView({ behavior: 'smooth' });
    
    mostrarToast('Dados carregados para edição', 'info');
}

async function pagarParcela(id) {
    const success = await pagarParcelaAPI(id);
    if (success) {
        renderDespesas();
    }
}

async function excluirDespesa(id) {
    if (confirm('Excluir despesa?')) {
        const success = await excluirDespesaAPI(id);
        if (success) {
            renderDespesas();
        }
    }
}

document.getElementById('despesaForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    let editId = document.getElementById('despesaEditId').value;
    
    let despesa = {
        descricao: document.getElementById('despDesc').value,
        categoria: document.getElementById('despCat').value,
        valor_total: parseFloat(document.getElementById('despValor').value),
        num_parcelas: parseInt(document.getElementById('despParcelas').value),
        parcelas_pagas: parseInt(document.getElementById('despPagas').value),
        dia_vencimento: parseInt(document.getElementById('despDiaVenc').value) || null,
        data_inicio: document.getElementById('despDataInicio').value || null
    };
    
    let success;
    if (editId) {
        despesa.id = editId;
        success = await atualizarDespesa(despesa);
        if (success) {
            document.getElementById('despesaEditId').value = '';
            const submitBtn = document.querySelector('#despesaForm button[type="submit"]');
            submitBtn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Salvar Despesa';
        }
    } else {
        success = await salvarDespesa(despesa);
    }
    
    if (success) {
        renderDespesas();
        e.target.reset();
        document.getElementById('despParcelas').value = 1;
        document.getElementById('despPagas').value = 0;
    }
});

async function atualizarDespesa(despesa) {
    try {
        const result = await apiRequest('despesas.php', 'PUT', despesa);
        if (result.success) {
            mostrarToast('Despesa atualizada com sucesso');
            await carregarDespesas();
            return true;
        }
    } catch (error) {
        return false;
    }
}

// ====================== FUNÇÕES DE PACOTES E ATENDIMENTOS ======================
let pacienteInfoAtual = null;

async function buscarInfoCompletaPaciente(pacienteId) {
    if (!pacienteId) return null;
    
    try {
        const response = await fetch(`api/pacientes.php?id=${encodeURIComponent(pacienteId)}&completo=true`);
        const result = await response.json();
        
        if (result.success && result.data) {
            return result.data;
        }
        return null;
    } catch (error) {
        console.error('Erro ao buscar informações completas do paciente:', error);
        return null;
    }
}

async function preencherPacoteAutomatico(pacienteId) {
    if (!pacienteId) {
        resetarInfoAtendimento();
        return;
    }
    
    const pacienteInfo = await buscarInfoCompletaPaciente(pacienteId);
    pacienteInfoAtual = pacienteInfo;
    
    if (!pacienteInfo) {
        mostrarToast('Erro ao buscar informações do paciente', 'danger');
        resetarInfoAtendimento();
        return;
    }
    
    const tipoPacoteField = document.getElementById('atendPacoteTipo');
    const dataInicioField = document.getElementById('atendInicioPacote');
    const atendUnidadeField = document.getElementById('atendUnidade');
    const infoPacienteDiv = document.getElementById('infoPacienteSelecionado');
    
    const finTipoPacote = document.getElementById('finTipoPacote');
    const finDataInicio = document.getElementById('finDataInicio');
    
    // Prioridade 1: Pacote Ativo formally registered
    if (pacienteInfo.pacote) {
        const pacote = pacienteInfo.pacote;
        const dataInicioISO = formataDataISO(pacote.data_inicio);
        
        if (tipoPacoteField) tipoPacoteField.value = pacote.tipo_pacote;
        if (dataInicioField) dataInicioField.value = dataInicioISO;
        if (finTipoPacote) finTipoPacote.value = pacote.tipo_pacote;
        if (finDataInicio) finDataInicio.value = dataInicioISO;
        
        mostrarToast(`Pacote ${pacote.tipo_pacote} ativo encontrado!`, 'success');
    } 
    // Prioridade 2: Histórico do último atendimento (Solicitação do Usuário)
    else if (pacienteInfo.ultimo_atendimento) {
        const ultimo = pacienteInfo.ultimo_atendimento;
        const dataInicioISO = formataDataISO(ultimo.data_inicio_pacote);
        
        if (tipoPacoteField) tipoPacoteField.value = ultimo.tipo_pacote;
        if (dataInicioField) dataInicioField.value = dataInicioISO;
        if (atendUnidadeField) atendUnidadeField.value = ultimo.unidade || 'ESPAÇO GUANAIS';
        
        if (finTipoPacote) finTipoPacote.value = ultimo.tipo_pacote;
        if (finDataInicio) finDataInicio.value = dataInicioISO;
        
        mostrarToast(`Histórico encontrado: Pacote ${ultimo.tipo_pacote} (Início: ${ultimo.data_inicio_pacote})`, 'info');
    } else {
        if (tipoPacoteField) tipoPacoteField.value = 'Avulso';
        if (dataInicioField) dataInicioField.value = '';
        if (finTipoPacote) finTipoPacote.value = 'Avulso';
        if (finDataInicio) finDataInicio.value = '';
    }

    // Atualizar visualização do resumo
    if (infoPacienteDiv) {
        const totalAtendimentos = pacienteInfo.total_atendimentos || 0;
        const totalFaltas = pacienteInfo.total_faltas || 0;
        const ultimoAtend = pacienteInfo.ultimo_atendimento;
        
        let htmlInfo = `
            <div class="alert alert-info mb-0">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-person-circle me-2 text-primary"></i>
                            <div>
                                <strong>Paciente:</strong><br>
                                <span class="text-muted">${pacienteInfo.nome}</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-clock-history me-2 text-muted"></i>
                            <span class="text-muted">Último: ${ultimoAtend ? ultimoAtend.data_atendimento : 'Nunca'}</span>
                        </div>
                    </div>`;

        if (pacienteInfo.pacote) {
            const pacote = pacienteInfo.pacote;
            const sessoesRealizadas = pacote.sessoes_realizadas || 0;
            const sessoesRestantes = pacote.sessoes_restantes || 0;
            
            htmlInfo += `
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-box-seam me-2 text-success"></i>
                            <div>
                                <strong>Pacote ${pacote.tipo_pacote} (Ativo)</strong><br>
                                <span class="text-muted">Início: ${pacote.data_inicio}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-2 bg-white rounded shadow-sm text-center">
                                    <small class="text-muted d-block">Sessões</small>
                                    <strong class="text-success">${sessoesRealizadas}/${pacote.sessoes_estimadas || 0}</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-white rounded shadow-sm text-center">
                                    <small class="text-muted d-block">Restantes</small>
                                    <strong class="text-warning">${sessoesRestantes}</strong>
                                </div>
                            </div>
                        </div>
                    </div>`;
        } else if (ultimoAtend) {
            htmlInfo += `
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-arrow-repeat me-2 text-info"></i>
                            <div>
                                <strong>Último Pacote: ${ultimoAtend.tipo_pacote}</strong><br>
                                <span class="text-muted">Início Ref: ${ultimoAtend.data_inicio_pacote}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-2 bg-white rounded shadow-sm text-center">
                                    <small class="text-muted d-block">Total Atend.</small>
                                    <strong class="text-primary">${totalAtendimentos}</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-white rounded shadow-sm text-center">
                                    <small class="text-muted d-block">Faltas</small>
                                    <strong class="text-danger">${totalFaltas}</strong>
                                </div>
                            </div>
                        </div>
                    </div>`;
        } else {
            htmlInfo += `
                    <div class="col-md-8 text-center py-2">
                        <em class="text-muted">Nenhum histórico de pacotes ou atendimentos encontrado.</em>
                    </div>`;
        }
        
        htmlInfo += `</div></div>`;
        infoPacienteDiv.innerHTML = htmlInfo;
    }
}

function resetarInfoAtendimento() {
    const infoPacienteDiv = document.getElementById('infoPacienteSelecionado');
    if (infoPacienteDiv) {
        infoPacienteDiv.innerHTML = '';
    }
    pacienteInfoAtual = null;
}

// Listeners para preencher automaticamente quando selecionar paciente
document.addEventListener('DOMContentLoaded', function() {
    const atendData = document.getElementById('atendData');
    if (atendData) {
        atendData.value = new Date().toISOString().slice(0, 10);
    }
    
    const atendPaciente = document.getElementById('atendPaciente');
    const finPaciente = document.getElementById('finPaciente');
    
    if (atendPaciente) {
        atendPaciente.addEventListener('change', function() {
            if (this.value) preencherPacoteAutomatico(this.value);
            else resetarInfoAtendimento();
        });
    }
    
    if (finPaciente) {
        finPaciente.addEventListener('change', function() {
            if (this.value) preencherPacoteAutomatico(this.value);
        });
    }
});

// ====================== EXPORTAR / IMPORTAR ======================
function exportarExcel() {
    if (typeof XLSX === 'undefined') {
        mostrarToast('Erro ao exportar: Biblioteca XLSX não encontrada', 'danger');
        return;
    }
    
    // Criar um novo workbook
    let wb = XLSX.utils.book_new();
    
    // 1. Aba de Atendimentos/Agenda
    if (dados.atendimentos && dados.atendimentos.length) {
        let agendaData = dados.atendimentos.map(a => ({
            'ID': a.id_atendimento,
            'Paciente ID': a.paciente_id,
            'Paciente': a.paciente_nome,
            'Data': a.data_atendimento,
            'Tipo Pacote': a.tipo_pacote,
            'Início Pacote': a.data_inicio_pacote,
            'Status': a.status,
            'Unidade': a.unidade,
            'Observação': a.observacoes || ''
        }));
        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(agendaData), 'Agenda');
    }
    
    // 2. Aba de Pacientes
    if (dados.pacientes && dados.pacientes.length) {
        let pacientesData = dados.pacientes.map(p => ({
            'ID': p.id,
            'Nome': p.nome,
            'CPF': p.cpf || '',
            'Nascimento': p.data_nascimento,
            'Telefone': p.telefone,
            'Email': p.email || '',
            'Endereço': p.endereco || '',
            'Responsável': p.responsavel_nome || '',
            'Tel Responsável': p.responsavel_telefone || '',
            'Ativo': p.ativo ? 'Sim' : 'Não'
        }));
        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(pacientesData), 'Pacientes');
    }
    
    // 3. Aba Financeiro
    if (dados.financeiro && dados.financeiro.length) {
        let financeiroData = dados.financeiro.map(f => ({
            'ID': f.id,
            'Paciente ID': f.paciente_id,
            'Paciente': f.paciente_nome,
            'Clínica': f.clinica,
            'Tipo Pacote': f.tipo_pacote || '',
            'Início Pacote': f.data_inicio_pacote || '',
            'Data': f.data,
            'Valor Bruto': parseFloat(f.valor) || 0,
            'Forma Pagamento': f.forma_pagamento,
            'NF Emitida': f.nf_emitida == 1 ? 'Sim' : 'Não',
            'Observação': f.observacoes || ''
        }));
        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(financeiroData), 'Financeiro');
    }
    
    // 4. Aba Despesas
    if (dados.despesas && dados.despesas.length) {
        let despesasData = dados.despesas.map(d => ({
            'ID': d.id,
            'Descrição': d.descricao,
            'Categoria': d.categoria,
            'Valor Total': parseFloat(d.valor_total) || 0,
            'Num Parcelas': d.num_parcelas,
            'Parcelas Pagas': d.parcelas_pagas,
            'Dia Vencimento': d.dia_vencimento || '',
            'Data Início': d.data_inicio || ''
        }));
        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(despesasData), 'Despesas');
    }
    
    // Gerar o arquivo
    const dataHora = new Date().toISOString().replace(/T/, '_').replace(/:/g, '-').slice(0, 19);
    XLSX.writeFile(wb, `Backup_Espaco_Guanais_${dataHora}.xlsx`);
    
    mostrarToast('Backup exportado com sucesso');
}

// Funções de Normalização para Importação
function normalizarClinica(valor) {
    if (!valor) return 'ANIMO';
    const v = valor.toString().toUpperCase().trim();
    if (v.includes('ANIMO')) return 'ANIMO';
    if (v.includes('GUANAIS')) return 'ESPAÇO GUANAIS';
    return 'ANIMO';
}

function normalizarFormaPagamento(valor) {
    if (!valor) return 'Pix';
    const v = valor.toString().toLowerCase().trim();
    if (v.includes('pix')) return 'Pix';
    if (v.includes('dinheiro')) return 'Dinheiro';
    if (v.includes('crédito') || v.includes('credito')) return 'Cartão Crédito';
    if (v.includes('débito') || v.includes('debito')) return 'Cartão Débito';
    return 'Pix';
}

function normalizarStatusAtendimento(valor) {
    if (!valor) return 'Confirmado';
    const v = valor.toString().toLowerCase().trim();
    if (v.includes('confirmado')) return 'Confirmado';
    if (v.includes('falta')) return 'Falta';
    if (v.includes('reagendado')) return 'Reagendado';
    if (v.includes('exceção') || v.includes('excecao')) return 'Exceção Justificada';
    return 'Confirmado';
}

// Função para importar dados e salvar no banco de dados
async function importarExcel(event, restaurar) {
    let file = event.target.files[0];
    if (!file) return;
    if (restaurar && !confirm('Restaurar apagará dados atuais. Continuar?')) return;
    
    let reader = new FileReader();
    reader.onload = async function(e) {
        let wb = XLSX.read(e.target.result, { type: 'array' });
        let novosPacientes = [], novosAtendimentos = [], novosFinanceiro = [], novosDespesas = [];
        
        wb.SheetNames.forEach(nome => {
            let sheet = wb.Sheets[nome];
            let data = XLSX.utils.sheet_to_json(sheet);
            
            if (nome.toLowerCase().includes('paciente')) {
                novosPacientes = data.map(p => ({
                    id: p['ID'] || p['id'] || gerarId('P'),
                    nome: p['Nome'] || p['nome'] || '',
                    cpf: p['CPF'] || p['cpf'] || '',
                    data_nascimento: converterDataBR(p['Nascimento'] || p['data_nascimento'] || p['data_nasc'] || ''),
                    telefone: p['Telefone'] || p['telefone'] || '',
                    email: p['Email'] || p['email'] || '',
                    endereco: p['Endereço'] || p['endereco'] || '',
                    ativo: true
                }));
            }
            else if (nome.toLowerCase().includes('agenda')) {
                novosAtendimentos = data.map(a => ({
                    id_atendimento: a['ID'] || a['id'] || gerarId('A'),
                    paciente_id: a['Paciente ID'] || a['paciente_id'] || '',
                    paciente_nome: a['Paciente'] || a['paciente_nome'] || '',
                    data_atendimento: converterDataBR(a['Data'] || a['data_atendimento'] || ''),
                    tipo_pacote: a['Tipo Pacote'] || a['tipo_pacote'] || 'Avulso',
                    data_inicio_pacote: converterDataBR(a['Início Pacote'] || a['data_inicio_pacote'] || a['Data'] || ''),
                    status: normalizarStatusAtendimento(a['Status'] || a['status']),
                    unidade: normalizarClinica(a['Unidade'] || a['unidade']),
                    observacoes: a['Observação'] || a['observacoes'] || ''
                }));
            }
            else if (nome.toLowerCase().includes('financeiro')) {
                novosFinanceiro = data.map(f => ({
                    paciente_id: f['Paciente ID'] || f['paciente_id'] || '',
                    paciente_nome: f['Paciente'] || f['paciente_nome'] || '',
                    clinica: normalizarClinica(f['Clínica'] || f['clinica']),
                    tipo_pacote: f['Tipo Pacote'] || f['tipo_pacote'] || '',
                    data: converterDataBR(f['Data'] || f['data'] || ''),
                    valor: parseFloat(f['Valor Bruto'] || f['valor'] || f['Valor'] || 0),
                    forma_pagamento: normalizarFormaPagamento(f['Forma Pagamento'] || f['forma_pagamento']),
                    nf_emitida: (f['NF Emitida'] === 'Sim' || f['nf_emitida'] == 1) ? 1 : 0,
                    observacoes: f['Observação'] || f['observacoes'] || '',
                    data_inicio_pacote: converterDataBR(f['Início Pacote'] || f['data_inicio_pacote'] || f['Data'] || '')
                }));
            }
            // ... resto igual ...
            else if (nome.toLowerCase().includes('despesa')) {
                novosDespesas = data.map(d => ({
                    id: 'desp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
                    descricao: d['Descrição'] || d['descricao'] || '',
                    categoria: d['Categoria'] || d['categoria'] || 'Fixa',
                    valor_total: parseFloat(d['Valor Total'] || d['valor_total'] || 0),
                    num_parcelas: parseInt(d['Num Parcelas'] || d['num_parcelas'] || 1),
                    parcelas_pagas: parseInt(d['Parcelas Pagas'] || d['parcelas_pagas'] || 0),
                    dia_vencimento: parseInt(d['Dia Vencimento'] || d['dia_vencimento'] || null),
                    data_inicio: converterDataBR(d['Data Início'] || d['data_inicio'] || '')
                }));
            }
        });
        
        try {
            // 1. Criar Mapa de Pacientes (Nome -> ID)
            const pacienteMapa = new Map();
            
            // Salvar pacientes no banco
            for (const paciente of novosPacientes) {
                try {
                    const result = await apiRequest('pacientes.php', 'POST', paciente);
                    if (result.success) {
                        pacienteMapa.set(paciente.nome, result.data.id);
                    }
                } catch (err) {
                    // Se falhar (ex: CPF duplicado), tentar buscar o ID existente
                    if (err.message.includes('já cadastrado')) {
                        // O ID já deve estar no 'paciente' se for um backup completo
                        pacienteMapa.set(paciente.nome, paciente.id);
                    }
                }
            }
            
            // 2. Corrigir IDs nos Atendimentos e Salvar
            for (const atendimento of novosAtendimentos) {
                if (!atendimento.paciente_id && pacienteMapa.has(atendimento.paciente_nome)) {
                    atendimento.paciente_id = pacienteMapa.get(atendimento.paciente_nome);
                }
                if (atendimento.paciente_id) {
                    await apiRequest('atendimentos.php', 'POST', atendimento);
                }
            }
            
            // 3. Corrigir IDs no Financeiro e Salvar
            for (const lancamento of novosFinanceiro) {
                if (!lancamento.paciente_id && pacienteMapa.has(lancamento.paciente_nome)) {
                    lancamento.paciente_id = pacienteMapa.get(lancamento.paciente_nome);
                }
                if (lancamento.paciente_id) {
                    await apiRequest('financeiro.php', 'POST', lancamento);
                }
            }
            
            // 4. Salvar despesas
            for (const despesa of novosDespesas) {
                await apiRequest('despesas.php', 'POST', despesa);
            }
            
            // Recarregar todos os dados
            await inicializarSistema();
            
            const totalRegistros = novosPacientes.length + novosAtendimentos.length + novosFinanceiro.length + novosDespesas.length;
            mostrarToast(`Importação concluída com ${totalRegistros} registros salvos no banco de dados`);
        } catch (error) {
            console.error('Erro na importação:', error);
            mostrarToast('Erro ao importar dados: ' + error.message, 'danger');
        }
    };
    reader.readAsArrayBuffer(file);
    event.target.value = '';
}

function converterDataBR(data) {
    if (!data) return '';
    if (data.includes('-') && data.split('-')[0].length === 4) return data;
    if (data.includes('/')) {
        let partes = data.split('/');
        if (partes.length === 3) {
            return `${partes[2]}-${partes[1]}-${partes[0]}`;
        }
    }
    return data;
}

// ========== TEMA ESCURO/CLARO ==========
function toggleTheme() {
    const body = document.body;
    const isDark = body.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateThemeIcons();
}

function updateThemeIcons() {
    const isDark = document.body.classList.contains('dark');
    const sunIcon = document.querySelector('#themeToggle .bi-sun-fill');
    const moonIcon = document.querySelector('#themeToggle .bi-moon-fill');
    
    if (sunIcon) sunIcon.style.display = isDark ? 'none' : 'inline';
    if (moonIcon) moonIcon.style.display = isDark ? 'inline' : 'none';
}

if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark');
}
updateThemeIcons();

const themeToggle = document.getElementById('themeToggle');
if (themeToggle) {
    themeToggle.addEventListener('click', toggleTheme);
}

// ========== LOGIN ==========
async function sairSistema() {
    await logout();
    document.getElementById('appScreen').style.display = 'none';
    document.getElementById('loginScreen').style.display = 'flex';
    sessionStorage.clear();
}

document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    let user = document.getElementById('loginUser').value;
    let pass = document.getElementById('loginPass').value;
    
        const success = await login(user, pass);
    if (success) {
        document.getElementById('loginScreen').style.display = 'none';
        document.getElementById('moduleSelectionScreen').style.display = 'flex';
        document.getElementById('moduleSelectionUserName').textContent = usuarioLogado.nome.split(' ')[0];
        aplicarPermissoesUI(); // Aplicar permissões aos cards da tela de seleção
        mostrarToast('Bem-vindo ao sistema!');
    } else {
        mostrarToast('Usuário ou senha inválidos', 'danger');
    }
});

async function verificarLoginSalvo() {
    if (sessionStorage.getItem('logged') === 'true') {
        const authValid = await verificarAuth();
        if (authValid) {
            document.getElementById('loginScreen').style.display = 'none';
            document.getElementById('moduleSelectionScreen').style.display = 'flex';
            document.getElementById('moduleSelectionUserName').textContent = usuarioLogado.nome.split(' ')[0];
            aplicarPermissoesUI(); // Aplicar permissões aos cards da tela de seleção
        } else {
            sessionStorage.clear();
        }
    }
}

verificarLoginSalvo();

async function sairSistema() {
    await logout();
    document.getElementById('appScreen').style.display = 'none';
    document.getElementById('moduleSelectionScreen').style.display = 'none'; // Ocultar tela de seleção
    document.getElementById('loginScreen').style.display = 'flex';
    sessionStorage.clear();
}

// ====================== LISTENERS DAS ABAS ======================
function initTabListeners() {
    const pacientesTab = document.querySelector('[data-bs-target="#pacientes"]');
    if (pacientesTab) {
        pacientesTab.addEventListener('shown.bs.tab', function() {
            renderPacientes();
        });
    }
    
    const agendaTab = document.querySelector('[data-bs-target="#agenda"]');
    if (agendaTab) {
        agendaTab.addEventListener('shown.bs.tab', function() {
            renderAgenda();
        });
    }
    
    const financeiroTab = document.querySelector('[data-bs-target="#financeiro"]');
    if (financeiroTab) {
        financeiroTab.addEventListener('shown.bs.tab', function() {
            renderFinanceiro();
        });
    }
    
    const despesasTab = document.querySelector('[data-bs-target="#despesas"]');
    if (despesasTab) {
        despesasTab.addEventListener('shown.bs.tab', function() {
            renderDespesas();
        });
    }
    
    const relatoriosTab = document.querySelector('[data-bs-target="#relatorios"]');
    if (relatoriosTab) {
        relatoriosTab.addEventListener('shown.bs.tab', function() {
            atualizarSelectPacientes();
        });
    }
}

// ====================== RELATÓRIOS ======================
async function carregarRelatorioAtendimentos() {
    try {
        const mes = document.getElementById('relatorioMes')?.value || '';
        const pacienteId = document.getElementById('relatorioPaciente')?.value || '';
        
        const params = new URLSearchParams();
        if (mes) params.append('mes', mes);
        if (pacienteId) params.append('paciente_id', pacienteId);
        
        const response = await fetch(`api/atendimentos.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            const tbody = document.querySelector('#relatorioAtendimentosTable tbody');
            if (tbody) {
                if (data.atendimentos.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Nenhum atendimento encontrado</td></tr>';
                } else {
                    tbody.innerHTML = '';
                    data.atendimentos.forEach(atendimento => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${atendimento.paciente_nome}</td>
                            <td>${formataDataBR(atendimento.data_atendimento)}</td>
                            <td>${atendimento.tipo_pacote}</td>
                            <td>${formataDataBR(atendimento.data_inicio_pacote)}</td>
                            <td>${atendimento.status}</td>
                            <td>${atendimento.unidade}</td>
                            <td>${atendimento.observacoes || '-'}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            }
            
            const totalEl = document.getElementById('totalAtendimentos');
            const faltasEl = document.getElementById('totalFaltas');
            if (totalEl) totalEl.textContent = data.resumo.total_atendimentos || 0;
            if (faltasEl) faltasEl.textContent = data.resumo.total_faltas || 0;
        }
    } catch (error) {
        console.error('Erro ao carregar relatório de atendimentos:', error);
    }
}

async function carregarRelatorioFinanceiro() {
    try {
        const mes = document.getElementById('relatorioMesFinanceiro')?.value || document.getElementById('relatorioMes')?.value || '';
        const clinica = document.getElementById('relatorioClinica')?.value || '';
        
        let url = 'api/financeiro.php?';
        if (mes) url += 'mes=' + encodeURIComponent(mes) + '&';
        if (clinica) url += 'clinica=' + encodeURIComponent(clinica);
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success && data.data) {
            const lancamentos = data.data.lancamentos || [];
            const resumo = data.data.resumo || null;
            
            const tbody = document.querySelector('#relatorioFinanceiroTable tbody');
            if (tbody) {
                if (lancamentos.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-3 text-muted">Nenhum lançamento encontrado</td></tr>';
                } else {
                    tbody.innerHTML = '';
                    lancamentos.forEach(lancamento => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${lancamento.paciente_nome || ''}</td>
                            <td>${lancamento.data || ''}</td>
                            <td>${lancamento.clinica || ''}</td>
                            <td>${lancamento.tipo_pacote || '-'}</td>
                            <td>R$ ${(parseFloat(lancamento.valor) || 0).toFixed(2)}</td>
                            <td>R$ ${(parseFloat(lancamento.despesa_automatica) || 0).toFixed(2)}</td>
                            <td>R$ ${(parseFloat(lancamento.receita_disponivel) || 0).toFixed(2)}</td>
                            <td>${lancamento.forma_pagamento || ''}</td>
                            <td>${lancamento.nf_emitida == 1 || lancamento.nf_emitida === true ? 'Sim' : 'Não'}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            }
            
            const totalBrutoEl = document.getElementById('relatorioTotalBruto');
            const totalDespesasEl = document.getElementById('relatorioTotalDespesas');
            const totalDisponivelEl = document.getElementById('relatorioTotalDisponivel');
            
            if (resumo) {
                if (totalBrutoEl) totalBrutoEl.textContent = `R$ ${(resumo.total_bruto || 0).toFixed(2)}`;
                if (totalDespesasEl) totalDespesasEl.textContent = `R$ ${(resumo.custo || 0).toFixed(2)}`;
                if (totalDisponivelEl) totalDisponivelEl.textContent = `R$ ${(resumo.liquido || 0).toFixed(2)}`;
            }
        }
    } catch (error) {
        console.error('Erro ao carregar relatório financeiro:', error);
    }
}

async function carregarRelatorioPacientes() {
    try {
        const response = await fetch('api/pacientes.php');
        const data = await response.json();
        
        if (data.success) {
            const tbody = document.querySelector('#relatorioPacientesTable tbody');
            if (tbody) {
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Nenhum paciente encontrado</td></tr>';
                } else {
                    tbody.innerHTML = '';
                    data.data.forEach(paciente => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${paciente.id}</td>
                            <td>${paciente.nome}</td>
                            <td>${paciente.telefone}</td>
                            <td>${paciente.email || '-'}</td>
                            <td>${paciente.ativo ? 'Ativo' : 'Inativo'}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            }
        }
    } catch (error) {
        console.error('Erro ao carregar relatório de pacientes:', error);
    }
}

async function gerarRelatorios() {
    await carregarRelatorioAtendimentos();
    await carregarRelatorioFinanceiro();
    await carregarRelatorioPacientes();
    mostrarToast('Relatórios gerados com sucesso');
}

function exportarRelatorioCSV() {
    exportarRelatorio('atendimentos');
    exportarRelatorio('financeiro');
    exportarRelatorio('pacientes');
}

function exportarRelatorio(tipo) {
    let dadosExport = [];
    let nomeArquivo = '';

    if (tipo === 'atendimentos') {
        dadosExport = dados.atendimentos.map(a => ({
            Paciente: a.paciente_nome,
            Data: a.data_atendimento,
            TipoPacote: a.tipo_pacote,
            Status: a.status,
            Unidade: a.unidade
        }));
        nomeArquivo = `relatorio_atendimentos_${new Date().toISOString().slice(0, 10)}.csv`;
    } else if (tipo === 'financeiro') {
        dadosExport = dados.financeiro.map(f => ({
            Paciente: f.paciente_nome,
            Data: f.data,
            Clinica: f.clinica,
            Valor: f.valor,
            Despesa: f.despesa_automatica,
            Disponivel: f.receita_disponivel
        }));
        nomeArquivo = `relatorio_financeiro_${new Date().toISOString().slice(0, 10)}.csv`;
    } else if (tipo === 'pacientes') {
        dadosExport = dados.pacientes.map(p => ({
            ID: p.id,
            Nome: p.nome,
            Telefone: p.telefone,
            Email: p.email,
            Ativo: p.ativo ? 'Sim' : 'Não'
        }));
        nomeArquivo = `relatorio_pacientes_${new Date().toISOString().slice(0, 10)}.csv`;
    }

    if (dadosExport.length === 0) {
        mostrarToast('Não há dados para exportar', 'danger');
        return;
    }

    // Usar ponto e vírgula como delimitador e adicionar BOM para o Excel identificar como UTF-8
    const BOM = '\uFEFF';
    let csv = BOM + Object.keys(dadosExport[0]).join(';') + '\n';
    dadosExport.forEach(d => {
        csv += Object.values(d).map(v => {
            if (v === null || v === undefined) return '""';
            let str = String(v).replace(/"/g, '""'); // Escapar aspas
            return `"${str}"`;
        }).join(';') + '\n';
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', nomeArquivo);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    mostrarToast('Relatório exportado com sucesso');
}
// ==================== INICIALIZAÇÃO ====================
document.addEventListener('DOMContentLoaded', function() {
    initUsuariosTab();
    initTabListeners();
    
    // Verificar se está logado e inicializar sistema
    verificarLoginSalvo();
});
