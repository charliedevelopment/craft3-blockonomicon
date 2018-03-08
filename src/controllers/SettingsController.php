<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\controllers;

use charliedev\blockonomicon\Blockonomicon;
use charliedev\blockonomicon\events\RenderImportControlsEvent;
use charliedev\blockonomicon\models\StoredBlock;

use Craft;
use craft\base\Field;
use craft\elements\Asset;
use craft\elements\Tag;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\models\FieldGroup;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\TagGroup;
use craft\web\Controller;
use craft\helpers\FileHelper;

use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Controller for pages within the Blockonomicon control panel.
 */
class SettingsController extends Controller
{
	public function init()
	{
		// All requests require admin privileges.
		$this->requireAdmin();

		if (!Blockonomicon::getInstance()->canUserAccessSettings()) {
			throw new ForbiddenHttpException('User is not permitted to perform this action');
		}

		// Control panel requests will require the Blockonomicon asset bundle.
		$this->getView()->registerAssetBundle(\charliedev\blockonomicon\assets\BlockonomiconPanelAsset::class);

		parent::init();
	}

	/**
	 * Renders the Blockonomicon overview control panel.
	 */
	public function actionBlocksOverview(): Response
	{
		$matrices = Blockonomicon::getInstance()->blocks->getMatrixFields(); // All matrix fields installed in Craft.
		$blocks = Blockonomicon::getInstance()->blocks->getBlocks(true); // All installed blocks, forced to refresh to the absolute newest information.

		return $this->renderTemplate('blockonomicon/blocks/_index', [
			'matrixid' => null,
			'fields' => $matrices,
			'blocks' => $blocks,
		]);
	}

	/**
	 * Renders the individual block editing panel.
	 */
	public function actionEditBlock(string $blockhandle = null, StoredBlock $block = null): Response
	{
		// No block provided, use the handle instead.
		if ($block === null) {
			$blocks = Blockonomicon::getInstance()->blocks->getBlocks(true); // All installed blocks.

			// Make sure the block handle provided is for an actual block.
			if (!isset($blocks[$blockhandle])) {
				throw new \yii\web\NotFoundHttpException;
			}

			// Create the block model to use.
			$block = new StoredBlock();
			$block->handle = $blocks[$blockhandle]['handle'];
			$block->name = $blocks[$blockhandle]['name'];
			$block->description = !empty($blocks[$blockhandle]['description']) ? $blocks[$blockhandle]['description'] : '';
			$block->fields = $blocks[$blockhandle]['fields'];
		}

		$matrices = Blockonomicon::getInstance()->blocks->getMatrixFields(); // All matrix fields installed in Craft.

		return $this->renderTemplate('blockonomicon/blocks/_edit', [
			'matrixid' => null,
			'fields' => $matrices,
			'block' => $block,
		]);
	}

