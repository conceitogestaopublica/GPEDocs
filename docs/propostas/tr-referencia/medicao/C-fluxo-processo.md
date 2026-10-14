# Medição C — GPE Flow: fluxos de trabalho e processo administrativo eletrônico

Base: repositório `c:\laragon\www\ged` (branch feat/integracao-arquivos-sem-assinatura), leitura de código em 2026-10-06. Nenhum arquivo do repositório foi alterado.
Legenda: ✅ atendido (caminho completo tela → rota → controller → model/migration verificado) · 🟡 parcial · ❌ inexistente.

Observação transversal: nenhuma rota do GPE Flow tem middleware de permissão/perfil (somente `auth` + UG selecionada — routes/web.php:160-372); as ações de processo (receber, despachar, devolver, concluir, cancelar, anexar) não validam no servidor se o usuário é o destinatário da etapa (TramitacaoController::receber/despachar, ProcessoController::concluir/cancelar). O controle existe só na interface (flags `pode_*` em ProcessoController::show).

## C.1 Modelagem de tipos de processo e de fluxos

| status | subseção sugerida | requisito (redação TR) | evidência | observação (o que falta, se 🟡/❌) |
|---|---|---|---|---|
| ✅ | C.1 Modelagem | Cadastro de tipos de processo administrativo, com nome, sigla, descrição, categoria e prazo padrão de atendimento em horas, com ativação/inativação e bloqueio de exclusão quando houver processos vinculados. | Admin/TipoProcessoController::store/update/destroy/toggleAtivo; Pages/GED/Admin/TiposProcesso/Index.jsx; migration 2026_04_07_000001 (proc_tipos_processo) | — |
| ✅ | C.1 Modelagem | Definição, por tipo de processo, de sequência ordenada de etapas, cada uma com natureza (análise, parecer, aprovação, assinatura, despacho, arquivamento), unidade sugerida, prazo em horas, texto-modelo e indicação de obrigatoriedade. | TipoProcessoController::store (etapas.*); Models/Processo/TipoEtapa; TiposProcesso/Index.jsx `tiposEtapa`, `emptyEtapa` | Campo de unidade da etapa é texto livre (não vinculado ao organograma). Ao editar um tipo, as etapas são apagadas e recriadas (`$tipo->etapas()->delete()`), o que viola a FK de tramitações já existentes em tipos em uso. |
| 🟡 | C.1 Modelagem | Encadeamento automático das etapas do tipo de processo durante a tramitação, sugerindo a próxima etapa e aplicando seu prazo. | TramitacaoController::despachar (`$proximaEtapa` por `ordem`) | A próxima etapa só define o prazo/rótulo; o destino (unidade/usuário) é sempre escolhido manualmente; a obrigatoriedade da etapa e o responsável padrão (`responsavel_id`, não editável na tela) não são aplicados; não há impedimento de pular etapas. |
| ✅ | C.1 Modelagem | Configuração, por tipo de processo, de formulário dinâmico de abertura, com campos de texto, número, valor monetário, data, texto longo e lista de opções, rótulo e ordem configuráveis, cujos dados ficam registrados no processo. | TiposProcesso/Index.jsx (`schema_formulario`, `emptyField`); Processos/Create.jsx `renderDynamicField`; ProcessoController::store (`dados_formulario`) | Obrigatoriedade do campo é apenas visual (asterisco); não há validação, nem no navegador nem no servidor. Sem regras de visibilidade condicional ou máscaras/validações por campo. |
| ✅ | C.1 Modelagem | Cadastro, por tipo de processo, de modelos de texto de despacho, aplicáveis com um clique ao redigir o despacho. | TiposProcesso/Index.jsx (`templates_despacho`); Processos/Show.jsx `templatesDespacho`/`applyTemplate` | Modelos sem variáveis de substituição (dados do processo). |
| 🟡 | C.1 Modelagem | Desenhador visual de fluxos de trabalho, com paleta de elementos (início, aprovação, revisão, notificação, condição, assinatura, arquivamento, fim), configuração de responsável, prazo e mensagem por elemento, e gravação do modelo. | Pages/GED/Fluxos/Builder.jsx; Pages/GED/Fluxos/Index.jsx; FluxoController::store/update; tabela ged_fluxos | Não é desenho gráfico: os nós são uma lista vertical (comentário no código: "without react-flow"); não há conexões/arestas desenháveis (`edges` sempre vazio), nem ramificação; responsável é texto livre. Tela não está no menu do GPE Flow (acesso só por /fluxos ou atalho no Dashboard do GED). `GED/Fluxos/Show` não existe (FluxoController::show quebra). |
| ❌ | C.1 Modelagem | Execução dos fluxos modelados no desenhador: instanciação sobre um documento, geração das etapas, atribuição ao responsável, avanço/aprovação/rejeição de etapas e conclusão. | FluxoController::iniciar; models FluxoInstancia/FluxoEtapa | `iniciar` lê `definicao['etapas']`, mas o desenhador grava `definicao['nodes']` → nenhuma etapa é criada. Nenhuma tela chama `/fluxos/{id}/iniciar`. Não existe rota/ação para concluir, aprovar ou rejeitar etapa de fluxo. Motor de workflow genérico inexistente na prática. |
| ❌ | C.1 Modelagem | Modelagem em notação BPMN 2.0, com importação/exportação do modelo e versionamento de versões de fluxo. | busca por "bpmn" em app/, resources/js, database/ — sem ocorrências; package.json tem @xyflow/react mas não é usado | — |
| ❌ | C.1 Modelagem | Regras condicionais/gateways que direcionem automaticamente o processo conforme dados do formulário ou decisão da etapa. | Builder.jsx tem nó "condicao" apenas decorativo; nenhuma avaliação de regra em TramitacaoController/FluxoController | — |
| ❌ | C.1 Modelagem | Etapas paralelas (tramitação simultânea para mais de uma unidade, com junção). | TramitacaoController::despachar cria uma única tramitação por vez | — |

