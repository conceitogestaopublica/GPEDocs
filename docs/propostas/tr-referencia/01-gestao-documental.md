## 6. DOS REQUISITOS FUNCIONAIS

6.0.1. Os requisitos deste item descrevem **funções**, não produtos. Serão considerados atendidos por qualquer solução que os execute, independentemente de nomenclatura de tela, de módulo ou de tecnologia empregada.

6.0.2. Todo documento digital incorporado ao repositório, qualquer que seja a forma de entrada, observará o requisito RF-031.

---

### 6.1. Estrutura de arquivamento

**RF-001.** Organização do acervo em estrutura hierárquica de pastas e subpastas, sem limite de níveis, com navegação em árvore e indicação do caminho completo da pasta corrente.

**RF-002.** Criação, renomeação e exclusão de pastas, com atualização automática do caminho das subpastas na renomeação e bloqueio da exclusão de pasta que contenha subpastas ou documentos.

**RF-003.** Inativação de pasta, com propagação da inativação às subpastas e preservação dos documentos nelas contidos.

**RF-004.** Movimentação de documentos entre pastas, individualmente ou em lote, com validação de que a pasta de destino pertence à mesma unidade gestora do documento.

### 6.2. Captura e incorporação de documentos

**RF-010.** Incorporação de documentos por envio de múltiplos arquivos em uma única operação, inclusive por arrastar e soltar, com aplicação ao lote do tipo documental, da pasta de destino, da descrição e dos metadados, admitidos arquivos de até 50 MB cada.

**RF-011.** Digitalização de documentos pela câmera do próprio dispositivo, com captura de múltiplas páginas, reordenação e exclusão de páginas antes do arquivamento.

**RF-012.** Arquivamento no repositório, por ação do usuário e com escolha da pasta, dos documentos produzidos nos módulos de processo e de comunicações oficiais, com geração do documento em PDF, registro de versão e indexação do seu conteúdo textual para pesquisa.

**RF-013.** Classificação e arquivamento em lote, em pasta e tipo documental, de documentos já assinados, permitido somente ao autor ou aos signatários de cada documento.

### 6.3. Tipos documentais e metadados

**RF-020.** Cadastro de tipos documentais, com nome, descrição, ativação e inativação, oferecendo-se na incorporação de documentos apenas os tipos ativos.

**RF-021.** Definição, para cada tipo documental, de **esquema próprio de metadados**, com campos de texto, número, data e lista de opções, rótulo e ordem de apresentação, exibido como formulário dinâmico no momento da incorporação do documento.

### 6.4. Versões e integridade

**RF-030.** Preservação conjunta do documento original e da sua versão assinada digitalmente, com indicação da versão exibida e alternância entre a versão assinada e a original, tanto na visualização quanto no download.

**RF-031.** Cálculo e armazenamento do **resumo criptográfico SHA-256** de cada versão de documento, em todas as formas de entrada: envio manual, digitalização, interface de integração e arquivamento de documentos produzidos nos demais módulos.

### 6.5. Pesquisa e recuperação

**RF-040.** Pesquisa no **conteúdo integral** de documentos PDF que contenham camada de texto, com extração automática do texto na incorporação e reprocessamento do acervo já existente sob demanda.

**RF-041.** Pesquisa no repositório por termo livre aplicado ao nome, à descrição, ao conteúdo textual e aos metadados (nome e valor do campo), combinável com filtros de tipo documental, situação e período de criação, restrita à pasta corrente ou estendida a todo o acervo da unidade gestora.

**RF-042.** Exportação, em planilha eletrônica, da listagem de documentos exibida em tela, acompanhada dos metadados.

### 6.6. Visualização e acesso

**RF-050.** Visualização de documentos PDF e de imagens no próprio sistema, sem necessidade de download, com orientação para o download nos demais formatos.

**RF-051.** Download do documento, vedado para os documentos cancelados pelo sistema de origem.

**RF-052.** Marcação de documentos como favoritos, por usuário, com lista própria de favoritos.

### 6.7. Ciclo de vida

**RF-060.** Controle da situação do documento — rascunho, em revisão, publicado e arquivado —, com alteração individual ou em lote.

**RF-061.** Exclusão lógica de documentos, individualmente ou em lote, com preservação do arquivo digital e do registro na base de dados.
