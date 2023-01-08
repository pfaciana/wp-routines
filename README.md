# WP Routines

WP Routines is an in-browser console for WordPress that displays a command line style output for executed code.

It brings a command line like interaction to the browser, but instead of the input being a text command, the input comes from the HTTP request. The purpose is to provide live real-time updates of code executing on the server. Often times this is used for admin tasks or cron jobs. Code that may take more than a few seconds to execute, and where real-time updates would be beneficial for progress and logging purposes. In additoin, it can also be interactive since it is simply a html output, that can have clickable events.

WP Routines can overlap similar use cases as the WP CLI, with two major differences. One, you don't need to learn or write CLI specific code. Second, more importantly, you may not always have access to the CLI on a particular server. However, it these two concerns are relevant to your use case, the WP CLI would typically be a better option.

## Getting Started

Install as a composer package

```shell
composer require pfaciana/wp-routines
```

> NOTE: This also comes bundled in [WP Debug Bar](https://github.com/pfaciana/wp-debug-bar), which is another composer package.
>
> ```composer require pfaciana/wp-debug-bar```
>
> If you are already using WP Debug Bar, or would like to, then you don't need to install WP Routines via composer.

## How to Use

### Concepts

There are three main concepts: a `Stream`, a `Task` and a `Page`, and the `Routines` singleton manages all of these.

Simply put, `Routines` is a collection of `Pages`. A `Page` is a collection of `Tasks`. A `Task` can be any registered function that accepts a `Stream` argument. A `Stream` instance is what allows the developer to send content to the browser.

#### Documentation Links

* [Routines](https://github.com/pfaciana/wp-routines/wiki/WP_Routines_Routines) - A collection of `Page`s
* [Page](https://github.com/pfaciana/wp-routines/wiki/WP_Routines_Page) - A collection of task groups or `Tasks`
* [Tasks](https://github.com/pfaciana/wp-routines/wiki/WP_Routines_Tasks) - This is an abstract class. When extended represents a group of `Task`s on a `Page`
* [Task](https://github.com/pfaciana/wp-routines/wiki/WP_Routines_Task) - A class that contains a callback method to be executed on a `Page`
* [Stream](https://github.com/pfaciana/wp-routines/wiki/WP_Routines_Stream) - The layer that communicates with the ajax call to stream data

See [the Wiki](https://github.com/pfaciana/wp-routines/wiki) for the full documentation.

In its simplest form...

```php
add_action( 'wp_routines', function ( \WP_Routines\Routines $routines ) {
    $routines->addTask( 'My Task Title', function ( \WP_Routines\Stream $stream ) {
        $stream->send( 'Hello World!' );
    } );
});
```

That's it! This will automatically create a WordPress admin page called `Routines Console` (the default name). On that page, the console html and a header of available task groups will display across the top. In this example, there will be just one task, called `My Task Title`. When the user clicks on `My Task Title`, an ajax call will be made to server and the response back will be a stream of data that will print out `Hello World!` to the console in real time. Now, WP Routines is much more powerful than that, but this is the most basic concept.

### Additional Examples

#### Creating a Task

```php
add_action( 'wp_routines', function ( \WP_Routines\Routines $routines ) {
    // This is the long style first, then we'll go over shorthand options.
    
    // $taskCallback can be any function that accepts a $stream as the first argument
    $taskCallback = function ( \WP_Routines\Stream $stream ) {
        $stream->send( 'Begin...' );
        $result = do_some_complicated_thing();
        echo $result;
        $stream->flush();
    };
    
    // $pageConfig can be a config array or a `Page` object, here we'll define the config
    // if the `Page` already exists, then this can be the string representing the $menu_slug
    $pageConfig = [
        'menu_slug'       => 'page_name_123',
        'menu_title'      => 'Page 123',
        'page_title'      => 'Page 123 Console',
        'capability'      => 'manage_options',
        'icon_url'        => 'dashicons-tickets',
        'groups'          => [ 'Group Name #2', 'default' => 'Group Name' ],
        'admin_menu_page' => TRUE,
        'debug_bar_panel' => TRUE,
    ];
    
    $taskConfig = [
        'title'    => 'Tab Name',
        'group'    => 'Group Name',
        'page'     => $pageConfig, 
        'callback' => $taskCallback, 
        'priority' => 10,
    ];
    
    // Register the Task to the Routines manager via the config array
    $task = $routines->addTask( $taskConfig );
    // Or as a `Task` object
    $task = $routines->addTask( new \WP_Routines\Task( $taskConfig ) );
    
    # Here are a couple quick shorthand options
    
    // 1) Send just a callable, the rest of the values will be auto generated 
    $routines->addTask( 'some_function' );
    // 2) Or send the title and callback, and the rest of the values will be auto generated 
    $routines->addTask( 'Tab Title', 'some_function' );
    
    // You can also add a group of tasks all at once by adding a `Tasks` class
    $routines->addTasks( new Custom_Tasks_Class() );
    // More on this later...
});
```

> A note about default values<br>
> * Default `title` is 'Task #1', where the number is incremented as additional tasks are added<br>
> * Default `group` is 'Main'<br>
> * Default `page` is an auto-generated 'Routines Console' admin page<br>
> * Default `priority` is 10

#### Creating a Page

By default, you don't need to create a page, an admin page called 'Routines Console' will be created for you.
However, you can override this, or create additional pages with the `Page` class.

```php
add_action( 'wp_routines', function ( \WP_Routines\Routines $routines ) {
    // Create the page config array
    // You should notice most of these keys match the arguments for the `add_menu_page` and `add_submenu_page` functions
    // That's no coincidence, depending on if you add a `parent_slug` key, it will call once of those function using these values
    $pageConfig = [
        'menu_slug'       => 'page_name_123',
        'menu_title'      => 'Page 123',
        'page_title'      => 'Page 123 Console',
        'capability'      => 'manage_options',
        'icon_url'        => 'dashicons-tickets',
        'groups'          => [ 'Group Name #2', 'default' => 'Group Name' ],
        'admin_menu_page' => TRUE,
        'debug_bar_panel' => TRUE,
    ];
    
    // Register the Page to the Routines manager via the config array
    $page = $routines->addPage( $pageConfig );
    // Or as a `Page` object
    $page = $routines->addPage( new \WP_Routines\Page( $pageConfig ) );
    
    // At a bare minimum you can just send the $pageConfig['menu_slug']
    $page = $routines->addPage( 'page_name_123' );
    // The rest of the config array will be built with the default values
});
```

#### Adding a Task or Tasks to a Page

Adding a Task or Tasks to a Page is identical to adding it to the $routines manager as shown above. This only difference is since you're adding it to an existing page, that will be used instead of the default or defined page from the config.

Defining a $page in the `Task` or `Tasks` object and registering them through the $routines manager is the same as not defining a $page in the `Task` or `Tasks`, but registering them through the $page itself. Both ways work and do the same thing.

```php
add_action( 'wp_routines', function ( \WP_Routines\Routines $routines ) {
    $page = $routines->addPage( $pageConfig );
    
    $page->addTask( 'Tab Title', 'some_function' );
    $page->addTasks( new Custom_Tasks_Class() );
});
```

#### Creating `Tasks`

See the [Tasks Documentation](https://github.com/pfaciana/wp-routines/wiki/WP_Routines_Tasks~__construct]) for full set of $config options, but here are a couple of things to be aware of.

`$this->taskPrefix` - is the prefix a method should have to be registered as a task. Default: 'wp_ajax_'. All methods that begin with this string (and are public methods) will automatically become a `Task`

`$this->crons` - is an array[] of methods to be registered as cron events. The first level keys are cron schedule ids registered to WordPress. If you want to use a custom cron schedule, you must create it first by hooking into the `cron_schedules` filter hook (See [cron_schedules hooks docs](https://developer.wordpress.org/reference/hooks/cron_schedules/)). You can do this in the optional `$this->preInit()` method (See below for example). At the secondary level, the crons array uses the key as the name of the method and the value is the priority to run the cron action for that method. If the priority values is an array, then it will schedule multiple cron actions to match those priorities. You may want that for a cleanup method that runs before AND after other code has run.

```php
class Custom_Tasks_Class extends \WP_Routines\Tasks
{
    // Set the page (Optional). If this is undefined, then it will go on the default page
    protected $page = 'page_name_123';
    
    // Optional Crons setup
    protected $crons = [
        'hourly' => [ // <- schedule name
            'wp_ajax_and_cron_task' => 10, // <- method name & priority
        ],
        'custom_schedules_name' => [  // <- schedule name
            'just_a_cron_task' => [-999, 999], // <- method name & priorities
        ],
    ];
    protected function preInit ( $config ) {
        add_filter('cron_schedules', [$this, 'add_custom_cron_intervals'], 10, 1);
    }
    public function add_custom_cron_intervals ( $schedules ) {
        $schedules['custom_schedules_name'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => 'Once Every 15 Minutes',
        ];
        
        return $schedules;
    }
    
    // Methods
    protected function neither_ajax_or_cron () {
        // This does not start with $taskPrefix, and is not in the $crons array
    }
    public function wp_ajax_import_data ( \WP_Routines\Stream $stream ) {
        // Starts with $taskPrefix, but not in the $crons array
        $stream->send('I only run as a task from the admin page.')
    }
    public function wp_ajax_and_cron_task ( \WP_Routines\Stream $stream ) {
        // Starts with $taskPrefix AND is in the $crons (hourly)
        $this->neither_ajax_or_cron();
        $stream->send('I run both as a task and as a cron job.')
    }
    public function just_a_cron_task ( \WP_Routines\Stream $stream ) {
        // Does not start with $taskPrefix, but is in the $crons (custom_schedules_name)
        $this->neither_ajax_or_cron();
        $stream->send('I only run as a cron job.')
    }
}

// If you're not autoloading or adding to a page, then you must add the new instance to the $routines manager manually
add_action( 'wp_routines', function ( \WP_Routines\Routines $routines ) {
    $routines->addTasks( new Custom_Tasks_Class() );
});

// Bare minimum setup
class Bare_Minimum_Tasks_Class extends \WP_Routines\Tasks {
    public function wp_ajax_ ( $stream ) {
        $stream->send( 'This is all you need to get this to work!' );
    }
};
```

### Anonymous Classes

You can also use anonymous classes to build a `Page`, `Tasks` or `Task`. They can be placed anywhere in your code as long as it's after the composer autoload file has loaded and before the admin_menu has been created. Here are examples very minimal setup to get started. Everything else from above and in the documentation still apply.

For a `Page` you need a `$this->config['menu_slug']` and `$this->config['autoload'] = TRUE`

```php
new class() extends \WP_Routines\Page {
    protected $config = [
        'menu_slug'  => 'some_page_123',
        'autoload'   => TRUE,
    ];
};
```

For a `Tasks` you need at least one public method that starts with `$this->taskPrefix`

```php
new class() extends \WP_Routines\Tasks {
    public function wp_ajax_some_callable ( \WP_Routines\Stream $stream ) {
        $stream->send( 'Hello World!' );
    }
};
```

For a `Task` you need a public method named `render`

```php
new class() extends \WP_Routines\Task {
    public function render ( \WP_Routines\Stream $stream ) {
        $stream->send( 'Hello World!' );
    }
};
```

### The `Stream` class

A new instance of the `Stream` class ([see Stream documentation](https://github.com/pfaciana/wp-routines/wiki/WP_Routines_Stream)) gets created and sent to the active task from the `Page` that it's on.

> NOTE: There are a set of handy filter hooks that allow you to customize the functionality of the `Stream` ([See hooks documentation](https://github.com/pfaciana/wp-routines/wiki/WP_Routines_Page#examples)).

There are a small handful of methods available, but we'll go over the most important ones here.

```php
function render ( \WP_Routines\Stream $stream ) {
    // Sending text (or html) to the client/browser console.
    $stream->send("<b>Header</b>\n---");
    ( function () {
        echo implode("\n", ['Row 1', 'Row 2', 'Row 3'];
    } )();
    $stream->flush();
    // `send` and `flush` are very similar. The difference being that `send` accepts text (or html) and `flush` does not.
    // `flush` calls `send`, with the text/html argument being the flushed buffer.
    // If nothing is in the buffer, then the output text/html is an empty string.
    // You typically would use `flush` when some code, which is out of scope of the `$stream` variable,
    //   sends something to the buffer, and you want that to be output in real time.
    
    // A couple helpful methods that allow you track the status of a script.
    // These values can be conditions that trigger an action or lack of an action.
    // For example, if you are running out of execution time, you may want to gracefully end a script early
    //   or if you are close to max memory, it could be a sign of a memory leak or bad code, etc.
    $currentAllocatedMemoryInMB = (int) $stream->getMemory();
    $secondsRemainingBeforeScriptTimesout = (int) $stream->getTimeRemaining();
}
```

#### `Stream` MAGIC chars

There currently are two arbitrary characters that manipulate the buffer output in a particular way.

Those chars are `\a` and `\b`. If you send those to the buffer, the browser console will interpret those as an instruction.
This is similar to how `\n` is a new line. `\a` tells the console position to go to the beginning of the previous line, deleting everything that was on current line and on the one before. `\b` tells the console position to go to the end of the previous line, deleting anything on the current line. Oftentimes the current line is empty, so `\b` usually doesn't delete any output, just moves the last position back one spot.

##### Example

```php
$stream->send( 'Line 1!' );
$stream->send( 'Line 2!' );
$stream->send( 'Line 3!' );
$stream->send( "\a\a\b" );
$stream->send( "New Line 2!" );
```

Outputs...

```text
# After First `send`
Line 1!
```

```shell
# After Second `send`
Line 1!
Line 2!
```

```shell
# After Third `send`
Line 1!
Line 2!
Line 3!
```

```shell
# After Fourth `send`
Line 1!

# Explanation
// the first `\a` deletes 'Line 3!' and puts the position at the beginning of that line
// the second `\a` deletes 'Line 2!' and puts the position at the beginning of that line
// the `\b` puts the position to the end of the 'Line 1!' line
// and the `send()` itself puts the position on the next line when complete
```

```shell
# After Last `send`
Line 1!
New Line 2!
```

> NOTE: Another way to do the same thing would be to combine the 4th and 5th send with `$stream->send( "\a\aNew Line 2!" );`
> However, I wanted to break out steps into their individual parts for the explanation.

##### Example 2 - A spinning asterisk

```php
for ( $i = 1; $i <= 5; $i++ ) {
    $stream->send( "\a-" );
    usleep( 100000 );
    $stream->send( "\a\\" );
    usleep( 100000 );
    $stream->send( "\a|" );
    usleep( 100000 );
    $stream->send( "\a/" );
    usleep( 100000 );
}
```

Outputs...

```shell
- (pause) \ (pause) | (pause) / (pause) x5
```

##### Example 3 - A text progress bar

```php
echo "\n\n\n";
for ( $i = 1; $i <= 100; $i++ ) {
    $stream->send( "\a\a{$i}&percnt;" );
    $stream->send( '[', 0 );
    for ( $j = 1; $j <= 100; $j++ ) {
        $stream->send( $j < $i || $i == 100 ? "=" : ( $j == $i ? '>' : "&nbsp;" ), 0 );
    }
    $stream->send( ']' );
}
```

Outputs...

```shell
90%
[=========================================================================================>          ]
```

##### Example 4 - A HTML progress bar

```php
echo "\n\n\n";
for ( $i = 1; $i <= 100; $i++ ) {
    $stream->send( "\a\a{$i}&percnt;" );
    $stream->send( ( "<div style='height: 20px; width: 100%; background-color: white;'><div style='height: 20px; width: {$i}%; background-color: green;'></div></div>" ) );
}
```

Outputs...

```html
90%
<div style="height: 20px; width: 100%; background-color: white;">
	<div style="height: 20px; width: 90%; background-color: green;"></div>
</div>
```

### Flexibility

I put a lot of effort to provide many different ways to do the same thing, depending on your coding preference and specific situation to make development as fast as possible. You can use the `$routines` singleton manager and build everything off that, you can create individual instances of classes and autoload them into the `$routines` manager, or create anonymous classes.

I also allow for shorthand version of most things. You can reference a `Page`, `Tasks` or `Task` by a string, config array or by a specific instance. The code will determine how to get what it needs. For a `Page` a string representation is the `$this->config['menu_slug']`, for a `Tasks` its the `$this->group` and for a `Task` its `$this->title`.