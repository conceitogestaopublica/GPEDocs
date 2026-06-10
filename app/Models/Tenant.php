<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Config;

/**
 * Tenant — registro de um município/entidade no banco landlord.
 * Cada tenant aponta para um banco MariaDB próprio.
 */
class Tenant extends Model
{
    use hasFactory;

    protected $connection = 'landlord';
    protected $table = 'tenants';
    protected $guarded = ['id'];
    protected $casts = [
        'active' => 'boolean',
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
            get: fn($value) => $value ? $this->decryptSafe($value) : null,
            set: fn($value) => $value ?: null,
        );
    }

    /** Lê valores legados criptografados; se já for texto plano, retorna como está. */
    private function decryptSafe(?string $value): ?string
    {
        if (!$value) return null;
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
     * URL pública do tenant — substitui {domain} no template configurado.
     *
     *   $tenant->url()              → https://paraguacu.maatgpecloud.com.br
     *   $tenant->url('/sso/landlord') → https://paraguacu.maatgpecloud.com.br/sso/landlord
     */
    public function url(string $path = ''): string
    {
        if (ResolveTenant::$LANDLORD_URL == config('multitenancy.dev_landlord_url')) {
            $url = "http://" . config('multitenancy.dev_landlord_url') . ":" . ResolveTenant::$LANDLORD_PORT;
        } else {
            $url = config('multitenancy.url_template');
            $url = str_replace('{domain}', $this->domain, $url);
        }
        return $path === '' ? $url : rtrim($url, '/') . '/' . ltrim($path, '/');
    }

    public static function getTenanttDomain() {
        return Config::get('multitenancy.tenant_default_domain');
    }

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('only_pgsql', function ($builder) {
            $builder->whereRaw("driver = 'pgsql'");
        });
    }
}
