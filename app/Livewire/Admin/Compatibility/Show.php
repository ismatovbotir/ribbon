<?php

namespace App\Livewire\Admin\Compatibility;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Services\RibbonLabelCompatibilityService;
use Livewire\Component;

/**
 * Super Admin edits which TT label materials fit each ribbon type, as one
 * checkbox matrix. The storefront's "Fits these labels" / "Fits these
 * ribbons" blocks read it via RibbonLabelCompatibilityService. Sellers never
 * set compatibility, so a wrong tick can't mislead buyers.
 */
class Show extends Component
{
    use AuthorizesAdmin;

    /** @var array<int, string> "typeOptionId:materialOptionId" */
    public array $pairs = [];

    protected function adminAccess(): ?string
    {
        return 'super-admin';
    }

    public function mount(): void
    {
        $this->authorizeSuperAdmin();

        $this->pairs = RibbonLabelCompatibilityService::pairKeys();
    }

    public function save(): void
    {
        $this->authorizeSuperAdmin();

        $this->validate([
            'pairs' => ['array'],
            'pairs.*' => ['string', 'regex:/^\d+:\d+$/'],
        ]);

        RibbonLabelCompatibilityService::sync($this->pairs);

        $this->pairs = RibbonLabelCompatibilityService::pairKeys();

        session()->flash('status', __('admin.compatibility.saved'));
    }

    public function render()
    {
        return view('livewire.admin.compatibility.show', [
            'typeParameter' => RibbonLabelCompatibilityService::typeParameter(),
            'materialParameter' => RibbonLabelCompatibilityService::materialParameter(),
        ])->layout('layouts.admin', [
            'title' => __('admin.compatibility.title'),
            'breadcrumb' => [
                ['label' => __('admin.compatibility.title')],
            ],
        ]);
    }
}
