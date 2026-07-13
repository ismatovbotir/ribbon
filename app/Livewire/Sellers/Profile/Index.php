<?php

namespace App\Livewire\Sellers\Profile;

use App\Models\Seller;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Minimal seller company-profile screen: today just the company logo.
 * Viewable by both Owner and Employee (seeing the company's own logo isn't
 * a privileged action), but only the Owner may change it — the upload
 * control is disabled+captioned for an Employee (see 05-form-patterns.md's
 * hidden-vs-disabled rule: an Employee would reasonably want to know this
 * screen/capability exists and who to ask, so this is disabled, not
 * hidden). Every mutating action re-checks Auth::user()->isOwnerOf($seller)
 * with a 403 regardless of what the UI renders, mirroring
 * Sellers\Employees\Index's pattern for the same reason — a Livewire
 * component's public methods are independently reachable network requests.
 */
class Index extends Component
{
    use WithFileUploads;

    public $logoUpload = null;

    public ?string $existingLogoPath = null;

    public function mount(): void
    {
        $this->existingLogoPath = $this->seller()->logo_path;
    }

    public function seller(): Seller
    {
        return Auth::user()->sellerOrFail();
    }

    public function isOwner(): bool
    {
        return Auth::user()->isOwnerOf($this->seller());
    }

    public function saveLogo(): void
    {
        abort_unless($this->isOwner(), 403);

        $this->validate([
            'logoUpload' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ]);

        $path = $this->logoUpload->store('logos', 'public');

        $this->seller()->update(['logo_path' => $path]);

        $this->logoUpload = null;
        $this->existingLogoPath = $path;

        session()->flash('status', __('sellers.profile.logo_saved'));
    }

    public function removeLogo(): void
    {
        abort_unless($this->isOwner(), 403);

        $this->seller()->update(['logo_path' => null]);

        $this->logoUpload = null;
        $this->existingLogoPath = null;

        session()->flash('status', __('sellers.profile.logo_removed'));
    }

    public function render()
    {
        return view('livewire.sellers.profile.index', [
            'seller' => $this->seller(),
            'isOwner' => $this->isOwner(),
        ])->layout('layouts.seller', [
            'title' => __('sellers.nav.profile'),
            'breadcrumb' => [
                ['label' => __('sellers.nav.profile')],
            ],
        ]);
    }
}
