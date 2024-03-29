<?php

/*
 * LGPL
 * 
 */

/** 
 * 
 * http://www.if-not-true-then-false.com/2010/php-class-for-coloring-php-command-line-cli-scripts-output-php-output-colorizing-using-bash-shell-colors/
 */
class Colors {
	private $foreground_colors = array();
	private $background_colors = array();
	
	public function __construct() {
		// Set up shell colors
		$this->foreground_colors['black'] = '30';
		$this->foreground_colors['dark_gray'] = '30';
		$this->foreground_colors['red'] = '31';
		$this->foreground_colors['light_red'] = '31';
		$this->foreground_colors['green'] = '32';
		$this->foreground_colors['light_green'] = '32';
		$this->foreground_colors['brown'] = '33';
		$this->foreground_colors['yellow'] = '33';
		$this->foreground_colors['blue'] = '34';
		$this->foreground_colors['light_blue'] = '34';
		$this->foreground_colors['purple'] = '35';
		$this->foreground_colors['light_purple'] = '35';
		$this->foreground_colors['cyan'] = '36';
		$this->foreground_colors['light_cyan'] = '36';
		$this->foreground_colors['light_gray'] = '37';
		$this->foreground_colors['white'] = '37';
		
		$this->background_colors['black'] = '40';
		$this->background_colors['red'] = '41';
		$this->background_colors['green'] = '42';
		$this->background_colors['yellow'] = '43';
		$this->background_colors['blue'] = '44';
		$this->background_colors['magenta'] = '45';
		$this->background_colors['cyan'] = '46';
		$this->background_colors['light_gray'] = '47';
	}
	

    public function __call( $method, $args )
    {
        $method = strtolower(trim($method));
        if (array_key_exists($method, $this->foreground_colors))
        {
            // TODO: formate proper color name e.g. lightCyan => light_cyan
            $len = strcspn($method, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
            if ($len)
            {
                $firstSegment = substr($method, 0, $len);
                $secondSegment = substr($method, $len);
                $method = $firstSegment . '_' . $secondSegment;
            }
            $this->getColoredString($args[0], $method, isset($args[1]) ?: '');
        } 
        else {
            die('Color is not supported!');
        }
        
    }

	// Returns colored string
	public function getColoredString($string, $foreground_color = null, $background_color = null) {
		$colored_string = "";
		
		// Check if given foreground color found
		if (isset($this->foreground_colors[$foreground_color])) {
			$colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
		}
		// Check if given background color found
		if (isset($this->background_colors[$background_color])) {
			$colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
		}
		
		// Add string and end coloring
		$colored_string .=  $string . "\033[0m";
		
		return $colored_string;
	}
	
	// Returns all foreground color names
	public function getForegroundColors() {
		return array_keys($this->foreground_colors);
	}
	
	// Returns all background color names
	public function getBackgroundColors() {
		return array_keys($this->background_colors);
	}
}
?>
