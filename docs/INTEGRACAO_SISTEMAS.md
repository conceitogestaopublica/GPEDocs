# Integração do GPE Docs com o gpe2 e o Tributário

Manual de configuração para a equipe de implantação e suporte. Explica, lado a lado, o que se configura no GPE Docs, no gpe2 e no Tributário para que os dois sistemas enviem documentos e PDFs ao GPE Docs.

Este documento não traz tokens nem senhas. Eles são gerados no GPE Docs na hora da configuração e devem ser passados de um sistema ao outro por canal seguro.

## 1. Como a integração funciona

O GPE Docs é o acervo e o assinador. O gpe2 e o Tributário não guardam a versão oficial dos documentos: eles enviam o PDF ao GPE Docs pela API de integração.

| Sistema | O que envia | Para quê | Volta alguma coisa? |
| --- | --- | --- | --- |
| gpe2 | Empenhos, liquidações, pagamentos e relatórios (ex.: balancetes), em PDF | Coletar a assinatura digital dos responsáveis | Sim: o GPE Docs avisa o gpe2 (webhook) quando a assinatura termina ou é recusada |
| Tributário | Anexos e PDFs dos módulos (certidões, fotos, documentos do contribuinte) | Arquivar no acervo, sem assinatura | Não |

A segurança da conversa tem duas peças, ambas geradas no GPE Docs para cada sistema:

- **Token de API**: o sistema o envia em toda chamada, no cabeçalho `Authorization: Bearer <token>`. Sem ele, ou com ele errado, o GPE Docs responde 401.
- **Segredo do webhook**: o GPE Docs assina com ele cada aviso que manda de volta (cabeçalho `X-GpeDocs-Signature: sha256=...`). O sistema que recebe confere a assinatura para ter certeza de que o aviso veio do GPE Docs. Só o gpe2 usa.

Cada sistema envia também o **código da Unidade Gestora** (UG). É ele que diz ao GPE Docs em qual acervo o documento entra (Prefeitura, Câmara...).

## 2. Antes de começar

Tenha em mãos:

1. O endereço do GPE Docs do município (ex.: `https://santoantoniodoamparo.gpedocs.com.br`).
2. Acesso de administrador ao GPE Docs, ao gpe2 (perfil com acesso a Configurações) e ao servidor do Tributário (para editar o `.env`).
3. O código da UG de cada órgão no GPE Docs: **GPE Config → Estrutura → Unidades Gestoras**. Em Santo Antônio do Amparo:

| Código | Unidade Gestora |
| --- | --- |
| UG-001 | Câmara Municipal |
| UG-002 | Município (Prefeitura) |
| UG-003 | Casa da Cultura |

## 3. Lado do GPE Docs

### 3.1 Cadastrar o sistema e gerar o token

1. Entre no GPE Docs como administrador.
2. Abra **GPE Config → Integrações → Sistemas Integrados**.
3. Se o sistema ainda não estiver na lista, clique em **Novo Sistema** e preencha:
   - **Código**: `gpe2` ou `tributario` (sem acento, minúsculo). Não pode ser alterado depois.
   - **Nome**: ex.: "GPE2 — Gestão Pública" ou "Tributário — Gestão da Receita".
   - **Descrição**: o que o sistema envia e um contato técnico.
4. Ao salvar, o GPE Docs mostra o **token de API** e o **segredo do webhook**. Copie os dois na hora: eles **só aparecem uma vez**. Se perder, use **Regenerar** (o valor antigo deixa de valer imediatamente; atualize o outro sistema em seguida).
5. Confira que o sistema está **Ativo**. Sistema inativo recebe 401 em todas as chamadas.

Em Santo Antônio do Amparo, `gpe2` e `tributario` já estão cadastrados e ativos. Os tokens atuais ficam no documento de acessos do ambiente (fora do repositório). Antes de levar para produção, regenere os dois.

### 3.2 Tipos documentais

Todo documento chega com o nome de um **tipo documental**, e ele precisa existir e estar ativo no GPE Docs. Caso contrário o GPE Docs recusa o envio com 422 ("Tipo documental não cadastrado").

