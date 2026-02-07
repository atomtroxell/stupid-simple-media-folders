<?php
/**
 * Media Library Folders - Core Functionality
 *
 * Implements a custom taxonomy-based folder system for the WordPress media library.
 * Allows organizing media files into hierarchical folders.
 *
 * @package Stupid_Simple_Media_Folders
 */

// Mark plugin as active to prevent theme version from loading
if ( ! defined( 'SSMF_PLUGIN_ACTIVE' ) ) {
	define( 'SSMF_PLUGIN_ACTIVE', true );
}

/**
 * Register a custom taxonomy for media library folders.
 */
function ssmf_register_media_folders() {
	register_taxonomy(
		'folders',
		array( 'attachment' ),
		array(
			'hierarchical'               => true,
			'labels'                     => array(
				'name'          => 'Folders',
				'singular_name' => 'Folder',
				'search_items'  => 'Search folders',
				'all_items'     => 'All folders',
				'parent_item'   => 'Parent folder',
				'edit_item'     => 'Edit folder',
				'update_item'   => 'Update folder',
				'add_new_item'  => 'Add new folder',
				'new_item_name' => 'New folder name',
				'menu_name'     => 'Folders',
			),
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'query_var'                  => true,
			'rewrite'                    => false,
			'update_count_callback'      => '_update_generic_term_count',
		)
	);
}
add_action( 'init', 'ssmf_register_media_folders' );

/**
 * Add folder selection dropdown on Media > Add New page.
 */
function ssmf_select_folder() {
	// Check if coming from "Add Files" action (has return_to_folder parameter)
	$return_to_folder = isset( $_GET['return_to_folder'] ) ? sanitize_text_field( $_GET['return_to_folder'] ) : '';

	// Also check if viewing a filtered folder on media library page (has folders parameter)
	$viewing_folder = isset( $_GET['folders'] ) ? sanitize_text_field( $_GET['folders'] ) : '';

	// Determine which folder to lock to
	$locked_folder_slug = $return_to_folder ? $return_to_folder : $viewing_folder;

	if ( $locked_folder_slug ) {
		// Get folder name for display
		$folder_term = get_term_by( 'slug', $locked_folder_slug, 'folders' );
		$folder_name = $folder_term ? $folder_term->name : 'Folder';

		// Show heading instead of dropdown
		echo '<div class="ssmf-upload-folder-heading">';
		echo '<h3>Uploading to:</h3>';
		echo '<p>' . esc_html( $folder_name ) . '</p>';
		echo '</div>';
		return;
	}

	// Check if a folder_id is passed in the URL (from other contexts)
	$preselected_folder = isset( $_GET['folder_id'] ) ? (int) $_GET['folder_id'] : 0;

	wp_dropdown_categories(
		array(
			'hide_empty'       => 0,
			'hide_if_empty'    => false,
			'taxonomy'         => 'folders',
			'name'             => 'folder_id',
			'id'               => 'folder_id',
			'orderby'          => 'name',
			'hierarchical'     => true,
			'show_option_none' => 'Choose folder',
			'selected'         => $preselected_folder,
		)
	);
}
add_action( 'pre-upload-ui', 'ssmf_select_folder' );

/**
 * Assign uploaded attachment to selected folder.
 * WordPress core handles authentication before this hook fires.
 */
function ssmf_add_attachment_to_folder( $attachment_id ) {
	// Get folder_id from POST data (set by JavaScript during upload)
	$folder_id = ! empty( $_POST['folder_id'] ) ? (int) $_POST['folder_id'] : 0;

	// Also check for folder_id in the action parameter (used by some upload methods)
	if ( ! $folder_id && ! empty( $_REQUEST['folder_id'] ) ) {
		$folder_id = (int) $_REQUEST['folder_id'];
	}

	// Debug logging (can be removed after testing)
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'SSMF: Attachment ' . $attachment_id . ' - folder_id from POST: ' . ( ! empty( $_POST['folder_id'] ) ? $_POST['folder_id'] : 'not set' ) );
		error_log( 'SSMF: Attachment ' . $attachment_id . ' - folder_id from REQUEST: ' . ( ! empty( $_REQUEST['folder_id'] ) ? $_REQUEST['folder_id'] : 'not set' ) );
		error_log( 'SSMF: Attachment ' . $attachment_id . ' - final folder_id: ' . $folder_id );
	}

	// If no folder selected, don't assign
	if ( ! $folder_id || $folder_id === -1 ) {
		return;
	}

	// Assign the attachment to the selected folder
	$result = wp_set_object_terms( $attachment_id, $folder_id, 'folders' );

	// Debug logging (can be removed after testing)
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		if ( is_wp_error( $result ) ) {
			error_log( 'SSMF: Error assigning folder - ' . $result->get_error_message() );
		} else {
			error_log( 'SSMF: Successfully assigned attachment ' . $attachment_id . ' to folder ' . $folder_id );
		}
	}
}
add_action( 'add_attachment', 'ssmf_add_attachment_to_folder' );

/**
 * Custom Walker class for displaying hierarchical folder checkboxes.
 */
class SSMF_Folders_Walker extends Walker {

	public $db_fields = array(
		'parent' => 'parent',
		'id'     => 'term_id',
	);

