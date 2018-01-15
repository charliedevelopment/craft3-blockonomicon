<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Asset bundle for assets used in the control panel.
 */
class BlockonomiconPanelAsset extends AssetBundle
{
	public function init()
	{
		$this->sourcePath = '@charliedev/blockonomicon/assets/dist';

		$this->depends = [
			CpAsset::class,
		];

		$this->css = [
			'panel.css',
		];

		$this->js = [
			'panel.js',
		];

		parent::init();
	}
}