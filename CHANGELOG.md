# Changelog

## Unreleased

## 1.1.6 - 2019-12-19

### Fixed
- Excluded the `contentTable` setting from export/import, as it being provided explicitly can cause a database table to not be generated for the field.

## 1.1.5 - 2019-02-26

### Fixed
- When updating a matrix, it is properly prepared for saving. As of Craft 3.1 this performs necessary setup for the project.yaml system.

## 1.1.4 - 2018-11-15

### Fixed
- Blocks marked as out of sync will now be properly saved when sorting.
- Updated cached import option reference to use the correct block and field reference.

### Added
- Import configuration mechanism has been extended to provide the handle of the block being imported, to help ensure proper unique IDs for import controls.

### Changed
- Internal adapters for native fields changed from using the `blockHandle` misnomer to a more apt `fieldHandle`.

## 1.1.3 - 2018-06-19

### Fixed
- Added leading slashes to css/js tag output so these route correctly on non-root pages.
- Typo regarding configuration location in settings page.

## 1.1.2 - 2018-06-06

### Added
- Warnings that indicate when a storage/block folder have gone missing, as they are sometimes overlooked during migration.

### Changed
- Documentation migrated from internal plugin page to GitHub readme.

### Fixed
- `.gitignore` file added to avoid committing cached/temporary files.
- Set correct `license` option in the `composer.json`.

## 1.1.1 - 2018-03-15

### Fixed
- Reworked some code that was relying on a php 7.1 feature, causing some compatibility issues.

## 1.1.0 - 2018-03-08

### Added
- Support for Matrix fields (accessible through the use of adapters).

### Fixed
- Re-importing the first block in a matrix could drop a block's definition in specific ordering cases.

## 1.0.2 - 2018-03-06

### Added
- Minor documentation notes about third-party field support.

## 1.0.1 - 2018-03-05

### Fixed
- Bad block configuration with the same handle of an existing block causing matrix editor templating errors.
- Missing `storage/blockonomicon` folder causing strange behavior.
- Deprecation error in SettingsController.

## 1.0.0 - 2018-03-02

The initial release of the Blockonomicon plugin.

### Added
- Block overview panel, allowing review of currently exported/installed blocks.
- Matrix editor panel, allowing export, import, and reordering of blocks.
- Block editor panel, allowing review of block fields and editing basic properties.
- Templating functions to render blocks on the frontend.
- Example system that sets up some initial test data to learn about the plugin.
