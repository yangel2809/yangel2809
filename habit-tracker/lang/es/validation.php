<?php

return [
    'accepted' => 'Debes aceptar :attribute.',
    'array' => ':Attribute debe ser una lista.',
    'between' => [
        'numeric' => ':Attribute debe estar entre :min y :max.',
        'string' => ':Attribute debe tener entre :min y :max caracteres.',
        'array' => ':Attribute debe tener entre :min y :max elementos.',
    ],
    'boolean' => ':Attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña es incorrecta.',
    'date' => ':Attribute no es una fecha válida.',
    'date_format' => ':Attribute debe tener el formato :format.',
    'email' => ':Attribute debe ser un correo válido.',
    'in' => ':Attribute no es válido.',
    'integer' => ':Attribute debe ser un número entero.',
    'lowercase' => ':Attribute debe estar en minúsculas.',
    'max' => [
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'string' => ':Attribute no puede tener más de :max caracteres.',
        'array' => ':Attribute no puede tener más de :max elementos.',
    ],
    'min' => [
        'numeric' => ':Attribute debe ser al menos :min.',
        'string' => ':Attribute debe tener al menos :min caracteres.',
        'array' => ':Attribute debe tener al menos :min elementos.',
    ],
    'password' => [
        'letters' => ':Attribute debe contener al menos una letra.',
        'mixed' => ':Attribute debe contener mayúsculas y minúsculas.',
        'numbers' => ':Attribute debe contener al menos un número.',
        'symbols' => ':Attribute debe contener al menos un símbolo.',
        'uncompromised' => ':Attribute apareció en una filtración de datos. Elige otra.',
    ],
    'present' => ':Attribute debe estar presente.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => ':Attribute es obligatorio.',
    'required_if' => ':Attribute es obligatorio cuando :other es :value.',
    'string' => ':Attribute debe ser texto.',
    'unique' => ':Attribute ya está en uso.',

    'attributes' => [
        'name' => 'nombre',
        'email' => 'correo',
        'password' => 'contraseña',
        'current_password' => 'contraseña actual',
        'date' => 'fecha',
        'note' => 'nota',
        'texts' => 'prioridades',
        'texts.*' => 'prioridad',
    ],
];
