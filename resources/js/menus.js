/**
 * Menus dos módulos do GPE Docs — fonte ÚNICA da sidebar (AdminLayout), da busca global
 * (CommandPalette, Ctrl+K) e do botão de favoritar do cabeçalho (FavoritarRotina).
 */
// Menus separados por modulo
export const MENU_GED = [
    { title: 'Dashboard', icon: 'fas fa-tachometer-alt', href: '/dashboard', color: 'text-blue-600 bg-blue-100', permissao: 'documento.visualizar' },
    { section: 'label', label: 'Documentos' },
    { title: 'Repositorio', icon: 'fas fa-folder-tree', href: '/repositorio', color: 'text-amber-600 bg-amber-100', permissao: 'documento.visualizar' },
    { title: 'Favoritos', icon: 'fas fa-star', href: '/repositorio?filtro=favoritos', color: 'text-yellow-600 bg-yellow-100', permissao: 'documento.visualizar' },
    { title: 'Recentes', icon: 'fas fa-clock', href: '/repositorio?filtro=recentes', color: 'text-cyan-600 bg-cyan-100', permissao: 'documento.visualizar' },
    { title: 'Mais Acessados', icon: 'fas fa-fire', href: '/repositorio?filtro=populares', color: 'text-orange-600 bg-orange-100', permissao: 'documento.visualizar' },
    { section: 'label', label: 'Acoes' },
    { title: 'Capturar', icon: 'fas fa-camera', href: '/capturar', color: 'text-purple-600 bg-purple-100', permissao: 'documento.criar' },
    { title: 'Assinaturas', icon: 'fas fa-file-signature', href: '/assinaturas', color: 'text-emerald-600 bg-emerald-100' },
    { section: 'label', label: 'Administracao' },
    { title: 'Tipos Documentais', icon: 'fas fa-file-signature', href: '/admin/tipos-documentais', color: 'text-violet-600 bg-violet-100', permissao: 'admin.tipos_documentais' },
];

export const MENU_CONFIGURACOES = [
    { title: 'Visao Geral', icon: 'fas fa-cog', href: '/configuracoes', color: 'text-slate-600 bg-slate-100', permissao: 'admin.configuracoes' },
    { section: 'label', label: 'Estrutura' },
    { title: 'Unidades Gestoras', icon: 'fas fa-building', href: '/configuracoes/ugs', color: 'text-indigo-600 bg-indigo-100', permissao: 'admin.ugs' },
    { section: 'label', label: 'Acessos' },
    { title: 'Usuarios', icon: 'fas fa-users', href: '/configuracoes/usuarios', color: 'text-red-600 bg-red-100', permissao: 'admin.usuarios' },
    { title: 'Perfis e Permissoes', icon: 'fas fa-shield-alt', href: '/configuracoes/perfis', color: 'text-slate-600 bg-slate-100', permissao: 'admin.roles' },
    { section: 'label', label: 'Portal do Cidadao' },
    { title: 'Carta de Servicos', icon: 'fas fa-clipboard-list', href: '/configuracoes/carta-servicos', color: 'text-blue-600 bg-blue-100', permissao: 'portal.carta_servicos' },
    { title: 'Solicitacoes', icon: 'fas fa-inbox', href: '/configuracoes/solicitacoes-portal', color: 'text-indigo-600 bg-indigo-100', permissao: 'portal.atendimento' },
    { section: 'label', label: 'Integracoes' },
    { title: 'Sistemas Integrados', icon: 'fas fa-plug', href: '/configuracoes/sistemas-integrados', color: 'text-violet-600 bg-violet-100', permissao: 'admin.sistemas_integrados' },
];

export const MENU_GEPSP = [
    {
        title: 'Caixa de Entrada', icon: 'fas fa-inbox', color: 'text-blue-600 bg-blue-100',
        children: [
            { title: 'Caixa Pessoal',          icon: 'fas fa-inbox',          href: '/flow/inbox-pessoal',         color: 'text-blue-600 bg-blue-100' },
            { title: 'Caixa Setor',            icon: 'fas fa-users',          href: '/flow/inbox-setor',           color: 'text-indigo-600 bg-indigo-100' },
            { title: 'Aguardando Assinatura',  icon: 'fas fa-file-signature', href: '/flow/aguardando-assinatura', color: 'text-purple-600 bg-purple-100' },
        ],
    },
    {
        title: 'Em Andamento', icon: 'fas fa-share', color: 'text-orange-600 bg-orange-100',
        children: [
            { title: 'Em Tramitacao', icon: 'fas fa-share',        href: '/flow/em-tramitacao', color: 'text-orange-600 bg-orange-100' },
            { title: 'Concluidos',    icon: 'fas fa-check-double', href: '/flow/concluidos',    color: 'text-green-600 bg-green-100' },
        ],
    },
    {
        title: 'Comunicacao', icon: 'fas fa-envelope', color: 'text-cyan-600 bg-cyan-100',
        children: [
            { title: 'Novo Memorando',     icon: 'fas fa-envelope',         href: '/memorandos/create',  color: 'text-amber-600 bg-amber-100', permissao: 'comunicacao.enviar' },
            { title: 'Nova Circular',      icon: 'fas fa-bullhorn',         href: '/circulares/create',  color: 'text-rose-600 bg-rose-100', permissao: 'comunicacao.enviar' },
            { title: 'Novo Oficio',        icon: 'fas fa-file-alt',         href: '/oficios/create',     color: 'text-cyan-600 bg-cyan-100', permissao: 'comunicacao.enviar' },
            { title: 'Controle de Oficios', icon: 'fas fa-book',            href: '/oficios/controle',   color: 'text-cyan-600 bg-cyan-100' },
        ],
    },
    {
        title: 'Processos Administrativos', icon: 'fas fa-folder-open', color: 'text-indigo-600 bg-indigo-100',
        children: [
            { title: 'Painel',           icon: 'fas fa-tachometer-alt', href: '/processos/dashboard', color: 'text-teal-600 bg-teal-100', permissao: 'processo.visualizar' },
            { title: 'Novo Processo',    icon: 'fas fa-plus-circle',    href: '/processos/create',    color: 'text-green-600 bg-green-100', permissao: 'processo.criar' },
            { title: 'Todos Processos',  icon: 'fas fa-folder-open',    href: '/processos',           color: 'text-indigo-600 bg-indigo-100', permissao: 'processo.visualizar' },
        ],
    },
    {
        title: 'Privado', icon: 'fas fa-lock', color: 'text-emerald-600 bg-emerald-100',
        children: [
            { title: 'Saida (Originados)', icon: 'fas fa-paper-plane',  href: '/flow/saida',     color: 'text-emerald-600 bg-emerald-100' },
            { title: 'Rascunhos',          icon: 'fas fa-pencil-alt',   href: '/flow/rascunhos', color: 'text-yellow-600 bg-yellow-100' },
        ],
    },
    {
        title: 'Cadastros', icon: 'fas fa-cogs', color: 'text-slate-600 bg-slate-100',
        children: [
            { title: 'Tipos de Processo',  icon: 'fas fa-cogs',            href: '/admin/tipos-processo',  color: 'text-teal-600 bg-teal-100', permissao: 'admin.tipos_processo' },
            { title: 'Modelos de Oficio',  icon: 'fas fa-file-signature',  href: '/admin/oficios-modelos', color: 'text-cyan-600 bg-cyan-100', permissao: 'admin.tipos_processo' },
        ],
    },
];

