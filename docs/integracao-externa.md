# Integração com Sistemas Externos

API REST para sistemas externos (GPE, RH, Patrimônio, etc) enviarem
documentos para assinatura digital + arquivamento no GPE Docs.

## Cadastro

Cada sistema externo precisa estar cadastrado e ter um API token. Use:

```bash
php artisan ged:sistema-cadastrar gpe "GPE - Sistema de Gestao Publica"
```

O token é exibido **uma única vez** no console. Guarde-o.
Para regenerar: `--regenerar-token` (invalida o anterior).

## Autenticação

Todas as requisições da API exigem o header:

```
Authorization: Bearer {seu_token_de_64_chars}
```

## Endpoints

### `POST /api/integracoes/documentos`

Envia um documento para assinatura.

**Body (JSON):**

```json
{
  "tipo": "Empenho",
  "ug_codigo": "UG-001",
  "numero": "2026/000123",
  "nome": "Empenho 2026/000123 - Fornecedor X",
  "descricao": "Empenho de aquisicao de material de escritorio",
  "metadados": {
    "valor": 5000.00,
    "fornecedor": "Fornecedor X Ltda",
    "cnpj_fornecedor": "00.000.000/0001-00",
    "dotacao_orcamentaria": "02.04.04.122.1001.2003.339030"
  },
  "pdf_base64": "JVBERi0xLjQKJ...",
  "signatarios": [
    { "cpf": "12345678900", "ordem": 1, "email": "ordenador@municipio.gov.br" },
    { "cpf": "98765432100", "ordem": 2 }
  ],
  "callback_url": "https://gpe.exemplo.com/webhooks/assinatura",
  "pasta_codigo": "EMPENHOS"
}
```

**Campos:**

| campo | obrigatório | descrição |
|---|---|---|
| `tipo` | sim | Nome do tipo documental cadastrado no GPE Docs (ex: "Empenho", "Liquidação") |
| `ug_codigo` | sim | Código da UG destinatária (multi-tenant) |
| `numero` | sim | Número externo no sistema de origem (ex: "2026/000123") |
| `nome` | sim | Nome de exibição |
| `descricao` | não | Texto livre |
| `metadados` | não | Objeto JSON com chave/valor — exibido no GPE Docs |
| `pdf_base64` | sim | PDF codificado em base64 |
| `signatarios` | sim | Array com pelo menos 1 signatário (CPF + ordem opcional) |
| `signatarios[].cpf` | sim | CPF (com ou sem máscara) |
| `signatarios[].ordem` | não | Inteiro 1+ — define ordem sequencial de assinatura |
| `signatarios[].email` | não | E-mail do signatário (opcional). No externo, recebe o link de assinatura |
| `signatarios[].externo` | não | `true` = signatário de fora da prefeitura (cidadão, empresa, fornecedor): assina **por link**, com cadastro (ver abaixo) |
| `signatarios[].nome` | se `externo` | Nome completo do signatário externo |
| `signatarios[].telefone` | não | Celular do externo (informativo) |
| `responsavel_cpf` | se todos forem externos | CPF do servidor que envia — vira autor do documento e solicitante (recebe as notificações). Sem ele, o autor é o 1º signatário interno |
| `callback_url` | não | URL para webhook após todas as assinaturas concluídas |
| `pasta_codigo` | não | Nome da pasta de arquivamento no GPE Docs |

**Resposta `201`:**

```json
{
  "id": 42,
  "numero_externo": "2026/000123",
  "sistema_origem": "gpe",
  "tipo": "Empenho",
  "ug": "UG-001",
  "pasta": "EMPENHOS",
  "url_visualizacao": "https://docs.exemplo.gov.br/documentos/42",
  "solicitacao_id": 17,
  "signatarios": [
    { "id": 81, "cpf": "12345678900", "ordem": 1 },
    { "id": 82, "cpf": "98765432100", "ordem": 2 }
  ],
  "criado_em": "2026-05-02T17:30:00-03:00"
}
```

#### Signatário externo (assinatura por link)

Para quem **não é usuário do GPE Docs** (fornecedor, contribuinte, cidadão), envie o
signatário com `"externo": true`, nome e CPF (o CPF precisa ser válido):

```json
"responsavel_cpf": "12345678900",
"signatarios": [
  { "cpf": "52998224725", "externo": true, "nome": "Maria Fornecedora da Silva",
    "email": "maria@fornecedor.com.br", "ordem": 1 }
]
```

Na resposta, cada externo traz o link de assinatura — o sistema de origem pode
mandá-lo por WhatsApp/SMS além do e-mail que o GPE Docs já envia:

```json
"signatarios": [
  { "id": 4, "cpf": "52998224725", "ordem": 1, "externo": true,
    "link_assinatura": "https://docs.exemplo.gov.br/assinar/WLODCa1r2VYO..." }
]
```

