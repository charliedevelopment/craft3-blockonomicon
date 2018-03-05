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
		// Set up storage directories.
		Blockonomicon::getInstance()->blocks->setupStorageFolder();
		Blockonomicon::getInstance()->blocks->setupBlockFolder();

		// Copy the default blockonomicon configuration over to the config directory, if it doesn't exist.
		if (!file_exists(Craft::$app->getConfig()->configDir . '/blockonomicon.php')) {
			@copy(
				Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/resources/config-example.php',
				Craft::$app->getConfig()->configDir . '/blockonomicon.php'
			);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
	}
}
