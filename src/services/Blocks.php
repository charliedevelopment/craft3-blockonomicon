<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\services;

use charliedev\blockonomicon\Blockonomicon;
use charliedev\blockonomicon\events\RegisterFieldSettingHandlersEvent;

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
	public function getMatrixFields(): array
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
	public function getStoragePath(): string
	{
		return Craft::$app->getPath()->getStoragePath() . '/blockonomicon';
	}

	/**
	 * Retrieves the path used for block storage.
	 * @return string The full path to the Blockonomicon storage folder.
	 */
	public function getBlockPath(): string
	{
		return $this->getStoragePath() . '/blocks';
	}

	/**
	 * Retrieves all available Blockonomicon blocks and their associated metadata.
	 * @param bool $force Set to true to force updating the block cache, otherwise defaults to false.
	 * @return array An array, each element being metadata for a block, loaded from its respective json file.
	 */
	public function getBlocks(bool $force = false): array
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

	/**
	 * Creates an array representing a block and all of its associated fields and settings.
	 * @param \Craft\models\MatrixBlockType $block The block to create a representation of.
	 * @return array The block data, as an array.
	 */
	public function getBlockData(\Craft\models\MatrixBlockType $block): array
	{
		$blockdata = [
			'name' => $block->name,
			'handle' => $block->handle,
			'fields' => [],
		];

		foreach ($block->getFields() as $field) {
			$fielddata = $this->getFieldData($field);
			if ($fielddata === null) {
				return null;
			}
			$blockdata['fields'][$fielddata['handle']] = $fielddata;
		}

		return $blockdata;
	}
	
	/**
	 * Creates an array representing a field and all of its associated settings.
	 * @param \Craft\base\Field $field The field to create a representation of.
	 * @return array The field data, as an array.
	 */
	public function getFieldData(\Craft\base\Field $field): array
	{
		$settings = [
			'name' => $field->name,
			'handle' => $field->handle,
			'translationMethod' => $field->translationMethod,
			'instructions' => $field->instructions,
			'required' => $field->required,
			'type' => get_class($field),
			'settings' => $field->getSettings(),
		];

		$this->modifyCustomFieldSettings($field, $settings);

		return $settings;
	}

	/**
	 * Stores a block definition in plugin storage, creating additional supporting files if necessary.
	 */
	public function saveBlockData($blockdata)
	{
		$blockhandle = $blockdata['handle'];

		// Store the base block path.
		$blockpath = $this->getBlockPath() . '/' . $blockhandle;

		// Ensure the directory to save the definition to already exists, determine if the directory exists.
		$newblock = !is_dir($blockpath);

		if ($newblock) { // This is a new block, create a directory and the rest of the supporting files.
			$created = FileHelper::createDirectory($blockpath);
			if (!$created) {
				return Craft::t('blockonomicon', 'Could not create directory for block.');
			}

			// Render base CSS file for the block.
			$outfile = @fopen($blockpath . '/' . $blockhandle . '.css', 'w');
			if ($outfile === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}
			if (@fwrite($outfile, Craft::$app->getView()->renderTemplate('blockonomicon/_base.css', $blockdata)) === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}
			if (@fclose($outfile) === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}

			// Render base JS file for the block.
			$outfile = @fopen($blockpath . '/' . $blockhandle . '.js', 'w');
			if ($outfile === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}
			if (@fwrite($outfile, Craft::$app->getView()->renderTemplate('blockonomicon/_base.js', $blockdata)) === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}
			if (@fclose($outfile) === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}

			// Render base HTML file for the block.
			$outfile = @fopen($blockpath . '/' . $blockhandle . '.html', 'w');
			if ($outfile === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}
			if (@fwrite($outfile, Craft::$app->getView()->renderTemplate('blockonomicon/_base.html', $blockdata)) === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}
			if (@fclose($outfile) === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}
		} else { // This is not a new block, attempt to copy a backup of the previous configuration file.
			@copy($blockpath . '/_' . $blockhandle . '.json', $blockpath . '/_' . $blockhandle . '.json.bak');
		}

		// Write the block definition file.
		$outfile = @fopen($blockpath . '/_' . $blockhandle . '.json', 'w');
		if ($outfile === false) {
			return Craft::t('blockonomicon', 'Could not write to block settings file.');
		}

		if (@fwrite($outfile, json_encode($blockdata, JSON_PRETTY_PRINT)) === false) {
			return Craft::t('blockonomicon', 'Could not write to block settings file.');
		}
		if (@fclose($outfile) === false) {
			return Craft::t('blockonomicon', 'Could not write to block settings file.');
		}

		return true;
	}

	/**
	 * Allows additional modification to the settings array on field export, based on registered handlers.
	 */
	private function modifyCustomFieldSettings($field, &$settings)
	{
		$event = new RegisterFieldSettingHandlersEvent();
		Blockonomicon::getInstance()->trigger(Blockonomicon::EVENT_REGISTER_FIELD_SETTING_HANDLERS, $event);

		$fieldclass = get_class($field);
		if (isset($event->handlers[$fieldclass])) {
			$event->handlers[$fieldclass]['saveCustomSettings']($field, $settings);
		}
	}
}