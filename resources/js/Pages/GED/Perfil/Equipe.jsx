/**
 * Meu perfil › Minha equipe — servidores dos setores pelos quais você
 * responde; reative quem foi bloqueado por inatividade (TR 4.7.29).
 */
import { Head, router } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import PerfilAbas from '../../../Components/PerfilAbas';
import Avatar from '../../../Components/Avatar';
import Button from '../../../Components/Button';
import { useConfirm } from '../../../Components/ConfirmProvider';

const SITUACAO = {
    ativo: ['Ativo', 'bg-emerald-100 text-emerald-700'],
    inatividade: ['Bloqueado por inatividade', 'bg-amber-100 text-amber-800'],
    suspenso: ['Suspenso pelo administrador', 'bg-red-100 text-red-700'],
};

export default function Equipe({ servidores, dias }) {
    const confirmar = useConfirm();
    return (
        <AdminLayout>
            <Head title="Minha equipe" />
            <PageHeader title="Meu perfil" subtitle={`Servidores dos setores pelos quais você responde. Quem passa ${dias} dias sem entrar é bloqueado e você pode reativar.`} />
            <PerfilAbas />
            <div className="ds-card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                            <th className="px-4 py-3">Servidor</th><th className="px-4 py-3">Setor</th>
                            <th className="px-4 py-3">Último acesso</th><th className="px-4 py-3">Situação</th><th />
                        </tr>
                    </thead>
                    <tbody>
                        {servidores.map((s) => (
                            <tr key={s.id} className="border-b border-gray-50">
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <Avatar usuario={s} tamanho="md" />
                                        <div><p className="font-medium text-gray-800">{s.name}</p>
                                            <p className="text-xs text-gray-400">{s.cargo || s.email}</p></div>
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-gray-600">{s.setor}</td>
                                <td className="px-4 py-3 text-gray-600">{s.ultimo_acesso || <span className="text-gray-400">nunca entrou</span>}</td>
                                <td className="px-4 py-3">
                                    <span className={`text-[11px] px-2 py-0.5 rounded-full font-medium ${SITUACAO[s.situacao][1]}`} title={s.motivo || ''}>
                                        {SITUACAO[s.situacao][0]}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    {s.situacao === 'inatividade' && (
                                        <Button size="sm" icon="fas fa-user-check" onClick={async () => {
                                            if (await confirmar({ titulo: `Reativar o acesso de ${s.name}?`, rotuloConfirmar: 'Reativar' }))
                                                router.post(`/perfil/equipe/${s.id}/reativar`, {}, { preserveScroll: true });
                                        }}>Reativar</Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {servidores.length === 0 && (
                            <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Nenhum servidor lotado nos seus setores.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
