<?php

namespace App\Livewire\Sellers;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Seller;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Public, unauthenticated two-step seller registration wizard, followed by
 * a static confirmation step. No login/session system exists yet in this
 * app (see CLAUDE.md), so this component never authenticates anyone — it
 * only creates the Seller + owner User rows (via Seller::register()) and
 * leaves the applicant on a "your application is under review" screen.
 */
class Register extends Component
{
    use WithFileUploads;

    /**
     * 1 = Account (owner), 2 = Company details, 3 = Confirmation.
     */
    public int $step = 1;

    // ---- Step 1 — Account (the eventual owner login) ----

    public string $ownerName = '';

    public string $ownerEmail = '';

    public string $ownerPassword = '';

    public string $ownerPasswordConfirmation = '';

    /**
     * Preferred language for the owner's own account (`users.locale`) —
     * distinct from the site-wide session-based locale switcher buyers use.
     * Options are ordered uz/en/ru (see config('ribbon.user_locales')),
     * deliberately different from the app-wide uz/ru/en order.
     */
    public string $locale = 'uz';

    // ---- Step 2 — Company details ----

    public string $companyName = '';

    /**
     * Cascading geography selects — a seller's service territory. Each
     * reset when its parent changes (see updatedCountryId()/
     * updatedRegionId()), same pattern as
     * Sellers\Products\Create::updatedCategoryId() resetting dependent
     * parameter state when the category changes.
     */
    public ?int $countryId = null;

    public ?int $regionId = null;

    public ?int $cityId = null;

    public string $companyAddress = '';

    public string $companyVatNumber = '';

    public string $companyPhone = '';

    /**
     * Optional company logo — jpg/png only, max 1MB (see register()).
     * Stored under the shared `logos/` folder on the `public` disk (also
     * used by Brand.logo_path — Laravel's store() generates
     * collision-proof hashed filenames, so sharing the folder is safe). A
     * seller may skip this at registration and add one later from
     * /seller/profile (see Sellers\Profile\Index).
     */
    public $companyLogo = null;

    /**
     * Custom attribute names so validation messages read naturally (the
     * default would otherwise print the raw camelCase property name, e.g.
     * "ownerName").
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'ownerName' => __('sellers.full_name_label'),
            'ownerEmail' => __('sellers.email_label'),
            'ownerPassword' => __('sellers.password_label'),
            'ownerPasswordConfirmation' => __('sellers.confirm_password_label'),
            'locale' => __('sellers.language_label'),
            'companyName' => __('sellers.company_name_label'),
            'countryId' => __('sellers.country_label'),
            'regionId' => __('sellers.region_label'),
            'cityId' => __('sellers.city_label'),
            'companyAddress' => __('sellers.address_label'),
            'companyVatNumber' => __('sellers.vat_number_label'),
            'companyPhone' => __('sellers.phone_label'),
            'companyLogo' => __('sellers.company_logo_label'),
        ];
    }

    /**
     * Custom validation messages for the account (step 1) rules, keyed by
     * `field.rule` and pulled from `sellers.validation.*` so errors render
     * in the active locale instead of falling back to the framework's
     * English-only `validation.php` lines.
     *
     * @return array<string, string>
     */
    protected function accountMessages(): array
    {
        return [
            'ownerName.required' => __('sellers.validation.full_name_required'),
            'ownerEmail.required' => __('sellers.validation.email_required'),
            'ownerEmail.email' => __('sellers.validation.email_invalid'),
            'ownerEmail.unique' => __('sellers.validation.email_unique'),
            'ownerPassword.required' => __('sellers.validation.password_required'),
            'ownerPassword.min' => __('sellers.validation.password_min'),
            'ownerPassword.same' => __('sellers.validation.password_mismatch'),
            'ownerPasswordConfirmation.required' => __('sellers.validation.password_required'),
        ];
    }

