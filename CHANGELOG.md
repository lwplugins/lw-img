# Changelog

## [1.0.0] - 2026-08-16

### Added
- Initial release
- Auto-convert non-WebP uploads to WebP via the HelloImg API
- Settings page under the LW Plugins menu (API key, optimization level, EXIF, skip rules)
- Connection test against HelloImg `/v1/account` endpoint
- Graceful fallback when the API is unreachable, the key is invalid, or the balance is exhausted
- Upload event log (last 200 events) with pagination and clear action
- Unit test suite (PHPUnit + Brain Monkey), PHPStan level 5, PHPCS (WPCS) — all wired into CI
