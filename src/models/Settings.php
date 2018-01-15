<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\models;

use Craft;
use craft\base\Model;

/**
 * The plugin settings model for the Fallback Site plugin.
 */
class Settings extends Model
{
	/**
	 * @inheritdoc
	 * @see yii\base\BaseObject
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Model
	 */
	public function rules()
	{
		return [];
	}
}
