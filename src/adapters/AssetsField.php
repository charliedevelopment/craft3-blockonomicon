<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\adapters;

use charliedev\blockonomicon\Blockonomicon;
use charliedev\blockonomicon\events\RenderImportControlsEvent;
use charliedev\blockonomicon\events\SaveFieldEvent;
use charliedev\blockonomicon\events\LoadFieldEvent;

use Craft;

use yii\base\Event;

/**
 * Blockonomicon adapter for built-in Craft Assets fields.
 * Prevents site and source-specific properties from being included in the exported
 * data, and provides properties that can be set on import to replace them.
 */
class AssetsField
{
	/**
	 * Binds to necessary event handlers.
	 */
	public static function setup()
	{
		// On export, remove source and site-specific properties.
		Event::on(
			Blockonomicon::class,
			Blockonomicon::EVENT_SAVE_FIELD,
			function(SaveFieldEvent $event) {
				
				// Ignore any fields that are not Assets fields.
				if (get_class($event->field) != \craft\fields\Assets::class) {
					return;
				}

				unset($event->settings['typesettings']['defaultUploadLocationSource']);
				unset($event->settings['typesettings']['defaultUploadLocationSubpath']);
				unset($event->settings['typesettings']['singleUploadLocationSource']);
				unset($event->settings['typesettings']['singleUploadLocationSubpath']);
				unset($event->settings['typesettings']['sources']);
				unset($event->settings['typesettings']['source']);
				unset($event->settings['typesettings']['targetSiteId']);
				unset($event->settings['typesettings']['localizeRelations']);
			}
		);

		// On import, re-add source and site-specific properties from the user-supplied options.
		Event::on(
			Blockonomicon::class,
			Blockonomicon::EVENT_LOAD_FIELD,
			function(LoadFieldEvent $event) {
				
				// Ignore any fields that are not Assets fields.
				if ($event->settings['type'] != \craft\fields\Assets::class) {
					return;
				}

				$event->settings['typesettings']['defaultUploadLocationSource'] = $event->importoptions['defaultUploadLocationSource'] ?? '';
				$event->settings['typesettings']['defaultUploadLocationSubpath'] = $event->importoptions['defaultUploadLocationSubpath'] ?? '';
				$event->settings['typesettings']['singleUploadLocationSource'] = $event->importoptions['singleUploadLocationSource'] ?? '';
				$event->settings['typesettings']['singleUploadLocationSubpath'] = $event->importoptions['singleUploadLocationSubpath'] ?? '';
				$event->settings['typesettings']['sources'] = $event->importoptions['sources'] ?? [];
				if ($event->importoptions['useTargetSite'] ?? false) {
					$event->settings['typesettings']['targetSiteId'] = $event->importoptions['targetSiteId'] ?? '';
				}
				$event->settings['typesettings']['localizeRelations'] = $event->importoptions['localizeRelations'] ?? '';
			}
		);

		// Generate controls to set data stripped on block export.
		Event::on(
			Blockonomicon::class,
			Blockonomicon::EVENT_RENDER_IMPORT_CONTROLS,
			function(RenderImportControlsEvent $event) {
				
				// Ignore any fields that are not Assets fields.
				if ($event->settings['type'] != \craft\fields\Assets::class) {
					return;
				}

				$sourceoptions = [];
				foreach (\craft\elements\Asset::sources('settings') as $key => $volume) {
					if (!isset($volume['heading'])) {
						$sourceoptions[] = [
							'label' => $volume['label'],
							'value' => $volume['key'],
						];
					}
				}

				$event->controls = Craft::$app->getView()->renderTemplate('blockonomicon/_adapters/AssetsFieldAdapter.html', [
					'blockHandle' => $event->handle,
					'settings' => $event->settings,
					'cachedOptions' => $event->cachedoptions,
					'sourceOptions' => $sourceoptions,
				]);
			}
		);
	}
}
