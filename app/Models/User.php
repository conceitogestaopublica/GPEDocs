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

#[Fillable(['name', 'email', 'cpf', 'telefone', 'password', 'tipo', 'ug_id', 'unidade_id', 'legado_usuario_id', 'legado_funcionario_id', 'matricula', 'super_admin', 'acesso_geral_ug',
    'cargo', 'sexo', 'data_nascimento', 'email_secundario'])]
#[Hidden(['password', 'remember_token', 'dois_fatores_segredo', 'dois_fatores_recuperacao', 'dois_fatores_ultimo_passo'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** A foto acompanha o usuário em toda tela que o mostra (tramitação, histórico…). */
    protected $appends = ['foto_url'];

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
            'suspenso_em'         => 'datetime',
            'ultimo_acesso_em'    => 'datetime',
            'ultima_atividade_em' => 'datetime',
            'dois_fatores_segredo'     => 'encrypted',
            'dois_fatores_recuperacao' => 'encrypted:array',
            'dois_fatores_ativado_em'  => 'datetime',
            'data_nascimento'          => 'date',
            'preferencias'             => 'array',
        ];
    }

    /** Endereço da foto do servidor (servida por rota autenticada) — null sem foto. */
    public function getFotoUrlAttribute(): ?string
    {
        // Lido cru: em consultas com colunas escolhidas (id, name) a coluna não vem
        $caminho = $this->attributes['foto_path'] ?? null;

        return $caminho ? url("/usuarios/{$this->id}/foto?v=" . substr(md5((string) $caminho), 0, 8)) : null;
    }

    public function ausencias(): HasMany
    {
        return $this->hasMany(UsuarioAusencia::class)->orderByDesc('inicio');
    }

    /**
     * Ausência vigente hoje de cada usuário, em uma consulta: [user_id => "Férias até 15/11"].
     *
     * @param  iterable<int>  $ids
     * @return array<int, string>
     */
    public static function ausentesHoje(iterable $ids): array
    {
        return UsuarioAusencia::vigentes()->whereIn('user_id', collect($ids)->all())
            ->orderBy('fim')->get()->keyBy('user_id')
            ->map(fn (UsuarioAusencia $a) => $a->rotulo())->all();
    }

    /** Verificação em duas etapas (aplicativo autenticador) ativa. */
    public function temDoisFatores(): bool
    {
        return $this->dois_fatores_ativado_em !== null && ! empty($this->dois_fatores_segredo);
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

    /** Setores em que também atende, além do principal (unidade_id) — TR 4.9.2. */
    public function setoresSecundarios(): BelongsToMany
    {
        return $this->belongsToMany(UgOrganograma::class, 'user_setores', 'user_id', 'unidade_id')->withTimestamps();
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

    /**
     * Servidores que podem ser escolhidos como signatários: ativos, com quem
     * está pedindo no topo ("você" — o autor costuma assinar o próprio documento).
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, email: string, voce: bool}>
     */
    public static function paraAssinatura(?int $atualId, bool $soInternos = true)
    {
        $usuarios = static::query()
            ->whereNull('suspenso_em')
            ->when($soInternos, fn ($q) => $q->where('tipo', 'interno'))
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [(int) $atualId])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        $ausentes = static::ausentesHoje($usuarios->pluck('id'));

        return $usuarios->map(fn ($u) => [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'voce' => $u->id === $atualId,
            'ausencia' => $ausentes[$u->id] ?? null,
        ]);
    }
}
