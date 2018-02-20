<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\events;

use yii\base\Event;

/**
 * Event raised when a field is imported, either over an existing field or as a
 * brand new one. Also comes with additional per-import options set by the user.
 */
class LoadFieldEvent extends Event
{
	/**
	 * @var \craft\base\Field The exiting field, if one exists.
	 */
	public $field;

	/**
	 * @var array The exported settings for the field, should be modified to match
	 * what would normally be provided to \craft\services\FieldsController::createField()
	 * such as when saving the field editor in the Craft control panel.
	 */
	public $settings;

	/**
	 * @var array The additional user-provided options for the field.
	 */
	public $importoptions;
}
