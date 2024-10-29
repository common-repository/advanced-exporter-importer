<?php defined( 'ABSPATH' ) || exit();?>
<?php if ( ! empty( $posts ) ) : ?>
<tbody id="the-list-<?php echo $cpt; ?>">
	<?php foreach ( $posts as $post_id => $post_data ) : ?>
	<tr id="post-<?php echo $post_id; ?>" class="iedit all-posts-list-row">
		<th scope="row" class="check-column">
			<input type="checkbox" class="cb-select-all cb-select-all-post-<?php echo $cpt; ?>" name="cb-select-all-post-<?php echo $cpt; ?>[]" id="cb-select-all-<?php echo $cpt . '-' . $post_id; ?>" data-id="<?php echo $post_id; ?>">
		</th>
		<td class="title column-title column-primary page-title overflow-hidden">
			<strong><?php echo $post_data['title']; ?></strong>
		</td>
		<td class="title column-date page-date">
			<strong><?php echo $post_data['date']; ?></strong>
		</td>
	</tr>
	<?php endforeach; ?>
</tbody>
<?php endif; ?>
