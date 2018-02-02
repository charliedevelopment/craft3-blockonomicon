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

		// Copy the default blockonomicon configuration over to the config directory.
		@copy(Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/config-example.php', Craft::$app->getConfig()->configDir . '/blockonomicon.php');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
		// Clean up the storage path.
		FileHelper::removeDirectory(Blockonomicon::getInstance()->blocks->getStoragePath());
		
		// Remove the blockonomicon configuration.
		@unlink(Craft::$app->getConfig()->configDir . '/blockonomicon.php');
    }
}
