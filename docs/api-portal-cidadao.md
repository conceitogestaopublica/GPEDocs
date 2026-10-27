# API do Portal do Cidadão (`api/portal/*`)

O **Portal do Cidadão** (Next.js, repositório `novo-portal-cidadao`) usa o GPE Docs como
**fonte única** de três coisas:

| O quê | Tabela | Quem edita |
|---|---|---|
| Carta de Serviços | `portal_categorias_servicos`, `portal_servicos` | a Prefeitura, em Configurações › Portal do Cidadão › Carta de Serviços |
| Cadastro do cidadão (e a senha dele) | `portal_cidadaos` | o próprio cidadão, pelo portal |
| Solicitações | `portal_solicitacoes` (+ processo no GPE Flow) | o cidadão abre; a Prefeitura atende em Solicitações do Portal |

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
| `POST cidadaos/autenticar` | `documento, senha` | `{ cidadao }`; 401 genérico; 403 se o e-mail não foi confirmado |
| `POST cidadaos/senha/codigo` | `documento` | `{ email_mascarado, minutos }` — **igual** exista ou não o documento |
| `POST cidadaos/senha/redefinir` | `documento, codigo, senha` | `{ cidadao }` ou 422 |
| `GET cidadaos/{id}` | — | `{ cidadao }` |

- `documento`: CPF ou CNPJ, com ou sem máscara; gravado só com dígitos, conferido com dígito
  verificador. Cadastros antigos com máscara são encontrados.
- Código: 6 dígitos, por e-mail, vale 15 minutos, uso único (guardado como sha256).
- Um cadastro que nunca confirmou o e-mail é **retomado** por um novo `POST cidadaos` do mesmo
  documento — quem desistiu no meio não fica trancado.
- `cidadao`: `{ id, nome, documento, email, telefone, email_confirmado }` — nunca senha nem token.
- **Desenvolvimento:** com `APP_ENV=local` e `PORTAL_API_ECO_CODIGO=true`, as respostas que
  enviam código trazem `codigo_dev` (o e-mail local vai só para o log). Nunca em produção.

## Solicitações

Sempre com `ug` e `cidadao_id` (o portal só informa o id do cidadão autenticado na sessão dele).

| Rota | O que faz |
|---|---|
| `GET solicitacoes` | lista do cidadão (até 200, mais recentes primeiro) |
| `POST solicitacoes` | `servico` (slug), `descricao`, `telefone_contato?`, `email_contato?` → 201; abre o processo se o serviço tiver tipo + setor; avisa por e-mail |
| `GET solicitacoes/{codigo}` | detalhe: descrição, resposta, processo (`numero_protocolo`, `status`) e eventos |
| `POST solicitacoes/{codigo}/cancelar` | cancela (422 se já finalizada) |

A solicitação é registrada pelo mesmo serviço do portal embutido (`RegistrarSolicitacaoService`),
e o andamento do processo volta sozinho para ela (`SincronizarPortalCidadaoService`).

## Configurar um município

1. `php artisan tenant:migrate --tenant={id}` (campos `area` e `acao_fiscal`).
2. Dar um `portal_slug` à UG.
3. Cadastrar o Sistema Integrado `portal-cidadao` e copiar o token (exibido uma vez) para o
   portal (`GED_API_TOKEN`, com `GED_BASE_URL` = endereço do GPE Docs do município).
4. Na Carta de Serviços, indicar a área de cada categoria e a ação fiscal dos serviços de
   autoatendimento.
