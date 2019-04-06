<?php
/**
 * Admin helper class for Comment Policy
 *
 * @package comment-policy
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \Press_Themes\PT_Settings\Page;

/**
 * Class Comment_Policy_Admin_Helper
 */
class Comment_Policy_Admin_Helper {

	/**
	 * What menu slug we will need
	 *
	 * @var string
	 */
	private $menu_slug;

	/**
	 * Used to keep a reference of the Page, It will be usde in rendering the view.
	 *
	 * @var \Press_Themes\PT_Settings\Page
	 */
	private $page;

	/**
	 * Limit_Comments_Per_User_Admin_Helper constructor.
	 */
	public function __construct() {
		$this->menu_slug = 'comment-policy-settings';
	}

	/**
	 * Callbacks for admin
	 */
	public function setup() {
		add_action( 'admin_init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Show/render the setting page
	 */
	public function render() {
		$this->page->render();
	}

	/**
	 * Is it the setting page?
	 *
	 * @return bool
	 */
	private function needs_loading() {

		global $pagenow;

		// We need to load on options.php otherwise settings won't be reistered.
		if ( 'options.php' === $pagenow ) {
			return true;
		}

		if ( isset( $_GET['page'] ) && $_GET['page'] === $this->menu_slug ) {
			return true;
		}

		return false;
	}

	/**
	 * Initialize the admin settings panel and fields
	 */
	public function init() {

		if ( ! $this->needs_loading() ) {
			return;
		}

		// The page 'pt-example-settings' is the option key.
		$page = new Page( 'comment-policy-settings' );

		// Add a panel to to the admin
		// A panel is a Tab and what coms under that tab.
		$panel = $page->add_panel( 'general', _x( 'General', 'Admin settings panel title', 'comment-policy' ) );

		$section = $panel->add_section( 'general_settings', _x( 'General Settings', 'Admin settings', 'comment-policy' ) );

		$section->add_field(
			array(
				'name'    => 'comment_restriction_policy',
				'label'   => _x( 'Restriction Policy', 'Admin settings', 'comment-policy' ),
				'type'    => 'select',
				'options' => array(
					'all_comments' => __( 'All comments', 'comment-policy' ),
					'per_post'     => __( 'Per post', 'comment-policy' ),
				),
				'default' => 'per_post',
			)
		);

		$roles = $this->get_roles();

		foreach ( $roles as $role => $label ) {
			// A panel can contain one or more sections.
			$section = $panel->add_section( $role . '_settings', $label );

			$section->add_fields(
				array(
					array(
						'name'    => 'restrict_' . $role,
						'label'   => _x( 'Apply restriction', 'Admin settings', 'comment-policy' ),
						'type'    => 'radio',
						'options' => array(
							1 => __( 'Yes', 'comment-policy' ),
							0 => __( 'No', 'comment-policy' ),
						),
						'default' => 0,
						'desc'    => __( 'If selected comment restriction will apply for this role', 'comment-policy' ),
					),
					array(
						'name'    => $role . '_threshold_limit',
						'label'   => _x( 'Limit', 'Admin settings', 'comment-policy' ),
						'type'    => 'text',
						'default' => 5,
					),
				)
			);
		}
		// Save page for future reference.
		$this->page = $page;

		do_action( 'comment_policy_settings', $page );

		// allow enabling options.
		$page->init();
	}

	/**
	 * Add Menu
	 */
	public function add_menu() {
		add_options_page(
			_x( 'Comment policy', 'Admin settings page title', 'comment-policy' ),
			_x( 'Comment policy', 'Admin settings menu label', 'comment-policy' ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'render' )
		);
	}

	/**
	 * Get roles details
	 *
	 * @return array
	 */
	private function get_roles() {
		$editable_roles = get_editable_roles();

		$roles = array();
		foreach ( $editable_roles as $role => $role_detail ) {
			$roles[ $role ] = $role_detail['name'];
		}

		return $roles;
	}
}