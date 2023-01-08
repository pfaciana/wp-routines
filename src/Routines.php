<?php

namespace WP_Routines;

/**
 * A collection of `Page`s
 *
 * @example
 ** # Using WP Routines...
 ** add_action( 'wp_routines', function ( WP_Routines\Routines $routines ) {
 **     $page = $routines->addPage( [ ...$some_config ] );
 ** } );
 *
 */
class Routines
{
	/**
	 * All the pags added to WP Routines
	 *
	 * key/value pair. The $menu_slug is the key and the Pages instance is the value.
	 *
	 * @var Page[]
	 */
	protected $pages = [];

	/**
	 * Get the singleton
	 *
	 * @return Routines the singleton
	 */
	public static function get_instance ()
	{
		static $instance;

		if ( !$instance instanceof static ) {
			$instance = new static;
		}

		return $instance;
	}

	/**
	 * Constructor
	 *
	 * This is called on load. Routines is a singleton, so the constructor is not accessible directly.
	 */
	protected function __construct ()
	{
		if ( wp_doing_ajax() ) {
			$hook_name = 'admin_init';
		}
		elseif ( is_admin() ) {
			$hook_name = '_admin_menu';
		}
		else {
			$hook_name = 'admin_bar_init';
		}

		add_action( $hook_name, function () { $this->admin_bar_init(); }, PHP_INT_MIN );
		add_action( 'admin_enqueue_scripts', function () { $this->enqueue_scripts(); } );
		add_action( 'debug_bar_post_init', function ( $enable, $is_admin_bar_showing, $is_user_logged_in, $is_super_admin, $wp_doing_ajax, $is_wp_login ) {
			$enable && add_action( 'wp_enqueue_scripts', function () { $this->enqueue_scripts(); } );
		}, 10, 6 );
	}

	/**
	 * Enqueue the WP Routines Styles and Scripts
	 *
	 * @return void
	 */
	protected function enqueue_scripts ()
	{
		wp_enqueue_style( 'wp-routines', plugins_url( '/assets/routines-console.css', WP_ROUTINES_PLUGIN_FILE ), [], filemtime( WP_ROUTINES_PLUGIN_DIR . '/assets/routines-console.css' ) );
		wp_enqueue_script( 'wp-routines', plugins_url( '/assets/routines-console.js', WP_ROUTINES_PLUGIN_FILE ), [], filemtime( WP_ROUTINES_PLUGIN_DIR . '/assets/routines-console.js' ), TRUE );
	}

	/**
	 * Build out all the pages in WP Routines
	 *
	 * @return void
	 */
	protected function admin_bar_init ()
	{
		do_action( 'wp_routines_autoload', $this );
		do_action( 'wp_routines', $this );
		foreach ( $this->pages as $page ) {
			$page->setUpAdminPage();
			$page->registerAjax();
		}
	}

