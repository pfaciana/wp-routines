<?php

namespace WP_Routines;

/**
 * This is an abstract class.
 * When extended represents a group of `Task`s on a `Page`
 */
abstract class Tasks
{
	/**
	 * The page this group is on
	 *
	 * @see \WP_Routines\Page
	 *
	 * @var string|array|Page|null
	 */
	protected $page = NULL;
	/**
	 * The name of this group
	 *
	 * @var string
	 */
	protected $group;
	/**
	 * The priority of this group
	 *
	 * @var int
	 */
	protected $priority = 10;
	/**
	 * The prefix a method should have to be registered as a task
	 *
	 * @var string
	 */
	protected $taskPrefix = 'wp_ajax_';
	/**
	 * Optional task title override.
	 *
	 * By default, the task title is a just the sanitized human-readable version of the method name.
	 * If this is not descriptive enough, it can be overwritten here.
	 * Set the $key to the method name and the $value to the desired title.<br><br>
	 *
	 * Example: `$titles = [<br>
	 *  'wp_ajax_something_vague' => 'Some more descriptive title',<br>
	 *  'wp_ajax_fix_name'        => 'Replace "underscores" w/ "hyphens"',<br>
	 * ].
	 *
	 * @var array
	 */
	protected $titles = [];
	/**
	 * List of methods to be registered as cron events.
	 *
	 * The $key is a previously registered cron schedule $recurrence value.
	 * The $value is a nested array with the $key the name of the method, and
	 * the $value is an integer representing the priority of the scheduled action.
	 * Since an action may run multiple times, the priority can be an int[]<br><br>
	 *
	 * Example: `$crons = [<br>
	 *     'hourly' => [<br>
	 *         'wp_ajax_clean_up' => [1, 99],<br>
	 *         'wp_ajax_import_data' => 10,<br>
	 *     ],<br>
	 * ];
	 *
	 * @var array[]
	 */
	protected $crons = [];
	/**
	 * The prefix to the $cronAction hook to make it unique so there isn't an unintended collision
	 * with other hooks
	 *
	 * @var string
	 */
	protected $cronActionPrefix = 'routine_tasks_';
	/**
	 * This will autoload this Tasks instance into the $routines manager
	 *
	 * @var bool
	 */
	protected $autoload = FALSE;
	/**
	 * Disable the Stream::start()
	 *
	 * @see \WP_Routines\Stream::start()
	 *
	 * @var bool
	 */
	protected $disablePre = FALSE;
	/**
	 * Disable the Stream::stop()
	 *
	 * @see \WP_Routines\Stream::stop()
	 *
	 * @var bool
	 */
	protected $disablePost = FALSE;

	/**
	 * Constructor
	 *
	 * @see \WP_Routines\Tasks::getPage()
	 *
	 * @param array            $config            {
	 *                                            If $page is a string, it's the $menu_slug.
	 *                                            If $page is an array, it's the $config array.
	 *                                            If $page is an object, it's a \WP_Routines\Page
	 * @type string|array|Page $page              {
	 * @type string            $menu_slug         <b>Required.</b> The slug name to refer to this menu by. Defaults to `routines_console`
	 * @type string|false      $parent_slug       The slug name for the parent menu. Defaults to `FALSE`
	 * @type string            $menu_title        The text to be used for the men. Defaults to `Routines`
	 * @type string            $page_title        The text to be displayed in the title tags of the page. Defaults to `Routines Console`
	 * @type string            $capability        The capability required for this menu to be displayed to the user. Defaults to `manage_options`
	 * @type string            $icon_url          The URL to the icon or dashicon to be used for this menu. Defaults to `dashicons-embed-generic`
	 * @type int|float|false   $position          The position in the menu order this item should appear. Defaults to `FALSE`
	 * @type bool              $admin_menu_page   If this should create an admin menu page. Defaults to `TRUE`
	 * @type bool              $debug_bar_panel   If this should create a WP Debug Bar panel. Defaults to `TRUE`
	 *                                            }
	 * @type string            $group             The name of this group
	 * @type int               $priority          The priority of this group. Defaults to `10`
	 * @type string            $taskPrefix        The prefix a method should have to be registered as a task. Defaults to `wp_ajax_`
	 * @type array             $titles            Optional task title override. Defaults to `[]`
	 * @type array[]           $crons             List of methods to be registered as cron events. Defaults to `[]`
	 * @type string            $cronActionPrefix  The prefix to the $cronAction hook. Defaults to `routine_tasks_`
	 * @type bool              $disablePost       Disable the Stream::stop(). Defaults to `FALSE`
	 * @type bool              $disablePre        Disable the Stream::start(). Defaults to `FALSE`
	 *                                            }
	 */
	public function __construct ( $config = [] )
	{
		$this->preInit( $config );

		foreach ( $config as $key => $value ) {
			$this->{$key} = $value;
		}

		if ( empty( $this->group ) ) {
			$ref         = new \ReflectionClass( $this );
			$this->group = $ref->isAnonymous() ? 'Main' : ucwords( str_replace( '_', ' ', $ref->getShortName() ) );
		}

		if ( is_string( $this->page ) ) {
			$ref = $ref ?? new \ReflectionClass( $this );
			if ( !$ref->isAnonymous() ) {
				$short_name = ucwords( str_replace( '_', ' ', ( new \ReflectionClass( $this ) )->getShortName() ) );
				$this->page = [
					'menu_slug'   => $this->page,
					'_menu_title' => $short_name,
					'_page_title' => $short_name,
				];
			}
		}

		$this->setupFilters();

		$this->scheduleCronEvents();

		if ( $this->autoload || ( new \ReflectionClass( $this ) )->isAnonymous() ) {
			if ( did_action( 'wp_routines_autoload' ) ) {
				\WP_Routines\Routines::get_instance()->addTasks( $this );
			}
			else {
				add_action( 'wp_routines_autoload', function ( \WP_Routines\Routines $routines ) {
					$routines->addTasks( $this );
				} );
			}
		}

		$this->init( $config );
	}

