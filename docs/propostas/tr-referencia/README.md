# Termo de Referência de referência — GPEDocs

Minuta de Termo de Referência montada a partir **do que o sistema faz hoje**, para
oferecer a município que vá licitar gestão documental e para responder item a
item a edital de terceiro. Mesmo modelo do TR do tributário
(`gpd-web-tribut-rio/docs/propostas/tr-referencia/`).

## O que tem aqui

| Arquivo                                 | O que é                                                                         |
| --------------------------------------- | ------------------------------------------------------------------------------- |
| `docx/TR-completo.docx`                 | **127 requisitos** — 19 RT, 10 RS, 98 RF                                        |
| `docx/TR-enxuto.docx`                   | **98 requisitos** — sem carta de serviços/portal (6.15, 6.16) e sem integração (6.18) |
| `requisitos-gpedocs.csv`                | os 127 em planilha, com a coluna que diz se está na enxuta. É o Anexo I         |
| `00-abertura.md` … `06-fechamento.md`   | **a fonte**. Os `.docx` e o `.csv` são gerados daqui                            |
| `medicao/A…E-*.md`                      | a medição contra o código, item a item, com evidência `arquivo:símbolo`         |

Gerar de novo, após editar os `.md`:

```
npm install docx          # num diretório de trabalho, não no repo
node gerar-tr.js <dir dos .md> <dir de saída>
```

A enxuta é **subconjunto da completa** (constantes `ENXUTA_FAIXAS` e
`ENXUTA_SECOES_FORA` do gerador), nunca texto paralelo.

## De onde vieram os requisitos

Não havia edital de origem medido nem manual do usuário. A fonte foi o
**código**, medido em 06/10/2026 na branch `feat/integracao-arquivos-sem-assinatura`
(já com o `main` de 29/09 incorporado), em cinco áreas:

| Área                                   | Medidos | ✅  | 🟡  | ❌  |
| -------------------------------------- | ------- | --- | --- | --- |
| A — Núcleo GED                         | 49      | 21  | 13  | 15  |
| B — Assinatura                         | 46      | 14  | 15  | 17  |
| C — Processo e comunicações            | 66      | 30  | 14  | 22  |
| D — Portal e integração                | 63      | 35  | 7   | 21  |
| E — Técnico, segurança e configuração  | 61      | 36  | 9   | 16  |
| **Total**                              | **285** | **136** | **58** | **91** |

**Critério de entrada no TR:** só o que **passa numa prova de conceito hoje**,
seguindo o caminho inteiro (tela → rota → controller → banco). Item 🟡 entrou
apenas na parte que funciona, e a redação foi cortada nessa medida — por
exemplo, "assinatura qualificada com certificado A1" (o A3 nunca foi testado com
token real), "PDF assinado" (e não "PDF com todas as assinaturas"), "envelope
criptográfico verificável em leitor de PDF" (e não "PAdES AD-RB").

Quando duas medições divergiram, valeu a verificação no código: a aba de
auditoria da ficha do documento foi dada como funcionando pela medição E, mas a
tela espera `audit_logs`, `versoes` e `metadados` no nível de cima e o
controller manda tudo dentro de `documento` — as abas ficam vazias.

## 🔴 Corrigir antes de oferecer este TR

Defeitos que a medição encontrou e que **um edital ou uma prova de conceito
expõem**, mesmo sem estar escritos no TR. Os quatro primeiros são de segurança.

| # | Defeito | Onde |
| - | ------- | ---- |
| 1 | **Escalada de privilégio:** qualquer usuário autenticado cria ou edita usuário com `super_admin = true` | `Admin/UsuarioController::validarUsuario` |
| 2 | **Perfis e permissões não são aplicados:** nenhuma rota confere permissão; qualquer usuário entra em usuários, perfis e configurações | `routes/web.php` (sem middleware de permissão), `menus.js` sem filtro |
| 3 | **Vazamento entre UGs:** a busca avançada e o painel do GED consultam as tabelas direto e ignoram a UG ativa; o contador de etapas atrasadas do painel de processos também | `BuscaController::index`, `DashboardController`, `ProcessoDashboardController` (Tramitacao) |
| 4 | **Grau de sigilo não restringe nada:** "restrito" e "confidencial" são gravados, mas qualquer usuário da UG vê, baixa e exclui; a verificação pública por QR mostra os metadados | Controllers do GED sem `Gate`/`authorize`; `VerificacaoController::verificar` |
| 5 | Dumps SQL de bases de municípios versionados e senha de banco em documento | `database/backups/*.sql`, `docs/DEPLOY_PRODUCAO.md` |
| 6 | Validação de cadeia ICP-Brasil não ancorada na AC Raiz: intermediária vinda do próprio arquivo vira âncora; sem LCR/OCSP | `CertificadoService::tentarValidar` |
| 7 | Recusa de assinatura não muda a solicitação: uma solicitação com recusa vira "concluída" e dispara `todas_concluidas` | `AssinaturaController::recusar` |
| 8 | Certificado expirado ou inativado não é bloqueado no ato de assinar | `AssinaturaController::assinarIcp`, `prepararIcpA3` |
| 9 | Manifesto de assinaturas sem conferência de permissão (qualquer ID) | `AssinaturaController::manifesto` |
| 10 | Envio para assinatura pela API não é idempotente: reenvio do mesmo número duplica o documento | `IntegracaoDocumentoController::store` |
| 11 | Editar tipo de processo em uso apaga e recria as etapas e falha por chave estrangeira | `TipoProcessoController::update` |
| 12 | Arquivos de todos os entes de uma instalação no mesmo diretório; `TenantStorage` existe e não é usado | `config/filesystems.php`, `app/Tenant/TenantStorage.php` |

## Correções rápidas que acrescentam requisitos ao TR

