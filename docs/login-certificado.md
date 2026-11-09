# Entrada com certificado digital sem plugin

O servidor entra no GPE Docs com o e-CPF (A1 instalado no computador ou A3 no token/cartão) **sem extensão
nem programa de terceiros**: o próprio navegador apresenta o certificado no HTTPS (certificado cliente).
Para o A3 basta o driver do token, que o usuário já usa para outros sistemas.

## Como funciona
1. Na tela de login do município, "Entrar com certificado digital" leva a
   `https://certificado.gpedocs.com.br/certificado/entrar?origem=paraguacu.gpedocs.com.br`.
2. Nesse endereço o servidor pede o certificado; o navegador mostra a lista e, no A3, pede o PIN do token.
   A posse da chave é provada no próprio aperto de mão do TLS.
3. O GED confere a validade, a cadeia até a AC Raiz ICP-Brasil com revogação (LCR) e procura o usuário
   pelo CPF **no município de origem**. Grava um passe de uso único (60 s) e devolve o navegador a
   `https://paraguacu.gpedocs.com.br/login/certificado/passe/{passe}`, que abre a sessão. O certificado
   dispensa o código de verificação em duas etapas.
4. Recusa (certificado vencido, fora da ICP-Brasil, sem usuário com o CPF, nenhum certificado escolhido)
   volta à tela de login com a mensagem e fica no registro de acessos.

O endereço é separado porque o navegador só pede certificado no início da conexão: pedir no endereço do
município faria a pergunta aparecer para todos, a cada acesso. O destino da volta sai do cadastro do
município, nunca do parâmetro — `origem` que não é de um município ativo dá 404.

## Configuração
### DNS e certificado do servidor
`certificado.gpedocs.com.br` apontando para o servidor do GED, com certificado TLS válido (serve o
curinga `*.gpedocs.com.br`).

### nginx do GED (TLS terminando nele)
Usar `docker/nginx/certificado.conf.exemplo`: `ssl_verify_client optional_no_ca` e o certificado repassado
ao PHP por `fastcgi_param SSL_CLIENT_CERT $ssl_client_escaped_cert`. Só a rota `/certificado/entrar`
responde nesse endereço.

### Proxy à frente terminando o TLS (Traefik, balanceador)
O proxy é que pede o certificado (`optional_no_ca` ou equivalente) e o repassa num cabeçalho, URL-encoded
como o `$ssl_client_escaped_cert`. No GED:
```
CERTIFICADO_LOGIN_CABECALHO=X-SSL-Client-Cert
TRUSTED_PROXIES=<IP do proxy>
```
O cabeçalho só é lido de proxy confiável e só no endereço do certificado. **O proxy tem de apagar esse
cabeçalho quando vier do cliente**, senão alguém poderia mandar um certificado alheio.

### .env do GED
```
CERTIFICADO_LOGIN_URL=https://certificado.gpedocs.com.br
```
Vazio desliga. Sem endereço configurado, o botão só aparece onde a extensão Web PKI funciona: em
`localhost` ou com `WEBPKI_LICENCA` (licença comprada da Lacuna). Fora disso o botão não aparece.

## Teste local
`CERTIFICADO_LOGIN_URL=https://localhost:8443` e um nginx com o exemplo acima publicado na 8443 (certificado
do servidor autoassinado: o navegador avisa uma vez). Entrar pela 8090 e escolher o e-CPF.
