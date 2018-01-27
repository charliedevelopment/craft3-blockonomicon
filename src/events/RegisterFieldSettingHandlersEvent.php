<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\events;

use yii\base\Event;

/**
 * Event sent to gather conversion functions for field settings.
 */
class RegisterFieldSettingHandlersEvent extends Event
{
	/**
	 * @var array The registered handlers, keyed by the classes they act as handlers for.
	 * Each handler should contain callables of `saveCustomSettings($field, &$settingsarray)` and `loadCustomSettings($field, $settingsarray)`.
	 */
	public $handlers = [];
}