1. Abra **GPE Docs → Administração → Tipos Documentais**.
2. Cadastre os tipos que cada sistema vai usar, com o nome **exatamente igual** ao configurado do outro lado:
   - gpe2: um tipo por lançamento configurado (ex.: "Empenho", "Liquidação", "Pagamento") e um por relatório (ex.: "Balancete da Despesa").
   - Tributário: o tipo informado em `GED_TIPO_DOCUMENTAL` (sugestão: "Anexo Tributário").

### 3.3 Pastas (opcional)

O gpe2 pode indicar a pasta de destino. O campo se chama "código da pasta" no gpe2, mas o GPE Docs procura a pasta pelo **nome**, na UG do envio, sem diferenciar maiúsculas de minúsculas. Se usar, crie as pastas em **GPE Docs → Documentos → Repositório** e informe no gpe2 o nome exato de cada uma. Se a pasta não existir, ou o campo ficar vazio, o documento fica na raiz do acervo da UG.

### 3.4 Acompanhar os avisos enviados

Na mesma tela de Sistemas Integrados, a aba **Webhooks** lista cada aviso que o GPE Docs mandou: endereço, assinatura, conteúdo, resposta do outro sistema e erros. É o primeiro lugar a olhar quando o gpe2 diz que não recebeu a conclusão de uma assinatura.

## 4. Lado do gpe2

Tudo é feito pela tela, por gestora. Entre no gpe2 já na gestora que será configurada (ex.: Município).

### 4.1 Configuração geral

Abra **Configuração → Integrações → GPE Docs (Assinatura Digital)**, aba **Configuração Geral**:

| Campo | O que colocar |
| --- | --- |
| URL base do GPE Docs | Endereço do GPE Docs, sem barra no fim (ver seção 6) |
| Código UG | Código da UG no GPE Docs (ex.: `UG-002`) |
| Bearer Token | Token de API do sistema `gpe2` gerado no GPE Docs |
| Webhook Secret (HMAC) | Segredo do webhook do sistema `gpe2` |
| Integração ativa | Ligado |

Salve. Depois de salvos, token e segredo aparecem mascarados. Para mantê-los, deixe os campos em branco ao salvar de novo.

Repita para cada gestora que vai enviar documentos (Câmara, autarquias...), cada uma com o próprio código de UG.

### 4.2 Tipos documentais (lançamentos)

Aba **Tipos Documentais → Novo**, um registro por tipo de lançamento:

| Campo | O que colocar |
| --- | --- |
| Tipo de lançamento | Empenho, Reforço, Anulação, Liquidação, Pagamento... |
| Nome no GPE Docs | Nome do tipo documental cadastrado no GPE Docs (item 3.2) |
| Pasta (opcional) | Nome da pasta no GPE Docs (item 3.3) |
| Ordem de assinatura | **Livre** (qualquer ordem) ou **Sequencial** (na ordem dos responsáveis) |
| Responsáveis | Quem assina: Ordenador de Despesas, Contador, Liquidante, Pagador, Controlador Interno, Tesoureiro, Responsável. Use as setas para definir a ordem |

### 4.3 Relatórios

Aba **Relatórios → Novo**, para relatórios que precisam de assinatura (ex.: balancetes):

| Campo | O que colocar |
| --- | --- |
| Relatório | Relatório do gpe2 |
| Slug | Identificador curto, só letras, números e `_` (ex.: `bal_despesa`) |
| Nome no GPE Docs | Tipo documental no GPE Docs |
| Pasta (template) | Opcional, aceita variáveis. Ex.: `Balancete/{ano}` |
| Nome do documento (template) | Ex.: `{slug}_{mes:02}_{ano}` |
| Número externo (template) | Identificador único do documento. Ex.: `{slug}_{mes:02}_{ano}` |
| Ordem de assinatura | Livre ou Sequencial |

### 4.4 Como o usuário envia