	public function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= "$indent<ul class='children'>\n";
	}

	public function end_lvl( &$output, $depth = 0, $args = array() ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= "$indent</ul>\n";
	}

	public function start_el( &$output, $term, $depth = 0, $args = array(), $id = 0 ) {
		$output .= sprintf(
			"\n<li id='%s-%s'><label class='selectit'><input value='%s' type='checkbox' name='%s' id='%s' %s %s /> %s</label>",
			$term->taxonomy,
			$term->term_id,
			$term->slug,
			"tax_input[{$term->taxonomy}][{$term->slug}]",
			"in-{$term->taxonomy}-{$term->term_id}",
			checked( in_array( $term->term_id, $args['selected_cats'] ), true, false ),
			disabled( empty( $args['disabled'] ), false, false ),
			esc_html( $term->name )
		);
	}

	public function end_el( &$output, $term, $depth = 0, $args = array() ) {
		$output .= "</li>\n";
	}
}

/**
 * Add folder selection checkboxes when editing media in Grid view.
 * Supports multiple folder assignments.
 */
function ssmf_edit_attachment_fields( $form_fields, $post ) {
	$folder_fields = array(
		'label'        => 'Folders',
		'show_in_edit' => false,
		'input'        => 'html',
		'value'        => '',
	);

	$taxonomy_name = 'folders';

	// Get assigned folders
	$terms = get_the_terms( $post->ID, $taxonomy_name );
	if ( $terms ) {
		$folder_fields['value'] = join( ', ', wp_list_pluck( $terms, 'slug' ) );
	}

	ob_start();

	wp_terms_checklist(
		$post->ID,
		array(
			'taxonomy'      => $taxonomy_name,
			'checked_ontop' => false,
			'walker'        => new SSMF_Folders_Walker(),
		)
	);

	$checklist = ob_get_clean();

	// Wrap checkboxes in a collapsible dropdown
	$html = '<div class="folders-dropdown-wrapper">';
	$html .= '<button type="button" class="folders-dropdown-toggle" aria-expanded="false">';
	$html .= '<span class="folders-dropdown-label">Select folders</span>';
	$html .= '<span class="folders-dropdown-arrow">▼</span>';
	$html .= '</button>';
	$html .= '<div class="folders-dropdown-panel">';
	// Hidden field ensures tax_input[folders] exists even if no checkboxes checked
	$html .= '<input type="hidden" name="tax_input[' . esc_attr( $taxonomy_name ) . '][_folders_submitted]" value="1" />';
	$html .= '<ul class="categorychecklist form-no-clear">' . $checklist . '</ul>';
	$html .= '</div>';
	$html .= '</div>';

	$folder_fields['html'] = $html;

	$form_fields[ $taxonomy_name ] = $folder_fields;

	return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'ssmf_edit_attachment_fields', 25, 2 );

/**
 * Save folder assignments when attachment is updated.
 */
function ssmf_save_attachment_folders( $post, $attachment ) {
	$taxonomy_name = 'folders';
	$attachment_id = $post['ID'];

	// Check if taxonomy input was submitted
	if ( ! isset( $_POST['tax_input'][ $taxonomy_name ] ) ) {
		return $post;
	}

	// Get selected folder terms from the submitted data
	$selected_folders = array();
	if ( is_array( $_POST['tax_input'][ $taxonomy_name ] ) ) {
		// Extract term slugs from checkbox input
		foreach ( $_POST['tax_input'][ $taxonomy_name ] as $slug => $value ) {
			// Skip the hidden field marker
			if ( $slug === '_folders_submitted' ) {
				continue;
			}
			if ( ! empty( $value ) ) {
				$selected_folders[] = sanitize_text_field( $slug );
			}
		}
	}

	// Update the attachment's folder terms
	wp_set_object_terms( $attachment_id, $selected_folders, $taxonomy_name, false );

	return $post;
}
add_filter( 'attachment_fields_to_save', 'ssmf_save_attachment_folders', 10, 2 );

/**
 * Add folder filter dropdown in List view.
 */
function ssmf_list_view_filter() {
	global $typenow;

	if ( 'attachment' !== $typenow ) {
		return;
	}

	$selected = isset( $_GET['folders'] ) ? $_GET['folders'] : false;
	wp_dropdown_categories(
		array(
			'show_option_all' => 'All folders',
			'taxonomy'        => 'folders',
			'name'            => 'folders',
			'orderby'         => 'name',
			'selected'        => $selected,
			'hierarchical'    => true,
			'value_field'     => 'slug',
			'depth'           => 3,
			'hide_empty'      => true,
		)
	);
}
add_action( 'restrict_manage_posts', 'ssmf_list_view_filter' );

/**
 * Add custom columns to folders taxonomy table and remove checkbox column.
 */
function ssmf_add_folders_columns( $columns ) {
	// Remove checkbox column for proper semantic HTML structure
	unset( $columns['cb'] );

	// Reorder columns for file manager layout
	$new_columns = array();
	$new_columns['name']        = __( 'Folder Name', 'stupid-simple-media-folders' );
	$new_columns['posts']       = __( 'Items', 'stupid-simple-media-folders' );
	$new_columns['subfolders']  = __( 'Subfolders', 'stupid-simple-media-folders' );
	$new_columns['file_types']  = __( 'File Types', 'stupid-simple-media-folders' );
	$new_columns['description'] = __( 'Description', 'stupid-simple-media-folders' );

	return $new_columns;
}
add_filter( 'manage_edit-folders_columns', 'ssmf_add_folders_columns' );

/**
 * Make columns sortable.
 */
function ssmf_folders_sortable_columns( $sortable ) {
	$sortable['subfolders'] = 'subfolders';
	return $sortable;
}
add_filter( 'manage_edit-folders_sortable_columns', 'ssmf_folders_sortable_columns' );