	/**
	 * Renders the Matrix block editor.
	 */
	public function actionEditMatrix(int $matrixid): Response
	{
		$matrices = Blockonomicon::getInstance()->blocks->getMatrixFields(); // All matrix fields installed in Craft.

		// Make sure the field ID provided is for an actual matrix.
		if (!isset($matrices[$matrixid])) {
			throw new \yii\web\NotFoundHttpException;
		}

		$allblocks = Blockonomicon::getInstance()->blocks->getBlocks(true); // All installed blocks.
		$matrix = $matrices[$matrixid]; // The currently edited Matrix.
		$matrixblocks = $matrix->getBlockTypes(); // Blocks attached to the matrix.
		$blocks = []; // Set of block information to render for the matrix being edited.

		// Compile a list of blocks already attached to the matrix, adding information from the cached block list if available.
		// Also key the matrix block array by block handle, instead of leaving it sorted by block order.
		$tmp = [];
		foreach ($matrixblocks as $block) {
			$tmp[$block->handle] = $block;
			$newblock = [];
			$newblock['name'] = $block->name;
			$newblock['handle'] = $block->handle;
			$newblock['id'] = $block->id;
			$newblock['description'] = !empty($allblocks[$block->handle]['description']) ? $allblocks[$block->handle]['description'] : '';
			$newblock['fields'] = count($block->getFieldLayout()->getFieldIds());

			if (isset($allblocks[$block->handle])) { // Block has an associated exported counterpart, check for consistency.
				$blockdata = Blockonomicon::getInstance()->blocks->getBlockData($block);
				if ($allblocks[$block->handle]['state'] != 'good' // Bad configuration.
					|| $blockdata['name'] != $allblocks[$block->handle]['name'] // Different name.
					|| !$this->assocArrayEqual($blockdata['fields'], $allblocks[$block->handle]['fields'])) { // Different fields.
					$newblock['status'] = 'desync';
				} else {
					$newblock['status'] = 'saved';
				}
			} else { // No exported counterpart.
				$newblock['status'] = 'not-saved';
			}

			$blocks[] = $newblock;
		}
		$matrixblocks = $tmp;

		// Add to the list of blocks any that are not attached to the matrix.
		foreach ($allblocks as $block) {
			// Ignore any blocks that have a bad configuration.
			if ($block['state'] != 'good') {
				continue;
			}
			// If it has already been added, skip it here.
			if (isset($matrixblocks[$block['handle']])) {
				continue;
			}
			$blocks[] = [
				'status' => 'not-loaded',
				'name' => $block['name'],
				'handle' => $block['handle'],
				'id' => null,
				'description' => !empty($block['description']) ? $block['description'] : null,
				'fields' => count($block['fields']),
			];
		}

		// Render import controls for each block, based on any existing fields.
		$options = Blockonomicon::getInstance()->blocks->loadImportOptions();
		$controls = [];
		foreach ($allblocks as $block) {
			// Ignore any blocks that have a bad configuration.
			if ($block['state'] != 'good') {
				continue;
			}

			// Key block fields by handle, if a block exists, otherwise keep as an empty array.
			$blockfields = [];
			if (isset($matrixblocks[$block['handle']])) {
				$blockfields = $matrixblocks[$block['handle']]->getFields();
				$blockfields = array_reduce($blockfields, function ($in, $val) {
					$in[$val->handle] = $val;
					return $in;
				}, []);
			}

			// Render all of the controls for each field individually.
			$blockcontrols = [];
			foreach ($block['fields'] as $field) {
				$event = new RenderImportControlsEvent();
				$event->handle = $field['handle'];
				$event->cachedoptions = $options[$field['handle']] ?? null; // Retrieve any previously cached import options, if available.
				$event->settings = $field;
				Blockonomicon::getInstance()->trigger(Blockonomicon::EVENT_RENDER_IMPORT_CONTROLS, $event);
				if (!empty($event->controls)) {
					$blockcontrols[] = [
						'handle' => $field['handle'],
						'name' => $field['name'],
						'control' => $event->controls,
					];
				}
			}
			if (count($blockcontrols) > 0) {
				$controls[] = [
					'block' => $block['handle'],
					'controls' => $blockcontrols,
				];
			}
		}

		$this->getView()->registerTranslations('blockonomicon', [
			'The current block settings and the definition file do not match! Are you sure you want to import the {handle} block?',
			'Are you sure you want to import the {handle} block?',
			'Are you sure you want to re-import the {handle} block? You may lose data if fields have changed significantly.',

			'The current block settings and the definition file do not match! Are you sure you want to overwrite the {handle} block definition with this new one? This will backup the existing definition, and does not overwrite any of the other bundled files.',
			'Are you sure you want to save {handle} as a new block?',
			'Are you sure you want to overwrite the {handle} block definition with this new one? This will backup the existing definition, and does not overwrite any of the other bundled files.',

			'Are you sure you want to delete the {handle} block? This cannot be reversed.',
		]);

		return $this->renderTemplate('blockonomicon/blocks/_matrix', [
			'matrixid' => $matrixid,
			'fields' => $matrices,
			'matrix' => $matrix,
			'blocks' => $blocks,
			'importControls' => $controls,
		]);
	}

