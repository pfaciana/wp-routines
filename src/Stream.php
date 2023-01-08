<?php

namespace WP_Routines;

/**
 * The layer that communicates with the ajax call to stream data
 */
class Stream
{
	/**
	 * Maximum time in seconds a script is allowed to run before it is terminated by the parser
	 *
	 * @var int
	 */
	protected int $defaultExecTime;
	/**
	 * Maximum amount of memory in bytes that a script is allowed to allocate
	 *
	 * @var int|string
	 */
	protected $defaultMemoryLimit;
	/**
	 * Unix timestamp for the start of the script
	 *
	 * @var int|float
	 */
	protected int $startTime;

	/**
	 * Constructor
	 *
	 * @param int        $defaultExecTime    Maximum time in seconds a script is allowed to run before it is terminated by the parser
	 * @param int|string $defaultMemoryLimit Maximum amount of memory in bytes that a script is allowed to allocate
	 */
	public function __construct ( $defaultExecTime = 720, $defaultMemoryLimit = '512M' )
	{
		$this->defaultExecTime    = (int) $defaultExecTime;
		$this->defaultMemoryLimit = $defaultMemoryLimit;
		$this->startTime          = WP_START_TIMESTAMP ?: microtime( TRUE );
	}

	/**
	 * Start the Task Stream and display the script Memory Limit and Max Execution Time
	 *
	 * You may want to disable the header message because it flushes the buffer and prevent
	 * the sending of an additional headers. For example, the WP Debug Bar's `console::log`
	 * uses the headers to send data to Kint. If the headers are already sent, then
	 * `console::log` would not be able to send the debug data.
	 *
	 * @param bool $showMessage Display the header message at the top of the console
	 * @return void
	 */
	public function start ( $showMessage = TRUE )
	{
		if ( (int) ini_get( 'memory_limit' ) < (int) $this->defaultMemoryLimit ) {
			ini_set( 'memory_limit', $this->defaultMemoryLimit );
		}

		if ( (int) ini_get( 'max_execution_time' ) < $this->defaultExecTime ) {
			set_time_limit( $this->defaultExecTime );
		}

		$showMessage && $this->send( 'Memory limit set: ' . $this->getMemoryLimit() . '. Max execution time set: ' . $this->getMaxExecutionTime() . ' seconds.', 2 );
	}

	/**
	 * Close the Task Stream and display the Peak Memory Usage and the Execution Time
	 *
	 * @param bool $showMessage Display the footer message at the bottom of the console
	 * @return void
	 */
	public function stop ( $showMessage = TRUE )
	{
		$showMessage && $this->send( 'Peak memory usage: ' . $this->getPeakMemory() . ' MB. Execution time: ' . $this->getTimeElapsed() . ' seconds.', TRUE, 2 );
		wp_die();
	}

	/**
	 * Get the elapsed time in seconds since the script has started
	 *
	 * @param int   $precision         Optional. Decimal precision to round the seconds to
	 * @param float $fallbackStartTime Optional. Unix timestamp (in seconds) for the start of the script. Defaults to `WP_START_TIMESTAMP`
	 * @return int current execution time in seconds
	 */
	public function getTimeElapsed ( $precision = 3, $fallbackStartTime = NULL )
	{
		if ( empty( $this->startTime ) && empty( $fallbackStartTime ) ) {
			return FALSE;
		}

		$timeElapsed = microtime( TRUE ) - ( $this->startTime ?: $fallbackStartTime );

		return $precision === FALSE ? $timeElapsed : number_format( $timeElapsed, $precision ?? 3 );
	}

	/**
	 * Get the time remaining time in seconds for the current script.
	 *
	 * Takes the maximum execution time for a script and subtracts how long the script has been running.
	 * This can be used to see how much time is left and decide if there extra time for an additional process.
	 *
	 * @param int   $maxExecutionTime  Optional. How many seconds a script will run before it terminates. Defaults to PHP INI `max_execution_time`
	 * @param float $fallbackStartTime Optional. Unix timestamp (in seconds) for the start of the script. Defaults to `WP_START_TIMESTAMP`
	 * @return int Seconds left in the script before termination
	 */
	public function getTimeRemaining ( $maxExecutionTime = NULL, $fallbackStartTime = NULL )
	{
		if ( ( $timeElapsed = $this->getTimeElapsed( $fallbackStartTime ) ) === FALSE ) {
			return FALSE;
		}

		if ( empty( $maxExecutionTime ) || $maxExecutionTime < 0 ) {
			$maxExecutionTime = $this->getMaxExecutionTime();
		}

		return $maxExecutionTime - $timeElapsed;
	}

	/**
	 * Get the number seconds a script will run before it terminates.
	 *
	 * @return int ini_get( 'max_execution_time' )
	 */
	public function getMaxExecutionTime ()
	{
		return (int) ( ini_get( 'max_execution_time' ) ?? $this->defaultExecTime );
	}