## C.2 Autuação, numeração e cadastro do processo

| status | subseção sugerida | requisito (redação TR) | evidência | observação |
|---|---|---|---|---|
| ✅ | C.2 Autuação | Abertura de processo administrativo eletrônico com tipo, assunto, descrição, prioridade (baixa, normal, alta, urgente), dados do requerente (nome, CPF, e-mail, telefone), unidade de origem e unidade de destino inicial obrigatória, com destinatário pessoal opcional. | Processos/Create.jsx; ProcessoController::store; tabela proc_processos | Sem cadastro de interessados múltiplos nem vínculo com cadastro de pessoas; CPF sem validação de dígito. |
| ✅ | C.2 Autuação | Atribuição automática de número de protocolo sequencial e anual por tipo de processo, no formato SIGLA-AAAA/NNNNNN, único no sistema. | ProcessoController::store (`$protocolo`); unique em proc_processos.numero_protocolo | Sequência via MAX+1 sem bloqueio (concorrência gera erro de duplicidade). Formato não segue o Número Único de Protocolo (NUP) com dígito verificador. |
| ✅ | C.2 Autuação | Juntada de arquivos digitais na abertura do processo, com registro de nome, tamanho, tipo, autor e resumo criptográfico SHA-256. | ProcessoController::store (ProcessoAnexo, `hash_sha256`); proc_anexos | Ver C.4 sobre download. |
| ✅ | C.2 Autuação | Abertura automática de processo a partir de solicitação do cidadão em portal de serviços, com encaminhamento à unidade responsável pelo serviço e tipo de processo pré-configurado. | Services/AbrirProcessoDoPortalService::abrir; Portal/SolicitacaoController::store | — |
| ✅ | C.2 Autuação | Consulta de processos com filtros por número/assunto, situação, tipo e prioridade, com paginação. | ProcessoController::index; Processos/Index.jsx | Lista todos os processos da UG para qualquer usuário (ver C.7). |
| ❌ | C.2 Autuação | Formação de autos com folhas/peças numeradas sequencialmente, organização em volumes e termo de abertura/encerramento de volume. | sem ocorrências de "volume", "folha", "peca" em app/ e migrations | — |
| ❌ | C.2 Autuação | Geração de capa do processo e exportação dos autos completos em arquivo único (PDF consolidado com peças e despachos). | resources/views/pdf contém apenas processo-decisao, memorando, oficio, circular | Só existe o PDF da decisão final. |
| ❌ | C.2 Autuação | Edição de dados cadastrais do processo após a autuação, com registro da alteração. | routes/web.php:337 `except(['edit','update','destroy'])` | — |

## C.3 Tramitação entre unidades

