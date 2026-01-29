# Changelog

All notable changes to the `jankx/search-engine` package will be documented in this file.

## [1.0.1] - 2026-01-29
### Changed
- Incremented package version to `1.0.17`.
- Cleaned up `composer.json` by removing `minimum-stability: dev`.

### Added
- Added `disabled` styles for filter labels and inputs when term counts reach zero.
- Added `not-allowed` cursor for disabled filter items in `search-engine.css`.
- **Live Term Counts**: Implemented dynamic term count updates in the UI.
  - Filters now refresh their item counts `(n)` in real-time when keywords or other filters are selected.
  - Implemented "shadow search" logic in AJAX handler to calculate available term counts for active taxonomies (allowing OR-like selection within the same group).
  - Added `updateTermCounts` method in `JankxSearchHub` (TypeScript) to sync counts with the DOM.
- **Query Logic Clarification**: Documented and confirmed the AND/OR relationship for filter queries:
  - **AND** between different taxonomy groups.
  - **OR** within terms of the same taxonomy group.
- **Backend Enhancements**:
  - `TNTSearchEngine`: Now returns `all_ids` in search results to facilitate aggregation and term count calculations.
  - `Handler`: Added `get_term_counts` helper method using optimized SQL queries for better performance.

### Fixed
- Improved synchronization of Selected Filters UI when updating from server-side HTML responses.

## [1.0.0] - 2026-01-28

### Initial Release (Stable Baseline)
- Core search functionality with TNTSearch integration.
- AJAX-based search updates with pagination and sorting.
- Multi-taxonomy filter support.
