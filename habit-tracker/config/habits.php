<?php

return [
    /*
    | Zona horaria usada para decidir qué fecha es "hoy" y para todos los
    | cálculos de rachas/porcentajes. Los timestamps se guardan en UTC
    | (APP_TIMEZONE), pero las fechas de registro son fechas locales.
    */
    'timezone' => env('HABITS_TIMEZONE', 'America/Caracas'),

    'max_priorities' => 3,
];
