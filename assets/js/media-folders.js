/**
 * Media Library Folders JavaScript
 * Handles folder selection during upload and Grid view filtering
 * WCAG 2.2 AA compliant with proper ARIA attributes
 */

(function ($) {
	'use strict';

	// Toggle folder dropdown in attachment details
	$(document).on('click', '.folders-dropdown-toggle', function (e) {
		e.preventDefault();
		const $button = $(this);
		const $panel = $button.siblings('.folders-dropdown-panel');
		const isExpanded = $button.attr('aria-expanded') === 'true';

		// Close all other dropdowns
		$('.folders-dropdown-toggle[aria-expanded="true"]')
			.not($button)
			.each(function () {
				$(this).attr('aria-expanded', 'false');
				$(this)
					.siblings('.folders-dropdown-panel')
					.removeClass('is-open');
			});

		// Toggle this dropdown
		$button.attr('aria-expanded', !isExpanded);
		$panel.toggleClass('is-open');
	});

	// Close dropdown when clicking outside
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.folders-dropdown-wrapper').length) {
			$('.folders-dropdown-toggle[aria-expanded="true"]').attr(
				'aria-expanded',
				'false'
			);
			$('.folders-dropdown-panel').removeClass('is-open');
		}
	});

	// Prevent dropdown from closing when clicking checkboxes
	$(document).on('click', '.folders-dropdown-panel', function (e) {
		e.stopPropagation();
	});

	// Update folder count in dropdown label when checkboxes change
	$(document).on(
		'change',
		'.folders-dropdown-panel input[type="checkbox"]',
		function () {
			const $wrapper = $(this).closest('.folders-dropdown-wrapper');
			const $label = $wrapper.find('.folders-dropdown-label');
			const checkedCount = $wrapper.find(
				'.folders-dropdown-panel input[type="checkbox"]:checked'
			).length;

			if (checkedCount === 0) {
				$label.text('Select folders');
			} else if (checkedCount === 1) {
				$label.text('1 folder selected');
			} else {
				$label.text(checkedCount + ' folders selected');
			}
		}
	);

	// Function to initialize folder count for a wrapper
	function initializeFolderCount($wrapper) {
		// Skip if already initialized recently
		const lastInit = $wrapper.data('last-init');
		const now = Date.now();
		if (lastInit && now - lastInit < 100) {
			return;
		}
		$wrapper.data('last-init', now);

		const $label = $wrapper.find('.folders-dropdown-label');
		const checkedCount = $wrapper.find(
			'.folders-dropdown-panel input[type="checkbox"]:checked'
		).length;

		if (checkedCount > 0) {
			if (checkedCount === 1) {
				$label.text('1 folder selected');
			} else {
				$label.text(checkedCount + ' folders selected');
			}
		} else {
			$label.text('Select folders');
		}
	}

	// Initialize folder count on page load
	$(document).ready(function () {
		$('.folders-dropdown-wrapper').each(function () {
			initializeFolderCount($(this));
		});
	});

	// Folder selection on Media > Add New page
	// Wait for uploader to be ready, then bind to it
	function bindUploaderFolderSelection() {
		if (typeof window.uploader !== 'undefined' && window.uploader) {
			window.uploader.bind('BeforeUpload', function (up) {
				// Ensure multipart_params exists before setting folder_id
				up.settings.multipart_params = up.settings.multipart_params || {};
				const folderValue = $('#folder_id').val();
				if (folderValue && folderValue !== '-1') {
					up.settings.multipart_params.folder_id = folderValue;
				}
			});
		}
	}

	// Try to bind immediately if on media-new.php
	if ($('body.wp-admin').hasClass('media-new-php')) {
		// Try immediately
		bindUploaderFolderSelection();

		// Also try after a delay in case uploader isn't ready yet
		setTimeout(bindUploaderFolderSelection, 500);
		setTimeout(bindUploaderFolderSelection, 1000);
	}

	// Hook into WordPress media modal Backbone.js views for reliable initialization
	// Wrapped in try-catch to prevent breaking upload functionality
	try {
		if (
			typeof wp !== 'undefined' &&
			wp.media &&
			wp.media.view &&
			wp.media.view.Attachment &&
			wp.media.view.Attachment.Details
		) {
			// Store original render function
			const originalRender =
				wp.media.view.Attachment.Details.prototype.render;

			// Override render to initialize folder counts after details are rendered
			wp.media.view.Attachment.Details.prototype.render = function () {
				// Call original render
				const result = originalRender.apply(this, arguments);

				// Initialize folder counts after a brief delay to ensure DOM is ready
				const self = this;
				setTimeout(function () {
					try {
						if (self.$el) {
							self.$el
								.find('.folders-dropdown-wrapper')
								.each(function () {
									initializeFolderCount($(this));
								});
						}
					} catch (e) {
						// Silently fail to prevent breaking the media modal
					}
				}, 50);

				return result;
			};
		}
	} catch (e) {
		// Silently fail to prevent breaking the page
	}

	// Fallback polling for edge cases (run less frequently)
	setInterval(function () {
		try {
			$('.folders-dropdown-wrapper').each(function () {
				const $wrapper = $(this);
				// Only initialize if it has checkboxes and hasn't been initialized in the last 2 seconds
				const lastInit = $wrapper.data('last-init');
				if (
					$wrapper.find('input[type="checkbox"]').length > 0 &&
					(!lastInit || Date.now() - lastInit > 2000)
				) {
					initializeFolderCount($wrapper);
				}
			});
		} catch (e) {
			// Silently fail to prevent breaking the page
		}
	}, 1000);

	// Folder selection in media modal and media-new.php (modern WordPress)
	if (typeof wp !== 'undefined' && wp.Uploader) {
		// Store original init function
		const originalInit = wp.Uploader.prototype.init;

		// Override init to add folder tracking
		wp.Uploader.prototype.init = function () {
			// Call original init
			if (originalInit) {
				originalInit.apply(this, arguments);
			}

			// Add folder_id to upload parameters
			if (this.uploader) {
				this.uploader.bind('BeforeUpload', function (up) {
					up.settings.multipart_params =
						up.settings.multipart_params || {};

					// Get current folder selection
					const folderSelect = $('#folder_id');
					if (folderSelect.length) {
						const selectedValue = folderSelect.val();
						// Only set folder_id if a folder is actually selected (not -1 or empty)
						if (selectedValue && selectedValue !== '-1' && selectedValue !== '0') {
							up.settings.multipart_params.folder_id = selectedValue;
							// Debug log to verify folder_id is being set
							if (typeof console !== 'undefined' && console.log) {
								console.log('HCD Folders: Setting folder_id to ' + selectedValue + ' for upload');
							}
						}
					}
				});
			}
		};
	}

	// Grid view folder filter (only on media pages and when folders exist)
	if (
		typeof ssmfData !== 'undefined' &&
		ssmfData.terms &&
		ssmfData.terms.length > 0
	) {
		// Function to set up the filter
		function setupFolderFilter() {
			if (
				typeof wp === 'undefined' ||
				!wp.media ||
				!wp.media.view ||
				!wp.media.view.AttachmentFilters
			) {
				return false;
			}

			const MediaLibraryTaxonomyFilter =
				wp.media.view.AttachmentFilters.extend({
					id: 'ssmf-grid-taxonomy-filter',
					className: 'attachment-filters',
					attributes: {
						'aria-label': 'Filter by folder',
					},

					createFilters: function () {
						const filters = {};

						// Add "All folders" option first
						filters.all = {
							text: 'All folders',
							props: {
								folders: '',
							},
							priority: 10,
						};

						// Add individual folder filters
						_.each(ssmfData.terms || [], function (value) {
							filters[value.term_id] = {
								text: value.name,
								props: {
									folders: value.slug,
								},
							};
						});

						this.filters = filters;
					},
				});

			// Extend AttachmentsBrowser to add our custom filter
			const AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
			wp.media.view.AttachmentsBrowser = AttachmentsBrowser.extend({
				createToolbar: function () {
					AttachmentsBrowser.prototype.createToolbar.call(this);
					this.toolbar.set(
						'MediaLibraryTaxonomyFilter',
						new MediaLibraryTaxonomyFilter({
							controller: this.controller,
							model: this.collection.props,
							priority: -75,
						}).render()
					);
				},
			});

			return true;
		}

		// Try to set up immediately
		if (!setupFolderFilter()) {
			// If not ready, try again after delays
			setTimeout(function () {
				if (!setupFolderFilter()) {
					setTimeout(setupFolderFilter, 500);
				}
			}, 100);
		}
	}

	// Add placeholder to media library search input
	$(document).ready(function () {
		const $searchInput = $('#media-search-input');
		if ($searchInput.length && !$searchInput.attr('placeholder')) {
			$searchInput.attr('placeholder', 'Search media');
		}
	});

	// Folder hierarchy expand/collapse on taxonomy page
	if ($('body').hasClass('taxonomy-folders')) {
		// Shared function for expand/collapse logic
		function toggleFolder($row) {
			const termId = $row.attr('data-term-id');
			const isExpanded = $row.hasClass('is-expanded');
			const $icon = $row.find('.ssmf-folder-icon');

			if (isExpanded) {
				// Collapse: hide direct children
				$('.taxonomy-folders .wp-list-table tbody tr[data-parent-id="' + termId + '"]').each(function() {
					const $child = $(this);
					$child.hide();
					// Also collapse any expanded grandchildren
					if ($child.hasClass('is-expanded')) {
						toggleFolder($child);
					}
				});

				$row.removeClass('is-expanded').addClass('is-collapsed');
				$icon
					.removeClass('is-open')
					.attr('aria-expanded', 'false')
					.attr('aria-label', 'Expand folder');
			} else {
				// Expand: show direct children only
				$('.taxonomy-folders .wp-list-table tbody tr[data-parent-id="' + termId + '"]').show();

				$row.removeClass('is-collapsed').addClass('is-expanded');
				$icon
					.addClass('is-open')
					.attr('aria-expanded', 'true')
					.attr('aria-label', 'Collapse folder');
			}
		}

		// Click on folder icon - toggle expand/collapse
		// Handle both mouse clicks and keyboard (Enter/Space) events
		$(document).on('click keydown', '.taxonomy-folders .ssmf-folder-icon.has-children', function (e) {
			// For keyboard events, only respond to Enter (13) or Space (32)
			if (e.type === 'keydown' && e.which !== 13 && e.which !== 32) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();

			const $row = $(this).closest('tr');
			toggleFolder($row);
		});

		// Click on folder title - also toggle expand/collapse for folders with children
		$(document).on('click', '.taxonomy-folders tr.has-children .row-title', function (e) {
			e.preventDefault();
			e.stopPropagation();

			const $row = $(this).closest('tr');
			toggleFolder($row);
		});
	}
})(jQuery);
