<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;

/**
 * Tenant — registro de um município/entidade no banco landlord.
 * Cada tenant aponta para um banco MariaDB próprio.
 */
class Tenant extends Model
{
    use HasFactory;

    protected $connection = 'landlord';

    protected $table = 'tenants';

    protected $guarded = ['id'];

    /**
     * A MATRIZ das tabelas universais — o banco modelo de onde as universais são
     * publicadas para os municípios.
     *
     * É dado, não convenção: quem publica pergunta ao landlord em vez de receber o nome
     * do banco por parâmetro. Um `--source` digitado à mão publicaria o cadastro de um
     * município como se fosse norma, e o erro só apareceria semanas depois como "o plano
     * de contas mudou sozinho".
     *
     * SÓ UMA pode existir — garantido por índice único parcial no banco
     * (`uq_tenants_uma_matriz`), não por validação de aplicação.
     */
    public static function matriz(): ?self
    {
        return static::where('is_matriz', true)->first();
    }

    protected $casts = [
        'active' => 'boolean',
        'is_matriz' => 'boolean',
        'contratado_em' => 'date',
        'encerrado_em' => 'date',
    ];

    //    protected static function boot()
    //    {
    //        parent::boot();
    //        static::addGlobalScope('maat_gpecloud', function ($builder) {
    //            $builder->whereRaw("domain = '". Config::get('multitenancy.tenant_default_domain') ."'");
    //        });
    //    }

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
     * Em dev a coluna `domain` guarda só a porta (':8080') e o tenant é servido
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

    /**
     * O landlord é compartilhado com o GPE2: o GPEDocs só enxerga tenants PostgreSQL.
     */
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('only_pgsql', function ($builder) {
            $builder->whereRaw("driver = 'pgsql'");
        });
    }
}
