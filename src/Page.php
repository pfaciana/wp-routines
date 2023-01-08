<?php

namespace WP_Routines;

/**
 * A collection of task groups or `Tasks`
 *
 * @example
 * # Available Page Hooks
 *
 * // Set the `max_execution_time` globally or for a specific task
 * apply_filters( "global_exec_time", 720, (int) ini_get( 'max_execution_time' ) );
 * apply_filters( "{$action}_exec_time", $global_exec_time, (int) ini_get( 'max_execution_time' ) );
 *
 * // Set the `memory_limit` globally or for a specific task
 * apply_filters( "global_memory_limit", '512M', ini_get( 'memory_limit' ) );
 * apply_filters( "{$action}_memory_limit", $global_memory_limit, ini_get( 'memory_limit' ) );
 *
 * // Disable the header message at the top of the console globally or for a specific task
 * apply_filters( "wp_routines_pre_task", TRUE, $stream )
 * apply_filters( "wp_routines_pre_{$action}", $wp_routines_pre_task, $stream ) );
 *
 * // Disable the footer message and close the stream globally or for a specific task
 * apply_filters( "wp_routines_post_task", TRUE, $stream )
 * apply_filters( "wp_routines_post_{$action}", $wp_routines_post_task, $stream ) );
 *
 * // NOTES:
 *
 * // $action is the sanitized concatenation of the $group_name and the $task_title.
 * // For example, if the task's group is `Main` and the title is `Task #1`, then the $action is `main_task_1`
 * // So to disable the console header message for only `Main > Task #1` you write...
 * // add_filter( 'wp_routines_pre_main_task_1' , function ( $enable ) { return FALSE; } );
 *
 * // Since the title is auto-generated from the method name, if the `Task` was created automatically from a `Tasks` class, and the title was not overwritten, then you might have
 * // add_filter( 'wp_routines_pre_main_wp_ajax_import_data' , '__return_false' );
 *
 */
class Page
{
	/**
	 * The default $menu_slug used when building an admin page
	 */
	const DEFAULT_SLUG = 'routines_console';
	/**
	 * Config for building this page
	 *
	 * @var array
	 */
	protected $config = [
		'menu_slug'       => 'routines_console',
		'parent_slug'     => FALSE,
		'menu_title'      => 'Routines',
		'page_title'      => 'Routines Console',
		'capability'      => 'manage_options',
		'icon_url'        => 'dashicons-embed-generic',
		'position'        => FALSE,
		'autoload'        => FALSE,
		'admin_menu_page' => TRUE,
		'debug_bar_panel' => TRUE,
	];
	/**
	 * Copy of the original config for building this page
	 *
	 * @var array
	 */
	private $defaultConfig = [
		'menu_slug'       => 'routines_console',
		'parent_slug'     => FALSE,
		'menu_title'      => 'Routines',
		'page_title'      => 'Routines Console',
		'capability'      => 'manage_options',
		'icon_url'        => 'dashicons-embed-generic',
		'position'        => FALSE,
		'autoload'        => FALSE,
		'admin_menu_page' => TRUE,
		'debug_bar_panel' => TRUE,
	];
	/**
	 * WP Debug Bar Panel
	 *
	 * @var Panel
	 */
	protected $panel;

	/**
	 * All the tasks grouped by the group they are in
	 *
	 * @var array[]
	 */
	protected $tasks = [ 'Main' => [] ];

