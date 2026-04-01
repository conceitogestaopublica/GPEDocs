/**
 * Card de estatística para dashboards.
 *
 * Props:
 *   - title: string — nome do indicador
 *   - value: string|number — valor principal formatado
 *   - icon: string — classe FontAwesome
 *   - color: 'blue'|'green'|'red'|'yellow'|'purple'
 *   - trend: string — percentual de variação (opcional)
 *   - trendUp: boolean — se a tendência é positiva
 */
const colorMap = {
    blue:   { bg: 'bg-blue-50',   icon: 'text-blue-600',   ring: 'ring-blue-200' },
    green:  { bg: 'bg-green-50',  icon: 'text-green-600',  ring: 'ring-green-200' },
    red:    { bg: 'bg-red-50',    icon: 'text-red-600',    ring: 'ring-red-200' },
    yellow: { bg: 'bg-yellow-50', icon: 'text-yellow-600', ring: 'ring-yellow-200' },
    purple: { bg: 'bg-purple-50', icon: 'text-purple-600', ring: 'ring-purple-200' },
};

export default function StatCard({ title, value, icon = 'fas fa-chart-bar', color = 'blue', trend, trendUp = true }) {
    const c = colorMap[color] || colorMap.blue;

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <p className="text-sm font-medium text-gray-500">{title}</p>
                    <p className="text-2xl font-bold text-gray-800 mt-1">{value}</p>
                    {trend && (
                        <p className={`text-xs mt-2 flex items-center gap-1 ${trendUp ? 'text-green-600' : 'text-red-600'}`}>
                            <i className={`fas fa-arrow-${trendUp ? 'up' : 'down'}`} />
                            {trend}
                        </p>
                    )}
                </div>
                <div className={`${c.bg} ${c.ring} ring-1 p-3 rounded-xl`}>
                    <i className={`${icon} ${c.icon} text-xl`} />
                </div>
            </div>
        </div>
    );
}
