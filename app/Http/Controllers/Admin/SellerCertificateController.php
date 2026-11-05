<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way to reach a seller's registration certificate. The file is on the
 * private disk, and is always served as a download so it never renders on this origin.
 */
class SellerCertificateController extends Controller
{
    public function __invoke(Seller $seller): StreamedResponse
    {
        Gate::authorize('admin-permission', 'sellers.moderate');

        $path = $seller->registration_certificate_path;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download(
            $path,
            'certificate-'.$seller->id.'.'.pathinfo($path, PATHINFO_EXTENSION),
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }
}