/**
 * Handle custom sorting for subfolders column.
 * Note: Sorting uses direct children count for performance.
 * Display shows recursive count (all descendants).
 */
function ssmf_folders_custom_sort( $pieces, $taxonomies, $args ) {
	global $wpdb;

	// Only apply on folders taxonomy admin page
	if ( ! is_admin() || ! in_array( 'folders', $taxonomies ) ) {
		return $pieces;
	}

	// Check if we're sorting by subfolders
	if ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'subfolders' ) {
		$order = isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Add subquery to count direct children (used for sorting)
		$pieces['fields'] .= ", (SELECT COUNT(*) FROM $wpdb->term_taxonomy tt2 WHERE tt2.parent = t.term_id AND tt2.taxonomy = 'folders') as child_count";
		$pieces['orderby'] = "child_count";
		$pieces['order']   = $order;
	}

	return $pieces;
}
add_filter( 'terms_clauses', 'ssmf_folders_custom_sort', 10, 3 );

/**
 * Add data attributes to table rows for hierarchy visualization.
 */
function ssmf_add_folder_row_attributes( $tag_ID ) {
	$term = get_term( $tag_ID, 'folders' );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}

	// Add data attributes for parent/child relationship
	$data_attrs = array(
		'data-term-id' => $term->term_id,
		'data-parent-id' => $term->parent,
	);

	// Check if this term has children
	$children = get_terms( array(
		'taxonomy' => 'folders',
		'parent' => $term->term_id,
		'hide_empty' => false,
	) );

	if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
		$data_attrs['data-has-children'] = 'true';
	}

	// Get the depth level for proper indentation
	$depth = 0;
	$parent_id = $term->parent;
	while ( $parent_id > 0 ) {
		$depth++;
		$parent_term = get_term( $parent_id, 'folders' );
		$parent_id = $parent_term->parent;
	}
	$data_attrs['data-depth'] = $depth;

	// Output data attributes
	foreach ( $data_attrs as $attr => $value ) {
		echo ' ' . esc_attr( $attr ) . '="' . esc_attr( $value ) . '"';
	}
}
add_action( 'admin_footer-edit-tags.php', function() {
	$screen = get_current_screen();
	if ( $screen && $screen->taxonomy === 'folders' ) {
		?>
<script type="text/javascript">
jQuery(document).ready(function($) {
  // Initialize folder hierarchy visualization
  function initializeFolderHierarchy() {
    var $rows = $('.taxonomy-folders .wp-list-table tbody tr');

    // Save currently expanded folders before re-initializing
    var expandedFolders = {};
    $rows.each(function() {
      var $row = $(this);
      var termId = $row.attr('data-term-id');
      if (termId && $row.hasClass('is-expanded')) {
        expandedFolders[termId] = true;
      }
    });

    // First pass: Extract term IDs and slugs from each row
    $rows.each(function(index) {
      var $row = $(this);
      var $nameCell = $row.find('.name.column-name');
      var $link = $nameCell.find('a.row-title');

      if ($link.length) {
        var href = $link.attr('href');
        var matches = href.match(/tag_ID=(\d+)/);
        if (matches) {
          var termId = matches[1];
          $row.attr('data-term-id', termId);
          $row.attr('data-row-index', index);
        }

        // Extract slug from "View Media" link in row actions
        var $viewLink = $row.find('.row-actions .view_media a');
        if ($viewLink.length) {
          var viewHref = $viewLink.attr('href');
          var slugMatch = viewHref.match(/folders=([^&]+)/);
          if (slugMatch) {
            $row.attr('data-term-slug', decodeURIComponent(slugMatch[1]));
          }
        }
      }
    });

    // Build hierarchy map from WordPress's parent dropdown
    var termMap = {};
    $('#parent option').each(function() {
      var $option = $(this);
      var termId = $option.val();
      var optionText = $option.text();

      if (termId) {
        // Count leading special characters to determine depth
        // WordPress uses &nbsp; (rendered as \u00a0) or dashes
        var leadingChars = optionText.match(/^[\u00a0–—\-\s]*/)[0];
        var depth = Math.floor(leadingChars.length / 3); // WordPress uses 3 chars per level

        termMap[termId] = {
          depth: depth,
          name: optionText.trim()
        };
      }
    });

    // Apply depth and find parent for each row
    $rows.each(function() {
      var $row = $(this);
      var termId = $row.attr('data-term-id');

      if (termId && termMap[termId]) {
        var depth = termMap[termId].depth;
        $row.attr('data-depth', depth);

        // Find parent ID by looking at previous rows with depth - 1
        if (depth > 0) {
          var rowIndex = parseInt($row.attr('data-row-index'));
          for (var i = rowIndex - 1; i >= 0; i--) {
            var $prevRow = $($rows[i]);
            var prevDepth = parseInt($prevRow.attr('data-depth'));
            if (prevDepth === depth - 1) {
              var parentId = $prevRow.attr('data-term-id');
              $row.attr('data-parent-id', parentId);
              break;
            }
          }
        } else {
          $row.attr('data-parent-id', '0');
        }
      }
    });

    // Mark rows that have children and hide child rows IMMEDIATELY
    $rows.each(function() {
      var $row = $(this);
      var termId = $row.attr('data-term-id');
      var depth = parseInt($row.attr('data-depth') || 0);

      // Hide child rows (unless parent is expanded)
      if (depth > 0) {
        $row.addClass('is-child');
        var parentId = $row.attr('data-parent-id');
        // Only hide if parent is NOT expanded
        if (!expandedFolders[parentId]) {
          $row.css('display', 'none'); // Force hide with inline style
        }
      }

      // Check if this row has children
      var hasChildren = false;
      $rows.each(function() {
        if ($(this).attr('data-parent-id') === termId) {
          hasChildren = true;
          return false;
        }
      });

      // Mark rows that have children and restore their state
      if (hasChildren) {
        $row.attr('data-has-children', 'true');
        // Remove any existing state classes first
        $row.removeClass('has-children is-collapsed is-expanded');
        // Add has-children class
        $row.addClass('has-children');
        // Restore previous state or default to collapsed
        if (expandedFolders[termId]) {
          $row.addClass('is-expanded');
        } else {
          $row.addClass('is-collapsed');
        }
      }

      // Remove WordPress hierarchy prefixes (— ) from folder names
      var $link = $row.find('.row-title');
      if ($link.length) {
        var linkText = $link.text();
        // Remove leading em dashes, hyphens, and non-breaking spaces
        var cleanText = linkText.replace(/^[\u00a0–—\-\s]+/, '');

        if (linkText !== cleanText) {
          $link.text(cleanText);
        }

        // Add folder icon before the link (for expand/collapse)
        // Check if icon already exists to avoid unnecessary DOM manipulation
        var $existingIcon = $link.siblings('.ssmf-folder-icon').first();

        if ($existingIcon.length) {
          // Update existing icon classes and ARIA attributes
          var isExpanded = $row.hasClass('is-expanded');
          $existingIcon
            .removeClass('has-children is-open')
            .addClass(hasChildren ? 'has-children' : '')
            .addClass((hasChildren && isExpanded) ? 'is-open' : '');

          // Update ARIA attributes for accessibility
          if (hasChildren) {
            var ariaLabel = isExpanded ? 'Collapse folder' : 'Expand folder';
            $existingIcon
              .attr('aria-label', ariaLabel)
              .attr('aria-expanded', isExpanded ? 'true' : 'false');
          } else {
            $existingIcon
              .attr('aria-label', 'Folder')
              .removeAttr('aria-expanded');
          }
        } else {
          // Create new icon as a button for keyboard accessibility
          var iconClass = 'ssmf-folder-icon';
          var ariaAttrs = '';

          if (hasChildren) {
            iconClass += ' has-children';
            var isExpanded = $row.hasClass('is-expanded');
            if (isExpanded) {
              iconClass += ' is-open';
            }
            var ariaLabel = isExpanded ? 'Collapse folder' : 'Expand folder';
            ariaAttrs = ' aria-label="' + ariaLabel + '" aria-expanded="' + (isExpanded ? 'true' : 'false') + '"';
          } else {
            ariaAttrs = ' aria-label="Folder"';
          }

          var $icon = $('<button type="button" class="' + iconClass + '"' + ariaAttrs + '></button>');
          $link.before($icon);
        }

        // Remove link from title - navigation is handled by View action
        // Title is used for expand/collapse of folders with children
        $link.removeAttr('href');
        $link.css({
          'text-decoration': 'none',
          'cursor': hasChildren ? 'pointer' : 'default'
        });
      }
    });
  }

  // Add description tooltips to folder names
  function addFolderDescriptions() {
    // Only proceed if we have folder data with descriptions
    if (typeof ssmfData === 'undefined' || !ssmfData.terms) {
      return;
    }

    // Create a map of term IDs to descriptions
    var descriptionMap = {};
    $.each(ssmfData.terms, function(index, term) {
      if (term.description && term.description.trim() !== '') {
        descriptionMap[term.term_id] = term.description;
      }
    });

    // Add info icons to folders that have descriptions
    $('.taxonomy-folders .wp-list-table tbody tr').each(function() {
      var $row = $(this);
      var termId = $row.attr('data-term-id');

      if (termId && descriptionMap[termId]) {
        var $nameCell = $row.find('.column-name');
        var description = descriptionMap[termId];

        // Remove any existing info icon first (in case of re-initialization)
        $nameCell.find('.ssmf-folder-description-icon').remove();

        // Create info icon with tooltip
        var $infoIcon = $('<span class="ssmf-folder-description-icon dashicons dashicons-info-outline" aria-label="' + description.replace(/"/g, '&quot;') + '"></span>');

        // Insert after the row-title link
        $nameCell.find('.row-title').after($infoIcon);
      }
    });
  }

  // Create delete confirmation modal
  var modalHtml = '<div class="ssmf-folder-delete-modal">' +
    '<div class="ssmf-modal-overlay"></div>' +
    '<div class="ssmf-modal-content">' +
    '<div class="ssmf-modal-header">' +
    '<h2>Delete Folder</h2>' +
    '<button type="button" class="ssmf-modal-close" aria-label="Close">&times;</button>' +
    '</div>' +
    '<div class="ssmf-modal-body">' +
    '<p>Deleting this folder will NOT remove the files from your library.</p>' +
    '<p>If you want to delete the files, visit the folder and delete from there.</p>' +
    '</div>' +
    '<div class="ssmf-modal-footer">' +
    '<button type="button" class="button button-primary ssmf-confirm-delete">Delete Folder</button>' +
    '<button type="button" class="button ssmf-view-folder">View Folder</button>' +
    '<button type="button" class="button ssmf-cancel-delete">Cancel</button>' +
    '</div>' +
    '</div>' +
    '</div>';

  $('body').append(modalHtml);

  var $modal = $('.ssmf-folder-delete-modal');
  var deleteUrl = '';
  var termId = '';
  var termSlug = '';
  var folderName = '';

  // Run initialization on page load
  initializeFolderHierarchy();
  addFolderDescriptions();

  // Attach click handler to our custom delete links (created server-side without WordPress's onclick)
  $(document).on('click', '.ssmf-custom-delete', function(e) {
    e.preventDefault();
    e.stopPropagation();

    var $link = $(this);

    // Get data from the link's data attributes (set server-side)
    deleteUrl = $link.attr('href');
    termId = $link.attr('data-term-id');
    termSlug = $link.attr('data-term-slug');
    folderName = $link.attr('data-term-name');

    // Show modal
    $modal.addClass('is-open');

    return false;
  });

  // Modal event handlers
  // Close modal on overlay click
  $modal.on('click', '.ssmf-modal-overlay', function() {
    $modal.removeClass('is-open');
  });

  // Close modal on close button click
  $modal.on('click', '.ssmf-modal-close, .ssmf-cancel-delete', function() {
    $modal.removeClass('is-open');
  });

  // View folder button - go to media library with folder filter
  $modal.on('click', '.ssmf-view-folder', function() {
    var mediaUrl = '<?php echo admin_url( "upload.php" ); ?>';

    // Use the slug from the delete link's data attribute
    if (termSlug) {
      mediaUrl += '?folders=' + encodeURIComponent(termSlug);
    }

    window.location.href = mediaUrl;
  });

  // Confirm delete button - proceed with deletion
  $modal.on('click', '.ssmf-confirm-delete', function() {
    if (deleteUrl) {
      window.location.href = deleteUrl;
    }
  });

  // Re-run after AJAX term additions
  $(document).ajaxSuccess(function(event, xhr, settings) {
    // Check if this is a term addition AJAX call
    if (settings.data && settings.data.indexOf('action=add-tag') !== -1) {
      // Capture the parent ID before resetting the form
      var selectedParentId = $('#parent').val();

      // Hide the add folder form
      $('#col-left').hide();

      // Reset the parent dropdown to "None"
      $('#parent').val('-1');

      // Wait a moment for DOM to update and parent dropdown to refresh
      setTimeout(function() {
        // Re-initialize hierarchy (sets data attributes)
        // No need to re-setup delete links - we're using event delegation now
        initializeFolderHierarchy();
        addFolderDescriptions();

        // Update subfolder counts if a parent was selected
        if (selectedParentId && selectedParentId !== '-1') {
          // Find the parent row and increment its subfolder count
          var $parentRow = $('.taxonomy-folders .wp-list-table tbody tr[data-term-id="' + selectedParentId + '"]');
          if ($parentRow.length) {
            var $countCell = $parentRow.find('.column-subfolders');
            var currentCount = parseInt($countCell.text()) || 0;
            $countCell.text(currentCount + 1);

            // Update all ancestor counts as well (recursive count)
            var currentParentId = $parentRow.attr('data-parent-id');
            while (currentParentId && currentParentId !== '0') {
              var $ancestor = $('.taxonomy-folders .wp-list-table tbody tr[data-term-id="' + currentParentId + '"]');
              if ($ancestor.length) {
                var $ancestorCountCell = $ancestor.find('.column-subfolders');
                var ancestorCount = parseInt($ancestorCountCell.text()) || 0;
                $ancestorCountCell.text(ancestorCount + 1);
                currentParentId = $ancestor.attr('data-parent-id');
              } else {
                break;
              }
            }
          }
        }
      }, 150);
    }
  });

  // Add "Add Folder" button to page title
  $('.wrap h1').first().append(' <button type="button" id="show-add-folder-form" class="page-title-action">Add Folder</button>');

  // Hide form by default
  $('#col-left').hide();

  // Toggle form visibility (no animation)
  $('#show-add-folder-form').on('click', function(e) {
    e.preventDefault();
    var $form = $('#col-left');
    if ($form.is(':visible')) {
      $form.hide();
    } else {
      $form.show();
      // Focus first input
      $('#col-left input[type="text"]').first().focus();
    }
  });

  // Hide form after successful submission (message=1 means term added successfully)
  if (window.location.search.indexOf('message=1') > -1 || window.location.search.indexOf('message=') > -1) {
    $('#col-left').hide();
    // Reset form fields
    $('#col-left form')[0].reset();
  }
});
</script>
<?php
	}
}, 999 );

/**
 * Recursively count all subfolders (children, grandchildren, etc.)
 */
function ssmf_count_all_subfolders( $term_id ) {
	$children = get_terms(
		array(
			'taxonomy'   => 'folders',
			'parent'     => $term_id,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( is_wp_error( $children ) || empty( $children ) ) {
		return 0;
	}

	$count = count( $children );

	// Recursively count children of children
	foreach ( $children as $child_id ) {
		$count += ssmf_count_all_subfolders( $child_id );
	}

	return $count;
}

/**
 * Populate custom columns in folders taxonomy table.
 */
function ssmf_populate_folders_columns( $content, $column_name, $term_id ) {
	if ( $column_name === 'subfolders' ) {
		// Get recursive count of all subfolders (children, grandchildren, etc.)
		$content = ssmf_count_all_subfolders( $term_id );
	} elseif ( $column_name === 'file_types' ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'folders',
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		);

		$attachments = get_posts( $args );
		$types       = array();

		foreach ( $attachments as $attachment ) {
			$mime_type = get_post_mime_type( $attachment->ID );
			if ( strpos( $mime_type, 'image/' ) === 0 ) {
				$types['image'] = array(
					'icon'  => 'dashicons-format-image',
					'label' => __( 'Images', 'stupid-simple-media-folders' ),
				);
			} elseif ( strpos( $mime_type, 'video/' ) === 0 ) {
				$types['video'] = array(
					'icon'  => 'dashicons-video-alt3',
					'label' => __( 'Videos', 'stupid-simple-media-folders' ),
				);
			} elseif ( strpos( $mime_type, 'audio/' ) === 0 ) {
				$types['audio'] = array(
					'icon'  => 'dashicons-media-audio',
					'label' => __( 'Audio', 'stupid-simple-media-folders' ),
				);
			} elseif ( strpos( $mime_type, 'application/pdf' ) === 0 ) {
				$types['pdf'] = array(
					'icon'  => 'dashicons-pdf',
					'label' => __( 'PDFs', 'stupid-simple-media-folders' ),
				);
			} else {
				$types['other'] = array(
					'icon'  => 'dashicons-media-default',
					'label' => __( 'Other', 'stupid-simple-media-folders' ),
				);
			}
		}

		if ( ! empty( $types ) ) {
			$icons = array();
			foreach ( $types as $type_data ) {
				$icons[] = sprintf(
					'<span class="ssmf-file-type-icon dashicons %s" title="%s" aria-label="%s"></span>',
					esc_attr( $type_data['icon'] ),
					esc_attr( $type_data['label'] ),
					esc_attr( $type_data['label'] )
				);
			}
			$content = '<span class="ssmf-file-type-icons">' . implode( '', $icons ) . '</span>';
		} else {
			$content = '—';
		}
	}

	return $content;
}
add_filter( 'manage_folders_custom_column', 'ssmf_populate_folders_columns', 10, 3 );


/**
 * Automatically update folder slug when name changes.
 * Since slug field is hidden, always regenerate from name with uniqueness check.
 */
function ssmf_auto_update_folder_slug( $term_id, $tt_id, $taxonomy ) {
	if ( $taxonomy !== 'folders' ) {
		return;
	}

	// Get the updated term
	$term = get_term( $term_id, 'folders' );
	if ( is_wp_error( $term ) || ! $term ) {
		return;
	}

	// Generate new slug from the name
	$new_slug = sanitize_title( $term->name );

	// Ensure slug is unique using WordPress's built-in function
	// This will append -2, -3, etc. if the slug already exists
	$unique_slug = wp_unique_term_slug( $new_slug, $term );

	// Only update if slug is different (avoid infinite loop)
	if ( $term->slug !== $unique_slug ) {
		// Remove this action temporarily to prevent infinite loop
		remove_action( 'edited_folders', 'ssmf_auto_update_folder_slug', 10 );

		// Update the term with unique slug
		wp_update_term(
			$term_id,
			'folders',
			array(
				'slug' => $unique_slug,
			)
		);

		// Re-add the action
		add_action( 'edited_folders', 'ssmf_auto_update_folder_slug', 10, 3 );
	}
}
add_action( 'edited_folders', 'ssmf_auto_update_folder_slug', 10, 3 );

/**
 * Modify folder row actions - replace View with View Media, remove Quick Edit, customize Delete.
 */
function ssmf_modify_folder_row_actions( $actions, $term ) {
	// Build ordered actions array: View | Edit | Add Media | Delete
	$ordered_actions = array();

	// 1. View - only show if folder has media files
	if ( $term->count > 0 ) {
		$media_url = admin_url( 'upload.php?folders=' . $term->slug );
		$ordered_actions['view_media'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $media_url ),
			__( 'View', 'stupid-simple-media-folders' )
		);
	}

	// 2. Edit - keep the default edit action
	if ( isset( $actions['edit'] ) ) {
		$ordered_actions['edit'] = $actions['edit'];
	}

	// 3. Add Media - goes to upload page with folder pre-selected
	$add_files_url = admin_url( 'media-new.php?folder_id=' . $term->term_id . '&return_to_folder=' . urlencode( $term->slug ) );
	$ordered_actions['add_media'] = sprintf(
		'<a href="%s">%s</a>',
		esc_url( $add_files_url ),
		__( 'Add Media', 'stupid-simple-media-folders' )
	);

	// 4. Delete - custom delete link without WordPress's onclick handler
	$delete_url = wp_nonce_url(
		admin_url( 'edit-tags.php?action=delete&taxonomy=folders&tag_ID=' . $term->term_id ),
		'delete-tag_' . $term->term_id
	);
	$ordered_actions['delete'] = sprintf(
		'<a href="%s" class="submitdelete ssmf-custom-delete" data-term-id="%d" data-term-name="%s" data-term-slug="%s">%s</a>',
		esc_url( $delete_url ),
		$term->term_id,
		esc_attr( $term->name ),
		esc_attr( $term->slug ),
		__( 'Delete', 'stupid-simple-media-folders' )
	);

	return $ordered_actions;
}
add_filter( 'folders_row_actions', 'ssmf_modify_folder_row_actions', 10, 2 );




/**
 * Enqueue media library folders JavaScript and CSS.
 */
function ssmf_enqueue_media_folders_assets( $hook ) {
	// Check if we're on a folders-related page
	$is_folders_page = false;
	$screen          = get_current_screen();

	if ( $screen && $screen->taxonomy === 'folders' ) {
		$is_folders_page = true;
	}

	// Only load on media pages and folders taxonomy page
	if ( ! in_array( $hook, array( 'upload.php', 'media-new.php', 'post.php', 'post-new.php' ) ) && ! $is_folders_page ) {
		return;
	}

	// Always enqueue CSS (with Dashicons dependency for file type icons)
	wp_enqueue_style(
		'ssmf-media-folders',
		SSMF_URL . 'assets/css/media-folders.css',
		array( 'dashicons' ),
		SSMF_VERSION
	);

	// Enqueue JavaScript
	wp_enqueue_script(
		'ssmf-media-folders',
		SSMF_URL . 'assets/js/media-folders.js',
		array( 'jquery' ),
		SSMF_VERSION,
		true
	);

	// Pass folder terms to JavaScript for Grid view filter and folders page
	$localize_data = array(
		'terms' => array(),
	);

	if ( in_array( $hook, array( 'upload.php', 'media-new.php', 'post.php', 'post-new.php' ) ) || $is_folders_page ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'folders',
				'hide_empty' => false,
			)
		);

		$folder_terms = array();
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$folder_terms[] = array(
					'term_id'     => (int) $term->term_id,
					'name'        => esc_html( $term->name ),
					'slug'        => sanitize_title( $term->slug ),
					'description' => ! empty( $term->description ) ? wp_kses_post( $term->description ) : '',
				);
			}
		}
		$localize_data['terms'] = $folder_terms;
	}

	// Always localize script to prevent JavaScript errors
	wp_localize_script(
		'ssmf-media-folders',
		'ssmfData',
		$localize_data
	);
}
add_action( 'admin_enqueue_scripts', 'ssmf_enqueue_media_folders_assets' );

