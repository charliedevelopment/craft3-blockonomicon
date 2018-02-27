<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\controllers;

use charliedev\blockonomicon\Blockonomicon;
use charliedev\blockonomicon\events\RenderImportControlsEvent;
use charliedev\blockonomicon\models\StoredBlock;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;

use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Controller for pages within the Blockonomicon control panel.
 */
class SettingsController extends Controller
{
	public function init()
	{
		// All requests require admin privileges.
		$this->requireAdmin();

		if (!Blockonomicon::getInstance()->canUserAccessSettings()) {
			throw new ForbiddenHttpException('User is not permitted to perform this action');
		}

		// Control panel requests will require the Blockonomicon asset bundle.
		$this->getView()->registerAssetBundle(\charliedev\blockonomicon\assets\BlockonomiconPanelAsset::class);

		parent::init();
	}

	/**
	 * Renders the Blockonomicon overview control panel.
	 */
	public function actionBlocksOverview(): Response
	{
		$matrices = Blockonomicon::getInstance()->blocks->getMatrixFields(); // All matrix fields installed in Craft.
		$blocks = Blockonomicon::getInstance()->blocks->getBlocks(true); // All installed blocks, forced to refresh to the absolute newest information.

		return $this->renderTemplate('blockonomicon/blocks/_index', [
			'matrixid' => null,
			'fields' => $matrices,
			'blocks' => $blocks,
		]);
	}

	/**
	 * Renders the individual block editing panel.
	 */
	public function actionEditBlock(string $blockhandle = null, StoredBlock $block = null): Response
	{
		// No block provided, use the handle instead.
		if ($block === null) {
			$blocks = Blockonomicon::getInstance()->blocks->getBlocks(true); // All installed blocks.

			// Make sure the block handle provided is for an actual block.
			if (!isset($blocks[$blockhandle])) {
				throw new \yii\web\NotFoundHttpException;
			}

			// Create the block model to use.
			$block = new StoredBlock();
			$block->handle = $blocks[$blockhandle]['handle'];
			$block->name = $blocks[$blockhandle]['name'];
			$block->description = !empty($blocks[$blockhandle]['description']) ? $blocks[$blockhandle]['description'] : '';
			$block->fields = $blocks[$blockhandle]['fields'];
		}

		$matrices = Blockonomicon::getInstance()->blocks->getMatrixFields(); // All matrix fields installed in Craft.

		return $this->renderTemplate('blockonomicon/blocks/_edit', [
			'matrixid' => null,
			'fields' => $matrices,
			'block' => $block,
		]);
	}

