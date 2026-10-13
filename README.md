# CondoConecta

Marketplace regional de fornecedores para condomínios, síndicos e administradoras.

## Proposta

O CondoConecta conecta contratantes a fornecedores locais. O cadastro e o envio de propostas são gratuitos para fornecedores; condomínios, síndicos, empresas e administradoras utilizam período de teste e planos pagos para publicar solicitações e organizar cotações, contratações e histórico.

O CondoConecta atua como plataforma de intermediação tecnológica. O contrato de prestação de serviços é celebrado diretamente entre contratante e prestador.

## Perfis

- Fornecedor
- Condomínio / Síndico / Empresa
- Administradora
- Administrador da plataforma

## MVP implementado

1. Cadastro, autenticação e recuperação de senha
2. Termos de Uso versionados e aceite obrigatório
3. Perfil do fornecedor, categorias e regiões atendidas
4. Perfil público, portfólio e Fornecedor Verificado
5. Solicitação de serviço com fotos protegidas
6. Matching por categoria/região e distância
7. Painel de oportunidades do fornecedor
8. Envio e edição de propostas
9. Comparação e contratação
10. Acompanhamento da execução
11. Avaliação pós-serviço
12. Solicitações urgentes (“Preciso Agora”)
13. Histórico de contratações
14. Notificações internas de urgência
15. Planos, trial, limites de uso e assinatura recorrente
16. Integração de assinatura com Mercado Pago e webhook autenticado

## Stack inicial

- PHP 8.0+
- MySQL/MariaDB
- HTML/CSS/JavaScript
- Composer / PSR-4
- Mercado Pago API de Assinaturas

## Instalação do banco

1. Execute `database/schema.sql` em um banco vazio.
2. Execute, em ordem, os arquivos de `database/migrations/`.
3. Copie `.env.example` para `.env` e configure apenas no ambiente de hospedagem/local.

Migrações atuais:

- `001_supplier_portfolio.sql` — portfólio público dos fornecedores.
- `002_service_request_photos.sql` — fotos protegidas das solicitações.
- `003_hiring_timeline.sql` — início da execução da contratação.
- `004_geolocation_notifications.sql` — coordenadas e notificações internas.
- `005_supplier_verification.sql` — revisão documental e selo de fornecedor verificado.
- `006_password_reset_portfolio_upload.sql` — recuperação de senha e upload do portfólio.
- `007_terms_monetization.sql` — aceite versionado dos termos, planos, assinatura e pagamentos.

Para implantação de homologação na Locaweb, siga `docs/deploy-locaweb-homologacao.md`.

## Termos de Uso e responsabilidades

A versão atual dos Termos está em `public/termos.php` e é controlada por `App\Core\Terms::VERSION`.

Contas que ainda não aceitaram a versão vigente são redirecionadas para `aceitar-termos.php`. O sistema grava usuário, versão, data, origem do aceite e hashes técnicos mínimos para auditoria. O endereço IP puro não é armazenado pela tabela de aceite.

Os Termos deixam expresso que:

- prestador e contratante celebram e executam a contratação diretamente;
- as partes são responsáveis por conferir escopo, preço, prazo, garantia, documentos, capacidade técnica e dados fornecidos;
- prestador e contratante são responsáveis pelas obrigações fiscais, previdenciárias, contábeis e acessórias relacionadas ao serviço;
- as partes devem conferir emissão de nota fiscal, incidência, retenções, recolhimentos e repasses de ISS, INSS, IRRF, PIS, Cofins, CSLL e demais tributos quando aplicáveis;
- o CondoConecta não presta consultoria tributária e não assume retenções/recolhimentos dos serviços contratados entre usuários;
- o selo Fornecedor Verificado não equivale a garantia técnica ou comercial;
- o CondoConecta mantém suas responsabilidades próprias como fornecedor da plataforma e agente de tratamento de dados, inclusive segurança, controle de acesso e proteção de dados conforme a legislação aplicável;
- nenhuma cláusula pretende afastar obrigações legais indisponíveis da própria plataforma.

