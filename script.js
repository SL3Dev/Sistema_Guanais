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
        return result.data && result.data.logged;
    } catch (error) {
        return false;
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
            dados.atendimentos = result.data || [];
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

// ====================== FUNÇÕES AUXILIARES ======================
function mostrarToast(msg, tipo = 'success') {
    let toastDiv = document.createElement('div');
    toastDiv.className = 'toast-custom';
    toastDiv.innerHTML = `<i class="bi bi-${tipo === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'}"></i> ${msg}`;
    document.body.appendChild(toastDiv);
    setTimeout(() => toastDiv.remove(), 3000);
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

// ====================== INICIALIZAÇÃO ======================
async function inicializarSistema() {
    // Carregar todos os dados da API
    await Promise.all([
        carregarPacientes(),
        carregarAtendimentos(),
        carregarFinanceiro(),
        carregarDespesas()
    ]);
    
    renderPacientes();
    renderAgenda();
    renderFinanceiro();
    renderDespesas();
    atualizarSelectPacientes();
    
    // Setar valor padrão do mês atual
    const hoje = new Date();
    const mesAtual = hoje.toISOString().slice(0, 7);
    document.getElementById('finMesFiltro').value = mesAtual;
    document.getElementById('relatorioMes').value = mesAtual;
    
    document.getElementById('finMesFiltro').addEventListener('change', () => {
        carregarFinanceiro(document.getElementById('finMesFiltro').value, document.getElementById('finClinicaFiltro').value)
            .then(() => renderFinanceiro());
    });
    document.getElementById('finClinicaFiltro').addEventListener('change', () => {
        carregarFinanceiro(document.getElementById('finMesFiltro').value, document.getElementById('finClinicaFiltro').value)
            .then(() => renderFinanceiro());
    });
    
    document.getElementById('filtroNome').addEventListener('input', renderAgenda);
    document.getElementById('filtroPacote').addEventListener('change', renderAgenda);
    document.getElementById('filtroStatus').addEventListener('change', renderAgenda);
    document.getElementById('filtroUnidade').addEventListener('change', renderAgenda);
    document.getElementById('buscaPaciente').addEventListener('input', renderPacientes);
    
    document.getElementById('pacNasc').addEventListener('change', function() {
        let idade = calcularIdade(this.value);
        document.getElementById('respSec').classList.toggle('d-none', idade >= 18);
        document.getElementById('emergSec').classList.toggle('d-none', idade < 18);
    });
    
    aplicarRegraExcecao();
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
    filtered.forEach(p => {
        html += `<tr><td>${p.id}</td><td>${p.nome}</td><td>${p.telefone}</td><td>${calcularIdade(p.data_nascimento)}</td><td><button class="btn btn-sm btn-outline" onclick="editarPaciente('${p.id}')"><i class="bi bi-pencil"></i></button> <button class="btn btn-sm btn-outline text-danger" onclick="excluirPaciente('${p.id}')"><i class="bi bi-trash"></i></button></td></tr>`;
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
        document.getElementById('emergTel').value = p.emergencia_telefone;
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
    // A regra de exceção agora é aplicada no backend
    if (!dados.atendimentos.length) return;
    
    let hoje = new Date();
    dados.atendimentos.forEach(a => {
        if (a.status !== 'Confirmado' || a.tipoPacote === 'Avulso') return;
        let dataAtual = new Date(formataDataISO(a.dataAtendimento || a.data_atendimento));
        let dataInicio = new Date(formataDataISO(a.dataInicioPacote || a.data_inicio_pacote));
        let diff = (dataAtual - dataInicio) / 86400000;
        let limite = a.tipoPacote === 'Mensal' ? 35 : 20;
        // Apenas para referência visual
    });
}

function renderAgenda() {
    let nome = document.getElementById('filtroNome')?.value.toLowerCase() || '';
    let pacote = document.getElementById('filtroPacote')?.value || '';
    let status = document.getElementById('filtroStatus')?.value || '';
    let unidade = document.getElementById('filtroUnidade')?.value || '';
    
    let filtrados = dados.atendimentos.filter(a => {
        let nomePaciente = (a.nomePaciente || a.paciente_nome || '').toLowerCase();
        let tipoPacote = a.tipoPacote || a.tipo_pacote || '';
        let statusAtend = a.status || '';
        let unidadeAtend = a.unidade || '';
        
        return nomePaciente.includes(nome) &&
            (!pacote || tipoPacote === pacote) &&
            (!status || statusAtend === status) &&
            (!unidade || unidadeAtend === unidade);
    });
    
    // Ordenar por data decrescente
    filtrados.sort((a, b) => {
        let da = a.dataAtendimento || a.data_atendimento || '';
        let db = b.dataAtendimento || b.data_atendimento || '';
        return new Date(formataDataISO(db)) - new Date(formataDataISO(da));
    });
    
    let faltasMes = 0, excecoes = 0, hoje = new Date(), proxData = null, proxNome = '';
    filtrados.forEach(a => {
        let dataAtend = a.dataAtendimento || a.data_atendimento || '';
        let dt = new Date(formataDataISO(dataAtend));
        let statusAtend = a.status || '';
        let nomePaciente = a.nomePaciente || a.paciente_nome || '';
        
        if (statusAtend === 'Falta' && dt.getMonth() === hoje.getMonth() && dt.getFullYear() === hoje.getFullYear()) faltasMes++;
        if (statusAtend === 'Exceção Justificada' || statusAtend === 'Excecao Justificada') excecoes++;
        if (dt >= hoje && (!proxData || dt < proxData)) { proxData = dt; proxNome = nomePaciente; }
    });
    
    document.getElementById('dashFaltas').innerText = faltasMes;
    document.getElementById('dashExcecoes').innerText = excecoes;
    document.getElementById('dashProximo').innerHTML = proxData ? `${formataDataBR(proxData.toISOString().slice(0, 10))}<br><small>${proxNome}</small>` : '—';
    
    let html = '';
    filtrados.forEach(a => {
        let idAtend = a.idAtendimento || a.id_atendimento || '';
        let nomePaciente = a.nomePaciente || a.paciente_nome || '';
        let unidadeAtend = a.unidade || '';
        let dataAtend = a.dataAtendimento || a.data_atendimento || '';
        let tipoPacote = a.tipoPacote || a.tipo_pacote || '';
        let dataInicioPacote = a.dataInicioPacote || a.data_inicio_pacote || '';
        let statusAtend = a.status || '';
        
        let badgeClass = statusAtend === 'Confirmado' ? 'badge-success' :
            statusAtend === 'Falta' ? 'badge-danger' :
                statusAtend === 'Exceção Justificada' || statusAtend === 'Excecao Justificada' ? 'badge-warning' : 'badge-info';
        
        html += `<tr><td>${idAtend}</td><td>${nomePaciente}</td><td>${unidadeAtend}</td><td>${dataAtend}</td><td>${tipoPacote}</td><td>${dataInicioPacote}</td><td><span class="badge-custom ${badgeClass}">${statusAtend}</span></td><td><button class="btn btn-sm btn-outline" onclick="excluirAtendimento('${idAtend}')"><i class="bi bi-trash"></i></button></td></tr>`;
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
    
    let novo = {
        paciente_id: pacienteId,
        paciente_nome: pacienteNome,
        data_atendimento: formataDataISO(document.getElementById('atendData').value),
        tipo_pacote: document.getElementById('atendPacoteTipo').value,
        data_inicio_pacote: formataDataISO(document.getElementById('atendInicioPacote').value),
        status: document.getElementById('atendStatus').value,
        unidade: document.getElementById('atendUnidade').value
    };
    
    const success = await salvarAtendimento(novo);
    if (success) {
        renderAgenda();
        e.target.reset();
        // Manter data de hoje como padrão
        document.getElementById('atendData').value = new Date().toISOString().slice(0, 10);
    }
});

// ========== FINANCEIRO ==========
async function renderFinanceiro() {
    let mes = document.getElementById('finMesFiltro')?.value || new Date().toISOString().slice(0, 7);
    let clinicaFiltro = document.getElementById('finClinicaFiltro')?.value || '';
    
    let resumo = await carregarFinanceiro(mes, clinicaFiltro);
    
    let html = '';
    let totalBruto = 0;
    
    dados.financeiro.forEach(f => {
        let valor = f.valor || 0;
        let liquido = f.receita_disponivel || (valor * 0.75);
        let dataFormatada = formataDataBR(f.data);
        
        totalBruto += valor;
        
        html += `<tr><td>${dataFormatada}</td><td>${f.paciente_nome || f.pacienteNome || ''}</td><td>${f.clinica || ''}</td><td>R$ ${valor.toFixed(2)}</td><td>R$ ${liquido.toFixed(2)}</td><td>${f.forma_pagamento || f.formaPagamento || ''}</td><td>${f.nf_emitida || f.nfEmitida ? 'Sim' : 'Não'}</td><td><button class="btn btn-sm btn-outline" onclick="excluirFinanceiro('${f.id}')"><i class="bi bi-trash"></i></button></td></tr>`;
    });
    
    document.getElementById('finTbody').innerHTML = html || '<tr><td colspan="8" class="text-center py-3 text-muted">Nenhum lançamento encontrado</td></tr>';
    
    if (resumo) {
        document.getElementById('finTotalBruto').innerHTML = `R$ ${resumo.total_bruto.toFixed(2)}`;
        document.getElementById('finCusto').innerHTML = `R$ ${resumo.custo.toFixed(2)}`;
        document.getElementById('finLiquido').innerHTML = `R$ ${resumo.liquido.toFixed(2)}`;
    } else {
        let custo = totalBruto * 0.25;
        document.getElementById('finTotalBruto').innerHTML = `R$ ${totalBruto.toFixed(2)}`;
        document.getElementById('finCusto').innerHTML = `R$ ${custo.toFixed(2)}`;
        document.getElementById('finLiquido').innerHTML = `R$ ${(totalBruto - custo).toFixed(2)}`;
    }
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
    let pacienteId = document.getElementById('finPaciente').value;
    let pacienteNome = document.getElementById('finPaciente').options[document.getElementById('finPaciente').selectedIndex]?.text || '';
    
    let novo = {
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
    
    const success = await salvarFinanceiro(novo);
    if (success) {
        renderFinanceiro();
        e.target.reset();
        document.getElementById('finData').value = new Date().toISOString().slice(0, 10);
    }
});

// ========== DESPESAS ==========
function renderDespesas() {
    let html = '';
    dados.despesas.forEach(d => {
        let status = d.parcelas_pagas >= d.num_parcelas ? 'Paga' : (d.parcelas_pagas > 0 ? 'Parcial' : 'Pendente');
        let valor = d.valor_total || 0;
        let numParcelas = d.num_parcelas || 1;
        
        let badgeClass = status === 'Paga' ? 'badge-success' : (status === 'Parcial' ? 'badge-warning' : 'badge-danger');
        
        html += `<tr><td>${d.descricao || ''}</td><td>${d.categoria || ''}</td><td>R$ ${valor.toFixed(2)}</td><td>${d.parcelas_pagas}/${numParcelas}</td><td><span class="badge-custom ${badgeClass}">${status}</span></td><td><button class="btn btn-sm btn-outline" onclick="pagarParcela('${d.id}')" ${status === 'Paga' ? 'disabled' : ''}><i class="bi bi-check2"></i></button> <button class="btn btn-sm btn-outline" onclick="excluirDespesa('${d.id}')"><i class="bi bi-trash"></i></button></td></tr>`;
    });
    document.getElementById('despesasTbody').innerHTML = html || '<tr><td colspan="6" class="text-center py-3 text-muted">Nenhuma despesa cadastrada</td></tr>';
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
    
    let nova = {
        descricao: document.getElementById('despDesc').value,
        categoria: document.getElementById('despCat').value,
        valor_total: parseFloat(document.getElementById('despValor').value),
        num_parcelas: parseInt(document.getElementById('despParcelas').value),
        parcelas_pagas: parseInt(document.getElementById('despPagas').value),
        dia_vencimento: parseInt(document.getElementById('despDiaVenc').value) || null,
        data_inicio: document.getElementById('despDataInicio').value || null
    };
    
    const success = await salvarDespesa(nova);
    if (success) {
        renderDespesas();
        e.target.reset();
        document.getElementById('despParcelas').value = 1;
        document.getElementById('despPagas').value = 0;
    }
});

// ====================== FUNÇÕES DE PACOTES ======================
async function buscarPacoteAutomatico(pacienteId) {
    try {
        const response = await fetch(`api/pacotes.php?paciente_id=${pacienteId}&status=Ativo`);
        const result = await response.json();
        
        if (result.success) {
            const pacotes = result.data?.pacotes || result.data || [];
            
            if (pacotes.length > 0) {
                // Ordena por data de início decrescente para pegar o mais recente
                pacotes.sort((a, b) => {
                    const dateA = new Date(a.data_inicio.split('/').reverse().join('-'));
                    const dateB = new Date(b.data_inicio.split('/').reverse().join('-'));
                    return dateB - dateA;
                });
                
                const pacote = pacotes[0];
                
                // Verificar se o pacote está vencido (data_fim no passado)
                if (pacote.data_fim) {
                    const dataFim = new Date(pacote.data_fim.split('/').reverse().join('-'));
                    if (dataFim < new Date()) {
                        mostrarToast(`Pacote vencido em ${pacote.data_fim}`, 'danger');
                        return null;
                    }
                }
                
                return pacote;
            }
        }
        return null;
    } catch (error) {
        console.error('Erro ao buscar pacote automático:', error);
        return null;
    }
}

async function preencherPacoteAutomatico(pacienteId) {
    if (!pacienteId) return;
    
    const pacote = await buscarPacoteAutomatico(pacienteId);
    
    if (pacote) {
        // Converte data BR (dd/mm/aaaa) para ISO (aaaa-mm-dd)
        const dataInicioISO = pacote.data_inicio.split('/').reverse().join('-');
        
        // Formulário de Atendimento
        const tipoPacoteField = document.getElementById('atendPacoteTipo');
        const dataInicioField = document.getElementById('atendInicioPacote');
        
        if (tipoPacoteField) tipoPacoteField.value = pacote.tipo_pacote;
        if (dataInicioField) dataInicioField.value = dataInicioISO;
        
        // Formulário Financeiro
        const finTipoPacote = document.getElementById('finTipoPacote');
        const finDataInicio = document.getElementById('finDataInicio');
        if (finTipoPacote) finTipoPacote.value = pacote.tipo_pacote;
        if (finDataInicio) finDataInicio.value = dataInicioISO;
        
        mostrarToast(`Pacote ${pacote.tipo_pacote} encontrado e preenchido automaticamente`, 'success');
    }
}

// Listeners para preencher automaticamente quando selecionar paciente
document.addEventListener('DOMContentLoaded', function() {
    const atendPaciente = document.getElementById('atendPaciente');
    const finPaciente = document.getElementById('finPaciente');
    
    if (atendPaciente) {
        atendPaciente.addEventListener('change', function() {
            if (this.value) preencherPacoteAutomatico(this.value);
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
        mostrarToast('Erro ao exportar', 'danger');
        return;
    }
    let wb = XLSX.utils.book_new();
    
    if (dados.atendimentos.length) {
        let agendaData = dados.atendimentos.map(a => ({
            ID: a.idAtendimento || a.id_atendimento,
            Paciente: a.nomePaciente || a.paciente_nome,
            Data: a.dataAtendimento || a.data_atendimento,
            TipoPacote: a.tipoPacote || a.tipo_pacote,
            InicioPacote: a.dataInicioPacote || a.data_inicio_pacote,
            Status: a.status,
            Unidade: a.unidade
        }));
        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(agendaData), 'Agenda');
    }
    if (dados.pacientes.length) XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(dados.pacientes), 'Pacientes');
    if (dados.financeiro.length) XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(dados.financeiro), 'Financeiro');
    if (dados.despesas.length) XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(dados.despesas), 'Despesas');
    
    XLSX.writeFile(wb, `backup_guanais_${new Date().toISOString().slice(0, 10)}.xlsx`);
    mostrarToast('Backup exportado');
}

function importarExcel(event, restaurar) {
    let file = event.target.files[0];
    if (!file) return;
    if (restaurar && !confirm('Restaurar apagará dados atuais. Continuar?')) return;
    
    let reader = new FileReader();
    reader.onload = function(e) {
        let wb = XLSX.read(e.target.result, { type: 'array' });
        let novosPacientes = [], novosAtendimentos = [], novosFinanceiro = [], novosDespesas = [];
        
        wb.SheetNames.forEach(nome => {
            let sheet = wb.Sheets[nome];
            let data = XLSX.utils.sheet_to_json(sheet);
            if (nome.toLowerCase().includes('paciente')) novosPacientes = data;
            else if (nome.toLowerCase().includes('agenda')) novosAtendimentos = data;
            else if (nome.toLowerCase().includes('financeiro')) novosFinanceiro = data;
            else if (nome.toLowerCase().includes('despesa')) novosDespesas = data;
        });
        
        if (restaurar) {
            dados.pacientes = novosPacientes;
            dados.atendimentos = novosAtendimentos;
            dados.financeiro = novosFinanceiro;
            dados.despesas = novosDespesas;
        } else {
            dados.pacientes.push(...novosPacientes);
            dados.atendimentos.push(...novosAtendimentos);
            dados.financeiro.push(...novosFinanceiro);
            dados.despesas.push(...novosDespesas);
        }
        
        inicializarSistema();
        mostrarToast('Importação concluída');
    };
    reader.readAsArrayBuffer(file);
    event.target.value = '';
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

// Aplicar tema salvo ao carregar
if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark');
}
updateThemeIcons();

// Adicionar listener para o botão de tema
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
        document.getElementById('appScreen').style.display = 'block';
        await inicializarSistema();
    } else {
        alert('Usuário ou senha inválidos');
    }
});

