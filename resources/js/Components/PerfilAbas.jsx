/**
 * Abas do "Meu perfil" — comum às páginas do perfil do servidor.
 */
import { Link, usePage } from '@inertiajs/react';

const ABAS = [
    { href: '/perfil', icone: 'fa-user', rotulo: 'Meus dados' },
    { href: '/perfil/seguranca', icone: 'fa-shield-alt', rotulo: 'Segurança' },
    { href: '/perfil/ausencias', icone: 'fa-umbrella-beach', rotulo: 'Ausências' },
    { href: '/perfil/preferencias', icone: 'fa-sliders-h', rotulo: 'Preferências' },
    { href: '/perfil/certificados', icone: 'fa-id-card', rotulo: 'Certificados' },
];

export default function PerfilAbas() {
    const { url } = usePage();
    const caminho = url.split('?')[0];

    return (
        <div className="flex flex-wrap gap-1 mb-4 border-b border-gray-200">
            {ABAS.map((a) => {
                const ativa = caminho === a.href;
                return (
                    <Link key={a.href} href={a.href}
                        className={`px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors ${ativa
                            ? 'border-cyan-600 text-cyan-700' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                        <i className={`fas ${a.icone} mr-1.5 text-xs`} />{a.rotulo}
                    </Link>
                );
            })}
        </div>
    );
}
