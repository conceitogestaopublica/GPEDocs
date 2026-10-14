### 6.9. Processo administrativo eletrônico — tipos e autuação

**RF-150.** Cadastro de tipos de processo administrativo, com nome, sigla, descrição, categoria e prazo padrão de atendimento, ativação e inativação, e bloqueio da exclusão de tipo que possua processos vinculados.

**RF-151.** Definição, por tipo de processo, de **sequência ordenada de etapas**, cada uma com natureza — análise, parecer, aprovação, assinatura, despacho ou arquivamento —, unidade sugerida, prazo e texto-modelo.

**RF-152.** Configuração, por tipo de processo, de **formulário dinâmico de abertura**, com campos de texto, número, valor monetário, data, texto longo e lista de opções, rótulo, ordem e obrigatoriedade configuráveis, recusada a abertura quando faltar campo obrigatório ou o valor não corresponder ao tipo do campo, e com os dados registrados no processo.

**RF-153.** Cadastro, por tipo de processo, de modelos de texto de despacho, aplicáveis com uma única ação no momento da redação do despacho.

**RF-154.** Abertura de processo administrativo eletrônico com tipo, assunto, descrição, prioridade — baixa, normal, alta ou urgente —, dados do requerente (nome, CPF, correio eletrônico e telefone), unidade de origem e unidade de destino inicial obrigatória, admitida a indicação de servidor destinatário.

**RF-155.** Atribuição automática de **número de protocolo** sequencial, anual e por tipo de processo, composto pela sigla do tipo, pelo ano e pelo número sequencial, único no sistema.

**RF-156.** Consulta de processos por número ou assunto, situação, tipo e prioridade, com paginação dos resultados.

**RF-157.** Juntada de arquivos digitais na abertura e nos despachos, com registro de nome, tamanho, autor e resumo criptográfico, e download dos arquivos juntados por quem tem acesso ao processo.

### 6.10. Tramitação e decisão

**RF-170.** Encaminhamento do processo a unidade da estrutura organizacional, com indicação opcional de servidor específico da unidade e texto de despacho obrigatório.

**RF-171.** Aplicação automática, a cada encaminhamento, do prazo da etapa seguinte prevista no tipo de processo.

**RF-172.** Histórico da tramitação em **linha do tempo**, com unidade de origem e de destino, remetente, destinatário, recebedor, situação, despacho, prazo e indicação de cumprimento do prazo.

**RF-173.** Registro da decisão final do processo — deferido, indeferido ou deferido parcialmente —, com fundamentação obrigatória, geração automática do documento da decisão em PDF e indexação do seu texto para pesquisa.

**RF-174.** Exigência de assinatura eletrônica do responsável no documento de decisão, permanecendo o processo na situação "aguardando assinatura" até que seja assinado e encerrando-se automaticamente após a assinatura.

**RF-175.** Arquivamento do processo sem decisão de mérito, com justificativa obrigatória e geração do documento de encerramento.

**RF-176.** Arquivamento do documento final do processo em pasta do repositório documental da mesma unidade gestora, com registro no processo.

**RF-177.** Registro formal do recebimento do processo pela unidade ou pelo servidor de destino, com data, hora e recebedor; o despacho de etapa ainda não recebida registra o recebimento por quem despacha.

**RF-178.** Devolução do processo ao remetente anterior, com justificativa, e cancelamento do processo, com motivo.

**RF-179.** Execução de recebimento, despacho, devolução, juntada, decisão, arquivamento e cancelamento somente pelo destinatário da etapa ativa — servidor indicado, servidor da unidade de destino ou usuário com acesso geral à unidade gestora — ou, antes do primeiro despacho, pelo autor, com verificação no servidor.

**RF-180.** Comentários no processo, com indicação de comentário interno, relacionados à etapa em curso e consultáveis no próprio processo.

### 6.11. Caixas de trabalho e prazos

**RF-190.** Caixa de entrada pessoal unificada, reunindo os processos e as comunicações internas endereçados ao usuário, com indicação de confidencialidade.

**RF-191.** Caixa de entrada da unidade organizacional, com os itens endereçados à unidade do usuário sem destinatário específico, e visão geral da unidade gestora para o usuário designado na forma do requisito RS-007.

**RF-192.** Caixas de acompanhamento com os itens em tramitação de que o usuário participou, os itens concluídos, os itens por ele originados e os itens aguardando a sua assinatura.

**RF-193.** Filtros nas caixas de trabalho por texto (número ou assunto), tipo de item e período, com contadores de pendências.

**RF-194.** Ações rápidas a partir da caixa de trabalho: assinar decisão pendente e arquivar no repositório documental o documento resultante.

**RF-195.** Cálculo automático do prazo de cada etapa, a partir do prazo da etapa ou do prazo padrão do tipo de processo, com sinalização visual das situações "no prazo", "próximo do vencimento" e "atrasado".

### 6.12. Comunicações oficiais

**RF-210.** Emissão de **memorando** interno com numeração anual automática, múltiplos destinatários — servidores ou unidades —, marcação de confidencialidade, respostas encadeadas, arquivamento e geração do documento em PDF.

**RF-211.** Tramitação de memorandos entre servidores e unidades, com confirmação de recebimento, encaminhamento sucessivo com parecer e opção de registrar o parecer como resposta ao remetente.

**RF-212.** Emissão de **circular** com numeração anual automática, destinada a todos os servidores, a unidades ou a servidores selecionados, com registro individual da leitura, com data e hora, e painel de acompanhamento das leituras.

**RF-213.** Emissão de **ofício** externo com numeração anual automática, dados do destinatário (nome, cargo, órgão e correio eletrônico), respostas, arquivamento, geração do documento em PDF e livro de controle de ofícios por ano.

**RF-214.** Cadastro de modelos de ofício, inclusive por importação de arquivo de editor de texto, e carga do modelo na redação do ofício.

**RF-215.** Restrição da visualização de memorandos, ofícios e circulares ao remetente, aos destinatários — pessoais ou por unidade — e aos participantes da tramitação.

**RF-216.** Download dos anexos de memorandos, ofícios e circulares por quem tem acesso à comunicação.

**RF-217.** Verificação pública da autenticidade de memorando, ofício e circular por QR Code impresso no documento em PDF, com número, remetente, unidade, órgão, data de emissão e situação, omitido o assunto de comunicação confidencial.

### 6.13. Notificações e mensagens

**RF-230.** Notificação interna automática ao destinatário, ou aos servidores da unidade de destino, quando processo, memorando ou circular lhe for encaminhado, respondido ou devolvido.

**RF-231.** Mensagens instantâneas entre servidores da mesma unidade gestora, com lista de contatos, histórico da conversa, contador de mensagens não lidas e marcação automática de leitura, disponíveis em todas as telas.

**RF-232.** Tela de notificações do usuário, com filtro das não lidas, marcação individual ou geral como lida e acesso direto ao documento, processo ou comunicação a que a notificação se refere.

### 6.14. Painel de processos

**RF-250.** Painel de processos da unidade gestora com o total de processos abertos, em tramitação e concluídos no mês, as pendências do usuário e os processos recentes.
