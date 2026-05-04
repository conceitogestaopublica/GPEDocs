onde
## 1. Base legal

| Lei 14.063/2020 | Tipo | Mecanismo | Implementado |
|---|---|---|---|
| art. 4, I | Simples | E-mail + IP + geolocalização + hash | ✅ |
| art. 4, II | Avançada | Cert digital não-ICP-Brasil | ❌ (não comum no contexto público) |
| art. 4, III | Qualificada | Cert ICP-Brasil (A1/A3) | ✅ A1 servidor + A3 cliente |

**Decreto 10.543/2020** define que, para interagir com o poder público, a
**Qualificada** é o único tipo aceito sem restrição. Por isso o foco do GED.

**Padrão técnico adotado**: PAdES-BES com política **AD-RB v2** (DOC-ICP-15.03
do ITI), OID `2.16.76.1.7.1.1.2.3`, algoritmo SHA-256 com RSA. Esta política é
a referência básica para assinaturas em PDF aceitas em órgãos brasileiros.

---

## 2. Visão geral da arquitetura

```
┌─────────────────────────────────────────────────────────────────┐
│  Frontend (Inertia + React)                                     │
│  resources/js/Pages/GED/Assinaturas/Index.jsx                   │
│  ├── FormSimples       ──┐                                      │
│  ├── FormIcp     (A1)  ──┤  POST /assinaturas/{id}/...          │
│  └── FormIcpA3   (A3)  ──┘  (Web PKI Lacuna)                    │
└─────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  AssinaturaController                                            │
│  app/Http/Controllers/AssinaturaController.php                   │
│  ├── assinar()          (Simples)                               │
│  ├── assinarIcp()       (A1)                                    │
│  ├── prepararIcpA3()    (A3 etapa 1)                            │
│  ├── finalizarIcpA3()   (A3 etapa 2)                            │
│  ├── recusar()                                                  │
│  ├── manifesto()        (PDF do registro de assinaturas)        │
│  └── downloadAssinado()                                         │
└─────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  Services                                                        │
│  ├── CertificadoService       (parse PFX, valida cadeia, CPF)   │
│  ├── AssinaturaIcpService     (A1: openssl_pkcs7_sign + TCPDF)  │
│  ├── AssinaturaIcpA3Service   (A3: ASN.1 manual + external sig) │
│  ├── AssinaturaValidadorService (re-abre PDF e valida)          │
│  └── AsnDer                   (helpers ASN.1 DER)               │
└─────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  Persistência                                                    │
│  ├── ged_solicitacoes_assinatura                                │
│  ├── ged_assinaturas                                            │
│  ├── ged_certificados                                           │
│  └── storage/app/private/                                       │
│      ├── icp-brasil/raiz/*.pem        (truststore ITI)          │
│      ├── icp-brasil/intermediarias/*  (ACs intermediárias)      │
│      ├── icp-brasil-internal/         (cert dummy do A3)        │
│      └── assinaturas/icp/             (PDFs assinados)          │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Os três tipos de assinatura

### 3.1 Simples (Lei 14.063/2020 art. 4, I)

Não envolve criptografia: registra-se apenas evidência circunstancial da
identidade (CPF, e-mail, IP, geolocalização, hash do documento).

**Endpoint**: `POST /assinaturas/{id}/assinar`
**Payload**: `{ cpf, geolocalizacao? }`
**Persistência**: `ged_assinaturas.tipo_assinatura = 'simples'`, sem PKCS#7.

Útil para fluxos internos onde o emissor confia na autenticação Laravel mas
quer evidência adicional. Não é aceito sem restrição em interações com o
poder público.

### 3.2 Qualificada A1 (servidor)

O usuário faz upload do arquivo `.pfx`/`.p12` (cert + chave privada
criptografados com senha) que ele mesmo gerou e baixou da AC ICP-Brasil.

```
Browser ──[PFX + senha]──→ Server
                           ├── openssl_pkcs12_read (PFX → cert + key em memória)
                           ├── valida cadeia ICP-Brasil contra truststore
                           ├── confere CPF do cert × CPF do usuário
                           ├── extrai metadados via openssl_x509_parse
                           ├── TCPDF + FPDI montam PDF com /AcroForm + /Sig
                           └── openssl_pkcs7_sign embute o envelope
                           
