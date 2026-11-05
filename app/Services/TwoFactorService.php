<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTwoFactor;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor authentication for admin staff. Whether a session has passed
 * it is stored against the user id in the session, and enforced both by the
 * admin.auth middleware and by a Gate::before check, because route middleware
 * doesn't run on Livewire's update endpoint.
 */
class TwoFactorService
{
    private const SESSION_KEY = 'two_factor_passed';

    private const RECOVERY_CODE_COUNT = 8;

    /** One 30-second step either side, for clock drift. */
    private const WINDOW = 1;

    public function __construct(private Google2FA $google2fa) {}

    /** True while this admin's session still has to pass 2FA. */
    public function blocks(User $user): bool
    {
        return config('ribbon.admin_2fa_required')
            && $user->isAdmin()
            && session(self::SESSION_KEY) !== $user->id;
    }

    public function isConfirmed(User $user): bool
    {
        return UserTwoFactor::where('user_id', $user->id)->whereNotNull('confirmed_at')->exists();
    }

    /** The in-progress setup, creating a new secret if none exists yet. */
    public function pendingFor(User $user): UserTwoFactor
    {
        return UserTwoFactor::firstOrCreate(
            ['user_id' => $user->id],
            ['secret' => $this->google2fa->generateSecretKey(32)],
        );
    }

    public function qrSvg(User $user, UserTwoFactor $twoFactor): string
    {
        $url = $this->google2fa->getQRCodeUrl('Ribbon', $user->email, $twoFactor->secret);

        return (new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd)))->writeString($url);
    }

    /**
     * Confirms setup with a first valid code.
     *
     * @return array<int, string>|null The recovery codes, shown only this once.
     */
    public function confirm(User $user, string $code): ?array
    {
        return DB::transaction(function () use ($user, $code) {
            $twoFactor = UserTwoFactor::where('user_id', $user->id)->lockForUpdate()->first();

            if (! $twoFactor || $twoFactor->confirmed_at) {
                return null;
            }

            $timestep = $this->verifyTotp($twoFactor, $code);

            if ($timestep === null) {
                return null;
            }

            $codes = collect(range(1, self::RECOVERY_CODE_COUNT))
                ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
                ->all();

            $twoFactor->update([
                'confirmed_at' => now(),
                'last_used_timestep' => $timestep,
                'recovery_codes' => array_map($this->hashCode(...), $codes),
            ]);

            return $codes;
        });
    }

    /** Checks a login code, or a recovery code which is then used up. */
    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $twoFactor = UserTwoFactor::where('user_id', $user->id)->whereNotNull('confirmed_at')->lockForUpdate()->first();

            if (! $twoFactor) {
                return false;
            }

            $timestep = $this->verifyTotp($twoFactor, $code);

            if ($timestep !== null) {
                $twoFactor->update(['last_used_timestep' => $timestep]);

                return true;
            }

            $hash = $this->hashCode($code);
            $codes = $twoFactor->recovery_codes ?? [];

            if (! in_array($hash, $codes, true)) {
                return false;
            }

            $twoFactor->update(['recovery_codes' => array_values(array_diff($codes, [$hash]))]);

            return true;
        });
    }

    /** New session id on the privilege change, as on login. */
    public function markPassed(User $user): void
    {
        session()->regenerate();
        session()->put(self::SESSION_KEY, $user->id);
    }

    /** Removes 2FA, so the user sets it up again at next sign-in. */
    public function reset(User $user): void
    {
        UserTwoFactor::where('user_id', $user->id)->delete();
    }

    private function verifyTotp(UserTwoFactor $twoFactor, string $code): ?int
    {
        $digits = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{6}$/', $digits)) {
            return null;
        }

        $timestep = $this->google2fa->verifyKeyNewer($twoFactor->secret, $digits, $twoFactor->last_used_timestep, self::WINDOW);

        return $timestep === false ? null : ($timestep === true ? $this->google2fa->getTimestamp() : $timestep);
    }

    private function hashCode(string $code): string
    {
        return hash('sha256', Str::upper(trim($code)));
    }
}