	/**
	 * Renders the Blockonomicon global settings panel.
	 */
	public function actionGlobal(): Response
	{
		return $this->renderTemplate('blockonomicon/_settings', [
			'settings' => Blockonomicon::getInstance()->getSettings(),
		]);
	}

	/**
	 * Renders the Blockonomicon documentation panel.
	 */
	public function actionDocumentation(): Response
	{
		return $this->renderTemplate('blockonomicon/_documentation');
	}

	/**
	 * Saves an updated block order for a matrix..
	 */
	public function actionUpdateMatrixBlockOrder(): Response
	{
		$this->requirePostRequest();
		$this->requireAcceptsJson();

		// Retrieve the blocks and their respective order.
		$blocks = Craft::$app->getRequest()->getRequiredBodyParam('blocks');
		if (!is_array($blocks)) {
			return $this->asErrorJson(Craft::t('blockonomicon', '`blocks` must be an array.'));
		}
		foreach ($blocks as $key => $val) {
			if (!is_numeric($val)) {
				return $this->asErrorJson(Craft::t('blockonomicon', '`blocks` must only contain numbers.'));
			}
			$blocks[$key] = intval($val);
		}

		// Manually update the table, instead of going through craft's own update methods.
		// This way things like SuperTable don't lose their data.
		$order = 1;
		foreach ($blocks as $block) {
			Craft::$app->getDb()->createCommand()
				->update('{{%matrixblocktypes}}', ['sortOrder' => $order], ['id' => $block])
				->execute();
			$order += 1;
		}

		return $this->asJson(['success' => true, 'message' => Craft::t('blockonomicon', 'Block order updated.')]);
	}

	public function actionImportBlock(): Response
	{
		$this->requirePostRequest();

		// Retrieve the ID of the matrix to import to.
		$matrixid = Craft::$app->getRequest()->getRequiredBodyParam('matrix');

		$matrix = Craft::$app->getFields()->getFieldById($matrixid);
		if (!$matrix) {
			return $this->asErrorJson(Craft::t('blockonomicon', 'Matrix {id} does not exist.', ['id' => $matrixid]));
		}

		// Retrieve the handle of the block to import.
		$blockhandle = Craft::$app->getRequest()->getRequiredBodyParam('handle');

		// Retrieve the block definition.
		$allblocks = Blockonomicon::getInstance()->blocks->getBlocks(true); // All installed blocks.
		if (!isset($allblocks[$blockhandle])) {
			return $this->asErrorJson(Craft::t('blockonomicon', 'Block {id} does not exist.', ['id' => $blockhandle]));
		}
		$block = $allblocks[$blockhandle];

		// Retrieve the position in the current block list to insert the new block.
		$order = Craft::$app->getRequest()->getRequiredBodyParam('order');
		if (!is_numeric($order)) {
			return $this->asErrorJson(Craft::t('blockonomicon', '`order` must be a number.'));
		}
		$order = intval($order);

		// Retrieve extra options for building the block.
		$options = Craft::$app->getRequest()->getBodyParam('options');
		if ($options === null) {
			$options = [];
		}
		if (!is_array($options)) {
			return $this->asErrorJson(Craft::t('blockonomicon', '`options` must be an array.'));
		}

		// Create the block from the provided request.
		$result = Blockonomicon::getInstance()->blocks->rebuildBlock($matrix, $block, $order, $options);
		if (!is_a($result, \craft\models\MatrixBlockType::class)) {
			return $this->asErrorJson($result);
		}

		// Store import options for later use.
		Blockonomicon::getInstance()->blocks->storeImportOptions($options, $blockhandle);

		Craft::$app->getSession()->setNotice(Craft::t('blockonomicon', 'Block imported.'));
		return $this->asJson(['success' => true]);
	}

