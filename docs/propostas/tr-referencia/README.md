# Termo de Referência de referência — GPEDocs

Minuta de Termo de Referência montada a partir **do que o sistema faz hoje**, para
oferecer a município que vá licitar gestão documental e para responder item a
item a edital de terceiro. Mesmo modelo do TR do tributário
(`gpd-web-tribut-rio/docs/propostas/tr-referencia/`).

## O que tem aqui

| Arquivo                                 | O que é                                                                         |
| --------------------------------------- | ------------------------------------------------------------------------------- |
| `docx/TR-completo.docx`                 | **148 requisitos** — 19 RT, 16 RS, 113 RF                                       |
| `docx/TR-enxuto.docx`                   | **119 requisitos** — sem carta de serviços/portal (6.15, 6.16) e sem integração (6.18) |
| `requisitos-gpedocs.csv`                | os 148 em planilha, com a coluna que diz se está na enxuta. É o Anexo I         |
| `00-abertura.md` … `06-fechamento.md`   | **a fonte**. Os `.docx` e o `.csv` são gerados daqui                            |
| `medicao/A…E-*.md`                      | a medição original contra o código, item a item, com evidência `arquivo:símbolo` |

Gerar de novo, após editar os `.md`:

```
npm install docx          # num diretório de trabalho, não no repo
node gerar-tr.js <dir dos .md> <dir de saída>
```

A enxuta é **subconjunto da completa** (constantes `ENXUTA_FAIXAS` e
`ENXUTA_SECOES_FORA` do gerador), nunca texto paralelo.

## De onde vieram os requisitos

Não havia edital de origem medido nem manual do usuário. A fonte foi o
**código**, medido em 06/10/2026 em cinco áreas:

| Área                                   | Medidos | ✅  | 🟡  | ❌  |
| -------------------------------------- | ------- | --- | --- | --- |
| A — Núcleo GED                         | 49      | 21  | 13  | 15  |
| B — Assinatura                         | 46      | 14  | 15  | 17  |
| C — Processo e comunicações            | 66      | 30  | 14  | 22  |
| D — Portal e integração                | 63      | 35  | 7   | 21  |
| E — Técnico, segurança e configuração  | 61      | 36  | 9   | 16  |
| **Total**                              | **285** | **136** | **58** | **91** |

A primeira versão do TR (127 requisitos) saiu dessa medição. Depois, a branch
`fix/pendencias-tr` corrigiu os defeitos que ela encontrou e o TR ganhou os 21
requisitos que passaram a valer — cada correção com teste de feature contra
banco PostgreSQL. **A pasta `medicao/` é o retrato de antes das correções**: os
itens 🟡 e ❌ dela que aparecem abaixo como corrigidos já não valem como estão
escritos lá.

**Critério de entrada no TR:** só o que **passa numa prova de conceito hoje**,
seguindo o caminho inteiro (tela → rota → controller → banco). Item parcial
entrou apenas na parte que funciona — por exemplo, "assinatura qualificada com
certificado A1" (o A3 não foi testado com token físico), "PDF assinado" (e não
"PDF com todas as assinaturas"), "envelope criptográfico verificável em leitor
de PDF" (e não "PAdES AD-RB").

## Corrigido na branch `fix/pendencias-tr`

