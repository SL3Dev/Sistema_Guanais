<?php
/**
 * Testador interativo (JS) com rollback por teste.
 */
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Testador do Sistema Guanais</title>
  <style>
    body{font-family:Arial,sans-serif;background:#f4f6fb;margin:0;padding:20px;color:#1f2937}
    .wrap{max-width:1150px;margin:0 auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.1)}
    h1{margin-top:0}.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    input,button{padding:8px 10px;border-radius:8px;border:1px solid #d1d5db}
    button{cursor:pointer;background:#2563eb;color:#fff;border:none}.sec{background:#111827}
    .ok{color:#166534}.fail{color:#991b1b}.muted{color:#6b7280}
    .card{border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin:12px 0}
    pre{background:#0f172a;color:#e2e8f0;padding:10px;border-radius:8px;overflow:auto;max-height:280px}
    table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left;font-size:14px}
  </style>
</head>
<body>
<div class="wrap">
  <h1>🧪 Testador por Botões (com desfazer ação)</h1>
  <p class="muted">Cada teste cria/edita/consulta e ao final remove os dados criados (rollback).</p>

  <div class="card">
    <div class="row">
      <input id="usuario" value="admin" placeholder="Usuário" />
      <input id="senha" value="0301" placeholder="Senha" type="password" />
      <button onclick="testAuth()">Testar Auth</button>
      <button class="sec" onclick="runAll()">Rodar todos</button>
      <button onclick="clearLogs()">Limpar logs</button>
    </div>
  </div>

  <div class="card">
    <div class="row">
      <button onclick="testPacientes()">Pacientes (CRUD + rollback)</button>
      <button onclick="testPacotes()">Pacotes (CRUD + rollback)</button>
      <button onclick="testAtendimentos()">Atendimentos (CRUD/PATCH + rollback)</button>
      <button onclick="testFinanceiro()">Financeiro (CRUD + rollback)</button>
      <button onclick="testDespesas()">Despesas (CRUD/PATCH + rollback)</button>
      <button onclick="testConfiguracoes()">Configurações (CRUD + upload logo + rollback)</button>
      <button onclick="testArquivos()">Arquivos/Fotos paciente (upload + delete)</button>
      <button onclick="testUsuarios()">Usuários (CRUD/PATCH + rollback)</button>
    </div>
  </div>

  <div class="card">
    <table>
      <thead><tr><th>#</th><th>Módulo</th><th>Status</th><th>Mensagem</th></tr></thead>
      <tbody id="tb"></tbody>
    </table>
  </div>

  <div class="card">
    <strong>Detalhes:</strong>
    <pre id="log"></pre>
  </div>
</div>

<script>
const api = 'api/';
const state = { pacienteId:null };
let n = 0;

function stamp(){ return new Date().toISOString().replace(/[-:.TZ]/g,'').slice(0,14); }
function uid(p){ return `${p}_${stamp()}_${Math.floor(Math.random()*9999)}`; }
function push(mod, ok, msg, data=null){
  n++; const tr=document.createElement('tr');
  tr.innerHTML = `<td>${n}</td><td>${mod}</td><td class="${ok?'ok':'fail'}">${ok?'OK':'FALHOU'}</td><td>${msg}</td>`;
  document.getElementById('tb').appendChild(tr);
  if(data) log(data);
}
function log(d){ document.getElementById('log').textContent += JSON.stringify(d,null,2)+'\n\n'; }
function clearLogs(){ document.getElementById('tb').innerHTML=''; document.getElementById('log').textContent=''; n=0; }

async function apiJson(url, method='GET', data=null){
  const opt={method,credentials:'same-origin',headers:{}};
  if(data){ opt.headers['Content-Type']='application/json'; opt.body=JSON.stringify(data); }
  const r=await fetch(api+url,opt); let j=null; try{ j=await r.json(); }catch(e){ j={success:false,error:'JSON inválido'}; }
  return {http:r.status, json:j};
}
async function apiForm(url, formData){
  const r=await fetch(api+url,{method:'POST',body:formData,credentials:'same-origin'});
  let j=null; try{ j=await r.json(); }catch(e){ j={success:false,error:'JSON inválido'}; }
  return {http:r.status, json:j};
}
function ok(r){ return r && r.json && r.json.success; }

async function ensurePaciente(){
  if(state.pacienteId) return true;
  const id = uid('PTEST');
  const r = await apiJson('pacientes.php','POST',{id,nome:'Paciente Base '+stamp(),data_nascimento:'01/01/2000',telefone:'11999990000'});
  if(ok(r)){ state.pacienteId = id; return true; }
  push('base',false,'Falha ao criar paciente base',r);
  return false;
}

async function testAuth(){
  const usuario=document.getElementById('usuario').value.trim();
  const senha=document.getElementById('senha').value;
  const login = await apiJson('auth.php','POST',{usuario,senha});
  push('auth', ok(login), ok(login)?'Login realizado':'Falha no login', login);
  const sess = await apiJson('auth.php','GET');
  push('auth', ok(sess), ok(sess)?'Sessão validada':'Falha sessão', sess);
}

async function testPacientes(){
  const id = uid('P');
  const c = await apiJson('pacientes.php','POST',{id,nome:'Paciente '+id,data_nascimento:'10/10/2000',telefone:'11988887777'});
  push('pacientes', ok(c), 'Criar paciente', c);
  if(!ok(c)) return;
  const u = await apiJson('pacientes.php','PUT',{id,nome:'Paciente Edit '+id,data_nascimento:'10/10/2000',telefone:'11988887777'});
  push('pacientes', ok(u), 'Editar paciente', u);
  const g = await apiJson('pacientes.php?id='+encodeURIComponent(id),'GET');
  push('pacientes', ok(g), 'Buscar paciente', g);
  const d = await apiJson('pacientes.php?id='+encodeURIComponent(id),'DELETE');
  push('pacientes', ok(d), 'Rollback: excluir paciente', d);
}

async function testPacotes(){
  if(!(await ensurePaciente())) return;
  const c = await apiJson('pacotes.php','POST',{paciente_id:state.pacienteId,tipo_pacote:'Mensal',data_inicio:'01/05/2026',valor_total:300,forma_pagamento:'Pix'});
  push('pacotes', ok(c), 'Criar pacote', c);
  if(!ok(c)) return;
  const id = c.json.data.id;
  const u = await apiJson('pacotes.php','PUT',{id,tipo_pacote:'Quinzenal',data_inicio:'01/05/2026',data_fim:'16/05/2026',valor_total:220,forma_pagamento:'Dinheiro',status:'Ativo'});
  push('pacotes', ok(u), 'Editar pacote', u);
  const d = await apiJson('pacotes.php?id='+encodeURIComponent(id),'DELETE');
  push('pacotes', ok(d), 'Rollback: excluir pacote', d);
}

async function testAtendimentos(){
  if(!(await ensurePaciente())) return;
  const nome='Paciente Base';
  const c = await apiJson('atendimentos.php','POST',{paciente_id:state.pacienteId,paciente_nome:nome,data_atendimento:'01/05/2026',tipo_pacote:'Mensal',data_inicio_pacote:'01/05/2026',status:'Confirmado',unidade:'ANIMO',evolucao:'teste'});
  push('atendimentos', ok(c), 'Criar atendimento', c);
  if(!ok(c)) return;
  const id = c.json.data.id_atendimento;
  const p = await apiJson('atendimentos.php','PATCH',{id_atendimento:id,status:'Falta',evolucao:'patch ok'});
  push('atendimentos', ok(p), 'PATCH atendimento', p);
  const u = await apiJson('atendimentos.php','PUT',{id_atendimento:id,paciente_id:state.pacienteId,paciente_nome:nome,data_atendimento:'02/05/2026',tipo_pacote:'Quinzenal',data_inicio_pacote:'01/05/2026',status:'Confirmado',unidade:'ESPAÇO GUANAIS'});
  push('atendimentos', ok(u), 'Editar atendimento', u);
  const d = await apiJson('atendimentos.php?id='+encodeURIComponent(id),'DELETE');
  push('atendimentos', ok(d), 'Rollback: excluir atendimento', d);
}

async function testFinanceiro(){
  if(!(await ensurePaciente())) return;
  const c = await apiJson('financeiro.php','POST',{paciente_id:state.pacienteId,paciente_nome:'Paciente Base',clinica:'ANIMO',data:'01/05/2026',valor:200,forma_pagamento:'Pix'});
  push('financeiro', ok(c), 'Criar lançamento', c);
  if(!ok(c)) return;
  const id = c.json.data.id;
  const u = await apiJson('financeiro.php','PUT',{id,paciente_id:state.pacienteId,paciente_nome:'Paciente Base',clinica:'ESPAÇO GUANAIS',data:'01/05/2026',valor:250,forma_pagamento:'Dinheiro'});
  push('financeiro', ok(u), 'Editar lançamento', u);
  const d = await apiJson('financeiro.php?id='+encodeURIComponent(id),'DELETE');
  push('financeiro', ok(d), 'Rollback: excluir lançamento', d);
}

async function testDespesas(){
  const c = await apiJson('despesas.php','POST',{descricao:'Despesa teste '+stamp(),categoria:'Fixa',valor_total:180,num_parcelas:2,parcelas_pagas:0,dia_vencimento:10,data_inicio:'01/05/2026'});
  push('despesas', ok(c), 'Criar despesa', c);
  if(!ok(c)) return;
  const id = c.json.data.id;
  const p = await apiJson('despesas.php','PATCH',{id});
  push('despesas', ok(p), 'PATCH pagar parcela', p);
  const u = await apiJson('despesas.php','PUT',{id,descricao:'Despesa editada',categoria:'Extra/Investimento',valor_total:200,num_parcelas:3,parcelas_pagas:1,dia_vencimento:12,data_inicio:'01/05/2026'});
  push('despesas', ok(u), 'Editar despesa', u);
  const d = await apiJson('despesas.php?id='+encodeURIComponent(id),'DELETE');
  push('despesas', ok(d), 'Rollback: excluir despesa', d);
}

function makePngBlob(){
  const b64='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wm6Lz4AAAAASUVORK5CYII=';
  const bytes=atob(b64); const arr=new Uint8Array(bytes.length);
  for(let i=0;i<bytes.length;i++) arr[i]=bytes.charCodeAt(i);
  return new Blob([arr],{type:'image/png'});
}

async function testConfiguracoes(){
  const chave='cfg_teste_'+stamp();
  const c = await apiJson('configuracoes.php','POST',{chave,valor:'abc',tipo:'texto'});
  push('configuracoes', ok(c), 'Criar configuração', c);
  const u = await apiJson('configuracoes.php','PUT',{chave,valor:'abc-editado'});
  push('configuracoes', ok(u), 'Editar configuração', u);

  const fd = new FormData();
  fd.append('type','login');
  fd.append('logo', new File([makePngBlob()], 'logo_teste.png', {type:'image/png'}));
  const up = await apiForm('configuracoes.php', fd);
  push('configuracoes', ok(up), 'Upload de foto/logo', up);

  const d = await apiJson('configuracoes.php?chave='+encodeURIComponent(chave),'DELETE');
  push('configuracoes', ok(d), 'Rollback: excluir configuração teste', d);
}

async function testArquivos(){
  if(!(await ensurePaciente())) return;
  const fd = new FormData();
  fd.append('paciente_id', state.pacienteId);
  fd.append('arquivo', new File([makePngBlob()], 'foto_teste.png', {type:'image/png'}));
  const c = await apiForm('arquivos.php', fd);
  push('arquivos', ok(c), 'Upload foto paciente', c);
  if(!ok(c)) return;
  const id = c.json.data.id;
  const l = await apiJson('arquivos.php?paciente_id='+encodeURIComponent(state.pacienteId),'GET');
  push('arquivos', ok(l), 'Listar arquivos do paciente', l);
  const d = await apiJson('arquivos.php?id='+encodeURIComponent(id),'DELETE');
  push('arquivos', ok(d), 'Rollback: excluir arquivo enviado', d);
}

async function testUsuarios(){
  const usuario='user_teste_'+stamp();
  const c = await apiJson('usuarios.php','POST',{usuario,senha:'123456',nome:'Usuário Teste',email:'u@teste.com',tipo:'secretaria'});
  push('usuarios', ok(c), 'Criar usuário', c);
  if(!ok(c)) return;
  const id = c.json.data.id;
  const p = await apiJson('usuarios.php?id='+encodeURIComponent(id),'PATCH',{permissoes:[{modulo:'pacientes',acao:'visualizar',permitido:true}]});
  push('usuarios', ok(p), 'PATCH permissões', p);
  const u = await apiJson('usuarios.php?id='+encodeURIComponent(id),'PUT',{nome:'Usuário Editado',tipo:'terapeuta',ativo:true});
  push('usuarios', ok(u), 'Editar usuário', u);
  const d = await apiJson('usuarios.php?id='+encodeURIComponent(id),'DELETE');
  push('usuarios', ok(d), 'Rollback: excluir usuário', d);
}

async function runAll(){
  await testAuth();
  await testPacientes();
  await testPacotes();
  await testAtendimentos();
  await testFinanceiro();
  await testDespesas();
  await testConfiguracoes();
  await testArquivos();
  await testUsuarios();
  if(state.pacienteId){
    const d = await apiJson('pacientes.php?id='+encodeURIComponent(state.pacienteId),'DELETE');
    push('final', ok(d), 'Rollback final: excluir paciente base', d);
    state.pacienteId = null;
  }
  const out = await apiJson('auth.php','DELETE');
  push('auth', ok(out), 'Logout final', out);
}
</script>
</body>
</html>
