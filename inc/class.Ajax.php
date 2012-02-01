<?php
/**
 * This file contains the class to handle all AJAX-Links
 */

Config::register('Design', 'JS_USE_TOOLTIP', 'bool', true, 'Tooltip f&uuml;r Icons');

/**
 * Class: Ajax
 * 
 * @author Hannes Christiansen <mail@laufhannes.de>
 * @version 1.0
 * @uses class::Error
 */
class Ajax {
	/**
	 * CSS-class for waiter-image
	 * @var string
	 */
	public static $IMG_WAIT = 'waitImg';

	/**
	 * Init own JS-library on frontend (direct output)
	 */
	public static function initJSlibrary() {
		$Options = array();
		$Options['useTooltip'] = CONF_JS_USE_TOOLTIP;

		echo self::wrapJS('Runalyze.init('.json_encode($Options).');');
	}

	/**
	 * Get all js-files needed for included in header
	 * @return array
	 */
	static public function getNeededJSFilesAsArray() {
		$Files = array();

		$Files[] = 'lib/jQuery.1.4.2.js';
		$Files[] = 'lib/jQuery.scrollTo.js';
		$Files[] = 'lib/jQuery.form.js';
		$Files[] = 'lib/jQuery.metadata.js';
		$Files[] = 'lib/jQuery.tablesorter.js';
		$Files[] = 'lib/jQuery.tablesorter.pager.js';
		$Files[] = 'lib/jquery.tooltip.js';

		$Files[] = 'lib/datepicker.js';
		$Files[] = 'lib/fileuploader.js';

		$Files[] = 'lib/runalyze.lib.js';
		$Files[] = 'lib/runalyze.lib.tablesorter.js';

		$Files[] = 'lib/form_scripts.js';

		if (CONF_DESIGN_BG_FIX_AND_STRETCH)
			$Files[] = 'lib/jQuery.backgroundStretch.js';

		return $Files;
	}

	/**
	 * Gives a HTML-link for using jTraining
	 * @param int $training_id ID of the training
	 * @param string $name Name of the link to be displayed
	 * @param bool $closeOverlay [optional] Boolean flag: Should the overlay be closed after clicking? (default: false)
	 * @return string
	 */
	static function trainingLink($id, $name, $closeOverlay = false) {
		return '<a class="training" href="call/call.Training.display.php?id='.$id.'" rel="'.$id.'"'.($closeOverlay ? ' onclick="Runalyze.closeOverlay()"' : '').'>'.$name.'</a>';
	}

	/**
	 * Get onclick-string for loading training
	 * @param int $training_id ID of the training
	 * @return string
	 */
	static function trainingLinkAsOnclick($id) {
		return 'onclick="Runalyze.loadTraining('.$id.')"';
	}

	/**
	 * Gives a HTML-link for using jToggle()
	 * @param string $link        The normal HTML-link
	 * @param string $toggle_id   The ID of the css-container to toggle
	 * @return string
	 */
	static function toggle($link, $toggle_id) {
		$link = self::insertClass($link, 'toggle');
		$link = self::insertRel($link, $toggle_id);

		return $link;
	}

	/**
	 * Gives a HTML-link for using jWindow()
	 * @param string $link     The normal HTML-link
	 * @param string $size     Enum: big|normal|small
	 * @return string
	 */
	static function window($link, $size = 'normal') {
		$link = self::insertClass($link, 'window');
		if ($size == 'big' || $size == 'small')
			$link = self::insertRel($link, $size);

		return $link;
	}

	/**
	 * Gives a HTML-link for using jChange()
	 * @param string $name   Displayed name for this link
	 * @param string $target ID of surrounding div-container
	 * @param string $href   ID of div-container to be displayed
	 * @return string
	 */
	static function change($name, $target, $href, $additional_class = '') {
		if (substr($href, 0, 1) != '#')
			$href = '#'.$href;

		if ($additional_class != '')
			$additional_class .= ' ';

		return '<a class="'.$additional_class.'change" target="'.$target.'" href="'.$href.'">'.$name.'</a>';
	}

	/**
	 * Gives a HTML-link for using jLinks()
	 * @param string $name    Displayed name for this link
	 * @param string $target  ID of target div-container
	 * @param string $href    URL to be loaded
	 * @param string $data    data to be passed
	 * @return string
	 */
	static function link($name, $target, $href, $data = '') {
		return '<a class="ajax" href="'.$href.'" target="'.$target.'" rel="'.$data.'">'.$name.'</a>';
	}

