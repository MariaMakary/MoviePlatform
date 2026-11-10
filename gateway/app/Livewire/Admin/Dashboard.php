<?php

namespace App\Livewire\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Admin')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.admin.dashboard');
    }
}
