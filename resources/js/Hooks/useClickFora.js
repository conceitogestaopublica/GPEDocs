import { useEffect, useRef } from 'react';

/** Fecha o dropdown ao clicar fora: devolve a ref do container. */
export default function useClickFora(setOpen) {
    const ref = useRef(null);
    useEffect(() => {
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [setOpen]);
    return ref;
}
