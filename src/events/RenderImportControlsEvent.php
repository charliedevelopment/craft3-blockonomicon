<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\events;

use yii\base\Event;

/**
 * Event raised to gather additional import controls to be presented to the user
 * upon block import. If any controls are provided, they will be displayed after pressing
 * the 'Import' button of the Blockonomicon matrix editor, otherwise the user will see
 * the usual confirmation dialog. This is useful for fields that require additional
 * per-import configuration. Furthermore, if any resource bundles need to be registered for
 * the provided controls, they may be registered when handling this event.
 */
class RenderImportControlsEvent extends Event
{
	/**
	 * @var string The unique block handle/namespace, in bracket notation.
	 * Especially useful for ensuring unique input ids and handling nested fields.
	 */
	public $handle;

	/**
	 * @var array The currently exported settings for the field.
	 */
	public $settings;

	/**
	 * @var array A set of prior options used on import, if any.
	 */
	public $cachedoptions = null;

	/**
	 * @var string The HTML controls to display within the modal dialog, if any.
	 */
	public $controls = '';
}
