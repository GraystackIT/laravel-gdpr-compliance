# Changelog

All notable changes to `graystackit/laravel-gdpr-compliance` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-06-15

### Added

- Initial release of `graystackit/laravel-gdpr-compliance`.
- Fluent personal-data declaration on Eloquent models via a builder DSL, with registry-driven processing of all PII-holding models from a single config array.
- Subject data export (DSGVO Art. 15) as a structured JSON file via a queue job, and subject data erasure (DSGVO Art. 17) with configurable grace period, three retention modes (`delete`, `anonymize`, `legal_hold`) and deterministic FK-safe processing order.
- Per-purpose consent management (append-only `gdpr_consents` table, cookie-consent helper, middleware) and policy version tracking with subject acceptance records.
- Event-driven audit log (`gdpr_audits`) that records field names, event names and metadata only — never PII values.
- Seven built-in anonymizers (name, email, phone, IP address, address, free text, static text) with custom alias support, plus a package inventory scanner that snapshots `composer.lock` and `package-lock.json` to JSON.
- Four Laravel notifications (deletion requested/cancelled/completed, export ready), three middleware (consent-gated routes, cookie propagation, deletion-pending auth blocking), eight Artisan commands and three queue jobs.
