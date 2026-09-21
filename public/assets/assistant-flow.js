window.FS_ASSIST_FLOW = {
  start: "menu",
  groups: {
    menu: {
      texts: [
        "Olá. Sou a Priscila, do FirestepCRM.\n\nPosso explicar cada área do painel e onde usar cada recurso.",
        "Por onde você quer começar?"
      ],
      choices: [
        { label: "Atendimento", next: "hub_atendimento" },
        { label: "Relacionamento", next: "hub_relacionamento" },
        { label: "Gestão e mapa", next: "hub_gestao" },
        { label: "Financeiro e conexões", next: "hub_conexoes" },
        { label: "Conta e outras dúvidas", next: "hub_conta" }
      ]
    },

    hub_atendimento: {
      texts: ["Atendimento reúne o calendário, a lista de horários, as solicitações que entram e o Pipeline de status."],
      choices: [
        { label: "Agenda", next: "agenda" },
        { label: "Agendamentos", next: "agendamentos" },
        { label: "Solicitações", next: "solicitacoes" },
        { label: "Pipeline", next: "pipeline" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    hub_relacionamento: {
      texts: ["Relacionamento cuida de quem você atende, de quem atende com você e de serviços e fornecedores."],
      choices: [
        { label: "Clientes", next: "clientes" },
        { label: "Agentes", next: "agentes" },
        { label: "Fornecedores", next: "fornecedores" },
        { label: "Serviços", next: "servicos" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    hub_gestao: {
      texts: ["Gestão concentra anotações da equipe, indicadores, PDFs e o mapa de abrangência."],
      choices: [
        { label: "Anotações", next: "anotacoes" },
        { label: "Métricas", next: "metricas" },
        { label: "Relatórios", next: "relatorios" },
        { label: "Abrangência", next: "abrangencia" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    hub_conexoes: {
      texts: ["Aqui entram caixa, site/webhook e as configurações do painel."],
      choices: [
        { label: "Financeiro", next: "financeiro" },
        { label: "Webhooks", next: "webhooks" },
        { label: "Configurações", next: "config" },
        { label: "Comunicar (WhatsApp)", next: "comunicar" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    hub_conta: {
      texts: ["Conta, senha, backup e o que não aparece em um menu específico."],
      choices: [
        { label: "Conta, senha e busca", next: "conta" },
        { label: "Backup automático", next: "backup" },
        { label: "Quem vê o quê no painel", next: "permissoes" },
        { label: "Falar com atendimento", next: "atendimento" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    agenda: {
      texts: [
        "A **Agenda** mostra horários, solicitações e anotações marcadas para o calendário.",
        "Em cima: **Hoje**, setas e os modos **Dia**, **Semana** e **Mês**. **Novo agendamento** abre o cadastro no horário escolhido."
      ],
      choices: [
        { label: "Como navegar no calendário?", next: "agenda_nav" },
        { label: "Como criar um horário?", next: "agenda_criar" },
        { label: "O que cada cor significa?", next: "agenda_cores" },
        { label: "Voltar a Atendimento", next: "hub_atendimento" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    agenda_nav: {
      texts: [
        "Use **Dia** para um horário de cada vez, **Semana** para os sete dias lado a lado e **Mês** para o panorama.",
        "As setas avançam o período (um dia, uma semana ou um mês). **Hoje** volta para a data atual."
      ],
      choices: [
        { label: "Mais sobre a Agenda", next: "agenda" },
        { label: "Voltar ao início", next: "menu" },
        { label: "Falar com atendimento", next: "atendimento" }
      ]
    },
    agenda_criar: {
      texts: [
        "Clique em **Novo agendamento** (canto superior direito) ou em um horário vazio, quando a tela permitir.",
        "Preencha cliente, serviço, data e hora. O mesmo cadastro existe em **Agendamentos**."
      ],
      choices: [
        { label: "Mais sobre a Agenda", next: "agenda" },
        { label: "Ir para Agendamentos", next: "agendamentos" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    agenda_cores: {
      texts: [
        "Agendamentos mudam de cor conforme o status (aguardando, confirmado, cancelado, concluído).",
        "Solicitações, anotações no calendário e bloqueios de horário usam cores próprias para não misturar com um atendimento comum."
      ],
      choices: [
        { label: "Mais sobre a Agenda", next: "agenda" },
        { label: "Voltar ao início", next: "menu" },
        { label: "Falar com atendimento", next: "atendimento" }
      ]
    },

    agendamentos: {
      texts: [
        "Em **Agendamentos** está a lista completa: cadastro manual, site, webhook e outros canais.",
        "Dá para buscar pelo número da reserva, cliente, agente ou serviço, e filtrar por status e origem."
      ],
      choices: [
        { label: "Abrir ou editar um horário", next: "agendamentos_ver" },
        { label: "Filtros e número de reserva", next: "agendamentos_filtro" },
        { label: "Enviar mensagem ao cliente", next: "comunicar" },
        { label: "Voltar a Atendimento", next: "hub_atendimento" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    agendamentos_ver: {
      texts: [
        "Clique no registro para ver os detalhes. Em edição você altera cliente, horário, serviço e status.",
        "O Pipeline também muda o status, com confirmação **Sim** / **Não**."
      ],
      choices: [
        { label: "Mais sobre Agendamentos", next: "agendamentos" },
        { label: "Ir para o Pipeline", next: "pipeline" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    agendamentos_filtro: {
      texts: [
        "A busca cobre número de reserva, nome, agente e serviço. Os seletore de status e origem recortam a lista.",
        "O número da reserva aparece no card e também na vista compacta do Pipeline."
      ],
      choices: [
        { label: "Mais sobre Agendamentos", next: "agendamentos" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    solicitacoes: {
      texts: [
        "As **Solicitações** são pedidos que ainda não viraram horário confirmado — em geral do site ou de um webhook.",
        "Elas também podem aparecer na Agenda. O nome do menu segue o termo do seu segmento (pedidos, leads, etc.)."
      ],
      choices: [
        { label: "De onde elas vêm?", next: "solicitacoes_origem" },
        { label: "Como tratar um pedido?", next: "solicitacoes_tratar" },
        { label: "Voltar a Atendimento", next: "hub_atendimento" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    solicitacoes_origem: {
      texts: [
        "Pedidos do site só entram se o domínio estiver em **Origens autorizadas** em Webhooks e o endpoint estiver ativo.",
        "Campos mínimos: nome e telefone ou e-mail. Data desejada, serviço e UTM são opcionais."
      ],
      choices: [
        { label: "Ir para Webhooks", next: "webhooks" },
        { label: "Mais sobre Solicitações", next: "solicitacoes" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    solicitacoes_tratar: {
      texts: [
        "Abra a solicitação, confira os dados e transforme em agendamento quando o horário existir.",
        "Se o pedido for inválido, recuse ou arquive conforme as ações da tela — não deixe fila parada sem olhar a origem."
      ],
      choices: [
        { label: "Mais sobre Solicitações", next: "solicitacoes" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    pipeline: {
      texts: [
        "O **Pipeline** é o kanban de todos os agendamentos, de qualquer origem.",
        "Há duas vistas: **Cards** (dados completos) e **Nº reserva** (mais compacta). A última vista fica lembrada."
      ],
      choices: [
        { label: "Como mudar o status?", next: "pipeline_status" },
        { label: "Cards ou número de reserva", next: "pipeline_vista" },
        { label: "Voltar a Atendimento", next: "hub_atendimento" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    pipeline_status: {
      texts: [
        "No card, escolha o novo status. O sistema pede confirmação: **Sim** grava, **Não** cancela.",
        "O mesmo status aparece na lista de Agendamentos e nas cores da Agenda."
      ],
      choices: [
        { label: "Mais sobre o Pipeline", next: "pipeline" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    pipeline_vista: {
      texts: [
        "**Cards** mostra cliente, serviço, origem, telefone e data. **Nº reserva** destaca o número e o status, útil quando a coluna está cheia.",
        "Troque no canto superior direito. Ao voltar ao menu Pipeline, a vista escolhida permanece."
      ],
      choices: [
        { label: "Mais sobre o Pipeline", next: "pipeline" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    clientes: {
      texts: [
        "Em **Clientes** (ou o nome do seu segmento) fica o cadastro: busca por nome, telefone ou e-mail no topo do painel também leva para cá.",
        "A ficha reúne dados, campos extras, agendamentos ligados e ações de comunicação."
      ],
      choices: [
        { label: "Cadastrar ou buscar", next: "clientes_cadastro" },
        { label: "Ficha e campos extras", next: "clientes_ficha" },
        { label: "Voltar a Relacionamento", next: "hub_relacionamento" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    clientes_cadastro: {
      texts: [
        "Use **Novo** na lista ou a busca do cabeçalho. Telefone e e-mail ajudam a evitar duplicata na hora de agendar.",
        "Clientes com CNPJ e endereço geocodificado também entram no mapa de Abrangência."
      ],
      choices: [
        { label: "Mais sobre Clientes", next: "clientes" },
        { label: "Ir para Abrangência", next: "abrangencia" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    clientes_ficha: {
      texts: [
        "Abra o cadastro para ver histórico e marcar um novo horário já vinculado à pessoa.",
        "Campos extras se definem em **Configurações → Avançado**. Só aparecem se o administrador os cadastrou."
      ],
      choices: [
        { label: "Ir para Configurações", next: "config" },
        { label: "Mais sobre Clientes", next: "clientes" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    agentes: {
      texts: [
        "**Agentes** são as pessoas que atendem. Só o perfil CRM (administrador da empresa) gerencia esta lista.",
        "Um agente vê a agenda e os atendimentos do próprio trabalho; não acessa financeiro, métricas, webhooks nem configurações."
      ],
      choices: [
        { label: "Como cadastrar um agente?", next: "agentes_cadastro" },
        { label: "Diferença entre agente e CRM", next: "permissoes" },
        { label: "Voltar a Relacionamento", next: "hub_relacionamento" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    agentes_cadastro: {
      texts: [
        "Em Agentes, crie o acesso com nome e contato. No primeiro login a pessoa define a senha (mínimo 10 caracteres, letras e números).",
        "Repasses e comissões ligados ao agente aparecem no Financeiro, em contas a pagar quando houver valor em aberto."
      ],
      choices: [
        { label: "Mais sobre Agentes", next: "agentes" },
        { label: "Ir para Financeiro", next: "financeiro" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    fornecedores: {
      texts: [
        "**Fornecedores** guarda empresas (em geral CNPJ) que você usa no operação.",
        "Com endereço válido, eles entram no mapa de Abrangência junto com clientes CNPJ e visitas externas."
      ],
      choices: [
        { label: "Cadastro e mapa", next: "fornecedores_mapa" },
        { label: "Voltar a Relacionamento", next: "hub_relacionamento" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    fornecedores_mapa: {
      texts: [
        "Preencha endereço com cuidado: o mapa usa geocodificação. Sem coordenadas, o pin não aparece em Abrangência.",
        "Você também cadastra atendimentos externos manuais nesse mapa, além de fornecedor e cliente CNPJ."
      ],
      choices: [
        { label: "Ir para Abrangência", next: "abrangencia" },
        { label: "Mais sobre Fornecedores", next: "fornecedores" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    servicos: {
      texts: [
        "**Serviços** é o catálogo que aparece no agendamento. Sem serviço cadastrado, o horário fica como “serviço a definir”.",
        "Nome, duração e valores usados no financeiro dependem do que você configurar aqui."
      ],
      choices: [
        { label: "Como usar no agendamento?", next: "servicos_uso" },
        { label: "Voltar a Relacionamento", next: "hub_relacionamento" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    servicos_uso: {
      texts: [
        "Ao criar um agendamento, escolha o serviço da lista. Isso amarra cliente, profissional e, quando houver, lançamento financeiro.",
        "Mantenha o catálogo enxuto: nomes claros evitam duplicar o mesmo atendimento com títulos diferentes."
      ],
      choices: [
        { label: "Mais sobre Serviços", next: "servicos" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    anotacoes: {
      texts: [
        "**Anotações** é o livro de plantão: o que aconteceu no turno fica salvo até alguém excluir.",
        "Dá para marcar a nota para aparecer na **Agenda**, útil para recados do dia."
      ],
      choices: [
        { label: "Criar e editar notas", next: "anotacoes_criar" },
        { label: "Mostrar na Agenda", next: "anotacoes_agenda" },
        { label: "Voltar a Gestão", next: "hub_gestao" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    anotacoes_criar: {
      texts: [
        "Clique em **Nova anotação**, escreva o recado e salve. Quem criou (e o perfil CRM) costuma poder editar ou apagar.",
        "Não use anotações no lugar da ficha do cliente: dados permanentes ficam melhor no cadastro da pessoa."
      ],
      choices: [
        { label: "Mais sobre Anotações", next: "anotacoes" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    anotacoes_agenda: {
      texts: [
        "No formulário da nota, marque a opção de mostrar na Agenda. Ela aparece no calendário na data da anotação.",
        "Assim o plantão seguinte vê o recado sem abrir a lista de notas."
      ],
      choices: [
        { label: "Mais sobre Anotações", next: "anotacoes" },
        { label: "Ir para a Agenda", next: "agenda" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    metricas: {
      texts: [
        "**Métricas** resume o negócio: novos clientes, agenda, solicitações e variação em relação ao período anterior.",
        "O recorte fica na barra: Hoje, 7 dias, 30 dias, 90 dias ou 12 meses. Agentes não veem esta tela."
      ],
      choices: [
        { label: "Como ler os indicadores?", next: "metricas_ler" },
        { label: "Voltar a Gestão", next: "hub_gestao" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    metricas_ler: {
      texts: [
        "Cada cartão mostra o total do recorte e a variação (para cima ou para baixo) contra o período imediatamente anterior.",
        "Use o botão de atualizar se você acabou de lançar muitos horários e o número parecer defasado."
      ],
      choices: [
        { label: "Mais sobre Métricas", next: "metricas" },
        { label: "Ir para Relatórios", next: "relatorios" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    relatorios: {
      texts: [
        "Em **Relatórios** os PDFs ficam em pastas. Dois cliques na pasta ou no nome abrem os filtros. O + só expande a árvore.",
        "Não há ícone de PDF na lista: o arquivo é gerado depois que você confirma o filtro."
      ],
      choices: [
        { label: "Como gerar um PDF?", next: "relatorios_pdf" },
        { label: "Relatório de caixa", next: "relatorios_caixa" },
        { label: "Voltar a Gestão", next: "hub_gestao" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    relatorios_pdf: {
      texts: [
        "Abra a pasta, dê dois cliques no relatório, preencha o período ou o filtro pedido e confirme.",
        "Cabeçalho de folha e assinatura, se ativos em Configurações → Avançado, entram nos documentos que usam folha oficial."
      ],
      choices: [
        { label: "Folha e assinatura", next: "config_avancado" },
        { label: "Mais sobre Relatórios", next: "relatorios" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    relatorios_caixa: {
      texts: [
        "O relatório de caixa também pode ser aberto a partir do Dashboard financeiro. Ele usa os lançamentos do período, não só o que está em tela."
      ],
      choices: [
        { label: "Ir para Financeiro", next: "financeiro" },
        { label: "Mais sobre Relatórios", next: "relatorios" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    abrangencia: {
      texts: [
        "**Abrangência** é o mapa: fornecedores CNPJ, clientes CNPJ e atendimentos externos lançados à mão.",
        "O mapa carrega o endereço com o serviço de geocodificação. Sem coordenadas, o pin não entra."
      ],
      choices: [
        { label: "O que aparece no mapa?", next: "abrangencia_pins" },
        { label: "Voltar a Gestão", next: "hub_gestao" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    abrangencia_pins: {
      texts: [
        "Há três tipos de pin: fornecedor, cliente CNPJ e visita/agendamento externo. Cada um tem cor e ficha próprias.",
        "Atualize o endereço no cadastro se o ponto caiu longe: o mapa segue a geocodificação, não um arraste livre."
      ],
      choices: [
        { label: "Mais sobre Abrangência", next: "abrangencia" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    financeiro: {
      texts: [
        "O menu **Financeiro** tem Dashboard, Lançamentos, Contas a receber, Faturado e Contas a pagar.",
        "O dashboard mostra previsto, recebido, a pagar, saldo, lucro presumido e repasses."
      ],
      choices: [
        { label: "Lançamentos", next: "fin_lanc" },
        { label: "Receber e faturar", next: "fin_receber" },
        { label: "Contas a pagar e repasse", next: "fin_pagar" },
        { label: "Voltar a conexões", next: "hub_conexoes" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    fin_lanc: {
      texts: [
        "**Lançamentos** é o livro-caixa: entradas e saídas. Use **Novo lançamento** no dashboard ou na própria lista.",
        "Cancelar um lançamento tira o valor dos totais; não apague histórico só para “corrigir” — prefira um lançamento inverso quando fizer sentido."
      ],
      choices: [
        { label: "Mais sobre Financeiro", next: "financeiro" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    fin_receber: {
      texts: [
        "**Contas a receber** fica em aberto até o pagamento. A fatura só sai quando você usa **Faturar**.",
        "**Faturado** lista o que já foi confirmado na data acordada. O dashboard soma previsto (abertos + recebidos) e recebido no mês."
      ],
      choices: [
        { label: "Mais sobre Financeiro", next: "financeiro" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    fin_pagar: {
      texts: [
        "**Contas a pagar** junta despesas, boletos e repasses de comissão ainda em aberto.",
        "Lucro presumido = previsto menos repasse aos agentes. Lucro líquido = recebido menos saídas (incluindo repasses já pagos)."
      ],
      choices: [
        { label: "Mais sobre Financeiro", next: "financeiro" },
        { label: "Ir para Agentes", next: "agentes" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    webhooks: {
      texts: [
        "**Webhooks** recebe solicitações do seu site. Sem domínio em **Origens autorizadas**, qualquer origem é recusada.",
        "O endereço fica oculto. Só mostre o endpoint neste painel quando for colar no código. Não publique o link em página aberta."
      ],
      choices: [
        { label: "Como autorizar o site?", next: "webhooks_origem" },
        { label: "Endpoint, token e teste", next: "webhooks_token" },
        { label: "Voltar a conexões", next: "hub_conexoes" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    webhooks_origem: {
      texts: [
        "Abra Origens autorizadas e cadastre o domínio do site. www e subdomínios entram juntos. Não use o domínio do CRM.",
        "Enquanto a lista estiver vazia, o webhook recusa tudo. Chamadas de outro domínio aparecem em Últimas requisições como origem bloqueada."
      ],
      choices: [
        { label: "Mais sobre Webhooks", next: "webhooks" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    webhooks_token: {
      texts: [
        "Com origem pronta, use **Mostrar endpoint**. Envie JSON via fetch: name e phone ou email são obrigatórios.",
        "Pode incluir service, desired_date, desired_time, message, source e UTMs. **Gerar novo token** invalida o segredo antigo. Ative ou desative o endpoint no mesmo card."
      ],
      choices: [
        { label: "Ir para Solicitações", next: "solicitacoes" },
        { label: "Mais sobre Webhooks", next: "webhooks" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    config: {
      texts: [
        "**Configurações** se divide em abas. Nada grava até você confirmar naquela aba.",
        "Visão geral é só leitura. Negócio, Horários, Avançado, Integrações e Conta se editam à parte."
      ],
      choices: [
        { label: "Negócio e horários", next: "config_negocio" },
        { label: "Avançado (folha, campos)", next: "config_avancado" },
        { label: "Integrações (GTM e WhatsApp)", next: "config_integ" },
        { label: "Voltar a conexões", next: "hub_conexoes" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    config_negocio: {
      texts: [
        "**Negócio**: nome, contato, cidade e fuso. Clique em Editar, altere e salve.",
        "**Horários**: abertura, fechamento, intervalo e dias fechados. A Agenda usa essa grade para o dia útil."
      ],
      choices: [
        { label: "Mais sobre Configurações", next: "config" },
        { label: "Tela inicial do menu", next: "conta" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    config_avancado: {
      texts: [
        "Em **Avançado**: cor/aparência, **menu principal** (para onde o logo leva), campos extras, cabeçalho de folha, assinatura de contratos e cláusulas.",
        "Folha e assinatura só entram nos PDFs/contratos quando estão ativas. A assinatura não aparece no restante do CRM."
      ],
      choices: [
        { label: "Contratos e cláusulas", next: "config_contratos" },
        { label: "Mais sobre Configurações", next: "config" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    config_contratos: {
      texts: [
        "Em cláusulas você cria contratos, marca os agendamentos envolvidos e edita o texto. Salve na própria tela de contratos.",
        "Ative a assinatura só quando o arquivo estiver certo: ela é aplicada aos contratos, não à lista do dia a dia."
      ],
      choices: [
        { label: "Mais sobre Avançado", next: "config_avancado" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    config_integ: {
      texts: [
        "**Tag Manager**: ID no formato GTM-XXXXXXX, opcional. Informe também os endereços do site (separados por vírgula), sem o domínio do CRM.",
        "**WhatsApp**: conexão usada pelo botão **Comunicar**. Sem isso configurado, o envio de mensagem não sai."
      ],
      choices: [
        { label: "Como usar Comunicar?", next: "comunicar" },
        { label: "Mais sobre Configurações", next: "config" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    comunicar: {
      texts: [
        "**Comunicar** envia WhatsApp ao destinatário pela conexão da empresa.",
        "Em **Configurações → Comunicar** você liga envios automáticos: novo horário, confirmação, cancelamento, lembrete e nova solicitação."
      ],
      choices: [
        { label: "Envios automáticos", next: "comunicar_auto" },
        { label: "Onde configurar o WhatsApp?", next: "config_integ" },
        { label: "Voltar a Agendamentos", next: "agendamentos" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    comunicar_auto: {
      texts: [
        "A aba **Comunicar** nas Configurações liga a automação. Cada regra liga à parte e pode usar o último modelo do botão Comunicar.",
        "O lembrete usa as horas que você definir antes do horário e é conferido uma vez por dia. Sem a conexão de WhatsApp em Integrações, nada é enviado."
      ],
      choices: [
        { label: "Voltar a Comunicar", next: "comunicar" },
        { label: "Ir para Integrações", next: "config_integ" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    conta: {
      texts: [
        "A aba **Conta** em Configurações trata do acesso da empresa. A busca do topo procura cliente por nome, telefone ou e-mail.",
        "No primeiro acesso cada usuário define senha (mínimo 10 caracteres, letras e números). O sino do cabeçalho mostra avisos não lidos."
      ],
      choices: [
        { label: "Menu principal e aparência", next: "conta_home" },
        { label: "Backup automático", next: "backup" },
        { label: "Voltar a Conta", next: "hub_conta" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },
    conta_home: {
      texts: [
        "Em Avançado, **Menu principal** define para qual tela o logo do FirestepCRM leva (Agenda, Pipeline, etc.).",
        "A cor primária do painel também fica nas configurações da empresa."
      ],
      choices: [
        { label: "Ir para Configurações", next: "config" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    backup: {
      texts: [
        "Na aba **Conta**, o botão **AUTO BACKUP** liga a cópia diária às 03:00 (horário de Brasília).",
        "Pastas sem mudança ou sem dados são puladas. Conta nova nasce com o backup desligado. Ao ligar, o sistema tenta uma cópia na hora."
      ],
      choices: [
        { label: "Voltar a Conta", next: "hub_conta" },
        { label: "Falar com atendimento", next: "atendimento" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    permissoes: {
      texts: [
        "Quem entra como **CRM** vê o painel completo: financeiro, métricas, agentes, webhooks e configurações.",
        "Quem entra como **agente** fica no atendimento (agenda, horários, clientes da operação) e não altera a conta da empresa."
      ],
      choices: [
        { label: "Mais sobre Agentes", next: "agentes" },
        { label: "Voltar a Conta", next: "hub_conta" },
        { label: "Voltar ao início", next: "menu" }
      ]
    },

    atendimento: {
      texts: [
        "Certo. Vou te chamar no WhatsApp.",
        "Digite seu número no formato **(00) 0 0000-0000**."
      ],
      input: { placeholder: "(00) 0 0000-0000", button: "Enviar", next: "atendimento_ok", kind: "whatsapp" }
    },
    duvida: {
      texts: [
        "Certo. Vou te chamar no WhatsApp.",
        "Digite seu número no formato **(00) 0 0000-0000**."
      ],
      input: { placeholder: "(00) 0 0000-0000", button: "Enviar", next: "atendimento_ok", kind: "whatsapp" }
    },
    atendimento_ok: {
      texts: [
        "Enviei a mensagem no seu WhatsApp. Estou encerrando o atendimento por aqui."
      ],
      end: true
    }
  }
};
