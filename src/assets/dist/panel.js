window.BNCN = {};

BNCN.MatrixEditor = Garnish.Base.extend(
	{
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
		 */
		importBlock: function(event) {
			var $block = $(event.target).closest('tr');
			var handle = $block.find('td:eq(1)').text();

			var message = '';
			if ($block.data('status') == 'not-loaded') { // First time export.
				message = 'Are you sure you want to import the {handle} block?';
			} else if ($block.data('status') == 'saved') { // Export overwrite.
				message = 'Are you sure you want to re-import the {handle} block? You may lose data if fields have changed significantly.';
			} else {
				return;
			}

			if (confirm(Craft.t('blockonomicon', message, {handle: handle}))) {
				Craft.postActionRequest('blockonomicon/settings/update-matrix-block-order', {}, $.proxy(function(response, status) {
					if (status === 'success') {
						if (response.success) {
							Craft.cp.displayNotice('Block imported!');
						} else {
							Craft.cp.displayError(response.error);
						}
					}
				}, this));
				Craft.cp.displayNotice('Import goes here');
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
			if ($block.data('status') == 'not-saved') { // First time export.
				message = 'Are you sure you want to save {handle} as a new block?';
			} else if ($block.data('status') == 'saved') { // Export overwrite.
				message = 'Are you sure you want to overwrite the {handle} block definition with this new one?';
			} else {
				return;
			}
			if (confirm(Craft.t('blockonomicon', message, {handle: handle}))) {
				var data = {
					block: $block.data('id')
				};
				Craft.postActionRequest('blockonomicon/settings/export-block', data, $.proxy(function(response, status) {
					Craft.cp.displayNotice('Export goes here');
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

			if ($block.data('status') == 'saved' || $block.data('status') == 'not-saved') {
				if (confirm(Craft.t('blockonomicon', 'Are you sure you want to delete the {handle} block? This cannot be reversed.', {handle: handle}))) {
					Craft.cp.displayNotice('Delete goes here');
				}
			}
		},
		/**
		 * Saves the current block order.
		 */
		saveBlockOrder: function() {
			this.updateBlockList();
			var data = {
				matrix: $('#matrixblocks').data('id'),
				blocks: this.$blocks.toArray().reduce(function(arr, val) {
					val = $(val);
					if (val.data('status') == 'saved' || val.data('status') == 'not-saved') {
						arr.push(val.data('id'));
					}
					return arr;
				}, []),
			};
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