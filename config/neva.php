<?php

return [
    'name' => 'Neva Medic',
    'tagline' => 'Elegancia y confort para salvar vidas.',
    'location' => 'Torreón, Coahuila, México',
    // Número internacional: solo dígitos. Vacío hasta que el cliente lo confirme.
    'whatsapp' => env('NEVA_WHATSAPP'),
    'sizes' => ['XS', 'S', 'M', 'G', 'L', 'XL', 'XXL', 'XXXL', 'Única'],
    'product_types' => [
        'filipinas' => 'Filipinas',
        'batas-clinicas' => 'Batas clínicas',
        'pantalones' => 'Pantalones',
        'gorros-cofias' => 'Gorros y cofias',
        'conjuntos-quirurgicos' => 'Conjuntos quirúrgicos',
        'batas-medicas' => 'Batas médicas',
        'filipinas-medicas' => 'Filipinas médicas',
        'accesorios' => 'Accesorios',
        'camisas-industriales' => 'Camisas industriales',
        'overoles' => 'Overoles',
        'chalecos' => 'Chalecos',
        'pantalones-trabajo' => 'Pantalones de trabajo',
    ],
    'sector_types' => [
        'clinico' => ['filipinas', 'batas-clinicas', 'pantalones', 'gorros-cofias'],
        'medico' => ['conjuntos-quirurgicos', 'batas-medicas', 'filipinas-medicas', 'accesorios'],
        'industrial' => ['camisas-industriales', 'overoles', 'chalecos', 'pantalones-trabajo'],
    ],
];
