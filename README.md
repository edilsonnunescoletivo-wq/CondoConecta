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
3. Perfil público, portfólio e Fornecedor Verificado
4. Solicitação de serviço com fotos protegidas
5. Matching por categoria/região e distância
6. Painel de oportunidades do fornecedor
7. Envio e edição de propostas
8. Comparação e contratação
9. Acompanhamento da execução
10. Avaliação pós-serviço
11. Solicitações urgentes (“Preciso Agora”)
12. Histórico de contratações
13. Notificações internas de urgência
14. Base para planos pagos do contratante

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
- `003_hiring_timeline.sql` — adiciona o marco de início da execução da contratação.
- `004_geolocation_notifications.sql` — adiciona coordenadas do fornecedor e a central de notificações internas.
- `005_supplier_verification.sql` — adiciona metadados de revisão, validade e gestão do selo de fornecedor verificado.

## Perfil e verificação do fornecedor

O fornecedor pode configurar gratuitamente:

- nome público e apresentação;
- categorias de serviço;
- cidade base e raio de atendimento;
- cidades/regiões atendidas;
- participação em chamados emergenciais (“Preciso Agora”);
- portfólio de trabalhos realizados;
- localização opcional para matching por distância real;
- documentos para análise administrativa.

Os documentos aceitos nesta fase são PDF, JPG e PNG, com limite de 10 MB por arquivo. Eles ficam em `storage/supplier-documents`, fora da pasta pública, e são servidos somente por rota autenticada ao próprio fornecedor ou a um administrador.

A administração pode aprovar ou rejeitar cada documento com observação. O selo **Fornecedor Verificado** não é concedido automaticamente por upload: ele depende de análise administrativa e exige pelo menos um documento-base de identificação aprovado e ainda válido. Se essa condição deixar de existir, o selo é revogado automaticamente quando o perfil é validado pelo sistema.

O perfil público mostra apenas o selo e a informação de que a documentação-base foi revisada; os documentos brutos não são expostos aos contratantes.

Telefone e e-mail também não são exibidos diretamente no perfil público. O contato é liberado entre fornecedor e contratante somente após o aceite de uma proposta e a criação da contratação.

## Marketplace implementado

O contratante pode publicar uma solicitação com categoria, descrição, localidade, data desejada, urgência e até cinco fotos. As fotos ficam em `storage/request-photos`, fora da pasta pública, e só são servidas por uma rota autenticada.

A localização do chamado é opcional. Quando contratante e fornecedor permitem geolocalização, o CondoConecta calcula a distância entre os dois pontos e respeita o raio máximo configurado pelo fornecedor. Quando uma das coordenadas não existe, o sistema mantém o fallback por cidade/região cadastrada.

O matching considera:

- categoria selecionada pelo fornecedor;
- distância real dentro do raio configurado, quando houver coordenadas nos dois lados;
- cidade base ou cidade/região cadastrada como fallback;
- atendimento emergencial habilitado quando a solicitação for “Preciso Agora”.

O fornecedor compatível vê a oportunidade, acessa os detalhes e pode enviar ou editar gratuitamente uma proposta com valor, prazo, garantia e observações. As oportunidades são priorizadas por urgência e, dentro do mesmo nível, por proximidade quando a distância está disponível.

## Preciso Agora e notificações

Chamados `Urgente` e `Preciso Agora` geram notificações internas para fornecedores compatíveis. O fornecedor possui uma central de notificações com avisos não lidos e acesso direto à oportunidade.

Nesta fase, a notificação é interna ao sistema. Push notification e e-mail instantâneo ficam planejados para a etapa de expansão/PWA.

A coordenada do fornecedor não é exibida no perfil público e a coordenada do chamado não é mostrada diretamente aos fornecedores; ela é usada apenas para o cálculo de distância e elegibilidade.

## Contratação e confiança

O contratante recebe um comparador de propostas com valor, prazo, garantia, perfil do fornecedor e destaques de menor preço/menor prazo.

Ao aceitar uma proposta:

- a solicitação é encerrada para novas propostas;
- a proposta escolhida passa para `aceita`;
- as demais passam para `recusada`/não selecionada;
- é criado um registro único em `hirings`;
- os contatos das duas partes são liberados apenas dentro da contratação.

O fornecedor pode registrar o início do serviço. O contratante confirma a conclusão e, após isso, pode avaliar qualidade, prazo, atendimento e custo-benefício. A média dessas avaliações passa a compor o histórico público do fornecedor.

## Segurança

Nenhuma credencial deve ser versionada. Use `.env` local baseado em `.env.example`.

Senhas usam hash seguro, formulários sensíveis usam proteção CSRF, dados pessoais de contato não ficam expostos antes da contratação, fotos e documentos usam armazenamento privado com rotas autenticadas e coordenadas são usadas somente para matching interno.

## Status

MVP em desenvolvimento na branch `develop/mvp-inicial` e revisado pelo PR #1.
