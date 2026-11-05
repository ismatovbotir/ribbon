<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** The way back in when an admin has lost both their phone and recovery codes. */
class ResetTwoFactor extends Command
{
    protected $signature = 'two-factor:reset {email}';

    protected $description = 'Remove an admin\'s two-factor setup so they enrol again at next sign-in';

    public function handle(TwoFactorService $twoFactor): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        if (! $this->confirm("Remove two-factor for {$user->email}? Anyone with their password can then enrol a new device.")) {
            return self::FAILURE;
        }

        $twoFactor->reset($user);
        Log::warning('Two-factor authentication reset from the console.', ['user_id' => $user->id]);

        $this->info('Done. They will set up two-factor at next sign-in.');

        return self::SUCCESS;
    }
}