export const MODULO_CONFIG = {
    ged:           { nome: 'GPE Docs',   subtitulo: 'Gestao Documental',   icon: 'fas fa-archive',         iconText: 'Docs', cor: 'from-blue-600 to-indigo-700',  shadow: 'shadow-blue-200',  menu: MENU_GED },
    gepsp:         { nome: 'GPE Flow',   subtitulo: 'Fluxos e Tramitacao', icon: 'fas fa-project-diagram', iconText: 'Flow', cor: 'from-teal-600 to-emerald-700', shadow: 'shadow-teal-200',  menu: MENU_GEPSP },
    configuracoes: { nome: 'GPE Config', subtitulo: 'Ajustes e Estrutura', icon: 'fas fa-cog',             iconText: 'Conf', cor: 'from-slate-600 to-gray-700',   shadow: 'shadow-slate-200', menu: MENU_CONFIGURACOES },
};

/**
 * `permissoes` = auth.user.permissoes (['*'] para super admin). Só esconde menu: quem
 * decide o acesso é o Gate em cada rota. Item sem `permissao` é de uso pessoal e aparece a todos.
 */
export function pode(permissoes, permissao) {
    if (!permissao) return true;
    const lista = permissoes || [];
    return lista.includes('*') || lista.includes(permissao);
}

/** Menu sem os itens que o usuário não pode abrir; grupo vazio e rótulo sem itens somem. */
export function filtrarMenu(menu, permissoes) {
    const itens = menu
        .map((item) => (Array.isArray(item.children)
            ? { ...item, children: item.children.filter((c) => pode(permissoes, c.permissao)) }
            : item))
        .filter((item) => {
            if (item.section) return true;
            if (Array.isArray(item.children)) return item.children.length > 0;
            return pode(permissoes, item.permissao);
        });
    return itens.filter((item, i) => item.section !== 'label' || (itens[i + 1] && !itens[i + 1].section));
}

function achatar(permissoes) {
    return Object.values(MODULO_CONFIG).flatMap((cfg) => {
        const out = [];
        let secao = '';
        const add = (item, grupo) => {
            if (item.href) out.push({ href: item.href, label: item.title, icon: item.icon, modulo: cfg.nome, grupo });
        };
        for (const item of permissoes ? filtrarMenu(cfg.menu, permissoes) : cfg.menu) {
            if (item.section === 'label') { secao = item.label; continue; }
            if (item.section) continue;
            if (Array.isArray(item.children)) item.children.forEach((c) => add(c, item.title));
            else add(item, secao);
        }
        return out;
    });
}

/**
 * Índice da busca global (Ctrl+K): TODAS as rotinas de todos os módulos, achatadas a partir
 * dos mesmos menus da sidebar — a busca nunca diverge do menu. `grupo` = seção (label) ou
 * grupo (item com filhos) em que a rotina está.
 */
export const ROTINAS = achatar(null);

/** As rotinas que o usuário pode abrir — é o que a busca global oferece. */
export function rotinasDe(permissoes) {
    return achatar(permissoes);
}

/**
 * Rotina (item de menu) que corresponde à URL atual, ou null se a tela não é uma rotina
 * (detalhe, formulário...). Href com query casa exatamente; sem query, casa o caminho.
 * Vence o href mais longo — /repositorio?filtro=favoritos antes de /repositorio.
 */
export function rotinaDaUrl(url) {
    const caminho = url.split('?')[0];
    let melhor = null;
    for (const r of ROTINAS) {
        const casa = r.href.includes('?')
            ? (url === r.href || url.startsWith(r.href + '&'))
            : caminho === r.href;
        if (casa && (!melhor || r.href.length > melhor.href.length)) melhor = r;
    }
    return melhor;
}