// Se já logado, mostra sistema diretamente
async function verificarLoginSalvo() {
    if (sessionStorage.getItem('logged') === 'true') {
        const authValid = await verificarAuth();
        if (authValid) {
            document.getElementById('loginScreen').style.display = 'none';
            document.getElementById('appScreen').style.display = 'block';
            await inicializarSistema();
        } else {
            sessionStorage.clear();
        }
    }
}

verificarLoginSalvo();

// ========== GESTÃO DE USUÁRIOS ==========
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
                <button class="btn btn-sm btn-outline" onclick="editarPermissoes(${u.id})" title="Permissões">
                    <i class="bi bi-shield-lock"></i>
                </button>
                <button class="btn btn-sm btn-outline" onclick="editarUsuario(${u.id})" title="Editar">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline text-danger" onclick="excluirUsuario(${u.id})" title="Excluir" ${u.id === 1 ? 'disabled' : ''}>
                    <i class="bi bi-trash"></i>
                </button>
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
    const usuario = usuariosData.find(u => u.id === id);
    if (!usuario) return;
    
    document.getElementById('editUsuId').value = usuario.id;
    document.getElementById('editUsuNome').value = usuario.nome;
    document.getElementById('editUsuEmail').value = usuario.email || '';
    document.getElementById('editUsuTipo').value = usuario.tipo;
    document.getElementById('editUsuSenha').value = '';
    document.getElementById('editUsuAtivo').checked = usuario.ativo;
    
    const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
    modal.show();
}

