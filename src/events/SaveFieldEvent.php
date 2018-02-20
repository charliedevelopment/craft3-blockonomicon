<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\events;

use yii\base\Event;

/**
 * Event raised when a field is exported, allowing custom data to be written
 * in addition to or removed from the properties available directly on the base field class.
 */
class SaveFieldEvent extends Event
{
	/**
	 * @var \craft\base\Field The field being saved.
	 */
	public $field;

	/**
	 * @var array The gathered basic field settings, should be modified to match
	 * what would normally be provided to \craft\services\FieldsController::createField()
	 * such as when saving the field editor in the Craft control panel.
	 */
	public $settings;
}