	/**
	 * Creates/updates a block's settings file.
	 */
	public function actionExportBlock(): Response
	{
		$this->requirePostRequest();

		// Retrieve the ID of the block to export.
		$blockid = Craft::$app->getRequest()->getRequiredBodyParam('block');

		// Retrieve the block itself.
		$block = Craft::$app->getMatrix()->getBlockTypeById($blockid);
		if (!$block) {
			return $this->asErrorJson(Craft::t('blockonomicon', 'Block {id} does not exist.', ['id' => $blockid]));
		}

		// Retrieve ready to save block data.
		$blockdata = Blockonomicon::getInstance()->blocks->getBlockData($block);

		// Copy over the old description, if one exists.
		$oldblocks = Blockonomicon::getInstance()->blocks->getBlocks();
		if (isset($oldblocks[$blockdata['handle']])
			&& isset($oldblocks[$blockdata['handle']]['description'])) {
			$blockdata['description'] = $oldblocks[$blockdata['handle']]['description'];
		}

		$result = Blockonomicon::getInstance()->blocks->saveBlockData($blockdata);
		if ($result !== true) {
			return $this->asErrorJson($result);
		}

		Craft::$app->getSession()->setNotice(Craft::t('blockonomicon', 'Block exported.'));
		return $this->asJson(['success' => true]);
	}

	/**
	 * Deletes a block definition, removing it from its matrix.
	 */
	public function actionDeleteBlock(): Response
	{
		$this->requirePostRequest();

		// Retrieve the ID of the block to export.
		$blockid = Craft::$app->getRequest()->getRequiredBodyParam('block');

		// Retrieve the block itself.
		$block = Craft::$app->getMatrix()->getBlockTypeById($blockid);
		if (!$block) {
			return $this->asErrorJson(Craft::t('blockonomicon', 'Block {id} does not exist.', ['id' => $blockid]));
		}

		Craft::$app->getMatrix()->deleteBlockType($block);

		Craft::$app->getSession()->setNotice(Craft::t('blockonomicon', 'Block deleted.'));
		return $this->asJson(['success' => true]);
	}

	/**
	 * Updates block information saved to the block file.
	 */
	public function actionSaveBlock()
	{
		$this->requirePostRequest();

		$name = Craft::$app->getRequest()->getRequiredBodyParam('name');
		$oldhandle = Craft::$app->getRequest()->getRequiredBodyParam('oldhandle');
		$handle = Craft::$app->getRequest()->getRequiredBodyParam('handle');
		$description = Craft::$app->getRequest()->getBodyParam('description');
		if (empty($description)) {
			$description = '';
		}

		// Find the block being edited.
		$block = Blockonomicon::getInstance()->blocks->getBlocks();
		if (!isset($block[$oldhandle])) {
			return $this->asErrorJson(Craft::t('blockonomicon', 'Block {id} does not exist.', ['id' => $oldhandle]));
		}
		$block = $block[$oldhandle];

		// Validate the block settings.
		$blockmodel = new StoredBlock();
		$blockmodel->name = $name;
		$blockmodel->oldhandle = $oldhandle;
		$blockmodel->handle = $handle;
		$blockmodel->description = $description;
		$blockmodel->fields = $block['fields'];

		if (!$blockmodel->validate()) {
			Craft::$app->getSession()->setError(Craft::t('blockonomicon', 'Couldn’t save block.'));

			Craft::$app->getUrlManager()->setRouteParams([
				'block' => $blockmodel
			]);
			return null;
		}

		// Update block properties.
		$block['name'] = $name;
		$block['handle'] = $handle;
		$block['description'] = $description;

		if ($oldhandle != $handle) {
			Blockonomicon::getInstance()->blocks->changeBlockHandle($oldhandle, $handle);
		}
		Blockonomicon::getInstance()->blocks->saveBlockData($block);

		Craft::$app->getSession()->setNotice(Craft::t('blockonomicon', 'Block saved.'));
		return $this->redirectToPostedUrl();
	}

