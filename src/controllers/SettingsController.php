<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\controllers;

use charliedev\blockonomicon\Blockonomicon;

use Craft;
use craft\web\Controller;

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

		// Control panel requests will require the Blockonomicon asset bundle.
		$this->getView()->registerAssetBundle(\charliedev\blockonomicon\assets\BlockonomiconPanelAsset::class);

		parent::init();
	}

	public function actionBlocksOverview(): Response
	{
		$matrices = Blockonomicon::getInstance()->blocks->getMatrixFields(); // All matrix fields installed in Craft.
		$blocks = Blockonomicon::getInstance()->blocks->getBlocks(true); // All installed blocks, forced to refresh to the absolute newest information.

		return $this->renderTemplate('blockonomicon/blocks/_index', [
			'matrixId' => null,
			'fields' => $matrices,
			'blocks' => $blocks,
		]);
	}

	public function actionEditBlock(string $blockHandle): Response
	{
		$blocks = Blockonomicon::getInstance()->blocks->getBlocks(); // All installed blocks, from the cache.

		// Make sure the block handle provided is for an actual block.
		if (!isset($blocks[$blockHandle])) {
			throw new \yii\web\NotFoundHttpException;
		}

		$matrices = Blockonomicon::getInstance()->blocks->getMatrixFields(); // All matrix fields installed in Craft.

		return $this->renderTemplate('blockonomicon/blocks/_edit', [
			'matrixId' => null,
			'fields' => $matrices,
			'block' => $blocks[$blockHandle],
		]);
	}

	public function actionEditMatrix(int $matrixId): Response
	{
		$matrices = Blockonomicon::getInstance()->blocks->getMatrixFields(); // All matrix fields installed in Craft.

		// Make sure the field ID provided is for an actual matrix.
		if (!isset($matrices[$matrixId])) {
			throw new \yii\web\NotFoundHttpException;
		}

		$allblocks = Blockonomicon::getInstance()->blocks->getBlocks(); // All installed blocks, from the cache.
		$matrix = $matrices[$matrixId]; // The currently edited Matrix.
		$matrixblocks = $matrix->getBlockTypes(); // Blocks attached to the matrix.
		$blocks = []; // Set of block information to render for the matrix being edited.

		// Compile a list of blocks already attached to the matrix, adding information from the cached block list if available.
		// Also key the matrix block array by block handle, instead of leaving it sorted by block order.
		$tmp = [];
		foreach ($matrixblocks as $block) {
			$tmp[$block->handle] = $block;
			$blocks[] = [
				'status' => isset($allblocks[$block->handle]) ? 'saved' : 'not-saved',
				'name' => $block->name,
				'handle' => $block->handle,
				'id' => $block->id,
				'description' => isset($allblocks[$block->handle]) ? $allblocks[$block->handle]['description'] : '-',
				'fields' => count($block->getFieldLayout()->getFieldIds()),
			];
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
				'description' => isset($block['description']) ? $block['description'] : '-',
				'fields' => count($block['fields']),
			];
		}

		$this->getView()->registerTranslations('blockonomicon', [
			'Are you sure you want to import the {handle} block?',
			'Are you sure you want to re-import the {handle} block? You may lose data if fields have changed significantly.',
			'Are you sure you want to save {handle} as a new block?',
			'Are you sure you want to overwrite the {handle} block definition with this new one?',
			'Are you sure you want to delete the {handle} block? This cannot be reversed.',
		]);

		return $this->renderTemplate('blockonomicon/blocks/_matrix', [
			'matrixId' => $matrixId,
			'fields' => $matrices,
			'matrix' => $matrix,
			'blocks' => $blocks,
		]);
	}

	public function actionUpdateMatrixBlockOrder(): Response
	{
		$this->requirePostRequest();
		$this->requireAcceptsJson();

		// Retrieve parameters for the request.
		$matrixid = Craft::$app->getRequest()->getRequiredBodyParam('matrix');

		// Retrieve the field.
		$field = Craft::$app->getFields()->getFieldById($matrixid);
		if (!$field) { // If it doesn't exist, error out.
			return $this->asErrorJson(Craft::t('blockonomicon' 'Matrix {id} does not exist.', ['id' => $matrixid]));
		}

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

	public function actionSaveMatrix(): Response
	{
		$this->requirePostRequest();

		$request = Craft::$app->getRequest();

		return $this->redirectToPostedUrl();
	}

	public function actionGlobal(): Response
	{
		return $this->renderTemplate('blockonomicon/_settings', [
			'settings' => Blockonomicon::getInstance()->getSettings(),
		]);
	}

	public function actionDocumentation(): Response
	{
		return $this->renderTemplate('blockonomicon/_documentation');
	}
}