	/**
	 * Optional abstract method that runs before this object is constructed
	 *
	 * @param array $config The $config array passed to the constructor
	 * @return void
	 */
	protected function preInit ( $config ) { }

	/**
	 * Optional abstract method that runs after this object is constructed
	 *
	 * @param array $config The $config array passed to the constructor
	 * @return void
	 */
	protected function init ( $config ) { }

	/**
	 * Sets up the $disablePre and $disablePost filters if either is set to TRUE
	 *
	 * @return void
	 */
	protected function setupFilters ()
	{
		foreach ( get_class_methods( $this ) as $method ) {
			$title  = array_key_exists( $method, $this->titles ) ? $this->titles[$method] : ucwords( str_replace( '_', ' ', $method ) );
			$action = str_replace( '-', '_', sanitize_title( $this->group ) ) . '_' . str_replace( '-', '_', sanitize_title( $title ) );
			$this->disablePre && add_filter( "wp_routines_pre_{$action}", '__return_false', 1 );
			$this->disablePost && add_filter( "wp_routines_post_{$action}", '__return_false', 1 );
		}
	}

	/**
	 * Schedule the Cron Events in WordPress
	 *
	 * On class __construct(), this looks for all methods that start with the $cronActionPrefix
	 * and schedules their event and creates their action hooks automatically
	 *
	 * @return void
	 */
	protected function scheduleCronEvents ()
	{
		if ( is_array( $this->crons ) && !empty( $this->crons ) ) {
			foreach ( $this->crons as $schedule => $actions ) {
				foreach ( $actions as $method => $priorities ) {
					$cronAction = $this->cronActionPrefix . $schedule;
					!wp_next_scheduled( $cronAction ) && wp_schedule_event( time(), $schedule, $cronAction );
					foreach ( (array) $priorities as $priority ) {
						add_action( $cronAction, [ $this, $method ], $priority );
					}
				}
			}
		}
	}

	/**
	 * Get the name of this group
	 *
	 * @param string $default Optional. Fallback value if the group name does not exist
	 * @return string|null
	 */
	public function getGroup ( $default = NULL )
	{
		return $this->group ?: $default;
	}

	/**
	 * Get the page this group is on
	 *
	 * @param string|array|Page|null $default         Optional. {
	 *                                                If this is a string, it's the $menu_slug.
	 *                                                If this is an array, it's the $config array.
	 *                                                If this is an object, it's a \WP_Routines\Page
	 * @type string                  $menu_slug       <b>Required.</b> The slug name to refer to this menu by. Defaults to `routines_console`
	 * @type string|false            $parent_slug     The slug name for the parent menu. Defaults to `FALSE`
	 * @type string                  $menu_title      The text to be used for the men. Defaults to `Routines`
	 * @type string                  $page_title      The text to be displayed in the title tags of the page. Defaults to `Routines Console`
	 * @type string                  $capability      The capability required for this menu to be displayed to the user. Defaults to `manage_options`
	 * @type string                  $icon_url        The URL to the icon or dashicon to be used for this menu. Defaults to `dashicons-embed-generic`
	 * @type int|float|false         $position        The position in the menu order this item should appear. Defaults to `FALSE`
	 * @type bool                    $admin_menu_page If this should create an admin menu page. Defaults to `TRUE`
	 * @type bool                    $debug_bar_panel If this should create a WP Debug Bar panel. Defaults to `TRUE`
	 *                                                }
	 * @return string|array|Page|null
	 */
	public function getPage ( $default = NULL )
	{
		return $this->page ?: $default;
	}

	/**
	 * Get all the functions/tasks that are included in this group
	 *
	 * @return callable[]
	 */
	public function getCallbacks ()
	{
		$callbacks = [];

		foreach ( get_class_methods( $this ) as $method ) {
			if ( str_starts_with( $method, $this->taskPrefix ) ) {
				$title = array_key_exists( $method, $this->titles ) ? $this->titles[$method] : ucwords( str_replace( '_', ' ', substr_replace( $method, '', 0, strlen( $this->taskPrefix ) ) ) );

				$callbacks[$title] = [ $this, $method ];
			}
		}

		return $callbacks;
	}

	/**
	 * Get the priority for this group on a `Page`
	 *
	 * @see \WP_Routines\Page
	 *
	 * @param int $default Fallback value if priority does not exist
	 * @return int
	 */
	public function getPriority ( $default = 10 )
	{
		return $this->priority ?: $default;
	}
}