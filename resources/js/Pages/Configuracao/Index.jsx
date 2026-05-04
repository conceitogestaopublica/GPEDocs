/**
 * Configuracoes — visao geral
 */
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';

const ATALHOS = [
    {
        titulo: 'Unidades Gestoras',
        desc: 'Cadastro de UGs e gestao do organograma com 3 niveis nomeaveis',
        icon: 'fas fa-building',
        href: '/configuracoes/ugs',
        cor: 'from-indigo-500 to-blue-600',
        corLight: 'bg-indigo-50 text-indigo-600',
    },
    {
        titulo: 'Usuarios',
        desc: 'Cadastro de usuarios internos e externos, vinculo com unidade',
        icon: 'fas fa-users',
        href: '/configuracoes/usuarios',
        cor: 'from-red-500 to-pink-600',
        corLight: 'bg-red-50 text-red-600',
    },
    {
        titulo: 'Perfis e Permissoes',
        desc: 'Roles e permissoes de acesso aos modulos do sistema',
        icon: 'fas fa-shield-alt',
        href: '/configuracoes/perfis',
        cor: 'from-slate-500 to-gray-700',
        corLight: 'bg-slate-50 text-slate-600',
    },
    {
        titulo: 'Carta de Servicos',
        desc: 'Catalogo de servicos publicado no Portal do Cidadao',
        icon: 'fas fa-clipboard-list',
        href: '/configuracoes/carta-servicos',
        cor: 'from-blue-500 to-indigo-600',
        corLight: 'bg-blue-50 text-blue-600',
    },
    {
        titulo: 'Solicitacoes do Portal',
        desc: 'Atender pedidos feitos pelos cidadaos via Portal',
        icon: 'fas fa-inbox',
        href: '/configuracoes/solicitacoes-portal',
        cor: 'from-indigo-500 to-purple-600',
        corLight: 'bg-indigo-50 text-indigo-600',
    },
];

export default function Configuracoes() {
    return (
        <AdminLayout>
            <Head title="Configuracoes" />
            <PageHeader title="Configuracoes" subtitle="Estrutura organizacional, usuarios e permissoes" />

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {ATALHOS.map(a => (
                    <Link key={a.href} href={a.href}
                        className="group bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-lg hover:border-transparent hover:ring-2 hover:ring-blue-200 transition-all">
                        <div className={`w-12 h-12 bg-gradient-to-br ${a.cor} rounded-xl flex items-center justify-center mb-3 shadow-md group-hover:scale-110 transition-transform`}>
                            <i className={`${a.icon} text-white text-lg`} />
                        </div>
                        <h3 className="text-sm font-bold text-gray-800 mb-1">{a.titulo}</h3>
                        <p className="text-xs text-gray-500 leading-relaxed">{a.desc}</p>
                    </Link>
                ))}
            </div>
        </AdminLayout>
    );
}
