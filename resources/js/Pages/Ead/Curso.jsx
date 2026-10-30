/**
 * Sala de aula do EAD (TR 4.24.1–7): lista de aulas com o percentual de cada uma, vídeo (arquivo ou YouTube)
 * que só deixa avançar até onde já foi assistido, progresso salvo a cada 10 s, rever aulas, avaliação final
 * depois de todas as aulas e o certificado quando aprovado.
 */
import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';
import Card from '../../Components/Card';
import Button from '../../Components/Button';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const mmss = (s) => (s == null ? '—' : `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`);
const ytId = (url) => (url?.match(/(?:youtu\.be\/|v=|embed\/)([\w-]{11})/) || [])[1];

function enviarProgresso(aulaId, dados) {
    return fetch(`/ead/aulas/${aulaId}/progresso`, {
        method: 'POST', credentials: 'same-origin', keepalive: true,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(dados),
    }).then((r) => (r.ok ? r.json() : null)).catch(() => null);
}

/** Player com a regra de não pular: guarda o ponto mais adiantado assistido em sequência. */
function Player({ aula, onAtualizou }) {
    const video = useRef(null);
    const yt = useRef(null);
    const caixaYt = useRef(null);
    const maximo = useRef(aula.assistido_ate);
    const ultimo = useRef(aula.posicao);
    const [aviso, setAviso] = useState('');
    const [pct, setPct] = useState(aula.percentual);

    const salvar = async (pos, duracao) => {
        const r = await enviarProgresso(aula.id, { posicao: Math.floor(pos), assistido_ate: Math.floor(maximo.current), duracao: duracao ? Math.floor(duracao) : null });
        if (r) {
            maximo.current = Math.max(maximo.current, r.assistido_ate);
            setPct(r.percentual);
            if (r.concluiu_agora) onAtualizou();
        }
    };
    const andou = (t) => {
        // avanço normal de reprodução (até 2 s entre leituras) conta; salto para frente não
        if (t - ultimo.current > 0 && t - ultimo.current <= 2.5 && t > maximo.current) maximo.current = t;
        ultimo.current = t;
    };

    useEffect(() => {
        maximo.current = aula.assistido_ate; ultimo.current = aula.posicao; setPct(aula.percentual); setAviso('');
        let timer;
        if (aula.video_tipo === 'arquivo') {
            const v = video.current;
            const pronto = () => { if (aula.posicao > 3 && aula.posicao < v.duration - 3) v.currentTime = aula.posicao; ultimo.current = v.currentTime; };
            const tempo = () => andou(v.currentTime);
            const buscando = () => {
                if (v.currentTime > maximo.current + 1) { v.currentTime = maximo.current; setAviso('Você só pode avançar até onde já assistiu.'); setTimeout(() => setAviso(''), 3000); }
                ultimo.current = v.currentTime;
            };
            const parou = () => salvar(v.currentTime, v.duration);
            v.addEventListener('loadedmetadata', pronto); v.addEventListener('timeupdate', tempo); v.addEventListener('seeking', buscando);
            v.addEventListener('pause', parou); v.addEventListener('ended', parou);
            timer = setInterval(() => { if (! v.paused) salvar(v.currentTime, v.duration); }, 10000);
            return () => { clearInterval(timer); parou(); v.removeEventListener('loadedmetadata', pronto); v.removeEventListener('timeupdate', tempo);
                v.removeEventListener('seeking', buscando); v.removeEventListener('pause', parou); v.removeEventListener('ended', parou); };
        }
        // YouTube: IFrame API; lê o tempo a cada segundo e devolve ao máximo se pular
        const montar = () => {
            yt.current = new window.YT.Player(caixaYt.current, { videoId: ytId(aula.video_url), playerVars: { start: aula.posicao > 3 ? aula.posicao : 0, rel: 0 },
                events: { onStateChange: (e) => { if (e.data === 2 || e.data === 0) salvar(yt.current.getCurrentTime(), yt.current.getDuration()); } } });
            let seg = 0;
            timer = setInterval(() => {
                const p = yt.current; if (! p?.getCurrentTime) return;
                const t = p.getCurrentTime();
                if (t > maximo.current + 2.5 && t - ultimo.current > 2.5) { p.seekTo(maximo.current, true); setAviso('Você só pode avançar até onde já assistiu.'); setTimeout(() => setAviso(''), 3000); ultimo.current = maximo.current; return; }
                andou(t);
                if (++seg % 10 === 0 && p.getPlayerState() === 1) salvar(t, p.getDuration());
            }, 1000);
        };
        if (window.YT?.Player) montar();
        else {
            window.onYouTubeIframeAPIReady = montar;
            if (! document.getElementById('yt-api')) { const s = document.createElement('script'); s.id = 'yt-api'; s.src = 'https://www.youtube.com/iframe_api'; document.body.appendChild(s); }
        }
        return () => { clearInterval(timer); try { if (yt.current?.getCurrentTime) salvar(yt.current.getCurrentTime(), yt.current.getDuration()); yt.current?.destroy(); } catch (_) { /* já fechado */ } };
    }, [aula.id]);

    return (
        <div>
            <div className="relative overflow-hidden rounded-xl bg-black">
                {aula.video_tipo === 'arquivo'
                    ? <video ref={video} src={aula.video_url} controls controlsList="nodownload noplaybackrate" disablePictureInPicture playsInline className="aspect-video w-full" />
                    : <div className="aspect-video w-full"><div ref={caixaYt} className="h-full w-full" /></div>}
                {aviso && <p className="absolute left-1/2 top-3 -translate-x-1/2 rounded-full bg-black/75 px-3 py-1 text-xs text-white">{aviso}</p>}
            </div>
            <div className="mt-2 flex items-center gap-3 text-xs text-gray-600">
                <div className="h-2 flex-1 overflow-hidden rounded-full bg-gray-200"><div className="h-full bg-cyan-600 transition-all" style={{ width: `${pct}%` }} /></div>
                <span>{pct}% assistido</span>
            </div>
        </div>
    );
}