Server ──[PDF assinado]──→ Browser
```

**Endpoint**: `POST /assinaturas/{id}/assinar-icp` (multipart)
**Payload**: `{ pfx (file), senha, razao?, local?, geolocalizacao? }`

⚠️ A senha é processada em RAM e nunca persistida. Os bytes da chave
privada também só vivem na requisição.

### 3.3 Qualificada A3 (token/smartcard, two-step)

A chave privada vive no token e nunca sai dele. O servidor calcula o que
deve ser assinado, o token assina, o servidor monta o envelope.

```
[Browser] Web PKI extension lista certs do token/smartcard/Windows store
[Browser] usuário escolhe → readCertificate(thumbprint) → cert PEM público
[Browser] POST /assinaturas/{id}/preparar-icp-a3 { cert_pem }
[Server]  AssinaturaIcpA3Service::preparar()
          ├── valida cert ICP-Brasil
          ├── confere CPF cert × user
          ├── gera PDF com placeholder via TCPDF (cert dummy descartável)
          ├── extrai /ByteRange
          ├── content_hash = SHA-256(byteRange)
          ├── monta SignedAttributes ASN.1:
          │     ├── contentType (1.2.840.113549.1.9.3) → id-data
          │     ├── messageDigest (1.2.840.113549.1.9.4) → content_hash
          │     ├── signingTime (1.2.840.113549.1.9.5) → UTCTime now
          │     └── signingCertV2 (1.2.840.113549.1.9.16.2.47) → SHA-256(cert)
          ├── hash_a_assinar = SHA-256(DER de SignedAttributes)
          └── cacheia { pdf_placeholder, signed_attrs, byte_range, cert } por 10min
[Server → Browser] { sessao_id, hash_a_assinar (b64), algoritmo_digest }

[Browser] pki.signHash({ thumbprint, hash, 'SHA-256' })
          → token solicita PIN do usuário
          → token executa RSA(SHA-256-DigestInfo(hash), pkcs1v15)
          → assinatura_b64

[Browser] POST /assinaturas/{id}/finalizar-icp-a3 { sessao_id, assinatura_b64 }
[Server]  AssinaturaIcpA3Service::finalizar()
          ├── recupera estado do cache
          ├── extrai issuer DN (DER) e serial via phpseclib3
          ├── monta SignerInfo:
          │     ├── version 1
          │     ├── sid: IssuerAndSerialNumber
          │     ├── digestAlgorithm: SHA-256
          │     ├── signedAttrs [0] IMPLICIT (re-tag de SET → 0xA0)
          │     ├── signatureAlgorithm: rsaEncryption
          │     └── signature: bytes do token
          ├── monta SignedData:
          │     ├── version 1
          │     ├── digestAlgorithms: { SHA-256 }
          │     ├── encapContentInfo: id-data (detached, sem eContent)
          │     ├── certificates [0]: signer + cadeia
          │     └── signerInfos: { SignerInfo }
          ├── envelopa em ContentInfo (signedData)
          └── substitui /Contents do PDF (preenche zeros até tamanho original)

