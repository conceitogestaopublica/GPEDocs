<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Tenant — registro de um município/entidade no banco landlord.
 *
 * O landlord (tabela `tenants`) é do gpe2 e é compartilhado: o cadastro é feito
 * no painel do gpe2 e este projeto só LÊ. O que separa os tenants do GPEDocs dos
 * do gpe2 é a coluna `domain`:
 *   prod: domain = domínio base do GPEDocs (ex: gpedocs.com.br)
 *   dev:  domain = ':<porta>' em que o GPEDocs é servido (ex: ':8090')
 *
 * Por isso o model tem o escopo global `gpedocs`: TODA consulta deste projeto (comandos,
 * jobs, SSO, ResolveTenant) só enxerga tenants com domain = TENANT_DOMINIO_BASE. Igualdade
 * exata, não LIKE — ':8090' casaria com ':80901'.
 */
class Tenant extends Model
{
    use HasFactory;

    protected $connection = 'landlord';

    protected $table = 'tenants';

    protected $guarded = ['id'];

    /** Só tenants do GPEDocs — o landlord é compartilhado com o gpe2. */
    protected static function booted(): void
    {
        static::addGlobalScope('gpedocs', function (Builder $q) {
            $q->where($q->qualifyColumn('domain'), (string) config('multitenancy.dominio_base'));
        });
    }

    protected $casts = [
        'active' => 'boolean',
        'is_matriz' => 'boolean',
        'contratado_em' => 'date',
        'encerrado_em' => 'date',
    ];

    /**
     * Senha do banco do tenant — guardada em texto plano (decisão do operador).
     *
     * É uma credencial de conexão que o app precisa ler de forma reversível para
     * abrir o PDO do tenant; não há ganho de hash. O `get` ainda decripta valores
     * legados que tenham sido gravados criptografados antes desta mudança.
     */
    protected function dbPassword(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? $this->decryptSafe($value) : null,
            set: fn ($value) => $value ?: null,
        );
    }

    /** Lê valores legados criptografados; se já for texto plano, retorna como está. */
    private function decryptSafe(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value; // texto plano (novo padrão) ou legado não-criptografado
        }
    }

    /** Apenas tenants ativos. */
    public function scopeActive($q)
    {
        return $q->where('active', true);
    }

    /**
     * URL pública do tenant — monta o host a partir de {subdomain}.{domain}.
     *
     *   subdomain = paraguacu · domain = gpedocs.com.br
     *   $tenant->url()                → https://paraguacu.gpedocs.com.br
     *   $tenant->url('/sso/landlord') → https://paraguacu.gpedocs.com.br/sso/landlord
     *
     * Em dev a coluna `domain` guarda só a porta (':8090') e o tenant é servido
     * em localhost — o ResolveTenant casa por subdomain='localhost', não existe
     * host `paraguacu`. Daí o ramo separado.
     */
    public function url(string $path = ''): string
    {
        if (str_starts_with($this->domain, ':')) {
            $url = 'http://localhost'.$this->domain;
        } else {
            // O template pode trazer esquema (TENANT_URL_TEMPLATE=https://{subdomain}.{domain});
            // quem define o esquema aqui é o ambiente, então remove antes de montar.
            $host = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', (string) config('multitenancy.url_template'));
            $url = 'https://'.str_replace(['{subdomain}', '{domain}'], [$this->subdomain, $this->domain], $host);
        }

        return $path === '' ? $url : rtrim($url, '/').'/'.ltrim($path, '/');
    }
}
