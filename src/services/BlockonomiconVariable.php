<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\services;

use charliedev\blockonomicon\Blockonomicon;
use charliedev\blockonomicon\services\BlockonomiconSettingsVariable;

use Craft;
use craft\helpers\Template;
use craft\helpers\Component as ComponentHelper;

use yii\base\Component;

class BlockonomiconVariable extends Component
{
	/**
	 * Used to store the BlockonomiconSettingsVariable component that is used on the
	 * backend to provide additional Twig functionality.
	 */
	private $_settings;

	public function init()
	{

		if (Craft::$app->getRequest()->getIsCpRequest()) {
			$this->_settings = new BlockonomiconSettingsVariable();
			$this->_settings->init();
		}

		parent::init();
	}

	/**
	 * Retrieves the Twig templating variable used for the backend.
	 */
	public function getSettings()
	{
		return $this->_settings;
	}

	/**
	 * Renders `link` tags referencing the stylesheets for all loaded blocks.
	 */
	public function renderCss($options = [])
	{
		if (isset($options['condense']) && $options['condense'] === true) { // If the files should be condensed into one file.
			Blockonomicon::getInstance()->blocks->condenseFiles();

			$out = '<link type="text/css" rel="stylesheet" href="/blockonomicon/blocks.css">';
		} else { // Output individual files.
			// Retrieve all cached blocks.
			$blocks = Blockonomicon::getInstance()->blocks->getBlocks();

			$out = [];
			foreach ($blocks as $handle => $block) { // Build a set of link tags referencing the CSS files of each block.
				if ($block['state'] != 'good') {
					continue;
				}
				$out[] = '<link type="text/css" rel="stylesheet" href="/blockonomicon/' . $handle . '/' . $handle . '.css">';
			}
			$out = implode('', $out);
		}

		return Template::raw($out);
	}

	/**
	 * Renders `script` tags referencing the javascript for all loaded blocks.
	 */
	public function renderJs($options = [])
	{
		if (isset($options['condense']) && $options['condense'] === true) { // If the files should be condensed into one file.
			Blockonomicon::getInstance()->blocks->condenseFiles();

			$out = '<script type="application/javascript" src="/blockonomicon/blocks.js"></script>';
		} else { // Output individual files.
			// Retrieve all cached blocks.
			$blocks = Blockonomicon::getInstance()->blocks->getBlocks();

			$out = [];
			foreach ($blocks as $handle => $block) { // Build a set of link tags referencing the CSS files of each block.
				if ($block['state'] != 'good') {
					continue;
				}
				$out[] = '<script type="application/javascript" src="/blockonomicon/' . $handle . '/' . $handle . '.js"></script>';
			}
			$out = implode('', $out);
		}

		return Template::raw($out);
	}

	public function renderMatrix($matrix, $options = [])
	{
		// Ensure the matrix provided is either a raw/filtered matrix.
		if (is_object($matrix) && get_class($matrix) == 'craft\elements\db\MatrixBlockQuery') {
			$matrix = $matrix->all();
		}

		// Or an array of matrix blocks.
		if (!is_array($matrix)) {
			return;
		}

		$out = [];
		foreach ($matrix as $block) { // Iterate over every block and store the output.
			if (!is_object($block) || !get_class($block) == 'craft\elements\MatrixBlock') {
				return;
			}
			$out[] = Blockonomicon::getInstance()->blocks->render($block->type, $block);
		}
		return Template::raw(implode('', $out));
	}

	public function renderBlock($block, $options = [])
	{
		if (!is_object($block) || !get_class($block) == 'craft\elements\MatrixBlock') {
			return;
		}

		return Template::raw(Blockonomicon::getInstance()->blocks->render($block->type, $block));
	}
}
