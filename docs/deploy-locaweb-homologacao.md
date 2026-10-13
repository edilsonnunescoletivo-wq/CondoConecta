# Homologação do CondoConecta na Locaweb

Este roteiro prepara um ambiente isolado de homologação, sem compartilhar arquivos, banco ou credenciais com outros sistemas.

## Estrutura recomendada

Use uma pasta própria para a aplicação, por exemplo:

```text
condoconecta-homolog/
├── app/
├── config/
├── database/
├── docs/
├── public/        <- raiz web do subdomínio
├── storage/
├── .env           <- nunca versionar
├── composer.json
└── README.md
```

No painel da Locaweb, configure o subdomínio para exibir especificamente a pasta `condoconecta-homolog/public`. Dessa forma, `.env`, `storage`, `app`, `config` e `database` permanecem fora da raiz pública.

## Requisitos do PHP

O CondoConecta está preparado para PHP 8.0 ou superior.

Extensões necessárias/recomendadas:

- PDO e pdo_mysql;
- cURL;
- mbstring;
- fileinfo;
- openssl;
- json;
- session.

Confirme a versão do PHP pelo painel da hospedagem antes da primeira execução.

## Banco de homologação

Crie um banco MySQL exclusivo para o CondoConecta. Não reutilize bancos de outros sistemas.

Depois de criado, execute:

1. `database/schema.sql`
2. `database/migrations/001_supplier_portfolio.sql`
3. `database/migrations/002_service_request_photos.sql`
4. `database/migrations/003_hiring_timeline.sql`
5. `database/migrations/004_geolocation_notifications.sql`
6. `database/migrations/005_supplier_verification.sql`
7. `database/migrations/006_password_reset_portfolio_upload.sql`
8. `database/migrations/007_terms_monetization.sql`

## Arquivo .env da homologação

Crie o `.env` apenas no servidor, usando valores da própria homologação:

```env
APP_NAME=CondoConecta Homologação
APP_ENV=homologation
APP_DEBUG=false
APP_URL=https://SEU-SUBDOMINIO-DE-HOMOLOGACAO

DB_HOST=HOST_MYSQL_LOCAWEB
DB_PORT=3306
DB_DATABASE=BANCO_HOMOLOGACAO
DB_USERNAME=USUARIO_BANCO
DB_PASSWORD=SENHA_BANCO

SESSION_NAME=condoconecta_homolog_session

MAIL_DRIVER=mail
MAIL_FROM_ADDRESS=no-reply@SEU-DOMINIO
MAIL_FROM_NAME=CondoConecta Homologação

TERMS_AUDIT_SECRET=GERAR_SEGREDO_FORTE_EXCLUSIVO

MP_ACCESS_TOKEN=
MP_WEBHOOK_SECRET=
```

Não usar credenciais de produção na homologação. Para Mercado Pago, usar credenciais de teste/homologação até a validação final.

## Permissões de armazenamento

O processo PHP precisa conseguir gravar em:

- `storage/request-photos`
- `storage/portfolio-images`
- `storage/supplier-documents`
- `storage/logs`

Evite permissões globais 777 se não forem necessárias. Use a permissão mínima aceita pela hospedagem.

## Webhook Mercado Pago

Após o subdomínio possuir HTTPS válido, o endpoint de homologação será:

```text
https://SEU-SUBDOMINIO-DE-HOMOLOGACAO/mercadopago-webhook.php
```

O webhook só deve atualizar assinatura depois da validação da assinatura HMAC e da confirmação do status na API do Mercado Pago.

## Testes mínimos antes de qualquer produção

1. Abrir a landing page sem erro 500.
2. Criar fornecedor e contratante de teste.
3. Validar aceite dos Termos de Uso.
4. Validar login, logout e recuperação de senha.
5. Enviar foto de solicitação, foto de portfólio e documento do fornecedor.
6. Aprovar documento pelo administrador e validar o selo público.
7. Criar solicitação normal e `Preciso Agora`.
8. Confirmar matching por categoria/região e, quando permitido, distância.
9. Enviar, comparar e aceitar proposta.
10. Iniciar e concluir contratação e registrar avaliação.
11. Validar teste de 14 dias e limites dos planos.
12. Validar checkout Mercado Pago com ambiente de teste.
13. Validar webhook, ativação, inadimplência e bloqueio de novas solicitações.
14. Confirmar que nenhuma pasta privada ou `.env` pode ser acessada pela web.

## Regra de segurança

A homologação é um ambiente separado. Não usar o banco `gestaosindicos`, `condohomolog26`, `restaura26` ou qualquer banco de outro projeto. O CondoConecta deve possuir seu próprio banco e suas próprias credenciais.
