<?php
/**
 * Plugin Name: Message Board
 * Plugin URI:  http://themehybrid.com
 * Description: Simple forums for us simple folks.
 * Version:     1.0.0-pre-alpha
 * Author:      Justin Tadlock
 * Author URI:  http://justintadlock.com
 * Text Domain: message-board
 * Domain Path: /languages
 */

/**
 * Sets up and initializes the Message Board plugin.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
final class Message_Board {

	/**
	 * Plugin version number.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $version = '1.0.0';

	/**
	 * Current database version.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    int
	 */
	public $db_version = 1;

	/**
	 * Directory path to the plugin folder.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $dir_path = '';

	/**
	 * Directory URI to the plugin folder.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $dir_uri = '';

	/**
	 * Forum types (e.g., normal, category).
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    array
	 */
	public $forum_types = array();

	/**
	 * Topic types (e.g., normal, super, sticky).
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    array
	 */
	public $topic_types = array();

	/**
	 * Forum query.  Is assigned a WP_Query object.  On forum archive/single views, this is the 
	 * main `$wp_query` object.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    object
	 */
	public $forum_query;

	/**
	 * Sub-forum query.  Is assigned a WP_Query object.  This is only useful when getting the sub-forums 
	 * of a particular forum.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    object
	 */
	public $subforum_query;

	/**
	 * Topic query.  Is assigned a WP_Query object.  On topic single/archive views, this is the 
	 * main `$wp_query` object.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    object
	 */
	public $topic_query;

	/**
	 * Reply query.  Is assigned a WP_Query object.  This is mainly useful on single topic views, 
	 * where it is used to display the replies to the current topic.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    object
	 */
	public $reply_query;

	/**
	 * Search query.  Is assigned a WP_Query object.  This is the `$wp_query` object when viewing 
	 * a forum search results page.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    object
	 */
	public $search_query;

	/**
	 * User query. This holds the results of `get_users()` and is particularly useful for the user 
	 * archive page.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    object|null
	 */
	public $user_query = null;

	/**
	 * Used for temporarily saving a deleted post object.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    object|null
	 */
	public $deleted_post = null;

	/**
	 * Returns the instance.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {

		static $instance = null;

		if ( is_null( $instance ) ) {
			$instance = new Message_Board;
			$instance->setup();
			$instance->includes();
			$instance->setup_actions();
		}

		return $instance;
	}

	/**
	 * Constructor method.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Initial plugin setup.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function setup() {

		/* Set up the forum queries. */
		$this->forum_query    = new WP_Query();
		$this->subforum_query = new WP_Query();
		$this->topic_query    = new WP_Query();
		$this->reply_query    = new WP_Query();
		$this->search_query   = new WP_Query();

		/* Set up the directory path and URI. */
		$this->dir_path = trailingslashit( plugin_dir_path( __FILE__ ) );
		$this->dir_uri  = trailingslashit( plugin_dir_url(  __FILE__ ) );
	}

	/**
	 * Loads include and admin files for the plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function includes() {

		require_once( $this->dir_path . 'inc/functions-post-types.php'     );
		require_once( $this->dir_path . 'inc/functions-post-statuses.php'  );
		require_once( $this->dir_path . 'inc/functions-meta.php'           );
		require_once( $this->dir_path . 'inc/functions-filters.php'        );
		require_once( $this->dir_path . 'inc/functions-formatting.php'     );
		require_once( $this->dir_path . 'inc/functions-query.php'          );
		require_once( $this->dir_path . 'inc/functions-capabilities.php'   );
		require_once( $this->dir_path . 'inc/functions-rewrite.php'        );
		require_once( $this->dir_path . 'inc/functions-handler.php'        );
		require_once( $this->dir_path . 'inc/functions-shortcodes.php'     );
		require_once( $this->dir_path . 'inc/functions-options.php'        );
		require_once( $this->dir_path . 'inc/functions-admin-bar.php'      );
		require_once( $this->dir_path . 'inc/template-hierarchy.php'       );

		/* Load common files. */
		require_once( $this->dir_path . 'inc/common/template.php' );

		/* Load forum files. */
		require_once( $this->dir_path . 'inc/forum/functions.php' );
		require_once( $this->dir_path . 'inc/forum/template.php'  );
		require_once( $this->dir_path . 'inc/forum/types.php'     );

		/* Load topic files. */
		require_once( $this->dir_path . 'inc/topic/functions.php' );
		require_once( $this->dir_path . 'inc/topic/template.php'  );
		require_once( $this->dir_path . 'inc/topic/types.php'     );

		/* Load reply files. */
		require_once( $this->dir_path . 'inc/reply/functions.php' );
		require_once( $this->dir_path . 'inc/reply/template.php'  );

		/* Load user files. */
		require_once( $this->dir_path . 'inc/user/functions.php'     );
		require_once( $this->dir_path . 'inc/user/template.php'      );
		require_once( $this->dir_path . 'inc/user/subscriptions.php' );

		/* Load search files. */
		require_once( $this->dir_path . '/inc/search/template.php' );

		/* Load admin files. */
		if ( is_admin() ) {

			/* Common admin files. */
			require_once( $this->dir_path . 'admin/admin.php'      );
			require_once( $this->dir_path . 'admin/meta-boxes.php' );

			/* Edit posts screen files. */
			require_once( $this->dir_path . 'admin/edit-forum.php' );
			require_once( $this->dir_path . 'admin/edit-topic.php' );
			require_once( $this->dir_path . 'admin/edit-reply.php' );

			/* Post screen files. */
			require_once( $this->dir_path . 'admin/post-forum.php' );
			require_once( $this->dir_path . 'admin/post-topic.php' );
			require_once( $this->dir_path . 'admin/post-reply.php' );

			/* User screen files. */
			require_once( $this->dir_path . 'admin/users.php' );
		}
	}

	/**
	 * Sets up initial actions.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function setup_actions() {

		/* Internationalize the text strings used. */
		add_action( 'plugins_loaded', array( $this, 'i18n' ), 2 );

		/* Add front end styles. */
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		/* Register activation hook. */
		register_activation_hook( __FILE__, array( $this, 'activation' ) );
	}

	/**
	 * Loads the translation files.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function i18n() {
		load_plugin_textdomain( 'message-board', false, 'message-board/languages' );
	}

	/**
	 * Loads the front end stylesheet in case the theme doesn't support the plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue_scripts() {

		if ( !current_theme_supports( 'message-board' ) )
			wp_enqueue_style( 'message-board', trailingslashit( $this->dir_uri ) . 'css/style.css' );
	}

	/**
	 * Method that runs only when the plugin is activated.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function activation() {

		/*
		 * Temporary code to simplify post type names and to match bbPress.
		 *
		global $wpdb;

		$wpdb->query( "UPDATE  {$wpdb->posts} SET  post_type = 'topic' WHERE  post_type = 'forum_topic'" );
		$wpdb->query( "UPDATE  {$wpdb->posts} SET  post_type = 'reply' WHERE  post_type = 'forum_reply'" );
		*/

		/* Get the administrator role. */
		$role = get_role( 'administrator' );

		/* If the administrator role exists, add required capabilities for the plugin. */
		if ( !empty( $role ) ) {

			$role->add_cap( 'manage_forums' );

			$role->add_cap( 'create_forums'      );
			$role->add_cap( 'edit_forums'        );
			$role->add_cap( 'edit_others_forums' );
			$role->add_cap( 'moderate_forums'    );
			$role->add_cap( 'read_forums'        );

			$role->add_cap( 'create_topics'      );
			$role->add_cap( 'edit_topics'        );
			$role->add_cap( 'edit_others_topics' );
			$role->add_cap( 'moderate_topics'    );
			$role->add_cap( 'read_topics'        );

			$role->add_cap( 'create_replies'      );
			$role->add_cap( 'edit_replies'        );
			$role->add_cap( 'edit_others_replies' );
			$role->add_cap( 'moderate_replies'    );
			$role->add_cap( 'read_replies'        );
		}
	}
}

/**
 * Gets the instance of the Message_Board class.  This function is useful for quickly grabbing data 
 * used throughout the plugin.
 *
 * @since  1.0.0
 * @access public
 * @return object
 */
function message_board() {
	return Message_Board::get_instance();
}

/* Let's do this thang! */
message_board();
