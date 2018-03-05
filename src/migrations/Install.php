<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\migrations;

use charliedev\blockonomicon\Blockonomicon;

use Craft;
use craft\db\Migration;
use craft\helpers\FileHelper;

/**
 * Install migration.
 */
class Install extends Migration
{
	/**
	 * @inheritdoc
	 */
	public function safeUp()
	{
		// Copy the default blockonomicon configuration over to the config folder, if it doesn't exist.
		if (!file_exists(Craft::$app->getConfig()->configDir . '/blockonomicon.php')) {
			@copy(
				Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/resources/config-example.php',
				Craft::$app->getConfig()->configDir . '/blockonomicon.php'
			);
		}

		// Set up storage folders.
		Blockonomicon::getInstance()->blocks->setupStorageFolder();
		Blockonomicon::getInstance()->blocks->setupBlockFolder();
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
	}
}
