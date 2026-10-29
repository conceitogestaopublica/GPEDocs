# API do Portal do Cidadão (`api/portal/*`)

O **Portal do Cidadão** (Next.js, repositório `novo-portal-cidadao`) usa o GPE Docs como
**fonte única** de três coisas:

| O quê | Tabela | Quem edita |
|---|---|---|
| Carta de Serviços | `portal_categorias_servicos`, `portal_servicos` | a Prefeitura, em Configurações › Portal do Cidadão › Carta de Serviços |
| Cadastro do cidadão (e a senha dele) | `portal_cidadaos` | o próprio cidadão, pelo portal |
| Solicitações | `portal_solicitacoes` (+ processo no GPE Flow) | o cidadão abre; a Prefeitura atende em GPE Fluxos e Processos › Portal do Cidadão › Solicitações |

O cadastro é o mesmo da assinatura por link: **uma pessoa, um cadastro, uma senha**.

## Acesso

- **Município:** pelo host, como todo o GPE Docs (`ResolveTenant`). Em desenvolvimento,
  `http://localhost:8090`.
- **UG:** parâmetro `ug` = `ugs.portal_slug` (Configurações › Unidades Gestoras).
- **Autenticação:** `Authorization: Bearer {token}` de um **Sistema Integrado com código
  `portal-cidadao`** (`PORTAL_API_SISTEMA`). A chave de outro sistema integrado recebe 403, e a
  chave do portal não vale para `api/integracoes/*`.
- Envie `Accept: application/json`. Erros vêm como `{ "erro": "..." }` (regras do cadastro) ou no
  formato de validação do Laravel (422).
- **Limite de tentativas:** fica no portal (por documento + município); o portal é a única porta.

## Carta de Serviços

`GET /api/portal/catalogo?ug={slug}` → `{ ug, categorias[], servicos[] }`, só o que está
ativo/publicado.

- `categorias[].area`: `contribuinte` | `protocolo` | `servidor` | `transparencia` (vazio =
  protocolo). Define em que área do portal a categoria aparece.
- `servicos[].acao_fiscal`: tela de autoatendimento no tributário (`segunda_via`, `certidao`,
  `parcelamento`, `debitos`, ...). Com ação fiscal o serviço **não** vira solicitação.
- `servicos[].abre_processo`: o serviço tem tipo de processo + setor, então a solicitação abre o
  processo sozinha.

## Cidadão

| Rota | Corpo | Resposta |
|---|---|---|
| `POST cidadaos/identificar` | `documento` | `{ existe, email_confirmado }` |
| `POST cidadaos` | `documento, nome, email, telefone?, senha (mín. 8)` | 201 `{ cidadao, email_mascarado, minutos }` + e-mail com código; 409 se já existe cadastro confirmado |
| `POST cidadaos/confirmar-email` | `documento, codigo` | `{ cidadao }` ou 422 |
| `POST cidadaos/reenviar-codigo` | `documento` | `{ email_mascarado, minutos }` |
| `POST cidadaos/autenticar` | `documento` **ou** `email`, `senha` | `{ cidadao }`; 401 genérico; 403 se o e-mail não foi confirmado |
| `POST cidadaos/senha/codigo` | `documento` | `{ email_mascarado, minutos }` — **igual** exista ou não o documento |
| `POST cidadaos/senha/redefinir` | `documento, codigo, senha` | `{ cidadao }` ou 422 |
| `GET cidadaos/{id}` | — | `{ cidadao }` |
| `PUT cidadaos/{id}` | `nome?, telefone?, nomes_anteriores?, endereco?, email?` | `{ cidadao, email_pendente? }` — ver "Meus dados" |
| `POST cidadaos/{id}/confirmar-email-novo` | `codigo` | `{ cidadao }` ou 422 |
| `GET cidadaos/{id}/caixa?ug={slug}` | — | `{ itens[] }` — ver "Caixa" |
| `POST cidadaos/identificar-externo` | `provedor` (`govbr`\|`google`), `documento` **ou** `email` | `{ cidadao }` ou 404 |
| `POST cidadaos/certificado/desafio` | — | `{ desafio, hash, algoritmo }` |
| `POST cidadaos/certificado/autenticar` | `desafio, cert_pem, assinatura_b64` | `{ cidadao }`; 404 `{ erro, documento, nome }` sem cadastro; 422 `{ erro }` |

- `documento`: CPF ou CNPJ, com ou sem máscara; gravado só com dígitos, conferido com dígito
  verificador. Cadastros antigos com máscara são encontrados.
- Código: 6 dígitos, por e-mail, vale 15 minutos, uso único (guardado como sha256).
- Um cadastro que nunca confirmou o e-mail é **retomado** por um novo `POST cidadaos` do mesmo
  documento — quem desistiu no meio não fica trancado.
- **Usuário do GPE Docs** (servidor, funcionário) sem cadastro de cidadão: `identificar` responde
  `existe: true` e `autenticar` aceita a senha de usuário (CPF ou e-mail). No primeiro acesso nasce
  o cidadão ligado a ele (`portal_cidadaos.user_id`); daí em diante a senha conferida é a do
  usuário, e redefinir a senha pelo portal troca as duas. Uma pessoa, uma senha.