	/**
	 * Renders the Matrix block editor.
	 */
	public function actionEditMatrix(int $matrixid): Response
	{
		$matrices = Blockonomicon::getInstance()->blocks->getMatrixFields(); // All matrix fields installed in Craft.

		// Make sure the field ID provided is for an actual matrix.
		if (!isset($matrices[$matrixid])) {
			throw new \yii\web\NotFoundHttpException;
		}

		$allblocks = Blockonomicon::getInstance()->blocks->getBlocks(true); // All installed blocks.
		$matrix = $matrices[$matrixid]; // The currently edited Matrix.
		$matrixblocks = $matrix->getBlockTypes(); // Blocks attached to the matrix.
		$blocks = []; // Set of block information to render for the matrix being edited.

		// Compile a list of blocks already attached to the matrix, adding information from the cached block list if available.
		// Also key the matrix block array by block handle, instead of leaving it sorted by block order.
		$tmp = [];
		foreach ($matrixblocks as $block) {
			$tmp[$block->handle] = $block;
			$newblock = [];
			$newblock['name'] = $block->name;
			$newblock['handle'] = $block->handle;
			$newblock['id'] = $block->id;
			$newblock['description'] = !empty($allblocks[$block->handle]['description']) ? $allblocks[$block->handle]['description'] : '';
			$newblock['fields'] = count($block->getFieldLayout()->getFieldIds());

			if (isset($allblocks[$block->handle])) { // Block has an associated exported counterpart, check for consistency.
				$blockdata = Blockonomicon::getInstance()->blocks->getBlockData($block);
				if ($blockdata['name'] != $allblocks[$block->handle]['name']
					|| !$this->assocArrayEqual($blockdata['fields'], $allblocks[$block->handle]['fields'])) {
					$newblock['status'] = 'desync';
				} else {
					$newblock['status'] = 'saved';
				}
			} else { // No exported counterpart.
				$newblock['status'] = 'not-saved';
			}

			$blocks[] = $newblock;
		}
		$matrixblocks = $tmp;

		// Add to the list of blocks any that are not attached to the matrix.
		foreach ($allblocks as $block) {
			// Ignore any blocks that have a bad configuration.
			if ($block['state'] != 'good') {
				continue;
			}
			// If it has already been added, skip it here.
			if (isset($matrixblocks[$block['handle']])) {
				continue;
			}
			$blocks[] = [
				'status' => 'not-loaded',
				'name' => $block['name'],
				'handle' => $block['handle'],
				'id' => null,
				'description' => !empty($block['description']) ? $block['description'] : null,
				'fields' => count($block['fields']),
			];
		}

		// Render import controls for each block, based on any existing fields.
		$options = Blockonomicon::getInstance()->blocks->loadImportOptions();
		$controls = [];
		foreach ($allblocks as $block) {
			// Ignore any blocks that have a bad configuration.
			if ($block['state'] != 'good') {
				continue;
			}

			// Key block fields by handle, if a block exists, otherwise keep as an empty array.
			$blockfields = [];
			if (isset($matrixblocks[$block['handle']])) {
				$blockfields = $matrixblocks[$block['handle']]->getFields();
				$blockfields = array_reduce($blockfields, function ($in, $val) {
					$in[$val->handle] = $val;
					return $in;
				}, []);
			}

			// Render all of the controls for each field individually.
			$blockcontrols = [];
			foreach ($block['fields'] as $field) {
				$event = new RenderImportControlsEvent();
				$event->handle = $block['handle'];
				$event->cachedoptions = $options[$field['handle']] ?? null; // Retrieve any previously cached import options, if available.
				$event->settings = $field;
				Blockonomicon::getInstance()->trigger(Blockonomicon::EVENT_RENDER_IMPORT_CONTROLS, $event);
				if (!empty($event->controls)) {
					$blockcontrols[] = [
						'handle' => $field['handle'],
						'name' => $field['name'],
						'control' => $event->controls,
					];
				}
			}
			if (count($blockcontrols) > 0) {
				$controls[] = [
					'block' => $block['handle'],
					'controls' => $blockcontrols,
				];
			}
		}

		$this->getView()->registerTranslations('blockonomicon', [
			'The current block settings and the definition file do not match! Are you sure you want to import the {handle} block?',
			'Are you sure you want to import the {handle} block?',
			'Are you sure you want to re-import the {handle} block? You may lose data if fields have changed significantly.',

			'The current block settings and the definition file do not match! Are you sure you want to overwrite the {handle} block definition with this new one? This will backup the existing definition, and does not overwrite any of the other bundled files.',
			'Are you sure you want to save {handle} as a new block?',
			'Are you sure you want to overwrite the {handle} block definition with this new one? This will backup the existing definition, and does not overwrite any of the other bundled files.',

			'Are you sure you want to delete the {handle} block? This cannot be reversed.',
		]);

		return $this->renderTemplate('blockonomicon/blocks/_matrix', [
			'matrixid' => $matrixid,
			'fields' => $matrices,
			'matrix' => $matrix,
			'blocks' => $blocks,
			'importControls' => $controls,
		]);
	}

	/**
	 * Renders the Blockonomicon global settings panel.
	 */
	public function actionGlobal(): Response
	{
		return $this->renderTemplate('blockonomicon/_settings', [
			'settings' => Blockonomicon::getInstance()->getSettings(),
		]);
	}

	/**
	 * Renders the Blockonomicon documentation panel.
	 */
	public function actionDocumentation(): Response
	{
		return $this->renderTemplate('blockonomicon/_documentation');
	}

