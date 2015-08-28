<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>, Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage generic-browser
 */
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Underscore\Types\Arrays;

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowser extends Module {
	private $columns = array();
	private $rows = array();
	private $rows_jses = array();
	private $rows_qty;
	private $actions = array();
	private $row_attrs = array();
	private $en_actions = false;
	private $per_page;
	private $forced_per_page = false;
	private $offset;
	private $custom_label = '';
	private $custom_label_args = '';
	private $table_prefix = '';
	private $table_postfix = '';
	private $no_actions = array();
    private $expandable = false;
	public $form_s = null;
	private $fixed_columns_selector = '.Utils_GenericBrowser__actions';

	public function construct() {
		$this->form_s = $this->init_module(Libs_QuickForm::module_name());
		if (is_numeric($this->get_instance_id()))
			trigger_error('GenericBrowser did not receive string name for instance in module '.$this->get_parent_type().'.<br>Use $this->init_module(\'Utils/GenericBrowser\',<construct args>, \'instance name here\');',E_USER_ERROR);
	}

	//region Settings
	public function no_action($num) {
		$this->no_actions[$num] = true;
	}

	public function set_custom_label($arg, $args=''){
		$this->custom_label = $arg;
		$this->custom_label_args = $args;
	}
	
	public function set_fixed_columns_class($classes = array()){
		if (!is_array($classes)) {
			$classes = array($classes);
		}
		
		$classes[] = 'Utils_GenericBrowser__actions';
	
		$classes = array_map(function($c){return (substr($c,0,1)=='.')? $c: '.'.$c;}, $classes);	
		$this->fixed_columns_selector = implode(',', $classes);
	}

	/**
	 * Sets table columns according to given definition.
	 *
	 * Argument should be an array, each array field represents one column.
	 * A column is defined using an array. The following fields may be used:
	 * name - column label
	 * width - width of the column (percentage of the whole table)
	 * search - sql column by which search should be performed
	 * order - sql column by which order should be deterined
	 * quickjump - sql column by which quickjump should be navigated
	 * wrapmode - what wrap method should be used (nowrap, wrap, cut)
	 *
	 * @param array $arg columns definiton
	 */
	public function set_table_columns(array $arg){
		foreach($arg as $v) {
			if (!is_array($v))
				$this->columns[] = array('name' => $v);
			else
				$this->columns[] = $v;
		}
	}

	/**
	 * Sets default order for the table.
	 * This function can be called multiple times
	 * and only at the first call or if reset argument if set
	 * it will manipulate current order.
	 *
	 * The default order should be provided as an array
	 * containing column names (names given with set_table_columns, not SQL column names).
	 *
	 * @param array array with column names
	 * @param bool true to force order reset
	 */
	public function set_default_order(array $arg,$reset=false){
		if (($this->isset_module_variable('first_display') && !$reset) || empty($arg)) return;
		$order=array();

		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		foreach($arg as $k=>$v){
            if ($k[0] == ':') {
                $order[] = array('column' => $k, 'direction' => $v, 'order' => $k);
                continue;
            }
			$ord = false;
			foreach($this->columns as $val)
				if ($val['name'] == $k && isset($val['order'])) {
					$ord = $val['order'];
					break;
				}
			if ($ord===false) {
				trigger_error('Invalid column name for default order: '.$k,E_USER_ERROR);
			}
			$order[] = array('column'=>$k,'direction'=>$v,'order'=>$ord);
		}
		$this->set_module_variable('order',$order);
		$this->set_module_variable('default_order',$order);
	}

	public function set_expandable($b) {
		if (Base_User_SettingsCommon::get($this->get_type(), 'disable_expandable'))
			return;
		$this->set_module_variable('expandable',$this->expandable = ($b ? true : false));
	}

	public function set_per_page($pp) {
		if (!isset(Utils_GenericBrowserCommon::$possible_vals_for_per_page[$pp])) $pp = 5;
		$this->set_module_variable('per_page',$this->per_page = $pp);
	}
	//endregion

	//region Add data
	/**
	 * Creates new row object.
	 * You can then use methods add_data, add_data_array or add_action
	 * to manipulate and extend the row.
	 *
	 * @return object Generic Browser row object
	 */
	public function get_new_row() {
		return new Utils_GenericBrowser_RowObject($this,count($this->rows));
	}

	//region Internal

	/**
	 * For internal use only.
	 */
	public function __add_row_data($num,array $arg) {
		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		if (count($arg) != count($this->columns))
			trigger_error('Invalid size of array for argument 2 while adding data, was '.count($arg).', should be '.count($this->columns).'. Aborting.<br>Given '.print_r($arg, true).' to table '.print_r($this->columns, true),E_USER_ERROR);

		$this->rows[$num] = $arg;
	}

	/**
	 * For internal use only.
	 */
	public function __add_row_action($num,$tag_attrs,$label,$tooltip,$icon,$order=0,$off=false,$size=1) {
		if (!isset($icon)) $icon = strtolower(trim($label));
		switch ($icon) {
			case 'view': $order = -3; break;
			case 'edit': $order = -2; break;
			case 'delete': $order = -1; break;
			case 'info': $order = 1000; break;
		}
		$this->actions[$num][$icon] = array('tag_attrs'=>$tag_attrs,'label'=>$label,'tooltip'=>$tooltip, 'off'=>$off, 'size'=>$size, 'order'=>$order);
		$this->en_actions = true;
	}

	/**
	 * For internal use only.
	 */
	public function __set_row_attrs($num,$tag_attrs) {
		$this->row_attrs[$num] = $tag_attrs;
	}

	/**
	 * For internal use only.
	 */
	public function __add_row_js($num,$js) {
		if(!isset($this->rows_jses[$num])) $this->rows_jses[$num]='';
		$this->rows_jses[$num] .= rtrim($js,';').';';
	}

	//endregion

	/**
	 * Adds new row with data to Generic Browser.
	 *
	 * Each argument fills one field,
	 * it can be either a string or an array.
	 *
	 * If an array is passed it may consists following fields:
	 * value - text that will be displayed in the field
	 * style - additional css style definition
	 * hint - tooltip for the field
	 * wrapmode - what wrap method should be used (nowrap, wrap, cut)
	 *
	 * If a string is passed it will be displayed in the field.
	 *
	 * It's not recommended to use this function in conjunction with add_new_row().
	 *
	 * @param mixed $args list of arguments
	 */
	public function add_row($args) {
		$args = func_get_args();
		$this->add_row_array($args);
	}

	/**
	 * Adds new row with data to Generic Browser.
	 *
	 * The argument should be an array,
	 * each array entry fills one field,
	 * it can be either a string or an array.
	 *
	 * If an array is passed it may consists following fields:
	 * value - text that will be displayed in the field
	 * style - additional css style definition
	 * hint - tooltip for the field
	 *
	 * If a string is passed it will be displayed in the field.
	 *
	 * It's not recommended to use this function in conjunction with add_new_row().
	 *
	 * @param $arg array array with row data
	 */
	public function add_row_array(array $arg) {
		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		if (count($arg) != count($this->columns))
			trigger_error('Invalid size of array for argument 2 while adding data, was '.count($arg).', should be '.count($this->columns).'. Aborting.<br>',E_USER_ERROR);

		$this->rows[] = $arg;

		if ($this->per_page && count($this->rows) > $this->per_page)
			trigger_error('Added more rows than expected, aborting.',E_USER_ERROR);

	}

	//endregion

	/**
	 * Returns values needed for proper selection of elements.
	 * This is only neccessary if you are using 'paged' version of Genric Browser.
	 * Returned values should be used together with DB::SelectLimit();
	 *
	 * @return array array containing two fields: 'numrows' and 'offset'
	 */
	public function get_limit($max) {
		$offset = $this->get_module_variable('offset',0);
		$per_page = $this->get_module_variable('per_page',Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(),'per_page'));
		if (!isset(Utils_GenericBrowserCommon::$possible_vals_for_per_page[$per_page])) {
			$per_page = 5;
			$this->get_module_variable('per_page',Base_User_SettingsCommon::save(Utils_GenericBrowser::module_name(),'per_page', 5));
		}
		$this->rows_qty = $max;
		if ($offset>=$max) $offset = 0;
        if($offset % $per_page != 0) $offset = floor($offset/$per_page)*$per_page;

		if($this->get_unique_href_variable('next')=='1')
			$offset += $per_page;
		elseif($this->get_unique_href_variable('prev')=='1') {
			$offset -= $per_page;
			if ($offset<0) $offset=0;
		}
		elseif($this->get_unique_href_variable('first')=='1')
			$offset = 0;
		elseif($this->get_unique_href_variable('last')=='1')
			$offset = floor(($this->rows_qty-1)/$per_page)*$per_page;

		$this->unset_unique_href_variable('next');
		$this->unset_unique_href_variable('prev');
		$this->unset_unique_href_variable('first');
		$this->unset_unique_href_variable('last');
		$this->set_module_variable('offset', $offset);
		$this->set_module_variable('per_page', $per_page);
		$this->per_page = $per_page;
		$this->offset = $offset;
		return array(	'numrows'=>$per_page,
						'offset'=>$offset);
	}

	/**
	 * Returns 'ORDER BY' part of an SQL query
	 * which will sort rows in order chosen by end-user.
	 * Default value returned is determined by arguments passed to set_default_order().
	 * Returned string contains space at the beginning.
	 *
	 * Do not use this method in conjuntion with get_order()
	 *
	 * @param string columns to force order
	 * @return string 'ORDER BY' part of the query
	 */
	public function get_query_order($force_order=null) {
		$ch_order = $this->get_unique_href_variable('change_order');
		if ($ch_order)
			$this->change_order($ch_order);
		$order = $this->get_module_variable('order');
		if(!is_array($order)) return '';
		ksort($order);
		$sql = '';
		$ohd = '';
		$first = true;
		foreach($order as & $v){
			$ohd .= ($first?'':',').' '.$v['column'].' '.$v['direction'];
			$sql .= ($first?'':',').' '.$v['order'].' '.$v['direction'];
			$first = false;
		}
		if ($sql) $sql = ' ORDER BY'.($force_order?' '.trim($force_order,',').',':'').$sql;
		$this->set_module_variable('order_history_display',$ohd);
		$this->set_module_variable('order',$order);
		return $sql;
	}

	/**
	 * Returns an array containing information about row order.
	 * Each field represents a column by which the order is determined.
	 * First field is used as the final order criteria,
	 * while the last field is used for the initial sort.
	 *
	 * Each field contains:
	 * column - Generic Browser column name
	 * order - SQL column name
	 * direction - ASC or DESC
	 *
	 * Default value returned is determined by arguments passed to set_default_order().
	 *
	 * Do not use this method in conjuntion with get_query_order()
	 *
	 * @return array array containing information about row order
	 */
	public function get_order(){
		$this->get_query_order();
		$order = $this->get_module_variable('order');
		return $order;
	}

	/**
	 * For internal use only.
	 */
	public function change_order($ch_order){
		$order = $this->get_module_variable('order', array());

		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		$ord = null;
		foreach($this->columns as $val)
			if ($val['name'] == $ch_order) {
				$ord = $val['order'];
				break;
			}
		$pos = -1;
		foreach($order as $k=>$v) {
			if ($v['order']==$ord) {
				$pos = $k;
				break;
			}
		}
		if ($pos == 0) {
			if ($order[$pos]['column']==$ch_order && $order[$pos]['direction']=='ASC') $order[$pos]['direction']='DESC';
			else $order[$pos]['direction']='ASC';
			$order[$pos]['column']=$ch_order;
			$this->set_module_variable('order',$order);
			return;
		}
		if ($pos == -1){
			$new_order = array(array('column'=>$ch_order,'direction'=>'ASC','order'=>$ord));
			foreach($order as $k=>$v)
				$new_order[] = $v;
			$this->set_module_variable('order',$new_order);
			return;
		}
		$new_order = array();
		unset($order[$pos]);
		foreach($order as $k=>$v){
			$new_order[$k+($k<$pos?1:0)] = $v;
		}
		$new_order[0]=array('column'=>$ch_order,'direction'=>'ASC','order'=>$ord);
		$this->set_module_variable('order',$new_order);
	}

	/**
	 * Returns statement that should be used in 'WHERE' caluse
	 * to select elements that were searched for.
	 *
	 * The statement generated using search criteria is enclosed with parenthesis
	 * and does not include keyword 'WHERE'.
	 *
	 * If no conditions where spcified returns empty string.
	 *
	 * @return string part of sql statement
	 */
	public function get_search_query( $array = false, $separate=false){
		$search = $this->get_module_variable('search');

		$this->get_module_variable_or_unique_href_variable('quickjump_to');
		$quickjump = $this->get_module_variable('quickjump');
		$quickjump_to = $this->get_module_variable('quickjump_to');
		$this->set_module_variable('quickjump_to',$quickjump_to);

		if (!$array) {
			$where = '';
		} else {
			$where = array();
		}
		
		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		if(isset($search['__keyword__'])) {
			if(!$array) {
				if($separate)
					$search = explode(' ',$search['__keyword__']);
				else
					$search = array($search['__keyword__']);
			}
			foreach($this->columns as $k=>$v){
				if (isset($v['search']))
					if (!$array) {
						$t_where = '';
						foreach($search as $s) {
							$t_where .= ($t_where?' AND':'').' '.$v['search'].' '.DB::like().' '.DB::Concat(DB::qstr('%'),sprintf('%s',DB::qstr($s)),DB::qstr('%'));
						}
						$where .= ($where?' OR':'').' ('.$t_where.')';
					} else
						$where[(empty($where)?'(':'|').$v['search']][] = sprintf('%s',$search['__keyword__']);
			}
		}
 		if (isset($quickjump) && $quickjump_to!='') {
 			if ($quickjump_to=='0') {
	 			if (!$array) {
					$where = ($where?'('.$where.') AND':'').' (false';
					foreach(range(0,9) as $v)
						$where .= 	' OR '
									.$quickjump.' '.DB::like().' '.DB::Concat(sprintf('%s',DB::qstr($v)),'\'%\'');
					$where .= 	')';
					if ($where) $where = ' ('.$where.')';
	 			} else {
					$where[$quickjump] = array();
					foreach(range(0,9) as $v)
						$where[$quickjump][] = DB::qstr($v.'%');
	 			}
 			} else {
	 			if (!$array) {
					$where = ($where?'('.$where.') AND':'').' ('
								.$quickjump.' '.DB::like().' '.DB::Concat(sprintf('%s',DB::qstr($quickjump_to)),'\'%\'')
								.' OR '
								.$quickjump.' '.DB::like().' '.DB::Concat(sprintf('%s',DB::qstr(strtolower($quickjump_to))),'\'%\'').
								')';
					if ($where) $where = ' ('.$where.')';
	 			} else {
					$where[$quickjump] = array(DB::Concat(DB::qstr($quickjump_to),DB::qstr('%')),DB::Concat(DB::qstr(strtolower($quickjump_to)),DB::qstr('%')));
	 			}
 			}
		}
		return $where;
	}

	private function check_if_row_fits_array($row){
		$search = $this->get_module_variable('search');
		$this->get_module_variable_or_unique_href_variable('quickjump_to');
		$quickjump = $this->get_module_variable('quickjump');
		$quickjump_to = $this->get_module_variable('quickjump_to');
		$this->set_module_variable('quickjump_to',$quickjump_to);

		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

 		if (isset($quickjump) && $quickjump_to!='') {
			foreach($this->columns as $k=>$v){
				if (isset($v['quickjump'])){
					$r = strip_tags($row[$k]);
	 				if (!isset($r[0]) ||
	 					($quickjump_to != $r[0] &&
	 					strtolower($quickjump_to) != $r[0]))
	 					return false;
				}
			}
 		}
			if (!isset($search['__keyword__']) || $search['__keyword__']=='') return true;
			$ret = true;
			foreach($this->columns as $k=>$v){
				if (isset($v['search']) && isset($search['__keyword__'])) {
					$ret = false;
					if (is_array($row[$k])) $row[$k] = $row[$k]['value'];
					if (stripos(strip_tags($row[$k]),$search['__keyword__'])!==false) return true;
				}
			}
			return $ret;
	}

	private function sort_data(& $data, & $js=null, & $actions=null, & $row_attrs=null){
		if(!$this->columns) trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);
		if(($order = $this->get_order()) && $order=$order[0]) {
			$col = array();
			foreach($data as $j=>$d)
				foreach($d as $i=>$c)
					if(isset($this->columns[$i]['order']) && $this->columns[$i]['order']==$order['order']) {
						if(is_array($c)) {
							if(isset($c['order_value']))
								$xxx = $c['order_value'];
							else
								$xxx = $c['value'];
						} else $xxx = $c;
						if(isset($this->columns[$i]['order_preg'])) {
							$ret = array();
							preg_match($this->columns[$i]['order_preg'],$xxx, $ret);
							$xxx = isset($ret[1])?$ret[1]:'';
						}
						$xxx = strip_tags(strtolower($xxx));
						$col[$j] = $xxx;
					}

			asort($col);
			$data2 = array();
			$js2 = array();
			$actions2 = array();
			$row_attrs2 = array();
			foreach($col as $j=>$v) {
				$data2[] = $data[$j];
				if (isset($js)) $js2[] = $js[$j];
				if (isset($actions)) $actions2[] = $actions[$j];
				if (isset($row_attrs)) $row_attrs2[] = $row_attrs[$j];
			}
			if($order['direction']!='ASC') {
				$data2 = array_reverse($data2);
				$js2 = array_reverse($js2);
				$actions2 = array_reverse($actions2);
				$row_attrs2 = array_reverse($row_attrs2);
			}
			$data = $data2;
			$js = $js2;
			$actions = $actions2;
			$row_attrs = $row_attrs2;
		}
	}
	/**
	 * For internal use only.
	 */
	public function simple_table($header, $data, $page_split = true, $template=null, $order=true) {
		$len = count($header);
		foreach($header as $i=>$h) {
			if(is_string($h)) $header[$i]=array('name'=>$h);
			if($order) {
				$header[$i]['order']="$i";
			} else
				unset($header[$i]['order']);
		}
		$this->set_table_columns($header);

		if($order) {
			if(is_array($order)) $this->set_default_order($order);
			$this->sort_data($data);
		}

		if ($page_split){
			$cd = count($data);
			$limit = $this->get_limit($cd);
			for($i=$limit['offset']; $i<$limit['offset']+$limit['numrows'] && $i<$cd; $i++){
				$this->add_row_array($data[$i]);
			}

		} else {
			foreach($data as $row)
				$this->add_row_array($row);
		}
		$this->body($template);
	}

	/**
	 * Displays the table performing paging and searching automatically.
	 *
	 * @param bool enabling paging, true by default
	 */
	public function automatic_display($paging=true){
		if(!$this->columns)
			trigger_error('columns array empty, please call set_table_columns',E_USER_ERROR);

		$rows = array();
		$js = array();
		$actions = array();
		$row_attrs = array();
		foreach($this->columns as $k=>$v)
			if (isset($v['search'])) $this->columns[$k]['search'] = $k;

		foreach($this->rows as $k=>$v){
			if ($this->check_if_row_fits_array($v)) {
				$rows[] = $v;
				$js[] = isset($this->rows_jses[$k])?$this->rows_jses[$k]:'';
				$actions[] = isset($this->actions[$k])?$this->actions[$k]:array();
				$row_attrs[] = isset($this->row_attrs[$k])?$this->row_attrs[$k]:'';
			}
		}
		$this->sort_data($rows, $js, $actions, $row_attrs);

		$this->rows = array();
		$this->rows_jses = array();
		$this->actions = array();
		$this->row_attrs = array();
		if ($paging) $limit = $this->get_limit(count($rows));
		$id = 0;
		foreach($rows as $k=>$v) {
			if (!$paging || ($id>=$limit['offset'] && $id<$limit['offset']+$limit['numrows'])){
				$this->rows[] = $v;
				$this->rows_jses[] = $js[$k];
				$this->actions[] = $actions[$k];
				$this->row_attrs[] = $row_attrs[$k];
			}
			$id++;
		}
		$this->body();
	}

	/**
	 * Executes SQL query that selects elements needed for the current page
	 * and performs sort.
	 *
	 * @param string SQL query that selects all elements for the table
	 * @param string SQL query that will return number of rows in the table
	 */
	public function query_order_limit($query,$query_numrows) {
		$query_order = $this->get_query_order();
		$qty = DB::GetOne($query_numrows);
		$query_limits = $this->get_limit($qty);
		return DB::SelectLimit($query.$query_order,$query_limits['numrows'],$query_limits['offset']);
	}

  	//internal use
  	public function sort_actions($a,$b) {
		return $a['order']-$b['order'];
	}

	public function force_per_page($i) {
		if(!is_numeric($i))
			trigger_error('Invalid argument passed to force_per_page method.',E_USER_ERROR);

		$this->set_module_variable('per_page',$i);
		$this->forced_per_page = true;
	}

	//region Display
	/**
	 * Displays the table.
	 *
	 * @param string template file that should be used to display the table, use Base_ThemeCommon::get_template_filename() for proper filename
	 * @param bool enabling paging, true by default
	 */
	public function body($template=null,$paging=true)
	{
		if (!$this->columns)
			trigger_error('columns array empty, please call set_table_columns', E_USER_ERROR);

		$options = array();
		$md5_id = md5($this->get_path());
		$this->set_module_variable('first_display', 'done');
		$theme = $this->init_module(Base_Theme::module_name());
		$per_page = $this->get_module_variable('per_page');


		$pagination_form_builder = $this->create_form_builder(array());
		if (isset($this->rows_qty) && $paging) {

			if (!$this->forced_per_page) {
				$pagination_form_builder->add('per_page', 'choice', array(
						'label' => __('Number of rows per page'),
						'choices' => Utils_GenericBrowserCommon::$possible_vals_for_per_page,
						'data' => $per_page
					)
				);
			}

			$qty_pages = ceil($this->rows_qty / $this->per_page);
			if ($qty_pages <= 25) {
				$pages = array();
				if ($this->rows_qty == 0)
					$pages[0] = 1;
				else
					foreach (range(1, $qty_pages) as $row_col) $pages[$row_col] = $row_col;

				$pagination_form_builder->add('page','choice',array(
					'label' => __('Page'),
					'choices' => $pages,
					'data' => (int)(ceil($this->offset / $this->per_page) + 1)
				));
			} else {
				$pagination_form_builder->add('page', 'text', array(
					'label' => __('Page (%s to %s)', array(1, $qty_pages)),
					'data' => (int)(ceil($this->offset / $this->per_page) + 1)
				));
			}
			$pagination_form = $pagination_form_builder->getForm();


			$pagination_form->handleRequest();
			if($pagination_form->isValid()){
				$values = $pagination_form->getData();
				if (isset($values['per_page'])) {
					$this->set_module_variable('per_page', $values['per_page']);
					Base_User_SettingsCommon::save(Utils_GenericBrowser::module_name(), 'per_page', $values['per_page']);
				}
				if (isset($values['page']) && is_numeric($values['page']) && ($values['page'] >= 1 && $values['page'] <= $qty_pages)) {
					$this->set_module_variable('offset', ($values['page'] - 1) * $this->per_page);
				}
			}
		}


		$search = $this->get_module_variable('search');

		$search_on = Arrays::matchesAny($this->columns, function($column){
			return isset($column['search']);
		});

		$search_form_builder = $this->create_form_builder(array());

		if ($search_on) {

			$search_form_builder->add('search', 'text', array(
				'label' => __('Keyword'),
//				'placeholder' => __('search keyword...'),
				'data' => isset($search['__keyword__']) ? $search['__keyword__'] : ''
			));

//			$search_form_builder->add('submit_search', 'submit', array(
//				'label' => 'Search'
//			));

			if (Base_User_SettingsCommon::get($this->get_type(), 'show_all_button')) {
//				$search_form_builder->add('show_all','submit',array(
//					'label' => __('Show all')
//				));
			}

			$search_form = $search_form_builder->getForm();

			$search_form->handleRequest();
			if ($search_form->isValid()) {
				$values = $search_form->getData();
				//todo-pj: to nie będzie działać jeżeli bedziemy mieli system odświeżania strony jaki mamy (is clikced zawsze jest false)
//				if ($search_form->get('show_all')->isClicked()) {
//					$this->set_module_variable('search', array());
//					$this->set_module_variable('show_all_triggered', true);
//					location(array());
//					return;
//				}

				$search = array();
				foreach ($values as $row_col_num => $row_col) {
					if ($row_col_num == 'search') {
						if ($row_col != __('search keyword...') && $row_col != '')
							$search['__keyword__'] = $row_col;
						break;
					}
					if (substr($row_col_num, 0, 8) == 'search__') {
						$val = substr($row_col_num, 8);
						if ($row_col != __('search keyword...') && $row_col != '') $search[$val] = $row_col;
					}
				}
				$this->set_module_variable('search', $search);
			}
		}


		if ($this->en_actions) {
			$max_actions = 0; // Possibly improve it to calculate it during adding actions
			foreach ($this->actions as $row_num => $row_col) {
				$this_width = 0;
				foreach ($row_col as $vv) {
					$this_width += $vv['size'];
				}
				if ($this_width > $max_actions) $max_actions = $this_width;
			}
		}


		$all_width = 0;
		foreach ($this->columns as $row_col_num => $row_col) {
			if (!isset($this->columns[$row_col_num]['width'])) $this->columns[$row_col_num]['width'] = 100;
			if (!is_numeric($this->columns[$row_col_num]['width'])) continue;
			$all_width += $this->columns[$row_col_num]['width'];
			if (isset($row_col['quickjump'])) {
				$quickjump = $this->set_module_variable('quickjump', $row_col['quickjump']);
				$quickjump_col = $row_col_num;
			}
		}


		$is_order = Arrays::matchesAny($this->columns, function ($column) {
			return Arrays::has($column, 'order');
		});

		$out_data = array();

		$letter_links = null;
		if (isset($quickjump)) {
			$letter_links = $this->get_quickjump_letters();
			$options['quickjump_to'] = $this->get_module_variable('quickjump_to');
		}

		$table_data = $this->get_rows_template_data();

		foreach($table_data as $row_data)
			foreach($row_data['columns'] as $col_data)
				$out_data[] = $col_data;

		$options['data'] = $out_data;
		$options['cols'] = $this->get_columns_template_data();

		$options['row_attrs'] = $this->row_attrs;

		$options['table_id'] = 'table_' . $md5_id;
		$options['table_prefix'] = $this->table_prefix;
		$options['table_postfix'] = $this->table_postfix;

		$options['summary'] = $this->summary();
		$options['custom_label'] = $this->custom_label;
		$options['custom_label_args'] = $this->custom_label_args;

		$this->expandable = $this->get_module_variable('expandable', $this->expandable);


		if ($this->expandable) {
			if (!$this->en_actions) {
				eval_js('gb_expandable_hide_actions("' . $md5_id . '")');
				$this->en_actions = true;
			}

			eval_js_once('gb_expandable["' . $md5_id . '"] = {};');
			eval_js_once('gb_expanded["' . $md5_id . '"] = 0;');

			eval_js_once('gb_expand_icon = "' . Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'expand.gif') . '";');
			eval_js_once('gb_collapse_icon = "' . Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'collapse.gif') . '";');
			eval_js_once('gb_expand_icon_off = "' . Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'expand_gray.gif') . '";');
			eval_js_once('gb_collapse_icon_off = "' . Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'collapse_gray.gif') . '";');

			foreach ($this->rows as $row_num => $row) {
				$row_id = $md5_id . '_' . $row_num;
				$this->__add_row_action($row_num, 'style="display:none;" href="javascript:void(0)" onClick="gb_expand(\'' . $md5_id . '\',\'' . $row_num . '\')" id="gb_more_' . $row_id . '"', 'Expand', null, Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'plus_gray.png'), 1001);
				$this->__add_row_action($row_num, 'style="display:none;" href="javascript:void(0)" onClick="gb_collapse(\'' . $md5_id . '\',\'' . $row_num . '\')" id="gb_less_' . $row_id . '"', 'Collapse', null, Base_ThemeCommon::get_template_file(Utils_GenericBrowser::module_name(), 'minus_gray.png'), 1001, false, 0);
				$this->__add_row_js($row_num, 'gb_expandable_init("' . Epesi::escapeJS($md5_id, true, false) . '","' . Epesi::escapeJS($row_num, true, false) . '")');
				if (!isset($this->row_attrs[$row_num])) $this->row_attrs[$row_num] = '';
				$this->row_attrs[$row_num] .= 'id="gb_row_' . $row_id . '"';
			}

			$options['expand_collapse'] = array(
				'e_label' => __('Expand All'),
				'e_href' => 'href="javascript:void(0);" onClick=\'gb_expand_all("' . $md5_id . '")\'',
				'e_id' => 'expand_all_button_' . $md5_id,
				'c_label' => __('Collapse All'),
				'c_href' => 'href="javascript:void(0);" onClick=\'gb_collapse_all("' . $md5_id . '")\'',
				'c_id' => 'collapse_all_button_' . $md5_id
			);
			$max_actions = isset($max_actions) ? $max_actions : 0;
			eval_js('gb_expandable_adjust_action_column("' . $md5_id . '", ' . $max_actions . ')');
			eval_js('gb_show_hide_buttons("' . $md5_id . '")');
		}


		if (Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(), 'adv_history') && $is_order) {
			$options['reset'] = '<a ' . $this->create_unique_href(array('action' => 'reset_order')) . '>' . __('Reset Order') . '</a>';
			$options['order'] = $this->get_module_variable('order_history_display');
		}
		$options['id'] = md5($this->get_path());

		foreach($options as $key => $value)
			$theme->assign($key, $value);

		if (isset($template))
			$theme->display($template, true);
		else
			$theme->display();
		$this->set_module_variable('show_all_triggered', false);

		$pagination = array(
			'first' => $this->gb_first(),
			'prev' => $this->gb_prev(),
			'next' => $this->gb_next(),
			'last' => $this->gb_last()
		);

		//todo-pj: Zamienić content na callback
		$js = <<<JS
		jQuery('.popover-info').each(function () {
        var rownum = jQuery(this).parent().parent().data('row-num');
        var actionnum = jQuery(this).data('action-num');
        var html = jQuery('tr[data-row-num="' + rownum + '"] .tooltip-data[data-action-num="' + actionnum + '"]').html();
        jQuery(this).popover(
                {
                	title: "",
                    html: true,
                    content: html,
                    trigger: 'hover'
                }
        )
    });