	/**
	 * Add a Page to WP Routines
	 *
	 * @see \WP_Routines\Page::__construct()
	 *
	 * @param string|array|Page $config           {
	 *                                            If this is a string, it's the $menu_slug.
	 *                                            If this is an array, it's the $config array.
	 *                                            If this is an object, it's a \WP_Routines\Page
	 * @type string             $menu_slug        <b>Required.</b> The slug name to refer to this menu by. Defaults to `routines_console`
	 * @type string|false       $parent_slug      The slug name for the parent menu. Defaults to `FALSE`
	 * @type string             $menu_title       The text to be used for the men. Defaults to `Routines`
	 * @type string             $page_title       The text to be displayed in the title tags of the page. Defaults to `Routines Console`
	 * @type string             $capability       The capability required for this menu to be displayed to the user. Defaults to `manage_options`
	 * @type string             $icon_url         The URL to the icon or dashicon to be used for this menu. Defaults to `dashicons-embed-generic`
	 * @type int|float|false    $position         The position in the menu order this item should appear. Defaults to `FALSE`
	 * @type bool               $admin_menu_page  If this should create an admin menu page. Defaults to `TRUE`
	 * @type bool               $debug_bar_panel  If this should create a WP Debug Bar panel. Defaults to `TRUE`
	 *                                            }
	 * @return Page|FALSE the new Page added or FALSE on error
	 */
	public function addPage ( $config )
	{
		if ( empty( $config ) ) {
			$config = [
				'menu_slug'       => ( $menu_slug = Page::DEFAULT_SLUG ),
				'admin_menu_page' => TRUE,
				'debug_bar_panel' => TRUE,
			];
		}

		if ( gettype( $config ) === 'object' && method_exists( $config, 'get' ) ) {
			$menu_slug = $config->get( 'menu_slug' );
		}

		if ( is_array( $config ) ) {
			$menu_slug = $config['menu_slug'];
		}

		if ( is_string( $config ) && !empty( $config ) ) {
			$config = [ 'menu_slug' => ( $menu_slug = $config ) ];
		}

		if ( !isset( $menu_slug ) || empty( $menu_slug ) ) {
			return FALSE;
		}

		if ( array_key_exists( $menu_slug, $this->pages ) ) {
			if ( gettype( $config ) === 'object' && method_exists( $config, 'get' ) ) {
				$this->pages[$menu_slug]->set( $config->get() );
				unset( $config );
			}
			else {
				$this->pages[$menu_slug]->set( $config );
			}
		}
		else {
			if ( gettype( $config ) === 'object' && method_exists( $config, 'get' ) ) {
				$this->pages[$menu_slug] = $config;
			}
			else {
				$this->pages[$menu_slug] = new Page( $config );
			}
		}

		return $this->pages[$menu_slug];
	}

	/**
	 * Add a Tasks inherited class to WP Routines
	 *
	 * @see \WP_Routines\Tasks
	 *
	 * @param Tasks $tasks The Tasks inherited class that to add to WP Routines
	 * @return Tasks itself
	 */
	public function addTasks ( $tasks )
	{
		$page = $this->addPage( $tasks->getPage() );
		$page->addTasks( $tasks );

		return $tasks;
	}

	/**
	 * Add a Task to WP Routines
	 *
	 * Arguments are identical to new Task(...arguments)
	 *
	 * @see \WP_Routines\Task::__construct()
	 *
	 * @param string|array|callable|Task $task            {
	 *                                                    If this is a string, it's the $title, and you must add the callable as the second argument.
	 *                                                    If this is a callable, it's the $callback.
	 *                                                    If this is an array, it's the $config array.
	 *                                                    If this is an array, and the $config['page'] is a string, it's the  $menu_slug.
	 * @type string                      $title           The title. Defaults to `Task #...`
	 * @type string|array                $page            {
	 *                                                    If this is a string, it's the $menu_slug.
	 *                                                    If this is an array, it's the $config array shown in this table below.
	 * @type string                      $menu_slug       The slug name to refer to this menu by. Defaults to `routines_console`
	 * @type string|false                $parent_slug     The slug name for the parent menu. Defaults to `FALSE`
	 * @type string                      $menu_title      The text to be used for the men. Defaults to `Routines`
	 * @type string                      $page_title      The text to be displayed in the title tags of the page. Defaults to `Routines Console`
	 * @type string                      $capability      The capability required for this menu to be displayed to the user. Defaults to `manage_options`
	 * @type string                      $icon_url        The URL to the icon or dashicon to be used for this menu. Defaults to `dashicons-embed-generic`
	 * @type int|float|false             $position        The position in the menu order this item should appear. Defaults to `FALSE`
	 * @type bool                        $admin_menu_page If this should create an admin menu page. Defaults to `TRUE`
	 * @type bool                        $debug_bar_panel If this should create a WP Debug Bar panel. Defaults to `TRUE`
	 *                                                    }
	 * @type string                      $group           The group the task is in. Defaults to `Main`
	 * @type callable                    $callback        The function to be called to output the content for this task
	 * @type int                         $priority        The order priority in which the task is executed. Defaults to `10`
	 *                                                    }
	 * @return Task
	 */
	public function addTask ( $task )
	{
		if ( gettype( $task ) !== 'object' ) {
			$task = new Task( ...func_get_args() );
		}

		$page = $this->addPage( $task->getPage() );
		$page->addTask( $task );

		return $task;
	}
}