	/**
	 * Saves an updated block order for a matrix..
	 */
	public function actionUpdateMatrixBlockOrder(): Response
	{
		$this->requirePostRequest();
		$this->requireAcceptsJson();

		// Retrieve the blocks and their respective order.
		$blocks = Craft::$app->getRequest()->getRequiredBodyParam('blocks');
		if (!is_array($blocks)) {
			return $this->asErrorJson(Craft::t('blockonomicon', '`blocks` must be an array.'));
		}
		foreach ($blocks as $key => $val) {
			if (!is_numeric($val)) {
				return $this->asErrorJson(Craft::t('blockonomicon', '`blocks` must only contain numbers.'));
			}
			$blocks[$key] = intval($val);
		}
		
		// Manually update the table, instead of going through craft's own update methods.
		// This way things like SuperTable don't lose their data.
		$order = 1;
		foreach ($blocks as $block) {
			Craft::$app->getDb()->createCommand()
				->update('{{%matrixblocktypes}}', ['sortOrder' => $order], ['id' => $block])
				->execute();
			$order += 1;
		}

		return $this->asJson(['success' => true, 'message' => Craft::t('blockonomicon', 'Block order updated.')]);
	}

	public function actionImportBlock(): Response
	{
		$this->requirePostRequest();

		// Retrieve the ID of the matrix to import to.
		$matrixid = Craft::$app->getRequest()->getRequiredBodyParam('matrix');

		$matrix = Craft::$app->getFields()->getFieldById($matrixid);
		if (!$matrix) {
			return $this->asErrorJson(Craft::t('blockonomicon', 'Matrix {id} does not exist.', ['id' => $matrixid]));
		}

		// Retrieve the handle of the block to import.
		$blockhandle = Craft::$app->getRequest()->getRequiredBodyParam('handle');

		// Retrieve the block definition.
		$allblocks = Blockonomicon::getInstance()->blocks->getBlocks(true); // All installed blocks.
		if (!isset($allblocks[$blockhandle])) {
			return $this->asErrorJson(Craft::t('blockonomicon', 'Block {id} does not exist.', ['id' => $blockhandle]));
		}
		$block = $allblocks[$blockhandle];

		// Retrieve the position in the current block list to insert the new block.
		$order = Craft::$app->getRequest()->getRequiredBodyParam('order');
		if (!is_numeric($order)) {
			return $this->asErrorJson(Craft::t('blockonomicon', '`order` must be a number.'));
		}
		$order = intval($order);

		// Retrieve extra options for building the block.
		$options = Craft::$app->getRequest()->getBodyParam('options');
		if ($options === null) {
			$options = [];
		}
		if (!is_array($options)) {
			return $this->asErrorJson(Craft::t('blockonomicon', '`options` must be an array.'));
		}

		// Create the block from the provided request.
		$result = Blockonomicon::getInstance()->blocks->rebuildBlock($matrix, $block, $order, $options);
		if (!is_a($result, \craft\models\MatrixBlockType::class)) {
			return $this->asErrorJson($result);
		}

		// Store import options for later use.
		Blockonomicon::getInstance()->blocks->storeImportOptions($options, $blockhandle);
		
		Craft::$app->getSession()->setNotice(Craft::t('blockonomicon', 'Block imported.'));
		return $this->asJson(['success' => true]);
	}

	/**
	 * Creates/updates a block's settings file.
	 */
	public function actionExportBlock(): Response
	{
		$this->requirePostRequest();

		// Retrieve the ID of the block to export.
		$blockid = Craft::$app->getRequest()->getRequiredBodyParam('block');

		// Retrieve the block itself.
		$block = Craft::$app->getMatrix()->getBlockTypeById($blockid);
		if (!$block) {
			return $this->asErrorJson(Craft::t('blockonomicon', 'Block {id} does not exist.', ['id' => $blockid]));
		}

		// Retrieve ready to save block data.
		$blockdata = Blockonomicon::getInstance()->blocks->getBlockData($block);

		// Copy over the old description, if one exists.
		$oldblocks = Blockonomicon::getInstance()->blocks->getBlocks();
		if (isset($oldblocks[$blockdata['handle']])
			&& isset($oldblocks[$blockdata['handle']]['description'])) {
			$blockdata['description'] = $oldblocks[$blockdata['handle']]['description'];
		}

		$result = Blockonomicon::getInstance()->blocks->saveBlockData($blockdata);
		if ($result !== true) {
			return $this->asErrorJson($result);
		}
		
		Craft::$app->getSession()->setNotice(Craft::t('blockonomicon', 'Block exported.'));
		return $this->asJson(['success' => true]);
	}

