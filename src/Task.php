<?php

namespace WP_Routines;

/**
 * A class that represents a single function/task to be executed on a `Page`
 */
class Task
{
	/**
	 * The task title
	 *
	 * @var string
	 */
	protected $title;
	/**
	 * The page the task is on
	 *
	 * If a string, it's the admin page `menu_slug`
	 * If an array, it's the \WP_Routines\Page $config
	 *
	 * @see \WP_Routines\Page
	 *
	 * @var string|array
	 */
	protected $page;
	/**
	 * The group the task is in
	 *
	 * @var string
	 */
	protected $group;
	/**
	 * The function called when the task is run
	 *
	 * @var callable
	 */
	protected $callback;
	/**
	 * The order priority in which the task is executed
	 *
	 * @var int
	 */
	protected $priority = 10;
	/**
	 * This will autoload this Task instance into the $routines manager
	 *
	 * @var bool
	 */
	protected $autoload = FALSE;

	/**
	 * Constructor
	 *
	 * @param string|array|callable $config          {
	 *                                               If this is a string, it's the $title.
	 *                                               If this is a callable, it's the $callback.
	 *                                               If this is an array, it's the $config array.
	 *                                               If this is an array, and the $config['page'] is a string, it's the  $menu_slug.
	 * @type string                 $title           The title. Defaults to `Task #...`
	 * @type string|array           $page            {
	 *                                               If this is a string, it's the $menu_slug.
	 *                                               If this is an array, it's the $config array shown in this table below.
	 * @type string                 $menu_slug       The slug name to refer to this menu by. Defaults to `routines_console`
	 * @type string|false           $parent_slug     The slug name for the parent menu. Defaults to `FALSE`
	 * @type string                 $menu_title      The text to be used for the men. Defaults to `Routines`
	 * @type string                 $page_title      The text to be displayed in the title tags of the page. Defaults to `Routines Console`
	 * @type string                 $capability      The capability required for this menu to be displayed to the user. Defaults to `manage_options`
	 * @type string                 $icon_url        The URL to the icon or dashicon to be used for this menu. Defaults to `dashicons-embed-generic`
	 * @type int|float|false        $position        The position in the menu order this item should appear. Defaults to `FALSE`
	 * @type bool                   $admin_menu_page If this should create an admin menu page. Defaults to `TRUE`
	 * @type bool                   $debug_bar_panel If this should create a WP Debug Bar panel. Defaults to `TRUE`
	 *                                               }
	 * @type string                 $group           The group the task is in. Defaults to `Main`
	 * @type callable               $callback        The function to be called to output the content for this task
	 * @type int                    $priority        The order priority in which the task is executed. Defaults to `10`
	 *                                               }
	 * @param callable|null         $callback        Optional. The function called when the task is run
	 */
	public function __construct ( $config = [], $callback = NULL )
	{
		if ( is_callable( $config ) ) {
			$config = [ 'callback' => $config ];
		}

		if ( is_callable( $callback ) ) {
			if ( is_string( $config ) ) {
				$config = [ 'title' => $config ];
			}
			$config['callback'] = $callback;
		}

		foreach ( $config as $key => $value ) {
			$this->{$key} = $value;
		}

		if ( $this->autoload || ( new \ReflectionClass( $this ) )->isAnonymous() ) {
			if ( did_action( 'wp_routines_autoload' ) ) {
				\WP_Routines\Routines::get_instance()->addTask( $this );
			}
			else {
				add_action( 'wp_routines_autoload', function ( \WP_Routines\Routines $routines ) {
					$routines->addTask( $this );
				} );
			}
		}
	}

	/**
	 * Get the task title
	 *
	 * @param string $default Optional. Fallback value if title does not exist
	 * @return string|null
	 */
	public function getTitle ( $default = NULL )
	{
		return $this->title ?: $default;
	}

	/**
	 * Get the group the task is in
	 *
	 * @param string $default Optional. Fallback value if group does not exist
	 * @return string|null
	 */
	public function getGroup ( $default = NULL )
	{
		return $this->group ?: $default;
	}

	/**
	 * Get the admin `menu_slug` for the page the task is on
	 *
	 * @param string $default Optional. Fallback value if page does not exist
	 * @return string|null
	 */
	public function getPage ( $default = NULL )
	{
		return $this->page ?: $default;
	}

	/**
	 * Get the function called when the task is run
	 *
	 * @param callable $default Optional. Fallback value if function does not exist
	 * @return callable|null
	 */
	public function getCallback ( $default = NULL )
	{
		return $this->callback ?: ( method_exists( $this, 'render' ) ? [ $this, 'render' ] : $default );
	}

	/**
	 * Get the order priority in which the task is executed
	 *
	 * @param int $default Fallback value if priority does not exist
	 * @return int
	 */
	public function getPriority ( $default = 10 )
	{
		return $this->priority ?: $default;
	}
}