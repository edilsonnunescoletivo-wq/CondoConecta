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
4. Solicitação de serviço com fotos protegidas
5. Matching por categoria/região
6. Painel de oportunidades do fornecedor
7. Envio e edição de propostas
8. Comparação e contratação
9. Avaliação pós-serviço
10. Solicitações urgentes (“Preciso Agora”)
11. Histórico de contratações
12. Base para planos pagos do contratante

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
- `002_service_request_photos.sql` — adiciona fotos protegidas às solicitações de serviço.

## Perfil do fornecedor

O fornecedor pode configurar gratuitamente:

- nome público e apresentação;
- categorias de serviço;
- cidade base e raio de atendimento;
- cidades/regiões atendidas;
- participação em chamados emergenciais (“Preciso Agora”);
- portfólio de trabalhos realizados.

Telefone e e-mail não são exibidos diretamente no perfil público. O contato é tratado dentro do fluxo de solicitação/proposta.

## Marketplace já implementado

O contratante pode publicar uma solicitação com categoria, descrição, localidade, data desejada, urgência e até cinco fotos. As fotos ficam em `storage/request-photos`, fora da pasta pública, e só são servidas por uma rota autenticada.

O matching inicial usa:

- categoria selecionada pelo fornecedor;
- cidade base ou cidade/região cadastrada pelo fornecedor;
- atendimento emergencial habilitado quando a solicitação for “Preciso Agora”.

O fornecedor compatível vê a oportunidade, acessa os detalhes e pode enviar ou editar gratuitamente uma proposta com valor, prazo, garantia e observações.

A geolocalização por distância real e o uso efetivo do raio em quilômetros serão implementados na fase específica de geolocalização.

## Segurança

Nenhuma credencial deve ser versionada. Use `.env` local baseado em `.env.example`.

Senhas usam hash seguro, formulários sensíveis usam proteção CSRF, dados pessoais de contato não ficam expostos no perfil público e fotos de solicitações exigem autorização para visualização.

## Status

MVP em desenvolvimento na branch `develop/mvp-inicial` e revisado pelo PR #1.
