<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>GPE Cloud — Painel de Tenants</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-100 min-h-screen">
    <div class="max-w-6xl mx-auto py-8 px-4">
        <header class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    <i class="fas fa-building-shield text-blue-600 mr-2"></i>GPE Cloud — Painel de Tenants
                </h1>
                <p class="text-sm text-slate-500">Catálogo central de municípios atendidos pelo sistema.</p>
            </div>
            <div class="text-right">
                <div class="text-xs text-slate-500">Logado como</div>
                <div class="font-medium text-slate-700">{{ session('super_admin_email') ?? '—' }}</div>

                <button onclick="logout()"
                   class="px-3 py-1.5 rounded-lg bg-blue-600 text-white font-medium">
                    <i class="fas fa-sign-out mr-1"></i>Logout
                </button>
            </div>
        </header>

        {{-- Navegação entre as telas do painel landlord --}}
        <nav class="flex gap-2 mb-5 text-sm">
            <span class="px-3 py-1.5 rounded-lg bg-blue-600 text-white font-medium">
                <i class="fas fa-building-shield mr-1"></i>Tenants
            </span>
            <a href="{{ route('landlord.links.index') }}"
                class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i class="fas fa-link mr-1"></i>Vínculos
            </a>
        </nav>

        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
                <i class="fas fa-circle-check mr-1"></i>{{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                <div class="font-semibold mb-1"><i class="fas fa-triangle-exclamation mr-1"></i>Não foi possível salvar:</div>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-slate-100 flex justify-between items-center">
                <h2 class="font-semibold text-slate-700">Tenants cadastrados ({{ $tenants->count() }})</h2>
                <button onclick="abrirCriar()"
                    class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    <i class="fas fa-plus mr-1"></i>Novo tenant
                </button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="px-3 py-2 text-right">ID</th>
                        <th class="px-3 py-2">Subdomínio</th>
                        <th class="px-3 py-2">Nome</th>
                        <th class="px-3 py-2">UF</th>
                        <th class="px-3 py-2">Driver</th>
                        <th class="px-3 py-2">Banco</th>
                        <th class="px-3 py-2">Host</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tenants->groupBy('domain') as $dominio => $grupo)
                        {{-- Cabeçalho do grupo: domínio base --}}
                        <tr class="bg-slate-100/70 border-t border-slate-200">
                            <td colspan="9" class="px-3 py-1.5 text-xs font-semibold text-slate-600">
                                <i class="fas fa-globe text-slate-400 mr-1"></i>{{ $dominio ?: '—' }}
                                <span class="ml-1 font-normal text-slate-400">({{ $grupo->count() }})</span>
                            </td>
                        </tr>
                        @foreach($grupo as $t)
                            {{-- MATRIZ das tabelas universais: destacada porque publicar a partir
                                 do banco errado propaga o cadastro de um município como se fosse
                                 norma, para todos. Só um tenant pode ter a flag (índice único
                                 parcial `uq_tenants_uma_matriz`). --}}
                            <tr class="{{ $t->is_matriz ? 'bg-violet-50 hover:bg-violet-100 ring-1 ring-inset ring-violet-200' : 'hover:bg-slate-50' }}">
                                <td class="px-3 py-2 font-mono text-xs text-right text-slate-400">{{ $t->id }}</td>
                                <td class="px-3 py-2 font-mono">
                                    <a href="#" onclick="goToTenant('{{$t->id}}')" rel="noopener"
                                        class="text-blue-700 hover:text-blue-900 hover:underline inline-flex items-center gap-1"
                                        title="Abrir {{ $t->nome }} — {{ $t->subdomain }}.{{ $t->domain }}">
                                        {{ $t->subdomain }}
                                        <i class="fas fa-arrow-up-right-from-square text-[10px] opacity-60"></i>
                                    </a>
                                </td>
                                <td class="px-3 py-2 font-medium">
                                    {{ $t->nome }}
                                    @if($t->is_matriz)
                                        <span class="ml-1.5 px-2 py-0.5 text-[11px] rounded bg-violet-100 text-violet-700 font-semibold whitespace-nowrap"
                                              title="Banco modelo das tabelas universais — origem das publicações para os municípios">
                                            <i class="fas fa-star text-[9px]"></i> MATRIZ
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs">{{ $t->uf ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @php $isPg = $t->driver === 'pgsql'; @endphp
                                    <span class="px-2 py-0.5 text-[11px] rounded font-mono {{ $isPg ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $t->driver ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $t->db_name }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-slate-500">{{ $t->db_host }}:{{ $t->db_port }}</td>
                                <td class="px-3 py-2">
                                    @if($t->active)
                                        <span class="px-2 py-0.5 text-[11px] rounded bg-emerald-100 text-emerald-700">Ativo</span>
                                    @else
                                        <span class="px-2 py-0.5 text-[11px] rounded bg-slate-200 text-slate-700">Inativo</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex gap-1 justify-end">
                                        {{-- MATRIZ das universais: uma só, trocada por esta ação
                                             (o formulário não a expõe — é decisão consequente). --}}
                                        <form method="POST" action="{{ route('landlord.tenants.matriz', $t->id) }}" class="inline"
                                              onsubmit="return confirmarSubmit(this, @js([
                                                  'titulo' => $t->is_matriz
                                                      ? "Desmarcar {$t->nome} como matriz?"
                                                      : "Tornar {$t->nome} a matriz das tabelas universais?",
                                                  'descricao' => $t->is_matriz
                                                      ? 'A publicação de universais fica sem origem.'
                                                      : 'As universais deste banco passam a ser a norma publicada para todos os municípios.',
                                                  'tom' => 'atencao',
                                                  'rotuloConfirmar' => $t->is_matriz ? 'Desmarcar' : 'Tornar matriz',
                                              ]))">
                                            @csrf
                                            <button type="submit"
                                                class="px-2 py-1 text-xs rounded {{ $t->is_matriz ? 'text-violet-700 hover:bg-violet-200' : 'text-slate-400 hover:bg-slate-200' }}"
                                                title="{{ $t->is_matriz ? 'É a matriz das tabelas universais — clique para desmarcar' : 'Tornar este a matriz das tabelas universais' }}">
                                                <i class="fas fa-star"></i>
                                            </button>
                                        </form>
                                        <button type="button" onclick="testarConexao({{ $t->id }}, this)"
                                            class="px-2 py-1 text-xs rounded hover:bg-slate-200 text-slate-600"
                                            title="Testar conexão">
                                            <i class="fas fa-plug"></i>
                                        </button>
                                        <button type="button" onclick="abrirEditar({{ $t->id }})"
                                            class="px-2 py-1 text-xs rounded hover:bg-blue-100 text-blue-700"
                                            title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button type="button" onclick="abrirDuplicar({{ $t->id }})"
                                            class="px-2 py-1 text-xs rounded hover:bg-indigo-100 text-indigo-700"
                                            title="Duplicar — novo cadastro a partir deste">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <form method="POST" action="{{ route('landlord.tenants.toggle', $t->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" title="{{ $t->active ? 'Desativar' : 'Ativar' }}"
                                                class="px-2 py-1 text-xs rounded hover:bg-amber-100 text-amber-700">
                                                <i class="fas fa-{{ $t->active ? 'pause' : 'play' }}"></i>
                                            </button>
                                        </form>
                                        <button type="button"
                                            onclick="abrirExcluir({{ $t->id }}, '{{ addslashes($t->subdomain) }}')"
                                            class="px-2 py-1 text-xs rounded hover:bg-rose-100 text-rose-700"
                                            title="Excluir do catálogo">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="9" class="px-3 py-6 text-center text-slate-400">Nenhum tenant cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="text-xs text-slate-500 text-center">
            GPE Cloud · landlord database · use <code>php artisan tenant:list</code> ou <code>tenant:create</code> via CLI
        </div>
    </div>

    {{-- Modal: criar / editar tenant --}}
    <div id="mForm" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <form id="frmTenant" method="POST" action="{{ route('landlord.tenants.store') }}"
            class="bg-white rounded-xl shadow-xl max-w-lg w-full p-5 max-h-[90vh] overflow-y-auto">
            @csrf
            <input type="hidden" name="_method" id="frmMethod" value="POST">
            <div class="flex justify-between items-center mb-3">
                <h3 id="frmTitulo" class="font-semibold">Novo Tenant</h3>
                <button type="button" onclick="fecharForm()" class="text-slate-400 hover:text-slate-700">×</button>
            </div>
            <div class="space-y-3 text-sm">
                <div class="grid grid-cols-3 gap-2">
                    <input name="subdomain" id="fSubdomain" placeholder="Subdomínio (ex: santoantoniodoamparo)" required
                        class="col-span-2 px-3 py-2 rounded border border-slate-200 bg-slate-50">
                    <input name="uf" id="fUf" placeholder="UF" maxlength="2"
                        class="px-3 py-2 rounded border border-slate-200 bg-slate-50">
                </div>
                <div>
                    <input name="domain" id="fDomain" placeholder="Domínio base (ex: gpedocs.com.br)" value="gpedocs.com.br" required
                        class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">
                    <p class="text-[11px] text-slate-400 mt-1">Host de acesso: <code id="fHostPreview" class="text-slate-500"></code></p>
                </div>
                <input name="nome" id="fNome" placeholder="Nome do município" required class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">
                <input name="cnpj" id="fCnpj" placeholder="CNPJ (14 dígitos)" maxlength="14" class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">

                <hr class="border-slate-100">
                <div class="text-xs text-slate-500">Banco de dados do tenant</div>
                <div class="grid grid-cols-3 gap-2">
                    <select name="driver" id="fdriver" required class="px-3 py-2 rounded border border-slate-200 bg-slate-50">
                        <option value="pgsql">PostgreSQL</option>
                    </select>
                    <input name="db_host" id="fDbHost" placeholder="Host" value="127.0.0.1" required class="px-3 py-2 rounded border border-slate-200 bg-slate-50">
                    <input name="db_port" id="fDbPort" placeholder="Porta" value="5432" class="px-3 py-2 rounded border border-slate-200 bg-slate-50">
                </div>
                <input name="db_name" id="fDbName" placeholder="Nome do banco (ex: gpd_santoantonio)" required class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">
                <div id="fSchemaWrap" class="hidden">
                    <input name="db_schema" id="fDbSchema" placeholder="Schema do Postgres (ex: public)" maxlength="64"
                        class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">
                    <p class="text-[11px] text-slate-400 mt-1">Define o <code>search_path</code> da conexão. Obrigatório para PostgreSQL.</p>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input name="db_username" id="fDbUser" placeholder="Usuário" required class="px-3 py-2 rounded border border-slate-200 bg-slate-50">
                    <input id="fDbPass" name="db_password" type="password" placeholder="Senha" required class="px-3 py-2 rounded border border-slate-200 bg-slate-50">
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-600 select-none">
                    <input type="checkbox" id="chkNoPass" name="db_no_password" value="1" onchange="aplicarSemSenha(this.checked)" class="rounded border-slate-300">
                    <span>Banco sem senha (ex: <code class="text-[11px] bg-slate-100 px-1 rounded">root</code> no Laragon local)</span>
                </label>
                <label id="lblKeepPass" class="hidden flex items-center gap-2 text-xs text-slate-600 select-none">
                    <input type="checkbox" id="chkKeepPass" name="db_keep_password" value="1" onchange="aplicarManterSenha(this.checked)" class="rounded border-slate-300">
                    <span>Manter senha atual (não alterar)</span>
                </label>

                <hr class="border-slate-100">
                <div class="text-xs text-slate-500">Vigência e observações (opcional)</div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[11px] text-slate-500">Contratado em</label>
                        <input name="contratado_em" id="fContratado" type="date" class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">
                    </div>
                    <div>
                        <label class="text-[11px] text-slate-500">Encerrado em</label>
                        <input name="encerrado_em" id="fEncerrado" type="date" class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">
                    </div>
                </div>
                <textarea name="observacoes" id="fObs" placeholder="Observações" rows="2" class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50"></textarea>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="fecharForm()" class="px-3 py-2 text-sm rounded hover:bg-slate-100">Cancelar</button>
                <button type="submit" id="frmSubmit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    <i class="fas fa-plus mr-1"></i><span id="frmSubmitTxt">Criar tenant</span>
                </button>
            </div>
        </form>
    </div>

    <x-confirm-dialog />

    {{-- Modal: confirmar exclusão --}}
    <div id="mDel" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <form id="frmDel" method="POST" class="bg-white rounded-xl shadow-xl max-w-md w-full p-5">
            @csrf
            @method('DELETE')
            <h3 class="font-semibold mb-2 text-rose-700"><i class="fas fa-triangle-exclamation mr-1"></i>Excluir tenant?</h3>
            <p class="text-sm text-slate-600 mb-3">
                Vamos remover <code id="delDomain" class="font-mono bg-slate-100 px-1 rounded"></code> do catálogo do landlord.
                <strong class="text-rose-700">O banco MariaDB do tenant não será apagado</strong> — apenas o registro aqui.
            </p>
            <p class="text-xs text-slate-500 mb-4">
                Recomendação: prefira <em>desativar</em> a excluir. Tenants excluídos não aparecem mais no painel nem resolvem
                via subdomain, mas dados ficam órfãos no banco do município.
            </p>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('mDel').classList.add('hidden')"
                    class="px-3 py-2 text-sm rounded hover:bg-slate-100">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-rose-600 text-white rounded-lg text-sm font-medium hover:bg-rose-700">
                    <i class="fas fa-trash mr-1"></i>Excluir
                </button>
            </div>
        </form>
    </div>

    <script>
        const URL_STORE = "{{ route('landlord.tenants.store') }}";
        const URL_SHOW  = "{{ url('landlord/tenants') }}";
        const URL_LOGOUT  = "{{ url('landlord/logout') }}";

        // Mostra o host resultante ({subdomain}.{domain}) enquanto edita o form.
        function updateHostPreview() {
            const sub = (document.getElementById('fSubdomain').value || 'subdominio').trim();
            const dom = (document.getElementById('fDomain').value || 'dominio.base').trim();
            document.getElementById('fHostPreview').textContent = `${sub}.${dom}`;
        }
        document.addEventListener('DOMContentLoaded', () => {
            ['fSubdomain', 'fDomain'].forEach(id =>
                document.getElementById(id).addEventListener('input', updateHostPreview));
            document.getElementById('fdriver').addEventListener('change', () => aplicarDriver());
        });

        // Mostra/esconde o campo de schema; obrigatório só em pgsql.
        function aplicarDriver() {
            const driver = document.getElementById('fdriver').value;
            const wrap = document.getElementById('fSchemaWrap');
            const input = document.getElementById('fDbSchema');
            if (driver === 'pgsql') {
                wrap.classList.remove('hidden');
                input.required = true;
                if (!input.value) input.value = 'public';
            } else {
                wrap.classList.add('hidden');
                input.required = false;
                input.value = '';
            }
        }

        function abrirCriar() {
            const f = document.getElementById('frmTenant');
            f.reset();
            f.action = URL_STORE;
            document.getElementById('frmMethod').value = 'POST';
            document.getElementById('frmTitulo').textContent = 'Novo Tenant';
            document.getElementById('frmSubmitTxt').textContent = 'Criar tenant';
            document.getElementById('lblKeepPass').classList.add('hidden');
            document.getElementById('chkKeepPass').checked = false;
            document.getElementById('chkNoPass').checked = false;
            aplicarSemSenha(false);
            // Defaults
            document.getElementById('fDbHost').value = '127.0.0.1';
            document.getElementById('fDbPort').value = '3306';
            aplicarDriver();
            updateHostPreview();
            document.getElementById('mForm').classList.remove('hidden');
        }

        async function abrirEditar(id) {
            try {
                const r = await fetch(`${URL_SHOW}/${id}`, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('Falha ao carregar tenant');
                const d = await r.json();
                const f = document.getElementById('frmTenant');
                f.reset();
                f.action = `${URL_SHOW}/${id}`;
                document.getElementById('frmMethod').value = 'PUT';
                document.getElementById('frmTitulo').textContent = `Editar Tenant — ${d.subdomain}`;
                document.getElementById('frmSubmitTxt').textContent = 'Salvar alterações';

                document.getElementById('fSubdomain').value  = d.subdomain ?? '';
                document.getElementById('fDomain').value     = d.domain ?? 'gpedocs.com.br';
                document.getElementById('fUf').value         = d.uf ?? '';
                document.getElementById('fNome').value       = d.nome ?? '';
                document.getElementById('fCnpj').value       = d.cnpj ?? '';
                document.getElementById('fdriver').value     = d.driver ?? 'pgsql';
                document.getElementById('fDbHost').value     = d.db_host ?? '';
                document.getElementById('fDbPort').value     = d.db_port ?? '3306';
                document.getElementById('fDbName').value     = d.db_name ?? '';
                document.getElementById('fDbSchema').value   = d.db_schema ?? '';
                document.getElementById('fDbUser').value     = d.db_username ?? '';
                document.getElementById('fContratado').value = d.contratado_em ?? '';
                document.getElementById('fEncerrado').value  = d.encerrado_em ?? '';
                document.getElementById('fObs').value        = d.observacoes ?? '';

                document.getElementById('chkNoPass').checked = false;
                aplicarSemSenha(false);

                // Em edição, oferece "manter senha" (default ON quando há senha cadastrada)
                document.getElementById('lblKeepPass').classList.remove('hidden');
                const keep = document.getElementById('chkKeepPass');
                keep.checked = !!d.has_password;
                aplicarManterSenha(keep.checked);

                aplicarDriver();
                updateHostPreview();
                document.getElementById('mForm').classList.remove('hidden');
            } catch (e) {
                alert('Erro ao carregar tenant: ' + e.message);
            }
        }

        // Duplicar: copia os dados de infra (driver, host, porta, usuário do banco,
        // domínio base, UF, observações) e deixa em branco o que tem que ser único
        // por município (subdomínio, nome, CNPJ, nome do banco, senha do banco).
        async function abrirDuplicar(id) {
            try {
                const r = await fetch(`${URL_SHOW}/${id}`, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('Falha ao carregar tenant');
                const d = await r.json();

                const f = document.getElementById('frmTenant');
                f.reset();
                f.action = URL_STORE;
                document.getElementById('frmMethod').value = 'POST';
                document.getElementById('frmTitulo').textContent = `Duplicar Tenant — origem: ${d.subdomain}`;
                document.getElementById('frmSubmitTxt').textContent = 'Criar duplicata';

                // "Manter senha" não se aplica em criação.
                document.getElementById('lblKeepPass').classList.add('hidden');
                document.getElementById('chkKeepPass').checked = false;
                document.getElementById('chkNoPass').checked = false;
                aplicarSemSenha(false);

                // Campos herdados do tenant de referência (infra compartilhada).
                document.getElementById('fDomain').value     = d.domain ?? 'gpedocs.com.br';
                document.getElementById('fUf').value         = d.uf ?? '';
                document.getElementById('fdriver').value     = d.driver ?? 'pgsql';
                document.getElementById('fDbHost').value     = d.db_host ?? '127.0.0.1';
                document.getElementById('fDbPort').value     = d.db_port ?? '3306';
                document.getElementById('fDbUser').value     = d.db_username ?? '';
                document.getElementById('fDbSchema').value   = d.db_schema ?? '';
                document.getElementById('fObs').value        = d.observacoes ?? '';

                // Campos que precisam ser únicos / específicos do novo município: limpos.
                document.getElementById('fSubdomain').value = '';
                document.getElementById('fNome').value      = '';
                document.getElementById('fCnpj').value      = '';
                document.getElementById('fDbName').value    = '';
                document.getElementById('fDbPass').value    = '';
                document.getElementById('fContratado').value = '';
                document.getElementById('fEncerrado').value  = '';

                aplicarDriver();
                updateHostPreview();
                document.getElementById('mForm').classList.remove('hidden');
                document.getElementById('fSubdomain').focus();
            } catch (e) {
                alert('Erro ao carregar tenant: ' + e.message);
            }
        }

        function fecharForm() {
            document.getElementById('mForm').classList.add('hidden');
        }

        function aplicarSemSenha(checked) {
            const i = document.getElementById('fDbPass');
            i.disabled = checked;
            if (checked) {
                i.value = '';
                i.required = false;
                i.classList.add('bg-slate-100', 'text-slate-400');
                // Sem senha implica não "manter senha"
                const keep = document.getElementById('chkKeepPass');
                if (keep) { keep.checked = false; keep.disabled = true; }
            } else {
                i.required = !document.getElementById('chkKeepPass')?.checked;
                i.classList.remove('bg-slate-100', 'text-slate-400');
                const keep = document.getElementById('chkKeepPass');
                if (keep) keep.disabled = false;
            }
        }

        function aplicarManterSenha(checked) {
            const i = document.getElementById('fDbPass');
            if (checked) {
                i.disabled = true;
                i.required = false;
                i.placeholder = '••••• (mantida)';
                i.classList.add('bg-slate-100', 'text-slate-400');
                document.getElementById('chkNoPass').checked = false;
                document.getElementById('chkNoPass').disabled = true;
            } else {
                i.disabled = false;
                i.required = !document.getElementById('chkNoPass').checked;
                i.placeholder = 'Senha';
                i.classList.remove('bg-slate-100', 'text-slate-400');
                document.getElementById('chkNoPass').disabled = false;
            }
        }

        function abrirExcluir(id, domain) {
            const f = document.getElementById('frmDel');
            f.action = `${URL_SHOW}/${id}`;
            document.getElementById('delDomain').textContent = domain;
            document.getElementById('mDel').classList.remove('hidden');
        }

        async function testarConexao(id, btn) {
            const old = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            try {
                const r = await fetch(`${URL_SHOW}/${id}/test`, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                if (d.ok) {
                    alert('Conexão OK — ' + (d.version ?? ''));
                } else {
                    alert('Falha: ' + (d.error ?? 'erro desconhecido'));
                }
            } catch (e) {
                alert('Erro: ' + e.message);
            } finally {
                btn.innerHTML = old;
                btn.disabled = false;
            }
        }

        async function goToTenant(tenant_id) {
            try {
                let request = await fetch("{{ route('landlord.sso.emit', ['tenant' => '%tenant_id%']) }}".replace('%tenant_id%', tenant_id), {
                    method: 'POST',
                    headers: {'Accept': 'application/json'}
                })
                let response = await request.json();
                if (!response.success) {
                    alert("Erro: " + response.message.sso);
                }
                window.open(response.url, '_blank');
            } catch (e) {
                alert("Erro:  " + e.message);
                console.log( e.message)
            }
        }

        async function logout() {
            try {
                let request = await fetch(URL_LOGOUT, { method: 'POST', headers: { 'Accept': 'application/json' } })
                let response = await request.json();
                if (response.success) {
                    window.location.href = "{{route('landlord.login')}}";
                }
            } catch (e) {
                alert ("Erro tentar Logout: " + e.message);
            }
        }
    </script>
</body>
</html>