/**
 * Add "Back to Folders" link and Cancel button on edit folder page.
 */
function ssmf_add_back_to_folders_link() {
	$screen = get_current_screen();

	// Only on edit folder page (term.php with tag_ID parameter)
	if ( ! $screen || $screen->taxonomy !== 'folders' || ! isset( $_GET['tag_ID'] ) ) {
		return;
	}

	$folders_url = admin_url( 'edit-tags.php?taxonomy=folders&post_type=attachment' );
	?>
<script type="text/javascript">
jQuery(document).ready(function($) {
  // Add "Back to Folders" link in page title
  $('.wrap h1').first().append(' <a href="<?php echo esc_url( $folders_url ); ?>" class="page-title-action">← Back to Folders</a>');

  // Reorganize buttons: Cancel and Delete on left, Update on right
  var $submitButton = $('.edit-tag-actions .button-primary');
  var $deleteButton = $('.edit-tag-actions .delete');

  if ($submitButton.length) {
    var $actionsDiv = $('.edit-tag-actions');

    // Create Cancel button
    var $cancelButton = $('<a href="<?php echo esc_url( $folders_url ); ?>" class="button" style="margin-right: 10px;">Cancel</a>');

    // Style the actions container
    $actionsDiv.css({
      'display': 'flex',
      'justify-content': 'space-between',
      'align-items': 'center'
    });

    // Create left container for Cancel and Delete
    var $leftActions = $('<div class="left-actions"></div>');
    $leftActions.append($cancelButton);

    // Move Delete button to left side if it exists
    if ($deleteButton.length) {
      $deleteButton.css('margin-left', '10px');
      $leftActions.append($deleteButton);
    }

    // Create right container for Update
    var $rightActions = $('<div class="right-actions"></div>');
    $rightActions.append($submitButton);

    // Clear and rebuild the actions div
    $actionsDiv.empty();
    $actionsDiv.append($leftActions);
    $actionsDiv.append($rightActions);
  }
});
</script>
<?php
}
add_action( 'admin_footer', 'ssmf_add_back_to_folders_link' );