Como o externo assina:

1. abre o link (vale 30 dias, ou até o `prazo` da solicitação);
2. entra com CPF e senha ou faz um cadastro rápido — **o CPF tem de ser o do convite** —
   e confirma o e-mail com um código de 6 dígitos;
3. vê o PDF, posiciona/redimensiona/gira o carimbo e confirma com a senha;
4. o PDF carimbado ("Assinado digitalmente por… CPF… código de verificação") fica no
   GPE Docs como versão oficial — o mesmo `pdf-assinado` desta API.

Em solicitação `sequencial`, o externo só recebe o e-mail (e só consegue assinar) quando
chegar a vez dele. O webhook `assinatura.individual` do externo traz `externo: true` e
`signatario_nome`. A consulta (`GET .../{numero}`) mostra `link_assinatura` enquanto
pendente e `codigo_verificacao` depois de assinado; o código é conferido em
`/verificar/codigo/{codigo}`.

#### Pacote: vários documentos num link só

Para mandar vários documentos ao mesmo externo (ex.: os empenhos do dia de um fornecedor)
sem gerar um e-mail e um link por documento, envie em cada chamada o mesmo código de pacote:

```json
"pacote": "EMPENHOS-2026-10-23",
"pacote_titulo": "Empenhos de 23/10/2026"
```

Os documentos com o mesmo `pacote` (do mesmo sistema) entram no mesmo link de cada externo
(`link_pacote` na resposta). O e-mail sai na primeira chamada e no máximo um a cada
10 minutos depois — a página do link sempre mostra a lista atualizada.

Para o e-mail sair **uma vez, com a lista completa**, envie cada documento do lote com
`"notificar": false` e, no fim, chame:

```
POST /api/integracoes/pacotes/{codigo}/notificar
→ 200 { "pacote": "EMPENHOS-2026-10-23", "emails_enviados": 2 }
```

Só recebe e-mail quem já tem algum documento na vez (em ordem, os demais recebem quando
chegar a vez deles). Lá o externo abre
cada documento ou usa **"Assinar todos"** (confirma que conferiu e digita a senha uma vez).
No "Assinar todos", se o signatário veio com `signature_position`, o carimbo vai nesse
quadro; senão, na parte de baixo da última página.

Para **servidores** (ordenador, contador…) não é preciso nada na API: os documentos caem nos
pendentes de cada um no GPE Docs, onde ele marca vários e usa **"Assinar selecionados"**
(simples com CPF ou ICP A1 com certificado e senha uma vez).

Assinatura por link só vale para PDF. É assinatura eletrônica com identificação por
cadastro (CPF + e-mail confirmado + senha), IP, data/hora e hash — **não** é ICP-Brasil.

### `POST /api/integracoes/documentos/{numero_externo}/versao`

Envia uma **nova versão** do PDF (substitui o anterior) **antes de qualquer assinatura**.
Bloqueado com `409 Conflict` se já houver assinatura ou recusa registrada.

**Body:**

```json
{
  "pdf_base64": "JVBERi0xLjQKJ...",
  "comentario": "Correcao no valor"
}
```

**Resposta `200`:**

```json
{
  "numero_externo": "2026/000123",
  "versao_atual": 2,
  "tamanho": 248391,
  "visualizacao_url": "..."
}
```

### `POST /api/integracoes/documentos/{numero_externo}/reenviar-webhook`

Reenvia o webhook `assinatura.todas_concluidas` para o `callback_url` original.
Útil quando o sistema externo perdeu o callback (queda, indisponibilidade).

Só funciona se todas as assinaturas já foram concluídas.

**Resposta `200`:**

```json
{ "sucesso": true, "mensagem": "Webhook reenviado com sucesso." }
```

### `GET /api/integracoes/documentos/{numero_externo}`

Consulta o status de um documento já enviado (útil pra polling antes do webhook).

**Resposta `200`:**

```json
{
  "id": 42,
  "numero_externo": "2026/000123",
  "status": "rascunho",
  "solicitacao": "em_andamento",
  "assinaturas": [
    { "cpf": "12345678900", "ordem": 1, "status": "assinado", "assinado_em": "2026-05-02T18:00:00-03:00" },
    { "cpf": "98765432100", "ordem": 2, "status": "pendente", "assinado_em": null }
  ],
  "todas_assinadas": false,
  "callback_executado": false,
  "pdf_assinado_url": "https://docs.exemplo.gov.br/documentos/42/download",
  "visualizacao_url": "https://docs.exemplo.gov.br/documentos/42"
}
```

### `GET /api/integracoes/documentos/{numero_externo}/pdf-assinado`

Devolve o PDF assinado: o arquivo da assinatura mais recente, que traz os quadros e as assinaturas de todos os anteriores.

