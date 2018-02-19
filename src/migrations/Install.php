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
		// Make sure storage path is created, so it's not having to be checked for creation every call.
		FileHelper::createDirectory(Blockonomicon::getInstance()->blocks->getBlockPath());

		// Copy the default blockonomicon configuration over to the config directory, if it doesn't exist.
		if (!file_exists(Craft::$app->getConfig()->configDir . '/blockonomicon.php')) {
			@copy(
				Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/resources/config-example.php',
				Craft::$app->getConfig()->configDir . '/blockonomicon.php'
			);
		}

		// Copy the base resource templates for block exporting, if they don't already exist.
		if (!file_exists(Blockonomicon::getInstance()->blocks->getStoragePath() . '/base.html')) {
			@copy(
				Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/resources/base.html',
				Blockonomicon::getInstance()->blocks->getStoragePath() . '/base.html'
			);
		}
		if (!file_exists(Blockonomicon::getInstance()->blocks->getStoragePath() . '/base.css')) {
			@copy(
				Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/resources/base.css',
				Blockonomicon::getInstance()->blocks->getStoragePath() . '/base.css'
			);
		}
		if (!file_exists(Blockonomicon::getInstance()->blocks->getStoragePath() . '/base.js')) {
			@copy(
				Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/resources/base.js',
				Blockonomicon::getInstance()->blocks->getStoragePath() . '/base.js'
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
