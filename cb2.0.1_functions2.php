<?php
// Массив данных с "русскими" именами полей
function data_table($name_table, $where="", $return_rows="1")
{
  // получаем id таблицы по ее имени
  $table = sql_select_array(TABLES_TABLE, "name_table='",$name_table,"'");
  $table_id = $table['id'];

  if ($table_id)
    {
      $table = get_table($table_id);
      $table_fields = get_table_fields($table);

      // формируем набор полей и заменяем имена в условии
      foreach ($table_fields as $one_field)
        {
          $fields[$one_field['int_name']] = $one_field['name_field'];
          $where = str_replace("`".$one_field['name_field']."`", $one_field['int_name'], $where);
        }

      // получаем строку данных из таблицы и заменяем внутрение имена полей на внешние
      $result = data_select($table_id, ($where?$where:"1=1").(($return_rows!="all")?" LIMIT $return_rows":""));
      while ($row = sql_fetch_array($result))
        {
          foreach ($row as $int_name=>$value) if ($fields[$int_name]) $data[$fields[$int_name]] = $value;
          $lines[] = $data;
        }

      // возвращаем результат
      if ($return_rows==1)
        if ($lines) return $lines[0]; else return false;
      else
        if ($lines) return $lines; else return array();
    }
    else
    {
      echo "Error in function 'data_table': table '".$name_table."' no found.";
      return false;
    }
}

// Вставка данных в таблицу name_table массивом data с "русскими" именами полей
function insert_query($data, $name_table)
{
  // получаем id таблицы по ее имени
  $table = sql_select_array(TABLES_TABLE, "name_table='",$name_table,"'");
  $table_id = $table['id'];

  if ($table_id)
    {
      $table = get_table($table_id);
      $table_fields = get_table_fields($table);

      // формируем набор для вставки
      foreach ($table_fields as $one_field)
        {
          if (isset($data[$one_field['name_field']])) $fields[$one_field['int_name']] = $data[$one_field['name_field']];
        }

      // вставляем данные
      $new_id = data_insert($table_id, EVENTS_ENABLE, $fields);

      // возвращаем id новой записи
      return $new_id;
    }
    else
    {
      echo "Error in function 'insert_query': table '".$name_table."' no found.";
      return false;
    }
}

// Обновление данных таблицы name_table массивом data с "русскими" именами полей
function update_query($data, $name_table, $where="")
{
  // получаем id таблицы по ее имени
  $table = sql_select_array(TABLES_TABLE, "name_table='",$name_table,"'");
  $table_id = $table['id'];

  if ($table_id)
    {
      $table = get_table($table_id);
      $table_fields = get_table_fields($table);

      // формируем набор для обновления и заменяем имена в условии
      foreach ($table_fields as $one_field)
        {
          if (isset($data[$one_field['name_field']])) $fields[$one_field['int_name']] = $data[$one_field['name_field']];
          $where = str_replace("`".$one_field['name_field']."`", $one_field['int_name'], $where);
        }

      // обновляем таблицу
      data_update($table_id, EVENTS_ENABLE, $fields, $where?$where:"1=1");
    }
    else
    {
      echo "Error in function 'update_query': table '".$name_table."' no found.";
    }
}

// Удаление строки таблицы name_table
function delete_query($name_table, $where="")
{
  // получаем id таблицы по ее имени
  $table = sql_select_array(TABLES_TABLE, "name_table='",$name_table,"'");
  $table_id = $table['id'];

  if ($table_id)
    {
      $table = get_table($table_id);
      $table_fields = get_table_fields($table);

      // заменяем внешние имена в условии на внутренние
      foreach ($table_fields as $one_field)
        {
          $where = str_replace("`".$one_field['name_field']."`", $one_field['int_name'], $where);
        }

      // обновляем таблицу
      data_update($table_id, EVENTS_ENABLE, array('status'=>2), $where?$where:"1=1");
    }
    else
    {
      echo "Error in function 'delete_query': table '".$name_table."' no found.";
    }
}

// Совместимость со старой функцией update_table
function update_table($data, $name_table, $where="")
{
  return update_query($data, $name_table, $where);
}

// Поиск по полю
function set_search($name_table, $name_field, $compare, $value, $value2="", $union=" and ")
{
  global $ses_id;

  // получаем id таблицы по ее имени
  $table = sql_select_array(TABLES_TABLE, "name_table='",$name_table,"'");
  $table_id = $table['id'];

  // получаем id поля по его имени
  $field = sql_select_array(FIELDS_TABLE, "table_id=",$table_id," AND name_field='",$name_field,"'");
  $field_id = $field['id'];

  $condition['field'] = $field_id;
  $condition['term'] = $compare;
  $condition['value'] =  $value;
  $condition['value2'] = $value2;
  $condition['union'] = $union;

  $i = count($_SESSION[$ses_id]['search'][$table_id]) + 1;
  $_SESSION[$ses_id]['search'][$table_id][$i] = $condition;
}