| situação | resposta |
|---|---|
| todos assinaram | `200`, o PDF |
| ainda há assinatura pendente | `409` |
| pendente, com `?parcial=1` | `200`, a **versão atual**, com os quadros de quem já assinou, para o sistema de origem mostrar o andamento |
| ninguém assinou ainda | `404` |
| documento cancelado | `410` |

### Webhook de callback

Se `callback_url` for fornecido no `POST /documentos`, o GPE Docs faz `POST` no callback
em vários eventos do ciclo de vida das assinaturas:

| evento | quando dispara | payload extra |
|---|---|---|
| `assinatura.individual` | Cada vez que **um** signatário assina | `tipo_assinatura`, `signatario_id`, `cpf`, `assinado_em`, `todas_assinadas` |
| `assinatura.recusada` | Signatário **recusou** assinar | `signatario_id`, `cpf`, `motivo`, `recusado_em` |
| `assinatura.todas_concluidas` | **Todas** as assinaturas concluídas (final) | `concluido_em`, `pdf_assinado_url` |

O sistema externo pode optar por receber só alguns eventos via campo `eventos_assinatura`
no admin de Sistemas Integrados (default: todos).

Todos os webhooks têm o **mesmo formato** com identificação do evento no body e header:

**Headers:**

```
Content-Type: application/json
X-GpeDocs-Sistema: gpe
X-GpeDocs-Evento: assinatura.todas_concluidas
X-GpeDocs-Signature: sha256=a3f8b2c4d5e6...    (HMAC-SHA256 do body)
```

**Body do webhook:**

```json
{
  "sistema_origem": "gpe",
  "numero_externo": "2026/000123",
  "documento_id": 42,
  "status": "assinado",
  "todas_assinadas": true,
  "concluido_em": "2026-05-02T18:30:00-03:00",
  "pdf_assinado_url": "https://docs.exemplo.gov.br/documentos/42/download",
  "visualizacao_url": "https://docs.exemplo.gov.br/documentos/42"
}
```

O sistema externo deve responder com `2xx` em até 10s (com 2 retries de 500ms).

**Validação da assinatura HMAC** (recomendado):

```php
// PHP
$body = file_get_contents('php://input');
$assinaturaEsperada = 'sha256=' . hash_hmac('sha256', $body, $WEBHOOK_SECRET);
$assinaturaRecebida = $_SERVER['HTTP_X_GPEDOCS_SIGNATURE'] ?? '';
if (! hash_equals($assinaturaEsperada, $assinaturaRecebida)) {
    http_response_code(401);
    exit('Assinatura invalida');
}
```

```javascript
// Node.js
const crypto = require('crypto');
const expected = 'sha256=' + crypto
    .createHmac('sha256', WEBHOOK_SECRET)
    .update(req.rawBody)
    .digest('hex');
if (! crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(req.headers['x-gpedocs-signature']))) {
    return res.status(401).send('Assinatura invalida');
}
```

O `WEBHOOK_SECRET` é exibido **uma única vez** no cadastro/regeneração do sistema —
guarde com o mesmo cuidado que o API token. Pode ser regenerado independentemente do
API token (`POST /sistemas-integrados/{id}/regenerar-webhook-secret`).

## Fluxo de uso

1. **Cadastro inicial** (uma vez): `php artisan ged:sistema-cadastrar gpe "..."` → guarda o token
2. **Cadastrar tipos documentais** que o sistema vai usar (ex: "Empenho", "Liquidação", "Pagamento") com `sistema_origem='gpe'` no admin
3. **Para cada documento gerado**:
   - GPE gera o PDF com assinatura visual (cabeçalho, dados, espaços de assinatura)
   - GPE chama `POST /api/integracoes/documentos` com PDF em base64 + lista de CPFs
   - GPE Docs cria documento + solicitação + assinaturas + envia notificações aos signatários
   - Signatários acessam GPE Docs (`/flow/aguardando-assinatura` ou `/assinaturas`) e assinam digitalmente
   - Após última assinatura, GPE Docs faz webhook no `callback_url`
   - GPE atualiza no seu banco que o doc foi assinado

## Segurança

- Token é armazenado como **SHA-256 hash** — nunca exibido após cadastro
- HTTPS obrigatório em produção (validado via APP_URL)
- Rate limiting recomendado via reverse proxy (nginx) — não implementado na app
- Cada sistema só pode consultar/atualizar seus próprios documentos (filtra por `sistema_origem`)

## Limitações conhecidas

- PDFs apenas (mime fixo `application/pdf`)
- Tipo documental precisa estar cadastrado **previamente** no admin (não cria automaticamente)
- UG e Pasta resolvidas por código/nome (case-insensitive) — precisam existir
- Signatários novos viram `User` com `tipo=externo` e senha aleatória (precisam definir senha pra logar)
