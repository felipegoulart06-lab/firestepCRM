<?php
return [
    'psicologia' => [
        'name' => 'Psicologia', 'category' => 'Saúde e Bem-Estar',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Psicoterapia individual','duration'=>50,'price'=>180,'category'=>'Terapia'],
            ['name'=>'Terapia de casal','duration'=>60,'price'=>250,'category'=>'Terapia'],
            ['name'=>'Primeira consulta','duration'=>60,'price'=>200,'category'=>'Avaliação'],
        ],
        'fields' => [],
    ],
    'psiquiatria' => [
        'name' => 'Psiquiatria', 'category' => 'Saúde e Bem-Estar',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Consulta psiquiátrica','duration'=>50,'price'=>350],
            ['name'=>'Retorno','duration'=>30,'price'=>220],
        ],
        'fields' => [],
    ],
    'odontologia' => [
        'name' => 'Dentista', 'category' => 'Saúde e Bem-Estar',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Avaliação','duration'=>40,'price'=>150],
            ['name'=>'Limpeza','duration'=>50,'price'=>220],
            ['name'=>'Clareamento','duration'=>60,'price'=>450],
        ],
        'fields' => [],
    ],
    'fisioterapia' => [
        'name' => 'Fisioterapia', 'category' => 'Saúde e Bem-Estar',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Avaliação fisioterapêutica','duration'=>60,'price'=>180],
            ['name'=>'Sessão de fisioterapia','duration'=>50,'price'=>140],
        ],
        'fields' => [],
    ],
    'nutricao' => [
        'name' => 'Nutrição', 'category' => 'Saúde e Bem-Estar',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Consulta nutricional','duration'=>50,'price'=>180],
            ['name'=>'Retorno','duration'=>30,'price'=>120],
        ],
        'fields' => [],
    ],
    'veterinaria' => [
        'name' => 'Veterinário', 'category' => 'Saúde e Bem-Estar',
        'terms' => ['client'=>'Tutor','clients'=>'Tutores','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Consulta veterinária','duration'=>40,'price'=>160],
            ['name'=>'Vacinação','duration'=>20,'price'=>90],
            ['name'=>'Retorno','duration'=>20,'price'=>80],
        ],
        'fields' => [
            ['label'=>'Nome do pet','key'=>'pet_name','type'=>'text'],
            ['label'=>'Espécie','key'=>'pet_species','type'=>'select','options'=>['Cão','Gato','Ave','Outro']],
            ['label'=>'Raça','key'=>'pet_breed','type'=>'text'],
            ['label'=>'Idade','key'=>'pet_age','type'=>'text'],
        ],
    ],
    'barbearia' => [
        'name' => 'Barbearia', 'category' => 'Beleza e Estética',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Corte','duration'=>40,'price'=>50],
            ['name'=>'Barba','duration'=>25,'price'=>35],
            ['name'=>'Corte + barba','duration'=>60,'price'=>75],
        ],
        'fields' => [],
    ],
    'salao' => [
        'name' => 'Cabeleireiro / Salão', 'category' => 'Beleza e Estética',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Corte feminino','duration'=>50,'price'=>90],
            ['name'=>'Coloração','duration'=>120,'price'=>220],
            ['name'=>'Escova','duration'=>45,'price'=>70],
        ],
        'fields' => [],
    ],
    'estetica' => [
        'name' => 'Estética', 'category' => 'Beleza e Estética',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Limpeza de pele','duration'=>60,'price'=>150],
            ['name'=>'Massagem relaxante','duration'=>50,'price'=>130],
        ],
        'fields' => [],
    ],
    'advocacia' => [
        'name' => 'Advogado', 'category' => 'Consultoria e Serviços Jurídicos',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Consulta jurídica','duration'=>60,'price'=>350],
            ['name'=>'Reunião de acompanhamento','duration'=>40,'price'=>220],
        ],
        'fields' => [['label'=>'Área do caso','key'=>'case_area','type'=>'text']],
    ],
    'contabilidade' => [
        'name' => 'Contabilista', 'category' => 'Consultoria e Serviços Jurídicos',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [['name'=>'Reunião de assessoria','duration'=>60,'price'=>200]],
        'fields' => [],
    ],
    'imobiliaria' => [
        'name' => 'Corretor de Imóveis', 'category' => 'Consultoria e Serviços Jurídicos',
        'terms' => ['client'=>'Interessado','clients'=>'Interessados','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Visita ao imóvel','duration'=>45,'price'=>0],
            ['name'=>'Reunião de proposta','duration'=>40,'price'=>0],
        ],
        'fields' => [
            ['label'=>'Tipo de imóvel','key'=>'property_type','type'=>'select','options'=>['Apartamento','Casa','Sala comercial','Terreno','Outro']],
            ['label'=>'Faixa de preço','key'=>'price_range','type'=>'text'],
        ],
    ],
    'fotografia' => [
        'name' => 'Fotógrafo', 'category' => 'Serviços Técnicos e Especializados',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Ensaio individual','duration'=>120,'price'=>450],
            ['name'=>'Ensaio casal','duration'=>120,'price'=>550],
            ['name'=>'Ensaio família','duration'=>150,'price'=>650],
        ],
        'fields' => [
            ['label'=>'Tipo de ensaio','key'=>'shoot_type','type'=>'text'],
            ['label'=>'Local desejado','key'=>'shoot_location','type'=>'text'],
        ],
    ],
    'mecanica' => [
        'name' => 'Mecânico', 'category' => 'Serviços Técnicos e Especializados',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Diagnóstico','duration'=>40,'price'=>80],
            ['name'=>'Revisão','duration'=>120,'price'=>350],
            ['name'=>'Troca de óleo','duration'=>40,'price'=>180],
        ],
        'fields' => [
            ['label'=>'Veículo','key'=>'vehicle','type'=>'text'],
            ['label'=>'Modelo','key'=>'vehicle_model','type'=>'text'],
            ['label'=>'Placa','key'=>'plate','type'=>'text'],
            ['label'=>'Ano','key'=>'year','type'=>'number'],
        ],
    ],
    'educacao' => [
        'name' => 'Professor particular', 'category' => 'Educação e Desenvolvimento Pessoal',
        'terms' => ['client'=>'Aluno','clients'=>'Alunos','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Aula individual','duration'=>60,'price'=>120],
            ['name'=>'Aula em grupo','duration'=>90,'price'=>80],
        ],
        'fields' => [],
    ],
    'personal' => [
        'name' => 'Personal Trainer', 'category' => 'Educação e Desenvolvimento Pessoal',
        'terms' => ['client'=>'Aluno','clients'=>'Alunos','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [
            ['name'=>'Treino personal','duration'=>60,'price'=>120],
            ['name'=>'Avaliação física','duration'=>50,'price'=>150],
        ],
        'fields' => [],
    ],
    'coaching' => [
        'name' => 'Coach / Mentor', 'category' => 'Educação e Desenvolvimento Pessoal',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [['name'=>'Sessão de coaching','duration'=>60,'price'=>250]],
        'fields' => [],
    ],
    'outros' => [
        'name' => 'Outros serviços', 'category' => 'Geral',
        'terms' => ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos'],
        'services' => [['name'=>'Atendimento','duration'=>60,'price'=>0]],
        'fields' => [],
    ],
];