[Server → Browser] { ok: true, caminho }
```

**Endpoints**:
- `POST /assinaturas/{id}/preparar-icp-a3` (JSON)
- `POST /assinaturas/{id}/finalizar-icp-a3` (JSON)

A extensão **Web PKI da Lacuna** (gratuita) precisa estar instalada no
navegador do signatário. Suporta tokens A3 (USB), smartcards e certs do
Windows/Mac/Linux cert store.

---

## 4. Componentes do código

### 4.1 Services

| Arquivo | Responsabilidade |
|---|---|
| `CertificadoService` | Abre PFX, valida cadeia ICP-Brasil contra truststore, extrai CPF (OID 2.16.76.1.3.1) e CNPJ (OID 2.16.76.1.3.3), persiste cert público |
| `AssinaturaIcpService` | Assinatura A1: TCPDF/FPDI + `openssl_pkcs7_sign` |
| `AssinaturaIcpA3Service` | Assinatura A3: external signing two-step com ASN.1 manual |
| `AssinaturaValidadorService` | Re-abre PDF, extrai `/ByteRange`/`/Contents`, valida via `openssl smime -verify` (CLI), valida cadeia, decodifica metadados PAdES |
| `AsnDer` | Helpers ASN.1 DER puros: `seq()`, `set()`, `oid()`, `octet()`, `intFromBytes()`, `utcTime()`, `ctxExplicit()`, `reTag()` |

### 4.2 Controllers

| Método | Rota | Tipo |
|---|---|---|
| `index` | `GET /assinaturas` | Lista pendentes/assinadas do usuário |
| `solicitar` | `POST /documentos/{id}/solicitar-assinatura` | Cria solicitação |
| `solicitarLote` | `POST /assinaturas/solicitar-lote` | Múltiplos documentos |
| `assinar` | `POST /assinaturas/{id}/assinar` | Simples |
| `assinarIcp` | `POST /assinaturas/{id}/assinar-icp` | A1 |
| `prepararIcpA3` | `POST /assinaturas/{id}/preparar-icp-a3` | A3 etapa 1 |
| `finalizarIcpA3` | `POST /assinaturas/{id}/finalizar-icp-a3` | A3 etapa 2 |
| `recusar` | `POST /assinaturas/{id}/recusar` | Recusa |
| `manifesto` | `GET /assinaturas/{id}/manifesto` | Gera PDF manifesto |
| `downloadAssinado` | `GET /assinaturas/{id}/download-assinado` | Baixa PDF PAdES |

Validador (público, sem auth):

| Método | Rota |
|---|---|
| `validarPdfPagina` | `GET /validar-assinatura` |
| `validarPdf` | `POST /validar-assinatura` |

### 4.3 Frontend

| Arquivo | Conteúdo |
|---|---|
| `resources/js/Pages/GED/Assinaturas/Index.jsx` | Lista pendentes/assinadas + `AssinarModal` com 3 abas (`FormSimples`, `FormIcp`, `FormIcpA3`) |
| `resources/js/Pages/GED/ValidarAssinatura.jsx` | Página pública drag-and-drop de validação |

---

## 5. Esquema de banco

### `ged_solicitacoes_assinatura`
| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint | PK |
| `documento_id` | FK → ged_documentos | |
| `solicitante_id` | FK → users | |
| `status` | string(20) | pendente / em_andamento / concluida / cancelada |
| `mensagem` | text | |
| `prazo` | timestamp | nullable |

### `ged_assinaturas`
| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint | PK |
| `solicitacao_id` | FK | |
| `documento_id` | FK | |
| `signatario_id` | FK → users | |
| `ordem` | int | |
| `status` | string(20) | pendente / assinado / recusado |
| `tipo_assinatura` | string(20) | simples / qualificada |
| `certificado_id` | FK → ged_certificados | só em qualificadas |
| `email_signatario` | string | |
| `cpf_signatario` | string(14) | |
| `ip` | string(45) | |
| `geolocalizacao` | string(255) | |
| `user_agent` | text | |
| `hash_documento` | string(64) | SHA-256 |
| `assinatura_pkcs7` | longblob | envelope CMS detached |
| `cadeia_certificados` | json | resumo da cadeia |
| `politica_assinatura` | string(120) | ex: "AD-RB v2 (OID 2.16.76.1.7.1.1.2.3)" |
| `algoritmo_hash` | string(20) | SHA-256 |
| `arquivo_assinado_path` | string(500) | em storage/app/private |
| `hash_assinatura_sha256` | string(64) | hash do envelope |
| `timestamp_assinatura` | timestamp | |
| `versao_id` | FK → ged_versoes | |
| `motivo_recusa` | text | |
| `assinado_em` | timestamp | |

### `ged_certificados`
| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint | PK |
| `user_id` | FK → users | |
| `tipo` | string(5) | A1 / A3 |
| `subject_cn`, `subject_cpf`, `subject_dn` | | titular |
| `issuer_cn`, `issuer_dn` | | AC emissora |
| `serial_number` | string(80) | hex |
| `thumbprint_sha1`, `thumbprint_sha256` | | |
| `valido_de`, `valido_ate` | timestamp | |
| `certificado_pem` | text | público (sem chave privada) |
| `cadeia_pem` | json | intermediárias |
| `politica_oid` | string(80) | |
| `icp_brasil` | bool | |
| `revogado` | bool | placeholder; OCSP/CRL não implementado |

Índice único: `(user_id, thumbprint_sha256)` — mesmo cert reusado entre assinaturas.

### `users`
- Campo `cpf` (string 14) adicionado para conferência cert × usuário.

---

## 6. Validação independente

A página `/validar-assinatura` é **pública** e funciona offline contra a
truststore local. Não consulta OCSP/CRL (revogação em tempo real).

O serviço extrai todas as assinaturas do PDF (multi-signature suportado) e
para cada uma reporta:

- ✅/❌ **Integridade criptográfica** — hash do `/ByteRange` confere com o
  `messageDigest` do PKCS#7 e a assinatura RSA do cert valida o hash do
  `SignedAttributes` (executado por `openssl smime -verify` via shell)
- ✅/❌ **Cadeia ICP-Brasil** — `openssl_x509_checkpurpose` contra a
  truststore de `storage/app/private/icp-brasil/`
- ✅/❌ **Validade temporal** — `valido_de ≤ now ≤ valido_ate`

Mais metadados extraídos:
- Algoritmo (SHA-1 / SHA-256 / SHA-512)
- Signatário: CN, CPF, AC emissora, serial, thumbprint
- PAdES: `/Reason`, `/Location`, `/Name`, `/M` (timestamp), `/SubFilter`

> **Bug notório do PHP no Windows**: `openssl_pkcs7_verify()` e
> `openssl_cms_verify()` falham ao verificar PKCS#7 detached mesmo quando o
> envelope é válido. Por isso o validador chama o `openssl smime -verify`
> via `shell_exec`. O CLI sempre está disponível onde o PHP openssl está.

---

## 7. Manifesto PDF

`GET /assinaturas/{id}/manifesto` gera um PDF (DomPDF) descrevendo:

1. Dados do documento (nome, tipo documental, autor, hash, QR token)
2. Solicitação de assinatura (solicitante, data, status, mensagem)
3. Tabela de signatários com CPF mascarado, status, tipo (Simples /
   Avançada / Qualificada), data, IP
4. Para cada **Qualificada**: card detalhado com titular, AC emissora,
   serial, validade, thumbprint SHA-256, algoritmo, política, timestamp,
   hash do PDF e hash do envelope PKCS#7
5. Base legal completa (Lei 14.063/2020 + DOC-ICP-15.03)

Template: `resources/views/assinaturas/manifesto.blade.php`.

---

## 8. Truststore ICP-Brasil

A validação de cadeia depende de ter os certificados raiz e intermediários
da ICP-Brasil em `storage/app/private/icp-brasil/`.

### Instalação

```bash
php artisan icp-brasil:install-truststore
```

Baixa do servidor oficial `acraiz.icpbrasil.gov.br` os certs configurados
em `config/icp_brasil.php`. Por padrão a AC Raiz V10 (válida até 2032-07-01).

```bash
php artisan icp-brasil:install-truststore --force
```

Sobrescreve certs existentes (use após o ITI publicar nova versão).

### Verificação

```bash
php artisan icp-brasil:verify-truststore
```

Lista os certs instalados, valida formato X.509 e mostra validade. Sai com
`exit code 1` se a truststore estiver vazia ou se a AC Raiz estiver
faltando.

### ACs intermediárias

O comando hoje só baixa a AC Raiz. As intermediárias (Serasa, Caixa,
Certisign, Valid, Soluti, etc.) precisam ser baixadas manualmente do
repositório do ITI e colocadas em `storage/app/private/icp-brasil/intermediarias/`.

Para acrescentar uma URL de download ao comando, edite a chave
`'intermediarias'` em `config/icp_brasil.php`.

---

## 9. Comandos artisan

| Comando | Função |
|---|---|
| `icp-brasil:install-truststore` | Baixa AC Raiz V10 e intermediárias configuradas |
| `icp-brasil:verify-truststore` | Lista e valida certs instalados |

---

## 10. Configuração

### `.env`

```env
# Opcional — sobrescreve URLs do ITI caso mudem
ICP_BRASIL_RAIZ_V10_URL=https://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv10.crt
```

### `config/icp_brasil.php`

```php
return [
    'truststore_path' => storage_path('app/private/icp-brasil'),
    'politica_padrao' => [
        'oid'  => '2.16.76.1.7.1.1.2.3',
        'nome' => 'AD-RB v2 (Assinatura Digital de Referência Básica)',
        'doc'  => 'DOC-ICP-15.03',
    ],
    'raiz' => [...],
    'intermediarias' => [],
];
```

---

## 11. Como rodar localmente

```bash
# 1) Instalar deps PHP
composer install