/**
 * Redirect to folders page after updating a folder.
 */
function ssmf_redirect_after_folder_update( $term_id, $tt_id, $taxonomy ) {
	if ( $taxonomy !== 'folders' ) {
		return;
	}

	// Check if this is coming from the edit form
	if ( isset( $_POST['action'] ) && $_POST['action'] === 'editedtag' ) {
		// Redirect to folders page
		$folders_url = admin_url( 'edit-tags.php?taxonomy=folders&post_type=attachment&message=3' );
		wp_safe_redirect( $folders_url );
		exit;
	}
}
add_action( 'edited_folders', 'ssmf_redirect_after_folder_update', 99, 3 );

/**
 * Add "Back to Folder" link on upload page when coming from "Add Files" action.
 */
function ssmf_add_upload_folder_navigation() {
	$screen = get_current_screen();

	// Only on media-new.php page with return_to_folder parameter
	if ( ! $screen || $screen->id !== 'media' || ! isset( $_GET['return_to_folder'] ) ) {
		return;
	}

	$folder_slug   = sanitize_text_field( $_GET['return_to_folder'] );
	$folder_url    = admin_url( 'upload.php?folders=' . urlencode( $folder_slug ) );
	$folders_url   = admin_url( 'edit-tags.php?taxonomy=folders&post_type=attachment' );
	$folder_term   = get_term_by( 'slug', $folder_slug, 'folders' );
	$folder_name   = $folder_term ? $folder_term->name : 'Folder';
	?>
<script type="text/javascript">
jQuery(document).ready(function($) {
  // Add "Back to Folders" and "View Folder" links in page title
  $('.wrap h1').first().append(' <a href="<?php echo esc_url( $folders_url ); ?>" class="page-title-action">← Back to Folders</a>');
  $('.wrap h1').first().append(' <a href="<?php echo esc_url( $folder_url ); ?>" class="page-title-action">View Folder</a>');

  // Add info message above upload area
  var $uploadUI = $('.upload-ui');
  if ($uploadUI.length) {
    var $infoBox = $('<div class="notice notice-info ssmf-upload-info-notice"><p><strong>Uploading to folder:</strong> <?php echo esc_html( $folder_name ); ?>. Files will be automatically assigned to this folder.</p></div>');
    $uploadUI.before($infoBox);
  }

  // Add "View Folder" button after upload area
  var $viewFolderBox = $('<div class="ssmf-view-folder-cta"><p>When finished uploading, view your files:</p><a href="<?php echo esc_url( $folder_url ); ?>" class="button button-primary">View Folder</a> <a href="<?php echo esc_url( $folders_url ); ?>" class="button">Manage All Folders</a></div>');
  $('.wrap .upload-php-errors').first().before($viewFolderBox);
});
</script>
<?php
}
add_action( 'admin_footer-media-new.php', 'ssmf_add_upload_folder_navigation' );