| Defeito encontrado na medição | Requisito do TR que passou a valer |
| --- | --- |
| Perfis e permissões só cadastrais; qualquer usuário entrava na administração | RS-008 |
| Qualquer usuário criava ou editava conta `super_admin` | RS-009 |
| Busca avançada e painéis mostravam dados de outras UGs | RT-005 |
| Grau de sigilo gravado mas sem efeito; QR público expunha documento restrito | RF-023, RF-340 |
| Dumps com CPF e hash de senha versionados; senhas no documento de deploy; `admin123` fixo no seeder | (operação — ver abaixo) |
| Recusa não encerrava a solicitação, que virava "concluída" | RF-115 |
| Certificado vencido ou inativado e CPF ausente não barravam a assinatura | RF-102 |
| Manifesto de assinaturas baixável por qualquer usuário | RF-121 |
| Envio para assinatura pela API duplicava o documento | RF-405 |
| Editar tipo de processo em uso falhava | RF-151 |
| Arquivos de todos os entes no mesmo diretório | (isolamento: `tenants/<ente>/`) |
| Abas de versões, metadados e auditoria da ficha sempre vazias | RF-022, RF-032, RS-015 |
| "Recentes" e "Mais acessados" sempre vazios | RF-053 |
| Obrigatoriedade de metadado e de campo de formulário só visual | RF-021, RF-152 |
| Anexos de processo e de comunicações sem rota de download | RF-157, RF-216 |
| Comentário de processo postava em rota inexistente | RF-180 |
| Receber, despachar e cancelar sem conferência no servidor; sem botão de receber e de cancelar | RF-177, RF-178, RF-179 |
| Histórico do processo gravado e nunca exibido | RS-016 |
| "Ver todas as notificações" abria JSON bruto | RF-232 |
| Ordem de signatários gravada e ignorada | RF-114 |
| Sem lixeira; reativação de pasta só no servidor | RF-003, RF-061 |
| Link "Esqueceu a senha?" dava 404; login sem limite de tentativas | RS-010, RS-011 |
| QR do termo de assinatura caía na tela genérica; página de verificação sem assinaturas | RF-107, RF-340 |
| QR dos PDFs de memorando, ofício e circular apontava para rota inexistente | RF-217 |
| A3: assinatura devolvida pelo token gravada sem conferência | (ainda fora do TR: falta token físico) |
| Migration não criava `webhook_secret`: ente novo quebrava ao cadastrar sistema integrado | — |

## 🔴 Ainda pendente

| # | Pendência | Por quê não entrou |
| - | --------- | ------------------ |
| 1 | **Trocar as senhas** do Postgres de produção e dos logins `admin123` de paraguacu e arinos | Operação nos servidores, fora do código |
| 2 | **Limpar o histórico do Git** (dumps e senhas continuam nos commits antigos) | Reescreve o histórico e exige push forçado: decisão da equipe |
| 3 | **Validação de cadeia ICP-Brasil ancorada na AC Raiz**, com LCR/OCSP | Endurecer pode recusar certificados hoje aceitos até as demais raízes serem instaladas |
| 4 | **Assinatura A3 com token físico** | O servidor já confere a assinatura, mas falta o teste com hardware e a licença da extensão Web PKI em produção |

## O que ficou de fora por não existir

Itens que editais de GED, assinatura e processo costumam pedir e que **não
existem** (detalhe e local da busca em `medicao/`):

- **GED:** OCR; plano de classificação e tabela de temporalidade (CONARQ);
  eliminação com termo; metadados do Decreto nº 10.278/2020; PDF/A;
  verificação periódica de integridade; captura de e-mail; importação de acervo
  legado; compartilhamento e link público com expiração; check-in/check-out;
  etiquetas (só estrutura); edição dos dados do documento pela tela;
  relatórios gerenciais.
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
  CAPTCHA; LGPD/consentimento; acessibilidade.
- **Segurança e continuidade:** 2FA; LDAP/SSO; política de senha além do
  tamanho mínimo; inativação de usuário; restrição por horário/IP; auditoria
  administrativa e de login; consulta centralizada de auditoria; log imutável;
  backup automatizado; exportação integral da base; dicionário de dados;
  ajuda contextual.

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
não há rotina automática nem exportação na aplicação, e cumpri-las depende de
procedimento de operação. O **manual do usuário** (7.3.2) também não existe e
precisa ser produzido.

O município tem de adaptar antes de publicar — quantitativos, prazos, dotação e
os módulos que ele efetivamente quer. O que está aqui é minuta, não edital
pronto.

## Como usar para responder edital de terceiro

A planilha `requisitos-gpedocs.csv` é o acervo. Para cada item do edital
alheio, localizar o requisito nosso equivalente e responder com ele. Quando não
houver equivalente, consultar a lista acima e `medicao/`: se o item não existe,
a resposta é **não atende**, ou atende parcialmente dizendo qual parte. O item
13.2 deste TR existe justamente porque declarar atendimento que a prova de
conceito derruba é o erro que desclassifica.
