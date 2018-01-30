<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon;

use charliedev\blockonomicon\events\RegisterFieldSettingSaveHandlersEvent;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\View;

use yii\base\Event;

/**
 * The main Craft plugin class.
 */
class Blockonomicon extends Plugin
{

	/**
	 * @event RegisterFieldSettingSaveHandlersEvent Register save handlers on this event.
	 * @see [[charliedev\blockonomicon\events\RegisterFieldSettingSaveHandlersEvent]]
	 */
	public const EVENT_REGISTER_FIELD_SETTING_SAVE_HANDLERS = 'registerFieldSettingSaveHandlers';

	/**
	 * @event RegisterFieldSettingLoadHandlersEvent Register load handlers on this event.
	 * @see [[charliedev\blockonomicon\events\RegisterFieldSettingLoadHandlersEvent]]
	 */
	public const EVENT_REGISTER_FIELD_SETTING_LOAD_HANDLERS = 'registerFieldSettingLoadHandlers';

	/**
	 * @event RegisterFieldSettingConstructHandlersEvent Register construct handlers on this event.
	 * @see [[charliedev\blockonomicon\events\RegisterFieldSettingConstructHandlersEvent]]
	 */
	public const EVENT_REGISTER_FIELD_SETTING_CONSTRUCT_HANDLERS = 'registerFieldSettingConstructHandlers';

	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	public function init()
	{
		// Register plugin services.
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

		// Add routes for the plugin control panels.
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function(RegisterUrlRulesEvent $event) {
				$event->rules['blockonomicon/blocks'] = 'blockonomicon/settings/blocks-overview';
				$event->rules['blockonomicon/blocks/<blockHandle:{handle}>'] = 'blockonomicon/settings/edit-block';
				$event->rules['blockonomicon/matrix/<matrixId:\d+>'] = 'blockonomicon/settings/edit-matrix';
				$event->rules['blockonomicon/settings'] = 'blockonomicon/settings/global';
				$event->rules['blockonomicon/documentation'] = 'blockonomicon/settings/documentation';
			}
		);

		// Route template requests for frontend Blockonomicon resoruces.
		Event::on(
			View::class,
			View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
			function(RegisterTemplateRootsEvent $event) {
				$event->roots['blockonomicon'] = $this->blocks->getBlockPath();
			}
		);

		/*
		// Example of special per-field functionality for saving.
		Event::on(
			Blockonomicon::class,
			Blockonomicon::EVENT_REGISTER_FIELD_SETTING_SAVE_HANDLERS,
			function(RegisterFieldSettingSaveHandlersEvent $event) {
				$event->handlers[\craft\fields\PlainText::class] = [
					'saveCustomSettings' => function($field, &$settings) {
						$settings['custom'] = '!!' . $field->name . '!!';
					},
					'loadCustomSettings' => function($field, $settings) {
						Blockonomicon::log(print_r($settings, true));
					},
				];
			}
		);
		*/

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
				'label' => Craft::t('blockonomicon', 'Settings'),
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
