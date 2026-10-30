# Backup, integridade e portabilidade (TR 4.26.13–17)

Tela: **GPE Config → Estrutura → Backup e portabilidade** (`/configuracoes/portabilidade`, permissão `admin.portabilidade`).

## Backup do município

Cada backup é um `.zip` com:

| Item | Conteúdo |
|---|---|
| `banco.dump` | `pg_dump -Fc` do banco do município (formato custom do PostgreSQL) |
| `arquivos/…` | arquivos do acervo — **todos** no completo; no incremental, os novos ou alterados desde o backup anterior |
| `manifesto.json` | SHA-256 e tamanho do dump e de cada arquivo |

Agenda (`routes/console.php`, horário de Brasília):

| Quando | Comando |
|---|---|
| todo dia 02:00 | `php artisan backup:executar --tipo=incremental` |
| domingo 03:00 | `php artisan backup:executar --tipo=completo` |
| todo dia 05:00 | `php artisan backup:executar --verificar` (hash do pacote, de cada item e `pg_restore --list`) |
| sábado 04:00 | `php artisan backup:executar --acervo` (recalcula o hash de cada arquivo do acervo) |

Retenção: configurável na tela (padrão 35 dias); o último backup completo nunca é apagado.

### Destino em outro ambiente (redundância)

O disco `backup` (`config/filesystems.php`) é **local só em desenvolvimento**. Em produção, aponte para outro ambiente:

```
BACKUP_DRIVER=s3
BACKUP_BUCKET=gpedocs-backup-<municipio>
BACKUP_REGION=sa-east-1
BACKUP_KEY=...
BACKUP_SECRET=...
BACKUP_ENDPOINT=https://...   # MinIO / outro provedor
```

Use credenciais **só de escrita/leitura do bucket de backup**, em conta/região diferente da produção, com versionamento ou bloqueio de objeto (WORM) ligado no provedor.

### Requisitos do servidor

- `pg_dump`/`pg_restore` na **mesma versão do PostgreSQL** (a imagem instala `postgresql18-client` — rebuild após este bloco).
- Cron do Laravel: `* * * * * php artisan schedule:run`, rodando com o **mesmo usuário do PHP-FPM** (www-data), para a tela conseguir baixar os pacotes.

## Restauração

1. Baixe o último **completo** e os **incrementais** posteriores (tela → baixar, ou direto do bucket).
2. Banco — do pacote mais recente (o dump é sempre completo):
   ```
   unzip -p <pacote>.zip banco.dump > banco.dump
   createdb -h <host> -U postgres docs_<municipio>
   pg_restore -h <host> -U postgres -d docs_<municipio> --no-owner banco.dump
   ```
3. Arquivos — extraia `arquivos/` do completo e, na ordem, de cada incremental, para `storage/app/private/tenants/<banco>/` (ou para o bucket de documentos, no mesmo prefixo).
4. Confira: `php artisan backup:executar --tenant=<id> --acervo` (0 divergentes, 0 ausentes).

## Exportação completa (fim de contrato)

Botão **Gerar exportação** (fila, com aviso ao terminar). Pacote:

```
LEIA-ME.html                       o que é cada pasta e como validar as assinaturas
dicionario/dicionario-de-dados.*   tabelas, colunas, tipos, obrigatoriedade, chaves estrangeiras
dados/<tabela>.csv                 todas as tabelas (separador ;, UTF-8), ids preservados
arquivos/…                         arquivos originais (versões, anexos, PDFs assinados)
arquivos/indice.csv                tabela, id e coluna que apontam para cada arquivo
assinaturas/evidencias.csv         signatário, data (UTC), IP, hash do documento, arquivo assinado
manifesto.json                     SHA-256 de cada item
```

Não saem: senhas, segredos de 2FA e tokens (marcados "não exportada" no dicionário).

Assinaturas após o contrato: as **ICP-Brasil (PAdES)** validam no validador do ITI com o PDF de `arquivos/`; as **simples/por link** são provadas pelo registro em `evidencias.csv` + o SHA-256 do arquivo.

## Ambiente somente leitura (pós-contratual)

- Por município: caixa **Somente leitura** na tela (com o motivo).
- No servidor inteiro: `GED_SOMENTE_LEITURA=true` no `.env`.

Com o modo ligado, toda alteração é recusada (middleware `SomenteLeitura`); consultar, baixar e exportar continuam, e uma faixa avisa no topo de todas as telas. O sistema não tem chave de licença nem bloqueio por licença.
