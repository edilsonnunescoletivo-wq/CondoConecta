# CondoConecta

Marketplace regional de fornecedores para condomínios, síndicos e administradoras.

## Proposta

O CondoConecta conecta condomínios a fornecedores locais qualificados. O cadastro e o envio de propostas são gratuitos para fornecedores; condomínios e administradoras contratam planos para acessar recursos de cotação, comparação, histórico, gestão e contratação.

## Perfis iniciais

- Fornecedor
- Condomínio / Síndico
- Administradora
- Administrador da plataforma

## MVP

1. Cadastro e autenticação
2. Perfil do fornecedor, categorias e regiões atendidas
3. Perfil público e portfólio
4. Solicitação de orçamento
5. Envio e comparação de propostas
6. Seleção/contratação de fornecedor
7. Avaliação pós-serviço
8. Solicitações urgentes (“Preciso Agora”)
9. Histórico de contratações
10. Base para planos pagos do contratante

## Stack inicial

- PHP 8.1+
- MySQL/MariaDB
- HTML/CSS/JavaScript
- Composer / PSR-4

## Instalação do banco

1. Execute `database/schema.sql` em um banco vazio.
2. Execute, em ordem, os arquivos de `database/migrations/`.
3. Copie `.env.example` para `.env` e configure apenas no ambiente de hospedagem/local.

Migrações atuais:

- `001_supplier_portfolio.sql` — cria o portfólio público dos fornecedores.

## Perfil do fornecedor

O fornecedor pode configurar gratuitamente:

- nome público e apresentação;
- categorias de serviço;
- cidade base e raio de atendimento;
- cidades/regiões atendidas;
- participação em chamados emergenciais (“Preciso Agora”);
- portfólio de trabalhos realizados.

Telefone e e-mail não são exibidos diretamente no perfil público. O contato será tratado dentro do fluxo de solicitação/proposta.

## Segurança

Nenhuma credencial deve ser versionada. Use `.env` local baseado em `.env.example`.

Senhas usam hash seguro, formulários sensíveis usam proteção CSRF e o perfil público não expõe dados pessoais de contato nesta fase.

## Status

MVP em desenvolvimento na branch `develop/mvp-inicial` e revisado pelo PR #1.
