<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\models;

use charliedev\blockonomicon\Blockonomicon;

use Craft;
use craft\base\Model;
use craft\validators\HandleValidator;

/**
 * A model used for storing/updating properties of an individual block.
 */
class StoredBlock extends Model
{
	/**
	 * @var string The user-friendly name of the block.
	 */
	public $name;

	/**
	 * @var string The previous handle of the block.
	 */
	public $oldhandle;

	/**
	 * @var string The current/new handle of the block.
	 */
	public $handle;

	/**
	 * @var string The long description for the block.
	 */
	public $description;

	/**
	 * @var array The underlying field data stored for the block.
	 */
	public $fields;

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
		return [
			[['handle'], HandleValidator::class],
			[['handle'], 'validateHandleConflicts'],
			[['name', 'handle'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
		];
	}

	/**
	 * Ensures that if the handle is changing, another block doesn't currently use the new handle.
	 * @return void
	 */
	public function validateHandleConflicts()
	{
		if ($this->handle != $this->oldhandle) {
			$blocks = Blockonomicon::getInstance()->blocks->getBlocks();

			foreach ($blocks as $handle => $block) {
				if ($handle == $this->handle) {
					$this->addError('handle', Craft::t('blockonomicon', '“{handle}” handle is already in use.', ['handle' => $this->handle]));
					break;
				}
			}
		}
	}
}
