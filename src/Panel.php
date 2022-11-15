<?php

namespace WP_Routines;

if ( class_exists( 'Debug_Bar_Panel' ) ) {

	class Panel extends \Debug_Bar_Panel
	{
		protected $output;

		public function setRenderCallback ( $callback )
		{
			$this->output = $callback;
		}

		public function render ()
		{
			is_callable( $this->output ) && ( $this->output )();
		}
	}

}