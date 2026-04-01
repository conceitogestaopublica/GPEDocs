import { Link } from '@inertiajs/react';

const variants = {
    primary:   'ds-btn ds-btn-primary',
    secondary: 'ds-btn ds-btn-outline',
    danger:    'ds-btn ds-btn-danger',
    accent:    'ds-btn ds-btn-accent',
    ghost:     'ds-btn ds-btn-ghost',
    success:   'ds-btn ds-btn-accent',
};

const sizes = { sm: 'ds-btn-sm', md: '', lg: '' };

export default function Button({ variant = 'primary', size = 'md', icon, href, loading = false, children, className = '', ...rest }) {
    const classes = `${variants[variant] || variants.primary} ${sizes[size] || ''} ${className}`.trim();
    const content = (
        <>
            {loading ? <i className="fas fa-spinner fa-spin" /> : icon && <i className={icon} />}
            {children}
        </>
    );
    if (href) return <Link href={href} className={classes} {...rest}>{content}</Link>;
    return <button className={classes} disabled={loading || rest.disabled} {...rest}>{content}</button>;
}
