# NewDOC

Aplicação web em português para cadastro e busca de empresas, autenticação, acervo de documentos, auditoria e gestão Newsic/IPBX e Newbot. O núcleo foi implementado em PHP com arquitetura MVC pequena, SQLite e bibliotecas nativas.

A pasta de trabalho `C:\NewtelSistema` contém somente este projeto, em `newdoc\`. Os arquivos antigos foram preservados, sem importação ou mesclagem de dados, em `C:\NewtelSistema_arquivo_20261116`, fora da pasta de trabalho. O NewDOC não depende desse arquivo externo.

Esta execução não tinha PHP, Composer ou Node instalados. Por isso, conforme a alternativa prevista na especificação, a Fase 1 usa MVC próprio sem dependências de terceiros, mantendo PSR-4, rotas centralizadas e migrations; o projeto requer PHP 8.3 e inclui configuração Composer para autoload futuro.

## Requisitos

- PHP 8.3 ou superior.
- Extensões PHP `pdo_sqlite`, `sodium` e `fileinfo`.
- Composer é opcional: o projeto não possui dependências externas de runtime e inclui autoloader PSR-4 simples.

PostgreSQL é a opção recomendada para uma futura implantação multiusuário; esta Fase 1 usa SQLite e migrations SQL específicas de SQLite.

## Instalação local (Windows / PowerShell)

No PowerShell, entre na pasta do projeto:

```powershell
Set-Location C:\NewtelSistema\newdoc
Copy-Item .env.example .env
```

Edite `.env` e gere a chave de criptografia para credenciais e documentos:

```powershell
$key = php bin/generate-credentials-key.php
```

Guarde `$key` em um cofre de segredos e configure `CREDENTIALS_KEY` em `.env`. A chave deve ser Base64 de 32 bytes. Não a coloque no banco, no código, em backups do banco ou no Git. Perder ou trocar a chave impede a leitura dos dados criptografados. Para produção, injete-a pelo gerenciador de segredos do sistema operacional, em vez de manter um `.env` acessível.

Exemplo de `.env` local:

```dotenv
APP_ENV=local
APP_DEBUG=false
APP_TIMEZONE=America/Sao_Paulo
DB_PATH=storage/database/newdoc.sqlite
SESSION_SECURE_COOKIE=false
MAX_UPLOAD_BYTES=20971520
CREDENTIALS_KEY=COLE_A_CHAVE_GERADA_AQUI
```

Crie o banco e aplique as migrations:

```powershell
php bin/migrate.php
```

Crie o primeiro usuário administrador. Informe a senha em um prompt seguro do PowerShell; ela não é passada como argumento nem exibida:

```powershell
$secure = Read-Host "Senha inicial do administrador" -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
    $env:NEWDOC_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    php bin/create-admin.php --name="Administrador Newtel" --email="admin@newtel.com.br"
} finally {
    Remove-Item Env:\NEWDOC_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
}
```

A senha deve ter entre 6 e 12 caracteres. São aceitos letras, números, os caracteres especiais `! @ # $ % ^ & * ( ) _ - + = { } [ ] : ; , . ? / ~` e espaços internos; espaços no início ou no fim não são aceitos. Não é obrigatório combinar tipos de caracteres. A regra também é validada no login. O comando cria apenas o primeiro administrador; usuários adicionais e gestão de perfis pela interface ainda não fazem parte desta fase.

Inicie o servidor local:

```powershell
php -S 127.0.0.1:8000 -t public public/router.php
```

Abra <http://127.0.0.1:8000>. O servidor embutido do PHP é somente para desenvolvimento; produção deve usar um servidor web configurado com HTTPS, `public/` como raiz pública e cookies de sessão seguros.

## Configuração

| Variável | Finalidade |
|---|---|
| `APP_ENV` | Ambiente (`local` ou `production`). |
| `APP_DEBUG` | Reservado para diagnóstico futuro; erros HTTP não exibem detalhes internos. |
| `APP_TIMEZONE` | Fuso horário para exibição e auditoria. |
| `DB_PATH` | Caminho SQLite relativo à pasta do projeto ou absoluto. |
| `SESSION_SECURE_COOKIE` | Use `true` sob HTTPS. |
| `MAX_UPLOAD_BYTES` | Limite de upload; padrão 20 MiB. |
| `CREDENTIALS_KEY` | Chave Base64 externa para Sodium; obrigatória para criptografia e registro de documentos. |

O SQLite é criado em `storage/database/`, fora da raiz pública. Arquivos de documentos ficam em `storage/documents/`, também fora da raiz pública, e são criptografados com Sodium antes de serem persistidos. A aplicação não oferece rota para exibir ou baixar o conteúdo original. O hash SHA-256 é calculado antes da criptografia. Nenhuma planilha é lida ou importada automaticamente.

## Migrations, testes e backup

```powershell
php bin/migrate.php
php tests/run.php
php bin/backup.php
```

O backup usa `VACUUM INTO` para o SQLite e cria uma pasta lateral com cópias dos documentos já criptografados, ambos em `storage/backups/`. Proteja os dois artefatos e mantenha uma cópia segura de `CREDENTIALS_KEY` separadamente: nem a chave nem o conteúdo em texto puro entram no backup. Teste restauração em ambiente isolado antes de usar em produção. A política externa de retenção e cópias 3-2-1 deve ser definida pela Newtel.

Os testes de `tests/run.php` usam um banco SQLite temporário e uma chave aleatória, sem tocar no banco real. Eles cobrem migrations, homônimos, política de senha, criptografia, auditoria sensível, CSRF e as regras de schema do Newsic e do Newbot. As telas não têm testes automatizados e devem ser verificadas manualmente.

