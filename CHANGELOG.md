# Changelog

## Em desenvolvimento

- Módulo Newsic/IPBX com migration de instâncias, ramais, troncos, URA, direcionamentos e redes.
- Busca e consulta de instâncias por empresa, operações de cadastro e edição para técnicos e administradores.
- Criptografia de credenciais Newsic, auditoria sem valores sensíveis e consulta minimizada para usuários somente leitura.
- Testes focados de unicidade e armazenamento criptografado de credenciais.
- Correção do bootstrap e da liberação de recursos SQLite no teste integrado, evitando saída antes da sessão e arquivos temporários bloqueados.
- Módulo Newbot (Fase 3) com migration `003_newbot_schema.sql`: instâncias, canais, administradores, responsáveis, autenticações, integrações e atendentes, com no máximo uma instância ativa por empresa.
- Rotas `/newbot` registradas, item Newbot no menu lateral e link na ficha da empresa.
- Senhas de administradores e integrações do Newbot criptografadas, nunca exibidas, e auditoria sem valores sensíveis.
- Testes do Newbot em `tests/run.php`: unicidade da instância ativa, histórico permitido, status inválido, credencial preservada exatamente e auditoria sensível.

## 1.0.0 - 2026-11-16

- Estrutura MVC PHP 8.3 com rotas web e migrations SQLite.
- Autenticação por sessão, hash de senha, CSRF e limitação de tentativas.
- Cadastro, edição, listagem, busca e filtros de empresas, permitindo homônimos.
- Acervo de documentos com status manual, hash SHA-256 e armazenamento criptografado.
- Mapeamento de campos e auditoria preparados no schema; sem importação automática.
- Criptografia de credenciais com Sodium e chave externa ao banco e ao código.
- Layout responsivo em português, documentação e backup SQLite.
