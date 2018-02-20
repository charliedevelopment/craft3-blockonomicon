<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\adapters;

use charliedev\blockonomicon\Blockonomicon;
use charliedev\blockonomicon\events\RenderImportControlsEvent;
use charliedev\blockonomicon\events\SaveFieldEvent;

use Craft;
use craft\elements\Asset;

use yii\base\Event;

/**
 * Blockonomicon adapter for built-in Craft Asset fields.
 * Prevents site and source-specific properties from being included in the exported
 * data, and provides properties that can be set on import to replace them.
 */
class AssetField
{
	/**
	 * Binds to necessary event handlers.
	 */
	public static function setup()
	{
		// On save, remove source and site-specific properties.
		Event::on(
			Blockonomicon::class,
			Blockonomicon::EVENT_SAVE_FIELD,
			function(SaveFieldEvent $event) {
				
				// Ignore any fields that are not Asset fields.
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

		Event::on(
			Blockonomicon::class,
			Blockonomicon::EVENT_RENDER_IMPORT_CONTROLS,
			function(RenderImportControlsEvent $event) {
				
				// Ignore any fields that are not Asset fields.
				if ($event->settings['type'] != \craft\fields\Assets::class) {
					return;
				}

				$sourceoptions = [];
				foreach (Asset::sources('settings') as $key => $volume) {
					if (!isset($volume['heading'])) {
						$sourceoptions[] = [
							'label' => $volume['label'],
							'value' => $volume['key'],
						];
					}
				}

				$event->controls = Craft::$app->getView()->renderTemplate('blockonomicon/_adapters/AssetFieldAdapter.html', [
					'blockHandle' => $event->handle,
					'field' => $event->field,
					'settings' => $event->settings,
					'sourceOptions' => $sourceoptions,
				]);
			}
		);
	}
}
