<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\events;

use yii\base\Event;

/**
 * Event sent to gather conversion functions for field settings.
 * Save handlers are registered to allow additional modifications to the exported
 * field arrays that will be saved on block export.
 */
class RegisterFieldSettingSaveHandlersEvent extends Event
{
	/**
	 * @var array The registered handlers, keyed by the classes they act as handlers for.
	 * Each handler is a callable of the form `function($field, &$settingsarray)`.
	 * The $field parameter is the field being saved, and the &$settingsarray parameter
	 * is the array of data that will be saved for the field.
	 */
	public $handlers = [];
}
