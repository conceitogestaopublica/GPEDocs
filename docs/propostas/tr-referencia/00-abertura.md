# TERMO DE REFERÊNCIA

## Contratação de solução informatizada de gestão documental, assinatura eletrônica e processo administrativo eletrônico

**Modalidade sugerida:** Pregão Eletrônico — Lei nº 14.133/2021
**Objeto:** licenciamento de uso, implantação, conversão de dados, treinamento, suporte técnico e manutenção de sistema integrado de gestão eletrônica de documentos, assinatura eletrônica, processo administrativo eletrônico e atendimento ao cidadão.

---

## 1. DO OBJETO

1.1. Contratação de empresa especializada para o **licenciamento de uso de solução informatizada, integrada e nativamente web**, destinada à gestão eletrônica de documentos do Município, abrangendo repositório documental, assinatura eletrônica e digital no padrão ICP-Brasil, processo administrativo eletrônico, comunicações oficiais, carta de serviços e atendimento ao cidadão, e interface de integração com os demais sistemas da Administração.

1.2. O objeto compreende, de forma indissociável:

| Item | Descrição                                                       | Unidade      |
| ---- | --------------------------------------------------------------- | ------------ |
| 1    | Licenciamento de uso da solução (todos os módulos)              | mês          |
| 2    | Implantação, parametrização e conversão de dados estruturais    | serviço      |
| 3    | Treinamento e capacitação de servidores                         | serviço      |
| 4    | Suporte técnico, manutenção legal e corretiva e atualizações    | mês          |
| 5    | Serviços técnicos eventuais sob demanda                         | hora técnica |

1.3. A solução será fornecida em regime de **licenciamento por prazo determinado**, não havendo cessão definitiva de código-fonte, ressalvado o disposto na cláusula de continuidade do item 11.3.

---

## 2. DA JUSTIFICATIVA

2.1. A Administração produz e recebe diariamente documentos que fundamentam atos administrativos, e a sua guarda em papel ou em pastas de rede dispersas impede a localização tempestiva, a comprovação de autenticidade e o controle de quem praticou cada ato.

2.2. A contratação é ainda necessária ao cumprimento de obrigações que independem da vontade do Município:

a) prestação digital de serviços públicos e tramitação eletrônica de processos administrativos, na forma da Lei nº 14.129/2021;

b) uso de assinaturas eletrônicas em interações com o ente público e em atos de agentes públicos, nos níveis definidos pela Lei nº 14.063/2020, e validade jurídica do documento eletrônico assinado com certificado ICP-Brasil, na forma da Medida Provisória nº 2.200-2/2001;

c) publicação da Carta de Serviços ao Usuário e recebimento de solicitações por meio eletrônico, na forma da Lei nº 13.460/2017;

d) atendimento à Lei nº 13.709/2018 (LGPD) no tratamento de dados pessoais de servidores e de cidadãos.

2.3. A exigência de **solução integrada** decorre da natureza do ciclo documental: o pedido do cidadão gera o processo, o processo gera a decisão, a decisão é assinada e o documento assinado é arquivado. A fragmentação desse ciclo em sistemas distintos reintroduz digitação, quebra a cadeia de autenticidade entre o documento produzido e o documento guardado e impede a rastreabilidade do ato.

---

## 3. DA DESCRIÇÃO DA SOLUÇÃO

3.1. A solução deverá constituir **sistema único**, com navegação integrada, de modo que o documento produzido, assinado ou recebido em um módulo esteja imediatamente disponível aos demais, sem exportação, importação ou replicação manual.

3.2. A solução deverá operar integralmente em **ambiente web**, acessível por navegador, na forma do requisito RT-001.

3.3. Os módulos relacionados no item 6 integram o objeto e deverão ser entregues em funcionamento, ressalvados os que a Administração expressamente dispensar no instrumento convocatório.

---

## 4. DOS REQUISITOS TÉCNICOS E DE ARQUITETURA

### 4.1. Arquitetura e acesso

**RT-001.** Aplicação inteiramente web, executada em navegador atualizado, sem instalação de componente na estação do usuário, ressalvado o componente exigido pelo fabricante do dispositivo criptográfico para a assinatura com certificado armazenado em token ou cartão.

**RT-002.** Interface responsiva, com adaptação do menu e do cabeçalho a telas de dimensão reduzida, inclusive de dispositivos móveis.

**RT-003.** Aplicação e banco de dados hospedados em **ambiente de nuvem**, por conta da contratada, dispensando o Município de prover infraestrutura de servidor e licenças de sistema operacional ou de banco de dados.