Configurado, o envio é feito na própria rotina. Em **Contabilidade → Empenhos**, por exemplo, a coluna **Assinatura** tem o botão **Opções GPE Docs**, com a ação **Enviar**; enquanto ninguém assinou, ele também permite **Enviar nova versão**. A mesma coluna mostra a situação e, depois de enviado, leva ao documento no GPE Docs. Quando todos assinam, o GPE Docs avisa o gpe2 e a situação muda sozinha.

## 5. Lado do Tributário

A configuração fica no arquivo `.env` do backend (`gpd-web-tribut-rio`). Não há tela.

```
ARQUIVO_STORAGE_DESTINO=ged
GED_BASE_URL=https://santoantoniodoamparo.gpedocs.com.br
GED_API_TOKEN=<token de API do sistema tributario no GPE Docs>
GED_TIPO_DOCUMENTAL=Anexo Tributário
GED_UG_CODIGO=UG-002
```

| Variável | O que faz |
| --- | --- |
| `ARQUIVO_STORAGE_DESTINO` | `ged` manda os arquivos ao GPE Docs. Qualquer outro valor (ou vazio) guarda em disco local, sem integração |
| `GED_BASE_URL` | Endereço do GPE Docs, sem barra no fim |
| `GED_API_TOKEN` | Token de API do sistema `tributario` |
| `GED_TIPO_DOCUMENTAL` | Tipo documental de todos os arquivos enviados; precisa existir no GPE Docs (item 3.2) |
| `GED_UG_CODIGO` | Código da UG usado quando não há município no contexto (ver a pendência 9.1) |

Depois de editar, **reinicie o backend** e confira o log da subida: deve aparecer `Arquivos no GED (<endereço>)`. Se `ARQUIVO_STORAGE_DESTINO=ged` estiver ligado sem URL ou sem token, o backend **não para**: grava os arquivos em disco e registra um aviso no log. Nesse caso, nada chega ao GPE Docs até a configuração ser completada.

O Tributário não usa webhook: ele só arquiva, sem assinatura. Para abrir um arquivo, ele mesmo busca o conteúdo no GPE Docs.

## 6. Endereços: produção e ambiente local

Em produção, cada sistema usa o endereço público do outro. No ambiente local em Docker é diferente: dentro de um container, `localhost` é o próprio container, não a máquina.

| Quem chama | Produção | Local (Docker) |
| --- | --- | --- |
| gpe2 → GPE Docs (URL base) | `https://<municipio>.gpedocs.com.br` | `http://host.docker.internal:8090` |
| Tributário → GPE Docs (`GED_BASE_URL`) | `https://<municipio>.gpedocs.com.br` | `http://localhost:8090` se o backend roda direto na máquina; `http://host.docker.internal:8090` se roda em container |
| GPE Docs → gpe2 (aviso de assinatura) | Endereço público do gpe2, informado pelo próprio gpe2 a cada envio | Não funciona hoje (ver pendência 9.2) |

Em produção com HTTPS, mantenha a verificação de certificado ligada no gpe2 (`GPEDOCS_VERIFY_SSL=true`, que é o padrão).

## 7. Testar a conexão

Antes de pedir a um usuário que envie um documento, teste o token direto na API. A consulta abaixo procura um documento que não existe, de propósito. A resposta diz se o token está certo:

```
curl -H "Authorization: Bearer <token>" -H "Accept: application/json" \
  https://<endereco-do-gpe-docs>/api/integracoes/documentos/teste-conexao
```

| Resposta | Significado |
| --- | --- |
| 404 `Documento não encontrado para este sistema.` | Conexão e token **corretos** |
| 401 `Token inválido, expirado ou sistema inativo.` | Token errado, regenerado ou sistema inativo no GPE Docs |
| Erro de conexão ou tempo esgotado | Endereço errado, firewall ou GPE Docs fora do ar |

Depois, o teste completo:

1. **gpe2**: envie um empenho de teste. Confira no GPE Docs (**Assinaturas** e **Repositório**) que ele chegou, assine e veja a situação mudar no gpe2. Se não mudar, olhe a aba **Webhooks** (item 3.4).
2. **Tributário**: anexe um arquivo em qualquer módulo. Confira no **Repositório** do GPE Docs, na UG configurada, um documento do tipo "Anexo Tributário".

