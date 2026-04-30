/**
 * EnderecoForm — bloco reutilizavel de campos de endereco
 *
 * Props:
 *   - data: objeto useForm (ou estado equivalente) com cep, logradouro, numero,
 *           complemento, bairro, cidade, uf
 *   - setData: setter do useForm
 *   - errors: objeto de erros
 *   - disabled: desabilita campos
 *   - autoCompletarCep: liga consulta ViaCEP ao perder foco do CEP (default true)
 */
import { useState } from 'react';

export default function EnderecoForm({ data, setData, errors = {}, disabled = false, autoCompletarCep = true }) {
    const [buscandoCep, setBuscandoCep] = useState(false);

    const consultarCep = async () => {
        if (! autoCompletarCep) return;
        const cep = (data.cep || '').replace(/\D/g, '');
        if (cep.length !== 8) return;

        setBuscandoCep(true);
        try {
            const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const j = await r.json();
            if (j.erro) return;
            setData({
                ...data,
                logradouro: data.logradouro || j.logradouro || '',
                bairro:     data.bairro     || j.bairro     || '',
                cidade:     data.cidade     || j.localidade || '',
                uf:         data.uf         || j.uf         || '',
            });
        } catch {
            // silencioso — CEP fora do ar nao bloqueia o usuario
        } finally {
            setBuscandoCep(false);
        }
    };

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-3 gap-2">
                <div>
                    <label className="block text-[11px] font-medium text-gray-700 mb-1">CEP</label>
                    <div className="relative">
                        <input type="text" value={data.cep || ''}
                            onChange={(e) => setData('cep', e.target.value)}
                            onBlur={consultarCep}
                            disabled={disabled}
                            className="ds-input" maxLength={9} placeholder="00000-000" />
                        {buscandoCep && (
                            <i className="fas fa-spinner fa-spin absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                        )}
                    </div>
                    {errors.cep && <p className="mt-1 text-[10px] text-red-600">{errors.cep}</p>}
                </div>
                <div className="col-span-2">
                    <label className="block text-[11px] font-medium text-gray-700 mb-1">Logradouro</label>
                    <input type="text" value={data.logradouro || ''}
                        onChange={(e) => setData('logradouro', e.target.value)}
                        disabled={disabled}
                        className="ds-input" maxLength={200} />
                    {errors.logradouro && <p className="mt-1 text-[10px] text-red-600">{errors.logradouro}</p>}
                </div>
            </div>

            <div className="grid grid-cols-3 gap-2">
                <div>
                    <label className="block text-[11px] font-medium text-gray-700 mb-1">Numero</label>
                    <input type="text" value={data.numero || ''}
                        onChange={(e) => setData('numero', e.target.value)}
                        disabled={disabled}
                        className="ds-input" maxLength={20} />
                </div>
                <div className="col-span-2">
                    <label className="block text-[11px] font-medium text-gray-700 mb-1">Complemento</label>
                    <input type="text" value={data.complemento || ''}
                        onChange={(e) => setData('complemento', e.target.value)}
                        disabled={disabled}
                        className="ds-input" maxLength={100} />
                </div>
            </div>

            <div className="grid grid-cols-4 gap-2">
                <div className="col-span-2">
                    <label className="block text-[11px] font-medium text-gray-700 mb-1">Bairro</label>
                    <input type="text" value={data.bairro || ''}
                        onChange={(e) => setData('bairro', e.target.value)}
                        disabled={disabled}
                        className="ds-input" maxLength={100} />
                </div>
                <div>
                    <label className="block text-[11px] font-medium text-gray-700 mb-1">Cidade</label>
                    <input type="text" value={data.cidade || ''}
                        onChange={(e) => setData('cidade', e.target.value)}
                        disabled={disabled}
                        className="ds-input" maxLength={100} />
                </div>
                <div>
                    <label className="block text-[11px] font-medium text-gray-700 mb-1">UF</label>
                    <input type="text" value={data.uf || ''}
                        onChange={(e) => setData('uf', e.target.value.toUpperCase())}
                        disabled={disabled}
                        className="ds-input uppercase" maxLength={2} placeholder="MG" />
                </div>
            </div>
        </div>
    );
}