	/**
	 * Returns the amount of memory allocated to PHP
	 *
	 * This can be used to determine if there is code that is leaking memory or that is inefficient.
	 *
	 * @param bool $real_usage Get the real size of memory allocated from system
	 * @param bool $inMB       Should the response be in Megabytes or bytes
	 * @return int the memory amount
	 */
	public function getMemory ( $real_usage = FALSE, $inMB = TRUE )
	{
		$memory = memory_get_usage( $real_usage );

		return $inMB ? round( $memory / 1024 / 1024 ) : $memory;
	}

	/**
	 * Returns the peak of memory allocated by PHP
	 *
	 * This can be used at the end of a script, or process, to determine if there was
	 * a memory leak or bad code.
	 *
	 * @param bool $real_usage Get the real size of memory allocated from system
	 * @param bool $inMB       Should the response be in Megabytes or bytes
	 * @return int the memory peak
	 */
	public function getPeakMemory ( $real_usage = TRUE, $inMB = TRUE )
	{
		$peakMemory = memory_get_peak_usage( $real_usage );

		return $inMB ? round( $peakMemory / 1024 / 1024 ) : $peakMemory;
	}

	/**
	 * Get the maximum amount of memory in bytes that a script is allowed to allocate
	 *
	 * @return int|string ini_get( 'memory_limit' )
	 */
	public function getMemoryLimit ()
	{
		return ini_get( 'memory_limit' ) ?? $this->defaultMemoryLimit;
	}

	/**
	 * Flush the current buffer and send it to the console
	 *
	 * This is very similar to writing $stream->send( '', FALSE );
	 * The difference being that `send` does not end the buffer, but this does.
	 *
	 * @param int|bool $newLineAfter  Number of new lines to add to the end of the buffer. Booleans get convertted to int. Defaults to 0
	 * @param int|bool $newLineBefore Number of new lines to add to the beginning of the buffer. Booleans get convertted to int. Defaults to 0
	 * @return void
	 */
	public function flush ( $newLineAfter = FALSE, $newLineBefore = FALSE )
	{
		$this->send( ob_get_clean(), $newLineAfter, $newLineBefore );
	}

	/**
	 * Send some text to the console
	 *
	 * @internal <b>Special Chars</b>
	 * There currently are two arbitrary characters that manipulate the buffer output in a particular way.
	 * Those chars are `\a` and `\b`. If you send those to the buffer, the browser console will interpret those as an instruction.
	 * `\a` tells the console position to go to the beginning of the previous line, deleting everything
	 * that was on current line and on the one before. `\b` tells the console position to go to the
	 * end of the previous line, deleting anything on the current line.
	 *
	 * @example
	 ** // A spinning asterisk
	 ** for ( $i = 1; $i <= 5; $i++ ) {
	 **     $stream->send( "\a-" );
	 **     usleep( 100000 );
	 **     $stream->send( "\a\\" );
	 **     usleep( 100000 );
	 **     $stream->send( "\a|" );
	 **     usleep( 100000 );
	 **     $stream->send( "\a/" );
	 **     usleep( 100000 );
	 ** }
	 **
	 ** // Outputs
	 ** - (pause) \ (pause) | (pause) / (pause) x5
	 *
	 * @example
	 ** // A text progress bar
	 ** $stream->flush( 3 );
	 ** for ( $i = 1; $i <= 100; $i++ ) {
	 **    $stream->send( "\a\a{$i}&percnt;" );
	 **    $stream->send( '[', 0 );
	 **    for ( $j = 1; $j <= 100; $j++ ) {
	 **        $stream->send( $j < $i || $i == 100 ? "=" : ( $j == $i ? '>' : "&nbsp;" ), 0 );
	 **    }
	 **    $stream->send( ']' );
	 ** }
	 **
	 ** // Outputs...
	 ** 90%
	 ** [=========================================================================================>          ]
	 *
	 * @param string   $content       The text (can be HTML) to send to client
	 * @param int|bool $newLineAfter  Number of new lines to add to the end of the buffer. Booleans get convertted to int. Defaults to 1
	 * @param int|bool $newLineBefore Number of new lines to add to the beginning of the buffer. Booleans get convertted to int. Defaults to 0
	 * @return bool TRUE on success
	 */
	public function send ( $content, $newLineAfter = TRUE, $newLineBefore = FALSE )
	{
		if ( !wp_doing_ajax() || wp_doing_cron() ) {
			return ob_clean() && FALSE;
		}

		if ( $newLineBefore ) {
			for ( $i = 0; $i < (int) $newLineBefore; $i++ ) {
				echo "\n";
			}
		}
		echo is_scalar( $content ) ? $content : json_encode( $content, JSON_PRETTY_PRINT );
		if ( $newLineAfter ) {
			for ( $i = 0; $i < (int) $newLineAfter; $i++ ) {
				echo "\n";
			}
		}
		ob_flush();
		flush();

		return TRUE;
	}
}