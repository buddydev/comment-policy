<?php
/**
 * Plugin name: Comment Policy
 * description: A plugin which enable site-admins to apply comment restriction based on user roles.
 * Author: BuddyDev
 * Author URI: https://buddydev.com
 * Plugin URI: https://buddydev.com/plugins/comment-policy
 * Version: 1.0.0
 * License: GPL
 */

/**
 * @contributor: Ravi Sharma(raviousprime)
 */

// Exit if file access directly over web.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Comment_Policy_Helper
 */
class Comment_Policy_Helper {

	/**
	 * Plugin directory path
	 *
	 * @var string
	 */
	private $path;

	/**
	 * Class instance
	 *
	 * @var Comment_Policy_Helper
	 */
	private static $instance;

	/**
	 * User comments count
	 *
	 * @var int
	 */
	private static $user_comments_count = 0;

	/**
	 * Comment_Policy_Helper constructor.
	 */
	private function __construct() {
		$this->path = plugin_dir_path( __FILE__ );

		$this->setup();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Comment_Policy_Helper
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Callbacks to necessaries actions
	 */
	public function setup() {
		add_filter(
			'comments_open',
			array( $this, 'modify_comments_open' ),
			10,
			2
		);

		add_action(
			'comment_form_logged_in_after',
			array( $this, 'modify_comment_form' )
		);

		add_action( 'plugins_loaded', array( $this, 'load_admin' ), 9996 );
	}

	/**
	 * Check if user can create groups or not.
	 *
	 * @param bool $open Post id.
	 * @param int  $post_id Post id.
	 *
	 * @return bool
	 */
	public function modify_comments_open( $open, $post_id ) {

		if ( ! is_user_logged_in() || is_super_admin() ) {
			return $open;
		}

		$user_id = get_current_user_id();

		if ( self::is_user_restricted( $user_id ) && self::has_limit_exceeded( $user_id, $post_id ) ) {
			$open = false;
		}

		return $open;
	}

	/**
	 * Add content before comment form
	 */
	public function modify_comment_form() {
		$user_id = get_current_user_id();

		// Return if user is not logged in or option not enabled.
		if ( is_super_admin() || ! self::is_user_restricted( $user_id ) ) {
			return;
		}

		$allowed_count = self::get_user_limit( get_current_user_id() );
		$allowed_count = absint( $allowed_count );

		if ( self::get_option( 'show_comment_count_limit' ) ) {
			echo sprintf( '<label>%s<span>%d</span></label>', __( 'Comment limit: ', 'comment-policy' ), $allowed_count );
		}

		if ( self::get_option( 'show_remaining_comment_count_limit' ) && $allowed_count > self::$user_comments_count ) {
			$remaining_count = $allowed_count - self::$user_comments_count;
			echo sprintf( '<label>%s<span>%d</span></label>', __( 'Remaining comment limit: ', 'comment-policy' ), absint( $remaining_count ) );
		}

		// Reset to zero.
		self::$user_comments_count = 0;
	}

	/**
	 * Load admin
	 */
	public function load_admin() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			require_once $this->path . 'admin/pt-settings/pt-settings-loader.php';
			require_once $this->path . 'admin/class-comment-policy-admin-helper.php';
			$admin_helper = new Comment_Policy_Admin_Helper();
			$admin_helper->setup();
		}
	}

	/**
	 * Get setting value
	 *
	 * @param string $key Option key.
	 *
	 * @return mixed
	 */
	public static function get_option( $key ) {
		$settings = get_option( 'comment-policy-settings', array() );

		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}

		return null;
	}

	/**
	 * Check if user group creation limited exceeded or not
	 *
	 * @param int $user_id User id.
	 * @param int $post_id Post id.
	 *
	 * @return bool
	 */
	public static function has_limit_exceeded( $user_id, $post_id ) {
		$has_exceeded = false;

		$allowed_limit = self::get_user_limit( $user_id );
		$comment_count = self::get_user_posted_comment_count( $user_id, $post_id );

		self::$user_comments_count = $comment_count;

		if ( $comment_count >= $allowed_limit ) {
			$has_exceeded = true;
		}

		return $has_exceeded;
	}

	/**
	 * Get groups count created by user
	 *
	 * @param int $user_id User id.
	 * @param int $post_id Post id.
	 *
	 * @return int
	 */
	public static function get_user_posted_comment_count( $user_id, $post_id ) {

		$comment_args = array(
			'count'      => true,
			'author__in' => (array) $user_id,
		);

		if ( 'per_post' === self::get_option( 'comment_restriction_policy' ) ) {
			$comment_args['post_id'] = $post_id;
		}

		$comments = get_comments( $comment_args );

		return $comments;
	}

	/**
	 * Get user group creation limit
	 *
	 * @param int $user_id User id.
	 *
	 * @return int
	 */
	public static function get_user_limit( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return 0;
		}

		$user_max_threshold = 0;
		foreach ( $user->roles as $role ) {
			$role_threshold = self::get_option( "{$role}_threshold_limit" );

			// Increase user threshold to max of his role threshold.
			if ( $role_threshold > $user_max_threshold ) {
				$user_max_threshold = $role_threshold;
			}
		}

		return absint( $user_max_threshold );
	}

	/**
	 * Check if user is restricted or not
	 *
	 * @param int $user_id user id.
	 *
	 * @return boolean
	 */
	public static function is_user_restricted( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( empty( $user ) ) {
			return false;
		}

		$is_restricted = true;

		foreach ( $user->roles as $role ) {
			// If any role among user roles has no restriction then restriction will be set to false.
			if ( ! self::get_option( 'restrict_' . $role ) ) {
				$is_restricted = false;
				break;
			}
		}

		return $is_restricted;
	}
}

Comment_Policy_Helper::get_instance();
