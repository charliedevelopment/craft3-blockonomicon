<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\services;

use Craft;

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
	 * Retrieves the path used for block storage.
	 * @return string The full path to the Blockonomicon storage folder.
	 */
	public function getStoragePath()
	{
		return Craft::$app->getPath()->getStoragePath() . '/blockonomicon';
	}

	/**
	 * Retrieves the path used for block storage.
	 * @return string The full path to the Blockonomicon storage folder.
	 */
	public function getBlockPath()
	{
		return $this->getStoragePath() . '/blocks';
	}

	/**
	 * Retrieves all available Blockonomicon blocks and their associated metadata.
	 * @param bool $force Set to true to force updating the block cache, otherwise defaults to false.
	 * @return array An array, each element being metadata for a block, loaded from its respective json file.
	 */
	public function getBlocks($force = false)
	{
		$blocks = Craft::$app->getCache()->get('blockonomicon_blocks'); // Retrieve our block set cache.
		
		if ($force === true || $blocks === false) {
			$blocks = []; // Storage for all block handles.
			$path = $this->getBlockPath(); // Get block path.
			$dirs = glob($path . '/*', GLOB_ONLYDIR); // Retrieve all blocks in block directory.

			foreach ($dirs as $dir) {
				$blockname = basename($dir);
				$meta = @file_get_contents($dir . '/' . '_' . $blockname . '.json', false, null, 0, 1024 * 512); // Read up to 512k, should be safe.
				if ($meta === false) { // No meta information, not a valid block file.
					$blocks[$blockname] = [
						'state' => 'no-config'
					];
					continue;
				}
				$meta = json_decode($meta, true);
				if ($meta == null) {
					$blocks[$blockname] = [
						'state' => 'bad-config'
					];
					continue;
				}
				$meta['state'] = 'good';
				$blocks[$blockname] = $meta;
			}

			Craft::$app->getCache()->delete('blockonomicon_blocks');
			Craft::$app->getCache()->add('blockonomicon_blocks', $blocks, 21600); // Cache for 6 hours (60 seconds * 60 minutes * 6 hours = 21600).
		}
		
		return $blocks;
	}
}