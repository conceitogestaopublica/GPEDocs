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
| `signatarios[].email` | não | E-mail do signatário (opcional) |
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

### Webhook de callback

Se `callback_url` for fornecido no `POST /documentos`, o GPE Docs faz `POST` no callback
quando **todas as assinaturas** forem concluídas.

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