# 2) Migrar
php artisan migrate

# 3) Instalar truststore ICP-Brasil
php artisan icp-brasil:install-truststore

# 4) Verificar
php artisan icp-brasil:verify-truststore

# 5) Servir + assets
composer dev   # Laravel + queue + Pail + Vite
```

Para A3 (token), o navegador precisa da **extensão Web PKI da Lacuna**:
https://get.webpkiplugin.com (Chrome/Firefox/Edge — gratuita).

---

## 12. Limitações e roadmap

| Item | Status | Plano |
|---|---|---|
| Carimbo de tempo qualificado (TSA) | ❌ | Integrar TSA — eg. Serpro, Bry — em PAdES-T |
| OCSP / CRL online | ❌ | Validador checa só validade temporal |
| PAdES-LT / PAdES-LTA | ❌ | Anexar OCSP responses + TS no envelope |
| Auto-cadastro de cadeia ICP intermediária | parcial | Hoje só Raiz auto, intermediárias manuais |
| Suporte a múltiplas assinaturas no mesmo PDF | parcial | Validador detecta múltiplas; signing acrescenta sempre uma nova ao final, mas não cobre re-validação encadeada |
| Revogação manual de cert (admin) | ❌ | Coluna `revogado` existe, sem UI |

---

## 13. Bugs conhecidos (com workarounds aplicados)

### `openssl_pkcs7_sign` no Windows + OpenSSL 3

Recusa decodificar PKCS#8 quando passado como path:
`error:1E08010C:DECODER routines::unsupported`. **Workaround**: passar
cert/key como **PEM inline** ao TCPDF. `extracerts` continua precisando
ser file path. Ver `AssinaturaIcpService::gerarPdfAssinado()`.

### `openssl_pkcs7_verify` / `openssl_cms_verify` no Windows + OpenSSL 3

Falham com `CMS verification failure` mesmo para envelopes válidos.
**Workaround**: chamar `openssl smime -verify` via `shell_exec`. Ver
`AssinaturaValidadorService::validarUma()`.

### `openssl_pkey_new()` falha sem `openssl.cnf`

`error:07000072:configuration file routines::no such file`.
**Workaround**: `AssinaturaIcpA3Service::materialDummy()` usa o `openssl
req` CLI para gerar o par dummy de placeholder.

### `preg_replace` com `'$1' . $hex . '$2'` come capture groups

Quando `$hex` começa com dígito, PHP interpreta `$10` como capture group
10 (inexistente → vazio). **Workaround**: usar `'${1}'` e `'${2}'`
explicitamente. Ver `AssinaturaIcpA3Service::substituirContents()`.

### `openssl_x509_parse($pem, false)` retorna keys em long-name

Use `openssl_x509_parse($pem, true)` para receber `CN`, `O`, `C` como
keys curtas. `false` retorna `commonName`, `organizationName`, etc.

---

## 14. Como testar

### Testar A1 servidor

1. Gerar um PFX self-signed:
   ```bash
   openssl req -x509 -newkey rsa:2048 -keyout u.key -out u.crt -days 365 -nodes \
     -subj "/C=BR/O=ICP-Brasil/OU=Pessoa Fisica/CN=TESTE:01234567890"
   openssl pkcs12 -export -out u.pfx -inkey u.key -in u.crt -password pass:teste123
   ```
2. Adicionar `u.crt` à truststore: `cp u.crt storage/app/private/icp-brasil/raiz/test.pem`
3. Solicitar uma assinatura no GED, escolher tipo "Qualificada A1", upload
   do `u.pfx` com senha `teste123`.

### Testar A3 (sem token físico)

Use o script abaixo para simular o fluxo sem extensão:

```php
// Simula o que o Web PKI faz (assinar hash com PKCS1 v1.5 + DigestInfo)
$hashBin = base64_decode($prep['hash_a_assinar']);
$digestInfo = hex2bin('3031300d060960864801650304020105000420') . $hashBin;
openssl_private_encrypt($digestInfo, $assinatura, $pkey, OPENSSL_PKCS1_PADDING);
```

Esse foi o método usado para validar end-to-end o serviço A3 antes do
deploy com hardware real.

### Validar um PDF assinado

1. Acesse `/validar-assinatura` (público)
2. Arraste o PDF
3. Veja relatório por assinatura

Ou via CLI:
```bash
openssl smime -verify -in pkcs7.bin -inform DER -content covered.bin -noverify -binary
```

---

## 15. Referências

| Documento | URL |
|---|---|
| Lei 14.063/2020 | https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2020/lei/l14063.htm |
| Decreto 10.543/2020 | https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2020/decreto/d10543.htm |
| DOC-ICP-15 (PAdES) | https://www.gov.br/iti/pt-br/centrais-de-conteudo/doc-icp-15-versao-3-0.pdf |
| DOC-ICP-04 (OIDs PF/PJ) | https://www.gov.br/iti/pt-br/centrais-de-conteudo/doc-icp-04-versao-8-0.pdf |
| RFC 5652 (CMS) | https://datatracker.ietf.org/doc/html/rfc5652 |
| RFC 5035 (signing-cert-v2) | https://datatracker.ietf.org/doc/html/rfc5035 |
| AC Raiz V10 (download) | https://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv10.crt |
| Web PKI Lacuna (extensão) | https://get.webpkiplugin.com |
| Web PKI docs (JS API) | https://docs.lacunasoftware.com/articles/web-pki/ |

### OIDs relevantes

| OID | Significado |
|---|---|
| `1.2.840.113549.1.7.1` | id-data |
| `1.2.840.113549.1.7.2` | id-signedData |
| `1.2.840.113549.1.9.3` | content-type |
| `1.2.840.113549.1.9.4` | message-digest |
| `1.2.840.113549.1.9.5` | signing-time |
| `1.2.840.113549.1.9.16.2.47` | signing-certificate-v2 (RFC 5035) |
| `2.16.840.1.101.3.4.2.1` | SHA-256 |
| `1.2.840.113549.1.1.1` | rsaEncryption |
| `1.2.840.113549.1.1.11` | sha256WithRSAEncryption |
| `2.16.76.1.3.1` | ICP-Brasil — CPF do titular (subjectAltName otherName) |
| `2.16.76.1.3.3` | ICP-Brasil — CNPJ do titular |
| `2.16.76.1.7.1.1.2.3` | Política AD-RB v2 |

---

*Documento mantido junto ao código. Atualize sempre que mexer nos serviços
de assinatura ou na truststore.*
