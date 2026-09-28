<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        // Ancho del contenido en escritorio: "narrow" (formularios, listas) o "wide" (tableros).
        public string $width = 'narrow',
    ) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
