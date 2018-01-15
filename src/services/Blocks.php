<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\services;

use Craft;
use craft\helpers\FileHelper;

use yii\base\Component;

/**
 * The main Blockonomicon service.
 */
class Blocks extends Component {

	/**
	 * Retrieves a list of all Matrix fields.
	 * @return array An array of all available Matrix fields in the Craft install.
	 */
	public function getMatrixFields()
	{
		$fields = Craft::$app->getFields()->getAllFields(); // Start with a list of all fields.

		$fields = array_filter($fields, function($val) { // Remove any field that isn't a Matrix.
			return $val instanceof \Craft\Fields\Matrix;
		});

		$fields = array_reduce($fields, function($in, $val) { // Make sure the fields are keyed by ID.
			$in[$val->id] = $val;
			return $in;
		}, []);

		return $fields;
	}

	/**
	 * Retrieves all available Blockonomicon blocks and their associated metadata.
	 * @return array An array, each element being metadata for a block, loaded from its respective json file.
	 */
	public function getBlocks()
	{
		$path = Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'blockonomicon';

		FileHelper::createDirectory($path);

		$blocks = [];

		return $blocks;
	}
}