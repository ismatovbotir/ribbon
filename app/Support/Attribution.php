<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Where a visitor came from: UTM params + external referrer. Remembered in the
 * session from the landing page, because by the time a buyer submits a request
 * they have browsed on and the original URL is gone.
 */
class Attribution
{
    private const SESSION_KEY = 'attribution';

    private const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /**
     * Remember this request's attribution. New UTM params replace older ones
     * (the latest campaign wins); a bare referrer only fills an empty slot, so
     * it can't erase a campaign the visitor arrived from.
     */
    public static function capture(Request $request): void
    {
        $found = self::fromRequest($request);

        if ($found['utm'] !== []) {
            $request->session()->put(self::SESSION_KEY, $found);
        } elseif ($found['referrer'] !== null && ! $request->session()->has(self::SESSION_KEY)) {
            $request->session()->put(self::SESSION_KEY, $found);
        }
    }

    /**
     * This request's own attribution, else what the session remembered.
     *
     * @return array{utm: array<string, string>, referrer: ?string}
     */
    public static function current(Request $request): array
    {
        $found = self::fromRequest($request);

        if ($found['utm'] !== [] || $found['referrer'] !== null) {
            return $found;
        }

        return session(self::SESSION_KEY, $found);
    }

    /**
     * @return array{utm: array<string, string>, referrer: ?string}
     */
    private static function fromRequest(Request $request): array
    {
        $utm = [];

        foreach (self::UTM_KEYS as $key) {
            $value = $request->query($key);

            if (is_string($value) && $value !== '') {
                $utm[$key] = Str::limit($value, 100, '');
            }
        }

        return ['utm' => $utm, 'referrer' => self::externalReferrer($request)];
    }

    private static function externalReferrer(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $parts = parse_url($referer);

        if (! isset($parts['host']) || $parts['host'] === $request->getHost()) {
            return null;
        }

        // Origin + path only: query strings can carry tokens or personal data.
        return Str::limit(($parts['scheme'] ?? 'https').'://'.$parts['host'].($parts['path'] ?? ''), 255, '');
    }
}