// Сброс поиска по таблице/полю
function reset_search($name_table, $name_field="")
{
  global $ses_id;

  // получаем id таблицы по ее имени
  $table = sql_select_array(TABLES_TABLE, "name_table='",$name_table,"'");
  $table_id = $table['id'];

  if ($name_field)
    {
      // получаем id поля по его имени
      $field = sql_select_array(FIELDS_TABLE, "table_id=",$table_id," AND name_field='",$name_field,"'");
      $field_id = $field['id'];

      $set_cond_old = is_array($_SESSION[$ses_id]['search'][$table_id])?$_SESSION[$ses_id]['search'][$table_id]:array(); $i = 1;
      foreach ($set_cond_old as $condition)
        {
          if ($condition['field']!=$field_id) $set_cond[$i++] = $condition;
        }
      $_SESSION[$ses_id]['search'][$table_id] = $set_cond;
    }
    else
    {
      unset($_SESSION[$ses_id]['search'][$table_id]);
    }
}

// Установка фильтра по полю
function set_filter($field_id, $compare, $value, $value2="", $union=" and ")
{
  global $ses_id;

  $field = sql_select_array(FIELDS_TABLE, "id=",$field_id);
  $table_id = $field['table_id'];

  $condition['field'] = $field_id;
  $condition['term'] = $compare;
  $condition['value'] =  $value;
  $condition['value2'] = $value2;
  $condition['union'] = $union;

  $i = count($_SESSION[$ses_id]['search'][$table_id]) + 1;
  $_SESSION[$ses_id]['search'][$table_id][$i] = $condition;
}

// Сброс фильтра по полю
function reset_filter($field_id)
{
  global $ses_id;

  $field = sql_select_array(FIELDS_TABLE, "id=",$field_id);
  $table_id = $field['table_id'];

  $set_cond_old = is_array($_SESSION[$ses_id]['search'][$table_id])?$_SESSION[$ses_id]['search'][$table_id]:array(); $i = 1;
  foreach ($set_cond_old as $condition)
    {
      if ($condition['field']!=$field_id) $set_cond[$i++] = $condition;
    }
  $_SESSION[$ses_id]['search'][$table_id] = $set_cond;
}

// Сброс фильтров по таблице
function reset_filters($table_id)
{
  global $ses_id;

  unset($_SESSION[$ses_id]['search'][$table_id]);
}

// выбор шаблона с учетом мобильности и разрешения экрана
function smarty_display($tpl_name)
{
  global $config, $smarty;
  $tpl_name = $tpl_name.'.tpl';

  $w = intval($_COOKIE["screen_width"]);
  $h = intval($_COOKIE["screen_height"]);
  $is_mobile = $config['is_mobile'];

  if ($w<640) $w = 320; // приведение по ширине
     elseif ($w<1024) $w = 640;
     else $w = 1024;

  if ($h<480) $h = 240; // приведение по высоте
     elseif ($h<768) $h = 480;
     else $h = 768;

  $path = $config['site_path'].'/templates/'.$tpl_name;

  if ($is_mobile) {
    $path = $config['site_path'].'/templates/mobile/'.$w.'_'.$h.'/'.$tpl_name; // вариант по размеру
    if (!file_exists($path)) {
      $path = $config['site_path'].'/templates/mobile/common/'.$tpl_name; // вариант common
      if (!file_exists($path)) {
        $path = $config['site_path'].'/templates/'.$tpl_name; // стандартный (desktop) вариант
      }
    }
    $m_style_css = '/templates/mobile/'.$w.'_'.$h.'/m_style.css';
    if (!file_exists($config['site_path'].$m_style_css))
       {
         $m_style_css = '/templates/mobile/common/m_style.css';
       }
    $m_style_css = $config['site_root'].$m_style_css;
  }
  $smarty->assign('screen_resolution', $w."*".$h);
  $smarty->assign('m_style_css', $m_style_css);
  $smarty->display($path);

}

// выбор изображения с учетом мобильности и разрешения экрана
function image_mobile($img_name, $only_path=false) 
{
  global $config;
  $w = intval($_COOKIE["screen_width"]);
  $h = intval($_COOKIE["screen_height"]);
  $is_mobile = $config['is_mobile'];

  if ($w<640) $w = 320; // приведение по ширине
     elseif ($w<1024) $w = 640;
     else $w = 1024;

  if ($h<480) $h = 240; // приведение по высоте
     elseif ($h<768) $h = 480;
     else $h = 768;

  $path = '/images/mobile/'.$w.'_'.$h.'/'.$img_name;
  if (!file_exists($config['site_path'].$path))
     { // вариант по размеру не существует, используем общий вариант
       $path = '/images/mobile/common/'.$img_name;
       if (!file_exists($config['site_path'].$path))
          { // общий вариант не существует используем (desktop) вариант
            $path = '/images/'.$img_name;
          }
     }

  if ($only_path) return $config['site_root'].$path;
     else return '<img src="'.$config['site_root'].$path.'" border="0">';
}

