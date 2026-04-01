/**
 * Tabs — Componente de navegação por abas
 *
 * Props:
 *   - tabs: Array<{ key, label, icon? }> — lista de abas
 *   - activeTab: string — key da aba ativa
 *   - onChange: function(key) — callback ao trocar de aba
 */
export default function Tabs({ tabs = [], activeTab, onChange }) {
    return (
        <div className="border-b border-gray-200">
            <nav className="flex gap-0 -mb-px">
                {tabs.map((tab) => {
                    const isActive = activeTab === tab.key;
                    return (
                        <button
                            key={tab.key}
                            onClick={() => onChange?.(tab.key)}
                            className={`px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                                ${isActive
                                    ? 'border-blue-600 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                        >
                            {tab.icon && <i className={`${tab.icon} mr-2`} />}
                            {tab.label}
                        </button>
                    );
                })}
            </nav>
        </div>
    );
}
