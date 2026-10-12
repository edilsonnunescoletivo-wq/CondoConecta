# Regras de negócio — MVP CondoConecta

## 1. Modelo comercial

### Fornecedor
- Cadastro gratuito.
- Perfil gratuito.
- Pode selecionar categorias e regiões atendidas.
- Pode receber oportunidades compatíveis.
- Pode enviar e editar propostas sem pagar por lead.
- Não paga comissão obrigatória no MVP.

### Contratante
Perfis: condomínio, administradora, síndico profissional e empresa.

O cadastro existe desde o MVP. A cobrança será aplicada aos recursos avançados quando a etapa de monetização for ativada.

## 2. Matching de oportunidades

Uma solicitação é elegível para um fornecedor quando:

1. a categoria da solicitação está selecionada no perfil do fornecedor;
2. a cidade/UF da solicitação coincide com a cidade base ou uma região cadastrada pelo fornecedor;
3. a solicitação está `aberta` ou `em_cotacao`;
4. para urgência `agora`, o fornecedor precisa ter `emergency_service = 1`.

Nesta fase, `service_radius_km` fica armazenado no perfil, mas não é usado para calcular distância real. O raio será aplicado quando houver geolocalização confiável.

## 3. Solicitações

O contratante pode informar:

- categoria;
- título e descrição;
- urgência `normal`, `urgente` ou `agora`;
- cidade e UF;
- referência do local;
- data desejada;
- até cinco fotos JPG, PNG ou WebP, com até 5 MB cada.

As fotos ficam fora da pasta pública e só podem ser visualizadas pelo contratante dono da solicitação, administrador ou fornecedor elegível/com proposta vinculada.

## 4. Propostas

O fornecedor elegível pode enviar:

- valor total;
- prazo estimado em dias;
- garantia em dias;
- detalhes de escopo e condições.

Uma proposta por fornecedor é permitida em cada solicitação. Enquanto a solicitação estiver aberta/em cotação, o fornecedor pode editar a própria proposta.

Ao receber a primeira proposta, a solicitação passa de `aberta` para `em_cotacao`.

## 5. Contratação

Quando uma proposta for aceita na próxima fase:
- a solicitação passará para `contratada`;
- as demais propostas continuarão registradas no histórico;
- será criado um registro em `hirings`;
- fornecedor e contratante terão o vínculo da contratação registrado.

## 6. Avaliações

Somente uma contratação concluída pela plataforma poderá gerar avaliação pública do fornecedor.

Critérios iniciais:
- qualidade;
- cumprimento de prazo;
- atendimento;
- custo-benefício.

## 7. Verificação do fornecedor

O selo “Fornecedor Verificado” não deve ser concedido apenas por cadastro. A plataforma deverá exigir e revisar os documentos definidos para a categoria e natureza da empresa.

## 8. Segurança e privacidade

- Senhas somente com hash seguro (`password_hash`).
- Credenciais nunca versionadas no GitHub.
- Fotos de solicitações ficam fora da pasta pública e exigem autorização.
- Documentos do fornecedor não deverão ser públicos por URL direta.
- Telefone/e-mail não são expostos na vitrine pública nesta fase.
- Dados pessoais devem seguir princípios de minimização e finalidade.
- Toda ação administrativa sensível deverá ser auditável.