async function atualizarUsuario() {
    const id = document.getElementById('editUsuId').value;
    const dados = {
        id: parseInt(id),
        nome: document.getElementById('editUsuNome').value,
        email: document.getElementById('editUsuEmail').value,
        tipo: document.getElementById('editUsuTipo').value,
        ativo: document.getElementById('editUsuAtivo').checked
    };
    
    const senha = document.getElementById('editUsuSenha').value;
    if (senha) dados.senha = senha;
    
    try {
        const result = await apiRequest('usuarios.php', 'PUT', dados);
        if (result.success) {
            mostrarToast('Usuário atualizado com sucesso');
            bootstrap.Modal.getInstance(document.getElementById('modalEditarUsuario')).hide();
            await carregarUsuarios();
        }
    } catch (error) {
        console.error('Erro ao atualizar usuário:', error);
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

const modulosPermissoes = ['pacientes', 'atendimentos', 'financeiro', 'despesas', 'configuracoes'];
const acoesPermissoes = ['visualizar', 'criar', 'editar', 'excluir'];

async function editarPermissoes(usuarioId) {
    const usuario = usuariosData.find(u => u.id === usuarioId);
    if (!usuario) return;
    
    document.getElementById('permUsuarioId').value = usuarioId;
    
    let html = '';
    modulosPermissoes.forEach(modulo => {
        const moduloLabel = modulo.charAt(0).toUpperCase() + modulo.slice(1);
        html += `<tr><td><strong>${moduloLabel}</strong></td>`;
        
        acoesPermissoes.forEach(acao => {
            const permissao = usuario.permissoes?.find(p => p.modulo === modulo && p.acao === acao);
            const checked = permissao ? permissao.permitido : false;
            html += `<td class="text-center">
                <input type="checkbox" class="form-check-input perm-check" 
                    data-modulo="${modulo}" data-acao="${acao}" ${checked ? 'checked' : ''}>
            </td>`;
        });
        
        html += '</tr>';
    });
    
    document.getElementById('permissoesBody').innerHTML = html;
    
    const modal = new bootstrap.Modal(document.getElementById('modalPermissoes'));
    modal.show();
}

async function salvarPermissoes() {
    const usuarioId = parseInt(document.getElementById('permUsuarioId').value);
    const permissoes = [];
    
    document.querySelectorAll('.perm-check').forEach(checkbox => {
        permissoes.push({
            modulo: checkbox.dataset.modulo,
            acao: checkbox.dataset.acao,
            permitido: checkbox.checked
        });
    });
    
    try {
        const result = await apiRequest('usuarios.php', 'PATCH', { usuario_id: usuarioId, permissoes });
        if (result.success) {
            mostrarToast('Permissões atualizadas com sucesso');
            bootstrap.Modal.getInstance(document.getElementById('modalPermissoes')).hide();
            await carregarUsuarios();
        }
    } catch (error) {
        console.error('Erro ao salvar permissões:', error);
    }
}

// Sobrescrever inicializarSistema para incluir carregamento de usuários
const inicializarSistemaOriginal = inicializarSistema;
async function inicializarSistema() {
    await inicializarSistemaOriginal();
    if (usuarioLogado && usuarioLogado.usuario === 'admin') {
        await carregarUsuarios();
    }
}

// ====================== FUNÇÕES DE RELATÓRIOS ======================

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
        
        const params = new URLSearchParams();
        if (mes) params.append('mes', mes);
        if (clinica) params.append('clinica', clinica);
        
        const response = await fetch(`api/financeiro.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            const tbody = document.querySelector('#relatorioFinanceiroTable tbody');
            if (tbody) {
                if (data.lancamentos.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-3 text-muted">Nenhum lançamento encontrado</td></tr>';
                } else {
                    tbody.innerHTML = '';
                    data.lancamentos.forEach(lancamento => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${lancamento.paciente_nome}</td>
                            <td>${formataDataBR(lancamento.data)}</td>
                            <td>${lancamento.clinica}</td>
                            <td>${lancamento.tipo_pacote || '-'}</td>
                            <td>R$ ${lancamento.valor.toFixed(2)}</td>
                            <td>R$ ${lancamento.despesa_automatica.toFixed(2)}</td>
                            <td>R$ ${lancamento.receita_disponivel.toFixed(2)}</td>
                            <td>${lancamento.forma_pagamento}</td>
                            <td>${lancamento.nf_emitida ? 'Sim' : 'Não'}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            }
            
            const totalBrutoEl = document.getElementById('relatorioTotalBruto');
            const totalDespesasEl = document.getElementById('relatorioTotalDespesas');
            const totalDisponivelEl = document.getElementById('relatorioTotalDisponivel');
            
            if (totalBrutoEl) totalBrutoEl.textContent = `R$ ${(data.resumo.total_bruto || 0).toFixed(2)}`;
            if (totalDespesasEl) totalDespesasEl.textContent = `R$ ${(data.resumo.total_despesas || 0).toFixed(2)}`;
            if (totalDisponivelEl) totalDisponivelEl.textContent = `R$ ${(data.resumo.total_liquido || 0).toFixed(2)}`;
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

function exportarRelatorio(tipo) {
    let dadosExport = [];
    let nomeArquivo = '';
    
    if (tipo === 'atendimentos') {
        dadosExport = dados.atendimentos.map(a => ({
            Paciente: a.nomePaciente || a.paciente_nome,
            Data: a.dataAtendimento || a.data_atendimento,
            TipoPacote: a.tipoPacote || a.tipo_pacote,
            Status: a.status,
            Unidade: a.unidade
        }));
        nomeArquivo = 'relatorio_atendimentos.csv';
    } else if (tipo === 'financeiro') {
        dadosExport = dados.financeiro.map(f => ({
            Paciente: f.paciente_nome,
            Data: f.data,
            Clinica: f.clinica,
            Valor: f.valor,
            Despesa: f.despesa_automatica,
            Disponivel: f.receita_disponivel
        }));
        nomeArquivo = 'relatorio_financeiro.csv';
    } else if (tipo === 'pacientes') {
        dadosExport = dados.pacientes.map(p => ({
            ID: p.id,
            Nome: p.nome,
            Telefone: p.telefone,
            Email: p.email,
            Ativo: p.ativo ? 'Sim' : 'Não'
        }));
        nomeArquivo = 'relatorio_pacientes.csv';
    }
    
    if (dadosExport.length === 0) {
        mostrarToast('Não há dados para exportar', 'danger');
        return;
    }
    
    let csv = Object.keys(dadosExport[0]).join(',') + '\n';
    dadosExport.forEach(d => {
        csv += Object.values(d).map(v => `"${v}"`).join(',') + '\n';
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