	/**
	 * Forces minified/concatenated block styles and scripts to be recreated.
	 */
	public function actionRebuildFiles()
	{
		$this->requirePostRequest();

		Blockonomicon::getInstance()->blocks->condenseFiles(true);

		return $this->asJson(['success' => true, 'message' => Craft::t('blockonomicon', 'Minified files rebuilt.')]);
	}

	/**
	 * Installs example content.
	 */
	public function actionQuickStart()
	{
		// Copy blocks to the block storage folder.
		FileHelper::copyDirectory(
			Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/examples/exampleBanner',
			Blockonomicon::getInstance()->blocks->getBlockPath() . '/exampleBanner'
		);
		FileHelper::copyDirectory(
			Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/examples/exampleExpander',
			Blockonomicon::getInstance()->blocks->getBlockPath() . '/exampleExpander'
		);
		FileHelper::copyDirectory(
			Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/examples/exampleTaggedContent',
			Blockonomicon::getInstance()->blocks->getBlockPath() . '/exampleTaggedContent'
		);

		// Copy template to the templates folder.
		@copy(
			Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/examples/_blockonomicon.html',
			Craft::$app->getPath()->getSiteTemplatesPath() . '/_blockonomicon.html'
		);

		// Create tag group.
		$taggroup = new TagGroup();
		$taggroup->name = 'Blockonomicon Example Tag Group';
		$taggroup->handle = 'blockonomiconExampleTagGroup';
		$fieldlayout = Craft::$app->getFields()->assembleLayout([]);
		$fieldlayout->type = Tag::class;
		$taggroup->setFieldLayout($fieldlayout);
		if (!Craft::$app->getTags()->saveTagGroup($taggroup)) {
			return $this->asJson(['error' => Craft::t('app', 'Couldn’t save the tag group.')]);
		}

		// Create asset volume folder.
		if (!FileHelper::createDirectory(Craft::getAlias('@webroot/blockonomicon-assets'))) {
			return $this->asJson(['error' => Craft::t('blockonomicon', 'Couldn\'t create the asset folder.')]);
		}

		// Create asset volume.
		$volume = Craft::$app->getVolumes()->createVolume([
			'type' => \craft\volumes\Local::class,
			'name' => 'Blockonomicon Example Asset Volume',
			'handle' => 'blockonomiconExampleAssetVolume',
			'hasUrls' => true,
			'url' => '@web/blockonomicon-assets',
			'settings' => [
				'path' => '@webroot/blockonomicon-assets'
			]
		]);
		$fieldlayout = Craft::$app->getFields()->assembleLayout([]);
		$fieldlayout->type = Asset::class;
		$volume->setFieldLayout($fieldlayout);
		if (!Craft::$app->getVolumes()->saveVolume($volume)) {
			return $this->asJson(['error' => Craft::t('app', 'Couldn’t save volume.')]);
		}

		// Create field group.
		$fieldgroup = new FieldGroup();
		$fieldgroup->name = 'Blockonomicon Group';
		if (!Craft::$app->getFields()->saveGroup($fieldgroup)) {
			return $this->asJson(['error' => Craft::t('blockonomicon', 'Couldn\'t save field group.')]);
		}

		// Create matrix.
		$matrix = Craft::$app->getFields()->createField([
			'type' => \craft\fields\Matrix::class,
			'groupId' => $fieldgroup->id,
			'name' => 'Blockonomicon Matrix',
			'handle' => 'blockonomiconMatrix',
			'instructions' => 'This is an example matrix created by Blockonomicon.',
			'translationMethod' => Field::TRANSLATION_METHOD_NONE,
			'translationKeyFormat' => null,
			'settings' => [
				'blockTypes' => [],
				'localizeBlocks' => false,
				'minBlocks' => null,
				'maxBlocks' => null,
			]
		]);
		if (!Craft::$app->getFields()->saveField($matrix)) {
			return $this->asJson(['error' => Craft::t('app', 'Couldn’t save field.')]);
		}

		// Attach blocks to matrix.
		$allblocks = Blockonomicon::getInstance()->blocks->getBlocks(true); // All installed blocks.
		$blockoptions = [
			'backgroundImage' => [
				'singleUploadLocationSource' => 'folder:' . Craft::$app->getVolumes()->ensureTopFolder($volume)
			]
		];
		Blockonomicon::getInstance()->blocks->rebuildBlock($matrix, $allblocks['exampleBanner'], 0, $blockoptions);
		$blockoptions = [];
		Blockonomicon::getInstance()->blocks->rebuildBlock($matrix, $allblocks['exampleExpander'], 1, $blockoptions);
		$blockoptions = [
			'tags' => [
				'source' => 'taggroup:' . $taggroup->id
			]
		];
		Blockonomicon::getInstance()->blocks->rebuildBlock($matrix, $allblocks['exampleTaggedContent'], 2, $blockoptions);

		// Create single section.
		$section = new Section();
		$section->name = 'Blockonomicon Example';
		$section->handle = 'blockonomiconExample';
		$section->type = 'single';
		$section->enableVersioning = false;
		$section->propagateEntries = true;
		$sitesettings = new Section_SiteSettings();
		$sitesettings->siteId = Craft::$app->getSites()->getPrimarySite()->id;
		$sitesettings->hasUrls = true;
		$sitesettings->uriFormat = 'blockonomicon-example';
		$sitesettings->template = '_blockonomicon';
		$section->setSiteSettings([
			$sitesettings->siteId => $sitesettings
		]);
		if (!Craft::$app->getSections()->saveSection($section)) {
			return $this->asJson(['error' => Craft::t('app', 'Couldn’t save section.')]);
		}

		// Attach matrix to entry type via a field layout.
		$entrytype = Craft::$app->getSections()->getEntryTypesBySectionId($section->id)[0]; // It's a single, it only has one entry type automatically created for it.
		$tab = new FieldLayoutTab();
		$tab->name = 'Blockonomicon';
		$tab->sortOrder = 1;
		$matrix->required = false;
		$matrix->sortOrder = 1;
		$tab->setFields([$matrix]);
		$fieldlayout = $entrytype->getFieldLayout();
		$fieldlayout->setTabs([$tab]);
		$fieldlayout->setFields([$matrix]);
		$fieldlayout->type = Entry::class;
		Craft::$app->getSections()->saveEntryType($entrytype);

		// Add placeholder assets.
		$filename = uniqid('edan-cohen-2508-unsplash.jpg');
		@copy(
			Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/examples/edan-cohen-2508-unsplash.jpg',
			Craft::$app->getPath()->getTempPath() . '/' . $filename
		);
		$asset1 = new Asset();
		$asset1->tempFilePath = Craft::$app->getPath()->getTempPath() . '/' . $filename;
		$asset1->filename = 'edan-cohen-2508-unsplash.jpg';
		$asset1->newFolderId = Craft::$app->getVolumes()->ensureTopFolder($volume);
		$asset1->volumeId = $volume->id;
		$asset1->avoidFilenameConflicts = true;
		$asset1->setScenario(Asset::SCENARIO_CREATE);
		$result = Craft::$app->getElements()->saveElement($asset1);

		$filename = uniqid('demi-deherrera-84871-unsplash.jpg');
		@copy(
			Craft::$app->getPath()->getVendorPath() . '/charliedev/blockonomicon/src/examples/demi-deherrera-84871-unsplash.jpg',
			Craft::$app->getPath()->getTempPath() . '/' . $filename
		);
		$asset2 = new Asset();
		$asset2->tempFilePath = Craft::$app->getPath()->getTempPath() . '/' . $filename;
		$asset2->filename = 'demi-deherrera-84871-unsplash.jpg';
		$asset2->newFolderId = Craft::$app->getVolumes()->ensureTopFolder($volume);
		$asset2->volumeId = $volume->id;
		$asset2->avoidFilenameConflicts = true;
		$asset2->setScenario(Asset::SCENARIO_CREATE);
		$result = Craft::$app->getElements()->saveElement($asset2);

		// Add placeholder tags.
		$tag1 = new Tag();
		$tag1->groupId = $taggroup->id;
		$tag1->title = 'Craft CMS';
		Craft::$app->getElements()->saveElement($tag1);

		$tag2 = new Tag();
		$tag2->groupId = $taggroup->id;
		$tag2->title = 'Matrix';
		Craft::$app->getElements()->saveElement($tag2);

		$tag3 = new Tag();
		$tag3->groupId = $taggroup->id;
		$tag3->title = 'Blocks';
		Craft::$app->getElements()->saveElement($tag3);

		$tag4 = new Tag();
		$tag4->groupId = $taggroup->id;
		$tag4->title = 'Blockonomicon';
		Craft::$app->getElements()->saveElement($tag4);

		$tag5 = new Tag();
		$tag5->groupId = $taggroup->id;
		$tag5->title = 'Lorem';
		Craft::$app->getElements()->saveElement($tag5);

		$tag6 = new Tag();
		$tag6->groupId = $taggroup->id;
		$tag6->title = 'Ipsum';
		Craft::$app->getElements()->saveElement($tag6);

		// Fill out entry data on the single.
		$entry = Entry::find()->sectionId($section->id)->one();
		$entry->setFieldValue('blockonomiconMatrix', [
			'new1' => [
				'type' => 'exampleBanner',
				'enabled' => '1',
				'fields' => [
					'backgroundImage' => [
						$asset1->id,
					],
					'header' => 'Blockonomicon Example Page',
					'headerPosition' => 'bottom',
				],
			],
			'new2' => [
				'type' => 'exampleTaggedContent',
				'enabled' => '1',
				'fields' => [
					'tags' => [
						$tag1->id,
						$tag2->id,
						$tag3->id,
						$tag4->id,
					],
					'header' => '',
					'copy' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed at ante. Mauris eleifend, quam a vulputate dictum, massa quam dapibus leo, eget vulputate orci purus ut lorem. In fringilla mi in ligula. Pellentesque aliquam quam vel dolor. Nunc adipiscing. Sed quam odio, tempus ac, aliquam molestie, varius ac, tellus. Vestibulum ut nulla aliquam risus rutrum interdum. Pellentesque lorem. Curabitur sit amet erat quis risus feugiat viverra. Pellentesque augue justo, sagittis et, lacinia at, venenatis non, arcu. Nunc nec libero. In cursus dictum risus. Etiam tristique nisl a nulla. Ut a orci. Curabitur dolor nunc, egestas at, accumsan at, malesuada nec, magna.',
				],
			],
			'new3' => [
				'type' => 'exampleExpander',
				'enabled' => '1',
				'fields' => [
					'header' => 'Donec Semper',
					'copy' => 'Sem nec tristique tempus, justo neque commodo nisl, ut gravida sem tellus suscipit nunc. Aliquam erat volutpat. Ut tincidunt pretium elit.',
					'startExpanded' => 0,
				],
			],
			'new4' => [
				'type' => 'exampleBanner',
				'enabled' => '1',
				'fields' => [
					'backgroundImage' => [
						$asset2->id,
					],
					'header' => 'See More Example Content Below',
					'headerPosition' => 'top',
				],
			],
			'new5' => [
				'type' => 'exampleExpander',
				'enabled' => '1',
				'fields' => [
					'header' => 'Suspendisse Potenti',
					'copy' => 'Duis urna erat, ornare et, imperdiet eu, suscipit sit amet, massa. Nulla nulla nisi, pellentesque at, egestas quis, fringilla eu, diam.',
					'startExpanded' => 1,
				],
			],
			'new6' => [
				'type' => 'exampleExpander',
				'enabled' => '1',
				'fields' => [
					'header' => 'Aliquam Pulvinar',
					'copy' => 'Suspendisse potenti. Etiam condimentum hendrerit felis. Duis iaculis aliquam enim. Donec dignissim augue vitae orci.',
					'startExpanded' => 0,
				],
			],
			'new7' => [
				'type' => 'exampleTaggedContent',
				'enabled' => '1',
				'fields' => [
					'tags' => [
						$tag5->id,
						$tag6->id,
					],
					'header' => 'Nulla Cursus',
					'copy' => 'Curabitur luctus felis a metus. Sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. In varius neque at enim. Suspendisse massa nulla, viverra in, bibendum vitae, tempor quis, lorem.  Donec dapibus orci sit amet elit. Maecenas rutrum ultrices lectus. Aliquam suscipit, lacus a iaculis adipiscing, eros orci pellentesque nisl, non pharetra dolor urna nec dolor. Integer cursus dolor vel magna. Integer ultrices feugiat sem. Proin nec nibh. Duis eu dui quis nunc sagittis lobortis.',
				],
			],
		]);
		Craft::$app->getElements()->saveElement($entry);

		Craft::$app->getSession()->setNotice(Craft::t('blockonomicon', 'Example content successfully installed.'));
		return $this->asJson(['success' => true]);
	}