JS;
		eval_js($js);

		$this->display('table.twig', array(
				'columns' => $this->get_columns_template_data(),
				'rows' => $this->get_rows_template_data(),
				'summary' => $this->summary(),
				'letter_links' => $letter_links,
				'id' => $md5_id,
				'pagination' => $pagination,
				'pagination_form' => isset($pagination_form) ? $pagination_form->createView() : false,
				'search_form' => isset($search_form) ? $search_form->createView() : false,
				'enable_actions' => $this->en_actions,
				'actions_position' => Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(),'actions_position')
		));
	}

	private function get_rows_template_data()
	{
		$table_data = array();

		foreach ($this->rows as $row_num => $row) {
			$row_data = array();
			$row_data['actions'] = array();
			if ($this->en_actions) {
				//$actions_position jeśli 0 to na początku inaczej na końcu
				if (!empty($this->actions[$row_num])) {
					uasort($this->actions[$row_num], array($this, 'sort_actions'));
					foreach ($this->actions[$row_num] as $icon => $arr) {
						$action = array();
						$action['href'] = $arr['tag_attrs'];
						$action['label'] = $arr['label'];
						$action['tooltip'] = $arr['tooltip'];
						$action['icon'] = $icon;
						$row_data['actions'][] = $action;
					}

					// Add overflow_box to actions
					//REGRES
//					$settings = Base_User_SettingsCommon::get('Utils_GenericBrowser', 'zoom_actions');
//					if ($settings == 2 || ($settings == 1 && detect_iphone()))
//						' onmouseover="if(typeof(table_overflow_show)!=\'undefined\')table_overflow_show(this,true);"';
				}
			}

			$row_data['columns'] = array();
			foreach ($row as $row_col_num => $row_col) {
				$col = array();
				$column_meta = $this->columns[$row_col_num];

				if (!is_array($row_col)) $row_col = array('value' => $row_col);

				if(!Arrays::get($column_meta, 'display', true)) continue;

				if(Arrays::get($row_col, 'dummy', false)) $row_col['style'] = 'display:none;';

				if(Arrays::has($row_col, 'attrs')) $col['attrs'] = $row_col['attrs'];
				else $col['attrs'] = '';


				$col['label'] = $row_col['value'];

				if (Arrays::get($row_col, 'overflow_box', true)) {
					$col['attrs'] .= ' onmouseover="if(typeof(table_overflow_show)!=\'undefined\')table_overflow_show(this);"';
				} else {
					if (!isset($row_col['style'])) $row_col['style'] = '';
					$row_col['style'] .= 'white-space: normal;';
				}


				if (isset($quickjump_col) && $row_col_num == $quickjump_col) $col['attrs'] .= ' class="Utils_GenericBrowser__quickjump"';

				$col['style'] = Arrays::get($row_col, 'style');
				$col['class'] = Arrays::get($row_col, 'class');
				$col['hint'] = Arrays::get($column_meta,'hint');
				$col['wrapmode'] = Arrays::get($column_meta,'wrapmode');
				$col['expanded'] = $this->expandable;
				$row_data['columns'][] = $col;
			}

			//REGRES
//			if ($this->absolute_width)
//				foreach ($col as $row_col_num => $row_col) if (isset($row_col['width'])) $col['attrs'] .= ' width="' . $row_col['width'] . '"';

			if (isset($this->rows_jses[$row_num])) eval_js($this->rows_jses[$row_num]);
			$table_data[] = $row_data;
		}
		return $table_data;
	}

	private function get_columns_template_data()
	{
		$columns = array();
		$order = $this->get_module_variable('order');
		$adv_history = Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(), 'adv_history');
		foreach ($this->columns as $col) {
			if (array_key_exists('display', $col) && $col['display'] == false) {
				continue;
			}

			$out_col = array();

			if (!$adv_history && $col['name'] && $col['name'] == $order[0]['column'])
				$out_col['sort'] = strtolower($order[0]['direction']);

			$out_col['name'] = $col['name'];
			$out_col['preppend'] = Arrays::get($col,'preppend');
			$out_col['append'] = Arrays::get($col,'append');
			$out_col['change_order_href'] = $this->create_unique_href(array('change_order' => $col['name']));
			$columns[] = $out_col;
		}
		return $columns;
	}

	public function get_quickjump_letters()
	{
		$quickjump_to = $this->get_module_variable('quickjump_to');
		$letter_links = array();

		$all['label'] =  __('All');
		if (isset($quickjump_to) && $quickjump_to != '') $all['href'] = $this->create_unique_href(array('quickjump_to' => ''));
		$letter_links[] = $all;

		$one_two_three['label'] = '123';
		if ($quickjump_to != '0') {
			$one_two_three['href'] = $this->create_unique_href(array('quickjump_to' => '0'));
		}
		$letter_links[] = $one_two_three;

		$letter = 'A';
		while ($letter <= 'Z') {
			$letter_link['label'] = $letter;
			if ($quickjump_to != $letter)
				$letter_link['href'] = $this->create_unique_href(array('quickjump_to' => $letter));

			$letter_links[] = $letter_link;
			$letter = chr(ord($letter) + 1);
		}
		return $letter_links;
	}
	
	public function show_all() {
		return $this->get_module_variable('show_all_triggered',false);
	}

	private function summary() {
		if($this->rows_qty!=0)
			return __('Records %s to %s of %s',array('<b>'.($this->get_module_variable('offset')+1).'</b>','<b>'.(($this->get_module_variable('offset')+$this->get_module_variable('per_page')>$this->rows_qty)?$this->rows_qty:$this->get_module_variable('offset')+$this->get_module_variable('per_page')).'</b>','<b>'.$this->rows_qty.'</b>'));
		else
		if ((isset($this->rows_qty) || (!isset($this->rows_qty) && empty($this->rows))) && !Base_User_SettingsCommon::get(Utils_GenericBrowser::module_name(),'display_no_records_message'))
			return __('No records found');
		else
			return '';
	}
	//endregion
	//region Pagination
	private function gb_first() {
		if($this->get_module_variable('offset')>0){
			return array(
				'href' => $this->create_unique_href(array('first'=>1)),
				'label' => __('First')
			);
		}
			return null;
	}

	private function gb_prev() {
		if($this->get_module_variable('offset')>0){
			return array(
				'href' => $this->create_unique_href(array('prev'=>1)),
				'label' => __('Prev')
			);
		}
    		return null;
	}

	private function gb_next() {
		if($this->get_module_variable('offset')+$this->get_module_variable('per_page')<$this->rows_qty) {
			return array(
				'href' => $this->create_unique_href(array('next'=>1)),
				'label' => __('Next')
			);
		}
      		return null;
	}

	private function gb_last() {
		if($this->get_module_variable('offset')+$this->get_module_variable('per_page')<$this->rows_qty) {
			return array(
				'href' => $this->create_unique_href(array('last'=>1)),
				'label' => __('Last')
			);
		}
      		return null;
	}

	public function set_prefix($arg) {
		$this->table_prefix = $arg;
	}

	public function set_postfix($arg) {
		$this->table_postfix = $arg;
	}
	//endregion

}

?>