| status | subseção sugerida | requisito (redação TR) | evidência | observação |
|---|---|---|---|---|
| ✅ | C.3 Tramitação | Encaminhamento (despacho) do processo para unidade do organograma, com indicação opcional de servidor específico da unidade, texto de despacho obrigatório e juntada de arquivos no mesmo ato. | Processos/Show.jsx `handleDespachar`; TramitacaoController::despachar; proc_tramitacoes.destino_unidade_id | — |
| 🟡 | C.3 Tramitação | Registro formal de recebimento do processo pela unidade/servidor de destino, com data, hora e usuário recebedor. | TramitacaoController::receber; Processos/Inbox.jsx (`/tramitacoes/{id}/receber`) | O botão de recebimento só existe na tela /processos/inbox, que não está no menu e lista apenas processos endereçados pessoalmente (não os endereçados à unidade). Na tela do processo não há ação de receber, e o despacho é permitido sem recebimento prévio. |
| 🟡 | C.3 Tramitação | Devolução do processo ao remetente anterior, com justificativa. | TramitacaoController::devolver; rota tramitacoes/{id}/devolver | Backend existe, mas a função `handleDevolver` em Processos/Show.jsx não está ligada a nenhum botão — indisponível ao usuário. |
| ✅ | C.3 Tramitação | Histórico de tramitação do processo em linha do tempo, com unidades de origem e destino, remetente, destinatário, recebedor, situação, despacho, prazo e indicação de cumprimento do prazo. | Processos/Show.jsx aba "tramitacao" (`getSlaInfo`); ProcessoController::show (`tramitacoes.*`) | — |
| ✅ | C.3 Tramitação | Tramitação de memorandos entre servidores e unidades, com confirmação de recebimento, encaminhamento sucessivo com parecer e opção de registrar o parecer como resposta ao remetente. | MemorandoController::receber/tramitar; Memorandos/Show.jsx; proc_memorando_tramitacoes | — |
| ❌ | C.3 Tramitação | Tramitação em lote (receber/encaminhar vários processos de uma vez). | InboxController e Flow/Inbox.jsx sem seleção múltipla; rotas só por id | — |
| ❌ | C.3 Tramitação | Cancelamento/desfazimento de envio antes do recebimento pelo destino. | sem rota correspondente em routes/web.php | — |

## C.4 Peças processuais: despachos, pareceres, decisões e juntada

| status | subseção sugerida | requisito (redação TR) | evidência | observação |
|---|---|---|---|---|
| 🟡 | C.4 Peças | Juntada de documentos ao processo em qualquer etapa da tramitação, com registro de autor, data e resumo criptográfico. | TramitacaoController::anexar (rota tramitacoes/{id}/anexar) | Upload funciona no despacho e na abertura, mas não existe rota de download: Processos/Show.jsx aponta para `/anexos/{id}/download`, inexistente em routes/web.php — os arquivos juntados não podem ser abertos. A ação "anexar" avulsa não é chamada por nenhuma tela. Mesmo defeito em memorandos (`/memorandos/{id}/anexos/{id}/download`), ofícios e circulares. |
| ✅ | C.4 Peças | Registro de decisão final do processo (deferido, indeferido, deferido parcialmente) com fundamentação obrigatória, geração automática de documento PDF da decisão e indexação do seu texto para busca. | Processos/Show.jsx `handleDecidir`; ProcessoController::concluir (Pdf `pdf.processo-decisao`, Documento/Versao, `ocr_texto`) | — |
| ✅ | C.4 Peças | Exigência de assinatura digital do responsável no documento de decisão formal, mantendo o processo na situação "aguardando assinatura" até a assinatura e encerrando-o automaticamente após assinado. | ProcessoController::concluir (`aguardando_assinatura`, SolicitacaoAssinatura); AssinaturaController::finalizarProcessoSeVinculado; InboxController::aguardandoAssinatura | Assinatura apenas pelo próprio decisor (signatário único = usuário logado); sem coassinatura/assinatura em sequência para a decisão. |
| 🟡 | C.4 Peças | Registro de comentários no processo, com opção de comentário interno de visibilidade restrita. | TramitacaoController::comentar; Processos/Show.jsx aba "comentarios" | A tela envia para `/processos/{id}/comentarios` com campo `conteudo`; a rota não existe (o backend é `/tramitacoes/{id}/comentar` com campo `texto`) — inclusão de comentário falha. A marca "interno" é só visual; não restringe quem vê. |
| 🟡 | C.4 Peças | Registro de despachos e pareceres por etapa, com texto livre. | proc_tramitacoes.despacho/parecer; TramitacaoController::despachar | Despacho é texto livre gravado na tramitação; não é gerado como documento/peça autônoma nem assinável. Campo `parecer` nunca é preenchido. |
| ❌ | C.4 Peças | Editor de documentos internos do processo com modelos e assinatura eletrônica de cada peça (despacho, parecer, nota técnica) antes da juntada. | sem editor no fluxo de processo; peças são apenas uploads | Há editor/modelos apenas para ofício (ver C.9). |

