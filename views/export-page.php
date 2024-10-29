<?php defined( 'ABSPATH' ) || exit(); ?>

<div class="wrap">
	<nav class="nav-tab-wrapper woo-nav-tab-wrapper wp-clearfix">
		<!-- CPTs Tab -->
		<a href="<?php echo admin_url( 'tools.php?page=' . $plugin_info['name'] ); ?>" class="nav-tab<?php echo ( ! isset( $_GET['stab'] ) || isset( $_GET['stab'] ) && 'cpts' == $_GET['stab'] ) ? ' nav-tab-active' : ''; ?>"><?php _e( 'CPTs', 'gpls-cpt-exporter-importer' ); ?></a>
	</nav>
	<?php
	if ( empty( $_GET['stab'] ) ) :
		require_once $plugin_info['path'] . '/views/cpt-export-page.php';
	endif;
	?>
</div>