<?php
/*
Plugin Name: WP Apply Timezone
Plugin URI: http://www.callum-macdonald.com/code/wp-apply-timezone/
Description: Adds the option to apply the current blog's timezone retroactively to existing posts. Useful if you had the wrong timezone set for a period. Creates an "Apply Timezone" option to the Edit Post Bulk Action menu.
Version: 0.1.0
Author: Callum Macdonald
Author URI: http://www.callum-macdonald.com/
*/

/**
 * PROBLEMS
 * 
 * This plugin completely screws the tags / categories on posts.
 * It also times out after 12 or so posts.
 * Probably better to modify the data at the db level with $wpdb->update() instead of wp_insert_post().
 *
 */

define('WPAT_DOMAIN', 'wpat');

if (!function_exists('wpat_admin_init')) :
function wpat_admin_init() {
	
	/**
	 * Hook into admin_init. If Apply Timezone was chosen as the Bulk Action, do it now.
	 */
	// If action and action2 are not applytimezone, return here
	if ( (!isset($_GET['action']) && !isset($_GET['action2']) ) || ($_GET['action'] != 'applytimezone' && $_GET['action2'] != 'applytimezone') )
		return;
	
	// Make sure this is a genuine request
	check_admin_referer('bulk-posts');
	
	// Get the array of posts
	$post_IDs = array_map( intval, (array) $_GET['post'] );
	
	// If there are no posts, return now
	if (empty($post_IDs))
		return;
	
	global $wpdb;
	
	// Set the counts to 0
	$done['wpat_posts_updated'] = $done['wpat_posts_skipped'] = $done['wpat_comments_updated'] = $done['wpat_comments_skipped'] = 0;
	
	// Get the time offset once to save multiple calls to get_option()
	$time_offset = get_option( 'gmt_offset' ) * 3600;
	
	$where = 'ID = ' . implode(' || ID = ', $post_IDs);
	
	// Getting one post at a time can cause PHP max execution timeouts, get them all in one go instead
	$posts = $wpdb->get_results("SELECT * FROM `$wpdb->posts` WHERE $where");
	
	foreach ($posts as $post) {
		
		// Calculate the new post time
		$new_date = date( 'Y-m-d H:i:s', strtotime($post->post_date_gmt) + $time_offset );
		
		// If the date is the same as before, record that this post was skipped
		if ($new_date == $post->post_date) {
			$done['wpat_posts_skipped']++;
		}
		else {
			// Set the date to the new date
			$wpdb->update($wpdb->posts, array('post_date' => $new_date), array('ID' => $post->ID));
			// Increment the updated count
			$done['wpat_posts_updated']++;
		}
		
		// What, if anything, are we going to do about comments?
		// Change them to the timezone of the post so that they are related. You can compare times.
		$comments = get_comments(array('post_id' => $post->ID));
		
		foreach ($comments as $comment) {
			
			$new_date = date( 'Y-m-d H:i:s', strtotime($comment->comment_date_gmt) + $time_offset );
			
			if ($new_date == $comment->comment_date) {
				$done['wpat_comments_skipped']++;
			}
			else {
				// Set the new date
				$wpdb->update($wpdb->comments, array('comment_date' => $new_date), array('comment_ID' => $comment->comment_ID));
				$done['wpat_comments_updated']++;
			}
			
		}
		
	}
	
	// Get the sendback url then redirect and exit
	$sendback = wp_get_referer();
	$sendback = add_query_arg($done, $sendback);
	wp_redirect($sendback);
	exit();
	
}
add_action('admin_init', 'wpat_admin_init');
endif;

if (!function_exists('wpat_admin_notices')) :
function wpat_admin_notices() {
	if (!empty($_GET['wpat_posts_updated']) || !empty($_GET['wpat_posts_skipped']) || !empty($_GET['wpat_comments_updated']) || !empty($_GET['wpat_comments_skipped']) ) {
		echo '<div id="wpat-message" class="updated fade"><p>';
		if (!empty($_GET['wpat_posts_updated']))
			printf( __ngettext( '%s post updated.', '%s posts updated.', $_GET['wpat_posts_updated'] ), number_format_i18n( $_GET['wpat_posts_updated'] ) ); // Standard WP domain because it's a generic WP call
		if (!empty($_GET['wpat_posts_skipped']))
			printf( ' ' . __ngettext( '%s post skipped.', '%s posts skipped.', $_GET['wpat_posts_skipped'], WPAT_DOMAIN ), number_format_i18n( $_GET['wpat_posts_skipped'] ) );
		if (!empty($_GET['wpat_comments_updated']))
			printf( ' ' .__ngettext( '%s post updated.', '%s comments updated.', $_GET['wpat_comments_updated'], WPAT_DOMAIN ), number_format_i18n( $_GET['wpat_comments_updated'] ) );
		if (!empty($_GET['wpat_comments_skipped']))
			printf( ' ' .__ngettext( '%s post skipped.', '%s comments skipped.', $_GET['wpat_comments_skipped'] ), number_format_i18n( $_GET['wpat_comments_skipped'] ) );
		echo '</p></div>';
		// Stop these being added to the paged and other links
		$_SERVER['REQUEST_URI'] = remove_query_arg(array('wpat_posts_updated', 'wpat_posts_skipped', 'wpat_comments_updated', 'wpat_comments_skipped'));
		unset($_GET['wpat_posts_updated'], $_GET['wpat_posts_skipped'], $_GET['wpat_comments_updated'], $_GET['wpat_comments_skipped']);
	}
}
add_action('admin_notices', 'wpat_admin_notices');
endif;

if (!function_exists('wpat_admin_footer')) :
function wpat_admin_footer() {
	// Only do something if we're on the edit.php page
	if (strpos($_SERVER['REQUEST_URI'], 'edit.php') === false)
		return;
	?>
<script type="text/javascript">
jQuery(document).ready(function() {
	jQuery('div.tablenav div.actions select[name^="action"]').append('<option value="applytimezone"><?php _e('Apply Timezone', WPAT_DOMAIN); ?></option>');
});
</script>
	<?php
}
add_action('admin_footer', 'wpat_admin_footer');
endif;

?>