## C.5 Caixas de trabalho e acompanhamento

| status | subseção sugerida | requisito (redação TR) | evidência | observação |
|---|---|---|---|---|
| ✅ | C.5 Caixas | Caixa de entrada pessoal unificada, reunindo processos, memorandos e ofícios endereçados ao usuário, com indicação de não lidos e de confidencialidade. | Flow/InboxController::pessoal; view SQL proc_inbox (migrations 2026_05_01_120000/150000); Pages/GED/Flow/Inbox.jsx; menu "Caixa Pessoal" | Indicador "lido" de processos nunca é atualizado (`proc_tramitacoes.lida_em` não é gravado em nenhum ponto do código). Ofícios internos nunca aparecem (ver C.9). |
| ✅ | C.5 Caixas | Caixa de entrada da unidade organizacional, com os itens endereçados à unidade do usuário sem destinatário específico, e visão geral para usuário com acesso a toda a unidade gestora. | InboxController::setor (`acesso_geral_ug`) | — |
| ✅ | C.5 Caixas | Caixas de acompanhamento: itens em tramitação de que o usuário participou, itens concluídos, itens originados pelo usuário e itens aguardando sua assinatura. | InboxController::emTramitacao/concluidos/saida/aguardandoAssinatura; menu GPE Flow | — |
| 🟡 | C.5 Caixas | Caixa de rascunhos de documentos e processos ainda não enviados. | InboxController::rascunhos | Memorandos, ofícios, circulares e processos são gravados já como enviados/aberto; não há salvamento como rascunho — a caixa fica sempre vazia. |
| ✅ | C.5 Caixas | Filtros nas caixas de trabalho por texto (número/assunto), tipo de item, somente não lidos e período, com contadores de pendências. | InboxController::render (`busca`, `tipo`, `nao_lidos`, `data_de/ate`), `contagens()` | — |
| ✅ | C.5 Caixas | Ações rápidas a partir da caixa de trabalho: assinar decisão pendente e arquivar no repositório documental o documento resultante. | InboxController::render (`pode_assinar`, `pode_arquivar_ged`); Flow/Inbox.jsx | — |

## C.6 Prazos e SLA

| status | subseção sugerida | requisito (redação TR) | evidência | observação |
|---|---|---|---|---|
| ✅ | C.6 Prazos | Cálculo automático do prazo de cada etapa a partir do prazo da etapa ou do prazo padrão do tipo de processo, com sinalização visual de "no prazo", "próximo do vencimento" e "atrasado". | ProcessoController::store / TramitacaoController::despachar (`prazo = now()->addHours`); `getSlaInfo` em Processos/Show.jsx e Inbox.jsx | Prazo em horas corridas. |
| ❌ | C.6 Prazos | Contagem de prazos em dias úteis, considerando calendário de feriados e pontos facultativos. | busca por "feriado", "dias_uteis" — sem ocorrências | — |
| ❌ | C.6 Prazos | Alerta automático de prazo a vencer/vencido e escalonamento ao superior hierárquico. | routes/console.php sem agendamentos; app/Console/Commands sem rotina de prazo | — |
| ❌ | C.6 Prazos | Arquivamento automático de comunicações em data programada. | campo `data_arquivamento_auto` gravado (MemorandoController::store, CircularController::store), sem rotina agendada que o execute | Campo existe, mas nada o executa. |
| ❌ | C.6 Prazos | Sobrestamento/suspensão do processo com interrupção da contagem de prazo. | busca "sobrest", "suspens" — sem ocorrências | — |

## C.7 Encerramento, arquivamento, sigilo e controle de acesso

