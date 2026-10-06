### 6.15. Carta de Serviços ao Usuário

**RF-300.** Publicação, por unidade gestora, de catálogo de serviços ao usuário acessível sem autenticação, contendo, para cada serviço, descrição resumida e completa, requisitos, relação de documentos necessários, prazo de atendimento, custo, canais de atendimento, órgão responsável e legislação aplicável.

**RF-301.** Manutenção da carta de serviços pelo gestor da unidade, com cadastro, edição e exclusão de categorias — nome, ícone, cor, descrição, ordem e ativação — e de serviços, publicação e despublicação individual de cada serviço, definição do público-alvo e bloqueio da exclusão de categoria que possua serviços.

**RF-302.** Navegação dos serviços por categoria, exibindo-se apenas as categorias ativas e os serviços publicados, com a contagem de serviços de cada categoria.

**RF-303.** Pesquisa de serviços por título, descrições e palavras-chave, sem distinção de maiúsculas, com filtros por categoria e por público-alvo e paginação dos resultados.

**RF-304.** Contabilização dos acessos a cada serviço, destaque dos serviços mais acessados na página inicial e sugestão de serviços relacionados da mesma categoria.

**RF-305.** Personalização do portal por unidade gestora, com brasão, dados de contato institucional e carrossel de avisos com título, subtítulo, endereço de destino, ativação e ordem definidos pelo gestor.

### 6.16. Solicitações do cidadão

**RF-320.** Autocadastro do cidadão, com nome, correio eletrônico, CPF, telefone e senha com confirmação, e autenticação em sessão própria, segregada da autenticação dos servidores.

**RF-321.** Abertura de solicitação de serviço pelo cidadão autenticado, com descrição e dados de contato, gerando código de protocolo sequencial por unidade gestora.

**RF-322.** Registro de solicitação **anônima**, habilitável por serviço, sem coleta de dados pessoais, com exibição do código de protocolo ao final.

**RF-323.** Conversão automática da solicitação em **processo administrativo eletrônico**, com tipo de processo e unidade responsável definidos no cadastro do serviço, dados do requerente e primeira tramitação com prazo calculado.

**RF-324.** Área do cidadão com a relação das suas solicitações, a contagem por situação e o detalhamento de cada uma em linha do tempo — abertura, mudanças de situação, mensagens e conclusão.

**RF-325.** Download, pelo cidadão, dos anexos do processo vinculado à sua solicitação e do documento de decisão, preferencialmente na versão assinada digitalmente, com verificação de que o arquivo pertence a solicitação do próprio cidadão.

**RF-326.** Notificação do cidadão por correio eletrônico na abertura, a cada mudança de situação, a cada mensagem do atendente e na conclusão, com o documento de decisão assinado anexado.

**RF-327.** Atualização automática da situação da solicitação conforme a decisão do processo vinculado, inclusive após a assinatura do documento de decisão.

**RF-328.** Fila de atendimento das solicitações para os servidores, com filtros por situação, serviço e texto, contagem por situação, alteração de situação com resposta, envio de mensagens ao cidadão e exibição do processo vinculado.

### 6.17. Autenticidade de documentos

**RF-340.** Verificação pública da autenticidade de documento do repositório por código de verificação ou **QR Code**, sem autenticação, com exibição do tipo, do autor, da situação, da versão vigente e do resumo criptográfico do arquivo.

### 6.18. Integração com sistemas da Administração

#### 6.18.1. Credenciais

**RF-400.** Cadastro dos sistemas externos autorizados a integrar-se à solução, com emissão de credencial exclusiva na forma do requisito RS-008, regeneração com invalidação da credencial anterior, ativação e desativação, e bloqueio da exclusão de sistema que tenha enviado documentos.

**RF-401.** Autenticação de cada requisição da interface de integração por credencial do tipo portador, sem sessão, com resposta padronizada de acesso não autorizado e registro da data do último uso da credencial.

**RF-402.** Painel de acompanhamento das integrações, com a quantidade de documentos recebidos, de notificações entregues e de falhas de entrega por sistema.

#### 6.18.2. Documentos para assinatura

**RF-405.** Recebimento, pela interface de integração, de documento PDF com tipo documental, unidade gestora, número de origem, nome, descrição, metadados, pasta de destino, endereço de retorno e relação de signatários identificados por CPF, com posição visual da assinatura no documento, criando-se o documento, a versão e a solicitação de assinatura em uma única transação.

**RF-406.** Consulta, pelo sistema de origem, da situação do documento e de cada assinatura.

**RF-407.** Substituição do arquivo por nova versão antes da primeira assinatura, com substituição opcional dos signatários pendentes, rejeitando-se a operação após qualquer assinatura ou recusa.

**RF-408.** Recuperação, pelo sistema de origem, do PDF assinado, somente após a conclusão das assinaturas e vedada para documentos cancelados.

**RF-409.** Cancelamento do documento pelo sistema de origem, com motivo obrigatório, encerramento das assinaturas pendentes, retirada da pasta de arquivamento e preservação do histórico das assinaturas já realizadas.

#### 6.18.3. Arquivamento sem assinatura

**RF-410.** Recebimento e arquivamento, pela interface de integração, de arquivos de qualquer formato sem fluxo de assinatura, com tipo documental, grau de sigilo, pasta e metadados, de forma **idempotente** por sistema, unidade gestora e número de origem, de modo que o reenvio do mesmo número gere nova versão do mesmo documento, sem duplicá-lo.

**RF-411.** Recuperação, pelo sistema de origem, do conteúdo da versão vigente de arquivo arquivado, para exibição no próprio sistema externo.

#### 6.18.4. Notificações de retorno

**RF-415.** Envio de notificação ao endereço de retorno do sistema de origem nos eventos de assinatura individual, de recusa e de conclusão das assinaturas, com conteúdo assinado por HMAC-SHA256 e cabeçalhos de identificação do sistema e do evento.

**RF-416.** Registro de cada entrega de notificação, com o conteúdo enviado, a assinatura, o código de retorno, a resposta recebida, a duração e o erro, quando houver.

**RF-417.** Reenvio de notificação pelo administrador, a partir do registro de entrega, e pelo próprio sistema de origem, quanto à notificação de conclusão.

**RF-418.** Regeneração do segredo de assinatura das notificações independentemente da credencial de acesso.
