<?php
/**
 * The template for displaying comments
 *
 * The area of the page that contains both current comments
 * and the comment form.
 *
 * @package WordPress
 * @subpackage Storyform
 */

/*
 * If the current post is protected by a password and
 * the visitor has not yet entered the password we will
 * return early without loading the comments.
 */
if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area" data-win-control="UI.CommentsArea">
	<?php if ( have_comments() ) : ?>
		<div class="comments-header">
			<h3 class="comments-title">
				<?php
					printf( _nx( '1 Comment', '%1$s Comments', get_comments_number(), 'comments title', Storyform_Api::get_instance()->get_textdomain() ),
						number_format_i18n( get_comments_number() ), get_the_title() );
				?>
			</h3>
			<button class="comments-add-new" data-win-control="UI.CommentsAdd">Add a Comment</button>
		</div>

		<ol class="comment-list">
			<?php
				wp_list_comments( array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 46,
					'per_page'	  => 1000000,
					'format'	  => 'html5'
				) );
			?>
		</ol><!-- .comment-list -->

	<?php endif; // have_comments() ?>

	<?php
		// If comments are closed and there are comments, let's leave a little note, shall we?
		if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) :
	?>
		<p class="no-comments"><?php _e( 'Comments are closed.', 'twentyfifteen' ); ?></p>
	<?php endif; ?>

	<?php 

	$commenter = wp_get_current_commenter();
	$req = get_option( 'require_name_email' );
	$aria_req = ( $req ? " aria-required='true'" : '' );

	$comment_args = array(
		'comment_field' => '<p class="comment-form-comment"><textarea id="comment" placeholder="' . _x( 'Enter your comment', 'noun' ) . '" name="comment" cols="45" rows="8" aria-required="true"></textarea></p>',
		'fields' 		=>  array(
			'author' =>
				'<p class="comment-form-author"> ' .
			    '<input id="author" placeholder="' . __( 'Name', 'domainreference' ) . ' ' . ($req ? __( '(required)' ) : '') . '" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) .
			    '" size="30"' . $aria_req . ' /></p>',

			'email' =>
			    '<p class="comment-form-email"> ' .
			    '<input id="email" placeholder="' . __( 'Email', 'domainreference' ) . ' ' . ($req ? __( '(required)' ) : '') . '" name="email" type="text" value="' . esc_attr(  $commenter['comment_author_email'] ) .
			    '" size="30"' . $aria_req . ' /></p>',
		),
		'comment_notes_before'	=> '',
		'comment_notes_after'	=> ''
	);

	comment_form( $comment_args ); 

	?>

</div><!-- .comments-area -->
