<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon;

use charliedev\blockonomicon\services\Blocks;
use charliedev\blockonomicon\services\BlockonomiconVariable;
use charliedev\blockonomicon\services\BlockonomiconSettingsVariable;
use charliedev\blockonomicon\events\RegisterFieldSettingSaveHandlersEvent;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\View;
use craft\web\twig\variables\CraftVariable;

use yii\base\Event;

/**
 * The main Craft plugin class.
 */
class Blockonomicon extends Plugin
{
	/**
	 * @event SaveFieldEvent Update field settings before being exported.
	 * @see [[charliedev\blockonomicon\events\SaveFieldEvent]]
	 */
	const EVENT_SAVE_FIELD = 'saveField';

	/**
	 * @event RenderImportControlsEvent Display additional controls to the user for
	 * blocks that can be imported.
	 * @see [[charliedev\blockonomicon\events\RenderImportControlsEvent]]
	 */
	const EVENT_RENDER_IMPORT_CONTROLS = 'renderImportControls';

	/**
	 * @event LoadFieldEvent Update and combine field settings before being imported.
	 * @see [[charliedev\blockonomicon\events\LoadFieldEvent]]
	 */
	const EVENT_LOAD_FIELD = 'loadField';

	/**
	 * @var array Cached plugin configuration.
	 */
	private $_config;

	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	public function init()
	{
		// Register plugin services.
		$this->setComponents([
			'blocks' => Blocks::class,
		]);

		// Add routes for the plugin control panels.
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
				$this->registerCpUrlRules($event);
			}
		);

		// Route template requests for frontend Blockonomicon resoruces.
		Event::on(
			View::class,
			View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
			function (RegisterTemplateRootsEvent $event) {
				$event->roots['blockonomicon'] = $this->blocks->getBlockPath();
			}
		);

		// Add Blockonomicon to the craft object available via Twig.
		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			function (Event $event) {
				$variable = $event->sender;
				$variable->set('blockonomicon', BlockonomiconVariable::class);
			}
		);

		// Add additional event handlers for built-in field adapters.
		\charliedev\blockonomicon\adapters\AssetsField::setup();
		\charliedev\blockonomicon\adapters\CategoriesField::setup();
		\charliedev\blockonomicon\adapters\EntriesField::setup();
		\charliedev\blockonomicon\adapters\MatrixField::setup();
		\charliedev\blockonomicon\adapters\TagsField::setup();
		\charliedev\blockonomicon\adapters\UsersField::setup();

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

	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	public function getCpNavItem()
	{
		if (!$this->canUserAccessSettings()) {
			return null;
		}

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

	/**
	 * Checks to see if the user can access the settings panel or not.
	 * @return bool True if the user is allowed to access the settings, false if not.
	 */
	public function canUserAccessSettings(): bool
	{
		// Check config for explicitly allowed users, and if it exists, make sure the current user is in that list.
		$allowedusers = $this->getConfig('allowedUsers');
		if ($allowedusers != null
			&& (Craft::$app->getUser()->getIdentity() == null
			|| !in_array(Craft::$app->getUser()->getIdentity()->id, $allowedusers)
		)) {
			return false;
		}
		return true;
	}

	/**
	 * Retrieves a raw Blockonomicon configuration value.
	 * @param string $value The key of the value to retrieve.
	 * @return mixed The value stored, or null if no value exists.
	 */
	public function getConfig($value)
	{
		if ($this->_config == null) {
			$this->_config = Craft::$app->getConfig()->getConfigFromFile('blockonomicon');
		}
		if (!empty($this->_config[$value])) {
			return $this->_config[$value];
		}
		return null;
	}

	/**
	 * Registers routes for the Craft control panel.
	 */
	private function registerCpUrlRules(RegisterUrlRulesEvent $event)
	{
		if (!$this->canUserAccessSettings()) {
			return;
		}

		$event->rules['blockonomicon/blocks'] = 'blockonomicon/settings/blocks-overview';
		$event->rules['blockonomicon/blocks/<blockhandle:{handle}>'] = 'blockonomicon/settings/edit-block';
		$event->rules['blockonomicon/matrix/<matrixid:\d+>'] = 'blockonomicon/settings/edit-matrix';
		$event->rules['blockonomicon/settings'] = 'blockonomicon/settings/global';
		$event->rules['blockonomicon/documentation'] = 'blockonomicon/settings/documentation';
	}
}
