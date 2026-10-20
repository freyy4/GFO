<?php

namespace App\View\Composers;

use App\Support\Navigation;
use Illuminate\View\View;

class NavigationComposer
{
    public function __construct(private Navigation $navigation) {}

    public function compose(View $view): void
    {
        $view->with('navigation', $this->navigation->data());
    }
}