/**
 * Modify "Add New" button on filtered media library page to upload to current folder.
 */
function ssmf_modify_add_new_button_for_folder() {
	// Only on upload.php page with folders parameter
	if ( ! isset( $_GET['folders'] ) ) {
		return;
	}

	$folder_slug = sanitize_text_field( $_GET['folders'] );
	$folder_term = get_term_by( 'slug', $folder_slug, 'folders' );

	if ( ! $folder_term ) {
		return;
	}

	$folder_id = $folder_term->term_id;
	?>
	<script type="text/javascript">
	(function() {
		var folderId = '<?php echo $folder_id; ?>';
		console.log('SSMF: Setting up for folder ID:', folderId);

		// Override wp.Uploader IMMEDIATELY (before document.ready)
		if (typeof wp !== 'undefined' && wp.Uploader && !wp.Uploader._ssmfOverridden) {
			console.log('SSMF: Overriding wp.Uploader.prototype.init');
			var originalInit = wp.Uploader.prototype.init;
			wp.Uploader.prototype.init = function() {
				console.log('SSMF: wp.Uploader.init called!');
				var result = originalInit.apply(this, arguments);

				if (this.uploader && this.uploader.bind) {
					console.log('SSMF: Binding to uploader events');

					// Set folder_id before upload
					this.uploader.bind('BeforeUpload', function(up, file) {
						console.log('SSMF: BeforeUpload triggered! Setting folder_id:', folderId);
						up.settings.multipart_params = up.settings.multipart_params || {};
						up.settings.multipart_params.folder_id = folderId;
						console.log('SSMF: params:', up.settings.multipart_params);
					});

					// Show feedback on file upload
					this.uploader.bind('FileUploaded', function(up, file, response) {
						console.log('SSMF: FileUploaded!', file.name, response);
					});

					// Refresh media grid after upload complete
					this.uploader.bind('UploadComplete', function(up, files) {
						console.log('SSMF: UploadComplete! Refreshing media library...');

						// Trigger refresh of media library attachments browser
						if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
							console.log('SSMF: Triggering wp.media.frame refresh');
							var frame = wp.media.frame;
							if (frame.content && frame.content.get()) {
								var attachmentsBrowser = frame.content.get();
								if (attachmentsBrowser && attachmentsBrowser.collection) {
									console.log('SSMF: Fetching updated collection');
									attachmentsBrowser.collection.props.set({ignore: (+ new Date())});
								}
							}
						}
					});
				}

				return result;
			};
			wp.Uploader._ssmfOverridden = true;
		}

		jQuery(document).ready(function($) {
			// 1. Modify "Add New" button URL and force navigation (don't open modal)
			var $addNewButton = $('.page-title-action').filter(function() {
				return $(this).attr('href') && $(this).attr('href').indexOf('media-new.php') !== -1;
			});

			if ($addNewButton.length) {
				var currentHref = $addNewButton.attr('href');
				var newHref = currentHref + '?folder_id=' + folderId + '&return_to_folder=<?php echo urlencode( $folder_slug ); ?>';
				$addNewButton.attr('href', newHref);

				// Force navigation, prevent modal from opening
				$addNewButton.off('click').on('click', function(e) {
					e.preventDefault();
					e.stopImmediatePropagation();
					console.log('SSMF: Navigating to upload page:', newHref);
					window.location.href = newHref;
					return false;
				});

				console.log('SSMF: Modified Add New button to navigate to upload page');

				// Hide the inline uploader since we're using the dedicated upload page
				$('.uploader-inline').hide();
				console.log('SSMF: Hidden inline uploader on filtered folder page');
			}

			// 2. Check for existing uploader instances in wp.Uploader.queue
			if (typeof wp !== 'undefined' && wp.Uploader && wp.Uploader.queue) {
				console.log('SSMF: Checking wp.Uploader.queue for existing uploaders');
				_.each(wp.Uploader.queue, function(uploader) {
					if (uploader.uploader && uploader.uploader.bind) {
						console.log('SSMF: Found existing uploader in queue, binding to it');
						uploader.uploader.bind('BeforeUpload', function(up, file) {
							console.log('SSMF: Queue uploader BeforeUpload! Setting folder_id:', folderId);
							up.settings.multipart_params = up.settings.multipart_params || {};
							up.settings.multipart_params.folder_id = folderId;
						});
					}
				});
			}
		});
	})();
	</script>
	<?php
}
add_action( 'admin_footer-upload.php', 'ssmf_modify_add_new_button_for_folder' );