	/**
	 * Uninstalls example content.
	 */
	public function actionQuickStop()
	{
		// Delete section.
		$section = Craft::$app->getSections()->getSectionByHandle('blockonomiconExample');
		if ($section != null) {
			Craft::$app->getSections()->deleteSectionById($section->id);
		}

		// Delete matrix.
		$matrix = Craft::$app->getFields()->getFieldByHandle('blockonomiconMatrix');
		if ($matrix != null) {
			Craft::$app->getFields()->deleteFieldById($matrix->id);
		}

		// Delete field group.
		$fieldgroups = Craft::$app->getFields()->getAllGroups();
		foreach ($fieldgroups as $fieldgroup) {
			if ($fieldgroup->name == 'Blockonomicon Group') {
				Craft::$app->getFields()->deleteGroupById($fieldgroup->id);
			}
		}

		// Delete asset volume.
		$volume = Craft::$app->getVolumes()->getVolumeByHandle('blockonomiconExampleAssetVolume');
		if ($volume != null) {
			Craft::$app->getVolumes()->deleteVolumeById($volume->id);
		}

		// Delete underlying asset folder.
		FileHelper::removeDirectory(Craft::getAlias('@webroot/blockonomicon-assets'));

		// Delete tag group.
		$taggroup = Craft::$app->getTags()->getTagGroupByHandle('blockonomiconExampleTagGroup');
		if ($taggroup != null) {
			Craft::$app->getTags()->deleteTagGroupById($taggroup->id);
		}

		// Delete template from the templates folder.
		@unlink(Craft::$app->getPath()->getSiteTemplatesPath() . '/_blockonomicon.html');

		// Delete blocks from the block storage folder.
		FileHelper::removeDirectory(Blockonomicon::getInstance()->blocks->getBlockPath() . '/exampleBanner');
		FileHelper::removeDirectory(Blockonomicon::getInstance()->blocks->getBlockPath() . '/exampleExpander');
		FileHelper::removeDirectory(Blockonomicon::getInstance()->blocks->getBlockPath() . '/exampleTaggedContent');

		Craft::$app->getSession()->setNotice(Craft::t('blockonomicon', 'Example content removed.'));
		return $this->asJson(['success' => true]);
	}

	/**
	 * Determines if the two arrays are equal in keys and values.
	 */
	private function assocArrayEqual($a, $b)
	{
		if (count($a) != count($b)) { // Different keys, can't be the same.
			return false;
		}

		foreach ($a as $key => $value) {
			if (!array_key_exists($key, $b)) { // Do not share a key, can't be the same.
				return false;
			}
			if (is_array($a[$key]) && is_array($b[$key])) {
				if (!$this->assocArrayEqual($a[$key], $b[$key])) {
					return false;
				}
			} else {
				if ($a[$key] != $b[$key]) { // Different values, can't be the same.
					return false;
				}
			}
		}

		return true;
	}
}
