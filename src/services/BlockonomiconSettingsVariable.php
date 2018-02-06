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
	public function getBlockPath() {
		return Blockonomicon::getInstance()->blocks->getBlockPath();
	}

	/**
	 * Retrieves the IDs of users allowed to access the Blockonomicon panel, if any are explicitly allowed.
	 */
	public function getAllowedUsers() {
		return Blockonomicon::getInstance()->getConfig('allowedUsers');
	}
}