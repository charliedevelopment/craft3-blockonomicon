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
		$matrices = Blockonomicon::getInstance()->blocks->getMatrixFields();
		$blocks = Blockonomicon::getInstance()->blocks->getBlocks();

		return $this->renderTemplate('blockonomicon/blocks/_index', [
			'matrixId' => null,
			'fields' => $matrices,
			'blocks' => $blocks,
		]);
	}

	public function actionEditMatrix(int $matrixId): Response
	{
		$matrices = Blockonomicon::getInstance()->blocks->getMatrixFields();

		// Make sure the field ID provided is that of an actual matrix.
		if (!isset($matrices[$matrixId])) {
			throw new \yii\web\NotFoundHttpException;
		}

		return $this->renderTemplate('blockonomicon/blocks/_edit', [
			'matrixId' => $matrixId,
			'fields' => $matrices,
			'matrix' => $matrices[$matrixId],
		]);
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