// смена режима мобильный <-> компьютер
function set_mobile($is_mobile)
{
  global $config;
  if ($is_mobile)
     {
       setcookie('is_mobile', 1);
       $config['is_mobile']=1;
     }
     else
     {
      setcookie('is_mobile', 0);
      $config['is_mobile']=0;
    }
} 

/**
 * @param int   $cat_id         идентификатор категории
 * @param int   $table_id       идентификатор таблицы
 * @param array $some_params    дополнительные параметры
 *                 ['form_type']   тип формы (print,send,sms)
 *
 * @return array                возврат массива с данными навигации
 *                 ['cats']         категории
 *                 ['tables']       таблицы
 *                 ['reports']      представления
 *                 ['calendars']    календари
 *                 ['now_param']    название параметра таблицы, в котором мы находимся
 *                 ['count_params'] количество параметров
 *                 ['param_array']  массив с данными параметров таблицы,
 *                                  ключ - название параметра
 *                                  сортировка параметров по порядку определения
 *                    ['table_query'] - название таблицы с параметром
 *                    ['file_name']   - название файла с параметром
 *                    ['add_params']  - дополнительные параметры для ссылки на настройки параметров
 *                    ['add_query']   - дополнительные параметры для запроса таблицу параметра
 *                    ['count']       - количество настроенных позиций по параметру
 */
