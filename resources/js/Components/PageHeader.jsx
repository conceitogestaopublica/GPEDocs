export default function PageHeader({ title, subtitle, children }) {
    return (
        <div className="ds-page-header">
            <div>
                <h1>{title}</h1>
                {subtitle && <p>{subtitle}</p>}
            </div>
            {children && <div className="flex items-center gap-2">{children}</div>}
        </div>
    );
}
