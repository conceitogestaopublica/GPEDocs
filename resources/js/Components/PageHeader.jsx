import FavoritarRotina from './FavoritarRotina';

export default function PageHeader({ title, subtitle, children }) {
    return (
        <div className="ds-page-header">
            <div>
                {/* Estrela ao lado do título: favorita a rotina (só aparece em rotina do menu). */}
                <div className="flex items-center gap-1.5">
                    <h1>{title}</h1>
                    <FavoritarRotina />
                </div>
                {subtitle && <p>{subtitle}</p>}
            </div>
            {children && <div className="flex items-center gap-2">{children}</div>}
        </div>
    );
}
