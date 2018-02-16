window.BNCN = {};

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
	}
});

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
		var $block = $(event.target).closest('tr');
		var handle = $block.find('td:eq(1)').text();

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

		if (confirm(Craft.t('blockonomicon', message, {handle: handle}))) {
			var data = {
				matrix: $('#matrixblocks').data('id'),
				handle: $block.data('handle'),
				order: $block.prevAll('[data-status="saved"], [data-status="not-saved"]').length,
			};
			Craft.postActionRequest('blockonomicon/settings/import-block', data, $.proxy(function(response, status) {
				if (status === 'success') {
					if (response.success) {
						Craft.cp.displayNotice(response.message);
						$block.data('id', response.id);
						$block.attr('data-id', response.id);
						$block.data('status', 'saved');
						$block.attr('data-status', 'saved');
						$block.find('td:eq(0) .status')
							.removeClass('none')
							.removeClass('red')
							.addClass('green')
							.attr('title', Craft.t('blockonomicon', 'Attached and Saved'));
						$block.find('td:eq(3) .error').remove();
						$block.find('td:eq(4) .buttons .btn.export')
							.removeClass('disabled')
							.attr('title', '');
						$block.find('td:eq(4) .buttons .btn.delete')
							.removeClass('disabled')
							.attr('title', '');
					} else {
						Craft.cp.displayError(response.error);
					}
				}
			}, this));
		}
	},
	/**
	 * Run when a block is selected for export.
	 * On confirmation, exports the given block to the block directory, potentially overwriting the existing block configuration.
	 */
	exportBlock: function(event) {
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
		if (confirm(Craft.t('blockonomicon', message, {handle: handle}))) {
			var data = {
				block: $block.data('id'),
			};
			Craft.postActionRequest('blockonomicon/settings/export-block', data, $.proxy(function(response, status) {
				if (status === 'success') {
					if (response.success) {
						Craft.cp.displayNotice(response.message);
						$block.data('status', 'saved');
						$block.attr('data-status', 'saved');
						$block.find('td:eq(0) .status')
							.removeClass('yellow')
							.removeClass('red')
							.addClass('green')
							.attr('title', Craft.t('blockonomicon', 'Attached and Saved'));
						$block.find('td:eq(3) .error').remove();
						$block.find('td:eq(4) .buttons .btn.import')
							.removeClass('disabled')
							.attr('title', '');
					} else {
						Craft.cp.displayError(response.error);
					}
				}
			}));
		}
	},
	/**
	 * Run when a block is selected for deletion.
	 * On confirmation, deletes the given block from the matrix, including all of its associated data.
	 */
	deleteBlock: function(event) {
		var $block = $(event.target).closest('tr');
		var handle = $block.find('td:eq(1)').text();
		var _self = this;

		if ($block.data('status') == 'saved' || $block.data('status') == 'not-saved' || $block.data('status') == 'desync') {
			if (confirm(Craft.t('blockonomicon', 'Are you sure you want to delete the {handle} block? This cannot be reversed.', {handle: handle}))) {
				var data = {
					block: $block.data('id'),
				};
				Craft.postActionRequest('blockonomicon/settings/delete-block', data, $.proxy(function(response, status) {
					if (status === 'success') {
						if (response.success) {
							Craft.cp.displayNotice(response.message);
							if ($block.data('status') == 'not-saved') {
								$block.remove();
							} else {
								$block.data('status', 'not-loaded');
								$block.attr('data-status', 'not-loaded');
								$block.find('td:eq(0) .status')
									.removeClass('green')
									.removeClass('red')
									.addClass('none')
									.attr('title', Craft.t('blockonomicon', 'Not Attached'));
								$block.find('td:eq(3) .error').remove();
								$block.find('td:eq(4) .buttons .btn.export')
									.addClass('disabled')
									.attr('title', Craft.t('blockonomicon', 'Cannot export, block is not attached.'));
								$block.find('td:eq(4) .buttons .btn.delete')
									.addClass('disabled')
									.attr('title', Craft.t('blockonomicon', 'Cannot delete, block is not attached.'));
							}
							_self.updateBlockList();
						} else {
							Craft.cp.displayError(response.error);
						}
					}
				}));
			}
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
				if (val.data('status') == 'saved' || val.data('status') == 'not-saved') {
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
});