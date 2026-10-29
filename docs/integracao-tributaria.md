# Integração do GPE Docs com o sistema tributário

O GPE Docs (GED) usa o sistema tributário do município nos processos de aprovação de projetos,
licenciamento e fiscalização (TR 4.48, 4.49, 4.50, 4.51):

- consulta o imóvel pela inscrição;
- emite e acompanha a guia da taxa;
- pede alvará de construção, habite-se e certidão e anexa o PDF ao processo;
- abre ordem de fiscalização.

A integração é **configurável** (GPE Config › Integrações › Sistema tributário), geral do município
ou por UG, com três opções:

| Sistema | Quando usar | O que se configura |
|---|---|---|
| **GPE Tributário** | município usa o Tributário da GPE | endereço da API e o token de integração |
| **Outro sistema (REST configurável)** | tributário de terceiros | endereço, autenticação e, para cada recurso, método, caminho, corpo e de onde vem cada campo da resposta |
| **Sem integração** | não há API | nada; o servidor informa número, situação e PDF à mão no processo |

Recurso que o sistema não oferece aparece riscado na configuração. No processo, o servidor faz a
mesma coisa à mão.

---

## 1. Recursos e formato dos dados no GED

Todo sistema é traduzido para estes campos (`App\Integracoes\Tributario\Normalizado`).

**Imóvel** (`imovel`)

| Campo | Conteúdo |
|---|---|
| `inscricao` | inscrição imobiliária |
| `proprietario_nome`, `proprietario_documento` | proprietário e CPF/CNPJ |
| `logradouro`, `numero`, `complemento`, `bairro`, `cep` | endereço |
| `zona`, `quadra`, `lote` | localização no cadastro |
| `area_terreno`, `area_construida` | áreas em m² |
| `latitude`, `longitude` | coordenadas |
| `situacao` | situação do cadastro |

**Guia** (`guia_emitir`, `guia_consultar`)

| Campo | Conteúdo |
|---|---|
| `id_externo`, `numero` | identificador no tributário e número da guia |
| `valor` | valor |
| `vencimento` | data AAAA-MM-DD |
| `situacao` | `emitida`, `paga`, `vencida` ou `cancelada`. O GED também reconhece PAGA, QUITADA, LIQUIDADA, BAIXADA… |
| `linha_digitavel`, `codigo_barras`, `pix` | dados para pagamento |
| `pdf_url` | endereço do PDF |

**Documento** (`documento_solicitar`, `documento_consultar`)

- Tipos: `alvara_construcao`, `habitese` e `certidao`.
- Campos: `id_externo`, `numero`, `situacao`, `emitido_em`, `validade`, `pdf_url`.

**Fiscalização** (`fiscalizacao_abrir`)

- Campos: `id_externo`, `numero`, `situacao`.

**Marcadores que o GED preenche nos pedidos**

| Marcador | Conteúdo |
|---|---|
| `{inscricao}` | inscrição do imóvel vinculado |
| `{taxa}`, `{valor}`, `{descricao}`, `{vencimento}` | dados da guia pedida |
| `{documento}`, `{nome}` | requerente do processo |
| `{processo}`, `{assunto}` | número e assunto do processo no GED |
| `{tipo}` | tipo do documento pedido |
| `{id}` | id externo, nas consultas |
| `{area}`, `{responsavel_tecnico}`, `{registro_profissional}`, `{observacao}` | dados do pedido de documento |
| `{motivo}`, `{endereco}`, `{latitude}`, `{longitude}` | dados da fiscalização |

O marcador sozinho num campo do corpo mantém o tipo (número, nulo). Dentro de um texto, vira texto.

---

## 2. Contrato do GPE Tributário: rotas a criar no Tributário

O adaptador "GPE Tributário" do GED já vem configurado com estas rotas. **O Tributário precisa
implementá-las.** Hoje as rotas de obra, guia e imóvel exigem login de usuário, então não servem
para outro sistema.

### Regras gerais

**Prefixo:** `/api/v1/integracao/ged`.

**Autenticação**

- Header `X-Integracao-Token`, com o guard `IntegracaoTokenGuard` que já existe (consumidor em
  `config/contabil/integracao/consumidores`).
