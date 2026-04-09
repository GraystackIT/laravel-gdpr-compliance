<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Models\GdprPolicyAcceptance;
use GraystackIt\Gdpr\Models\GdprPolicyVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

/**
 * Resolves policy URLs (from an explicit URL, a named route, or config)
 * and handles policy acceptance tracking.
 */
class PolicyLinkManager
{
    /**
     * @param  array<string, array{url?: ?string, route?: ?string}>  $links
     */
    public function __construct(protected array $links = []) {}

    /**
     * Resolve a URL for the given slug (e.g. 'privacy', 'imprint').
     */
    public function urlFor(string $slug): ?string
    {
        $entry = $this->links[$slug] ?? null;
        if ($entry === null) {
            return null;
        }

        if (! empty($entry['url'])) {
            return $entry['url'];
        }

        if (! empty($entry['route']) && Route::has($entry['route'])) {
            return route($entry['route']);
        }

        return null;
    }

    public function latestVersion(string $slug): ?GdprPolicyVersion
    {
        return GdprPolicyVersion::query()
            ->where('slug', $slug)
            ->latest('published_at')
            ->first();
    }

    /**
     * Record that the subject has accepted the given policy version.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordAcceptance(
        Model $subject,
        GdprPolicyVersion $version,
        array $context = [],
    ): GdprPolicyAcceptance {
        return GdprPolicyAcceptance::create([
            'gdpr_policy_version_id' => $version->id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'context' => $context !== [] ? $context : null,
        ]);
    }

    public function hasAccepted(Model $subject, string $slug, ?string $minVersion = null): bool
    {
        $query = GdprPolicyAcceptance::query()
            ->whereHas('policyVersion', function ($q) use ($slug, $minVersion) {
                $q->where('slug', $slug);
                if ($minVersion !== null) {
                    $q->where('version', '>=', $minVersion);
                }
            })
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey());

        return $query->exists();
    }
}
