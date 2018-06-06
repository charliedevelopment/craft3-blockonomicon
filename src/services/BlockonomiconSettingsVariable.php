<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\services;

use charliedev\blockonomicon\Blockonomicon;

use Craft;

use yii\base\Component;

class BlockonomiconSettingsVariable extends Component
{
	/**
	 * @see \charliedev\blockonomicon\services\blocks::getBlockPath()
	 */
	public function getBlockPath()
	{
		return Blockonomicon::getInstance()->blocks->getBlockPath();
	}

	/**
	 * Retrieves the IDs of users allowed to access the Blockonomicon panel, if any are explicitly allowed.
	 */
	public function getAllowedUsers()
	{
		return Blockonomicon::getInstance()->getConfig('allowedUsers');
	}

	/**
	 * Retrieve an array of warning messages regarding the Blockonomicon system based on any set markers.
	 */
	public function getSystemWarnings() {
		$warnings = [];
		if (@is_file(Blockonomicon::getInstance()->blocks->getStoragePath() . '/warning_marker')) {
			$warnings[] = Craft::t('blockonomicon', 'The storage folder for Blockonomicon could not be found, so a new one has been created at <code>{path}</code>. This is the folder that contains base templates used to generate HTML, CSS, and JS for newly created blocks. If you are migrating Craft installations and have overlooked the storage folder, it can be copied to this location now.', ['path' => Blockonomicon::getInstance()->blocks->getStoragePath()]);
		}
		if (@is_file(Blockonomicon::getInstance()->blocks->getBlockPath() . '/warning_marker')) {
			$warnings[] = Craft::t('blockonomicon', 'The block folder for Blockonomicon could not be found, so a new one has been created at <code>{path}</code>. This is the folder that contains the HTML, CSS, JS, and field configuration JSON for each block. If you are migrating Craft installations and have overlooked the block folder, it can be copied to this location now.', ['path' => Blockonomicon::getInstance()->blocks->getBlockPath()]);
		}
		return $warnings;
	}
}
