# Regras de negócio — MVP CondoConecta

## 1. Modelo comercial

### Fornecedor
- Cadastro gratuito.
- Perfil gratuito.
- Pode selecionar categorias e regiões atendidas.
- Pode receber oportunidades compatíveis.
- Pode enviar propostas sem pagar por lead.
- Não paga comissão obrigatória no MVP.

### Contratante
Perfis: condomínio, administradora, síndico profissional ou empresa.

A pesquisa pública/básica de fornecedores poderá ser gratuita. Os recursos de gestão e contratação serão vinculados a assinatura, incluindo:
- publicação estruturada de cotações;
- recebimento e comparação de propostas;
- histórico de contratação;
- consulta organizada de documentos;
- relatórios;
- fluxo de aprovação;
- recursos avançados de análise.

## 2. Solicitação de serviço

Toda solicitação deve ter:
- categoria;
- título;
- descrição;
- cidade/UF;
- nível de urgência.

O contratante poderá opcionalmente informar fotos, data desejada, referência de endereço e geolocalização.

Níveis de urgência:
- Normal;
- Urgente;
- Preciso Agora.

## 3. Distribuição da oportunidade

Uma solicitação deve ser apresentada prioritariamente a fornecedores que coincidam em:
1. categoria de serviço;
2. área/região atendida;
3. situação ativa na plataforma.

Em “Preciso Agora”, fornecedores que declararem atendimento emergencial devem ter prioridade de exibição/notificação.

## 4. Propostas

Cada fornecedor pode enviar uma proposta por solicitação, com possibilidade de alteração enquanto ela não tiver sido aceita ou encerrada.

Campos mínimos:
- valor;
- descrição/escopo.

Campos recomendados:
- prazo de execução;
- garantia;
- observações.

## 5. Contratação

Quando uma proposta for aceita:
- a solicitação passa para `contratada`;
- as demais propostas permanecem registradas no histórico;
- é criado um registro de contratação;
- fornecedor e contratante recebem confirmação.

## 6. Avaliações

Somente uma contratação concluída pela plataforma pode gerar avaliação pública do fornecedor.

Critérios iniciais:
- qualidade;
- cumprimento de prazo;
- atendimento;
- custo-benefício.

Notas: 1 a 5.

## 7. Verificação do fornecedor

O selo “Fornecedor Verificado” não deve ser concedido apenas por cadastro. A plataforma deverá exigir e revisar os documentos definidos para a categoria e natureza da empresa.

Possíveis documentos:
- CPF/CNPJ;
- cartão CNPJ;
- certidões;
- registros profissionais;
- NRs e treinamentos;
- seguros;
- licenças e certificados.

## 8. Segurança e privacidade

- Senhas somente com hash seguro (`password_hash`).
- Credenciais nunca versionadas no GitHub.
- Uploads devem ficar fora da pasta pública ou usar acesso controlado.
- Documentos do fornecedor não devem ser públicos por URL direta.
- Dados pessoais devem seguir princípios de minimização e finalidade.
- Toda ação administrativa sensível deverá ser auditável.
