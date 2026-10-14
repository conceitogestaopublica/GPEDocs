<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>GPE Cloud — Vínculos Super-Admin × Tenant</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-100 min-h-screen">
    <div class="max-w-6xl mx-auto py-8 px-4">
        <header class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    <i class="fas fa-link text-blue-600 mr-2"></i>Vínculos Super-Admin × Tenant
                </h1>
                <p class="text-sm text-slate-500">Define como cada super-admin entra (via SSO) em cada município.</p>
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
            <a href="{{ route('landlord.tenants.index') }}"
                class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i class="fas fa-building-shield mr-1"></i>Tenants
            </a>
            <span class="px-3 py-1.5 rounded-lg bg-blue-600 text-white font-medium">
                <i class="fas fa-link mr-1"></i>Vínculos
            </span>
        </nav>

        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-slate-100 flex justify-between items-center">
                <h2 class="font-semibold text-slate-700">Vínculos cadastrados ({{ $links->count() }})</h2>
                <button onclick="abrirCriar()"
                    class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    <i class="fas fa-plus mr-1"></i>Novo vínculo
                </button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="px-3 py-2">Super-Admin</th>
                        <th class="px-3 py-2">Tenant</th>
                        <th class="px-3 py-2">Usuário no tenant (email)</th>
                        <th class="px-3 py-2 text-center">Padrão</th>
                        <th class="px-3 py-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($links as $l)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2">
                                <div class="font-medium text-slate-700">{{ $l->sa_email }}</div>
                                @unless($l->sa_active)
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-200 text-slate-600">inativo</span>
                                @endunless
                            </td>
                            <td class="px-3 py-2">
                                <span class="font-mono text-blue-700">{{ $l->t_domain }}</span>
                                <div class="text-[11px] text-slate-400">{{ $l->t_nome }}</div>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $l->tenant_user_email }}</td>
                            <td class="px-3 py-2 text-center">
                                @if($l->default)
                                    <span class="px-2 py-0.5 text-[11px] rounded bg-emerald-100 text-emerald-700">
                                        <i class="fas fa-star mr-0.5"></i>Padrão
                                    </span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex gap-1 justify-end">
                                    <button type="button" onclick="abrirEditar({{ $l->id }})"
                                        class="px-2 py-1 text-xs rounded hover:bg-blue-100 text-blue-700" title="Editar">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button"
                                        onclick="abrirExcluir({{ $l->id }}, '{{ addslashes($l->sa_email.' → '.$l->t_domain) }}')"
                                        class="px-2 py-1 text-xs rounded hover:bg-rose-100 text-rose-700" title="Excluir vínculo">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-400">Nenhum vínculo cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="text-xs text-slate-500 text-center">
            GPE Cloud · landlord database · o email do vínculo deve casar com <code>tenant.usuario.email</code> no banco do município.
        </div>
    </div>

    {{-- Modal: criar / editar vínculo --}}
    <div id="mForm" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <form id="frmLink" method="POST" action="{{ route('landlord.links.store') }}"
            class="bg-white rounded-xl shadow-xl max-w-lg w-full p-5 max-h-[90vh] overflow-y-auto">
            @csrf
            <input type="hidden" name="_method" id="frmMethod" value="POST">
            <div class="flex justify-between items-center mb-3">
                <h3 id="frmTitulo" class="font-semibold">Novo Vínculo</h3>
                <button type="button" onclick="fecharForm()" class="text-slate-400 hover:text-slate-700">×</button>
            </div>
            <div class="space-y-3 text-sm">
                <div>
                    <label class="text-[11px] text-slate-500">Super-Admin</label>
                    <select name="super_admin_id" id="fSuperAdmin" required
                        class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">
                        <option value="">— selecione —</option>
                        @foreach($superAdmins as $sa)
                            <option value="{{ $sa->id }}">{{ $sa->email }}{{ $sa->active ? '' : ' (inativo)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[11px] text-slate-500">Tenant</label>
                    <select name="tenant_id" id="fTenant" required
                        class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">
                        <option value="">— selecione —</option>
                        @foreach($tenants as $t)
                            <option value="{{ $t->id }}">{{ $t->subdomain }} — {{ $t->nome }}{{ $t->active ? '' : ' (inativo)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[11px] text-slate-500">Email do usuário no tenant</label>
                    <input name="tenant_user_email" id="fEmail" type="email" required
                        placeholder="usuario@municipio.gov.br"
                        class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">
                    <p class="text-[11px] text-slate-400 mt-1">Deve existir como <code>usuario.email</code> no banco do tenant.</p>
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-600 select-none">
                    <input type="checkbox" id="fDefault" name="default" value="1" class="rounded border-slate-300">
                    <span>Usar como vínculo <strong>padrão</strong> deste super-admin neste tenant (SSO sem escolha de vínculo usa este).</span>
                </label>
                <textarea name="observacoes" id="fObs" placeholder="Observações (opcional)" rows="2"
                    class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50"></textarea>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="fecharForm()" class="px-3 py-2 text-sm rounded hover:bg-slate-100">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    <i class="fas fa-check mr-1"></i><span id="frmSubmitTxt">Criar vínculo</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Modal: confirmar exclusão --}}
    <div id="mDel" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <form id="frmDel" method="POST" class="bg-white rounded-xl shadow-xl max-w-md w-full p-5">
            @csrf
            @method('DELETE')
            <h3 class="font-semibold mb-2 text-rose-700"><i class="fas fa-triangle-exclamation mr-1"></i>Excluir vínculo?</h3>
            <p class="text-sm text-slate-600 mb-4">
                Removendo o vínculo <code id="delLabel" class="font-mono bg-slate-100 px-1 rounded"></code>.
                Se for o padrão e houver outros vínculos para o mesmo par, um deles será promovido a padrão.
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
        const URL_STORE = "{{ route('landlord.links.store') }}";
        const URL_BASE  = "{{ url('landlord/links') }}";
        const URL_LOGOUT  = "{{ url('landlord/logout') }}";

        function abrirCriar() {
            const f = document.getElementById('frmLink');
            f.reset();
            f.action = URL_STORE;
            document.getElementById('frmMethod').value = 'POST';
            document.getElementById('frmTitulo').textContent = 'Novo Vínculo';
            document.getElementById('frmSubmitTxt').textContent = 'Criar vínculo';
            document.getElementById('fSuperAdmin').disabled = false;
            document.getElementById('fTenant').disabled = false;
            document.getElementById('mForm').classList.remove('hidden');
        }

        async function abrirEditar(id) {
            try {
                const r = await fetch(`${URL_BASE}/${id}`, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('Falha ao carregar vínculo');
                const d = await r.json();
                const f = document.getElementById('frmLink');
                f.reset();
                f.action = `${URL_BASE}/${id}`;
                document.getElementById('frmMethod').value = 'PUT';
                document.getElementById('frmTitulo').textContent = 'Editar Vínculo';
                document.getElementById('frmSubmitTxt').textContent = 'Salvar alterações';

                // super-admin e tenant são imutáveis na edição: pré-seleciona e desabilita.
                const sa = document.getElementById('fSuperAdmin');
                const tn = document.getElementById('fTenant');
                sa.value = d.super_admin_id; sa.disabled = true;
                tn.value = d.tenant_id;      tn.disabled = true;
                document.getElementById('fEmail').value   = d.tenant_user_email ?? '';
                document.getElementById('fDefault').checked = !!d.default;
                document.getElementById('fObs').value     = d.observacoes ?? '';

                document.getElementById('mForm').classList.remove('hidden');
            } catch (e) {
                alert('Erro ao carregar vínculo: ' + e.message);
            }
        }

        function fecharForm() {
            document.getElementById('mForm').classList.add('hidden');
        }

        function abrirExcluir(id, label) {
            const f = document.getElementById('frmDel');
            f.action = `${URL_BASE}/${id}`;
            document.getElementById('delLabel').textContent = label;
            document.getElementById('mDel').classList.remove('hidden');
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