**RT-004.** Base de dados **própria e exclusiva do Município**, separada da base de qualquer outro contratante da solução, com identificação do ente pelo endereço eletrônico de acesso.

**RT-005.** Atuação de **múltiplas unidades gestoras** no mesmo ente, com seleção obrigatória da unidade de trabalho após a autenticação, seleção automática quando o usuário possuir vínculo com uma única unidade, troca de unidade sem nova autenticação e revalidação do vínculo a cada operação.

**RT-006.** Armazenamento dos arquivos digitais em repositório configurável, em disco do servidor de aplicação ou em serviço de armazenamento de objetos compatível com o protocolo S3.

**RT-007.** Execução das cargas de grande volume em **segundo plano**, sem bloqueio da sessão do usuário, com identificação do ente em cada tarefa e registro das falhas.

**RT-008.** Implantação em contêineres, com procedimento automatizado de atualização de versão que compreenda a atualização do código, das dependências e da estrutura de dados de todos os entes atendidos.

**RT-009.** Endereço de verificação de disponibilidade da aplicação, para monitoramento contínuo.

**RT-010.** Tráfego entre o navegador e o servidor de aplicação exclusivamente por canal cifrado, com protocolo TLS na versão 1.2 ou superior e redirecionamento automático de acesso não cifrado.

### 4.2. Interoperabilidade

**RT-011.** Disponibilização de **interface de programação de aplicações (API)** para integração com sistemas de terceiros, em padrão REST, com tráfego em JSON, códigos de retorno HTTP padronizados e processamento transacional, com reversão integral da operação em caso de falha.

**RT-012.** Autenticação das integrações por credencial própria de cada sistema, distinta da credencial de usuário humano, com possibilidade de regeneração e de desativação individual.

**RT-013.** Mecanismo de notificação ativa de eventos (_webhook_) aos sistemas integrados, com assinatura criptográfica do conteúdo que permita ao receptor verificar a origem e a integridade da mensagem.

**RT-014.** Documentação técnica da interface de integração publicada pela própria aplicação, acessível para consulta e para download.

### 4.3. Usabilidade e padronização

**RT-015.** Padrão visual e funcional **uniforme** em todas as telas e módulos, obtido por componentes de interface compartilhados — cabeçalho de página, tabelas, campos, botões, janelas, confirmações e mensagens.

**RT-016.** Organização das funcionalidades em módulos, cada um com menu próprio, e tela inicial de escolha do módulo de trabalho.

**RT-017.** Busca de funcionalidades por **atalho de teclado**, a partir de campo único, com pesquisa por termo sem distinção de maiúsculas e de acentos e encaminhamento à pesquisa de conteúdo dos documentos.

**RT-018.** Marcação de funcionalidades favoritas por usuário e por unidade gestora, exibidas em destaque na busca de funcionalidades.

**RT-019.** Notificações internas ao usuário, com contador de não lidas permanentemente visível no cabeçalho.

---

## 5. DOS REQUISITOS DE SEGURANÇA E PROTEÇÃO DE DADOS

### 5.1. Identidade e acesso

**RS-001.** Autenticação individual por usuário, mediante endereço de correio eletrônico ou CPF e senha, com renovação do identificador de sessão a cada autenticação.

**RS-002.** Armazenamento de senhas exclusivamente sob a forma de resumo criptográfico irreversível, vedado o armazenamento de senha em texto legível.

**RS-003.** Encerramento automático da sessão após período de inatividade definido em parâmetro.

**RS-004.** Encerramento voluntário da sessão, com invalidação da sessão e do token de proteção de formulários.

**RS-005.** Proteção contra requisições forjadas entre sítios em todas as operações autenticadas da interface.

**RS-006.** Acesso do suporte técnico da contratada ao ambiente do Município somente por autenticação única a partir de painel central, com credencial de uso único, validade de segundos e verificação, no ato do acesso, de que o operador de suporte está ativo e vinculado ao ente.

**RS-007.** Indicação, por usuário, de acesso ampliado a todas as caixas de trabalho e setores da unidade gestora, de modo que a visão geral seja concedida apenas a quem for expressamente designado.

### 5.2. Credenciais de integração e chaves

**RS-008.** Credencial de cada sistema integrado exibida uma única vez, no ato da emissão, e armazenada apenas sob a forma de resumo criptográfico irreversível.

**RS-009.** Isolamento por sistema de origem: cada sistema integrado consulta, altera, cancela e recupera exclusivamente os documentos que ele próprio enviou.

**RS-010.** Na assinatura com certificado digital do tipo A1, manutenção da chave privada e da senha do certificado apenas em memória durante a operação, vedada a sua gravação em qualquer meio.
