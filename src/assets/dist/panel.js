window.BNCN = {};

BNCN.MatrixEditor = Garnish.Base.extend(
	{
		$blocks: null,
		init: function() {

			// Make the block table sortable, but save the block positions dynamically.
			new Craft.DataTableSorter($('#matrixblocks'), {
				helperClass: 'matrixblocksorthelper',
				copyDraggeeInputValuesToHelper: true,
				onSortChange: this.saveBlockOrder,
			});

			this.$blocks = $('#matrixblocks tbody tr');

			var $importButtons = this.$blocks.find('.btn.import');
			this.addListener($importButtons, 'click', this.importBlock);

			var $exportButtons = this.$blocks.find('.btn.export');
			this.addListener($exportButtons, 'click', this.exportBlock);

			var $deleteButtons = this.$blocks.find('.btn.delete');
			this.addListener($deleteButtons, 'click', this.deleteBlock);
		},
		importBlock: function(event) {
			console.log(event);
			var $block = $(event.target).closest('tr');
			var handle = $block.find('td:eq(1)').text();

			var message = ''
			if ($block.data('status') == 'not-loaded') { // First time export.
				message = 'Are you sure you want to import the {handle} block?';
			} else if ($block.data('status') == 'saved') { // Export overwrite.
				message = 'Are you sure you want to re-import the {handle} block? You may lose data if fields have changed significantly.';
			} else {
				return;
			}

			if ($block.data('status') == 'saved' || $block.data('status') == 'not-loaded') {
				Craft.cp.displayNotice('Import goes here');
			}
		},
		exportBlock: function(event) {
			console.log(event);
			var $block = $(event.target).closest('tr');
			var handle = $block.find('td:eq(1)').text();

			var message = ''
			if ($block.data('status') == 'not-saved') { // First time export.
				message = 'Are you sure you want to save {handle} as a new block?';
			} else if ($block.data('status') == 'saved') { // Export overwrite.
				message = 'Are you sure you want to overwrite the {handle} block definition with this new one?';
			} else {
				return;
			}
			if (confirm(Craft.t('blockonomicon', message, {handle: handle}))) {
				Craft.cp.displayNotice('Export goes here');
			}
		},
		deleteBlock: function(event) {
			console.log(event);
			var $block = $(event.target).closest('tr');
			var handle = $block.find('td:eq(1)').text();

			if ($block.data('status') == 'saved' || $block.data('status') == 'not-saved') {
				if (confirm(Craft.t('blockonomicon', 'Are you sure you want to delete the {handle} block? This cannot be reversed.', {handle: handle}))) {
					Craft.cp.displayNotice('Delete goes here');
				}
			}
		},
		saveBlockOrder: function() {
			Craft.postActionRequest('blockonomicon/settings/update-matrix-block-order', {}, $.proxy(function(response, status) {
				console.log(response, status);
				if (status === 'success') {
					if (response.success) {
						Craft.cp.displayNotice('Block order updated!');
					} else {
						Craft.cp.displayError(response.error);
					}
				}
			}, this));
		},
	});