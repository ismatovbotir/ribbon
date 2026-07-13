<?php

namespace App\Models;

use App\Jobs\NotifyTelegramOfNewSeller;
use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'address', 'country_id', 'region_id', 'city_id', 'vat_number', 'phone', 'logo_path', 'status', 'approved_by', 'approved_at', 'rejected_reason'])]
class Seller extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Every Seller row is created `pending` by construction (see
     * register()) — notify staff on Telegram unconditionally whenever a new
     * one shows up, no extra status check needed.
     */
    #[Boot]
    protected static function bootSeller(): void
    {
        static::created(function (Seller $seller) {
            NotifyTelegramOfNewSeller::dispatch($seller);
        });
    }

    /**
     * The staff user who approved this seller.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The country this seller's service territory is in.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * The region this seller's service territory is in.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * The city this seller's service territory is in.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * The products listed by this seller.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The users belonging to this seller company, with their role (Owner/
     * Employee) within it carried on the seller_user pivot row.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'seller_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * Register a new seller company together with its Owner user, in a
     * single transaction: creates the Seller (status left unset so the
     * migration's `pending` DB default applies), the User (password is
     * expected plain-text — the model's `password` => `hashed` cast hashes
     * it on save), and the seller_user pivot row linking them under the
     * seeded `owner` role. $sellerData carries the full company-details
     * step (name, address, country_id/region_id/city_id service territory,
     * vat_number, phone) — all required, since a Seller row is only ever
     * created once both registration steps are complete. `logo_path` is the
     * one optional key in $sellerData — a seller may skip uploading a logo
     * at registration and add one later from their dashboard.
     */
    public static function register(array $sellerData, array $ownerData): self
    {
        return DB::transaction(function () use ($sellerData, $ownerData) {
            $seller = static::create([
                'name' => $sellerData['name'],
                'address' => $sellerData['address'],
                'country_id' => $sellerData['country_id'],
                'region_id' => $sellerData['region_id'],
                'city_id' => $sellerData['city_id'],
                'vat_number' => $sellerData['vat_number'],
                'phone' => $sellerData['phone'],
                'logo_path' => $sellerData['logo_path'] ?? null,
            ]);

            $owner = User::create($ownerData);

            $ownerRole = Role::where('type', 'seller')->where('slug', 'owner')->first();

            $seller->users()->attach($owner->id, ['role_id' => $ownerRole->id]);

            return $seller;
        });
    }

    /**
     * Add an Employee to this seller company: creates the User (same
     * password-hashing behavior as register() — plain-text expected, the
     * model's `password` => `hashed` cast hashes it on save) and attaches
     * them via the seller_user pivot under the seeded `employee` role.
     * Mirrors register()'s User::create() + attach() pattern exactly, just
     * without the paired Seller-creation step (the seller already exists
     * here — only Owner-authorized code paths should call this, see
     * User::isOwnerOf()).
     */
    public function addEmployee(array $userData): User
    {
        return DB::transaction(function () use ($userData) {
            $employee = User::create($userData);

            $employeeRole = Role::where('type', 'seller')->where('slug', 'employee')->first();

            $this->users()->attach($employee->id, ['role_id' => $employeeRole->id]);

            return $employee;
        });
    }

    /**
     * Approve this seller, recording which admin acted and when.
     */
    public function approve(User $admin): void
    {
        $this->status = 'approved';
        $this->approved_by = $admin->id;
        $this->approved_at = now();
        $this->rejected_reason = null;
        $this->save();
    }

    /**
     * Reject this seller with a reason, recording which admin acted and
     * when. approved_by/approved_at are set here too, despite the name —
     * they track "who last acted on this application and when", not
     * literally "who approved it", so a rejection updates them just the
     * same as an approval would.
     */
    public function reject(User $admin, string $reason): void
    {
        $this->status = 'rejected';
        $this->approved_by = $admin->id;
        $this->approved_at = now();
        $this->rejected_reason = $reason;
        $this->save();
    }

    /**
     * Block an already-approved (active) seller, recording which admin acted
     * and when. Distinct from reject(): rejection is an initial-review
     * decision (status pending -> rejected); suspension is a punitive action
     * against a seller that was already approved and active. Reuses the
     * `rejected_reason` column to store the block reason rather than adding
     * a parallel `suspended_reason` column for what is conceptually the same
     * "why is this seller not active" note.
     */
    public function suspend(User $admin, string $reason): void
    {
        $this->status = 'suspended';
        $this->approved_by = $admin->id;
        $this->approved_at = now();
        $this->rejected_reason = $reason;
        $this->save();
    }
}
