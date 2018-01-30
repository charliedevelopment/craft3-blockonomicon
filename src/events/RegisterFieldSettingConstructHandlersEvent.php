<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\events;

use yii\base\Event;

/**
 * Event sent to gather conversion functions for field settings.
 * Construct handlers are registered to allow additional modifications to a field class after it has been constructed.
 */
class RegisterFieldSettingConstructHandlersEvent extends Event
{
	/**
	 * @var array The registered handlers, keyed by the classes they act as handlers for.
	 * Each handler is a callable of the form `function($field, $settingsarray)`.
	 * The $field parameter is the new/existing field, and the $settingsarray parameter is the array of data loaded for the field.
	 */
	public $handlers = [];
}