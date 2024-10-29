(function($) {
	var fetchedPosts     = [];
	var savedPosts       = [];
	var fetchCPTPostsXHR = null;

	$(document).ready( function() {
		$('.main-loader').hide();
		$('.main-loader .fetching-data').addClass('d-none');

		$('[data-toggle="tooltip"]').tooltip();

		$('.last-step-import-form, .cpt-exporter, .menus-exporter').on( 'submit', function() {
			var loader = $('.loader.main-loader');
			loader.find('.fetching-data').addClass('d-none');
			loader.find('.start-export').removeClass('d-none');
			loader.show();
		});

		// Toggle the author required attribute based on checkbox.
		$('input[name="user_mapping_type"]').on( 'change', function() {
			if ( $('#user-mapping-single-author').prop('checked') ) {
				if ( $('#user-mapping-single-author-select').val() ) {
					$('.last-step-submit').prop('disabled', false );
				} else {
					$('.last-step-submit').prop('disabled', true );
				}
			} else {
				$('.last-step-submit').prop('disabled', false );
			}
		});

		$('#user-mapping-single-author-select').on( 'change', function() {
			if ( $(this).val() ) {
				$('.last-step-submit').prop('disabled', false );
			} else {
				$('.last-step-submit').prop('disabled', true );
			}
		});

		// Toggle Select All functionality
		$('.taxonomy-select-all-checkbox').on( 'click', function() {
			var checkSelectInput = $(this).parent('label').prev('select');
			if ( $(this).prop('checked') == true ) {
				checkSelectInput.find('option').prop('selected', true );
			} else {
				checkSelectInput.find('option').prop('selected', false );
			}
		});

		// Expand All Menu Items.
		$('.menu-items-expand-all').on( 'click', function() {
			var toggleAll  = $(this).data('expand');
			toggleAll      = toggleAll == "show" ? "hide" : "show" ;
			$(this).data( 'expand', toggleAll );
			$(this).parents( '.list-group-item').find('.menu-item-controls').collapse( toggleAll );
		});

		// Check All Menu Items.
		$('.menu-items-check-all').on( 'click', function() {
			var checkedAll  = $(this).data('checked');
			checkedAll      = checkedAll == true ? false : true ;
			$(this).data( 'checked', checkedAll );
			var $checkboxes = $(this).parents('.list-group-item').find( '.menu-item-checkbox' );
			$checkboxes.prop( 'checked', checkedAll == true ? true : false );
			$(this).parents('.list-group-item').find('.menu-checkbox').prop( 'checked', checkedAll == true ? true : false );
		});

		// Check The menu Checkbox when any menu item is checked
		$('.menu-item-checkbox').on( 'change', function() {
			if ( $(this).prop( 'checked' ) ) {
				$(this).parents('.menu-wrapper').find('.menu-checkbox').prop( 'checked', true );
			}
		});

		// Stop Unchecking the Menu Checkbox if it has menu item checked.
		$('.menu-checkbox').on( 'change', function() {
			if ( ! $(this).prop( 'checked' ) ) {
				// check if it has any menu checked.
				$(this).parents('.menu-wrapper').find('.menu-item-checkbox').prop( 'checked', false );
			}
		});


		// Toggle Add selected Posts status on checking posts.
		$(document).on( 'click', '.cb-select-all, .cb-select-all-1', function() {
			var table   = $(this).parents('.wp-list-table');
			var wrapper = $(this).parents('.gpls-all-posts-list-wrapper');

			if ( table.find('.cb-select-all:checkbox:checked').length ) {
				wrapper.find('.add-selected-posts').removeClass('disabled');
			} else {
				wrapper.find('.add-selected-posts').addClass('disabled');
			}
		});

		// Add selected Posts event //
		$( '.add-selected-posts').on( 'click', function() {
			// Get the button wrapper.
			var cpt             = $(this).data('cpt');
			var allPostsWrapper = $('#all-posts-' + cpt );
			var table           = allPostsWrapper.find('.wp-list-table');
			var checkedPosts    = table.find('.cb-select-all:checkbox:checked');

			if ( ! savedPosts[ cpt ] ) {
				savedPosts[ cpt ] = [];
			}

			$.each( table.find('.cb-select-all:checkbox:checked'), function() {
				var postCheckInput = $(this);
				var postRow        = $(this).parents('.all-posts-list-row');
				var postID         = parseInt( $(this).data('id') );
				var postTitle      = postRow.find('.column-title strong').text();
				var postDate       = postRow.find('.column-date strong').text();
				var postData       = {
					id: postID,
					title: postTitle,
					date: postDate
				};

				// uncheck the Selected post and disable it.
				$(postCheckInput).prop( 'checked', false );
				$(postCheckInput).prop( 'disabled', true );

				// Add the post ID to the savedPosts variable if not exists.
				if  ( ! savedPosts[ cpt ].includes( postID ) ) {
					savedPosts[ cpt ].push( postID );

					// insert the selected post to the selectedPosts Section.
					insertSelectedPost( cpt, postData );
				}
			});

			$(this).addClass('disabled');
		});

		// Remove Selected Post when clicking on Delete Button.
		$(document).on( 'click', '.remove-selected-post', function() {
			var postID = parseInt( $(this).data('id') );
			var cpt    = $(this).data('cpt');
			// remove the ID from the savedPosts Array.
			var postIDIndex = savedPosts[ cpt ].indexOf( postID );
			if  ( postIDIndex > -1 ) {
				savedPosts[ cpt ].splice( postIDIndex, 1 );
			}

			// remove the post row from the Selected Posts Tab content.
			$(this).parents('.selected-posts-list-row').remove();

		});

		// On closing the specific posts modal.
		$('.specific-posts-modal').on( 'hidden.bs.modal', function(e) {

			var cpt                   = $(e.target).data('cpt');
			var cptSpecificPostsInput = $('.cpt_export_specific_posts_' + cpt );

			if ( savedPosts[ cpt ] ) {
				cptSpecificPostsInput.val( savedPosts[ cpt ].join( ',' ) );
			}

		});

		// Attachments Import Button loader.
		$('.import-attachment-zip').on( 'click', function() {
			$('.attachment-upload-loader').removeClass('d-none');
		});

		// Upload XML File Button Loader.
		$('.upload-xml-file').on( 'click', function() {
			$('.upload-xml-file-loader').removeClass('d-none');
		});

		// Start Import Ajax.
		$('.last-step-submit').on( 'click', function( e ) {
			e.preventDefault();
			importFunc( 0 );
		});

		// Start Import Ajax.
		$('button.export-xml-file, button.export-zip-file').on( 'click', function( e ) {
			e.preventDefault();
			var submitType = $(this).val();
			var exportType = $(this).data('cpt_type');
			exportFunc( 0, submitType, exportType );
		});

		$('.export-errors-dialog').dialog( { autoOpen: false } );

	});

	/**
	 * Start Importing Ajax.
	 * @param {integer} step
	 */
	function importFunc( step ) {
		var importType              = $('input.import-type-val').val();
		var totalItems              = parseInt( $('input.import-total-items').val() );
		var totalSteps              = parseInt( $('input.import-total-steps').val() );
		var nonce                   = $('.import-nonce').val();
		var importID                = $('.import-file-id').val();
		var authorImportType        = $('input.user-mapping-type:checked').val();
		var importedAuthors         = [];
		var userMap                 = [];
		var userMappingSingleAuthor = $('#user-mapping-single-author-select').val();
		var importerWrapper         = $('.importer-wrapper');
		toggleLoader( '', 'show', true );
		$('.main-loader .loader-text').removeClass('d-none');

		$('input.imported-authors-select').each( function() {
			importedAuthors.push( $(this).val() );
		});

		$('select.user-map-select').each( function() {
			var userMapVal = $(this).val();
			if ( 0 != userMapVal ) {
				userMap.push( $(this).val() );
			}
		});

		if ( 0 == step ) {
			$( '.main-loader .first-step-import-xml' ).removeClass('d-none');
			$( '.main-loader .rest-step-import-xml' ).addClass('d-none');
			$( '.main-loader .loader-progress-num' ).addClass('d-none');
		} else {
			$( '.main-loader .first-step-import-xml' ).addClass('d-none');
			$( '.main-loader .rest-step-import-xml' ).removeClass('d-none');
			$( '.main-loader .loader-progress-num' ).removeClass('d-none');
		}


		$.ajax({
			method: 'POST',
			url: window.gpls_cpt_exporter_importer_localize_data.ajax_url,
			data: {
				action: window.gpls_cpt_exporter_importer_localize_data.import_start_action,
				nonce: nonce,
				import_id: importID,
				import_type: importType,
				user_mapping_type: authorImportType,
				user_mapping_single_author_select: userMappingSingleAuthor,
				imported_authors: importedAuthors,
				user_map: userMap,
				step: step
			},
			success: function( resp ) {
				if ( resp['success'] ) {
					if ( false == resp['success'] ) {
						importerWrapper.empty();
						if ( resp['data'] == 'invalid_nonce' ) {
							$('.nonce-invalid').removeClass('d-none');
						}
					} else {
						if ( null !== resp['data']['progress'] ) {
							if ( 'end' === resp['data']['progress'] ) {
								importerWrapper.empty();
								$('.after-submit-notice').removeClass('d-none');
								if ( 'menu' === importType ) {
									$('.menu-import-finish').removeClass('d-none');
								}
								$('html, body').animate( { scrollTop: 0 }, 'slow' );
								toggleLoader( '', 'hide' );
							} else {
								var returnedStep = parseInt( resp['data']['progress'] );
								$('.main-loader').find( '.loader-progress' ).val( parseInt( ( ( returnedStep + 1 ) / totalSteps ) * 100 ) );
								$('.main-loader').find('.loader-progress-num').text( parseInt( ( ( ( returnedStep + 1 ) / totalSteps ) * 100 ) ) + '%' );
								importFunc( returnedStep + 1 );
								return;
							}
						}
					}

					if ( resp['data']['errors'] ) {
						resp['data']['errors'].forEach( function( error ) {
							importerWrapper.append( showNotice( 'danger', error ) );
						});
					}
				}
			},
			error: function( err ) {
				console.log( 'error: ', err );
				toggleLoader( '', 'hide' );
				$('.import-errors-dialog').html( err.responseText ).dialog( 'open' );
			}

		});
	}


	function exportFunc( step, submitType, exportType ) {
		var nonce               = $('#' + window.gpls_cpt_exporter_importer_localize_data.prefix + '-export-nonce' ).val();
		var postTypes           = [];
		var exportTypes         = {};
		var exportSpecificPosts = {};
		var cptTerms            = {};
		var cptStatuses         = {};
		var cptAuthors          = {};
		var cptStartDate        = {};
		var cptEndDate          = {};

		$('.cpt-name-checkbox:checked').each( function() {
			postTypes.push( $(this).val() );
		});

		$('.cpt-export-type-radio:checked').each( function() {
			var key   = $(this).data('cpt_type');
			var value = $(this).val();
			exportTypes[ key ] = value;
		});

		$('.cpt_export_specific_posts').each( function() {
			var key   = $(this).data('cpt_type');
			var value = $(this).val();
			exportSpecificPosts[ key ] = value;
		});

		$('.cpt-terms-select').each( function() {
			var key   = $(this).data('cpt_type');
			var tax   = $(this).data('tax_name');
			var value = $(this).val();
			if ( cptTerms[ key ] == undefined ) {
				cptTerms[ key ] = {};
			}
			cptTerms[ key ][ tax ] = value;
		});

		$('.cpt-statuses-select').each( function() {
			var key   = $(this).data('cpt_type');
			var value = $(this).val();
			cptStatuses[ key ] = value;
		});

		$('.cpt-authors-select').each( function() {
			var key   = $(this).data('cpt_type');
			var value = $(this).val();
			cptAuthors[ key ] = value;
		});

		$('.cpt-start-date-select').each( function() {
			var key   = $(this).data('cpt_type');
			var value = $(this).val();
			cptStartDate[ key ] = value;
		});

		$('.cpt-end-date-select').each( function() {
			var key   = $(this).data('cpt_type');
			var value = $(this).val();
			cptEndDate[ key ] = value;
		});

		var data = {
			action: window.gpls_cpt_exporter_importer_localize_data.export_start_action,
			export_type: 'cpt',
			cpt_name: postTypes,
			cpt_export_type: exportTypes,
			cpt_export_specific_posts: exportSpecificPosts,
			cpt_name_terms: cptTerms,
			cpt_statuses: cptStatuses,
			cpt_authors: cptAuthors,
			cpt_start_date: cptStartDate,
			cpt_end_date: cptEndDate,
			submit: submitType,
			step: step,
		};

		data[ window.gpls_cpt_exporter_importer_localize_data.prefix + '-export-nonce' ]  = nonce;
		data[ window.gpls_cpt_exporter_importer_localize_data.prefix + '-export-submit' ] = 1;

		resetNotices();
		if ( 'xml' === exportType ) {
			toggleLoader( '', 'show', true );
			$('.main-loader .start-export-xml').removeClass('d-none');
			$('.main-loader .first-step-export-xml').removeClass('d-none');
			$('.main-loader .rest-step-export-xml').addClass('d-none');
			$('.main-loader .start-export-zip').addClass('d-none');

		} else {
			toggleLoader( '', 'show' );
			$('.main-loader .start-export-zip').removeClass('d-none');
			$('.main-loader .start-export-xml').addClass('d-none');
		}

		if ( 0 == step ) {
			$('.main-loader .loader-progress').val( 0 );
			$('.main-loader .loader-progress-num').text( '0%' );
			$('.main-loader .loader-progress-num').addClass('d-none');
			$('.main-loader .first-step-export-xml').removeClass('d-none');
			$('.main-loader .rest-step-export-xml').addClass('d-none');
		} else {
			$('.main-loader .first-step-export-xml').addClass('d-none');
			$('.main-loader .rest-step-export-xml').removeClass('d-none');
			$('.main-loader .loader-progress-num').removeClass('d-none');
		}

		$.ajax({
			method: 'POST',
			url: window.gpls_cpt_exporter_importer_localize_data.ajax_url,
			data: data,
			success: function( resp ) {
				if ( resp['success'] == false ) {
					$('.export-errors-dialog').html( resp.data ).dialog( 'open' );
					toggleLoader( '', 'hide' );
				} else {
					if ( resp['data']['progress'] != 'end' ) {
						$('.main-loader .loader-progress').val( parseInt( resp['data']['progress'] ) );
						$('.main-loader .loader-progress-num').text( parseInt( resp['data']['progress'] ) + '%' );
						$('.main-loader .first-step-export-xml').addClass('d-none');
						$('.main-loader .rest-step-export-xml').removeClass('d-none');
						exportFunc( step + 5, submitType, exportType );
						return;
					} else {
						$('.main-loader .loader-progress').val( 100 );
						$('.main-loader .loader-progress-num').text( '100%' );
						window.location.href = resp['data']['download_link'];
						setTimeout(
							function() {
								toggleLoader( '', 'hide' );
							},
							8000
						);
					}
					return;
				}
			},
			error: function( err ) {
				console.log( 'error: ', err );
				toggleLoader( '', 'hide' );
				$('.export-errors-dialog').html( err.responseText ).dialog( 'open' );
			}
		});

	}

	/**
	 * Hide All notice upon export.
	 *
	 */
	function resetNotices() {
		$('.notice-skipped').addClass('d-none');
		$('.notice-same').addClass('d-none');
	}

	/**
	 * Insert Post Row in Selceted Posts Tab.
	 * @param {string} cpt
	 * @param {object} postData
	 */
	function insertSelectedPost( cpt, postData ) {
		var selectedPostsTable  = $('#selected-posts-' + cpt + ' table tbody' );
		var selectedPostElement = `<tr class="selected-posts-list-row" id="post-selected-` + postData['id'] + `" class="iedit">
				<td class="title column-title column-primary page-title">
					<strong>` + postData['title'] + `</strong>
				</td>
				<td class="title column-date page-date">
					<strong>` + postData['date'] + `</strong>
				</td>
				<td class="actions column-actions column-primary page-action">
					<button data-id="` + postData['id'] + `" type="button" class="remove-selected-post btn btn-danger" data-cpt="` + cpt + `">Remove</button>
				</td>
			</tr>`;

			selectedPostsTable.append( selectedPostElement );
	}

	/**
	 * Toggle the posts List loader.
	 *
	 * @param {string} cpt
	 * @param {string} action
	 */
	function toggleLoader( cpt = '', action, progress = false ) {
		if ( cpt == '' ) {
			var loader = $('.main-loader');
		} else {
			var loader = $('#all-posts-' + cpt ).find('.loader');
		}
		if ( 'show' == action ) {
			loader.show();
		} else if ( 'hide' == action ) {
			loader.hide();
		}

		if ( progress ) {
			loader.find( '.loader-progress').removeClass('d-none');
			loader.find( '.loader-progress-num').removeClass('d-none');
		} else {
			loader.find( '.loader-progress').addClass('d-none');
			loader.find( '.loader-progress-num').addClass('d-none');
		}

	}

	/**
	 * Update the Posts List Table.
	 *
	 * @param {string} wrapper
	 * @param {array} posts
	 */
	function updateListPostsTable( cpt, posts ) {
		var wrapper = $( '#all-posts-' + cpt );
		wrapper.find('.wp-list-table').find('tbody').remove();
		wrapper.find('.wp-list-table').append( posts );
	}

	/**
	 * Show Notice Message.
	 *
	 * @param {string} type
	 * @param {string} message
	 */
	function showNotice( type, message ) {
		var html = `<div class="alert alert-` + type + `" >` + message + `</div>`;
		return html;
	}


})(jQuery);