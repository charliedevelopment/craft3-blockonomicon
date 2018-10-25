(function() {

window.BNCN = {};

/**
 * Controller that runs functionality on the Blockonomicon documentation page.
 */
BNCN.DocumentationController = Garnish.Base.extend({
	init: function() {
		this.addListener($('.btn.submit.quick-start'), 'click', this.quickStart);
		this.addListener($('.btn.submit.quick-stop'), 'click', this.quickStop);
	},
	/**
	 * Runs the example content installer.
	 */
	quickStart: function(event) {
		$(event.target).addClass('icon').addClass('add').addClass('loading');
		Craft.postActionRequest('blockonomicon/settings/quick-start', {}, $.proxy(function(response, status) {
			if (status === 'success') {
				if (response.success) {
					window.location.reload();
				} else {
					$(event.target).removeClass('icon').removeClass('add').removeClass('loading');
					Craft.cp.displayError(response.error);
				}
			}
		}, this));
	},
	/**
	 * Runs the example content uninstaller.
	 */
	quickStop: function() {
		Craft.postActionRequest('blockonomicon/settings/quick-stop', {}, $.proxy(function(response, status) {
			if (status === 'success') {
				if (response.success) {
					window.location.reload();
				} else {
					Craft.cp.displayError(response.error);
				}
			}
		}, this));
	},
});

/**
 * Controller that runs on the Blockonomicon block overview page.
 */
BNCN.OverviewEditor = Garnish.Base.extend({
	init: function() {
		this.addListener($('#bncnrebuildfilesbtn'), 'click', this.rebuildFiles);
	},
	/**
	 * Rebuilds the cached, "minified" css and js block files.
	 */
	rebuildFiles: function() {
		Craft.postActionRequest('blockonomicon/settings/rebuild-files', {}, $.proxy(function(response, status) {
			if (status === 'success') {
				if (response.success) {
					Craft.cp.displayNotice(response.message);
				} else {
					Craft.cp.displayError(response.error);
				}
			}
		}, this));
	},
});

/**
 * Controlelr that runs on the Blockonomicon matrix editor page.
 */
BNCN.MatrixEditor = Garnish.Base.extend({
	$blocks: null,
	init: function() {

		// Make the block table sortable, but save the block positions dynamically.
		new Craft.DataTableSorter($('#matrixblocks'), {
			helperClass: 'matrixblocksorthelper',
			copyDraggeeInputValuesToHelper: true,
			onSortChange: $.proxy(this.saveBlockOrder, this),
		});

		this.updateBlockList();

		var $importButtons = this.$blocks.find('.btn.import');
		this.addListener($importButtons, 'click', this.importBlock);

		var $exportButtons = this.$blocks.find('.btn.export');
		this.addListener($exportButtons, 'click', this.exportBlock);

		var $deleteButtons = this.$blocks.find('.btn.delete');
		this.addListener($deleteButtons, 'click', this.deleteBlock);

		this.addListener($('#content .btn.quicksort'), 'click', this.sortBlocksAlphabetically);
	},
	/**
	 * Updates the internally stored block list with the newest set of blocks (mostly for updating their order).
	 */
	updateBlockList: function() {
		this.$blocks = $('#matrixblocks tbody tr');
	},
	/**
	 * Run when a block is selected for import.
	 * On confirmation, imports the given block into the current matrix at the given location.
	 * Note: If blocks are imported too quickly, they may wind up in a different order on the server side than they are on the client side.
	 */
	importBlock: function(event) {
		if ($(event.target).hasClass('disabled')) { // Do not handle any disabled element interactions.
			return;
		}

		var _self = this;

		function runImport(importdata = {}) {
			_self.startTemporaryDisable();
			var data = {
				matrix: $('#matrixblocks').data('id'),
				handle: $block.data('handle'),
				order: $block.prevAll('[data-status="saved"], [data-status="not-saved"]').length,
				options: importdata,
			};
			Craft.postActionRequest('blockonomicon/settings/import-block', data, $.proxy(function(response, status) {
				if (status === 'success') {
					if (response.success) {
						window.location.reload();
					} else {
						_self.stopTemporaryDisable();
						Craft.cp.displayError(response.error);
					}
				}
			}, this));
		}

		var $block = $(event.target).closest('tr');
		var handle = $block.find('td:eq(1)').text();

		// There is a special import control for this block, show it first.
		var importcontrol = $('#import-controls .import-control[data-handle="' + handle + '"]');
		if (importcontrol.length > 0) {
			var modal;
			var settings = {
				autoShow: false,
				onHide: function() {
					importcontrol.find('.btn').off('.import');
					$('#import-controls').append(importcontrol);
					modal.destroy();
				},
			};
			modal = new Garnish.Modal('<div class="modal bncn-matrix-modal"></div>', settings);
			importcontrol.find('.btn.cancel').on('click.import', function() {
				modal.hide();
			});
			importcontrol.find('.btn.import').on('click.import', function() {
				data = modal.$container.find('form').serializeArray().reduce(function(arr, val) { // Gather each input element's properties up.
					var names = val.name.split(/\]\[\]?|\[|\]/); // Split array-based input names.
					if (names.length > 1) { // Must be an array, strip off the extra item from the split.
						names = names.slice(0, -1);
					}
					console.log(names);
					var ref = arr; // Reference the base array first.
					var i;
					for (i = 0; i < names.length - 1; i += 1) { // Every key but the last in any array-based names creates an inner array.
						if (names[i] == '') { // Indexed array, push next value.
							if (names[i + 1] == '') { // Next value is also indexed.
								ref.push([]);
							} else { // Next value is keyed.
								ref.push({});
							}
							ref = ref[ref.length - 1]; // Set reference to newly added array.
						} else { // String name, add to referenced array and then set reference to (new) array.
							if (!ref[names[i]]) { // Create next value if it doesn't exist.
								if (names[i + 1] == '') { // Next value is indexed.
									ref[names[i]] = [];
								} else { // Next value is keyed.
									ref[names[i]] = {};
								}
							}
							ref = ref[names[i]]; // Set reference to newly added array.
						}
					};
					if (names[names.length - 1] == '') { // Last value is indexed.
						ref.push(val.value);
					} else { // Last value is keyed.
						ref[names[names.length - 1]] = val.value;
					}

					return arr;
				}, {});
				runImport(data);
				modal.hide();
			});
			modal.$container.append(importcontrol);
			modal.show();
			return;
		}

		var message = '';
		if ($block.data('status') == 'desync') { // Out of sync import.
			message = 'The current block settings and the definition file do not match! Are you sure you want to import the {handle} block?';
		} else if ($block.data('status') == 'not-loaded') { // First time import.
			message = 'Are you sure you want to import the {handle} block?';
		} else if ($block.data('status') == 'saved') { // Import overwrite.
			message = 'Are you sure you want to re-import the {handle} block? You may lose data if fields have changed significantly.';
		} else {
			return;
		}

		showBasicModal(
			Craft.t('blockonomicon', message, {handle: handle}),
			'Import',
			runImport
		);
	},
	/**
	 * Run when a block is selected for export.
	 * On confirmation, exports the given block to the block directory, potentially overwriting the existing block configuration.
	 */
	exportBlock: function(event) {
		if ($(event.target).hasClass('disabled')) { // Do not handle any disabled element interactions.
			return;
		}

		var _self = this;

		function runExport() {
			_self.startTemporaryDisable();
			var data = {
				block: $block.data('id'),
			};
			Craft.postActionRequest('blockonomicon/settings/export-block', data, $.proxy(function(response, status) {
				if (status === 'success') {
					if (response.success) {
						window.location.reload();
					} else {
						_self.stopTemporaryDisable();
						Craft.cp.displayError(response.error);
					}
				}
			}));
		}

		var $block = $(event.target).closest('tr');
		var handle = $block.find('td:eq(1)').text();

		var message = '';
		if ($block.data('status') == 'desync') { // Out of sync export.
			message = 'The current block settings and the definition file do not match! Are you sure you want to overwrite the {handle} block definition with this new one? This will backup the existing definition, and does not overwrite any of the other bundled files.';
		} else if ($block.data('status') == 'not-saved') { // First time export.
			message = 'Are you sure you want to save {handle} as a new block?';
		} else if ($block.data('status') == 'saved') { // Export overwrite.
			message = 'Are you sure you want to overwrite the {handle} block definition with this new one? This will backup the existing definition, and does not overwrite any of the other bundled files.';
		} else {
			return;
		}

		showBasicModal(
			Craft.t('blockonomicon', message, {handle: handle}),
			'Export',
			runExport
		);
	},
	/**
	 * Run when a block is selected for deletion.
	 * On confirmation, deletes the given block from the matrix, including all of its associated data.
	 */
	deleteBlock: function(event) {
		if ($(event.target).hasClass('disabled')) { // Do not handle any disabled element interactions.
			return;
		}

		var $block = $(event.target).closest('tr');
		var handle = $block.find('td:eq(1)').text();
		var _self = this;

		function runDelete() {
			_self.startTemporaryDisable();
			var data = {
				block: $block.data('id'),
			};
			Craft.postActionRequest('blockonomicon/settings/delete-block', data, $.proxy(function(response, status) {
				if (status === 'success') {
					if (response.success) {
						window.location.reload();
					} else {
						_self.stopTemporaryDisable();
						Craft.cp.displayError(response.error);
					}
				}
			}));
		}

		if ($block.data('status') == 'saved'
			|| $block.data('status') == 'not-saved'
			|| $block.data('status') == 'desync') {

			showBasicModal(
				Craft.t('blockonomicon', 'Are you sure you want to delete the {handle} block? This cannot be reversed.', {handle: handle}),
				'Delete',
				runDelete
			);
		}
	},
	/**
	 * Saves the current block order.
	 */
	saveBlockOrder: function() {
		this.updateBlockList();
		var data = {
			blocks: this.$blocks.toArray().reduce(function(arr, val) {
				val = $(val);
				if (val.data('status') == 'saved' || val.data('status') == 'not-saved' || val.data('status') == 'desync') {
					arr.push(val.data('id'));
				}
				return arr;
			}, []),
		};
		if (data.blocks.length == 0) { // Ignore reorder updates if there are no blocks in the matrix.
			return;
		}
		Craft.postActionRequest('blockonomicon/settings/update-matrix-block-order', data, $.proxy(function(response, status) {
			if (status === 'success') {
				if (response.success) {
					Craft.cp.displayNotice(response.message);
				} else {
					Craft.cp.displayError(response.error);
				}
			}
		}, this));
	},
	/**
	 * Sorts blocks alphabetically by title, saving the order immediately afterward.
	 */
	sortBlocksAlphabetically: function() {
		var sorted = this.$blocks.toArray().sort(function(a, b) {
			a = $(a).find('td:eq(0)').text();
			b = $(b).find('td:eq(0)').text();
			if (a > b) {
				return 1;
			} else if (a < b) {
				return -1;
			} else {
				return 0;
			}
		});
		var table = $('#matrixblocks');
		sorted.forEach(function(block) {
			table.append(block);
		});
		this.saveBlockOrder();
	},
	/**
	 * Disables all buttons.
	 */
	startTemporaryDisable: function() {
		$('#matrixblocks .buttons .btn:not(.disabled)').addClass('temporary').addClass('disabled');
	},
	/**
	 * Re-enables buttons that were temporarily disabled.
	 */
	stopTemporaryDisable: function() {
		$('#matrixblocks .buttons .btn.temporary').removeClass('disabled');
	},
});

function showBasicModal(message, submittext, submitaction) {
	var modal;
	var settings = {
		autoShow: false,
		onHide: function() {
			modal.destroy();
		},
	};
	modal = new Garnish.Modal('<div class="modal fitted bncn-matrix-modal"><form class="body"><p></p><div class="actions buttons"></div></form></div>', settings);
	modal.$container.find('.body p').text(message);
	modal.$container.find('.body .buttons').append('<a class="btn cancel" role="button">Cancel</a>');
	modal.$container.find('.body .cancel').on('click', function() {
		modal.hide();
	});
	modal.$container.find('.body .buttons').append('<a class="btn submit" role="button"></a>');
	modal.$container.find('.body .submit').text(submittext);
	modal.$container.find('.body .submit').on('click', function() {
		submitaction();
		modal.hide();
	});
	modal.show();
}

})();