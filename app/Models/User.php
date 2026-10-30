<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'cpf', 'telefone', 'password', 'tipo', 'ug_id', 'unidade_id', 'legado_usuario_id', 'legado_funcionario_id', 'matricula', 'super_admin', 'acesso_geral_ug'])]
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

    /**
     * Usuários com este CPF, comparando só os dígitos dos dois lados.
     *
     * O cadastro grava o CPF como foi digitado ("020.576.226-30") e quem integra manda só
     * dígitos ("02057622630"). Comparar a coluna crua não acha ninguém, e a integração criava
     * um usuário externo duplicado: o documento ia para ele, não para a conta de quem assina.
     * REPLACE encadeado (e não regexp_replace) para valer igual no PostgreSQL, MySQL e SQLite.
     *
     * A conta interna vem primeiro: bancos que já passaram pelo defeito têm, para o mesmo CPF,
     * a conta real e o externo duplicado — e `->first()` tem de devolver a real.
     */
    public function scopePorCpf(Builder $query, string $cpf): Builder
    {
        $digitos = preg_replace('/\D/', '', $cpf);

        // Sem dígitos não há CPF: sem esta trava, casaria com quem tem cpf '' ou só máscara.
        if ($digitos === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereNotNull('cpf')
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), '/', ''), ' ', '') = ?", [$digitos])
            ->orderByRaw("CASE WHEN tipo = 'externo' THEN 1 ELSE 0 END")
            ->orderBy('id');
    }

    /**
     * E-mail da conta que entra pelo CPF no login: '' quando não há conta, false quando o CPF
     * é ambíguo. Havendo várias, vale a única INTERNA — o externo duplicado que a integração
     * criou para o mesmo CPF (e-mail provisório, senha aleatória) não é quem está entrando.
     * Um externo sozinho (cadastrado com senha pelo administrador) entra normalmente.
     */
    public static function emailParaLoginPorCpf(string $cpf): string|false
    {
        $contas = static::porCpf($cpf)->get(['email', 'tipo']);
        if ($contas->count() <= 1) {
            return (string) ($contas->first()?->email ?? '');
        }

        $internas = $contas->where('tipo', '!=', 'externo');

        return $internas->count() === 1 ? (string) $internas->first()->email : false;
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