- Controller `@Public()` + `@UseGuards(IntegracaoTokenGuard)`.
- Criar um consumidor "GPE Docs" e informar o token no GED.

**Município**

- Pelo subdomínio do endereço configurado no GED, por exemplo
  `https://taquaracudeminas.tributario.gpe.cloud/api/v1`.
- Se não houver subdomínio, o GED pode mandar headers fixos em `config.headers`, por exemplo
  `{"headers": {"x-tenant-subdomain": "taquaracudeminas", "x-proxy-secret": "…"}}`.

**Formato das respostas**

- Respostas de sucesso: `{"data": {…}}`, com os nomes de campo da seção 1.
- Erros: status HTTP com `{"message": "…"}`.
- Registro não encontrado: 404. O GED mostra "não encontrado".
- `pdf_url` pode ser relativo ao prefixo `/api/v1` (`/integracao/ged/guias/{id}/pdf`) ou absoluto.

**Processo do GED:** gravar o número recebido (`processo`) nos registros criados (guia, alvará,
habite-se, ordem de serviço) para mandar de volta nos eventos.

### Rotas

| Método e caminho | Faz | Resposta `data` |
|---|---|---|
| `GET /status` | teste de conexão | `{ok: true, tenant}` |
| `GET /imoveis/{inscricao}` | imóvel pela inscrição exata (`ImovelRepository.findByInscricao`) | imóvel (seção 1); `area_construida` = soma de `Edificacao.areaTotal`; `zona` = nome da zona; proprietário = titular responsável |
| `POST /guias` | lança a taxa e emite a guia (`taxa/lancamentos`) | guia (seção 1) |
| `GET /guias/{id}` | situação da guia (`arrecadacao/guias/:id`) | guia |
| `GET /guias/{id}/pdf` | PDF da guia (`arrecadacao/guias/:id/pdf?modelo=dam`) | `application/pdf` |
| `POST /documentos/{tipo}` | `alvara_construcao`: prévia + efetivação (`obra/alvaras/previa` e `efetivar`); `habitese`: idem com o alvará do imóvel; `certidao`: `POST certidoes` | documento (seção 1) |
| `GET /documentos/{tipo}/{id}` | situação do documento | documento |
| `GET /documentos/{tipo}/{id}/pdf` | PDF oficial (certidão `ALVARA_CONSTRUCAO` / `HABITE_SE` em `certidoes/:id/pdf`) | `application/pdf` |
| `POST /fiscalizacoes` | ordem de fiscalização de obras/posturas: ação fiscal com alvo IMÓVEL ou ordem de serviço | `{id_externo, numero, situacao}` |

### Corpos

**`POST /guias`**

```json
{ "taxa": "código do tipo de taxa", "valor": 150.00, "documento": "CPF/CNPJ", "nome": "requerente",
  "inscricao": "inscrição do imóvel ou null", "processo": "LIC-2026/000123", "descricao": "…",
  "vencimento": "AAAA-MM-DD ou null" }
```

- `valor` null: o Tributário calcula pela fórmula da taxa.
- O contribuinte é achado pelo documento. Se não existir, criar como contribuinte eventual.
- O imóvel é achado pela inscrição.

**`POST /documentos/{tipo}`**

```json
{ "inscricao": "…", "documento": "CPF/CNPJ", "nome": "…", "processo": "LIC-2026/000123",
  "assunto": "…", "area": 180.5, "responsavel_tecnico": "…", "registro_profissional": "CREA/CAU",
  "observacao": "…" }
```

- Alvará: `areaAutorizada` = `area`; tipo de construção padrão do parâmetro. A efetivação emite a guia; ela entra no evento.
- Habite-se: usa o alvará efetivado do imóvel; `tipo` TOTAL quando `area` cobre o saldo, PARCIAL quando não.
- Campos novos sugeridos em `AlvaraConstrucao`/`Habitese`: `processoExterno`, `respTecnicoNome`, `respTecnicoRegistro`. Também são necessários ao Sisobra.

**`POST /fiscalizacoes`**