	/**
	 * Deletes a block definition, removing it from its matrix.
	 */
	public function actionDeleteBlock(): Response
	{
		$this->requirePostRequest();

		// Retrieve the ID of the block to export.
		$blockid = Craft::$app->getRequest()->getRequiredBodyParam('block');

		// Retrieve the block itself.
		$block = Craft::$app->getMatrix()->getBlockTypeById($blockid);
		if (!$block) {
			return $this->asErrorJson(Craft::t('blockonomicon', 'Block {id} does not exist.', ['id' => $blockid]));
		}

		Craft::$app->getMatrix()->deleteBlockType($block);

		Craft::$app->getSession()->setNotice(Craft::t('blockonomicon', 'Block deleted.'));
		return $this->asJson(['success' => true]);
	}

	/**
	 * Updates block information saved to the block file.
	 */
	public function actionSaveBlock()
	{
		$this->requirePostRequest();

		$name = Craft::$app->getRequest()->getRequiredBodyParam('name');
		$oldhandle = Craft::$app->getRequest()->getRequiredBodyParam('oldhandle');
		$handle = Craft::$app->getRequest()->getRequiredBodyParam('handle');
		$description = Craft::$app->getRequest()->getBodyParam('description');
		if (empty($description)) {
			$description = '';
		}

		// Find the block being edited.
		$block = Blockonomicon::getInstance()->blocks->getBlocks();
		if (!isset($block[$oldhandle])) {
			return $this->asErrorJson(Craft::t('blockonomicon', 'Block {id} does not exist.', ['id' => $oldhandle]));
		}
		$block = $block[$oldhandle];

		// Validate the block settings.
		$blockmodel = new StoredBlock();
		$blockmodel->name = $name;
		$blockmodel->oldhandle = $oldhandle;
		$blockmodel->handle = $handle;
		$blockmodel->description = $description;
		$blockmodel->fields = $block['fields'];

		if (!$blockmodel->validate()) {
			Craft::$app->getSession()->setError(Craft::t('blockonomicon', 'Couldn’t save block.'));

			Craft::$app->getUrlManager()->setRouteParams([
				'block' => $blockmodel
			]);
			return null;
		}
		
		// Update block properties.
		$block['name'] = $name;
		$block['handle'] = $handle;
		$block['description'] = $description;
		
		if ($oldhandle != $handle) {
			Blockonomicon::getInstance()->blocks->changeBlockHandle($oldhandle, $handle);
		}
		Blockonomicon::getInstance()->blocks->saveBlockData($block);

		Craft::$app->getSession()->setNotice(Craft::t('blockonomicon', 'Block saved.'));
		return $this->redirectToPostedUrl();
	}

	/**
	 * Forces minified/concatenated block styles and scripts to be recreated.
	 */
	public function actionRebuildFiles()
	{
		$this->requirePostRequest();

		Blockonomicon::getInstance()->blocks->condenseFiles(true);

		return $this->asJson(['success' => true, 'message' => Craft::t('blockonomicon', 'Minified files rebuilt.')]);
	}

	/**
	 * Determines if the two arrays are equal in keys and values.
	 */
	private function assocArrayEqual($a, $b)
	{
		if (count($a) != count($b)) { // Different keys, can't be the same.
			return false;
		}

		foreach ($a as $key => $value) {
			if (!array_key_exists($key, $b)) { // Do not share a key, can't be the same.
				return false;
			}
			if (is_array($a[$key]) && is_array($b[$key])) {
				if (!$this->assocArrayEqual($a[$key], $b[$key])) {
					return false;
				}
			} else {
				if ($a[$key] != $b[$key]) { // Different values, can't be the same.
					return false;
				}
			}
		}

		return true;
	}
}
