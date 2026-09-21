window.FS_ASSIST_FLOW = {
  start: "menu",
  groups: {
    menu: {
      texts: [
        "Olá! 👋 Sou o assistente do FirestepCRM.\n\nPosso te orientar sobre as áreas do seu painel e explicar onde cada recurso é usado.",
        "Escolha uma opção abaixo:"
      ],
      choices: [
        { label: "📅 Agenda", next: "agenda" },
        { label: "📝 Anotações", next: "anotacoes" },
        { label: "📊 Métricas", next: "metricas" },
        { label: "🔄 Pipeline", next: "pipeline" },
        { label: "📋 Agendamentos", next: "agendamentos" },
        { label: "📥 Solicitações", next: "solicitacoes" },
        { label: "👥 Clientes", next: "clientes" },
        { label: "👤 Agentes", next: "agentes" },
        { label: "🗺️ Abrangência", next: "abrangencia" },
        { label: "🏢 Fornecedores", next: "fornecedores" },
        { label: "🛠️ Serviços", next: "servicos" },
        { label: "📄 Relatórios", next: "relatorios" },
        { label: "💰 Financeiro", next: "financeiro" },
        { label: "🔗 Webhooks", next: "webhooks" },
        { label: "⚙️ Configurações", next: "config" },
        { label: "❓ Minha dúvida não está aqui", next: "duvida" }
      ]
    },
    agenda: {
      texts: [
        "Você está em **Agenda e calendário**.",
        "📅 **Agenda**\nA Agenda organiza horários, solicitações e anotações salvas no calendário. Na visualização semanal, você consegue consultar os dias e horários em um único painel.",
        "**Criar um agendamento:** use o botão **Novo agendamento** no canto superior direito. Depois, preencha as informações solicitadas pelo sistema.",
        "**Navegação:** use **Hoje**, as setas de anterior/próximo e os modos **Dia, Semana e Mês** para mudar a visualização."
      ],
      choices: [
        { label: "Como usar a Agenda?", next: "agenda_usar" },
        { label: "Como criar um agendamento?", next: "agenda_criar" },
        { label: "Como consultar a semana?", next: "agenda_semana" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    agenda_usar: {
      texts: [
        "Na **Agenda**, você visualiza horários no calendário e pode alternar entre **Dia, Semana e Mês**. A tela também permite voltar para **Hoje** e navegar entre períodos.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    agenda_criar: {
      texts: [
        "Clique em **Novo agendamento**. O sistema abrirá o fluxo para cadastrar o novo agendamento com as informações necessárias.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    agenda_semana: {
      texts: [
        "Na Agenda, selecione **Semana** para visualizar os dias lado a lado. Use as setas para avançar ou voltar uma semana.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    anotacoes: {
      texts: [
        "Você está em **Anotações**.",
        "📝 **Anotações**\nÉ a área destinada às anotações que você precisa manter organizadas dentro do CRM, evitando deixar informações importantes espalhadas em conversas ou arquivos."
      ],
      choices: [
        { label: "Para que servem as Anotações?", next: "anotacoes_para" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    anotacoes_para: {
      texts: [
        "Use as **Anotações** para registrar informações que precisam permanecer organizadas dentro do CRM.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    metricas: {
      texts: [
        "Você está em **Métricas**.",
        "📊 **Métricas**\nA área de Métricas concentra informações para acompanhar a operação e consultar indicadores do CRM."
      ],
      choices: [
        { label: "O que encontro em Métricas?", next: "metricas_oque" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    metricas_oque: {
      texts: [
        "A área de **Métricas** é usada para consultar indicadores e informações da operação. Os dados exibidos dependem dos registros existentes no seu painel.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    pipeline: {
      texts: [
        "Você está em **Pipeline**.",
        "🔄 **Pipeline**\nO Pipeline ajuda a visualizar o andamento dos contatos e atendimentos, permitindo acompanhar quem acabou de entrar, quem está em atendimento e quem já fechou."
      ],
      choices: [
        { label: "Como funciona o Pipeline?", next: "pipeline_como" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    pipeline_como: {
      texts: [
        "O Pipeline apresenta o andamento dos contatos e atendimentos. A ideia é enxergar em que etapa cada oportunidade está, desde a entrada até o fechamento.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    agendamentos: {
      texts: [
        "Você está em **Agendamentos**.",
        "📋 **Agendamentos**\nÉ a área de atendimento dedicada aos agendamentos cadastrados no CRM. Ela complementa a visão de calendário da Agenda.",
        "Use **Agenda** quando quiser visualizar horários no calendário. Use **Agendamentos** quando precisar trabalhar diretamente com os registros de agendamento."
      ],
      choices: [
        { label: "Qual a diferença entre Agenda e Agendamentos?", next: "agendamentos_diff" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    agendamentos_diff: {
      texts: [
        "A **Agenda** mostra os horários no calendário; **Agendamentos** é a área para consultar e trabalhar com os registros de agendamento.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    solicitacoes: {
      texts: [
        "Você está em **Solicitações**.",
        "📥 **Solicitações**\nAs solicitações representam novos pedidos ou contatos que precisam ser acompanhados pela empresa.",
        "Um novo contato pode entrar no painel como uma solicitação para a equipe acompanhar até o atendimento e, conforme o processo da empresa, avançar no fechamento."
      ],
      choices: [
        { label: "O que são Solicitações?", next: "solicitacoes_oque" },
        { label: "Como acompanhar uma solicitação?", next: "solicitacoes_como" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    solicitacoes_oque: {
      texts: [
        "Solicitações são registros de novos pedidos ou contatos que precisam de acompanhamento pela equipe.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    solicitacoes_como: {
      texts: [
        "Abra **Solicitações** para consultar os pedidos recebidos e acompanhar cada atendimento. A descrição do sistema indica que novos contatos podem entrar como solicitações para a equipe acompanhar.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    clientes: {
      texts: [
        "Você está em **Clientes**.",
        "👥 **Clientes**\nCentraliza os dados dos clientes em um só lugar.",
        "O FirestepCRM foi pensado para manter o histórico e os dados de atendimento organizados, reduzindo a dependência de informações espalhadas pelo WhatsApp e por planilhas."
      ],
      choices: [
        { label: "Como funciona o cadastro de clientes?", next: "clientes_cadastro" },
        { label: "O que fica organizado no histórico?", next: "clientes_historico" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    clientes_cadastro: {
      texts: [
        "Na área **Clientes**, mantenha os dados dos seus clientes organizados dentro do painel, em vez de depender de informações espalhadas.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    clientes_historico: {
      texts: [
        "O CRM centraliza dados e histórico dos clientes, permitindo consultar as informações em um único lugar.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    agentes: {
      texts: [
        "Você está em **Agentes**.",
        "👤 **Agentes**\nÁrea relacionada às pessoas/agentes que participam da operação de atendimento da empresa."
      ],
      choices: [
        { label: "O que são Agentes?", next: "agentes_oque" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    agentes_oque: {
      texts: [
        "**Agentes** é a área destinada à organização dos agentes/pessoas que participam do atendimento e da operação da empresa.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    abrangencia: {
      texts: [
        "Você está em **Abrangência**.",
        "🗺️ **Abrangência**\nÁrea para organizar a abrangência de atuação da empresa dentro do CRM."
      ],
      choices: [
        { label: "Para que serve Abrangência?", next: "abrangencia_para" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    abrangencia_para: {
      texts: [
        "**Abrangência** é a área para organizar a atuação da empresa. Use-a conforme a estrutura de regiões ou áreas atendidas pelo seu negócio.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    fornecedores: {
      texts: [
        "Você está em **Fornecedores**.",
        "🏢 **Fornecedores**\nÁrea destinada ao cadastro e organização dos fornecedores utilizados pela empresa."
      ],
      choices: [
        { label: "Como organizar fornecedores?", next: "fornecedores_como" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    fornecedores_como: {
      texts: [
        "Cadastre e organize os fornecedores da empresa na área **Fornecedores**, mantendo essas informações dentro do CRM.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    servicos: {
      texts: [
        "Você está em **Serviços**.",
        "🛠️ **Serviços**\nÁrea para cadastrar e organizar os serviços que a empresa oferece. Esses serviços podem fazer parte da rotina de atendimento e agendamento."
      ],
      choices: [
        { label: "Como cadastrar serviços?", next: "servicos_como" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    servicos_como: {
      texts: [
        "Em **Serviços**, cadastre os serviços oferecidos pela empresa. Esses registros ajudam a estruturar a rotina de atendimento e agendamento.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    relatorios: {
      texts: [
        "Você está em **Relatórios**.",
        "📄 **Relatórios**\nPermite consultar informações da operação e gerar relatórios para acompanhamento da empresa."
      ],
      choices: [
        { label: "O que posso consultar em Relatórios?", next: "relatorios_oque" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    relatorios_oque: {
      texts: [
        "Em **Relatórios**, consulte informações da operação e gere relatórios para acompanhamento.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    financeiro: {
      texts: [
        "Você está em **Financeiro**.",
        "💰 **Financeiro**\nO CRM reúne informações financeiras junto da operação de atendimento, incluindo valores a receber, pagamentos, faturamento e despesas."
      ],
      choices: [
        { label: "O que posso acompanhar no Financeiro?", next: "financeiro_oque" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    financeiro_oque: {
      texts: [
        "O **Financeiro** reúne informações como valores a receber, pagamentos, faturamento e despesas, mantendo o acompanhamento financeiro próximo da rotina de atendimento.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    webhooks: {
      texts: [
        "Você está em **Webhooks e integrações**.",
        "🔗 **Webhooks**\nÁrea de integrações para conectar o FirestepCRM a formulários, websites e automações externas.",
        "Se você utiliza uma automação externa, os webhooks podem servir como ponto de comunicação entre o CRM e outros serviços."
      ],
      choices: [
        { label: "Para que servem os Webhooks?", next: "webhooks_para" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    webhooks_para: {
      texts: [
        "Em **Webhooks**, você configura pontos de integração para comunicação com formulários, websites e automações externas. Os detalhes dependem da integração que você pretende fazer.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    config: {
      texts: [
        "Você está em **Configurações**.",
        "⚙️ **Configurações**\nÁrea destinada aos ajustes do sistema e da empresa dentro do CRM."
      ],
      choices: [
        { label: "O que encontro em Configurações?", next: "config_oque" },
        { label: "Voltar ao menu", next: "menu" }
      ]
    },
    config_oque: {
      texts: [
        "Em **Configurações**, ficam os ajustes do sistema e da empresa. As opções disponíveis podem variar conforme a configuração do seu painel.",
        "Posso te levar de volta ao menu principal para consultar outra área."
      ],
      choices: [{ label: "⬅️ Voltar ao menu", next: "menu" }]
    },
    duvida: {
      texts: [
        "Sem problema. Escreva sua dúvida no campo abaixo. Ela será enviada ao Admin Master no painel da plataforma. Este assistente não consulta automaticamente os dados internos do seu CRM."
      ],
      input: { placeholder: "Digite sua dúvida...", button: "Enviar", next: "encerrar" }
    },
    encerrar: {
      texts: [
        "Obrigado! Sua dúvida foi registrada e enviada ao Admin Master.",
        "Se quiser continuar consultando o CRM, volte ao menu principal."
      ],
      choices: [{ label: "🏠 Menu principal", next: "menu" }]
    }
  }
};