| status | subseção sugerida | requisito (redação TR) | evidência | observação |
|---|---|---|---|---|
| ✅ | C.7 Encerramento | Arquivamento do processo sem decisão de mérito, com justificativa e geração de documento de encerramento. | Processos/Show.jsx `handleArquivar`; ProcessoController::concluir (`decisao=arquivado`) | — |
| ✅ | C.7 Encerramento | Arquivamento do documento final do processo em pasta do repositório de gestão documental da mesma unidade gestora, com registro no histórico. | ProcessoController::arquivarNoGed; Processos/Show.jsx `handleArquivarGed` | Equivalente para memorandos, ofícios e circulares (`arquivarNoGed` nos respectivos controllers). |
| 🟡 | C.7 Encerramento | Cancelamento de processo com motivo. | ProcessoController::cancelar; rota processos/{id}/cancelar | Não há botão que abra o modal de cancelamento em Processos/Show.jsx (`setShowCancelarModal(true)` nunca é chamado) e o modal envia sem motivo. |
| ❌ | C.7 Encerramento | Desarquivamento/reabertura de processo arquivado ou concluído, com justificativa. | busca "desarquiv", "reabr" — sem ocorrências; sem rota | — |
| ❌ | C.7 Encerramento | Apensação e anexação de processos (vínculo entre processos), com desapensação. | busca "apens" — sem ocorrências; proc_processos sem relação processo-processo | — |
| ❌ | C.7 Sigilo | Classificação de processo quanto ao nível de acesso (público, restrito, sigiloso) com restrição de visualização aos participantes e credenciados. | ProcessoController::show/index sem verificação de acesso; proc_processos sem campo de sigilo | Qualquer usuário autenticado da UG lista e abre qualquer processo. |
| ✅ | C.7 Sigilo | Restrição da visualização de memorandos, ofícios e circulares ao remetente, destinatários (pessoais ou por unidade) e participantes da tramitação, com marcação de confidencialidade. | MemorandoController::show (abort 403), OficioController::show (403), CircularController::show (403); `confidencial` em proc_memorandos | Marca "confidencial" é apenas rótulo; a regra de acesso é a mesma para todos os memorandos. |
| 🟡 | C.7 Auditoria | Trilha de auditoria do processo registrando abertura, recebimento, despacho, devolução, comentário, juntada, decisão, assinatura, cancelamento e arquivamento, com usuário, data/hora, IP e navegador. | ProcessoHistorico::create em ProcessoController e TramitacaoController; proc_historico | Gravada no banco, mas não exibida em nenhuma tela (Processos/Show.jsx não usa `processo.historico`). |
| 🟡 | C.7 Acesso | Controle de acesso às funções de processo por perfil de usuário. | Models Role/Permission existem; routes/web.php sem middleware de permissão nas rotas do GPE Flow | Ver observação transversal: validação apenas na interface. |

## C.8 Notificações e comunicação instantânea

| status | subseção sugerida | requisito (redação TR) | evidência | observação |
|---|---|---|---|---|
| 🟡 | C.8 Notificações | Notificação interna automática ao destinatário (ou a todos os servidores da unidade de destino) quando um processo, memorando ou circular lhe é encaminhado, respondido ou devolvido, com contador de não lidas no cabeçalho. | Notificacao::create em ProcessoController::store, TramitacaoController::despachar/devolver, MemorandoController, CircularController; HandleInertiaRequests (`notificacoes_pendentes`); AdminLayout `NotificacoesDropdown` | O menu mostra só o número; a lista não é exibida: "Ver todas" leva a /notificacoes, que devolve JSON bruto (NotificacaoController::index), e não há tela para marcar como lida (rota existe sem uso). |
| ❌ | C.8 Notificações | Envio de notificações por e-mail a servidores sobre tramitações e prazos. | Mail:: só é usado no portal do cidadão (PortalSolicitacoesController, SincronizarPortalCidadaoService) | — |
| ✅ | C.8 Notificações | Comunicação automática ao requerente externo do andamento e da decisão de processos originados no portal do cidadão, após a assinatura da decisão quando exigida. | SincronizarPortalCidadaoService (Mail); ProcessoController::concluir; AssinaturaController::finalizarProcessoSeVinculado | — |
| ✅ | C.8 Chat | Mensagens instantâneas entre servidores da mesma unidade gestora, com lista de contatos, histórico de conversa, contador de não lidas e marcação automática de leitura, disponíveis em todas as telas. | ChatController::contatos/mensagens/enviar/naoLidas; Components/ChatFlutuante.jsx (atualização periódica 5–10 s); chat_mensagens | Só texto (até 2000 caracteres); sem anexos, grupos ou vínculo com processo. |
| ❌ | C.8 Ciência | Ciência/intimação eletrônica de interessado com registro de data de ciência e contagem de prazo a partir dela. | busca "ciencia", "intima" — sem ocorrências | Circulares registram leitura (ver C.9), mas sem efeito processual. |