```json
{ "motivo": "…", "inscricao": "…", "endereco": "…", "processo": "OUV-2026/000045",
  "descricao": "…", "latitude": -19.6, "longitude": -43.6 }
```

### Eventos: o Tributário avisa o GED

O Tributário não tem hoje aviso para outros sistemas. Quando uma guia, alvará, habite-se ou ordem
de fiscalização ligada a um processo do GED mudar, o Tributário chama:

```
POST {endereço do GED}/api/integracoes/tributario/eventos
Authorization: Bearer {token do "sistema integrado" criado no GED para o Tributário}
Content-Type: application/json

{
  "evento": "guia.paga | guia.cancelada | guia.vencida | documento.emitido | documento.cancelado | fiscalizacao.atualizada",
  "tipo": "guia | alvara_construcao | habitese | certidao | fiscalizacao | licenca",
  "id_externo": "mesmo id_externo devolvido ao GED",
  "processo": "número do processo no GED (o recebido no pedido)",
  "numero": "…", "situacao": "…", "valor": 0, "vencimento": "AAAA-MM-DD",
  "pdf_url": "URL pública ou assinada do PDF" ou "pdf_base64": "…",
  "dados": {}
}
```

**Quando disparar**

- `guia.paga`: na baixa (CNAB, conciliação, PIX).
- `documento.emitido`: na efetivação do alvará ou habite-se, ou na emissão da certidão.
- `documento.cancelado`: no cancelamento ou na baixa.
- `fiscalizacao.atualizada`: na mudança de situação da OS ou da ação fiscal.

O `IntegracaoConsumidor.urlDestino` (hoje sem uso) pode guardar o endereço do GED e o token.

**Respostas do GED**

| Status | Corpo | Situação |
|---|---|---|
| 200 | `{resultado: "aplicado", processo, situacao, anexo}` | evento aplicado |
| 404 | `{resultado: "ignorado"}` | sem processo ligado; mande `processo` |
| 401 | — | token errado |
| 422 | — | dados inválidos |

**Reenvio:** é seguro repetir o mesmo evento.

Sem eventos, o GED atualiza a situação quando o servidor clica em "atualizar" no processo.

---

## 3. Tributário de terceiros (REST configurável)

Em "Outro sistema", o JSON de configuração descreve cada recurso. O botão **usar modelo** preenche
um exemplo:

```json
{
  "teste": {"metodo": "GET", "caminho": "/status"},
  "headers": {"X-Municipio": "3168408"},
  "recursos": {
    "imovel": {"metodo": "GET", "caminho": "/cadastro/imovel/{inscricao}", "raiz": "resultado",
      "campos": {"inscricao": "insc", "proprietario_nome": "dono.nome", "logradouro": "end.rua",
                 "area_terreno": "areas.lote", "latitude": "geo.lat"}},
    "guia_emitir": {"metodo": "POST", "caminho": "/arrecadacao/dam",
      "corpo": {"receita": "{taxa}", "valor": "{valor}", "cpf": "{documento}", "historico": "Processo {processo}"},
      "raiz": "dam", "campos": {"id_externo": "codigo", "numero": "nosso_numero", "valor": "total",
                                 "vencimento": "venc", "situacao": "estado", "pdf_url": "arquivo"}},
    "documento_solicitar.habitese": {"metodo": "POST", "caminho": "/obras/habitese", "corpo": {"…": "…"},
      "raiz": "data", "campos": {"id_externo": "id", "numero": "numero", "situacao": "status"}}
  }
}
```

Cada chave de recurso tem:

- `metodo`: GET, POST, PUT ou PATCH.
- `caminho`: com `{marcadores}`, codificados na URL.
- `corpo`: JSON com `{marcadores}`.
- `raiz`: onde está o objeto na resposta (notação de ponto; vazio = a própria resposta).
- `campos`: campo do GED → caminho na resposta.

Para documentos, é possível configurar um tipo específico com `documento_solicitar.habitese` e um
genérico com `documento_solicitar`.

**Autenticação:** Bearer, header próprio (com o nome do header), Basic (`usuario:senha`) ou
nenhuma. A credencial é guardada cifrada.

O fornecedor terceiro pode usar o mesmo endpoint de eventos da seção 2.
