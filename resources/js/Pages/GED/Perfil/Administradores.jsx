/** Meu perfil › Administradores — quem administra o sistema (TR 4.7.21). */
import { Head } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import PerfilAbas from '../../../Components/PerfilAbas';
import Avatar from '../../../Components/Avatar';

export default function Administradores({ administradores }) {
    return (
        <AdminLayout>
            <Head title="Administradores" />
            <PageHeader title="Meu perfil" subtitle="Quem administra o sistema — procure-os para acesso, cadastro e senha" />
            <PerfilAbas />
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                {administradores.map((a) => (
                    <div key={a.id} className="ds-card p-4 flex items-start gap-3">
                        <Avatar usuario={a} tamanho="lg" />
                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-gray-800 truncate">{a.name}</p>
                            <p className="text-[11px] text-cyan-700 font-medium">{a.papel}</p>
                            {(a.cargo || a.setor) && <p className="text-xs text-gray-500 truncate">{[a.cargo, a.setor].filter(Boolean).join(' · ')}</p>}
                            <p className="text-xs text-gray-600 mt-1 truncate"><i className="fas fa-envelope mr-1 text-gray-400" /><a href={`mailto:${a.email}`} className="hover:underline">{a.email}</a></p>
                            {a.telefone && <p className="text-xs text-gray-600"><i className="fas fa-phone mr-1 text-gray-400" />{a.telefone}</p>}
                        </div>
                    </div>
                ))}
                {administradores.length === 0 && <p className="text-sm text-gray-400">Nenhum administrador cadastrado.</p>}
            </div>
        </AdminLayout>
    );
}