	/**
	 * Constructor
	 *
	 * @param array|Page     $config                  {
	 * @type string          $menu_slug               <b>Required.</b> The slug name to refer to this menu by. Defaults to `routines_console`
	 * @type string|false    $parent_slug             The slug name for the parent menu. Defaults to `FALSE`
	 * @type string          $menu_title              The text to be used for the men. Defaults to `Routines`
	 * @type string          $page_title              The text to be displayed in the title tags of the page. Defaults to `Routines Console`
	 * @type string          $capability              The capability required for this menu to be displayed to the user. Defaults to `manage_options`
	 * @type string          $icon_url                The URL to the icon or dashicon to be used for this menu. Defaults to `dashicons-embed-generic`
	 * @type int|float|false $position                The position in the menu order this item should appear. Defaults to `FALSE`
	 * @type bool            $admin_menu_page         If this should create an admin menu page. Defaults to `FALSE`
	 * @type bool            $debug_bar_panel         If this should create a WP Debug Bar panel. Defaults to `FALSE`
	 *                                                }
	 */
	public function __construct ( $config = [] )
	{
		if ( empty( $config ) ) {
			$config = $this->config + $this->defaultConfig;
		}
		$this->config['callback'] = [ $this, 'render' ];
		$this->set( $config );

		if ( $this->config['autoload'] ) {
			if ( did_action( 'wp_routines_autoload' ) ) {
				\WP_Routines\Routines::get_instance()->addPage( $this );
			}
			else {
				add_action( 'wp_routines_autoload', function ( \WP_Routines\Routines $routines ) {
					$routines->addPage( $this );
				} );
			}
		}

		add_filter( 'debug_bar_panels', [ $this, 'debug_bar_panels' ], );
	}

	/**
	 * Add the WP Routines Panel to WP Debug Bar
	 *
	 * @param Panel[] $panels all the registered WP Debug Bar panels
	 * @return Panel[] the new set of WP Debug Bar panels
	 */
	public function debug_bar_panels ( $panels )
	{
		if ( $this->config['debug_bar_panel'] ) {
			$this->panel = new Panel( $this->config['menu_title'] );
			$this->panel->setRenderCallback( [ $this, 'render' ] );
			$this->panel->_icon = $this->config['icon_url'];

			$panels[] = $this->panel;
		}

		return $panels;
	}

	/**
	 * Setter
	 *
	 * @param array|Page     $config                  {
	 * @type string          $menu_slug               <b>Required.</b> The slug name to refer to this menu by. Defaults to `routines_console`
	 * @type string|false    $parent_slug             The slug name for the parent menu. Defaults to `FALSE`
	 * @type string          $menu_title              The text to be used for the men. Defaults to `Routines`
	 * @type string          $page_title              The text to be displayed in the title tags of the page. Defaults to `Routines Console`
	 * @type string          $capability              The capability required for this menu to be displayed to the user. Defaults to `manage_options`
	 * @type string          $icon_url                The URL to the icon or dashicon to be used for this menu. Defaults to `dashicons-embed-generic`
	 * @type int|float|false $position                The position in the menu order this item should appear. Defaults to `FALSE`
	 * @type bool            $admin_menu_page         If this should create an admin menu page. Defaults to `FALSE`
	 * @type bool            $debug_bar_panel         If this should create a WP Debug Bar panel. Defaults to `FALSE`
	 *                                                }
	 * @return array the config
	 */
	public function set ( $config = [] )
	{
		if ( gettype( $config ) === 'object' && method_exists( $config, 'get' ) ) {
			$config = $config->get();
		}

		if ( array_key_exists( 'menu_slug', $config ) && !empty( $config['menu_slug'] ) ) {
			$this->config = array_filter( $config, function ( $value, $key ) {
					return $value !== FALSE && $key !== 'callback';
				}, ARRAY_FILTER_USE_BOTH ) + $this->config;
		}

		return $this->get();
	}

	/**
	 * Getter
	 *
	 * Gets a config value by key. To get the entire config, leave $key empty
	 *
	 * @param string|null $key     Optional. The key to get
	 * @param mixed|null  $default Optional. The default value if the $key does not exist. Defaults to `NULL`
	 * @return mixed
	 */
	public function get ( $key = NULL, $default = NULL )
	{
		if ( !empty( $key ) ) {
			return array_key_exists( $key, $this->config ) ? $this->config[$key] : $default;
		}

		return $this->config;
	}

