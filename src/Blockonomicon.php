<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\UserPermissions;
use craft\web\UrlManager;

use yii\base\Event;

/**
 * The main Craft plugin class.
 */
class Blockonomicon extends Plugin
{
	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	public function init()
	{
		$this->setComponents([
			'blocks' => \charliedev\blockonomicon\services\Blocks::class,
		]);

		/*
		// In the case of multi-user installs, register a custom user permission to allow fine-grained management of blocks in Blockonomicon.
		if (Craft::$app->getEdition() >= Craft::Client) {
			Event::on(
				UserPermissions::class,
				UserPermissions::EVENT_REGISTER_PERMISSIONS,
				function (RegisterUserPermissionsEvent $event) {
					$event->permissions['Blockonomicon'] = [
						'manageBlocks' => [
							'label' => Craft::t('blockonomicon', 'Manage Blocks'),
						],
					];
				}
			);
		}
		*/

		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function(RegisterUrlRulesEvent $event) {
				$event->rules['blockonomicon/blocks'] = 'blockonomicon/settings/blocks-overview';
				$event->rules['blockonomicon/blocks/<matrixId:\d+>'] = 'blockonomicon/settings/edit-matrix';
				$event->rules['blockonomicon/settings'] = 'blockonomicon/settings/global';
				$event->rules['blockonomicon/documentation'] = 'blockonomicon/settings/documentation';
			}
		);

		parent::init();
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	protected function createSettingsModel()
	{
		return new \charliedev\blockonomicon\models\Settings();
	}
	
	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	protected function settingsHtml()
	{
		return Craft::$app->getView()->renderTemplate('blockonomicon/index');
	}

	public function getCpNavItem()
	{
		$item = parent::getCpNavItem();
		$item['subnav'] = [
			'blocks' => [
				'label' => Craft::t('blockonomicon', 'Blocks'),
				'url' => 'blockonomicon/blocks',
			],
			'settings' => [
				'label' => Craft::t('blockonomicon', 'Global Settings'),
				'url' => 'blockonomicon/settings',
			],
			'documentation' => [
				'label' => Craft::t('blockonomicon', 'Documentation'),
				'url' => 'blockonomicon/documentation',
			],
		];
		return $item;
	}
}
