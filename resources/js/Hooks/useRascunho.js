/**
 * Rascunho automático da tela de criação (TR 4.16.6–4.16.8): salva 2 s depois da última
 * alteração, guarda os anexos no rascunho na hora em que são escolhidos, segura a trava
 * enquanto a tela está aberta e a solta ao sair. Se outra pessoa do setor está editando,
 * a tela fica só para leitura (`travadoPor`).
 *
 * `dados` não deve levar arquivos nem segredos (contrassenha) — vão para o banco.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

const ESPERA_MS = 2000;
const RENOVAR_MS = 60000;

function xsrf() {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

export default function useRascunho({ modulo, dados, titulo, inicial = null, temConteudo = true }) {
    const [id, setId] = useState(inicial?.id ?? null);
    const [estado, setEstado] = useState(inicial ? 'salvo' : 'novo'); // novo | pendente | salvando | salvo | erro
    const [salvoEm, setSalvoEm] = useState(inicial?.atualizado_em ?? null);
    const [travadoPor, setTravadoPor] = useState(inicial?.travado_por ?? null);
    const [anexos, setAnexos] = useState(inicial?.anexos ?? []);
    const [enviando, setEnviando] = useState(0);

    const idRef = useRef(id);
    const criando = useRef(null);
    const ultimo = useRef({ dados, titulo });
    ultimo.current = { dados, titulo };
    const serial = JSON.stringify(dados);
    const primeira = useRef(true);

    const marcarNaUrl = (novo) => {
        const url = new URL(window.location.href);
        url.searchParams.set('rascunho', novo);
        window.history.replaceState(window.history.state, '', url);
    };

    const tratarErro = (e) => {
        if (e?.response?.status === 423) { setTravadoPor(e.response.data?.travado_por || 'outra pessoa'); setEstado('salvo'); return; }
        setEstado('erro');
    };

    /** Cria o rascunho se ainda não existe (no primeiro salvamento ou no primeiro anexo). */
    const garantir = useCallback(async () => {
        if (idRef.current) return idRef.current;
        if (! criando.current) {
            criando.current = axios.post('/rascunhos', { modulo, dados: ultimo.current.dados, titulo: ultimo.current.titulo })
                .then((r) => { idRef.current = r.data.id; setId(r.data.id); setSalvoEm(r.data.salvo_em); marcarNaUrl(r.data.id); return r.data.id; })
                .finally(() => { criando.current = null; });
        }
        return criando.current;
    }, [modulo]);

    const salvar = useCallback(async () => {
        if (travadoPor) return;
        setEstado('salvando');
        try {
            if (! idRef.current) await garantir();
            else {
                const r = await axios.put(`/rascunhos/${idRef.current}`, { dados: ultimo.current.dados, titulo: ultimo.current.titulo });
                setSalvoEm(r.data.salvo_em);
            }
            setEstado('salvo');
        } catch (e) { tratarErro(e); }
    }, [garantir, travadoPor]);

    // Salva depois que a pessoa para de mexer
    useEffect(() => {
        if (primeira.current) { primeira.current = false; return undefined; }
        if (travadoPor || (! idRef.current && ! temConteudo)) return undefined;
        setEstado('pendente');
        const t = setTimeout(salvar, ESPERA_MS);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serial, titulo]);

    // Segura a trava enquanto a tela está aberta; solta ao sair
    useEffect(() => {
        if (! id || travadoPor) return undefined;
        const renovar = setInterval(() => {
            axios.post(`/rascunhos/${id}/trava`).catch(tratarErro);
        }, RENOVAR_MS);
        const soltar = () => fetch(`/rascunhos/${id}/liberar`, {
            method: 'POST', keepalive: true, credentials: 'same-origin',
            headers: { 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        window.addEventListener('pagehide', soltar);
        return () => { clearInterval(renovar); window.removeEventListener('pagehide', soltar); soltar(); };
    }, [id, travadoPor]);

    const anexar = async (arquivos) => {
        if (travadoPor) return;
        const lista = [...arquivos];
        setEnviando((n) => n + lista.length);
        try {
            const rid = await garantir();
            for (const arquivo of lista) {
                const fd = new FormData();
                fd.append('arquivo', arquivo);
                try {
                    const r = await axios.post(`/rascunhos/${rid}/anexos`, fd);
                    setAnexos((a) => [...a, r.data]);
                } catch (e) {
                    if (e?.response?.status === 423) { tratarErro(e); break; }
                    window.alert(`Não foi possível anexar "${arquivo.name}": ${e?.response?.data?.message || 'erro no envio'}.`);
                } finally {
                    setEnviando((n) => n - 1);
                }
            }
        } catch (e) { tratarErro(e); setEnviando(0); }
    };

    const removerAnexo = async (anexoId) => {
        if (! idRef.current || travadoPor) return;
        await axios.delete(`/rascunhos/${idRef.current}/anexos/${anexoId}`).catch(tratarErro);
        setAnexos((a) => a.filter((x) => x.id !== anexoId));
    };

    const descartar = async () => {
        if (idRef.current) await axios.delete(`/rascunhos/${idRef.current}`, { headers: { Accept: 'application/json' } });
        idRef.current = null;
        window.location.href = '/flow/rascunhos';
    };

    return { id, estado, salvoEm, travadoPor, anexos, enviando, anexar, removerAnexo, descartar, salvarAgora: salvar };
}