    /**
     * Custom validation messages for the company (step 2) rules — see
     * {@see accountMessages()}.
     *
     * @return array<string, string>
     */
    protected function companyMessages(): array
    {
        return [
            'companyName.required' => __('sellers.validation.company_name_required'),
            'countryId.required' => __('sellers.validation.country_required'),
            'regionId.required' => __('sellers.validation.region_required'),
            // The "exists" rule here already encodes "belongs to the
            // selected country" (see companyRules()) — one message covers
            // both "not selected" and "doesn't belong to this country".
            'regionId.exists' => __('sellers.validation.region_invalid'),
            'cityId.required' => __('sellers.validation.city_required'),
            'cityId.exists' => __('sellers.validation.city_invalid'),
            'companyAddress.required' => __('sellers.validation.address_required'),
            'companyVatNumber.required' => __('sellers.validation.vat_number_required'),
            'companyPhone.required' => __('sellers.validation.phone_required'),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function accountRules(): array
    {
        return [
            'ownerName' => ['required', 'string', 'max:150'],
            'ownerEmail' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            // `same` compares against another property in the data Livewire
            // validates against (i.e. $this->ownerPasswordConfirmation) —
            // no `_confirmation`-suffixed property naming trick needed.
            'ownerPassword' => ['required', 'string', 'min:8', 'same:ownerPasswordConfirmation'],
            'ownerPasswordConfirmation' => ['required', 'string'],
            'locale' => ['required', 'string', Rule::in(config('ribbon.user_locales'))],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function companyRules(): array
    {
        return [
            'companyName' => ['required', 'string', 'max:150'],
            'countryId' => ['required', 'integer', 'exists:countries,id'],
            // Not just "exists" — the region must actually belong to the
            // selected country (the cascading selects already only ever
            // offer valid combinations, but this guards a stale/tampered
            // request from persisting an inconsistent territory).
            'regionId' => [
                'required',
                'integer',
                Rule::exists('regions', 'id')->where('country_id', $this->countryId),
            ],
            'cityId' => [
                'required',
                'integer',
                Rule::exists('cities', 'id')->where('region_id', $this->regionId),
            ],
            'companyAddress' => ['required', 'string', 'max:500'],
            // Intentionally no format/regex — this is a multi-country B2B
            // marketplace and VAT formats vary; just require it's present,
            // Super Admin vets the value manually during approval.
            'companyVatNumber' => ['required', 'string', 'max:60'],
            'companyPhone' => ['required', 'string', 'max:30'],
            'companyLogo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ];
    }

    /**
     * Clears a staged (not-yet-uploaded) logo choice from step 2 — there's
     * no "existing" logo at registration time, so this simply lets the
     * applicant start over before submitting.
     */
    public function removeCompanyLogo(): void
    {
        $this->companyLogo = null;
    }

    /**
     * Countries for the first cascading select, ordered per CLAUDE.md's
     * `sort_order`.
     */
    #[Computed]
    public function countries()
    {
        return Country::orderBy('sort_order')->get();
    }

    /**
     * Regions scoped to the currently selected country — empty until a
     * country is chosen, driving the second select's disabled/empty state.
     */
    #[Computed]
    public function regions()
    {
        if (! $this->countryId) {
            return collect();
        }

        return Region::where('country_id', $this->countryId)->orderBy('sort_order')->get();
    }

    /**
     * Cities scoped to the currently selected region — empty until a
     * region is chosen, driving the third select's disabled/empty state.
     */
    #[Computed]
    public function cities()
    {
        if (! $this->regionId) {
            return collect();
        }

        return City::where('region_id', $this->regionId)->orderBy('sort_order')->get();
    }

    /**
     * The previously selected region/city are meaningless once the country
     * changes (they belong to a different tree branch) — reset them rather
     * than carrying a stale, inconsistent selection forward. Mirrors
     * Sellers\Products\Create::updatedCategoryId()'s reset-on-parent-change
     * pattern.
     */
    public function updatedCountryId(): void
    {
        $this->regionId = null;
        $this->cityId = null;
        $this->resetErrorBag(['regionId', 'cityId']);
    }

    /**
     * Same reasoning as updatedCountryId(), one level down.
     */
    public function updatedRegionId(): void
    {
        $this->cityId = null;
        $this->resetErrorBag(['cityId']);
    }

    public function continueToCompanyDetails(): void
    {
        $this->validate($this->accountRules(), $this->accountMessages());

        $this->step = 2;
    }

    public function back(): void
    {
        $this->step = 1;
    }

    public function register(): void
    {
        $this->validate($this->companyRules(), $this->companyMessages());

        $logoPath = $this->companyLogo ? $this->companyLogo->store('logos', 'public') : null;

        Seller::register(
            [
                'name' => $this->companyName,
                'address' => $this->companyAddress,
                'country_id' => $this->countryId,
                'region_id' => $this->regionId,
                'city_id' => $this->cityId,
                'vat_number' => $this->companyVatNumber,
                'phone' => $this->companyPhone,
                'logo_path' => $logoPath,
            ],
            [
                'name' => $this->ownerName,
                'email' => $this->ownerEmail,
                'password' => $this->ownerPassword,
                'locale' => $this->locale,
            ],
        );

        $this->step = 3;
    }

    public function render()
    {
        return view('livewire.sellers.register')->layout('layouts.public', [
            'title' => 'Become a Seller',
        ]);
    }
}
