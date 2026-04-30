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
        name:       usuario?.name || '',
        email:      usuario?.email || '',
        cpf:        usuario?.cpf || '',
        password:   '',
        tipo:       usuario?.tipo || 'interno',
        ug_id:      usuario?.ug_id || '',
        unidade_id: usuario?.unidade_id || '',
        roles:      usuario?.roles || [],
    });

    const ugSel = ugs.find(u => u.id === Number(data.ug_id));
    const unidadesDaUg = useMemo(() => {
        if (! data.ug_id) return [];
        return unidades
            .filter(u => u.ug_id === Number(data.ug_id))
            .sort((a, b) => a.nivel - b.nivel || a.nome.localeCompare(b.nome));
    }, [data.ug_id, unidades]);

    const unidadeSel = unidades.find(u => u.id === Number(data.unidade_id));
    const rolesSel = roles.filter(r => data.roles.includes(r.id));

    // Campos obrigatorios
    const obrigatoriosFaltando =
        ! data.name.trim() ||
        ! data.email.trim() ||
        (! isEdit && ! data.password.trim()) ||
        ! data.tipo;

    const onUgChange = (val) => {
        setData(d => ({ ...d, ug_id: val ? Number(val) : '', unidade_id: '' }));
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
        { icone: 'fa-user',         label: 'Nome',     valor: data.name,    vazio: ! data.name },
        { icone: 'fa-id-card',      label: 'CPF',      valor: formatarCpf(data.cpf), vazio: ! data.cpf },
        { icone: 'fa-envelope',     label: 'E-mail',   valor: data.email,   vazio: ! data.email },
        { icone: 'fa-key',          label: 'Senha',    valor: data.password ? '••••••••' : (isEdit ? 'manter atual' : ''), vazio: ! isEdit && ! data.password },
        { icone: 'fa-building',     label: 'UG',       valor: ugSel ? `${ugSel.codigo} · ${ugSel.nome}` : '', vazio: ! ugSel },
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

                {/* Vinculo com organograma */}
                <CadastroSecao
                    icone="fa-sitemap"
                    titulo="Vinculo com Estrutura"
                    descricao={data.tipo === 'externo'
                        ? 'Externos podem opcionalmente ter UG associada para receber notificacoes do Portal de Servicos'
                        : 'Selecione a UG e a unidade onde o usuario atua'}
                >
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Unidade Gestora</label>
                            <select value={data.ug_id} onChange={(e) => onUgChange(e.target.value)} className="ds-input">
                                <option value="">— Nenhuma —</option>
                                {ugs.map(u => (
                                    <option key={u.id} value={u.id}>{u.codigo} · {u.nome}</option>
                                ))}
                            </select>
                        </div>
                        {data.tipo === 'interno' && data.ug_id && (
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Unidade no organograma</label>
                                <select value={data.unidade_id}
                                    onChange={(e) => setData('unidade_id', e.target.value ? Number(e.target.value) : '')}
                                    className="ds-input">
                                    <option value="">— Selecionar —</option>
                                    {unidadesDaUg.map(u => {
                                        const labelN = ugSel?.[`nivel_${u.nivel}_label`] || `N${u.nivel}`;
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
                            </div>
                        )}
                    </div>
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
