<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\adapters;

use charliedev\blockonomicon\Blockonomicon;
use charliedev\blockonomicon\events\SaveFieldEvent;

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
			}
		);
	}
}