Antes do lançamento comercial, os Termos devem passar por revisão jurídica e o modelo tributário da assinatura da plataforma deve passar por revisão contábil.

## Monetização

O fornecedor permanece gratuito para cadastro, documentos, perfil, portfólio, recebimento de oportunidades e envio de propostas.

Planos atuais para contratantes:

- Básico — R$ 39,90/mês — limite inicial de 5 novas solicitações/mês;
- Profissional — R$ 79,90/mês — limite inicial de 30 novas solicitações/mês;
- Premium — R$ 129,90/mês — solicitações sem limite mensal configurado;
- Administradora — R$ 299,90/mês — plano específico para administradoras, sem limite mensal configurado nesta fase.

Os valores e limites são dados de banco e podem ser alterados sem modificar a lógica da aplicação.

Novas contas elegíveis recebem 14 dias de teste. O controle comercial reconhece os estados `trial`, `ativa`, `inadimplente` e `cancelada`. A criação de novas solicitações é bloqueada quando o teste termina ou a assinatura perde acesso, ou quando o limite mensal do plano é atingido.

## Mercado Pago

A integração usa a API de Assinaturas (`/preapproval`). O sistema cria uma assinatura pendente e redireciona o contratante ao `init_point` retornado pelo Mercado Pago.

A ativação local não depende do retorno do navegador. O endpoint `mp-webhook.php` valida `x-signature` com HMAC-SHA256 usando `MP_WEBHOOK_SECRET`, consulta a assinatura novamente na API e só depois sincroniza o estado local.

Configure no `.env`:

- `MP_ACCESS_TOKEN`
- `MP_WEBHOOK_SECRET`
- `APP_URL`

Nunca versione credenciais reais.

## Recuperação de senha

A tela de login possui o fluxo **Esqueci minha senha**. Quando existe uma conta ativa, o sistema cria um token aleatório, guarda apenas seu hash no banco e envia um link com validade de 30 minutos. O token é de uso único e uma nova solicitação invalida links anteriores ainda não usados.

Para reduzir enumeração de contas, a tela retorna a mesma confirmação independentemente de o e-mail existir.

Configure no `.env`:

- `APP_URL`
- `MAIL_DRIVER=mail` na hospedagem ou `MAIL_DRIVER=log` em desenvolvimento
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

## Perfil, portfólio e verificação do fornecedor

O fornecedor pode configurar gratuitamente categorias, regiões, raio de atendimento, atendimento emergencial, portfólio, localização opcional e documentos para análise.

O portfólio aceita JPG, PNG e WebP com até 5 MB. Arquivos ficam em `storage/portfolio-images`, fora da pasta pública. Documentos de verificação ficam em `storage/supplier-documents` e só podem ser acessados pelo fornecedor proprietário ou por administrador autenticado.

O selo **Fornecedor Verificado** depende de análise administrativa e de documento-base aprovado e válido. Ele não é garantia de qualidade, solvência ou execução técnica.

## Marketplace, proximidade e contratação

Solicitações podem conter categoria, descrição, localidade, data desejada, urgência e até cinco fotos protegidas. O matching considera categoria, distância real dentro do raio quando houver coordenadas e cidade/região como fallback.

O contratante compara propostas, aceita uma delas e o sistema encerra as concorrentes. É criado um registro de contratação e somente então os contatos entre as partes são liberados. O fornecedor registra início do serviço; o contratante confirma conclusão e pode avaliar qualidade, prazo, atendimento e custo-benefício.

## Segurança

Nenhuma credencial deve ser versionada. Use `.env` local baseado em `.env.example`.

Senhas usam hash seguro, formulários sensíveis usam CSRF, tokens de recuperação são armazenados somente em hash, arquivos privados ficam fora da pasta pública, documentos têm autorização de acesso e coordenadas são usadas somente para matching interno.

## Status

MVP em desenvolvimento na branch `develop/mvp-inicial` e revisado pelo PR #1. Nenhuma integração de pagamento deve ser considerada pronta para produção antes de testes em homologação com credenciais próprias do projeto.
