<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'cpf', 'password', 'tipo', 'ug_id', 'unidade_id', 'legado_usuario_id', 'super_admin', 'acesso_geral_ug'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'super_admin'     => 'boolean',
            'acesso_geral_ug' => 'boolean',
        ];
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'autor_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'ged_user_roles', 'user_id', 'role_id');
    }

    public function notificacoes(): HasMany
    {
        return $this->hasMany(Notificacao::class, 'usuario_id');
    }

    public function favoritos(): BelongsToMany
    {
        return $this->belongsToMany(Documento::class, 'ged_favoritos', 'user_id', 'documento_id')
            ->withPivot('created_at');
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class);
    }

    /**
     * UG primaria do usuario (mantida por compat — vai sumir em uma migracao futura).
     * Use `ugs()` para o vinculo multi-UG e `ugAtual()` para a UG ativa na sessao.
     */
    public function ug(): BelongsTo
    {
        return $this->belongsTo(Ug::class, 'ug_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(UgOrganograma::class, 'unidade_id');
    }

    /**
     * Vinculo multi-tenant: UGs em que o usuario tem acesso.
     */
    public function ugs(): BelongsToMany
    {
        return $this->belongsToMany(Ug::class, 'user_ugs', 'user_id', 'ug_id')
            ->withPivot('principal')
            ->withTimestamps();
    }

    /**
     * UG ativa na sessao (escolhida no login). Para super_admin pode ser null
     * (ver tudo) ou a UG explicitamente selecionada.
     */
    public function ugAtual(): ?Ug
    {
        $id = session('ug_id');
        if (! $id) {
            return null;
        }
        return $this->ugs()->where('ugs.id', $id)->first()
            ?? ($this->super_admin ? Ug::find($id) : null);
    }

    /**
     * Verifica se o usuario tem acesso a uma UG especifica.
     */
    public function temAcessoUg(int $ugId): bool
    {
        if ($this->super_admin) {
            return true;
        }
        return $this->ugs()->where('ugs.id', $ugId)->exists();
    }

    /** @var list<string>|null memo por requisição — o menu e cada rota consultam várias vezes */
    private ?array $permissoesMemo = null;

    /**
     * Nomes das permissões do usuário, somadas de todos os perfis. Super admin não
     * passa por aqui: o Gate::before o libera antes (AppServiceProvider).
     *
     * @return list<string>
     */
    public function permissoes(): array
    {
        return $this->permissoesMemo ??= \Illuminate\Support\Facades\DB::table('ged_user_roles as ur')
            ->join('ged_role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('ged_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('ur.user_id', $this->id)
            ->distinct()
            ->pluck('p.nome')
            ->all();
    }

    public function temPermissao(string $nome): bool
    {
        return $this->super_admin || in_array($nome, $this->permissoes(), true);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\RedefinirSenha($token));
    }

    public function ehInterno(): bool
    {
        return $this->tipo === 'interno';
    }

    public function ehExterno(): bool
    {
        return $this->tipo === 'externo';
    }
}