function get_navigation_data( $cat_id=0, $table_id=0, $some_params = array()) {

  global $lang, $user;
  
  if ($user['group_id']!=1)
    {
      $sa_arr = array();
      if (is_array($user['sub_admin_rights']['tables']))
        foreach ($user['sub_admin_rights']['tables'] as $k=>$v)
          {
            if ($v>0)
              {
                $q = sql_select(TABLES_TABLE,"id=",$k);
                $d = sql_fetch_assoc($q);
                $sa_arr[] = $d['cat_id'];
              }
          }
      if (is_array($user['sub_admin_rights']['reports']))
        foreach ($user['sub_admin_rights']['reports'] as $k=>$v)
          {
            if ($v>0)
              {
                $q = sql_select(REPORTS_TABLE,"id=",$k);
                $d = sql_fetch_assoc($q);
                $sa_arr[] = $d['cat_id'];
              }
          }
      if (is_array($user['sub_admin_rights']['calendars']))
        foreach ($user['sub_admin_rights']['calendars'] as $k=>$v)
          {
            if ($v>0)
              {
                $q = sql_select(CALENDARS_TABLE,"id=",$k);
                $d = sql_fetch_assoc($q);
                $sa_arr[] = $d['cat_id'];
              }
          }
      if (is_array($user['sub_admin_rights']['cats']))
        foreach ($user['sub_admin_rights']['cats'] as $k=>$v)
          {
            if ($v>0) $sa_arr[] = $k;
          }
      $sa_cats = implode(", ", $sa_arr);
      if ($sa_cats) $sa_cats = "AND id in (".$sa_cats.")";
      else $sa_cats = "AND id=0";
      
      $sa_arr = array();
      if (is_array($user['sub_admin_rights']['tables']))
        foreach ($user['sub_admin_rights']['tables'] as $k=>$v)
          {
            if ($v>0) $sa_arr[] = $k;
          }
      $sa_tables = implode(", ", $sa_arr);
      if ($sa_tables) $sa_tables = "AND id in (".$sa_tables.")";
      else $sa_tables = "AND id=0";
      
      $sa_arr = array();
      if (is_array($user['sub_admin_rights']['reports']))
        foreach ($user['sub_admin_rights']['reports'] as $k=>$v)
          {
            if ($v>0) $sa_arr[] = $k;
          }
      $sa_reports = implode(", ", $sa_arr);
      if ($sa_reports) $sa_reports = "AND id in (".$sa_reports.")";
      else $sa_reports = "AND id=0";
      
      $sa_arr = array();
      if (is_array($user['sub_admin_rights']['calendars']))
        foreach ($user['sub_admin_rights']['calendars'] as $k=>$v)
          {
            if ($v>0) $sa_arr[] = $k;
          }
      $sa_calendars = implode(", ", $sa_arr);
      if ($sa_calendars) $sa_calendars = "AND id in (".$sa_calendars.")";
      else $sa_calendars = "AND id=0";
    }
  
  $data_final = array();

  $cat = intval($cat_id);
  $table = intval($table_id);

  // ---------- получаем категории
  $data_final['cats'] = array();
  $result = sql_select_field(CATS_TABLE,"id,name","1=1 ".$sa_cats." ORDER BY name");
  while ($row = sql_fetch_assoc($result))
    $data_final['cats'][$row['id']] = $row['name'];

  // ---------- получаем таблицы и представления

  // если установлена категория, фильтруем таблицы по категории
  if ($cat)
    $cat_condition = " AND cat_id=".$cat;

  // таблицы
  $data_final['tables'] = array();
  $result = sql_select_field(TABLES_TABLE,"id,name_table","1=1".$cat_condition." ".$sa_tables." ORDER BY ".( $cat ? "table_num" : "name_table"));
  while ($row = sql_fetch_assoc($result))
    $data_final['tables'][$row['id']] = $row['name_table'];

  // представления
  $data_final['reports'] = array();
  $result = sql_select_field(REPORTS_TABLE,"id,name","1=1".$cat_condition." ".$sa_reports." ORDER BY ".( $cat ? "num" : "name"));
  while ($row = sql_fetch_assoc($result))
    $data_final['reports'][$row['id']] = $row['name'];

  // календари
  $data_final['calendars'] = array();
  $result = sql_select_field(CALENDARS_TABLE,"id,name","1=1".$cat_condition." ".$sa_calendars." ORDER BY ".( $cat ? "num" : "name"));
  while ($row = sql_fetch_assoc($result))
    $data_final['calendars'][$row['id']] = $row['name'];

  // получаем настройки выбранной таблицы
  if ($table>0) {
    $data_final['params'] = array();

    // определяем массив настроек и их таблицы
    $configs_array = array(
      $lang['fields']       => array( 'table_query' => FIELDS_TABLE,    'file_name' => 'edit_field.php'),
      $lang['formatting']   => array( 'table_query' => FORMAT_TABLE,    'file_name' => 'edit_format.php'),
      $lang['filters']      => array( 'table_query' => FILTERS_TABLE,   'file_name' => 'edit_filter.php'),
    );
    
    if ($user['group_id']==1)
      {
        $configs_array[$lang['adds_buttons']] = array( 'table_query' => BUTTONS_TABLE,   'file_name' => 'edit_button.php');
        $configs_array[$lang['computation']]  = array( 'table_query' => CALC_TABLE,      'file_name' => 'edit_calc.php', 'add_query' =>" AND name NOT LIKE 'Button %'");
      }
    
    $configs_array[$lang['questionaries']]    = array( 'table_query' => QST_TABLE,       'file_name' => 'edit_questionare.php');
    $configs_array[$lang['print_templates']]  = array( 'table_query' => FORMS_TABLE,     'file_name' => 'forms.php',     'add_params' =>"&mode=print&admin", 'add_query'=>" AND dest_form='print'");
    $configs_array[$lang['Mail_templates']]   = array( 'table_query' => FORMS_TABLE,     'file_name' => 'forms.php',     'add_params' =>"&mode=send&admin",  'add_query'=>" AND dest_form='send'");
    $configs_array[$lang['SMS_templates']]    = array( 'table_query' => FORMS_TABLE,     'file_name' => 'forms.php',     'add_params' =>"&mode=sms&admin",   'add_query'=>" AND dest_form='sms'");
    $configs_array[$lang['notifiers']]        = array( 'table_query' => TIPS_TABLE,      'file_name' => 'edit_tip.php');
    $configs_array[$lang['subtables']]        = array( 'table_query' => SUBTABLES_TABLE, 'file_name' => 'edit_subtable.php');
    
    $data_final['param_array'] = $configs_array;

    // проходим по всем настройкам
    foreach ($configs_array as $param_name=>$param_table) {
      $result = sql_select_field($param_table['table_query'],"COUNT(*) as cnt","table_id=",$table,$param_table['add_query']);
      $row = sql_fetch_assoc($result);
      $data_final['param_array'][$param_name]['count'] = $row['cnt'];
    }

    $data_final['count_params'] = count($configs_array);

    // определяем в какой настройке мы находимся
    $file_properties = pathinfo($_SERVER['SCRIPT_NAME']);
    foreach($configs_array as $name=>$data)
    {
      if ($data['file_name'] == $file_properties['basename']
          && $some_params['form_type']
          && $data['add_params']
          && stripos($data['add_params'],trim($some_params['form_type']))!==false)
      {
        $data_final['now_param'] = $name;
        break;
      }
      elseif (!$some_params['form_type'] && $data['file_name'] == $file_properties['basename'])
      {
        $data_final['now_param'] = $name;
        break;
      }
    }
  }

  // вот и все
  return $data_final;
}
?>
