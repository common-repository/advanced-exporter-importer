<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap">
	<a class="clearfix float-right my-3 button button-primary" href="<?php echo admin_url( 'tools.php?page=' . $plugin_info['name'] . '&amp;tab=import' ); ?>"><?php _e( 'Refresh' ); ?></a>

	<div class="gpls_cpt_importer my-5 py-3">
			<div class="attachment-upload-loader  w-100 h-100 position-absolute d-none">
				<div class="text-white wrapper text-center position-absolute d-block w-100 " style="top: 125px;z-index:1000;">
					<h6 class="text-dark loader-small-text mb-2"><?php _e( 'Importing Attachments Files...', 'gpls-cpt-exporter-importer' ); ?></h6>
					<img class="loader-icon" src="<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>"  />
				</div>
				<div class="overlay position-absolute d-block w-100 h-100" style="opacity:0.7;background:#FFF;z-index:100;"></div>
			</div>
			<div class="upload-xml-file-loader w-100 h-100 position-absolute d-none">
				<div class="text-white wrapper text-center position-absolute d-block w-100 " style="top: 125px;z-index:1000;">
					<h6 class="text-dark loader-small-text mb-2"><?php _e( 'Processing XML file...', 'gpls-cpt-exporter-importer' ); ?></h6>
					<img class="loader-icon" src="<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>"  />
				</div>
				<div class="overlay position-absolute d-block w-100 h-100" style="opacity:0.7;background:#FFF;z-index:100;"></div>
			</div>
		<div class="row">
			<?php
			if ( ! empty( $_GET['saved'] ) && empty( $_GET['disabled'] ) && empty( $_GET['updated'] ) ) :
				echo '<div class="notice notice-success"><p>' . __( 'Attachments have been imported in Uploads Folder!.', 'gpls-cpt-exporter-importer' ) . '</p></div>';
			endif;
			?>
			<div class="col-md-12">
				<div class="d-none text-center mx-auto w-100 nonce-invalid">
					<p><?php _e( 'The link you followed has expired.' ); ?></p>
					<p><a href="tools.php?page=<?php echo $plugin_info['name'] . '&tab=import'; ?>"><?php _e( 'Please type again.' ); ?></a></p>
				</div>

				<div class="d-none after-submit-notice">
					<strong class="d-block my-3"><?php _e( 'Import is finished!', 'gpls-cpt-exporter-importer' ); ?></strong>
					<strong class="d-none menu-import-finish">
						<p><?php _e( 'Check the Menus for any invalid or missing posts, terms, archives targets', 'gpls-cpt-exporter-importer' ); ?><a class="ml-1" href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php _e( 'Menus' ); ?></a></p>
					</strong>
				</div>
				<div class="importer-wrapper">
				<?php
				if ( ! empty( $_GET['updated'] ) || ! empty( $_GET['saved'] ) ) {
					if ( empty( $_GET['updated'] ) ) :
						?>
						<h4><?php _e( 'Import XML FILE', 'gpls-cpt-exporter-importer' ); ?></h4>
						<p class="font-weight-bold nonessential my-3"><?php _e( 'This step will import the posts and attachments in the database and generate attachments differnet sizes, This process could take some time for big content!', 'gpls-cpt-exporter-importer' ); ?></p>
						<?php
					endif;
					$importer_helper->handle_cpts_and_menus_import();
				}
				if ( empty( $_GET['step'] ) && empty( $_GET['saved'] ) && empty( $_GET['updated'] ) ) :
					?>
					<div class="wrapper clearfix">
						<h4 class="d-inline-block"><?php _e( 'Import Attachment ZIP FILE', 'gpls-cpt-exporter-importer' ); ?></h4>
						<a data-toggle="tooltip" data-placement="top" title="<?php _e( 'If you did this step or no related attachments found, proceed to the xml file step', 'gpls-cpt-exporter-importer' ); ?>" class="ml-3 button d-inline-block" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'import', 'saved' => true, 'disabled' => true ), admin_url( 'tools.php' ) . '?page=' . $plugin_info['name'] ) ); ?>"><?php _e( 'Move to The XML File Step', 'gpls-cpt-exporter-importer' ); ?></a>
					</div>
					<p class="nonessential my-3"><?php _e( 'This step will import the attachments files in the uploads folder', 'gpls-cpt-exporter-importer' ); ?></p>
					<?php
					$importer_helper->handle_zip_import();
				endif;
				?>
				</div>
			</div>
		</div>
	</div>

	<div class="import-errors-dialog"></div>
</div>