Cada linha abaixo é um requisito **já redigido** na medição (`medicao/`) que
entra no TR assim que o defeito indicado for corrigido.

| Requisito que entraria | Falta |
| ---------------------- | ----- |
| Versionamento pela interface, com consulta das versões | Corrigir as props de `Documentos/Show.jsx` (`documento.versoes`) e criar envio de nova versão pela tela |
| Trilha de auditoria por documento, consultável na ficha | Mesma correção de props (`documento.audit_logs`); registrar `visualizacao` |
| Metadados do tipo documental na ficha | Mesma correção de props (`documento.metadados`) |
| Recentes e mais acessados | Gravar a ação `visualizacao` na auditoria |
| Obrigatoriedade de metadados e de campos do formulário de processo | Validar no servidor |
| Juntada de documentos ao processo, memorando, ofício e circular | Criar as rotas de download de anexos (hoje 404) |
| Comentários no processo | A tela posta em `/processos/{id}/comentarios`; o backend é `/tramitacoes/{id}/comentar` |
| Recebimento formal, devolução e cancelamento de processo | Ligar os botões (backend existe) |
| Histórico de auditoria do processo | Exibir `proc_historico` na tela |
| Lista de notificações | `/notificacoes` devolve JSON bruto |
| Ordem sequencial de signatários | A coluna `ordem` é gravada e nunca aplicada |
| Lixeira e reativação de pasta | Tela de listagem/restauração (backend parcial) |
| Recuperação de senha | O link `/forgot-password` da tela de login dá 404 |
| Página de termo de assinatura com QR | O QR aponta para a página genérica de upload |
| Verificação por QR de memorando, ofício e circular | As rotas citadas no PDF não existem |
| Assinatura com certificado A3 | Verificar no servidor a assinatura devolvida pelo token e testar com hardware real; confirmar licença da extensão Web PKI em produção |

## O que ficou de fora por não existir

Itens que editais de GED, assinatura e processo costumam pedir e que **não
existem** (detalhe e local da busca em `medicao/`):

- **GED:** OCR; plano de classificação e tabela de temporalidade (CONARQ);
  eliminação com termo; metadados do Decreto nº 10.278/2020; PDF/A;
  verificação periódica de integridade; captura de e-mail; importação de acervo
  legado; compartilhamento e link público com expiração; check-in/check-out;
  etiquetas (só estrutura); relatórios gerenciais.
- **Assinatura:** PAdES AD-RB conforme DOC-ICP-15.03; carimbo do tempo;
  PAdES-LT/LTA; LCR/OCSP; CAdES para não-PDF; assinatura avançada; gov.br;
  nuvem/HSM; assinador local; e-CNPJ; multi-assinatura criptográfica no mesmo
  PDF; assinatura em lote pelo signatário; signatário externo sem cadastro;
  lembretes e e-mail.
- **Processo:** BPMN e motor de fluxo genérico (o desenhador grava `nodes` e o
  motor lê `etapas`); gateways; etapas paralelas; dias úteis e feriados;
  alertas e escalonamento de prazo; ciência/intimação; sobrestamento;
  apensação; desarquivamento; autos com volumes e PDF consolidado; tramitação
  em lote; relatórios.
- **Portal:** gov.br; anexos na solicitação; consulta pública por protocolo;
  resposta do cidadão; avaliação de satisfação; ouvidoria; transparência;
  CAPTCHA e limite de tentativas; LGPD/consentimento; acessibilidade.
- **Segurança e continuidade:** 2FA; LDAP/SSO; bloqueio por tentativas;
  política de senha; inativação de usuário; restrição por horário/IP; auditoria
  administrativa e de login; consulta centralizada de auditoria; log imutável;
  backup automatizado; exportação integral da base; dicionário de dados;
  ajuda contextual.

Por isso o TR **não** traz controle de acesso por perfil, sigilo de documento,
auditoria consultável, versionamento pela tela nem segregação de dados por UG:
hoje não passariam na prova de conceito. Um TR de GED sem perfis de acesso e sem
auditoria fica estranho para quem lê, e é a primeira coisa que o concorrente
aponta. **As correções 1 a 4 e as três correções de props da ficha do documento
são o mínimo antes de oferecer este TR.**

## Cuidado de licitação

Minuta escrita por fornecedor e publicada sem adaptação é **direcionamento**, e
a Lei nº 14.133/2021 veda a especificação que restrinja a competição sem
justificativa técnica. Por isso o texto:

- descreve **função**, não produto, e diz isso no item 6.0.1;
- não cita nome de tela, de módulo nem de tecnologia nossa;
- limita a qualificação técnica ao que a lei admite (item 10.3) e dispensa visita
  técnica como condição de habilitação (item 10.4);
- prevê prova de conceito com roteiro publicado e ata item a item (item 9).

As obrigações de **cópia de segurança** (11.1.3) e de **entrega integral da
base** (11.3.1) estão como serviço da contratada, não como função do sistema:
hoje não há rotina automática nem exportação na aplicação, e cumpri-las depende
de procedimento de operação. O **manual do usuário** (7.3.2) também não existe e
precisa ser produzido.

O município tem de adaptar antes de publicar — quantitativos, prazos, dotação e
os módulos que ele efetivamente quer. O que está aqui é minuta, não edital
pronto.

## Como usar para responder edital de terceiro

A planilha `requisitos-gpedocs.csv` é o acervo. Para cada item do edital
alheio, localizar o requisito nosso equivalente e responder com ele. Quando não
houver equivalente, consultar `medicao/`: se o item estiver lá como 🟡 ou ❌, a
resposta é **não atende**, ou atende parcialmente dizendo qual parte. O item
13.2 deste TR existe justamente porque declarar atendimento que a prova de
conceito derruba é o erro que desclassifica.
