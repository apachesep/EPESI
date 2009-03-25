<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC 
 * @version 1.0
 * @license MIT 
 * @package epesi-utils 
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TooltipCommon extends ModuleCommon {
	public static function user_settings(){
		return array('Misc'=>array(
			array('name'=>'help_tooltips','label'=>'Show help tooltips','type'=>'checkbox','default'=>1)
			));
	}

	private static $help_tooltips;
	private static function show_help() {
		if(!isset(self::$help_tooltips))
			self::$help_tooltips = Base_User_SettingsCommon::get('Utils/Tooltip','help_tooltips');
	}
	
	private static function init_tooltip_div(){
		if(!isset($_SESSION['client']['utils_tooltip']['div_exists'])) {
			$smarty = Base_ThemeCommon::init_smarty();
			$smarty->assign('tip','<span id="tooltip_text"></span>');
			ob_start();
			Base_ThemeCommon::display_smarty($smarty,'Utils_Tooltip');
			$tip_th = ob_get_clean();
			$js = 'div = document.createElement(\'div\');'.
				'div.id = \'tooltip_div\';'.
				'div.style.position = \'absolute\';'.
				'div.style.display = \'none\';'.
				'div.style.zIndex = 2000;'.
				'div.style.left = 0;'.
				'div.style.top = 0;'.
				'div.onmouseover = "Utils_Tooltip__hideTip()";'.
				'div.innerHTML = \''.Epesi::escapeJS($tip_th,false).'\';'.
				'body = document.getElementsByTagName(\'body\');'.
				'body = body[0];'.
				'document.body.appendChild(div);';
			eval_js($js,false);
			$_SESSION['client']['utils_tooltip']['div_exists'] = true;
		}
	}

	/**
	 * Returns string that when placed as tag attribute
	 * will enable tooltip when placing mouse over that element.
	 *
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 * @return string HTML tag attributes
	 */
	public static function open_tag_attrs( $tip, $help=true ) {
		if(MOBILE_DEVICE) return '';
		self::show_help();
		if($help && !self::$help_tooltips) return '';
		load_js('modules/Utils/Tooltip/js/Tooltip.js');
		self::init_tooltip_div();
		return ' onMouseMove="if(typeof(Utils_Toltip__showTip)!=\'undefined\')Utils_Toltip__showTip(this,event)" tip="'.htmlspecialchars($tip).'" onMouseOut="if(typeof(Utils_Toltip__hideTip)!=\'undefined\')Utils_Toltip__hideTip()" onMouseUp="if(typeof(Utils_Toltip__hideTip)!=\'undefined\')Utils_Toltip__hideTip()" ';
	}

	/**
	 * Returns string that when placed as tag attribute
	 * will enable ajax request to set a tooltip when placing mouse over that element.
	 *
	 * @param callback method that will be called to get tooltip content
	 * @param array parameters that will be passed to the callback
	 * @return string HTML tag attributes
	 */
	public static function ajax_open_tag_attrs( $callback ) {
		if(MOBILE_DEVICE) return '';
		static $tooltip_id = 0;
		load_js('modules/Utils/Tooltip/js/Tooltip.js');
		self::init_tooltip_div();
		$tooltip_id++;
		$args = func_get_args();
		array_shift($args);
		$_SESSION['client']['utils_tooltip']['callbacks'][$tooltip_id] = array('callback'=>$callback, 'args'=>$args);
		$loading_message = '<center><img src='.Base_ThemeCommon::get_template_file('Utils_Tooltip','loader.gif').' />&nbsp;'.Base_LangCommon::ts('Utils_Tooltip','Loading...').'</center>';
		return ' onMouseMove="if(typeof(Utils_Toltip__showTip)!=\'undefined\')Utils_Toltip__load_ajax_Tip(this,event)" tip="'.$loading_message.'" tooltip_id="'.$tooltip_id.'" onMouseOut="if(typeof(Utils_Toltip__hideTip)!=\'undefined\')Utils_Toltip__hideTip()" onMouseUp="if(typeof(Utils_Toltip__hideTip)!=\'undefined\')Utils_Toltip__hideTip()" ';
	}

	/**
	 * Returns string that if displayed will create text with tooltip.
	 *
	 * @param string text
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 * @return string text with tooltip
	 */
	public function create( $text, $tip, $help=true) {
		self::show_help();
		if((!$help || self::$help_tooltips) && is_string($tip) && $tip!=='')
			return '<span '.self::open_tag_attrs($tip,$help).'>'.$text.'</span>';
		else
			return $text;
	}

	/**
	* Returns a 2-column formatted table
	*
	* @param array keys are captions, values are values
	*/
	public static function format_info_tooltip( $arg,$group=null) {
		if($group===null)
			$group = self::get_type_with_bt(1);
		$table='<TABLE WIDTH="280" cellpadding="2">';
		foreach ($arg as $k=>$v){
			$table.='<TR><TD WIDTH="90"><STRONG>';
			$table.=Base_LangCommon::ts($group,$k).'</STRONG></TD><TD bgcolor="white">'; // Translated label
			$table.=$v; // Value
			$table.='</TD></TR>';
		}
		$table.='</TABLE>';
		return $table;
	}
}
?>
