### 6.8. Assinatura eletrônica e digital

#### 6.8.1. Modalidades

**RF-100.** Assinatura eletrônica **simples**, na forma do art. 4º, inciso I, da Lei nº 14.063/2020, com aceite declaratório do conteúdo e registro do CPF informado, do endereço IP, do navegador utilizado, da data e hora e do resumo criptográfico SHA-256 da versão assinada.

**RF-101.** Assinatura eletrônica **qualificada**, na forma do art. 4º, inciso III, da Lei nº 14.063/2020, com certificado digital ICP-Brasil do tipo A1, observado o requisito RS-010.

**RF-102.** Conferência, no ato da assinatura qualificada, da correspondência entre o CPF do titular do certificado e o CPF cadastrado do usuário signatário.

#### 6.8.2. Forma da assinatura no documento

**RF-105.** Assinatura digital **incorporada ao próprio arquivo PDF**, em envelope criptográfico destacado com algoritmo de resumo SHA-256, verificável em leitor de PDF de mercado sem dependência da solução contratada.

**RF-106.** Aposição, no PDF assinado com certificado, de representação visual da assinatura com nome do signatário, data e hora e endereço de verificação, acompanhada de tarja lateral indicativa em todas as páginas.

#### 6.8.3. Solicitação de assinatura

**RF-110.** Solicitação de assinatura de um documento a um ou mais signatários usuários do sistema, com mensagem e indicação de prazo.

**RF-111.** Solicitação de assinatura **em lote**: vários documentos selecionados enviados, em uma única operação, aos mesmos signatários.

**RF-112.** Notificação interna ao signatário sobre assinatura pendente e ao solicitante sobre assinatura realizada ou recusada.

**RF-113.** Painel do signatário com os documentos pendentes de sua assinatura, os que aguardam os demais signatários e os concluídos, com filtros por texto, CPF, modalidade de assinatura, período e sistema de origem.

#### 6.8.4. Evidências e verificação

**RF-120.** Registro, por assinatura, das evidências do ato: signatário, CPF, endereço IP, navegador, data e hora, resumo criptográfico do documento e, na assinatura com certificado, titular, autoridade certificadora emissora, número de série, impressão digital do certificado e resumo criptográfico do envelope de assinatura.

**RF-121.** Emissão de **manifesto de assinaturas** em PDF, por solicitação, com os dados do documento, a relação dos signatários, a modalidade de cada assinatura, as evidências previstas no requisito RF-120 e a base legal aplicável.

**RF-122.** Página pública, acessível sem autenticação, para validação de PDF assinado enviado pelo interessado, com a relação de todas as assinaturas presentes no arquivo, a verificação de integridade criptográfica de cada uma e a exibição do signatário, do CPF ou CNPJ, da autoridade certificadora emissora, do número de série, do período de validade do certificado, do algoritmo, do motivo, do local e da data declarada.

#### 6.8.5. Certificados

**RF-125.** Cadastro, pelo próprio usuário, dos seus certificados digitais ICP-Brasil, com guarda exclusiva da parte pública do certificado, verificação do período de validade e da correspondência de CPF no cadastramento, listagem com situação, data de expiração, dias restantes e número de assinaturas realizadas, e inativação e reativação pelo titular.
