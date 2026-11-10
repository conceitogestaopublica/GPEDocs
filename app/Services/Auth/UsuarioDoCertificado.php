<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Listeners\RegistrarAcesso;
use App\Models\User;
use App\Services\CertificadoService;

/**
 * Do certificado ao usuário: validade, cadeia até a AC Raiz ICP-Brasil com revogação (LCR) e um
 * único usuário com o CPF do e-CPF. Serve às duas entradas com certificado — pela extensão Web PKI
 * (desafio assinado) e pelo certificado cliente do HTTPS, em que a posse da chave já foi provada
 * no aperto de mão do TLS.
 */
class UsuarioDoCertificado
{
    public function __construct(private CertificadoService $certificados)
    {
    }

    /** @return array{user: ?User, erro: ?string, cn: ?string} */
    public function conferir(string $certPem): array
    {
        $meta = $this->certificados->lerMetadados($certPem);
        $cn = $meta['subject_cn'] ?? null;
        $agora = time();
        if ($agora < strtotime($meta['valido_de']) || $agora > strtotime($meta['valido_ate'])) {
            return $this->recusa('Certificado fora da validade.', $cn);
        }

        $cadeia = $this->certificados->verificarCadeia($certPem);
        if (! $cadeia['ok']) {
            return $this->recusa($cadeia['motivo'], $cn);
        }

        $cpf = preg_replace('/\D/', '', (string) ($meta['subject_cpf'] ?? ''));
        if (strlen($cpf) !== 11) {
            return $this->recusa('Este certificado não é um e-CPF (não traz o CPF do titular).', $cn);
        }

        $usuarios = User::query()
            ->whereNotNull('cpf')
            ->whereRaw("regexp_replace(cpf, '[^0-9]', '', 'g') = ?", [$cpf])
            ->limit(2)
            ->get();
        if ($usuarios->count() !== 1) {
            RegistrarAcesso::recusa(null, $usuarios->isEmpty() ? 'certificado sem usuário com o CPF' : 'CPF em mais de um usuário', 'certificado', $cn);

            return ['user' => null, 'cn' => $cn, 'erro' => $usuarios->isEmpty()
                ? 'Nenhum usuário cadastrado com o CPF deste certificado. Procure o administrador.'
                : 'O CPF deste certificado está em mais de um usuário. Procure o administrador.'];
        }

        return ['user' => $usuarios->first(), 'erro' => null, 'cn' => $cn];
    }

    private function recusa(string $mensagem, ?string $cn): array
    {
        RegistrarAcesso::recusa(null, $mensagem, 'certificado', $cn);

        return ['user' => null, 'erro' => $mensagem, 'cn' => $cn];
    }
}
