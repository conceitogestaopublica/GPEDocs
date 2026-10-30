const MINUTO = 60;
const HORA = 60 * MINUTO;
const DIA = 24 * HORA;
const SEMANA = 7 * DIA;

/**
 * Tempo relativo curto: "agora", "há 5 min", "há 3 h", "ontem", "há 4 dias" ou a data.
 * `agora` é parâmetro (padrão: relógio) para a função ser pura e testável.
 * "ontem" = de 24 a 48 h atrás (não o dia de calendário anterior).
 */
export function haQuantoTempo(data, agora = Date.now()) {
    if (!data) return '';
    const seg = Math.max(0, (agora - new Date(data).getTime()) / 1000);
    if (seg < MINUTO) return 'agora';
    if (seg < HORA) return `há ${Math.floor(seg / MINUTO)} min`;
    if (seg < DIA) return `há ${Math.floor(seg / HORA)} h`;
    if (seg < 2 * DIA) return 'ontem';
    if (seg < SEMANA) return `há ${Math.floor(seg / DIA)} dias`;
    return new Date(data).toLocaleDateString('pt-BR');
}
