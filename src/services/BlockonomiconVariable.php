<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\services;

use charliedev\blockonomicon\Blockonomicon;

use craft\helpers\Template;

use yii\base\Component;

class BlockonomiconVariable extends Component
{
	/**
	 * Renders `link` tags referencing the stylesheets for all loaded blocks.
	 */
	public function renderCss($options = [])
	{
		if (isset($options['condense']) && $options['condense'] === true) { // If the files should be condensed into one file.
			
			Blockonomicon::getInstance()->blocks->condenseFiles();
			
			$out = '<link type="text/css" rel="stylesheet" href="blockonomicon/blocks.css">';
		} else { // Output individual files.

			// Retrieve all cached blocks.
			$blocks = Blockonomicon::getInstance()->blocks->getBlocks();

			$out = array();
			foreach ($blocks as $handle => $block) { // Build a set of link tags referencing the CSS files of each block.
				if ($block['state'] != 'good') {
					continue;
				}
				$out[] = '<link type="text/css" rel="stylesheet" href="' . Blockonomicon::getInstance()->blocks->getBlockPath() . '/' . $handle . '/' . $handle . '.css">';
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
			
			$out = '<script type="application/javascript" src="blockonomicon/blocks.js"></script>';
		} else { // Output individual files.

			// Retrieve all cached blocks.
			$blocks = Blockonomicon::getInstance()->blocks->getBlocks();

			$out = array();
			foreach ($blocks as $handle => $block) { // Build a set of link tags referencing the CSS files of each block.
				if ($block['state'] != 'good') {
					continue;
				}
				$out[] = '<link type="application/javascript" src="' . Blockonomicon::getInstance()->blocks->getBlockPath() . '/' . $handle . '/' . $handle . '.js">';
			}
			$out = implode('', $out);
		}
		
		return Template::raw($out);
	}

	public function renderMatrix()
	{
	}

	public function renderBlock()
	{
	}

	public function renderTemplate()
	{
	}
}