	/**
	 * Adds the admin pages
	 *
	 * This gets called when the admin bar inits.
	 *
	 * @return void|false Returns FALSE on missing `admin_menu_page`
	 */
	public function setUpAdminPage ()
	{
		if ( empty( $this->get( 'admin_menu_page' ) ) ) {
			return FALSE;
		}

		is_callable( $this->config['callback'] ) && add_action( 'admin_menu', function () {
			foreach ( $this->config as $key => $value ) {
				if ( $key[0] === '_' ) {
					$overrideKey = substr( $key, 1 );
					if ( $this->defaultConfig[$overrideKey] === $this->config[$overrideKey] ) {
						$this->config[$overrideKey] = $value;
					}
				}
			}
			if ( $this->defaultConfig['menu_slug'] !== $this->config['menu_slug'] ) {
				if ( $this->defaultConfig['menu_title'] === $this->config['menu_title'] ) {
					$this->config['menu_title'] = $this->defaultConfig['page_title'] !== $this->config['page_title'] ? $this->config['page_title'] : ucwords( str_replace( '_', ' ', $this->config['menu_slug'] ) );
				}
				if ( $this->defaultConfig['page_title'] === $this->config['page_title'] ) {
					$this->config['page_title'] = $this->defaultConfig['menu_title'] !== $this->config['menu_title'] ? $this->config['menu_title'] : ucwords( str_replace( '_', ' ', $this->config['menu_slug'] ) );
				}
			}
			$args = $this->config + $this->defaultConfig;
			if ( !empty( $args['parent_slug'] ) ) {
				add_submenu_page( $args['parent_slug'], $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['callback'], $args['position'] );
			}
			else {
				add_menu_page( $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['callback'], $args['icon_url'], $args['position'] );
			}
		} );
	}

	/**
	 * Add a task group to this page
	 *
	 * @see \WP_Routines\Tasks
	 *
	 * @param Tasks $tasks The Tasks inherited class that to add to WP Routines
	 * @return void
	 */
	public function addTasks ( $tasks )
	{
		if ( !array_key_exists( $group = $tasks->getGroup( 'Main' ), $this->tasks ) ) {
			$this->tasks[$group] = [];
		}

		if ( !array_key_exists( $priority = $tasks->getPriority(), $this->tasks[$group] ) ) {
			$this->tasks[$group][$priority] = [];
		}

		foreach ( $tasks->getCallbacks() as $title => $callback ) {
			if ( empty( $title ) ) {
				$title = 'Task #' . ( count( $this->tasks[$group][$priority] ) + 1 );
			}
			$this->tasks[$group][$priority][$title] = $callback;
		}
	}

	/**
	 * Add a Task to this Page
	 *
	 * @see \WP_Routines\Task
	 *
	 * @param string|array|callable|Task $task     {
	 *                                             If this is a string, it's the $title, and you must add the callable as the second argument.
	 *                                             If this is a callable, it's the $callback.
	 *                                             If this is an array, it's the $config array.
	 * @type string                      $title    The title. Defaults to `Task #...`
	 * @type string                      $group    The group the task is in. Defaults to `Main`
	 * @type callable                    $callback The function to be called to output the content for this task
	 * @type int                         $priority The order priority in which the task is executed. Defaults to `10`
	 *                                             }
	 * @return Task
	 */
	public function addTask ( $task )
	{
		if ( gettype( $task ) !== 'object' ) {
			$task = new Task( ...func_get_args() );
		}

		if ( !array_key_exists( $group = $task->getGroup( 'Main' ), $this->tasks ) ) {
			$this->tasks[$group] = [];
		}

		if ( !array_key_exists( $priority = $task->getPriority(), $this->tasks[$group] ) ) {
			$this->tasks[$group][$priority] = [];
		}

		if ( empty( $title = $task->getTitle() ) ) {
			$title = 'Task #' . ( count( $this->tasks[$group][$priority] ) + 1 );
		}

		$this->tasks[$group][$priority][$title] = $task->getCallback();

		return $task;
	}

