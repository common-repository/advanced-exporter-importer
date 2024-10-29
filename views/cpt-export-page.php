<?php defined( 'ABSPATH' ) || exit; ?>

<style>
	.modal td { vertical-align:middle; }
	.main-loader .loader-text { position: fixed; left: 50%; top: 38%; transform: translate(-50%, -50%); color: #000; }
	.main-loader .loader-small-text { position: fixed; left: 50%; top: 44%; transform: translate(-50%, -50%); color: #000; }
	.main-loader .loader-icon { position: fixed; left: 50%; top: 50%; transform: translate(-50%, -50%); }
	.main-loader .loader-progress-num { position: fixed; color: #000; left: 50%; top: 60%; transform: translate(-50%, -50%); }
	.main-loader .loader-progress { position: fixed; left: 50%; top: 65%; transform: translate(-50%, -50%); }
</style>
<?php
if ( ! empty( $cpts ) ) :
	?>
	<form method="post" class="cpt-exporter position-relative">
		<div class="main-loader loader w-100 h-100 position-absolute">
			<div class="text-white wrapper text-center position-absolute d-block w-100 " style="top: 125px;z-index:1000;">
				<h4 class="loader-text fetching-data mb-4"><?php _e( 'Fetching and Preparing Post types', 'gpls-cpt-exporter-importer' ); ?></h4>
				<h4 class="loader-text start-export-xml d-none mb-4"><?php _e( 'Exporting Data..., It may take several minutes for big content!', 'gpls-cpt-exporter-importer' ); ?></h4>
				<h6 class="loader-small-text first-step-export-xml d-none mb-2"><?php _e( 'Applying Database Queries...', 'gpls-cpt-exporter-importer' ); ?></h6>
				<h6 class="loader-small-text rest-step-export-xml d-none mb-2"><?php _e( 'Generating the XML file...', 'gpls-cpt-exporter-importer' ); ?></h6>
				<h4 class="loader-text export-total-posts d-none mb-4"></h4>
				<h4 class="loader-text start-export-zip d-none mb-4"><?php _e( 'Exporting Attachments Zip File...', 'gpls-cpt-exporter-importer' ); ?></h4>
				<img class="loader-icon" style="position: fixed;" src="<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>"  />
				<div class="d-none loader-progress-num" ></div>
				<progress class="d-none loader-progress" value="0" max="100" ></progress>
			</div>
			<div class="overlay position-absolute d-block w-100 h-100" style="opacity:0.7;background:#FFF;z-index:100;"></div>
		</div>
		<fieldset>
		<?php
		foreach ( $cpts as $cpt_slug ) :
			$cpt_obj   = get_post_type_object( $cpt_slug );
			$cpt_count = $exporter_helper->get_cpt_count( $cpt_slug );
			?>
			<div class="cpt-name-row my-5">
				<label>
					<input type="checkbox" name="cpt_name[]" class="cpt-name-checkbox" value="<?php esc_attr_e( $cpt_slug ); ?>" >
					<?php esc_html_e( $cpt_obj->label ); ?>
					<?php echo '( ' . $cpt_count . ' )'; ?>
				</label>
				<?php
				if ( 0 == $cpt_count ) :
					?>
					<div class="subtitle w-100" style="display: inline-block;">
						<?php echo sprintf( __( 'No %s yet:', 'gpls-cpt-exporter-importer' ), $cpt_obj->label ); ?>
					</div>
					<?php
				else :
					?>
				<div class="subtitle" id="accordion-wrapper-<?php echo $cpt_slug; ?>">
					<div class="select-by-filters w-100">

						<div id="select-by-filters-<?php echo $cpt_slug; ?>" class="collapse show" aria-labelledby="heading-<?php echo $cpt_slug; ?>" data-parent="#accordion-<?php echo $cpt_slug; ?>">
							<div class="card-body">

								<!-- Filters Here -->
								<div style="display:inline-block;">
									<p class="pl-0 nonessential"><?php echo __( 'Available Status:', 'gpls-cpt-exporter-importer' ); ?></p>
									<select class="cpt-statuses-select" data-cpt_type="<?php echo $cpt_slug; ?>" name="cpt_statuses[<?php esc_attr_e( $cpt_slug ); ?>][]" multiple>
										<option value=""><?php _e( '&mdash; Select &mdash;' ); ?></option>
										<?php
										$post_statuses = $exporter_helper->get_cpt_statuses( $cpt_slug );
										foreach ( $post_statuses as $status_name ) :
											?>
											<option value="<?php echo esc_attr( $status_name ); ?>"><?php echo esc_attr( $status_name ); ?></option>
											<?php
										endforeach;
										?>
									</select>
								</div>
								<div style="display:inline-block;" class="ml-3">
									<fieldset>
										<div class="inline-block mb-1">
											<legend class="screen-reader-text"><?php _e( 'Date range' ); ?></legend>
											<label for="post-start-date" class="label-responsive mr-1"><?php _e( 'Start date:' ); ?></label>
											<select class="cpt-start-date-select" data-cpt_type="<?php echo $cpt_slug; ?>" name="cpt_start_date[<?php esc_attr_e( $cpt_slug ); ?>]">
												<option value=""><?php _e( '&mdash; Select &mdash;' ); ?></option>
												<?php $exporter_helper->export_date_options( $cpt_slug ); ?>
											</select>
										</div>
										<div class="inline-block">
											<label for="post-end-date" class="label-responsive mr-2"><?php _e( 'End date:' ); ?></label>
											<select class="cpt-end-date-select" data-cpt_type="<?php echo $cpt_slug; ?>" name="cpt_end_date[<?php esc_attr_e( $cpt_slug ); ?>]">
												<option value=""><?php _e( '&mdash; Select &mdash;' ); ?></option>
												<?php $exporter_helper->export_date_options( $cpt_slug ); ?>
											</select>
										</div>
									</fieldset>
								</div>
								<div style="display:inline-block;" class="ml-3">
									<p class="pl-0 nonessential"><?php _e( 'Authors' ); ?></p>
									<?php $exporter_helper->export_authors_options( $cpt_slug ); ?>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</div>
			<div class="modal specific-posts-modal fade" id="modal-cpt-posts-<?php echo $cpt_slug; ?>" tabindex="-1" role="dialog" aria-hidden="true" data-cpt="<?php echo $cpt_slug; ?>">
				<div class="modal-dialog" style="max-width: 1400px;">
					<div class="modal-content">
						<div class="modal-body accordion">
							<div class="row">
								<div class="col-3">
									<div class="list-group" role="tablist">
										<a data-cpt="<?php echo $cpt_slug; ?>" id="tab-cpt-selected-<?php echo $cpt_slug; ?>" class="export-list-selected-tab list-group-item list-group-item-action active" data-toggle="list" role="tab" href="#selected-posts-<?php echo $cpt_slug; ?>">Selected Posts</a>
										<a data-cpt="<?php echo $cpt_slug; ?>" id="tab-cpt-all-<?php echo $cpt_slug; ?>" class="export-list-all-tab list-group-item list-group-item-action" data-toggle="list" role="tab" href="#all-posts-<?php echo $cpt_slug; ?>">All Posts</a>
									</div>
								</div>
								<div class="col-9 tab-content">

									<!-- Select CPT posts Wrapper -->
									<div id="selected-posts-<?php echo $cpt_slug; ?>" aria-labelledby="tab-cpt-selected-<?php echo $cpt_slug; ?>" class="gpls-selected-posts-list-wrapper tab-pane fade show active">
										<table class="wp-list-selected-table widefat fixed striped posts">
											<thead>
												<tr>
													<th scope="col" id="title-<?php echo $cpt_slug; ?>" class="manage-column column-title column-primary">
														<span>Title</span>
													</th>
													<th scope="col" id="title-<?php echo $cpt_slug; ?>" class="manage-column column-date">
														<span>Date</span>
													</th>
													<th scope="col" id="date-<?php echo $cpt_slug; ?>" class="manage-column column-date">
														<span>Actions</span>
													</th>
												</tr>
											</thead>
											<tbody>

											</tbody>
										</table>
									</div>
									<!-- All CPT posts Wrapper -->
									<div id="all-posts-<?php echo $cpt_slug; ?>" aria-labelledby="tab-cpt-all-<?php echo $cpt_slug; ?>" class="gpls-all-posts-list-wrapper tab-pane fade position-relative overflow-hidden">
										<?php echo $exporter_helper->loader_html(); ?>
										<div class="actions overflow-hidden">
											<div id="all-posts-actions-<?php echo $cpt_slug; ?>" class="my-3 float-left all-cpt-posts-actions">
												<button type="button" data-cpt="<?php echo $cpt_slug; ?>" class="btn btn-primary add-selected-posts disabled"><?php _e( 'Add selected', 'gpls-cpt-exporter-importer' ); ?></button>
											</div>
											<?php
											if ( $cpt_count > 20 ) :
												$full_pages = ceil( $cpt_count / 20 );
											?>
											<div id="all-posts-pagination-<?php echo $cpt_slug; ?>" class="my-3 float-right all-cpt-posts-pagination">
												<div class="tablenav-pages m-0">
													<span class="float-left displaying-num p-1"><?php echo $cpt_count; ?> items</span>
													<span class="float-left pagination-links">
														<button type="button" data-cpt="<?php echo $cpt_slug; ?>" class="btn btn-primary float-left mr-1 first-page button disabled"  data-paged="1"><span>&#8606;</span></button>
														<button type="button" data-cpt="<?php echo $cpt_slug; ?>" class="btn btn-primary float-left mr-1 prev-page button disabled"  data-paged="1"><span>&#8592;</span></button>
														<span class="float-left mr-1 paging-input">
															<input data-cpt="<?php echo $cpt_slug; ?>" type="number" min="1" max="<?php echo $full_pages; ?>" value="1" class="float-left current-page" id="current-page-selector-<?php echo $cpt_slug; ?>" size="3">
															<span class="float-left tablenav-paging-text">
																<span class="float-left p-1"> of </span>
																<span class="float-left total-pages p-1" data-pages="<?php echo $full_pages; ?>"><?php echo $full_pages; ?></span>
															</span>
														</span>
														<button type="button" data-cpt="<?php echo $cpt_slug; ?>" class="btn btn-primary float-left mr-1 next-page button <?php echo ( ( $full_pages <= 1 ) ? 'disabled' : '' ); ?>" data-paged="2"><span>&#8594;</span></button>
														<button type="button" data-cpt="<?php echo $cpt_slug; ?>" class="btn btn-primary float-left mr-1 last-page button <?php echo ( ( $full_pages <= 1 ) ? 'disabled' : '' ); ?>" data-paged="<?php echo $full_pages; ?>"><span>&#8608;</span></button>
													</span>
												</div>
											</div>
											<?php endif; ?>
										</div>
										<table class="wp-list-table widefat fixed striped posts">
											<thead>
												<tr>
													<td id="cb-<?php echo $cpt_slug; ?>" class="manage-column column-cb check-column">
														<label for="cb-select-all-1-<?php echo $cpt_slug; ?>" for="cb-select-all-1-<?php echo $cpt_slug; ?>" class="screen-reader-text">Select All</label>
														<input type="checkbox" id="cb-select-all-1-<?php echo $cpt_slug; ?>" class="cb-select-all-1">
													</td>
													<th scope="col" id="title-<?php echo $cpt_slug; ?>" class="manage-column column-title column-primary">
														<span>Title</span>
													</th>
													<th scope="col" id="date-<?php echo $cpt_slug; ?>" class="manage-column column-date">
														<span>Date</span>
													</th>
												</tr>
											</thead>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
		</fieldset>
		<?php wp_nonce_field( $plugin_info['name'] . '-export-nonce', $plugin_info['name'] . '-export-nonce', false ); ?>
		<input type="hidden" name="_wp_http_referer" value="<?php echo admin_url( 'tools.php?page=' . $plugin_info['name'] ); ?>">
		<input type="hidden" name="export_type" value="cpt" >
		<input type="hidden" name="<?php echo $plugin_info['name'] . '-export-submit'; ?>" value="1" >
		<p class="submit">
			<button data-cpt_type="xml" class="d-block my-2 export-xml-file button button-primary" type="submit" name="submit" value="export_xml"><?php _e( 'Download Export XML File' ); ?></button>
			<button data-cpt_type="zip" class="d-block my-2 export-zip-file button button-primary" type="submit" name="submit" value="export_media_zip"><?php _e( 'Download Export Attachments Zip File' ); ?></button>
		</p>
	</form>

	<div class="export-errors-dialog"></div>

	<?php
endif;