## 8. Problemas comuns

| Sintoma | Causa provável | O que fazer |
| --- | --- | --- |
| 401 em todas as chamadas | Token errado, regenerado ou sistema inativo | Confira o sistema em Sistemas Integrados; regenere e atualize o outro lado |
| 422 `UG não encontrada: ...` | Código da UG diferente do cadastrado no GPE Docs | Use o código exato da tela de Unidades Gestoras (ex.: `UG-002`). No Tributário, ver pendência 9.1 |
| 422 `Tipo documental não cadastrado: ...` | Nome do tipo diferente, ou tipo inativo | Cadastre no GPE Docs com o nome idêntico (acentos e maiúsculas contam) |
| Documento assinado, mas o gpe2 continua "aguardando" | O aviso não chegou ao gpe2 | Aba Webhooks no GPE Docs: veja o erro. Corrija o endereço ou o segredo e reenvie o aviso |
| Aviso chega, mas o gpe2 rejeita | Segredo do webhook diferente nos dois lados | Copie de novo o segredo do GPE Docs para o campo Webhook Secret do gpe2 |
| Erro de certificado (SSL) | Certificado inválido ou vencido no GPE Docs | Corrija o certificado. Não desligue a verificação em produção |
| Tributário não envia nada e não dá erro | `ARQUIVO_STORAGE_DESTINO` diferente de `ged`, ou falta `GED_BASE_URL`/`GED_API_TOKEN` (os arquivos vão para o disco) | Veja o log da subida do backend, ajuste o `.env` e reinicie |

## 9. Pendências conhecidas

### 9.1 Tributário envia o subdomínio no lugar do código da UG

O Tributário manda como UG o **subdomínio do município** (ex.: `santoantoniodoamparo`), e só usa `GED_UG_CODIGO` quando não há município no contexto. O GPE Docs procura a UG pelo código exato (ex.: `UG-002`) e recusa o arquivo com 422 `UG não encontrada: santoantoniodoamparo`. Isso foi testado na API do GPE Docs.

Até corrigir, o arquivamento do Tributário no GPE Docs **não funciona** no caminho normal (com município). Há dois caminhos de correção, a decidir:

- **No Tributário** (recomendado): usar `GED_UG_CODIGO` quando definido e só cair no subdomínio quando ele estiver vazio.
- **No GPE Docs**: aceitar também o subdomínio do município como apelido da UG principal.

### 9.2 Aviso de assinatura não chega ao gpe2 no ambiente local

O gpe2 informa como endereço de retorno o próprio endereço do navegador (`http://localhost:8080/...`). De dentro do container do GPE Docs, esse endereço não leva ao gpe2. Localmente, o documento é assinado no GPE Docs, mas o gpe2 não recebe o aviso sozinho.

Em produção, com domínios reais, isso não acontece. Para demonstrar localmente o ciclo completo, o gpe2 precisaria ser acessado por um endereço que também funcione dentro do Docker.

## 10. Referência rápida da API

Todas as rotas exigem `Authorization: Bearer <token>` e respondem em JSON.

| Método e rota | Uso |
| --- | --- |
| `POST /api/integracoes/documentos` | Enviar documento para assinatura (gpe2) |
| `GET /api/integracoes/documentos/{numero}` | Consultar situação e assinaturas |
| `GET /api/integracoes/documentos/{numero}/pdf-assinado` | Baixar o PDF assinado |
| `POST /api/integracoes/documentos/{numero}/versao` | Substituir o PDF antes de alguém assinar |
| `POST /api/integracoes/documentos/{numero}/cancelar` | Cancelar o documento |
| `POST /api/integracoes/documentos/{numero}/reenviar-webhook` | Reenviar o aviso de conclusão |
| `POST /api/integracoes/arquivos` | Arquivar sem assinatura (Tributário) |
| `GET /api/integracoes/arquivos/{id}/conteudo` | Baixar um arquivo arquivado |

O `{numero}` é o número externo que o próprio sistema definiu no envio.
