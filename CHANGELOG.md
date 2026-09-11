# Changelog

All notable changes to `graystackit/laravel-gdpr-compliance` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- A subject deleted outside the package during its grace period took its whole deletion request down with it: Pass 1 found no subject, marked every row of the request `erased` and left the rows of the other models — orders, addresses, notes — untouched with their PII, and legal-hold rows never reached Pass 2 at all. Pass 1 now falls back to the same key-only ghost subject Pass 2 has always used, so every other model is still processed by its own retention mode. A row is closed as `erased` without processing only when nothing of its model is left.
- A deletion row that closed as `erased` because nothing of its model was left did so without a trace: no `deletion_completed` audit entry and no `PersonalDataErased` event. The audit log skipped a terminal state change, and listeners that clean up copies of the data outside the database never ran for those rows. Both now fire, with `affected_rows` 0 and the reason in the audit context.

## [1.0.2] - 2026-09-11

### Fixed

- Subjects whose own model carries a global scope — multi-tenancy being the common case — were invisible to the package wherever that scope matches nothing, which is the state the queue and the scheduler run in. `requestDeletion()`, the export, `SubjectRecordResolver` and both deletion passes read the subject's own model with a bare `whereKey()` on a fresh query instead of going through its subject scope, so `processDueDeletions()` took the subject for already gone, marked the row `erased` and erased nothing — a `gdpr_deletions` record saying the opposite of the truth. The subject's own model now goes through its registered scope like every other model: the scope is read for the global scopes it removes and the row is selected by primary key. Subject models without a subject scope are unaffected, and a subject model whose scope is written for a *different* subject (answering `1 = 0` for itself) keeps reaching its own row.
- A deletion whose subject exists but no scope reaches is no longer reported as done. `requestDeletion()` throws `SubjectNotReachable` before anything is written; `gdpr:process-deletions` leaves the row `pending_grace`, writes a `deletion_deferred` audit entry, logs an error and exits non-zero, while still processing every other row of the run. `processDueDeletions()` returns the count as `deferred`.
- `whereDeletionPending()` and `whereNotDeletionPending()` threw `SQLSTATE[42883] operator does not exist` on PostgreSQL for every `subject_key_type` other than `bigint`. They compare `gdpr_deletions.subject_id` to the subject's key column, and the two only share a SQL type in a bigint installation. Both sides are now cast to text for the other three types; `bigint` keeps the plain column comparison, and with it the index on `subject_id`.
- The upgrade migration could not be rolled back on PostgreSQL — `down()` failed with `SQLSTATE[42804] column "subject_id" cannot be cast automatically to type bigint`, and `up()` to `uuid` failed the same way. `ColumnDefinition::change()` emits no `USING` clause and PostgreSQL has no assignment cast from a string type back to bigint or uuid. `SubjectKeyType::changeColumn()` now emits the `ALTER TABLE ... USING` PostgreSQL needs and leaves every other driver on `change()`.

## [1.0.1] - 2026-09-11

### Added

- `ConsentPurpose::TalentPool` — keeping an applicant's data on file after the vacancy they applied for is closed. A separate, withdrawable purpose from processing the application itself, which runs on a legitimate interest and has its own deletion deadline.
- Non-numeric subject keys. `config('gdpr.subject_key_type')` (`bigint` | `uuid` | `ulid` | `string`) decides the column type of `subject_id` in all five subject tables and how subject keys are bound in queries. Defaults to `bigint`, so existing installations are unaffected. `string` holds numeric and non-numeric keys side by side, which is what an application needs once subjects with different key types are registered.
- `gdpr-upgrade-migrations` publish tag, carrying a migration that rewrites `subject_id` in `gdpr_consents`, `gdpr_requests`, `gdpr_deletions`, `gdpr_audits` and `gdpr_policy_acceptances` to the configured type. Required when changing `subject_key_type` on an existing installation. It refuses conversions it cannot perform — stored subject keys are application data the package cannot map onto new values — and checks every table before altering any, so a refusal leaves the schema untouched.

### Fixed

- `gdpr:prune` threw on the consents pass — it still queried the pre-1.0.0 `consents` table instead of `gdpr_consents`.
- `ConsentManager::hasConsent()` could return the wrong state when a grant and a withdraw shared a `created_at` timestamp: which of two tied rows `LIMIT 1` returned was up to the driver. It now breaks the tie on `id`, matching the row `gdpr:prune` preserves as the current state.

## [1.0.0] - 2026-06-15

### Added

- Initial release of `graystackit/laravel-gdpr-compliance`.
- Fluent personal-data declaration on Eloquent models via a builder DSL, with registry-driven processing of all PII-holding models from a single config array.
- Subject data export (DSGVO Art. 15) as a structured JSON file via a queue job, and subject data erasure (DSGVO Art. 17) with configurable grace period, three retention modes (`delete`, `anonymize`, `legal_hold`) and deterministic FK-safe processing order.
- Per-purpose consent management (append-only `gdpr_consents` table, cookie-consent helper, middleware) and policy version tracking with subject acceptance records.
- Event-driven audit log (`gdpr_audits`) that records field names, event names and metadata only — never PII values.
- Seven built-in anonymizers (name, email, phone, IP address, address, free text, static text) with custom alias support, plus a package inventory scanner that snapshots `composer.lock` and `package-lock.json` to JSON.
- Four Laravel notifications (deletion requested/cancelled/completed, export ready), three middleware (consent-gated routes, cookie propagation, deletion-pending auth blocking), eight Artisan commands and three queue jobs.
