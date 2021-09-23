<?php
/**
 * Header Template
 *
 * Uses HTML5, but all template HTML is (should be) xHTML 1.1 compatable
 */
class Header{
	private $title = "Template";
	private $scripts = array();
	private $stylesheets = array(
		"https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;600;800&display=swap"
	);

	/*
	 * The Attributes array. Holds data for the page.
	 */
	private $attributes = array(
		"title"    => "Template",
		"tagline"  => "A Tyzoid Production",
		"showbar"  => true,
		"loginbar" => ""
	);

	/**
	 * Constructor
	 *
	 * @param string $title - the title of the page
	 * @param mixed $scripts - the url (or array of urls) of the script(s) to include
	 * @param mixed $stylesheets - the url (or array of urls) of the stylesheet(s) to include
	 */
	public function __construct($title = null, $scripts = null, $stylesheets = null){
		// Page Title
		if (! empty($title)) $this->title = $title;

		if (! empty($scripts) && is_array($scripts)) $this->scripts = array_merge($scripts, $this->scripts);
		else if (! empty($scripts)) $this->addScript($scripts);

		if (! empty($stylesheets) && is_array($stylesheets)) $this->stylesheets = array_merge($stylesheets, $this->stylesheets);
		else if (! empty($stylesheets)) $this->addStyle($stylesheets);
	}

	public function setTitle($title){
		$this->title = $title;
	}

	public function addScript($script){
		if (empty($script) || strncmp($script, '/', 1) !== 0) return false;

		$this->scripts[] = $script;
		return true;
	}

	public function addStyle($stylesheet){
		if (empty($stylesheet) || strncmp($stylesheet, '/', 1) !== 0) return false;

		$this->stylesheets[] = $stylesheet;
		return true;
	}

	public function setAttribute($attribute, $value){
		$this->attributes[$attribute] = $value;
	}

	public function output($return = false){
		global $user;

		// Doctype
		$html  = "<!doctype html>\n";
		$html .= "<html>\n";
		$html .= "\t<head>\n";

		// Page Attributes/Head information
		$html .= "\t\t<title>" . $this->title . "</title>";

		// Set mobile-friendly
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />';

		// Stylesheet import
		foreach ($this->stylesheets as $style){
			$html .= "\t\t<link rel=\"stylesheet\" type=\"text/css\" href=\"$style\" />\n";
		}

		// Script import
		foreach ($this->scripts as $script){
			$html .= "\t\t<script type=\"text/javascript\" src=\"$script\"></script>\n";
		}

		// Start Body of the page
		$html .= "\t</head>\n";
		$html .= "\t<body>\n";

		// Page layout
		$html .= "\t\t<div id=\"container\">\n";

		// Login/Statusbar
		$html .= "\t\t\t<div class=\"loginbar\">\n";
		if ($user->loggedin()) {
			$html .= "\t\t\t\t" . $user->name() . "\n";
			$html .= "\t\t\t\t" . " <a href=\"/login.php?logout\">Log Out</a>\n";
			if ($user->getRole() === "admin")
				$html .= "\t\t\t\t" . " <a href=\"/admin/checkin.php\">Poll Worker</a>\n";
			$html .= "\t\t\t\t<a href=\"/index.php\">Home</a>\n";
		} else if (basename($_SERVER['PHP_SELF']) != 'login.php') {
			$html .= "\t\t\t\t<a href=\"/login.php\">Log In</a>\n";
		} else {
			$html .= "\t\t\t\t<a href=\"/index.php\">Home</a>\n";
		}
		$html .= "\t\t\t</div>\n";

		$html .= "\t\t\t<div class=\"header\">\n";
		$html .= "\t\t\t\t<h1>{$this->attributes['title']}</h1>\n";
		$html .= "\t\t\t\t<h2>{$this->attributes['tagline']}</h2>\n";
		$html .= "\t\t\t</div>\n";

		$html .= "\t\t\t<div class=\"content\">\n";
		$html .= "\t\t\t\t<div class=\"page\">\n";
		//End divs should be closed in the footer template

		if($return === true) return $html;
		echo $html;
	}
}
