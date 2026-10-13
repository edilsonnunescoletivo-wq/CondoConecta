# Roadmap — CondoConecta

## Fase 1 — Fundação do MVP
- [x] Estrutura PHP 8.1+ e configuração por ambiente.
- [x] Banco MySQL inicial.
- [x] Landing page responsiva.
- [x] Cadastro de fornecedor e contratante.
- [x] Login, sessão e logout.
- [x] Proteção CSRF e senha com hash seguro.
- [x] Perfis iniciais separados por tipo de usuário.
- [ ] Recuperação de senha por e-mail.

## Fase 2 — Perfil e confiança do fornecedor
- [x] Seleção de categorias de serviço.
- [x] Regiões/cidades atendidas.
- [x] Perfil público do fornecedor.
- [x] Portfólio inicial de trabalhos.
- [x] Configuração de atendimento emergencial.
- [x] Upload privado de documentos PDF/JPG/PNG.
- [x] Tipos de documento e controle de validade.
- [x] Fila administrativa de revisão.
- [x] Aprovação/rejeição com justificativa.
- [x] Selo de Fornecedor Verificado concedido pela administração.
- [x] Revogação automática do selo quando não houver documento-base aprovado e válido.
- [ ] Upload direto de fotos do portfólio.

## Fase 3 — Solicitações e marketplace
- [x] Criação de solicitação de serviço.
- [x] Upload de fotos da necessidade com acesso protegido.
- [x] Níveis de urgência: normal, urgente e Preciso Agora.
- [x] Match de fornecedores por categoria/região.
- [x] Painel de oportunidades do fornecedor.
- [x] Envio e edição de propostas.
- [x] Listagem de solicitações do contratante.
- [x] Listagem de propostas do fornecedor.

## Fase 4 — Contratação e confiança
- [x] Comparador estruturado de propostas.
- [x] Destaques de menor preço e menor prazo.
- [x] Aceite de proposta pelo contratante.
- [x] Alteração automática das demais propostas para não selecionadas.
- [x] Criação do registro de contratação (`hirings`).
- [x] Histórico de contratação para as duas partes.
- [x] Liberação de contato somente após contratação.
- [x] Registro de início e conclusão do serviço.
- [x] Avaliações vinculadas a serviço concluído.
- [x] Indicadores de qualidade, prazo, atendimento e custo-benefício.
- [x] Média de avaliações exibida no painel do fornecedor.

## Fase 5 — Preciso Agora e proximidade
- [x] Disponibilidade emergencial básica por região.
- [x] Geolocalização opcional do fornecedor.
- [x] Geolocalização opcional da solicitação.
- [x] Raio real de atendimento em quilômetros.
- [x] Cálculo de distância por coordenadas.
- [x] Fallback automático para cidade/região quando não houver localização.
- [x] Ordenação das oportunidades por urgência e proximidade.
- [x] Notificações internas para chamados urgentes e Preciso Agora.
- [ ] Push notification/e-mail instantâneo fora do sistema.

## Fase 6 — Monetização
- [ ] Planos para condomínio e administradora.
- [ ] Período de teste.
- [ ] Integração de pagamento.
- [ ] Controle automático de assinatura e inadimplência.

## Fase 7 — Inteligência e integração
- [ ] Comparação assistida de propostas.
- [ ] Indicadores de preço/prazo por categoria.
- [ ] Integração com Síndicos Gestão.
- [ ] Criação automática de fornecedor/OS/registro financeiro após contratação.

## Fase 8 — Expansão
- [ ] PWA e/ou aplicativo móvel.
- [ ] Push notifications.
- [ ] Novas regiões.
- [ ] Painel comercial e métricas de marketplace.
- [ ] Programa de indicação.
