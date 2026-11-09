# GPE Assinador — assinatura A3 sem extensão de terceiros

Assinar com token ou cartão (A3) exige um programa no computador do usuário, porque a página não acessa o
token sozinha. Em vez do Web PKI da Lacuna (licença paga fora de `localhost`), o GED usa o **GPE Assinador**,
programa nosso: repositório `conceitogestaopublica/gpe-assinador` (privado).

## Para o usuário
1. Na assinatura, escolher **A3 (token)**. Sem o programa, o GED mostra **Baixar o GPE Assinador**.
2. Abrir o arquivo baixado. Ele se instala sozinho, sem pedir administrador, em
   `%LOCALAPPDATA%\Programs\GPE Assinador`, cria o atalho no menu Iniciar, passa a abrir com o Windows e
   aparece em "Aplicativos instalados", de onde se desinstala.
3. Voltar ao GED e clicar em **Tentar de novo**. A cada assinatura, o programa mostra o site, o documento e o
   certificado e pede confirmação; depois o token pede o PIN.

Enquanto o programa não tiver certificado de assinatura de código, o Windows avisa "O Windows protegeu o
computador": **Mais informações › Executar assim mesmo**.

## Para o servidor (publicar o download)
O GED serve o executável em `/assinador/baixar` quando o arquivo existe em
`storage/app/assinador/GPE Assinador.exe`:
```
docker cp "GPE Assinador.exe" <container-app>:/var/www/html/storage/app/assinador/
docker exec <container-app> chown -R www-data:www-data storage/app/assinador
```
O executável sai do `dotnet publish` do repositório `gpe-assinador` (ver o README de lá). Para usar outro
endereço de download, `ASSINADOR_DOWNLOAD_URL` no `.env`. Uma versão nova é só trocar o arquivo: ao abrir,
o programa atualiza a cópia instalada.

## Funcionamento técnico
Serviço local em `http://127.0.0.1:47231`, atendendo só `https://*.gpedocs.com.br` e `http://localhost:8090`:
lista os certificados do Windows, entrega o certificado e as ACs intermediárias (vão em `cadeia_pem` no
`finalizar-icp-a3`) e assina o resumo preparado pelo servidor. O documento não passa pelo programa e a chave
não sai do token. No navegador: `resources/js/Support/assinadorLocal.js`.
