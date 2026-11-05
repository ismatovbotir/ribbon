<?php

namespace App\Livewire\Sellers\Profile;

use App\Livewire\Concerns\AuthorizesSeller;
use App\Models\Seller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
    use AuthorizesSeller;
    use WithFileUploads;

    public $logoUpload = null;

    public ?string $existingLogoPath = null;

    public $certificateUpload = null;

    /** '' = not stated, 'yes', 'no'. */
    public string $vatPayer = '';

    /** @var array<int, string> */
    public array $paymentMethods = [];

    public string $warrantyInfo = '';

    public function mount(): void
    {
        $seller = $this->seller();

        $this->existingLogoPath = $seller->logo_path;
        $this->vatPayer = match ($seller->is_vat_payer) {
            true => 'yes',
            false => 'no',
            default => '',
        };
        $this->paymentMethods = $seller->payment_methods ?? [];
        $this->warrantyInfo = (string) $seller->warranty_info;
    }

    public function saveBusinessInfo(): void
    {
        $this->authorizeOwner();

        $this->validate([
            'vatPayer' => ['nullable', Rule::in(['yes', 'no'])],
            'paymentMethods' => ['array'],
            'paymentMethods.*' => [Rule::in(Seller::PAYMENT_METHODS)],
            'warrantyInfo' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->seller()->update([
            'is_vat_payer' => $this->vatPayer === '' ? null : $this->vatPayer === 'yes',
            'payment_methods' => array_values($this->paymentMethods) ?: null,
            'warranty_info' => trim($this->warrantyInfo) ?: null,
        ]);

        session()->flash('status', __('sellers.profile.business_saved'));
    }

    public function saveCertificate(): void
    {
        $this->authorizeOwner();

        $this->validate([
            'certificateUpload' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $seller = $this->seller();
        $previous = $seller->registration_certificate_path;

        $seller->update(['registration_certificate_path' => $this->certificateUpload->store('seller-certificates', 'local')]);

        if ($previous) {
            Storage::disk('local')->delete($previous);
        }

        $this->certificateUpload = null;

        session()->flash('status', __('sellers.profile.certificate_saved'));
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
        $this->authorizeOwner();

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
        $this->authorizeOwner();

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