## Controles implementados

- `password_hash()` / `password_verify()`, sessão regenerada no login, expiração após 30 minutos de inatividade, cookies `HttpOnly` e `SameSite`, logout por POST e token CSRF.
- Cinco falhas por combinação de e-mail/IP ou 20 falhas por IP em 15 minutos acionam bloqueio temporário; erros de autenticação são genéricos.
- Roles `admin`, `technician` e `viewer`: os dois primeiros podem criar/editar empresas e registrar/atualizar documentos; `viewer` consulta dados. Auditoria fica restrita a quem pode gerenciar.
- Consultas PDO preparadas, validação de dados no servidor, escape HTML, headers CSP e ausência de valores de senha nos logs.
- Homônimos são permitidos; antes de criar ou renomear para um nome existente, a interface exige confirmação explícita. O ID interno permanece a referência.
- Upload restrito a XLSX, PDF, DOCX, ODT e CSV, com validação de MIME/extensão, tamanho máximo, SHA-256 e detecção de duplicidade. O estado inicial é `pendente`, sem associação automática a empresa ou vigência.
- A auditoria registra ação, usuário, registro, campo, data e IP, mas não grava valores anteriores ou novos. Campos sensíveis permanecem sem valores no log.
- A classe `NewDoc\CredentialCipher` oferece criptografia autenticada com `sodium_crypto_secretbox`; a chave obrigatória vem exclusivamente de `CREDENTIALS_KEY`.

## Fase 2 — Newsic / IPBX

A migration `002_newsic_schema.sql` cria tabelas para instâncias, ramais, troncos, opções de URA, direcionamentos e redes. Aplique-a junto das demais migrations com `php bin/migrate.php` antes de usar as telas Newsic.

- Instâncias podem ser consultadas por empresa ou na busca global; uma empresa pode manter uma instância ativa e registros inativos como histórico.
- Técnicos e administradores podem cadastrar e editar instâncias, ramais, troncos, URA, direcionamentos e dados de rede. A validação acontece no servidor e a auditoria registra as alterações sem valores sensíveis.
- Usuários `viewer` consultam apenas os dados gerais e contagens de registros. Dados de acesso e linhas técnicas não são carregados na consulta de detalhes para esse perfil.
- Senhas de acesso do Newsic são criptografadas com `CREDENTIALS_KEY`, nunca são exibidas, e podem ser mantidas, substituídas ou removidas sem revelar o valor atual.
- Um documento Newsic pode ser associado manualmente como origem. O sistema não lê nem importa dados dos documentos.

## Fase 3 — Newbot

A migration `003_newbot_schema.sql` cria tabelas para instâncias, canais, administradores, responsáveis, autenticações, integrações e atendentes. Em um banco com dados, faça `php bin/backup.php` antes de aplicá-la com `php bin/migrate.php`; a migration apenas acrescenta tabelas.

- As telas ficam em `/newbot` (lista e busca) e em `/empresas/{id}/newbot` (instâncias da empresa), com atalhos no menu lateral e na ficha da empresa.
- Uma empresa pode ter no máximo uma instância Newbot ativa, garantido por índice único no banco; instâncias inativas ou em manutenção ficam como histórico.
- Técnicos e administradores cadastram e editam instâncias e os itens de canais, administradores, responsáveis, autenticações, integrações e atendentes. A validação ocorre no servidor.
- Senhas de administradores e integrações são criptografadas com `CREDENTIALS_KEY`, preservadas exatamente como digitadas, nunca exibidas, e podem ser mantidas, substituídas ou removidas sem revelar o valor atual. A auditoria registra a alteração sem valores.
- Usuários `viewer` veem os dados gerais e a contagem de itens, sem os registros técnicos.
- Um documento do tipo Newbot pode ser associado manualmente como origem. O sistema não lê nem importa dados dos documentos.

## Estrutura

```text
app/                    Configuração, segurança, persistência, controladores
bin/                    Migrations, administrador inicial, chave e backup
database/migrations/    Evolução versionada do SQLite
public/                 Front controller e assets estáticos; raiz web
resources/views/        Templates PHP separados da lógica
storage/                SQLite, documentos criptografados e backups; fora da web
tests/                  Testes focados, sem dependências externas
```

## Ainda não implementado

- Módulos de serviço Newvoz e patrimônio.
- Integrações automáticas com plataformas externas: o Newbot guarda apenas os dados cadastrais.
- Importação assistida, leitura de abas, mapeamento manual na interface e aprovação de dados extraídos.
- Download/descriptografia dos originais pela interface.
- Gestão de usuários, recuperação de senha, permissões granulares e fluxo de redefinição.
- Filtros avançados de auditoria por usuário/período, relatórios e backup agendado.
- PostgreSQL, Vite, Tailwind/Alpine e pipeline de build. O layout desta fase usa CSS e JavaScript locais sem dependências externas.

Antes de habilitar a operação multiusuário em produção, configure HTTPS, armazenamento seguro da chave, permissões de arquivos, política de retenção e backup testado; revise os perfis de acesso com a Newtel.

O recebimento multipart usa temporariamente o diretório de upload configurado pelo PHP antes da criptografia em disco. Em produção, configure `upload_tmp_dir` com acesso restrito e armazenamento protegido; ajuste também `upload_max_filesize` e `post_max_size` para valores compatíveis com `MAX_UPLOAD_BYTES`.
