<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\services;

use charliedev\blockonomicon\Blockonomicon;
use charliedev\blockonomicon\events\RegisterFieldSettingSaveHandlersEvent;
use charliedev\blockonomicon\events\RegisterFieldSettingLoadHandlersEvent;

use Craft;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\FileHelper;
use craft\base\FieldInterface;
use craft\web\View;

use yii\base\Component;
use yii\base\Event;

/**
 * The main Blockonomicon service.
 */
class Blocks extends Component
{
	public function init()
	{
		// Route template requests for frontend Blockonomicon resoruces.
		Event::on(
			View::class,
			View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
			function (RegisterTemplateRootsEvent $event) {
				$event->roots['blockonomicon_storage'] = Blockonomicon::getInstance()->blocks->getStoragePath();
			}
		);

		parent::init();
	}

	/**
	 * Retrieves a list of all Matrix fields.
	 * @return array An array of all available Matrix fields in the Craft install.
	 */
	public function getMatrixFields(): array
	{
		$fields = Craft::$app->getFields()->getAllFields(); // Start with a list of all fields.

		// Remove any field that isn't a Matrix.
		$fields = array_filter($fields, function ($val) {
			return $val instanceof \Craft\Fields\Matrix;
		});

		// Make sure the fields are keyed by ID.
		$fields = array_reduce($fields, function ($in, $val) {
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
		$path = Blockonomicon::getInstance()->getConfig('blockStorage');
		if ($path != null) {
			return $path;
		} else {
			return $this->getStoragePath() . '/blocks';
		}
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
	 * @param \craft\base\Field $field The field to create a representation of.
	 * @return array The field data, as an array.
	 */
	public function getFieldData(\Craft\base\Field $field): array
	{
		$settings = [
			'type' => get_class($field),
			'name' => $field->name,
			'handle' => $field->handle,
			'instructions' => $field->instructions,
			'required' => $field->required,
			'translationMethod' => $field->translationMethod,
			'translationKeyFormat' => $field->translationKeyFormat,
			'typesettings' => $field->getSettings(),
		];

		// Allow additional transformations to be made to settings for fields before returning.
		$event = new RegisterFieldSettingSaveHandlersEvent();
		Blockonomicon::getInstance()->trigger(Blockonomicon::EVENT_REGISTER_FIELD_SETTING_SAVE_HANDLERS, $event); // Gather handlers.

		// Find a handler for this field, if one exists, and run it.
		$fieldclass = get_class($field);
		if (isset($event->handlers[$fieldclass])) {
			$event->handlers[$fieldclass]($field, $settings);
		}

		return $settings;
	}

	/**
	 * Creates/updates a block on a matrix.
	 * @param \craft\fields\Matrix $matrix The matrix to attach the block to.
	 * @param array $blockdata An array containing exported block data.
	 * @param int $order The 0-indexed integer position where the block should be created within the matrix.
	 */
	public function rebuildBlock(\craft\fields\Matrix $matrix, array $blockdata, int $order)
	{
		$transaction = Craft::$app->getDb()->beginTransaction();

		// If this is a new block, not updating an existing block.
		$blockhandle = $blockdata['handle'];

		// Get the existing block types, extract the existing block from the array, if one exists.
		$blocktypes = $matrix->getBlockTypes();
		$block = null;
		$blocktypes = array_reduce($blocktypes, function ($in, $val) use ($blockhandle) {
			if ($val->handle == $blockhandle) {
				$block = $val;
			} else {
				$in[] = $val;
			}
			return $in;
		}, []);

		// Make sure the order is valid.
		if ($order < 0 || $order > count($blocktypes)) {
			return '`order` out of range.';
		}

		if ($block) { // Block already exists, update fields.
			// Store a list of the block fields, keyed by handle.
			$blockfields = $block->getFields();
			$blockfields = array_reduce($blockfields, function ($in, $val) {
				$in[$val->handle] = $val;
				return $in;
			}, []);

			// Create an updated field set from the combined existing fields and the new settings.
			$fields = []; // Storage for updated field set.
			foreach ($blockdata['fields'] as $field) {
				if (isset($blockfields[$field['handle']])) { // Existing field, key by field id, but otherwise update in-place.
					$currentfield = $blockfields[$field['handle']];

					// Allow additional transformations to be made to settings for existing fields.
					$event = new RegisterFieldSettingLoadHandlersEvent();
					Blockonomicon::getInstance()->trigger(Blockonomicon::EVENT_REGISTER_FIELD_SETTING_LOAD_HANDLERS, $event); // Gather handlers.

					// Find a handler for this field, if one exists, and run it.
					$fieldclass = get_class($currentfield);
					if (isset($event->handlers[$fieldclass])) {
						$event->handlers[$fieldclass]($currentfield, $field);
					}

					$fields[$currentfield->id] = $field;
				} else { // New field.
					$fields['new' . count($fields)] = $field; // Add field with new ID index.
				}
			}

			$blocktypes = $fields; // Swap out existing block type list with new.
		} else { // New block, create fields, create block, and attach to matrix.
			// Make sure fields are keyed with 'new' IDs.
			$blockdata['fields'] = array_reduce($blockdata['fields'], function ($in, $val) {
				$in['new' . (count($in) + 1)] = $val;
				return $in;
			}, []);

			// Add the block to the existing block list.
			$blocktypes = array_slice($blocktypes, 0, $order, true)
				+ array('new1' => $blockdata)
				+ array_slice($blocktypes, $order, null, true);
		}

		$matrix->setBlockTypes($blocktypes);
		Craft::$app->getMatrix()->saveSettings($matrix);

		$transaction->commit();

		return $matrix->getBlockTypes()[$order];
	}

	/**
	 * Stores a block definition in plugin storage, creating additional supporting files if necessary.
	 * @param array $blockdata An array containing exported block data.
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
			if (@fwrite($outfile, Craft::$app->getView()->renderTemplate('blockonomicon_storage/base.css', $blockdata)) === false) {
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
			if (@fwrite($outfile, Craft::$app->getView()->renderTemplate('blockonomicon_storage/base.js', $blockdata)) === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}
			if (@fclose($outfile) === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}

			// Render base HTML file for the block.
			$outfile = @fopen($blockpath . '/_' . $blockhandle . '.html', 'w');
			if ($outfile === false) {
				return Craft::t('blockonomicon', 'Could not write to block settings file.');
			}
			if (@fwrite($outfile, Craft::$app->getView()->renderTemplate('blockonomicon_storage/base.html', $blockdata)) === false) {
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
	 * Updates file names of block files in preparation for a handle change.
	 * @param string $oldhandle The old handle of the block.
	 * @param string $newhandle The new handle of the block.
	 */
	public function changeBlockHandle($oldhandle, $newhandle)
	{
		$oldpath = $this->getBlockPath() . '/' . $oldhandle;
		$newpath = $this->getBlockPath() . '/' . $newhandle;

		@rename($oldpath, $newpath);
		@rename($newpath . '/_' . $oldhandle . '.json', $newpath . '/_' . $newhandle . '.json');
		@rename($newpath . '/_' . $oldhandle . '.json.bak', $newpath . '/_' . $newhandle . '.json.bak');
		@rename($newpath . '/_' . $oldhandle . '.html', $newpath . '/_' . $newhandle . '.html');
		@rename($newpath . '/' . $oldhandle . '.css', $newpath . '/' . $newhandle . '.css');
		@rename($newpath . '/' . $oldhandle . '.js', $newpath . '/' . $newhandle . '.js');
	}

	/**
	 * Builds new condensed CSS and JS files from all installed blocks.
	 * @param bool $force Set to true in order to force rebuilding the files, regardless of their cached state.
	 */
	public function condenseFiles($force = false)
	{
		$cached = Craft::$app->getCache()->get('blockonomicon_condensed_files'); // Get our caching token.

		if ($force || $cached) { // Cache token still good, rebuild the cached file.
			return;
		}

		// Retrieve all cached blocks.
		$blocks = Blockonomicon::getInstance()->blocks->getBlocks();

		$cssfile = fopen($this->getBlockPath() . '/blocks.css', 'w');
		$jsfile = fopen($this->getBlockPath() . '/blocks.js', 'w');
		foreach ($blocks as $handle => $block) { // Combine all block resource files into one.
			if ($block['state'] != 'good') {
				continue;
			}
			$contents = @file_get_contents($this->getBlockPath() . '/' . $handle . '/' . $handle . '.css');
			if ($contents !== false) {
				fwrite($cssfile, $contents . "\n");
			}
			$contents = @file_get_contents($this->getBlockPath() . '/' . $handle . '/' . $handle . '.js');
			if ($contents !== false) {
				fwrite($jsfile, $contents . "\n");
			}
		}
		fclose($cssfile);
		fclose($jsfile);

		Craft::$app->getCache()->add('blockonomicon_condensed_files', true, 21600); // Cache for 6 hours (60 seconds * 60 minutes * 6 hours = 21600).
	}

	/**
	 * Renders a provided template with the given context.
	 */
	public function render($template, $data): string
	{
		return Craft::$app->getView()->renderTemplate('blockonomicon/' . $template . '/_' . $template . '.html', ['block' => $data]);
	}
}
