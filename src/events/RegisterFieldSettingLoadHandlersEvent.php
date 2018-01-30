<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\events;

use yii\base\Event;

/**
 * Event sent to gather conversion functions for field settings.
 * Load handlers are registered to allow additional modifications to the loaded field data before a field object is constructed.
 */
class RegisterFieldSettingLoadHandlersEvent extends Event
{
	/**
	 * @var array The registered handlers, keyed by the classes they act as handlers for.
	 * Each handler is a callable of the form `function(&$settingsarray)`.
	 * The &$settingsarray parameter is the array of data that has been loaded for the field.
	 */
	public $handlers = [];
}