	/**
	 * Transform text to link for changing flot
	 * @param string $text
	 * @param string $divID
	 * @param string $flotID
	 */
	public static function flotChange($text, $divID, $flotID) {
		return '<span class="link" onclick="Runalyze.flotChange(\''.$divID.'\',\''.$flotID.'\')">'.$text.'</span>';
	}

	/**
	 * Adds a new class-value or creates a class-attribute
	 * @param string $link    The full HTML-link
	 * @param string $class   The new css-class
	 * @return string
	 */
	private static function insertClass($link, $class) {
		$text = preg_replace('#class="(.+?)"#i', 'class="'.$class.' \\1"', $link);
		if ($text == $link)
			$text = str_replace('<a', '<a class="'.$class.'"', $text);
		if ($text == $link)
			Error::getInstance()->addWarning('Unexpected error in using Ajax::insertClass(\''.$link.'\',\''.$class.'\')');

		return $text;
	}

	/**
	 * Overwrites an existing rel-attribute or creates a new one
	 * @param string $link   The full HTML-link
	 * @param string $rel    The new rel-value
	 * @return string
	 */
	private static function insertRel($link, $rel) {
		$text = preg_replace('#rel="(.+?)"#i', 'rel="'.$rel.'"', $link);
		if ($text == $link)
			$text = str_replace('<a', '<a rel="'.$rel.'"', $text);
		if ($text == $link)
			Error::getInstance()->addWarning('Unexpected error in using Ajax::insertRel(\''.$link.'\',\''.$rel.'\')');

		return $text;
	}

	/**
	 * Reload complete page
	 */
	public static function reloadPage() {
		self::wrapJS('location.reload();');
	}

	/**
	 * Wrap JavaScript into code block
	 * @param string $code
	 * @return string
	 */
	public static function wrapJS($code) {
		return '<script type="text/javascript">'.$code.'</script>';
	}

	/**
	 * Wrap JavaScript into code block for beeing executed on document ready
	 * @param string $code
	 * @return string
	 */
	public static function wrapJSforDocumentReady($code) {
		return self::wrapJS('$(document).ready(function(){ '.$code.' });');
	}

	/**
	 * Wrap JavaScript into code block for beeing an unnamed function
	 * @param string $code
	 * @return string
	 */
	public static function wrapJSasFunction($code) {
		return self::wrapJS('$(function(){ '.$code.' });');
	}

	/**
	 * JSON encode with function
	 * @param array $input
	 * @param array $funcs
	 * @param int $level
	 */
	public static function json_encode_jsfunc($input, $funcs = array(), $level = 0) {
		foreach($input as $key => $value) {
			if (is_array($value)) {
				$ret = self::json_encode_jsfunc($value, $funcs, 1);
				$input[$key] = $ret[0];
				$funcs = $ret[1];
			} elseif (substr($value,0,8) == 'function') {
                  $func_key = "#".uniqid()."#";
                  $funcs[$func_key] = $value;
                  $input[$key] = $func_key;
			}
		}
		if ($level == 1)
			return array($input, $funcs);
		else {
			$input_json = json_encode($input);
			foreach($funcs as $key => $value)
				$input_json = str_replace('"'.$key.'"', $value, $input_json);
			return $input_json;
		}
	}

	/**
	 * Get code for closing overlay
	 * @return string
	 */
	public static function closeOverlay() {
		return self::wrapJS('Runalyze.closeOverlay();');
	}

	/**
	 * Create code for binding tablesorter
	 * @param string $selector
	 */
	public static function createTablesorterFor($selector) {
		echo self::wrapJSforDocumentReady('$("'.$selector.'").tablesorterAutosort();');
	}

	/**
	 * Create code for binding tablesorter with pager
	 * @param string $selector
	 */
	public static function createTablesorterWithPagerFor($selector) {
		self::printPagerDiv();

		echo self::wrapJSforDocumentReady('$("'.$selector.'").tablesorterWithPager();');
	}

	/**
	 * Print div for pager for tables
	 */
	private static function printPagerDiv() {
		echo '
<div id="pager" class="pager c">
	<form>
		<a href="#main" class="first">|&laquo; Start</a>
		<a href="#main" class="prev">&laquo; zur&uuml;ck</a>
		<input type="text" class="pagedisplay" />
		<a href="#main" class="next">weiter &raquo;</a>
		<a href="#main" class="last">Ende &raquo;|</a>

		<select class="pagesize">
			<option value="10">10 pro Seite&nbsp;</option>
			<option selected="selected" value="20">20 pro Seite&nbsp;</option>
			<option value="30">30 pro Seite&nbsp;</option>
			<option value="40">40 pro Seite&nbsp;</option>
			<option value="50">50 pro Seite&nbsp;</option>
		</select>
	</form>
</div>';
	}
}
?>