export default function Curso({ curso, aulas, questoes, tentativas, tentativasRestantes }) {
    const primeira = aulas.find((a) => a.liberada && ! a.concluida) || aulas.find((a) => a.liberada) || aulas[0];
    const [atualId, setAtualId] = useState(primeira?.id);
    const [respostas, setRespostas] = useState({});
    const atual = aulas.find((a) => a.id === atualId);
    const recarregar = () => router.reload({ preserveScroll: true, only: ['curso', 'aulas', 'questoes', 'tentativas', 'tentativasRestantes'] });
    const situacao = { nao_iniciado: 'Não iniciado', em_andamento: 'Assistindo', avaliacao: 'Falta a avaliação', concluido: 'Concluído' }[curso.situacao];

    return (
        <AdminLayout>
            <Head title={curso.titulo} />
            <PageHeader title={curso.titulo} subtitle={`${String(curso.carga_horaria).replace('.', ',')} h · ${curso.aulas} aula(s) · nota mínima ${curso.nota_minima}`}>
                <Link href="/ead"><Button size="sm" variant="secondary" icon="fas fa-arrow-left">Meus treinamentos</Button></Link>
            </PageHeader>
            {! curso.publicado && <p className="mb-3 rounded-lg bg-amber-50 p-2 text-xs text-amber-800">Curso ainda não publicado: você está vendo como gestor.</p>}

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div className="space-y-4 xl:col-span-2">
                    {atual ? (
                        <Card>
                            <Player key={atual.id} aula={atual} onAtualizou={recarregar} />
                            <h2 className="mt-3 text-lg font-semibold text-gray-800">{atual.ordem}. {atual.titulo}</h2>
                            {atual.descricao && <p className="mt-1 whitespace-pre-wrap text-sm text-gray-600">{atual.descricao}</p>}
                        </Card>
                    ) : <Card><p className="text-sm text-gray-500">Este curso ainda não tem aulas.</p></Card>}

                    {curso.situacao === 'concluido' ? (
                        <Card>
                            <div className="flex flex-wrap items-center gap-3">
                                <i className="fas fa-award text-3xl text-amber-500" />
                                <div className="flex-1"><p className="font-semibold text-gray-800">Curso concluído com nota {curso.nota}</p>
                                    <p className="text-xs text-gray-500">em {new Date(curso.concluido_em).toLocaleDateString('pt-BR')}. Você pode rever as aulas quando quiser.</p></div>
                                <a href={curso.certificado} target="_blank" rel="noreferrer"><Button icon="fas fa-file-pdf">Baixar certificado</Button></a>
                            </div>
                        </Card>
                    ) : questoes.length > 0 ? (
                        <Card title="Avaliação final">
                            <p className="mb-3 text-xs text-gray-500">Nota mínima {curso.nota_minima}.{tentativasRestantes != null ? ` Tentativas restantes: ${tentativasRestantes}.` : ''}</p>
                            {questoes.map((q, i) => (
                                <fieldset key={q.id} className="mb-4">
                                    <legend className="mb-1 text-sm font-medium text-gray-800">{i + 1}. {q.enunciado}</legend>
                                    {q.alternativas.map((alt, j) => (
                                        <label key={j} className={`mb-1 flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2 text-sm ${respostas[q.id] === j ? 'border-cyan-600 bg-cyan-50' : 'border-gray-200'}`}>
                                            <input type="radio" name={`q${q.id}`} checked={respostas[q.id] === j} onChange={() => setRespostas({ ...respostas, [q.id]: j })} className="mt-0.5" />{alt}
                                        </label>
                                    ))}
                                </fieldset>
                            ))}
                            <Button icon="fas fa-check" disabled={Object.keys(respostas).length < questoes.length || tentativasRestantes === 0}
                                onClick={() => router.post(`/ead/cursos/${curso.id}/avaliacao`, { respostas }, { preserveScroll: true, onSuccess: () => setRespostas({}) })}>Enviar respostas</Button>
                        </Card>
                    ) : curso.situacao !== 'concluido' && <p className="text-xs text-gray-500">A avaliação final abre quando você concluir todas as aulas.</p>}
                </div>

                <div className="space-y-4">
                    <Card title="Seu progresso">
                        <p className="text-sm text-gray-700">{situacao} — {curso.aulas_concluidas} de {curso.aulas} aula(s)</p>
                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-gray-200"><div className="h-full bg-emerald-600" style={{ width: `${curso.percentual}%` }} /></div>
                        {curso.ordem_obrigatoria && <p className="mt-2 text-[11px] text-gray-500">As aulas seguem a ordem: a próxima abre quando você assistir {curso.percentual_minimo}% da anterior.</p>}
                        {tentativas.length > 0 && <p className="mt-2 text-xs text-gray-600">Tentativas: {tentativas.map((t) => `${t.nota}${t.aprovado ? ' ✓' : ''}`).join(' · ')}</p>}
                    </Card>
                    <Card title="Aulas">
                        <ol className="space-y-1">
                            {aulas.map((a) => (
                                <li key={a.id}>
                                    <button type="button" disabled={! a.liberada} onClick={() => setAtualId(a.id)}
                                        className={`flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm ${a.id === atualId ? 'bg-cyan-50 font-semibold text-cyan-900' : 'hover:bg-gray-50'} disabled:cursor-not-allowed disabled:opacity-50`}>
                                        <i className={`fas ${a.concluida ? 'fa-circle-check text-emerald-600' : a.liberada ? 'fa-circle-play text-cyan-700' : 'fa-lock text-gray-400'}`} />
                                        <span className="flex-1">{a.ordem}. {a.titulo}</span>
                                        <span className="text-[11px] text-gray-500">{a.concluida ? (a.vezes_revista ? `revista ${a.vezes_revista}×` : 'concluída') : `${a.percentual}%`} · {mmss(a.duracao_seg)}</span>
                                    </button>
                                </li>
                            ))}
                        </ol>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
