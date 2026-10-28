<?php

declare(strict_types=1);

namespace App\Models\Portal;

use App\Models\Concerns\BelongsToUg;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servico extends Model
{
    use BelongsToUg;

    protected $table = 'portal_servicos';

    protected $fillable = [
        'ug_id',
        'categoria_id',
        'titulo',
        'slug',
        'publico_alvo',
        'descricao_curta',
        'descricao_completa',
        'requisitos',
        'documentos_necessarios',
        'prazo_entrega',
        'custo',
        'canais',
        'orgao_responsavel',
        'legislacao',
        'palavras_chave',
        'icone',
        'publicado',
        'visualizacoes',
        'ordem',
        'permite_anonimo',
        'setor_responsavel_id',
        'tipo_processo_id',
        'acao_fiscal',
    ];

    protected function casts(): array
    {
        return [
            'documentos_necessarios' => 'array',
            'canais'                 => 'array',
            'palavras_chave'         => 'array',
            'publicado'              => 'boolean',
            'visualizacoes'          => 'integer',
            'ordem'                  => 'integer',
            'permite_anonimo'        => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaServico::class, 'categoria_id');
    }

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(Solicitacao::class, 'servico_id');
    }

    public const PUBLICOS = [
        'cidadao'  => 'Cidadão',
        'empresa'  => 'Empresa',
        'servidor' => 'Servidor',
    ];

    /**
     * Autoatendimento no tributário: qual tela do Portal do Cidadão o serviço
     * abre. Só vale para o município que tem o tributário integrado; sem ação
     * fiscal, o serviço é de solicitação (abre processo).
     */
    public const ACOES_FISCAIS = [
        'debitos'        => 'Consultar débitos',
        'segunda_via'    => '2ª via de guias',
        'certidao'       => 'Certidão negativa (CND)',
        'parcelamento'   => 'Parcelamento',
        'caixa_postal'   => 'Caixa postal (DTE)',
        'nfse'           => 'Nota fiscal (NFS-e)',
        'dms'            => 'Declaração (DMS)',
        'prestei'        => 'Serviço prestado no município',
        'desif'          => 'DES-IF (bancos)',
        'processos'      => 'Processos tributários',
        'procuracoes'    => 'Procurações',
        'meus_cadastros' => 'Meus cadastros',
    ];
}