- `cidadao`: `{ id, nome, documento, email, telefone, email_confirmado, servidor, nomes_anteriores, endereco }` — nunca senha nem token. `servidor`: ligado a usuário vindo do RH (só ele abre a Área do Servidor e os serviços da área "servidor"). `nomes_anteriores`: lista (pode ser vazia). `endereco`: `{ cep, logradouro, numero, complemento, bairro, municipio, uf }` ou `null`.
- `POST cidadaos` também aceita `nomes_anteriores[]` e `endereco{}` (opcionais).
- **Desenvolvimento:** com `APP_ENV=local` e `PORTAL_API_ECO_CODIGO=true`, as respostas que
  enviam código trazem `codigo_dev` (o e-mail local vai só para o log). Nunca em produção.

### Meus dados (`PUT cidadaos/{id}`)

- Só os campos enviados mudam. CEP gravado só com dígitos; UF em maiúsculas.
- **CPF/CNPJ não muda:** `documento` diferente do cadastro → 422 (igual ao cadastro é ignorado).
- **Nome:** trocar o nome guarda o anterior em `nomes_anteriores` (nome civil alterado).
- **E-mail:** o novo fica **pendente**: o cadastro segue com o antigo e vai um código de 6 dígitos
  (15 min) para o **novo**; a resposta traz `email_pendente: { email_mascarado, minutos }`. Só
  `POST cidadaos/{id}/confirmar-email-novo` com o código troca o e-mail. E-mail já usado por
  outro cadastro → 422.

### Entrada sem senha

- **gov.br / Google** (`identificar-externo`): o portal já conferiu a identidade no provedor (OIDC)
  e manda o CPF (gov.br) ou o e-mail (Google). Pelo e-mail só acha cadastro com e-mail
  confirmado. Usuário do GPE Docs sem cadastro de cidadão ganha o cadastro ligado a ele, como na
  senha.
- **Certificado ICP-Brasil** (e-CPF ou e-CNPJ, pela Web PKI no navegador do cidadão):
  1. `certificado/desafio` devolve `desafio` (token assinado, vale 2 min, **uso único**) e `hash`
     (SHA-256 em base64) — o navegador assina o `hash` com `signHash` (SHA-256);
  2. `certificado/autenticar` com o `desafio` de volta, o certificado (`cert_pem`: PEM ou base64
     DER, como a Web PKI devolve) e a assinatura em base64. Confere a assinatura, a validade e a
     cadeia até a AC Raiz ICP-Brasil com LCR, e acha o cadastro pelo CPF/CNPJ do certificado.
     Sem cadastro: 404 com `documento` e `nome` do titular, para o portal abrir o cadastro já
     preenchido.

### Caixa (`GET cidadaos/{id}/caixa?ug={slug}`)

O que a Prefeitura mandou ao cidadão, mais recente primeiro:
`itens[]: { tipo, titulo, numero, data, situacao, link }`.

- `tipo: "oficio"` — ofícios eletrônicos da UG enviados para o e-mail **confirmado** do cadastro;
  `situacao` `Lido`/`Não lido`; `link` = página pública de rastreio do ofício.
- `tipo: "assinatura"` — documentos enviados para assinatura por link ao CPF do cadastro (no
  município); `situacao` `Aguardando a sua assinatura`/`Assinado`/`Recusado`/`Prazo encerrado`;
  `link` = `/assinar/{token}`.

## Solicitações

Sempre com `ug` e `cidadao_id` (o portal só informa o id do cidadão autenticado na sessão dele).

| Rota | O que faz |
|---|---|
| `GET solicitacoes` | lista do cidadão — como titular **ou** como procurador (até 200, mais recentes primeiro) |
| `POST solicitacoes` | `servico` (slug), `descricao`, `telefone_contato?`, `email_contato?`, `anexos?` (até 3 `{ nome, conteudo }` em base64; PDF/JPG/PNG, 10 MB) → 201; abre o processo se o serviço tiver tipo + setor (anexos vão junto quando quem pede é usuário); avisa por e-mail; 403 em serviço da área "servidor" para quem não é servidor |
| `GET solicitacoes/{codigo}` | detalhe: descrição, resposta, processo (`numero_protocolo`, `status`) e eventos |
| `POST solicitacoes/{codigo}/cancelar` | cancela (422 se já finalizada) |

**Por procuração:** no `POST solicitacoes`, `cidadao_id` é o **titular** (outorgante) e
`procurador_cidadao_id` é quem protocola (o cidadão logado; a procuração vai nos anexos). Os dois
veem, acompanham e podem cancelar. Lista e detalhe trazem `titular: { id, nome }` e
`procurador: { id, nome } | null`; o evento "criada" registra o procurador como autor.

A solicitação é registrada pelo mesmo serviço do portal embutido (`RegistrarSolicitacaoService`),
e o andamento do processo volta sozinho para ela (`SincronizarPortalCidadaoService`).

## Configurar um município

1. `php artisan tenant:migrate --tenant={id}` (campos `area` e `acao_fiscal`).
2. Dar um `portal_slug` à UG.
3. Cadastrar o Sistema Integrado `portal-cidadao` e copiar o token (exibido uma vez) para o
   portal (`GED_API_TOKEN`, com `GED_BASE_URL` = endereço do GPE Docs do município).
4. Na Carta de Serviços, indicar a área de cada categoria e a ação fiscal dos serviços de
   autoatendimento.
5. Migrando de um portal que já tinha a Carta: exportar no portal
   (`node prisma/scripts/exportar-catalogo.mjs --municipio=X --saida=carta.json`) e importar aqui
   (`docker exec -i app-docs php artisan portal:importar-carta --tenant={id} --ug={portal_slug} < carta.json`).
   Não sobrescreve o que já existe (use `--sobrescrever` conscientemente). Serviço de solicitação
   só abre processo depois de ganhar tipo de processo e setor na Carta.
