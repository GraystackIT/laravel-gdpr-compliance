<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Commands;

use GraystackIt\Gdpr\Models\Consent;
use GraystackIt\Gdpr\Models\GdprAudit;
use GraystackIt\Gdpr\Models\GdprPolicyAcceptance;
use GraystackIt\Gdpr\Models\GdprRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Time-based pruning for append-only tables (Decision #24) plus
 * notification_email cleanup (Decision #27).
 *
 * Retention defaults to 3 years (per § 195 BGB / § 1489 ABGB and DPA
 * guidance). Configurable per table via config('gdpr.retention').
 *
 * Special case: consents prune preserves the latest row per
 * (subject, purpose) as the current state.
 */
class GdprPruneCommand extends Command
{
    protected $signature = 'gdpr:prune
        {--dry-run : Count affected rows without deleting}
        {--table= : Restrict to one of: audits, consents, policy_acceptances, notification_emails}';

    protected $description = 'Prune old GDPR records and wipe stale notification emails.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->option('table');

        $auditDays = (int) (config('gdpr.retention.audits_days') ?? 1095);
        $consentsDays = (int) (config('gdpr.retention.consents_days') ?? 1095);
        $policyDays = (int) (config('gdpr.retention.policy_acceptances_days') ?? 1095);
        $emailDays = (int) (config('gdpr.retention.notification_email_days') ?? 7);

        if ($only === null || $only === 'audits') {
            $cutoff = now()->subDays($auditDays);
            $count = GdprAudit::query()->where('created_at', '<', $cutoff)->count();
            if (! $dryRun && $count > 0) {
                GdprAudit::query()->where('created_at', '<', $cutoff)->delete();
            }
            $this->line(sprintf('gdpr_audits older than %d days: %d', $auditDays, $count));
        }

        if ($only === null || $only === 'consents') {
            // Preserve latest row per (subject_type, subject_id, purpose).
            $latestIds = DB::table('consents')
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('subject_type', 'subject_id', 'purpose')
                ->pluck('id');

            $cutoff = now()->subDays($consentsDays);
            $count = Consent::query()
                ->whereNotIn('id', $latestIds)
                ->where('created_at', '<', $cutoff)
                ->count();
            if (! $dryRun && $count > 0) {
                Consent::query()
                    ->whereNotIn('id', $latestIds)
                    ->where('created_at', '<', $cutoff)
                    ->delete();
            }
            $this->line(sprintf('historical consents older than %d days: %d', $consentsDays, $count));
        }

        if ($only === null || $only === 'policy_acceptances') {
            $cutoff = now()->subDays($policyDays);
            $count = GdprPolicyAcceptance::query()->where('accepted_at', '<', $cutoff)->count();
            if (! $dryRun && $count > 0) {
                GdprPolicyAcceptance::query()->where('accepted_at', '<', $cutoff)->delete();
            }
            $this->line(sprintf('gdpr_policy_acceptances older than %d days: %d', $policyDays, $count));
        }

        if ($only === null || $only === 'notification_emails') {
            $cutoff = now()->subDays($emailDays);
            $count = GdprRequest::query()
                ->whereNotNull('notification_email')
                ->whereIn('status', ['completed', 'cancelled', 'failed'])
                ->where('updated_at', '<', $cutoff)
                ->count();

            if (! $dryRun && $count > 0) {
                GdprRequest::query()
                    ->whereNotNull('notification_email')
                    ->whereIn('status', ['completed', 'cancelled', 'failed'])
                    ->where('updated_at', '<', $cutoff)
                    ->update(['notification_email' => null]);
            }
            $this->line(sprintf('notification_email wipes (terminal > %d days): %d', $emailDays, $count));
        }

        if ($dryRun) {
            $this->info('Dry run — no rows modified.');
        }

        return self::SUCCESS;
    }
}