## C.9 Comunicações oficiais (memorando, circular, ofício)

| status | subseção sugerida | requisito (redação TR) | evidência | observação |
|---|---|---|---|---|
| ✅ | C.9 Comunicações | Emissão de memorando interno com numeração anual automática, múltiplos destinatários (servidores e/ou unidades), anexos, marcação de confidencialidade, respostas encadeadas, arquivamento e geração de PDF. | MemorandoController::store/responder/arquivar/downloadPdf; Memorandos/Create.jsx, Show.jsx | Download dos anexos quebrado (rota inexistente). |
| ✅ | C.9 Comunicações | Emissão de circular com numeração anual automática, destinada a todos, a unidades ou a servidores selecionados, com registro individual de leitura (data/hora) e painel de acompanhamento de leituras. | CircularController::store/show (`lido`, `lido_em`); Circulares/Show.jsx "Rastreio de Leitura" | Destino por unidade gravado como texto do nome da unidade. |
| ✅ | C.9 Comunicações | Emissão de ofício externo com numeração anual automática, dados do destinatário (nome, cargo, órgão, e-mail), anexos, respostas, arquivamento, PDF e livro de controle de ofícios por ano. | OficioController::store/responder/arquivar/downloadPdf/controle; Oficios/Controle.jsx | — |
| ✅ | C.9 Comunicações | Cadastro de modelos de ofício, inclusive por importação de arquivo de editor de texto, e carga do modelo na redação do ofício. | Admin/OficioModeloController (importarDocx via PhpWord); Oficios/Create.jsx "Carregar modelo" | Sem variáveis de mesclagem. |
| 🟡 | C.9 Comunicações | Envio eletrônico do ofício ao destinatário externo com rastreamento de abertura (data/hora de leitura). | OficioController::rastrear; rota oficios/rastrear/{token}; Oficios/Rastreio.jsx | Nenhum e-mail é enviado (sem Mail:: em OficioController) e o `rastreio_token` nunca é gerado — o rastreio nunca é acionável. |
| ❌ | C.9 Comunicações | Ofício interno endereçado a servidor ou unidade, exibido na caixa de entrada. | colunas destinatario_usuario_id/unidade_id existem (migration 2026_05_01_120000), mas OficioController::store não as preenche | — |
| 🟡 | C.9 Autenticidade | Verificação de autenticidade de memorando, circular e ofício por código QR impresso no PDF. | MemorandoController/OficioController/CircularController::downloadPdf (`qrCodeUrl` /memorandos|oficios|circulares/verificar/{token}) | As rotas de verificação dessas comunicações não existem (só `verificar/{token}` de documentos do GED); o token não é gerado na criação. |

## C.10 Painéis e relatórios

| status | subseção sugerida | requisito (redação TR) | evidência | observação |
|---|---|---|---|---|
| ✅ | C.10 Painéis | Painel de processos com totais de abertos, em tramitação, concluídos no mês, etapas atrasadas, pendências do usuário e processos recentes. | ProcessoDashboardController::__invoke; Processos/Dashboard.jsx; menu "Painel" | Indicadores fixos; sem filtro por período/unidade/tipo nem gráficos. |
| ❌ | C.10 Relatórios | Relatórios gerenciais de processos (por unidade, tipo, situação, tempo médio por etapa, produtividade por servidor) com exportação em planilha/PDF. | sem controllers/rotas de relatório ou exportação no GPE Flow | — |
| ❌ | C.10 Relatórios | Consulta pública de andamento de processo por número de protocolo, sem autenticação. | rotas públicas só para ofício (rastrear) e documentos; acompanhamento do portal é da solicitação, não do processo | Avaliar com a área do Portal do Cidadão. |