	/**
	 * Runs a Task
	 *
	 * This gets called when the ajax request occurs and the action hook for a task is fired.
	 *
	 * @param $callback
	 * @param $action
	 * @return void
	 */
	protected function renderAction ( $callback, $action )
	{
		try {
			$request   = filter_input_array( INPUT_POST ) ?? filter_input_array( INPUT_GET ) ?: [];
			$func_args = $request['args'] ?? '[]';
			$func_args = json_decode( $func_args, FALSE, 512, JSON_THROW_ON_ERROR );
		}
		catch ( \Exception $e ) {
			if ( !isset( $_GET['args'] ) || empty( $_GET['args'] ) ) {
				$func_args = [];
			}
			else {
				$func_args = is_array( $_GET['args'] ) ? $_GET['args'] : [ $_GET['args'] ];
			}
		}

		$execTime    = apply_filters( "global_exec_time", 720, (int) ini_get( 'max_execution_time' ) );
		$memoryLimit = apply_filters( "global_memory_limit", '512M', ini_get( 'memory_limit' ) );

		$execTime    = apply_filters( "{$action}_exec_time", $execTime, (int) ini_get( 'max_execution_time' ) );
		$memoryLimit = apply_filters( "{$action}_memory_limit", $memoryLimit, ini_get( 'memory_limit' ) );

		$stream = new Stream( $execTime, $memoryLimit );
		$stream->start( apply_filters( "wp_routines_pre_{$action}", apply_filters( "wp_routines_pre_task", TRUE, $stream ), $stream ) );
		$callback( $stream, ...( (array) $func_args ) );
		$stream->stop( apply_filters( "wp_routines_post_{$action}", apply_filters( "wp_routines_post_task", TRUE, $stream ), $stream ) );
	}

	/**
	 * Registers all task methods for ajax requests coming from the browser console
	 *
	 * This gets called when the admin bar inits.
	 *
	 * @return void
	 */
	public function registerAjax ()
	{
		foreach ( $this->tasks as $group => $priorities ) {
			foreach ( $priorities as $priority => $tasks ) {
				foreach ( $tasks as $title => $callback ) {
					if ( $title !== FALSE ) {
						$action = str_replace( '-', '_', sanitize_title( $group ) ) . '_' . str_replace( '-', '_', sanitize_title( $title ) );
						add_action( "wp_ajax_{$action}", function () use ( $callback, $action ) {
							$this->renderAction( $callback, $action );
						}, $priority );
					}
				}
			}
		}
	}

	/**
	 * Get the html for the header group and tasks rows
	 *
	 * @return string
	 */
	protected function getActionLinks ()
	{
		$request = filter_input_array( INPUT_POST ) ?? filter_input_array( INPUT_GET ) ?: [];

		$groups = [];

		foreach ( $this->tasks as $group => $priorities ) {
			ksort( $priorities );
			$links = [];
			foreach ( $priorities as $priority => $tasks ) {
				foreach ( $tasks as $title => $callback ) {
					if ( $title !== FALSE ) {
						$action  = str_replace( '-', '_', sanitize_title( $group ) ) . '_' . str_replace( '-', '_', sanitize_title( $title ) );
						$args    = array_key_exists( $action, $request ) ? " <b> ( $request[$action] ) </b>" : '';
						$links[] = '<a data-action="' . $action . '">' . $title . '</a>' . $args;
					}
				}
			}
			if ( !empty( $links ) ) {
				$group    = $this->config['page_title'] === $group && ( !array_key_exists( 'Main', $this->tasks ) || empty( $this->tasks['Main'] ) ) ? 'Main' : $group;
				$groups[] = '<b>' . $group . '</b>: &nbsp; ' . implode( ' &nbsp; | &nbsp; ', $links );
			}
		}

		return '<hr>' . implode( '<hr>', $groups ) . '<hr>';
	}

	/**
	 * Display the page content
	 *
	 * @return void
	 */
	public function render ()
	{
		?>
		<div class="wrap" data-routine-id="<?= $this->config['menu_slug'] ?>" data-ajax-url="<?= admin_url( 'admin-ajax.php' ) ?>">
			<h1 style="font-size: 24px; font-weight: bold; margin: 1em 0"><?= $this->config['page_title'] ?></h1>
			<div><?= $this->getActionLinks() ?></div>
			<div class="routine-output-buffer"></div>
			<div><a class="routine-auto-scroll">Scroll To End</a> | <a class="routine-pause-scroll">Pause Scroll</a> | <a class="routine-abort-xhr">Stop Request</a></div>
		</div>
		<?php
	}
}