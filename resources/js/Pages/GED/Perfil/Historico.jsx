/** Meu perfil › Histórico — o que mudou no meu cadastro (TR 4.7.22). */
import { Head } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';
import PerfilAbas from '../../../Components/PerfilAbas';
import HistoricoCadastro from '../../../Components/HistoricoCadastro';

export default function Historico({ historico }) {
    return (
        <AdminLayout>
            <Head title="Histórico do cadastro" />
            <PageHeader title="Meu perfil" subtitle="Alterações no seu cadastro: o quê, quando e quem alterou" />
            <PerfilAbas />
            <div className="ds-card"><HistoricoCadastro historico={historico} /></div>
        </AdminLayout>
    );
}
