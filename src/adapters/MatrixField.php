<?php
/**
 * Blockonomicon plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\blockonomicon\adapters;

use charliedev\blockonomicon\Blockonomicon;
use charliedev\blockonomicon\events\RenderImportControlsEvent;
use charliedev\blockonomicon\events\SaveFieldEvent;
use charliedev\blockonomicon\events\LoadFieldEvent;

use Craft;

use yii\base\Event;

/**
 * Blockonomicon adapter for built-in Matrix fields.
 * Exports data about inner fields and blocks, finds and provides existing IDs for blocks
 * and fields when importing in an attempt to save data when re-importing.
 * Not really usable on its own; other fields that allow matrix-in-matrix type
 * schemes can make use of this in order to export/import data.
 */
class MatrixField
{
	/**
	 * Binds to necessary event handlers.
	 */
	public static function setup()
	{
		// On export, remove source and site-specific properties.
		Event::on(
			Blockonomicon::class,
			Blockonomicon::EVENT_SAVE_FIELD,
			function (SaveFieldEvent $event) {

				// Ignore any fields that are not Users fields.
				if (get_class($event->field) != \craft\fields\Matrix::class) {
					return;
				}

				$event->settings['typesettings']['blocktypes'] = [];

				foreach ($event->field->getBlockTypes() as $block) {
					$blocksettings = [];
					$blocksettings['handle'] = $block->handle;
					$blocksettings['name'] = $block->name;

					foreach ($block->getFields() as $field) {
						$fielddata = Blockonomicon::getInstance()->blocks->getFieldData($field);
						$blocksettings['fields'][] = $fielddata;
					}

					$event->settings['typesettings']['blocktypes'][] = $blocksettings;
				}
			}
		);

		// On import, re-add source and site-specific properties from the user-supplied options.
		Event::on(
			Blockonomicon::class,
			Blockonomicon::EVENT_LOAD_FIELD,
			function (LoadFieldEvent $event) {

				// Ignore any fields that are not Users fields.
				if ($event->settings['type'] != \craft\fields\Matrix::class) {
					return;
				}

				// TODO
			}
		);

		// Generate controls to set data stripped on block export.
		Event::on(
			Blockonomicon::class,
			Blockonomicon::EVENT_RENDER_IMPORT_CONTROLS,
			function (RenderImportControlsEvent $event) {

				// Ignore any fields that are not Assets fields.
				if ($event->settings['type'] != \craft\fields\Matrix::class) {
					return;
				}

				// Gather possible controls for each inner field.
				foreach ($event->settings['typesettings']['blocktypes'] as $block) {
					$blockcontrols = [];
					foreach ($block['fields'] as $field) {
						$secondaryevent = new RenderImportControlsEvent();
						$secondaryevent->handle = $event->handle . '[' . $block['handle'] . '][' . $field['handle'] . ']'; // Nest the block's handle inside the current handle.
						$secondaryevent->cachedoptions = $event->cachedoptions[$block['handle']][$field['handle']] ?? null; // Get cached options for the subfield, if possible.
						$secondaryevent->settings = $field;
						Blockonomicon::getInstance()->trigger(Blockonomicon::EVENT_RENDER_IMPORT_CONTROLS, $secondaryevent);
						if (!empty($secondaryevent->controls)) {
							$blockcontrols[] = [
								'handle' => $field['handle'],
								'name' => $field['name'],
								'control' => $secondaryevent->controls,
							];
						}
					}
				}

				// No controls, don't add them to the event.
				if (count($blockcontrols) == 0) {
					return;
				}

				$event->controls = Craft::$app->getView()->renderTemplate('blockonomicon/_adapters/MatrixFieldAdapter.html', [
					'safeHandle' => implode('_', preg_split('/[\[\]]+/', $event->handle, -1, PREG_SPLIT_NO_EMPTY)),
					'blockHandle' => $event->handle,
					'settings' => $event->settings,
					'cachedOptions' => $event->cachedoptions,
					'blockControls' => $blockcontrols,
				]);
			}
		);
	}
}
