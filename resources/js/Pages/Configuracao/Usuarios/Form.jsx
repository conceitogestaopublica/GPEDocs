/**
 * Cadastro de Usuario — tela dedicada (padrao "wizard com resumo lateral")
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import CadastroLayout, { CadastroSecao } from '../../../Components/CadastroLayout';

export default function UsuarioForm({ usuario, roles = [], ugs = [], unidades = [] }) {
    const isEdit = !! usuario;

    const { data, setData, post, put, processing, errors } = useForm({
        name:        usuario?.name || '',
        email:       usuario?.email || '',
        cpf:         usuario?.cpf || '',
        password:    '',
        tipo:        usuario?.tipo || 'interno',
        super_admin: !! usuario?.super_admin,
        ug_ids:      usuario?.ug_ids || [],
        unidade_id:  usuario?.unidade_id || '',
        roles:       usuario?.roles || [],
    });

    // UG "principal" para o dropdown de unidade = primeira do array
    const ugPrincipalId = data.ug_ids[0];
    const ugPrincipal = ugs.find(u => u.id === Number(ugPrincipalId));

    const unidadesDaUg = useMemo(() => {
        if (! ugPrincipalId) return [];
        return unidades
            .filter(u => u.ug_id === Number(ugPrincipalId))
            .sort((a, b) => a.nivel - b.nivel || a.nome.localeCompare(b.nome));
    }, [ugPrincipalId, unidades]);

    const unidadeSel = unidades.find(u => u.id === Number(data.unidade_id));
    const rolesSel = roles.filter(r => data.roles.includes(r.id));
    const ugsSel = ugs.filter(u => data.ug_ids.includes(u.id));

    // Campos obrigatorios
    const obrigatoriosFaltando =
        ! data.name.trim() ||
        ! data.email.trim() ||
        (! isEdit && ! data.password.trim()) ||
        ! data.tipo;

    const toggleUg = (id) => {
        const novas = data.ug_ids.includes(id)
            ? data.ug_ids.filter(x => x !== id)
            : [...data.ug_ids, id];
        // Se removeu a primeira (que era a "principal" para selecao de unidade),
        // limpa a unidade tambem
        const removeuPrincipal = data.ug_ids[0] === id && ! novas.includes(id);
        setData(d => ({
            ...d,
            ug_ids: novas,
            unidade_id: removeuPrincipal ? '' : d.unidade_id,
        }));
    };

    const definirUgPrincipal = (id) => {
        // Coloca essa UG como primeira do array
        setData(d => ({
            ...d,
            ug_ids: [id, ...d.ug_ids.filter(x => x !== id)],
            unidade_id: '',
        }));
    };

    const toggleRole = (id) => {
        setData('roles',
            data.roles.includes(id) ? data.roles.filter(x => x !== id) : [...data.roles, id]
        );
    };

    const onSalvar = () => {
        const opts = { preserveScroll: true };
        if (isEdit) put(`/configuracoes/usuarios/${usuario.id}`, opts);
        else        post('/configuracoes/usuarios', opts);
    };

    const onCancelar = () => router.visit('/configuracoes/usuarios');

    // Resumo lateral — calculado dinamicamente
    const resumo = [
        { icone: 'fa-id-badge',     label: 'Tipo',     valor: data.tipo === 'externo' ? 'Externo' : 'Interno' },
        ...(data.super_admin ? [{ icone: 'fa-crown', label: 'Super Admin', valor: 'Sim' }] : []),
        { icone: 'fa-user',         label: 'Nome',     valor: data.name,    vazio: ! data.name },
        { icone: 'fa-id-card',      label: 'CPF',      valor: formatarCpf(data.cpf), vazio: ! data.cpf },
        { icone: 'fa-envelope',     label: 'E-mail',   valor: data.email,   vazio: ! data.email },
        { icone: 'fa-key',          label: 'Senha',    valor: data.password ? '••••••••' : (isEdit ? 'manter atual' : ''), vazio: ! isEdit && ! data.password },
        { icone: 'fa-building',     label: 'UGs',
          valor: ugsSel.length === 0 ? '' : ugsSel.map(u => u.codigo).join(', ') + (ugsSel.length === 1 ? ` · ${ugsSel[0].nome}` : ''),
          vazio: ugsSel.length === 0 },
        { icone: 'fa-sitemap',      label: 'Unidade',  valor: unidadeSel?.nome, vazio: ! unidadeSel },
        { icone: 'fa-shield-alt',   label: 'Perfis',   valor: rolesSel.map(r => r.nome).join(', '), vazio: rolesSel.length === 0 },
    ];

    return (
        <AdminLayout>
            <Head title={isEdit ? `Editar ${usuario.name}` : 'Novo Usuario'} />

            <CadastroLayout
                titulo={isEdit ? 'Editar Usuario' : 'Novo Usuario'}
                subtitulo={isEdit ? `Atualize os dados de ${usuario.name}` : 'Cadastre um usuario interno ou externo'}
                voltarHref="/configuracoes/usuarios"
                voltarLabel="Voltar para usuarios"
                resumo={resumo}
                obrigatoriosFaltando={obrigatoriosFaltando}
                onCancelar={onCancelar}
                onSalvar={onSalvar}
                processing={processing}
                labelSalvar={isEdit ? 'Salvar alteracoes' : 'Criar Usuario'}
                iconeSalvar={isEdit ? 'fas fa-save' : 'fas fa-user-plus'}
            >
                {/* Tipo de usuario */}
                <CadastroSecao
                    icone="fa-user-tag"
                    titulo="Tipo de Usuario"
                    descricao="Define se o usuario faz parte da estrutura interna ou e um cidadao/parceiro externo"
                >
                    <div className="grid grid-cols-2 gap-2">
                        {[
                            { v: 'interno', icone: 'fa-id-badge',  cor: 'blue',  titulo: 'Interno', desc: 'Vinculado a uma unidade do organograma' },
                            { v: 'externo', icone: 'fa-user-tag',  cor: 'amber', titulo: 'Externo', desc: 'Cidadao/parceiro, sem vinculo com unidade' },
                        ].map(op => {
                            const ativo = data.tipo === op.v;
                            const sel = ativo
                                ? (op.cor === 'blue' ? 'border-blue-500 bg-blue-50' : 'border-amber-500 bg-amber-50')
                                : 'border-gray-200 hover:bg-gray-50';
                            const corIcone = op.cor === 'blue' ? 'text-blue-600' : 'text-amber-600';
                            return (
                                <label key={op.v}
                                    className={`flex items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-colors ${sel}`}>
                                    <input type="radio" value={op.v} checked={ativo}
                                        onChange={(e) => setData('tipo', e.target.value)} className="hidden" />
                                    <i className={`fas ${op.icone} ${corIcone}`} />
                                    <div>
                                        <p className="text-sm font-semibold text-gray-800">{op.titulo}</p>
                                        <p className="text-[10px] text-gray-500">{op.desc}</p>
                                    </div>
                                </label>
                            );
                        })}
                    </div>
                </CadastroSecao>

                {/* Identificacao */}
                <CadastroSecao
                    icone="fa-user"
                    titulo="Identificacao"
                    descricao="Nome, CPF e dados de acesso"
                >
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Nome <span className="text-red-500">*</span></label>
                            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                className="ds-input" placeholder="Nome completo" />
                            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">CPF</label>
                            <input type="text" value={data.cpf} onChange={(e) => setData('cpf', e.target.value)}
                                className="ds-input" maxLength={14} placeholder="000.000.000-00" />
                            {errors.cpf && <p className="mt-1 text-xs text-red-600">{errors.cpf}</p>}
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">E-mail <span className="text-red-500">*</span></label>
                            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)}
                                className="ds-input" placeholder="email@exemplo.com" />
                            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">
                                Senha {! isEdit && <span className="text-red-500">*</span>}
                                {isEdit && <span className="text-gray-400 font-normal"> (deixe vazio para manter)</span>}
                            </label>
                            <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)}
                                className="ds-input" autoComplete="new-password"
                                placeholder={isEdit ? '••••••••' : 'Minimo 8 caracteres'} />
                            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                        </div>
                    </div>
                </CadastroSecao>

                {/* Acesso multi-UG */}
                <CadastroSecao
                    icone="fa-building"
                    titulo="Unidades Gestoras com acesso"
                    descricao="O usuario podera escolher entre estas UGs ao logar. A primeira marcada e a UG principal (define a unidade do organograma)."
                >
                    {ugs.length === 0 ? (
                        <p className="text-xs text-gray-400">Nenhuma UG cadastrada.</p>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                            {ugs.map(u => {
                                const ativo = data.ug_ids.includes(u.id);
                                const principal = data.ug_ids[0] === u.id;
                                return (
                                    <div key={u.id} className={`p-3 rounded-xl border-2 transition-colors ${
                                        ativo ? (principal ? 'border-blue-500 bg-blue-50' : 'border-blue-300 bg-blue-50/50') : 'border-gray-200 hover:bg-gray-50'
                                    }`}>
                                        <label className="flex items-start gap-2 cursor-pointer">
                                            <input type="checkbox" checked={ativo} onChange={() => toggleUg(u.id)}
                                                className="rounded border-gray-300 text-blue-600 mt-0.5" />
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-semibold text-gray-800 truncate">{u.nome}</p>
                                                <p className="text-[10px] text-gray-500 font-mono">{u.codigo}</p>
                                            </div>
                                        </label>
                                        {ativo && data.ug_ids.length > 1 && (
                                            <div className="mt-2 pt-2 border-t border-blue-200">
                                                {principal ? (
                                                    <span className="text-[10px] text-blue-700 font-bold">
                                                        <i className="fas fa-star mr-1" />
                                                        UG Principal
                                                    </span>
                                                ) : (
                                                    <button type="button" onClick={() => definirUgPrincipal(u.id)}
                                                        className="text-[10px] text-blue-600 hover:underline">
                                                        Definir como principal
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CadastroSecao>

                {/* Unidade no organograma (so internos com pelo menos 1 UG) */}
                {data.tipo === 'interno' && ugPrincipalId && (
                    <CadastroSecao
                        icone="fa-sitemap"
                        titulo="Unidade no Organograma"
                        descricao={`Unidade dentro do organograma de ${ugPrincipal?.nome} (UG principal)`}
                    >
                        <select value={data.unidade_id}
                            onChange={(e) => setData('unidade_id', e.target.value ? Number(e.target.value) : '')}
                            className="ds-input">
                            <option value="">— Sem vinculo de unidade —</option>
                            {unidadesDaUg.map(u => {
                                const labelN = ugPrincipal?.[`nivel_${u.nivel}_label`] || `N${u.nivel}`;
                                const indent = '— '.repeat(u.nivel - 1);
                                return (
                                    <option key={u.id} value={u.id}>
                                        {indent}[{labelN}] {u.nome}
                                    </option>
                                );
                            })}
                        </select>
                        {unidadesDaUg.length === 0 && (
                            <p className="mt-1 text-[10px] text-amber-600">
                                Esta UG ainda nao possui organograma cadastrado.
                            </p>
                        )}
                    </CadastroSecao>
                )}

                {/* Super Admin */}
                <CadastroSecao
                    icone="fa-crown"
                    titulo="Super Administrador"
                    descricao="Super-admins veem dados de todas as UGs e podem trocar entre elas livremente. Use com moderacao."
                >
                    <label className={`flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors ${
                        data.super_admin ? 'border-amber-500 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'
                    }`}>
                        <input type="checkbox" checked={data.super_admin}
                            onChange={(e) => setData('super_admin', e.target.checked)}
                            className="rounded border-gray-300 text-amber-600 mt-0.5" />
                        <div>
                            <p className="text-sm font-semibold text-gray-800">
                                <i className="fas fa-crown text-amber-500 mr-1" />
                                Conceder privilegios de super-admin
                            </p>
                            <p className="text-[11px] text-gray-500 leading-tight">
                                Permite acessar dados de qualquer UG e ignorar o filtro multi-tenant.
                            </p>
                        </div>
                    </label>
                </CadastroSecao>

                {/* Perfis */}
                <CadastroSecao
                    icone="fa-shield-alt"
                    titulo="Perfis e Permissoes"
                    descricao="Selecione um ou mais perfis para definir o que o usuario pode acessar"
                >
                    {roles.length === 0 ? (
                        <p className="text-xs text-gray-400">Nenhum perfil cadastrado. Cadastre em Configuracoes → Perfis e Permissoes.</p>
                    ) : (
                        <div className="grid grid-cols-2 gap-2">
                            {roles.map(role => {
                                const ativo = data.roles.includes(role.id);
                                return (
                                    <label key={role.id}
                                        className={`flex items-start gap-2 p-3 rounded-xl border-2 cursor-pointer transition-colors ${
                                            ativo ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'
                                        }`}>
                                        <input type="checkbox" checked={ativo} onChange={() => toggleRole(role.id)}
                                            className="rounded border-gray-300 text-blue-600 mt-0.5" />
                                        <div>
                                            <p className="text-sm font-semibold text-gray-800">{role.nome}</p>
                                            {role.descricao && <p className="text-[10px] text-gray-500 leading-tight">{role.descricao}</p>}
                                        </div>
                                    </label>
                                );
                            })}
                        </div>
                    )}
                </CadastroSecao>
            </CadastroLayout>
        </AdminLayout>
    );
}

function formatarCpf(cpf) {
    const d = (cpf || '').replace(/\D/g, '');
    if (d.length !== 11) return cpf;
    return `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9)}`;
}
