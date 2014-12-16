<?php

function form_display($display)     //при выводе на экран
{
  if (is_array($display))
    foreach ($display as $k=>$v) $display[$k] = form_display($v);
  else
    $display = htmlspecialchars($display, ENT_QUOTES, "utf-8");
  return $display;
}

function form_input($input)     //при вводе данных
{
  if (get_magic_quotes_gpc())
    {
      if (is_array($input))
        foreach ($input as $k=>$v) $input[$k] = form_input($v);
      else
        $input = stripslashes($input);
    }
  return $input;
}

function form_sql($str)     //при занесении в базу
{
  global $sql_current_link, $sql_db_types;
  if ($link_identifier==0) $link_identifier = $sql_current_link;
  if ($sql_db_types[$link_identifier]=="mysql") return addslashes($str);
  if ($sql_db_types[$link_identifier]=="postgresql") return pg_escape_string($str);
}

function filter_sql($str) // Вырезаем кавычки
{
  $s=array("'",chr(0),'\\');
  $r=array("","","");
  return str_replace($s,$r,$str);
}

function form_local_number($eng_number, $decimals, $space_char=" ")   //при выводе на экран
{
  global $lang;
  $ret_val = number_format(floatval($eng_number), $decimals, $lang["float_delimiter"], $space_char);
  return $ret_val;
}

function form_eng_number($local_number)   //при занесении в базу
{
  global $lang;
  $local_number=$local_number;
  $local_number_len=mb_strlen($local_number);
  if ($local_number_len>255) return 0;
  $ret_val="";
  for ($i=0;$i<$local_number_len;$i++)
      {
         $chr=mb_substr($local_number,$i,1);
         if ((ord($chr)>47)&&(ord($chr)<58))
              $ret_val.=$chr;
            else
         if (($chr==$lang["float_delimiter"])||($chr==".")) $ret_val.=".";
         if ($chr=="-") $ret_val.="-";
      };
  return $ret_val;
}

function form_local_time($eng_time, $display_time=0, $display_sec=0)   //при выводе на экран
{
  global $lang;
  if ($eng_time=="") return "";
  if ($eng_time=="0000-00-00 00:00:00") return "";
  return date($lang["date_format"].($display_time?(" H:i".($display_sec?":s":"")):""), strtotime($eng_time));
}

function form_eng_time($local_time, $display_time=1)     //при занесении в базу
{
  global $lang;
  if ($local_time=="") return "0000-00-00".($display_time?(" 00:00:00"):"");
  eval($lang['date_to_timestamp']);
  if (!$year) $year="0000"; if (!$month) $month="00"; if (!$day) $day="00";
  if (!$hour) $hour="00";   if (!$min)   $min="00";   if (!$sec) $sec="00";
  $int_time = strtotime("$year-$month-$day $hour:$min:$sec");
  if ($int_time) return date("Y-m-d".($display_time?(" H:i:s"):""),$int_time); else return false;
}

function eval_php_condition($line,$evl)
{
  global $user, $config, $lang;
  
  $shablons = array("{current}", "{current_group}", "{empty_date}", "{current_date}", "{current_time}", "{cur.date}", "{cur.time}");
  $replace = array($user['id'], $user['group_id'], "0000-00-00 00:00:00", date("Y-m-d 00:00:00"), date("Y-m-d H:i:s"), date("Y-m-d 00:00:00"), date("Y-m-d H:i:s"));
  $evl = str_replace($shablons, $replace, $evl);
  
  $evl = 'return '.$evl.";";
//echo "<br>\n".$evl."<br>\n";
  return eval($evl);
}

// тест, можно ли генерировать напоминание
function test_allow_tip_line($table, $line, $tip_id, $default=-1)
{
  global $user;
  
  if (!is_array($table))
    {
      $table = get_table($table, 0);
    }
  
  $table_id = $table['id'];
  
  if ($default==-1)
    {
      $sqlQuery = "SELECT access FROM ".ACC_TIPS_TABLE." WHERE group_id=".intval($user['group_id'])." AND table_id=".intval($table['id'])." AND tip_id=".intval($tip_id);
      $result = sql_query($sqlQuery);
      $tip_temp = sql_fetch_assoc($result);
      $default = $tip_temp['access'];
    }
  
  if ($table['rules'])
    {
      $can_do = -1;
      // Проверяем правила
      foreach ($table['rules'] as $one_rule)
       { // Перебираем правила, смотрим, есть ли доступ к полю
         foreach ($one_rule['rights'] as $one_right)
           {
             if ($one_right['tip']==$tip_id and isset($one_right['access']))
                { // Есть правило на собственно само действие
                  if (eval_php_condition($line,$one_rule['condition_php']))
                     {
                       $can_do = $one_right['access'];
                       break;
                     }
                }
            }
       }
     if ($can_do==-1) return $default;
     else return $can_do;
    }
    else return $default;
}

// тест, можно ли распечать шаблон
function test_allow_template_line($table, $line, $template_id, $default=-1)
{
  global $user;
  
  if (!is_array($line))
    {
      $line = sql_select_array(DATA_TABLE.$table['id'],'id=',$line);
    }
  
  if ($default==-1)
    {
      $sqlQuery = "SELECT a.*, b.read_acc FROM ".FORMS_TABLE." a, ".ACC_FORMS_TABLE." b WHERE a.id=b.form_id AND a.id=".$template_id." AND b.group_id=".$user['group_id'];
      $result = sql_query($sqlQuery);
      $form = sql_fetch_assoc($result);
      $default = $form['read_acc'];
    }
  
  $table_id = $table['id'];
  
  if ($table['rules'])
    {
      $can_do = -1;
      // Проверяем правила
      foreach ($table['rules'] as $one_rule)
       { // Перебираем правила, смотрим, есть ли доступ к полю
         foreach ($one_rule['rights'] as $one_right)
           {
             if ($one_right['template']==$template_id and isset($one_right['read_acc']))
                { // Есть правило на собственно само действие
                  if (eval_php_condition($line,$one_rule['condition_php']))
                     {
                       $can_do = $one_right['read_acc'];
                       break;
                     }
                }
            }
       }
     if ($can_do==-1) return $default;
     else return $can_do;
    }
    else return $default;
}

function test_allow_template_table($table, $template_id, $default)
{
  $table_id = $table['id'];
  if ($table['rules'])
    {
      $can_do = -1;
      // Проверяем правила
      foreach ($table['rules'] as $one_rule)
       { // Перебираем правила, смотрим, есть ли доступ к полю
         foreach ($one_rule['rights'] as $one_right)
           {
             if ($one_right['template']==$template_id and isset($one_right['read_acc']))
                { // Есть правило на собственно само действие
                  $can_do = $one_right['read_acc'];
                  break;
                }
            }
       }
      if ($default==0 && (!$can_do || $can_do==-1)) return 0;
      else return 1;
    }
    else return $default;
}

// тест, можно ли применять доп действие
function test_allow_button_line($table, $line, $button_id, $default)
{
  $table_id = $table['id'];
  if ($table['rules'])
    {
      $can_do = -1;
      // Проверяем правила
      foreach ($table['rules'] as $one_rule)
       { // Перебираем правила, смотрим, есть ли доступ к полю
         foreach ($one_rule['rights'] as $one_right)
           {
             if ($one_right['button']==$button_id and isset($one_right['access']))
                { // Есть правило на собственно само действие
                  if (eval_php_condition($line,$one_rule['condition_php']))
                     {
                       $can_do = $one_right['access'];
                       break;
                     }
                }
            }
       }
     if ($can_do==-1) return $default;
     else return $can_do;
    }
    else return $default;
}

function test_allow_button_table($table, $button_id, $default)
{
  $table_id = $table['id'];
  if ($table['rules'])
    {
      $can_do = -1;
      // Проверяем правила
      foreach ($table['rules'] as $one_rule)
       { // Перебираем правила, смотрим, есть ли доступ к полю
         foreach ($one_rule['rights'] as $one_right)
           {
             if ($one_right['button']==$button_id and isset($one_right['access']))
                { // Есть правило на собственно само действие
                  $can_do = $one_right['access'];
                  break;
                }
            }
       }
      if ($default==0 && !$can_do) return 0;
      else return 1;
    }
    else return $default;
}

// тест, можно ли удалять/архивировать строку
function test_allow_right($table, $line, $type)
{
  $table_id = $table['id'];
  if ($table['rules'])
    {
      $can_do = -1;
      // Проверяем правила
      foreach ($table['rules'] as $one_rule)
       { // Перебираем правила, смотрим, есть ли доступ к полю
         foreach ($one_rule['rights'] as $one_right)
           {
             if ($one_right['table']==$table_id and isset($one_right[$type.'_acc']))
                { // Есть правило на собственно само действие
                  if (eval_php_condition($line,$one_rule['condition_php']))
                     {
                       $can_do = $one_right[$type.'_acc'];
                       break;
                     }
                }
            }
       }
     if (($can_do==-1 && !$table[$type]) || !$can_do) return 0;
    }
    elseif (!$table[$type]) return 0;
  if ($type) return 1;
}

// тест, можно ли читать в таблице данное поле
function test_allow_read($field, $line=array(), $type_out="")
{
  global $user;
  if ($user['is_root']) return 1;
  // Разрешенные режимы вывода
  $allowed_type_out = array(''=>'', 'view'=>'view', 'view_tb'=>'view_tb', 'view_edit'=>'view_edit', 'view_add'=>'view_add', 'read'=>'read');
  $type_out = $allowed_type_out[$type_out];
  $line_id=$line['id'];
  $table_id = $field['table_id'];
  $table = get_table($table_id);
  $type = $field['type_field'];
  $type_value = $field['type_value'];

  $disallow_mask = array();

  foreach ($table['rules_reverse'] as $one_rule)
   {
     foreach ($one_rule['rights'] as $one_right)
       {
         if ($one_right['field']==$field['id'] && (($type_out && isset($one_right[$type_out])) || (!$type_out && (isset($one_right['view'])||isset($one_right['view_tb'])||isset($one_right['read']) ))) )
            {  // Есть правило на данное поле
               if (eval_php_condition($line,$one_rule['condition_php']))
                  {  // Условие срабатывает, возвращаем права
                     if (!$type_out)
                        { // Разрешение просмотра в строке / таблице
                          if ($one_right['view']||$one_right['view_tb']||$one_right['read'])
                               return 1;
                             elseif ($line)
                              { // Необходимо чтобы все маски были запрещены
                                if ($disallow_mask['view']&&$disallow_mask['view_tb']&&$disallow_mask['read'])
                                    return 0;
                                   else
                                   {
                                    if ($one_right['view']) $disallow_mask['view']=1;
                                    if ($one_right['view_tb']) $disallow_mask['view_tb']=1;
                                    if ($one_right['read']) $disallow_mask['read']=1;
                                   }     
                              }

                        }
                        else
                        {
                          if (isset($one_right[$type_out]))
                             {
                               if ($one_right[$type_out]) return 1; elseif ($line) return 0;
                             }
                        }
                  }
            }
       }
   }
  // Правил на поле нет, срабатывает дефолт
  if (!$type_out)
     {
       if ($field['view']||$field['view_tb']||$field['read']) return 1;
          else return 0;
     } 
     else
       return $field[$type_out]?1:0;
}

// Дополнить строку - данными из ссылок
function get_link_tables_line(&$line, $table, $ln_tables, $rc)
{
  $table_fields=$table['table_fields'];
  $link_tables=$ln_tables['sub_list'];

  if ($link_tables)
     {
         foreach ($link_tables as $link_field_id=>$link_table_id)
          {  // Перебираем используемые таблицы
            if (is_array($line[$table_fields["$link_field_id"]["int_name"]]))
               { // уже получено значение подмассива
                 if ($ln_tables[$link_field_id])
                    {
                      // перебираем дальнейшие вкладки
                      $l_tbl = get_table($link_table_id);
                      $l_field = get_table_fields($l_tbl); // Заполняем link_tables
                      get_link_tables_line($line[$table_fields["$link_field_id"]["int_name"]], $l_tbl, $ln_tables[$link_field_id], $rc+1);
                    }
               }
               else
               { // получаем значение подмассива
                 if ($line[$table_fields["$link_field_id"]["int_name"]]>0) // Разворачиваем только реальные ссылки
                    {
                       $sqlQuery = "SELECT *  FROM ".DATA_TABLE.$link_table_id." WHERE id='".$line[$table_fields["$link_field_id"]["int_name"]]."'";
                       $result = sql_query($sqlQuery);
                       $raw_value=$line[$table_fields["$link_field_id"]["int_name"]];

                       $raw_value=$line[$table_fields["$link_field_id"]["int_name"]];
                       $line[$table_fields["$link_field_id"]["int_name"]] = sql_fetch_assoc($result);
                       $line[$table_fields["$link_field_id"]["int_name"]]['raw']=$raw_value;
                       if ($ln_tables[$link_field_id])
                          {
                            // перебираем дальнейшие вкладки
                            $l_tbl = get_table($link_table_id);
                            $l_field = get_table_fields($l_tbl); // Заполняем link_tables
                            get_link_tables_line($line[$table_fields["$link_field_id"]["int_name"]], $l_tbl, $ln_tables[$link_field_id], $rc+1);
                          }
                    }
                    else
                    {
                       $line[$table_fields["$link_field_id"]["int_name"]] = array();
                    }

               }
            
          }
     }
}

// Формируем события на изменившиеся строки
function form_event_recurs($table, &$line, $tmp_old_line, $calc_id, & $start_calc)
{
  global $calc_fields_changes;
  $table_id=$table['id'];
  $table_fields=get_table_fields($table);
  $line_id=$tmp_old_line['id'];
  $event=array('type'=>'calc', 'table_id'=>$table_id, 'line_id'=>$line_id, 'changed'=>array(), 'calc_id'=>$calc_id);
  foreach($line as $k=>$one_value)
    {
      if ($tmp_old_line[$k]!=$one_value)
         {
            $field_id=$table["int_names"][$k];
            $t_field=$table_fields[$field_id];
            if (is_array($one_value) && $one_value['id']) // Доп защита по id от некоректных строк $line
               { // изменился подмассив, на каждую отдельную таблицу генерируется свое отдельное событие
                 if ($tmp_old_line[$k]['raw']!=$one_value['raw'])
                    { // Исходное значение поля ссылки
                      $one_value=$one_value['raw'];
                      data_update($table_id, array($k=>$one_value), 'id=',$line_id);
                      // также фиксируем в истории изменений значений
                      $calc_fields_changes[$table_id][$field_id][$line_id]=$line;
                      $event["changed"][$field_id]=array("field_id"=>$field_id, "int_name"=>$table_fields[$k]['int_name'], "old"=>$tmp_old_line[$k]['raw'], "new"=>$one_value);
                    }
                    else
                    { // Значения в подтаблице
                      $s_table = get_table($t_field['s_table_id']);
                      get_table_fields($s_table);
                      $s_line=$one_value;
                      $s_tmp_old_line=$tmp_old_line[$k];
                      form_event_recurs($s_table, $s_line, $s_tmp_old_line, $calc_id, $start_calc);
                    }
               }
               else
            if (is_array($tmp_old_line[$k]) && $tmp_old_line['id']) // Доп защита по id от некоректных строк $line
               {
                 if ($tmp_old_line[$k]['raw']!=$one_value)
                    { // Исходное значение поля ссылки
                      data_update($table_id, array($k=>$one_value), 'id=',$line_id);
                      // также фиксируем в истории изменений значений
                      $calc_fields_changes[$table_id][$field_id][$line_id]=$line;
                      $event["changed"][$field_id]=array("field_id"=>$field_id, "int_name"=>$table_fields[$k]['int_name'], "old"=>$tmp_old_line[$k]['raw'], "new"=>$one_value);
                    }
               }
               else
               {
                  // изменилось значение - сохраняем его в базе
                  data_update($table_id, array($k=>$one_value), 'id=',$line_id);
                  // также фиксируем в истории изменений значений
                  if ($k!='r' and $k!='u')
                     {
                       $calc_fields_changes[$table_id][$field_id][$line_id]=$line;
                       $event["changed"][$field_id]=array("field_id"=>$field_id, "int_name"=>$table_fields[$k]['int_name'], "old"=>$tmp_old_line[$k], "new"=>$one_value);
                     }
               }
         }
    }
  if ($event["changed"])
     {
       // Поле изменилось, генерируем новое событие
       $start_calc[]=array('table'=>$table, 'line'=>$line, 'event'=>$event);
     };
};

// Произвести вычисления над строкой в таблице
// Таблица в которой происходит вычисление
// Строка
// Само вычисление
// Событие которе вызвало вычисление
function calc_line($table,& $line, $calc, $event)
{
  global $config, $user, $smarty, $lang, $ses_id, $button_id, $output, $event_cancel, $event_post_insert, $show_sql_request, $csrf, $calc_stack, $bm_errors;
  $line_id  = $line['id'];
  $calc['line_id'] = $line_id;
  // Проверка рекурсивного вызова
  if ($calc['recursion_disabled'] && $calc_stack)
    {
      foreach ($calc_stack AS $one_calc)
          if ($calc['id'] == $one_calc['id'] && $one_calc['line_id'] == $line_id)
              return;
    }
  // Вычисления в поле
  $table_id = $table['id'];
  $table_fields=$table['table_fields'];
  // Запоминаем старые параметры строки
  $start_values=$line;
  get_link_tables_line($line, $table, $calc['link_fields'], 1);
  $tmp_old_line=$line;
  $ID = $line['id'];
  $calculate=$calc['calculate'];
  if ($calc["old_format"])
     {
       $calculate=str_replace("{ID}",$ID,$calculate);
     }
  $calc_errors = array();
  $calc['table_name']=$table['name_table'];
  if (!is_array($calc_stack)) $calc_stack=array();
  array_push($calc_stack, $calc);

  $bm_errors->start_calc();
  eval($calculate);
  $bm_errors->stop_calc();

  // Сохраняем ошибки вычислений текущей строки в сессии
  foreach ($calc_errors as $one_error) $_SESSION[$ses_id]['calc_errors'][$line_id][] = $one_error;

  if (!$table['view_sql'])
     {
        // Сохраняем изменения в базе
        $start_calc = array();
        form_event_recurs($table, $line, $tmp_old_line, $calc['id'], $start_calc);
        // Если необходимо генерируем новые события
        foreach ($start_calc as $one_calc)
          {
            popup_event($one_calc['table'], $one_calc['line'], $one_calc['event']);
          }
     }
  array_pop($calc);
}

// Вычисление переменной в шаблоне печати
function calc_form_var($calc)
{
   global $config, $user, $smarty, $lang, $ses_id, $csrf, $xls_ext;
   $line_id = $eval_line_id;
   return @eval($calc);
}

// Получить список необходимых вычислений, изходя из mysql запроса выбирающего вычисления
function get_calcs($sqlQuery, &$calcs)
{
  global $calc_cache_flag, $calcs_cache;
  if ($calc_cache_flag)
     {
        if (isset($calcs_cache[$sqlQuery]))
           { // Есть в кеше
             $calcs=array_merge($calcs,$calcs_cache[$sqlQuery]);
           }
           else
           {
              $calcs_add = array();
              $result = sql_query($sqlQuery);
              while ($cond_l = sql_fetch_assoc($result))
                    {
                      if (!$calcs_add[$cond_l['id']])
                         { // если вычисление, не заполнено в массив, добавляем его.
                           $calc['id']=$cond_l['id'];
                           $calc['table_id']=$cond_l['table_id'];
                           $calc['name']=$cond_l['name'];
                           $calc['calculate']=$cond_l['calculate'];
                           $calc['old_format']=$cond_l['old_format'];
                           $calc['link_fields']=unserialize($cond_l['link_fields']);
                           $calc['recursion_disabled'] = $cond_l['recursion_disabled'];
                           $calcs_add[$calc['id']]=$calc;
                         }
                      $cond['type']=$cond_l['cond_type'];
                      $cond['param']=$cond_l['cond_param'];
                      $cond['param2']=$cond_l['cond_param2'];
                      $calcs_add[$cond_l['id']]['conditions'][]=$cond;
                    }
              $calcs_cache[$sqlQuery]=$calcs_add;
              $calcs=array_merge($calcs,$calcs_add);
           }
     }
     else
     {
        $result = sql_query($sqlQuery);
        while ($cond_l = sql_fetch_assoc($result))
              {
                  if (!$calcs[$cond_l['id']])
                     { // если вычисление, не заполнено в массив, добавляем его.
                       $calc['id']=$cond_l['id'];
                       $calc['table_id']=$cond_l['table_id'];
                       $calc['name']=$cond_l['name'];
                       $calc['calculate']=$cond_l['calculate'];
                       $calc['old_format']=$cond_l['old_format'];
                       $calc['link_fields']=unserialize($cond_l['link_fields']);
                       $calc['recursion_disabled'] = $cond_l['recursion_disabled'];
                       $calcs[$calc['id']]=$calc;
                     }
                  $cond['type']=$cond_l['cond_type'];
                  $cond['param']=$cond_l['cond_param'];
                  $cond['param2']=$cond_l['cond_param2'];
                  $calcs[$cond_l['id']]['conditions'][]=$cond;
              }
     }
}

// Функция 
// Выполняет всплытие безтабличного сообщения
function popup_event_g($event)
{
  global $config, $user, $smarty, $lang, $ses_id, $csrf;
  $calcs = array();
   if ($event['type']=='login')
      {
        $sqlQuery = "SELECT a.type as cond_type, a.param as cond_param, a.param2 as cond_param2, b.* FROM ".CALC_COND_TABLE. " a, ".CALC_TABLE." b WHERE a.type='".EVT_USER_LOGIN."' and a.calc_id=b.id and b.table_id='0' and b.disabled=0";
        get_calcs($sqlQuery, $calcs);
      }
   if ($event['type']=='user_change')
      {
        $sqlQuery = "SELECT a.type as cond_type, a.param as cond_param, a.param2 as cond_param2, b.* FROM ".CALC_COND_TABLE. " a, ".CALC_TABLE." b WHERE a.type='".EVT_USER_CHANGE."' and a.calc_id=b.id and b.table_id='0' and b.disabled=0";
        get_calcs($sqlQuery, $calcs);
      }
  foreach ($calcs as $calc)
    {
      eval($calc['calculate']);
    }
}

// Выполняет всплытие табличного сообщения
function popup_event($table, & $line, $event)
{
  global $calc_fields_changes, $event_recurs_level, $config, $lang, $user, $sync_exp_list, $sync_exp_fields, $sync_exp_rfields, $sync_exp_data, $sync_exp_rtables;
  $table_fields = get_table_fields($table); // В случае со старыми вычислениями, поля таблицы не загружены
  $table_id = $table['id'];
  $line_id = $line['id'];

  if ($event_recurs_level>100)
    {
      generate_error("event_recurs_level > 100"); // Произошло зацикливание
      exit;
    }
  $event_recurs_level++;

  $calcs = array();

  if ($event['type']=='save' || $event['type2']=='save')
    { // Событие сохранение строки
      $sqlQuery = "SELECT a.type as cond_type, a.param as cond_param, a.param2 as cond_param2, b.* FROM ".CALC_COND_TABLE. " a, ".CALC_TABLE." b WHERE a.type='".EVT_LINE_SAVE."' and a.calc_id=b.id and b.table_id='$table_id' and b.disabled=0";
      get_calcs($sqlQuery, $calcs);
    }

  if ($event['type']=='restore' || $event['type2']=='restore')
  { // Событие восстановления строки
    $sqlQuery = "SELECT a.type as cond_type, a.param as cond_param, a.param2 as cond_param2, b.* FROM ".CALC_COND_TABLE. " a, ".CALC_TABLE." b WHERE a.type='".EVT_LINE_RESTORE."' and a.calc_id=b.id and b.table_id='$table_id' and b.disabled=0";
    get_calcs($sqlQuery, $calcs);
  }

  if ($event['type']=='view' || $event['type2']=='view')
    { // Событие просмотр строки
      $sqlQuery = "SELECT a.type as cond_type, a.param as cond_param, a.param2 as cond_param2, b.* FROM ".CALC_COND_TABLE. " a, ".CALC_TABLE." b WHERE a.type='".EVT_FIELD_SHOW."' and a.calc_id=b.id and b.table_id='$table_id' and b.disabled=0";
      get_calcs($sqlQuery, $calcs);
    }

  if ($event['type']=='delete' || $event['type2']=='delete')
    { // Событие удаление строки
      $sqlQuery = "SELECT a.type as cond_type, a.param as cond_param, a.param2 as cond_param2, b.* FROM ".CALC_COND_TABLE. " a, ".CALC_TABLE." b WHERE a.type='".EVT_LINE_DROP."' and a.calc_id=b.id and b.table_id='$table_id' and b.disabled=0";
      get_calcs($sqlQuery, $calcs);
      if ($config['event_on']['delete_record'])
        {
          $name_record = get_name_record($table, $table_fields, $line);
          $mess = $lang['User'].' "'.$user['fio'].'" '.$lang["delete_record_p0"]."<a href='view_line".$config["vlm"].".php?table=$table_id&line=$line_id'>".$name_record."</a>".$lang["delete_record_p1"].$table['name_table'].$lang["delete_record_p2"];
          insert_log("delete_record", $mess, $table_id, $line_id);
        }
    }

  if ($event['type']=='import' || $event['type2']=='import')
    { // Событие импорт строки
      $sqlQuery = "SELECT a.type as cond_type, a.param as cond_param, a.param2 as cond_param2, b.* FROM ".CALC_COND_TABLE. " a, ".CALC_TABLE." b WHERE a.type='".EVT_TABLE_IMPORT."' and a.calc_id=b.id and b.table_id='$table_id' and b.disabled=0";
      get_calcs($sqlQuery, $calcs);
      if ($config['event_on']['import'])
        {
          $name_record = get_name_record($table, $table_fields, $line);
          $mess = $lang['User'].' "'.$user['fio'].'" '.$lang["import_p0"]."<a href='view_line".$config["vlm"].".php?table=$table_id&line=$line_id'>".$name_record."</a>".$lang["import_p1"].$table['name_table'].$lang["import_p2"];
          insert_log("import", $mess, $table_id, $line_id);
        }
    }

  if ($event['type']=='questionare' || $event['type2']=='questionare')
     { // Событие удаление строки
       $sqlQuery = "SELECT a.type as cond_type, a.param as cond_param, a.param2 as cond_param2, b.* FROM ".CALC_COND_TABLE. " a, ".CALC_TABLE." b WHERE a.type='".EVT_NEW_QST."' and a.param='".$event['param']."' and a.calc_id=b.id and b.table_id='$table_id' and b.disabled=0 LIMIT 1";
       get_calcs($sqlQuery, $calcs);
     }

  if ($event['changed'])
    { // Есть измененые поля, выполняем вычисления связанные с изменением полей
      foreach ($event['changed'] as $one_change)
        {
          $field_id=$one_change['field_id'];
          $type_field=$table['table_fields'][$field_id]['type_field'];
          $sqlQuery = "SELECT a.type as cond_type, a.param as cond_param, a.param2 as cond_param2, b.* FROM ".CALC_COND_TABLE. " a, ".CALC_TABLE." b WHERE a.param='$field_id' and a.type=3 and a.calc_id=b.id and b.disabled=0";
          get_calcs($sqlQuery, $calcs); 
          if ($event["type"]=='calc') $event_type = "change_calc_field"; else $event_type = "change_field";

          if (($config['event_on'][$event_type] and !$event["is_new_line"] and $type_field!=6 and $type_field!=9)||($sync_exp_fields[$field_id]))
            { // не пишем в лог изменения полей при добавлении новой записи и изменения полей с файлами (отдельно логируемые события)
              $t_field=$table['table_fields'][$one_change['field_id']];
              $t_line=$line;
              $t_line[$t_field["int_name"]]=$one_change['old'];
              $old_v=form_display_type($t_field, $t_line, 'text');
              $t_line[$t_field["int_name"]]=$one_change['new'];
              $new_v=form_display_type($t_field, $t_line, 'text');
              if ($old_v=="0") $old_v="";
              if ($new_v=="0") $new_v="";
              if ($old_v!=$new_v)
                {
                  $old_v = htmlSubstrTrig($old_v,100);
                  $new_v = htmlSubstrTrig($new_v,100);
                  // Синхронизация
                  if ($sync_exp_fields[$field_id])
                     {
                        foreach ($sync_exp_fields[$field_id] as $sync_id=>$sync_field_info)
                          {
                            if ($sync_id == $event['sync_id']) continue; // Событие вызвано текущей синхронизацией, пропускаем
                            if ($line['s'.$sync_id] == "") continue; // Запись уже была добавлена, но не системно, пропускаем, т.к. добавление произойдёт в flush_sync()
                            if ($line['status'] == 3 OR $line['status'] == 4) continue; // Пропускаем строки со статусом 3 или 4

                            $sync_new_line = false;
                            // Определяем идентификатор выгружаемой строки
                            if ($sync_exp_list[$sync_id]['sync_mode'] AND $line['s'.$sync_id] != "S")
                                $sync_line_id = $line['s'.$sync_id];
                            elseif (!$sync_exp_list[$sync_id]['sync_mode'] AND $line['s'.$sync_id] != "S")
                                $sync_line_id = $line_id;
                            else
                              {
                                $sync_line_id = "^".$line_id;
                                $sync_new_line = true;
                              }

                            if ($type_field==5)
                              { // Поле связи
                                $link_field_data = data_select_array($t_field['s_table_id'], "id=",$one_change['new']);
                                if (!$link_field_data) continue;

                                if ($sync_exp_list[$sync_id]['tables'][$t_field["s_table_id"]] OR $sync_exp_rtables[$t_field["s_table_id"]][$sync_id]) // Целевая таблица также синхронизируется
                                  {
                                    if ($link_field_data["s".$sync_id] == "S")
                                      { // Целевая строка не засинхронизирована
                                        $cache_res = sql_select_field(SYNC_CACHE_TABLE, "id", "sync_id=",$sync_id," and field_id=",$t_field['s_field_id']," and line_id=",$one_change['new']);
                                        if (!sql_fetch_assoc($cache_res)) // В кэше на выгрузку записи тоже нет, убираем S для того, чтобы строка добавилась в список для синхронизации
                                            data_update($t_field['s_table_id'], array("s".$sync_id=>""), "id=",$one_change['new']);
                                        // Добавляем текущую строку в кэш для выгрузки
                                        sql_insert(SYNC_CACHE_TABLE, array("sync_id"=>$sync_id, "table_id"=>$table_id, "field_id"=>$field_id, "line_id"=>$line_id, "value"=>"^".$one_change['new'], "upload_count"=>1, "last_upload"=>date("Y-m-d H:i:s")));
                                      }
                                    else // Целевая запись засинхронизирована, выгружаем поле связи
                                      {
                                        if ($sync_exp_list[$sync_id]['sync_mode']) // Активный режим
                                            $link_export_data = $link_field_data["s".$sync_id];
                                        else // Пассивный режим
                                            $link_export_data = $link_field_data["id"];
                                        $sync_exp_data[$sync_field_info['sync_id']][$field_id][$sync_line_id]=$link_export_data;
                                        if ($sync_new_line) // Строка новая, добавляем в кэш
                                            sql_insert(SYNC_CACHE_TABLE, array("sync_id"=>$sync_id, "table_id"=>$table_id, "field_id"=>$field_id, "line_id"=>$line_id, "value"=>$link_export_data, "upload_count"=>1, "last_upload"=>date("Y-m-d H:i:s")));
                                      }
                                  }
                                else
                                  { // Целевое поле связи не синхронизируется, выгружаем текст
                                    $link_field_intname = form_int_name($t_field['s_field_id']);
                                    $sync_exp_data[$sync_field_info['sync_id']][$field_id][$sync_line_id]=$link_field_data[$link_field_intname];
                                    if ($sync_new_line) // Строка новая, добавляем в кэш
                                        sql_insert(SYNC_CACHE_TABLE, array("sync_id"=>$sync_id, "table_id"=>$table_id, "field_id"=>$field_id, "line_id"=>$line_id, "value"=>$link_field_data[$link_field_intname], "upload_count"=>1, "last_upload"=>date("Y-m-d H:i:s")));
                                  }
                              }
                            else
                              { // Обычное поле, добавляем сразу
                                $sync_exp_data[$sync_field_info['sync_id']][$field_id][$sync_line_id]=$one_change['new'];
                                if ($sync_new_line) // Строка новая, добавляем в кэш
                                    sql_insert(SYNC_CACHE_TABLE, array("sync_id"=>$sync_id, "table_id"=>$table_id, "field_id"=>$field_id, "line_id"=>$line_id, "value"=>$one_change['new'], "upload_count"=>1, "last_upload"=>date("Y-m-d H:i:s")));
                              }
                          }
                     }
                  // Логи
                  if ($config['event_on'][$event_type] and !$event["is_new_line"] and $type_field!=6 and $type_field!=9)
                     {
                        $name_record = get_name_record($table, $table_fields, $line);
                        if ($event["type"]=='calc')
                          {
                            global $calc_names_cache;
                            if (!$calc_names_cache[$event['calc_id']])
                                {
                                  $sqlQuery = "SELECT name FROM ".CALC_TABLE." WHERE id='".$event['calc_id']."'";
                                  $result = sql_query($sqlQuery);
                                  $cl_info = sql_fetch_assoc($result);
                                  $calc_names_cache[$event['calc_id']]=$cl_info['name'];
                                }
                            $calc_name=$calc_names_cache[$event['calc_id']];
                            $mess = $lang['Calc'].' "'.$calc_name.'" '.$lang['change_record_p00'].$t_field['name_field'].$lang['change_record_p1'].$table['name_table'].$lang['change_record_p2']."<a href='view_line".$config["vlm"].".php?table=$table_id&line=$line_id'>".$name_record."</a>".$lang['change_record_p3'].strip_tags($old_v).$lang['change_record_p4'].strip_tags($new_v).$lang['change_record_p5'];
                          }
                          else
                          {
                            $mess = $lang['User'].' "'.$user['fio'].'" '.$lang['change_record_p0'].$t_field['name_field'].$lang['change_record_p1'].$table['name_table'].$lang['change_record_p2']."<a href='view_line".$config["vlm"].".php?table=$table_id&line=$line_id'>".$name_record."</a>".$lang['change_record_p3'].strip_tags($old_v).$lang['change_record_p4'].strip_tags($new_v).$lang['change_record_p5'];
                          }
                        insert_log($event_type, $mess, $table_id, $line_id);
                     }
                }
            }
        }
    }

  foreach ($calcs as $calc)
    {
      calc_line($table, $line, $calc, $event);
    }

  $event_recurs_level--;
}

// Сформировать значение ячейки для отображения в таблице
// $field - описание поля
// $line - строка содержащая необходимое поле, является результатом выборки из таблицы
// $type_out - тип вывода
//    view - просмотр записи
//    text - текстовое значение
// $recursive - служебное поле, указывать нет необходимости
function form_display_type($field, &$line, $type_out="view", $recursive=0)     //при выводе данных
{
  global $config, $user, $lang, $tables_cache;

  $test_read=test_allow_read($field, $line, $type_out);
  if (!$test_read) return false;

  $line_id=$line['id'];
  $value = $line[$field['int_name']];
  $table_id = $field['table_id'];
  $table = get_table($table_id);

  $type = $field['type_field'];
  $type_value = $field['type_value'];

  if ($type==1 or $type==10)  //тип поля число
    {
      // прибавляем нули слева, в случае необходимости ("минимальное число выводимых цифр")
      $dig_count = strlen(number_format($value, $field['dec_dig'], "", ""));
      if ($dig_count<$field['min_dig']) $value = str_repeat("9",$field['min_dig']-$dig_count).$value; // вставляем временно 9 вместо 0, для успешного форматирования
      // форматируем число в локальном формате
      if (($type_out=="view_tb")||($type_out=="view")) $new_value = form_local_number($value, $field['dec_dig']);
         elseif ($type_out=="text")  $new_value = form_local_number($value, $field['dec_dig']);
         elseif ($type_out=="export")  $new_value = form_local_number($value, $field['dec_dig'], ""); // отключаем разделитель (разделитель - пустая строка) при экспорте
         else $new_value = form_local_number($value, $field['dec_dig']);
      // завершающая обработка
      for ($i=0; $i<$field['min_dig']-$dig_count; $i++) if ($new_value[$i]==9) $new_value[$i] = 0; else $dig_count--; // заменяем 9 на 0
    }
  elseif ($type==2 or $type==12)  //тип поля дата
    {
      $new_value = form_local_time($value, $type_value);
    }
  elseif ($type==3)   //тип поля текст
    {
      if ($field["hyperlink"] and ($type_out=="view"||$type_out=="view_tb"))
         {
            if ($field["mult_value"])
               { // многострочное
                  $sites = explode("\n", $value);
                  $new_value="";
                  foreach ($sites as $one_site)
                     {
                       $new_value.=form_hyperlink($one_site)."<br>";
                     }
                  $new_value=substr($new_value,0,-4);
               }
               else
                $new_value=form_hyperlink($value);
         }
         else
      if ((!$field['view_html'])&&($type_out!="text")&&($type_out!="export")&&($type_out!="tips")) $new_value = form_display($value);
         else $new_value = $value;
    }
  elseif ($type==4)   //тип поля список
    {
      $values = explode("\r\n", $value);
      foreach ($values as $value) $new_values[] = $lang['conf'][$value]?$lang['conf'][$value]:$value;
      $new_value = implode("\r\n", $new_values);
    }
  elseif ($type==5)   //тип поля связь
    {
      if ($type_out!="search")
        {
          $table_id = $field['s_table_id'];
          $field_id = $field['s_field_id'];
          $filter_id = $field['s_filter_id'];
          $show_field_name = $field['s_show_field_name'];
          $show_field_inline = $field['s_show_field_inline'];

          $s_table_field  = get_table($table_id);
          $display_fields = get_table_fields($s_table_field);
          $display_field  = $display_fields[$field_id];

          if (is_array($value))
             { // Значение уже заполнено, т.к. использовалось в вычислениях
               $value=$value['raw'];
               $sub_line = data_select_array($table_id,"id=",$value);
             }     
             else
             {
               if (!$value) return '';
               $sub_line = data_select_array($table_id,"id=",$value);
               $sub_line['raw']=$line[$field['int_name']];
               $line[$field['int_name']]=$sub_line;
             }
          if ($sub_line)
             {
              $display_value = $sub_line[$s_table_field['int_names'][$field_id]];
              if ($type_out=="view_tb")
                  $type_out="view";
              else $field['disable_link'] = 0;
              
              if (($type_out=="" || $type_out=="view" || $type_out=="html") && $display_field['type_field']==9) $display_value = form_display_type($display_field, $sub_line, "tips", 1);
                elseif ($type_out=="text" or $type_out=="export") $display_value = form_display_type($display_field, $sub_line, "text", 1);
                else $display_value = form_display_type($display_field, $sub_line, "", 1);
              if ($display_value===false) $display_value=$lang["No_access"];
              if (($type_out=="edit" or $type_out=="html" or $type_out=="text" or $type_out=="tips")&&!$show_field_inline)
                {
                    // Если редактируем или выводим в шаблон/напоминания, то не вычисляем дополнительные поля
                }
                else
                {
                    $d_table=get_table($table_id);
                    $d_fields = get_table_fields($d_table);
                    foreach ($field['links_also_show'] as $also_show)
                      {
                          $d_field = $d_fields[$also_show];
                          if ((!$show_field_inline)&&($type_out=="view")&&(!$recursive)) $add_info.="<br>";
                              else $add_info.=" ";
                          if ($show_field_name) $add_info.=$d_field["name_field"].": ";
                          if ($type_out=="text" or $type_out=="export") $dsp_val=form_display_type($d_field, $sub_line, "text", 1);
                              else $dsp_val=form_display_type($d_field, $sub_line, "", 1);

                          if ($dsp_val===false) $dsp_val=$lang["No_access"];
                          $add_info.=$dsp_val;
                      }
                }        
             
              if ($type_out=="view" and !$recursive and $s_table_field['view_lnk'] and !$field['disable_link'])
                {
                  global $base64_current_url;
                  $new_value = "<a href='view_line".$config['vlm'].".php?table=$table_id&line=$value&back_url=$base64_current_url'>".$display_value;
                  if ((!$show_field_inline)&&($add_info))
                        $new_value.="</a><span class='show_field_slave'>".$add_info."</span>";
                    else
                        $new_value.=$add_info."</a>";
                }
                else
                {
                  $new_value = $display_value.$add_info;                  
                }
                
            }
        }
        else
        {
          $new_value = $value;
        }
    }
  elseif ($type==6)   //тип поля файл
    {
      if ($type_out=="view"||$type_out=="view_tb")
        {
          $files = $value?explode("\r\n", $value):array();
          foreach ($files as $i => $file)
            {
              $files[$i] = "<a href='open_file.php?table=".$field['table_id']."&field=".$field['id']."&line=".$line_id."&file=".urlencode($file)."'>".form_display($file)."</a>";
            }
          $new_value = implode("\r\n", $files);
        }
        else
        {
          $new_value = $value;
        }
    }
  elseif ($type==7 or $type==11)   //тип поля пользователь
    {
      $value = explode("-",trim($value,"-"));
      global $users_list_cache;
      if (!$users_list_cache)
         {
            $users_list_cache=array();
            $sqlQuery = "SELECT * FROM ".USERS_TABLE;
            $result = sql_query($sqlQuery);
            while ($row = sql_fetch_assoc($result))
              {
                $users_list_cache[] = $row;
              }
         }
      $users=array();
      foreach ($users_list_cache as $row)
         {
           if (in_array($row['id'], $value)) $users[] = $row['fio'];
         }
      if (in_array("{current}", $value)) $users[] = $user['fio'];
      if (count($users))
        $new_value = implode("\r\n", $users);
      elseif ($value[0] and ($type_out=="view" or $type_out=="view_tb"))
        $new_value = "<span style='color:#afafaf'>".$lang['deleted_u']."</span>";
      elseif ($value[0])
        $new_value = $lang['deleted_u'];
      else
        $new_value = "";
    }
  elseif ($type==9)   //тип поля изображение
    {
      if ($type_out=="view" or $type_out=="view_tb" or $type_out=="html" or $type_out=="inline" or $type_out=="embed" or $type_out=="tips" or $type_out=="pdf")
        {
          $files = $value?explode("\r\n", $value):array();
          foreach ($files as $i => $file)
            {
              $fname = get_file_path($field['id'], $line_id, $file);
              if (!file_exists($fname))
                 {
                   $files[$i]=$file." (".$lang['f_not_exits'].")";
                   continue;
                 }

              $content = file_get_contents($fname);
              list($w1, $h1) = getimagesize($fname);
              $w2=$field['img_size_x'];$h2=$field['img_size_y'];

              if (!file_exists($config["site_path"]."/cache/".$field['table_id']."_".$field['id']."_".$line_id."_".utf2eng($file).".png")) image_preview($content, $w1, $h1, $w2, $h2, $field['table_id']."_".$field['id']."_".$line_id."_".utf2eng($file).".png");

              if ($type_out=="view" or $type_out=="view_tb") $files[$i] = "<a href='open_file.php?field=".$field['id']."&line=".$line_id."&file=".urlencode($file)."&show=1' onclick=\"image_window=window.open('open_file.php?field=".$field['id']."&line=".$line_id."&file=".urlencode($file)."&show=1','','width=".($w1+50).",height=".($h1+50).",menubar=1,scrollbars=1,resizable=1,status=1');image_window.focus();return false;\"><img src='cache/".$field['table_id']."_".$field['id']."_".$line_id."_".utf2eng($file).".png'></a>";
              if ($type_out=="tips") $files[$i] = "<img src='cache/".$field['table_id']."_".$field['id']."_".$line_id."_".utf2eng($file).".png'>";
              if ($type_out=="html") $files[$i] = "<img src='open_file.php?field=".$field['id']."&line=".$line_id."&file=".urlencode($file)."&show=1'>";
              if ($type_out=="inline") $files[$i] = "<img src='cid:".$field['id']."_".$line_id."_".utf2eng($file)."'>";
              if ($type_out=="embed") $files[$i] = "<img src='data:image/gif;base64,".base64_encode($content)."'>";
              if ($type_out=="pdf") $files[$i] = "<img src='$fname'>";
            }
          $new_value = implode(" ", $files);
        }
        else
        {
          $new_value = $value;
        }
    }
  elseif ($type==13)   //тип поля статус
    {
      if ($value==0) $new_value = $lang['active'];
      if ($value==1) $new_value = $lang['archive'];
      if ($value==2) $new_value = $lang['deleted'];
    }
  elseif ($type==14)   //тип поля группа
    {
      $value = explode("-", $value);
      global $groups_list_cache;
      if (!$groups_list_cache)
         {
            $groups_list_cache=array();
            $sqlQuery = "SELECT * FROM ".GROUPS_TABLE;
            $result = sql_query($sqlQuery);
            while ($row = sql_fetch_assoc($result))
              {
                $groups_list_cache[] = $row;
              }
         }
      $groups=array();
      foreach ($groups_list_cache as $row)
         {
           if (in_array($row['id'], $value)) $groups[] = $row['name'];
         }
      if (in_array("{current_group}", $value)) $groups[] = $group['name'];
      if (count($groups))
        $new_value = implode("\r\n", $groups);
      elseif ($value[0] and ($type_out=="view" or $type_out=="view_tb"))
        $new_value = "<span style='color:#afafaf'>".$lang['deleted_g']."</span>";
      elseif ($value[0])
        $new_value = $lang['deleted_g'];
      else
        $new_value = "";
    }
  else
    {
      die("Error: function 'form_display_type' - Unknown field type ($type)");
    }

  // if ($type_out=="edit") $new_value = form_display($new_value); Т.к. получается двойная обработка в полях связи
  if (($type_out=="view" or $type_out=="view_tb" or $type_out=="html" or $type_out=="tips")&&(!$field['view_html']))
    {
      $new_value = str_replace("\r", "", $new_value);
      $new_value = str_replace("\n", "<br>", $new_value);
    }
  if ($type_out=="export") $new_value = str_replace("\r\n", "\n", $new_value);
  return $new_value;
}

// Режим быстрого редактирования
// $one_field - поле по которому формируется быстрое значение
// $line - строка из б.д.
// $one_value - возвращаемый параметр
// $handler - префикс функции обработчика
// В случае невозможности установки быстрого значения, $one_value не меняется.
function form_fast_edit_value($one_field, $line,& $one_value, $part="", $subtable_id=0)
{
  global $tabindex_fast_edit, $lang, $config, $csrf;
  if ($line["id"]==-1) $line["id"]="_undefined_line_id_";
  $st_id=$one_field["table_id"];
  $sf_id=$one_field["id"]; $sl_id=$line["id"];
  $sf_idp=$sf_id."_"; $sl_idp=$sl_id."_";
  $sf_ids = "";
  if ($subtable_id) $sf_ids = "_".$subtable_id;
  $tabindexp1_fast_edit=$tabindex_fast_edit;
  if ($part=="add_link_field")
     {
        $adds_class="fast_edit_bordered "; // При добавлении в поле связи - неактивный бордюр виден всегда
        $adds_part="\r\npart=$part";
     }
     else $adds_class=""; // Подтаблицы

  if ($one_field["type_field"]==2 || $one_field["type_field"]==12)
     { // ------------- Дата и время ------------
       if ($line["id"]!="_undefined_line_id_") $adds_class="datepicker ".$adds_class;
                                         else  $adds_class="undefined_datepicker_class ".$adds_class;
       if ($one_field["type_value"]) $input_lenght=19;
                                else $input_lenght=10;
       $input_value = form_input_type($one_field, $line, $one_field['type_out']);
       if ($input_value!==false)
          {
            $input_value = $input_value["input"];
            $one_value["value"]=<<<EOD
<input id="fast_edit_span_$sf_idp$sl_id$sf_ids" type=text $adds_part
SIZE=$input_lenght
MAXLENGTH=$input_lenght
class='fast_edit_span_$sf_idp$sl_id $adds_class'
tabindex="$tabindex_fast_edit"
value='$input_value'
field_id="$sf_id"
line_id="$sl_id"
subtable_id="$subtable_id"
>
EOD;
            $onload_script="addHandler_date(document.getElementById('fast_edit_span_$sf_idp$sl_id$sf_ids'));\n";
          }
       $one_value["fast_edit_div"]="<span class='datepicker_span'>";
       $one_value["fast_edit_div_close"]="</span><script>$onload_script</script>";
     }
  elseif ($one_field["type_field"]==1 || $one_field["type_field"]==8 || $one_field["type_field"]==10 || $one_field["type_field"]==3)
      { // -------------- Обычный текст --------------
        if ($one_field['width']) $adds_style = "\nstyle='width:".$one_field['width']."px;'";
        if ($one_field['mult_value']) $mult_value=1;
                              else    $mult_value=0;
        
        $one_value["fast_edit_div"]=<<<EOD
<span tabindex="$tabindex_fast_edit" onfocus="var t=this.nextSibling; t.contentEditable=true; t.focus();"></span><div $adds_part
id="fast_edit_span_$sf_idp$sl_id$sf_ids"
tabindex="$tabindexp1_fast_edit"
contentEditable=false
class="fast_edit_text fast_edit_span_$sf_idp$sl_id $adds_class" $adds_style
field_id="$sf_id"
line_id="$sl_id"
mult_value="$mult_value";
subtable_id="$subtable_id"
>
EOD;
        $onload_script="addHandler_text(document.getElementById('fast_edit_span_$sf_idp$sl_id$sf_ids'));\n";
        $one_value["fast_edit_div_full_value"]=$one_value["fast_edit_div"];
        $one_value["fast_edit_div_close"]="</div><script>$onload_script</script>";
        $input_value = form_input_type($one_field, $line, $one_field['type_out']);
        if ($one_value["value"]===0 || $one_value["value"]==='' || (!isset($one_value["value"]) && $part=="add_link_field")) $one_value["value"]=$input_value['value'];
      }
  if ((($one_field["type_field"]==4)||($one_field["type_field"]==13))||
      (($one_field["type_field"]==7)||($one_field["type_field"]==11))||
      (($one_field["type_field"]==14)))
      { // --------------  Список и пользователь --------------
        if ($one_field['width'] && $part!="add_link_field") $adds_style = "\nstyle='width:".$one_field['width']."px;'";
        $one_field['form_fast_edit_flag']=1;
        $input_value = form_input_type($one_field, $line, $one_field['type_out']);
        if ($input_value!==false)
          {
            $input_value = $input_value["input"];
            if (($one_field["mult_value"]))
              {  // множественное значение представляется как множество селектов
                 $one_value["fast_edit_div"]="<input type=hidden id='fast_edit_span_$sf_idp$sl_id$sf_ids' value='".$line[$one_field["int_name"]]."'>";
                 $one_value["fast_edit_div_full_value"]="";
                 $one_value["fast_edit_div_close"]="";
                 $one_value["value"]="";
                 $onload_script="";
                 if ($part=="add_link_field")
                     $adds_class="add_link_field nwidth "; // Неизвестная ширина - объект при создании скрыт
                   else
                     $adds_class="";
                 if ($line["id"]!="_undefined_line_id_") $adds_class.="fast_edit_select";
                                                   else  $adds_class.="undefined_fast_edit_select";
                 foreach ($input_value['set'] as $pos=>$one_set)
                   {   // один селект
                      if ($pos==(count($input_value['set'])-1))
                         {
                           $is_last=1;
                         }
                         else
                         {
                           $is_last=0;
                         }

                      $one_value["value"].=<<<EOD
<select $adds_part
tabindex="$tabindex_fast_edit"
id='fast_edit_span_$sf_idp$sl_id$sf_ids$pos'
multi_select_group="$sf_idp$sl_id"
field_id="$sf_id"
line_id="$sl_id"
subtable_id="$subtable_id"
pos="$pos"
is_last="$is_last"
class="fast_edit_span_$sf_idp$sl_id $adds_class sub_fast_edit_select"
$adds_style
>
EOD;
                      $one_value["value"].=$one_set;
                      $one_value["value"].=<<<EOD
</select>
EOD;
                      $onload_script.="addHandler_mult_select(document.getElementById('fast_edit_span_$sf_idp$sl_id$sf_ids$pos'));\n";
                   }
                $one_value["fast_edit_div_close"]="<script>$onload_script</script>";
              }
              else
              { // Одинарный селект
                if ($part=="add_link_field")
                     $adds_class="add_link_field nwidth "; // Неизвестная ширина - объект при создании скрыт
                   else
                     $adds_class="";
                if ($line["id"]!="_undefined_line_id_") $adds_class.="fast_edit_select";
                                                  else  $adds_class.="undefined_fast_edit_select";
                $one_value["fast_edit_div"]=<<<EOD
<select id='fast_edit_span_$sf_idp$sl_id$sf_ids' $adds_part
tabindex="$tabindex_fast_edit"
field_id="$sf_id"
line_id="$sl_id"
subtable_id="$subtable_id"
class="fast_edit_span_$sf_idp$sl_id $adds_class sub_fast_edit_select" $adds_style
>
EOD;
                $one_value["value"]=$input_value;
                $one_value["fast_edit_div_full_value"]=$one_value["fast_edit_div"];
                $onload_script.="addHandler_select(document.getElementById('fast_edit_span_$sf_idp$sl_id$sf_ids'));\n";
                $one_value["fast_edit_div_close"]="</select><script>$onload_script</script>";
              }
          }
      }
  elseif ($one_field["type_field"]==5)
      { // Поле связь
        $ta=explode("|",$one_field["type_value"]); list ($lnk_table_id, $lnk_field_id, $lnk_filter_id, $lnk_show_field_name, $lnk_show_field_inline) = $ta;

        if ($part=="add_link_field") {$adds_class="fast_edit_link_input fast_edit_bordered fast_add_link_field";$add_class2=" class='fast_edit_link_span_hover'";}
           else { $adds_class="sub_edit_link_input"; $add_class2="";}
        // Смотрим на какое поле ссылается поле связь
        $ln_id=(is_array($line[$one_field["int_name"]])&&$line[$one_field["int_name"]]['raw'])?$line[$one_field["int_name"]]['raw']:$line[$one_field["int_name"]];
        $one_value["fast_edit_div"]="<a href='view_line".$config["vlm"].".php?table=".$lnk_table_id."&line=$ln_id' onclick='if (!event.ctrlKey) return false;' class=fast_edit_link>";
        $ex_style="";
        $field_width=280;
        if ($one_field["width"])
           {
             $field_width=$one_field["width"];
             $ex_style="style='width: ".$field_width."px'";
           };
        if (is_array($line[$one_field["int_name"]])) $f_value=$line[$one_field["int_name"]]['raw'];
           else $f_value=$line[$one_field["int_name"]];
        $filter_field = "";
        if ($part == "add_link_field" && $lnk_filter_id) $filter_field = "filter_field=\"".str_replace("-", "", $lnk_filter_id)."\" ";
        $display_edit = form_display(form_display_type($one_field, $line, "text"));
        $one_value["value"]=<<<EOD
<input $adds_part
class="fast_edit_span_$sf_idp$sl_id $adds_class"
tabindex="$tabindex_fast_edit"
type="text"
id="fast_edit_span_$sf_idp$sl_id$sf_ids"
value="$display_edit"
field_id="$sf_id"
line_id="$sl_id"
subtable_id="$subtable_id"
f_value="$f_value"
$filter_field
field_width="$field_width"
$ex_style
><span$add_class2></span>
EOD;
        $one_value["fast_edit_div_full_value"]=$one_value["fast_edit_div"];
        $one_value["fast_edit_div_close"]="</a><script>addHandler_link(document.getElementById('fast_edit_span_$sf_idp$sl_id$sf_ids'));</script>";
      }
  elseif ($one_field["type_field"]==6)
      { // ФАЙЛ
        // поле не похожее на другие, обработка не вызывает form_input_type
        if ($one_field["width"])
           {
             $field_width = $one_field["width"];
             $ex_style = "width: ".$field_width."px;";
           }
        $one_value["fast_edit_div"]=<<<EOD
<div $adds_part
id='fast_edit_span_$sf_idp$sl_id$sf_ids'
class="fast_edit_span_$sf_idp$sl_id sub_fast_edit_file"
style="min-width: 75px;word-wrap: break-word;$ex_style"
>
EOD;

        $value =$line["f".$one_field['id']];
        $files = $value?explode("\r\n", $value):array();
        $one_value["value"]="";
        $onload_script="";
        foreach ($files as $pos => $file)
            {
              $url_encode_f_name=urlencode($file);
              $disp_f_name=form_display($file);
              $one_value["value"].=<<<EOD
<span class="fast_edit_span_$sf_idp$sl_id$pos"><a $adds_part
href='open_file.php?field=$sf_id&line=$sl_id&file=$url_encode_f_name'
id='fast_edit_span_$sf_idp$sl_id$sf_ids$pos'
field_id="$sf_id"
line_id="$sl_id"
subtable_id="$subtable_id"
title="$disp_f_name"
>$disp_f_name</a><span class="b_drop_hoverpopup"></span></span> 
EOD;
              $onload_script.="addHandler_file(document.getElementById('fast_edit_span_$sf_idp$sl_id$sf_ids$pos'));\n";
            }
        $one_value["fast_edit_div_full_value"]=$one_value["fast_edit_div"];
        $lng_s=$lang['file_wasnt_upload'];
        $lng_add=$lang['Add'];
        if ($part=="add_link_field")
        $one_value["fast_edit_div_close"]=<<<EOD
</div>
<div class="sub_fast_edit_file_url add_file_url_$sf_idp$sl_id" id='add_file_url_$sf_idp$sl_id$sf_ids'>
<div class="sub_fast_edit_file_form">
<div>
<input type=file name="lnk_add_file[]" size=1 $adds_part
onclick ='if (upload_in_progress) {alert("$lng_s");return false;}'
onchange='sub_add_file($sf_id,$sl_id,this);'
tabindex='$tabindex_fast_edit' multiple="multiple">
</div>
</div>
$lng_add
</div>
<script>$onload_script</script>
EOD;

           else
        $one_value["fast_edit_div_close"]=<<<EOD
</div>
<div class="sub_fast_edit_file_url add_file_url_$sf_idp$sl_id$sf_ids" id='add_file_url_$sf_idp$sl_id$sf_ids'>
<div class="sub_fast_edit_file_form">
<form
method=post
enctype="multipart/form-data"
action="update_value.php?field=$sf_id&line=$sl_id"
id='sbmt_file_$sf_idp$sl_id$sf_ids'
target='frame_upload'>
<input type=hidden name=csrf value='$csrf'><input type=hidden name=field value='$sf_id'><input type=hidden name=line value='$sl_id'>
<input type=hidden name=subtable_page value='' id='subtable_page$sf_idp$sl_id$sf_ids'>
<input type=hidden name=rel_field value='' id='rel_field$sf_idp$sl_id$sf_ids'>
<div class='sub_fast_edit_file_form2'>
<input type=file name="add_file[]" size=1 $adds_part
onclick ='if (upload_in_progress) {alert("$lng_s");return false;}'
onchange='sub_add_file($sf_id,$sl_id,this);'
tabindex='$tabindex_fast_edit' multiple="multiple">
</div>
</form>
</div>
$lng_add
</div>
<script>$onload_script</script>
EOD;
      }
  elseif ($one_field["type_field"]==9)
      { // Изображение
        // 
        $one_value["fast_edit_div"]=<<<EOD
<div $adds_part
id='fast_edit_span_$sf_idp$sl_id$sf_ids'
class="fast_edit_span_$sf_idp$sl_id sub_fast_edit_file"
>
EOD;
        $value =$line["f".$one_field['id']];
        $files = $value?explode("\r\n", $value):array();
        $one_value["value"]="";
        $onload_script="";

        foreach ($files as $pos => $file)
            {
              $url_encode_f_name=urlencode($file);
              $disp_f_name=form_display($file);
              $disp_img=form_display($file);
              $utf2eng_f_name=utf2eng($file);

              $fname = get_file_path($sf_id, $sl_id, $file);
              if (!file_exists($fname))
                 {
                   $disp_img=$disp_f_name." (".$lang['f_not_exits'].")";
                 }
                 else
                 {
                    list($w1, $h1) = getimagesize($fname);
                    if (!file_exists($config["site_path"]."/cache/".$st_id."_".$sf_id."_".$sl_id."_".$utf2eng_f_name.".png"))
                       {
                          $w2=$field['img_size_x'];$h2=$field['img_size_y'];
                          $content = file_get_contents($fname);
                          image_preview($content, $w1, $h1, $w2, $h2, $one_field['table_id']."_".$one_field['id']."_".$line_id."_".utf2eng($file).".png");
                       }
                    $disp_img="<img src='cache/".$st_id."_".$sf_id."_".$sl_id."_".$utf2eng_f_name.".png' class='sub_fast_edit_img'>";
                    $sz_x=$w1+50;
                    $sz_y=$h1+50;
                 }
              $space_delimiter=" ";
              // !!!!!!!!!!!!!!!!!!!!!!!!!!!! - Патч на ie, т.к. не воспринимает white-space:nowrap !!!!!!!!!!!!!!!!!!!!!!!!!!!!
              if (strpos($_SERVER["HTTP_USER_AGENT"], "MSIE 8.0")) $space_delimiter="<span class='white-small'>. .</span>";

              $one_value["value"].=<<<EOD
<span class='whitespace_nowrap fast_edit_span_$sf_idp$sl_id$pos'><a $adds_part
href='open_file.php?field=$sf_id&line=$sl_id&file=$url_encode_f_name&show=1'
onclick="image_window=window.open('open_file.php?field=$sf_id&line=$sl_id&file=$url_encode_f_name&show=1','','width=$sz_x,height=$sz_y,menubar=1,scrollbars=1,resizable=1,status=1');image_window.focus();return false;"
id='fast_edit_span_$sf_idp$sl_id$sf_ids$pos'
field_id="$sf_id"
line_id="$sl_id"
subtable_id="$subtable_id"
title="$disp_f_name"
file_img=1
>$disp_img</a><span class="b_drop_hoverpopup"></span></span>$space_delimiter
EOD;
              $onload_script.="addHandler_file(document.getElementById('fast_edit_span_$sf_idp$sl_id$sf_ids$pos'));\n";
            }
        $one_value["fast_edit_div_full_value"]=$one_value["fast_edit_div"];
        $lng_s=$lang['file_wasnt_upload'];
        $lng_add=$lang['Add'];
        $one_value["fast_edit_div_close"]=<<<EOD
</div>
<div class="sub_fast_edit_file_url add_file_url_$sf_idp$sl_id$sf_ids" id='add_file_url_$sf_idp$sl_id$sf_ids'>
<div class="sub_fast_edit_file_form">
<form
method=post
enctype="multipart/form-data"
action="update_value.php?field=$sf_id&line=$sl_id"
id='sbmt_file_$sf_idp$sl_id$sf_ids'
target='frame_upload'>
<div>
<input type=hidden name=csrf value='$csrf'><input type=hidden name=field value='$sf_id'><input type=hidden name=line value='$sl_id'>
<input type=hidden name=subtable_page value='' id='subtable_page$sf_idp$sl_id$sf_ids'>
<input type=hidden name=rel_field value='' id='rel_field$sf_idp$sl_id$sf_ids'>
<input type=file name="add_file[]" size=1 $adds_part
onclick ='if (upload_in_progress) {alert("$lng_s");return false;}'
onchange='sub_add_file($sf_id,$sl_id,this);'
file_img=1
tabindex='$tabindex_fast_edit' multiple="multiple">
</div>
</form>
</div>
$lng_add
</div>
<script>$onload_script</script>
EOD;
      }


  //$tabindex_fast_edit+=2;
}

// тест на доступ к записи в строку
// возвращает 0 - если запись запрещена
// 1 - если разрешена
// 2 - если запись разешена, но текст не видно
function test_allow_write($field, $line, $type_out='')
{
  global $user, $lang;
  if ($user['is_root']) return 1;

  // Тест можно ли запись читать
  $allow_read = test_allow_read($field,$line);

  // Разрешенные режимы вывода
  $allowed_type_out = array('view_edit'=>'view_edit', 'view_add'=>'view_add', 'write'=>'write');
  $type_out = $allowed_type_out[$type_out];

  $line_id=$line['id'];
  $table_id = $field['table_id'];
  $table = get_table($table_id);
  $type = $field['type_field'];
  $type_value = $field['type_value'];

  $disallow_mask = array();
  foreach ($table['rules_reverse'] as $one_rule)
   {
     foreach ($one_rule['rights'] as $one_right)
       {
         if ($one_right['field']==$field['id'] && (($type_out && isset($one_right[$type_out])) || (!$type_out && (isset($one_right['view_edit'])||isset($one_right['write']))) ))
            {  // Есть правило на данное поле
               if (eval_php_condition($line, $one_rule['condition_php']))
                  {  // Условие срабатывает, возвращаем права
                     if (!$type_out)
                        { // Разрешение записи редактирования / импорта
                          if ($one_right['view_edit']||$one_right['write'])
                               if (!$allow_read) return 2;
                                   else return 1;
                             else
                              { // Необходимо чтобы все маски были запрещены
                                if ($disallow_mask['view_edit'] && $disallow_mask['write'])
                                    return 0;
                                   else
                                   {
                                    if ($one_right['view_edit']) $disallow_mask['view_edit']=1;
                                    if ($one_right['write']) $disallow_mask['write']=1;
                                   }
                              }

                        }
                        else
                        {
                          if (isset($one_right[$type_out]))
                             {
                               if ($one_right[$type_out])
                                    if (!$allow_read) return 2;
                                       else return 1;
                                  else
                                    return 0;
                               return $one_right[$type_out]?1:0;
                             }
                        }
                  }
               if (!$line['id'])
                  {
                    if (!$type_out)
                       {
                         if ($one_right['view_edit']||$one_right['write'])
                            {
                               return 1;
                            }
                       }
                  }
            }
       }
   }

  // Правил на поле нет, срабатывает дефолт
  if (!$type_out)
     {
       if ($field['view_edit']||$field['write'])
          {
             if (!$allow_read) return 2;
                          else return 1;
          }
          else return 0;
     }
     else 
  if ($field[$type_out] && !$allow_read) return 2; // Запись разрешена, но запрещено чтение
     else return $field[$type_out]?1:0;
}

// сформировать значение при редактировании
function form_input_type($field, $line, $type_out='view_edit')   //при вводе данных
{
  global $user, $lang, $table, $config;
  $line_id = $line['id'];
  $value = is_array($line)?$line[$field['int_name']]:$line;
  
  $type = $field['type_field'];
  $type_value = $field['type_value'];
  
  $test_write=test_allow_write($field, $line, $type_out);
  if ($test_write===0) return false;
  if ($test_write===2 && $type!=4) return array('input'=>"", 'value'=>"");

  if ($type==1 or $type==10)  //тип поля число
    {
      if ($field['autonumber'] and ($type_out==="view_add" or $type_out==="write")) $value = "{".$lang['autonumber']."}";
      else $value = form_local_number($value, $field['dec_dig']);
      $new_value['input'] = $value;
      $new_value['value'] = $value;
    }
  elseif ($type==2 or $type==12)  //тип поля дата
    {
      $value = (substr($value,0,1)=="{") ? $value : form_local_time($value, $type_value, 0);
      $new_value['input'] = $value;
      $new_value['value'] = $value;
    }
  elseif ($type==3)   //тип поля текст
    {
      $value = form_display($value);
      $new_value['input'] = $value;
      $new_value['value'] = $value;
    }
  elseif ($type==4)   //тип поля список
    {
      $value = $value?explode("\r\n", form_display($value)):array();
      if ($field['mult_value']) // мн.выбор
        {
          if ($field['form_fast_edit_flag'])
             {  // новый режим быстрого редактирования
                $value[]='';
                $pos=0;
                $new_value['input']['set']=array();
                foreach ($value as $one_value)
                  {
                    if (!$field['main'] or !$field['default_value'] or !$line_id) $set = "<option value=''></option>";
                    foreach ($field['list_values'] as $list_value)
                      {
                        $disabled = "";
                        if ((in_array($list_value, $value))&&($list_value!=$one_value))
                           { // Ограничиваем возможность выбора повторно, одних и тех же элементов
                             $disabled = "disabled";
                           }
                        $display_value = $lang['conf'][$list_value]?$lang['conf'][$list_value]:$list_value;
                        if ($field['reduce'] and mb_strlen($display_value)>35) $display_value = mb_substr($display_value,0,35)."...";
                        $set .= "<option $disabled ".(($list_value==$one_value)?"selected ":"")."value='".$list_value."'>".$display_value."</option>";
                      }
                    $new_value['input']['set'][$pos] = $set;
                    $pos++;
                  }
                $new_value['value'] = "";
             }
             else 
             {  // старый режим галочками
                foreach ($field['list_values'] as $list_value)
                  {
                    $display_value = $lang['conf'][$list_value]?$lang['conf'][$list_value]:$list_value;
                    if ($field['reduce'] and mb_strlen($display_value)>35) $display_value = mb_substr($display_value,0,35)."...";
                    $one_value['list_value'] = $list_value;
                    if ($test_write===1) $one_value['checked'] = in_array($list_value, $value);
                    $one_value['display_value'] = $display_value;
                    $all_values[] = $one_value;
                  }
                $new_value['input'] = $all_values;
                $new_value['value'] = count($value);
            };
        }
        else
        {
          if ($test_write===2) $value = "";
          elseif ($test_write===1) $value = $value[0];
          
          if (!$field['main'] or !$field['default_value'] or !$line_id) $set = "<option value=''></option>";
          foreach ($field['list_values'] as $list_value)
            {
              $display_value = $lang['conf'][$list_value]?$lang['conf'][$list_value]:$list_value;
              if ($field['reduce'] and mb_strlen($display_value)>35) $display_value = mb_substr($display_value,0,35)."...";
              $set .= "<option ".(($list_value==$value)?"selected ":"")."value='".$list_value."'>".$display_value."</option>";
            }
          $new_value['input'] = $set;
          
          if ($test_write===2) $new_value['value'] = "";
          elseif ($test_write===1) $new_value['value'] = $value;
        }
    }
  elseif ($type==5)   //тип поля связь
    {
      $new_value['input'] = "";
      $new_value['value'] = $value;
    }
  elseif ($type==6)   //тип поля файл
    {
      $files = $value?explode("\r\n", $value):array();
      foreach ($files as $i => $file)
        {
          $one_file['name'] = form_display($file);
          $one_file['js'] = addslashes($file);
          $one_file['url'] = urlencode($file);
          $files[$i] = $one_file;
        }
      $new_value['input'] = $files;
      $new_value['value'] = $value;
    }
  elseif ($type==7 or $type==11)   //тип поля пользователь
    {
      $group_id = $field['groupe'];
      if (is_array($group_id))
      {
        foreach ($group_id as $k=>$v)
        {
          if (!$v) unset($group_id[$k]);
          if ($v=='{current}' or $v=='{current_group}') $group_id[$k] = $user['group_id'];
        }
      }
      
      if (substr($value,-1)=="-") $value = substr($value,1,-1);
      if ($field['mult_value']) // мн.выбор
        {
          $value = $value?explode("-",$value):array();

          if ($field['form_fast_edit_flag'])
            {  // новый режим быстрого редактирования
              $value[]='';
              $pos=0;
              $new_value['input']['set']=array();

              $type_value=array();
              $sqlQuery = "SELECT id, fio FROM ".USERS_TABLE." WHERE arc=0".($group_id?" AND group_id in (".implode(", ", $group_id).")":"")." ORDER BY fio";
              $result = sql_query($sqlQuery);
              while ($row = sql_fetch_assoc($result))
                {
                  $type_value[$row['id']] = $row['fio'];
                }
              foreach ($value as $one_value)
                {
                  $set = "<option value='0'></option>";
                  foreach ($type_value as $id=>$list_value)
                    {
                      $disabled = "";
                      if ((in_array($id, $value))&&($id!=$one_value))
                         { // Ограничиваем возможность выбора повторно, одних и тех же элементов
                           $disabled = "disabled";
                         }
                      if ($field['reduce'] and mb_strlen($list_value)>35) $display_value = mb_substr($list_value,0,35)."..."; else $display_value = $list_value;
                      $set .= "<option $disabled ".(($id==$one_value)?"selected ":"")."value='$id'>$display_value</option>";
                    }
                  $new_value['input']['set'][$pos] = $set;
                  $pos++;
                }
              $new_value['value'] = "";
            }
            else
            {  // старый режим галочками
              if ($line_id and in_array("{current}",$value)) $value[array_search("{current}",$value)] = $user['id'];
              if (!$line_id and $type_out!=="view_add")
                {
                  $one_value['list_value'] = "{current}";
                  $one_value['checked'] = in_array("{current}",$value);
                  $one_value['display_value'] = "{".$lang['current']."}";
                  $all_values[] = $one_value;
                  if (in_array("{current}",$value)) $in_list = 1;
                }
              $sqlQuery = "SELECT id, fio FROM ".USERS_TABLE." WHERE arc=0".($group_id?" AND group_id in (".implode(", ", $group_id).")":"")." ORDER BY fio";
              $result = sql_query($sqlQuery);
              while ($row = sql_fetch_assoc($result))
                {
                  $one_value['list_value'] = $row['id'];
                  $one_value['checked'] = in_array($row['id'],$value);
                  $one_value['display_value'] = form_display($row['fio']);
                  $all_values[] = $one_value;
                  if (in_array($row['id'],$value)) $in_list = 1;
                }
              if ($value and !$in_list and trim(implode(",",$value))!="")
                {
                  $sqlQuery = "SELECT id, fio FROM ".USERS_TABLE." WHERE arc=0 AND id in (".implode(",",$value).") ORDER BY fio";
                  $result = sql_query($sqlQuery);
                  while ($row = sql_fetch_assoc($result))
                    {
                      $one_value['list_value'] = $row['id'];
                      $one_value['checked'] = in_array($row['id'],$value);
                      $one_value['display_value'] = form_display($row['fio']);
                      $all_values[] = $one_value;
                    }
                }
              $new_value['input'] = $all_values;
              $new_value['value'] = count($value);
            }
        }
        else
        {
          if ($value<0) $value = -$value;
          $set = "<option value='0'></option>";
          if ($line_id and $value=="{current}") $value = $user['id'];
          if (!$line_id) $set.= "<option ".(($value=="{current}")?"selected ":"")."value='{current}'>{".$lang['current']."}</option>";
          $sqlQuery = "SELECT id, fio FROM ".USERS_TABLE." WHERE arc=0".($group_id?" AND group_id in (".implode(", ", $group_id).")":"")." ORDER BY fio";
          $result = sql_query($sqlQuery);
          while ($row = sql_fetch_assoc($result))
            {
              $set.= "<option value='".$row['id']."'".(($row['id']==$value)?" selected":"").">".$row['fio']."</option>";
              if ($row['id']==$value) $in_list = 1;
            }
          if ($line_id and $value and !$in_list)
            {
              $sqlQuery = "SELECT fio FROM ".USERS_TABLE." WHERE id='".$value."'";
              $result = sql_query($sqlQuery);
              if ($row = sql_fetch_assoc($result))
                $set.= "<option value='".$value."' selected>".$row['fio']."</option>";
              else
                $set.= "<option value='".$value."' selected style='color:#afafaf'>- ".$lang['deleted_u']." -</option>";
            }
          $new_value['input'] = $set;
          $new_value['value'] = $value;
        }
    }
  elseif ($type==9)   //тип поля изображение
    {
      $files = $value?explode("\r\n", $value):array();
      foreach ($files as $i => $file)
        {
          $one_file['name'] = form_display($file);
          $one_file['js'] = addslashes($file);
          $one_file['url'] = urlencode($file);
          $one_file['file'] = utf2eng($file);
          $one_file['size'] = $type_value;
          $files[$i] = $one_file;
        }
      $new_value['input'] = $files;
      $new_value['value'] = $value;
    }
  elseif ($type==13)   //тип поля статус
    {
      $set.= "<option ".(($value==0)?"selected ":"")."value=0>".$lang['active']."</option>";
      $set.= "<option ".(($value==1)?"selected ":"")."value=1>".$lang['archive']."</option>";
      $set.= "<option ".(($value==2)?"selected ":"")."value=2>".$lang['deleted']."</option>";
      $new_value['input'] = $set;
      $new_value['value'] = $value;
    }
  elseif ($type==14)   //тип поля группа
    {
      // Права на изменение группы доступа для Субадминов
      $field_for_group = $table['user_table_fields']['group_id'];
      $field_for_user = $table['user_table_fields']['user_id'];
      if ($field_for_group && $config['master_sub_admin']>0)
      {
        if ($line['f'.$field_for_user]==$config['master_sub_admin'])
        {
          $query = sql_select(GROUPS_TABLE, "sub_admin=1");
          while ($row = sql_fetch_assoc($query))
          {
            $sub_admin_groups[] = $row['id'];
          }
          $sub_admin_excep = " WHERE id IN (".implode(", ", $sub_admin_groups).") ";
          $sub_admin_excep_and = " AND id IN (".implode(", ", $sub_admin_groups).") ";
        }
      }
      elseif ($field_for_group && $user['group_id']!=1 && $user['sub_admin_rights']['access_subadmin']==1)
      {
        $sub_admin_excep = " WHERE id!=1 ";
        $sub_admin_excep_and = " AND id!=1 ";
      }
      elseif ($field_for_group && $user['group_id']!=1)
      {
        $sub_admin_groups[] = 1;
        $query = sql_select(GROUPS_TABLE, "sub_admin=1");
        while ($row = sql_fetch_assoc($query))
        {
          $sub_admin_groups[] = $row['id'];
          if ($value==$row['id']) $value = '0';
        }
        $sub_admin_excep = " WHERE id NOT IN (".implode(", ", $sub_admin_groups).") ";
        $sub_admin_excep_and = " AND id NOT IN (".implode(", ", $sub_admin_groups).") ";
      }
      else
      {
        $sub_admin_excep = "";
        $sub_admin_excep_and = "";
      }
      
      if (substr($value,-1)=="-") $value = substr($value,1,-1);
      if ($field['mult_value']) // мн.выбор
        {
          $value = $value?explode("-",$value):array();

          if ($field['form_fast_edit_flag'])
            {  // новый режим быстрого редактирования
              $value[]='';
              $pos=0;
              $new_value['input']['set']=array();

              $type_value=array();
              $sqlQuery = "SELECT id, name FROM ".GROUPS_TABLE." ".$sub_admin_excep." ORDER BY name";
              $result = sql_query($sqlQuery);
              while ($row = sql_fetch_assoc($result))
                {
                  $type_value[$row['id']] = form_display($row['name']);
                }
              foreach ($value as $one_value)
                {
                  if (!$sub_admin_excep) $set = "<option value=''></option>";
                  foreach ($type_value as $id=>$list_value)
                    {
                      $disabled = "";
                      if ((in_array($id, $value))&&($id!=$one_value))
                         { // Ограничиваем возможность выбора повторно, одних и тех же элементов
                           $disabled = "disabled";
                         }
                      if ($field['reduce'] and mb_strlen($list_value)>35) $display_value = mb_substr($list_value,0,35)."..."; else $display_value = $list_value;
                      $set .= "<option $disabled ".(($id==$one_value)?"selected ":"")."value='$id'>$display_value</option>";
                    }
                  $new_value['input']['set'][$pos] = $set;
                  $pos++;
                }
              $new_value['value'] = "";
            }
            else
            {  // старый режим галочками
              if ($line_id and in_array("{current_group}",$value)) $value[array_search("{current_group}",$value)] = $user['group_id'];
              if (!$line_id)
                {
                  $one_value['list_value'] = "{current_group}";
                  $one_value['checked'] = in_array("{current_group}",$value);
                  $one_value['display_value'] = "{".$lang['current_gr']."}";
                  $all_values[] = $one_value;
                  if (in_array("{current_group}",$value)) $in_list = 1;
                }
              $sqlQuery = "SELECT id, name FROM ".GROUPS_TABLE.($field['display_groups']?(" WHERE id in (".$field['display_groups'].")".$sub_admin_excep_and):$sub_admin_excep)." ORDER BY name";
              $result = sql_query($sqlQuery);
              while ($row = sql_fetch_assoc($result))
                {
                  $one_value['list_value'] = $row['id'];
                  $one_value['checked'] = in_array($row['id'],$value);
                  $one_value['display_value'] = form_display($row['name']);
                  $all_values[] = $one_value;
                  if (in_array($row['id'],$value)) $in_list = 1;
                }
              if ($value and !$in_list and implode(",",$value)!="")
                {
                  $sqlQuery = "SELECT id, name FROM ".GROUPS_TABLE." WHERE id in (".implode(",",$value).") ".$sub_admin_excep_and." ORDER BY name";
                  $result = sql_query($sqlQuery);
                  while ($row = sql_fetch_assoc($result))
                    {
                      $one_value['list_value'] = $row['id'];
                      $one_value['checked'] = in_array($row['id'],$value);
                      $one_value['display_value'] = form_display($row['name']);
                      $all_values[] = $one_value;
                    }
                }
              $new_value['input'] = $all_values;
              $new_value['value'] = count($value);
            }
        }
        else
        {
          if ($value<0) $value = -$value;
          $set = "<option value=0></option>";
          if ($line_id and $value=="{current_group}") $value = $user['group_id'];
          if (!$line_id) $set.= "<option ".(($value=="{current_group}")?"selected ":"")."value='{current_group}'>{".$lang['current_gr']."}</option>";
          $sqlQuery = "SELECT id, name FROM ".GROUPS_TABLE.($field['display_groups']?(" WHERE id in (".$field['display_groups'].")".$sub_admin_excep_and):$sub_admin_excep)." ORDER BY name";
          $result = sql_query($sqlQuery);
          while ($row = sql_fetch_assoc($result))
            {
              $set.= "<option value='".$row['id']."'".(($row['id']==$value)?" selected":"").">".$row['name']."</option>";
              if ($row['id']==$value) $in_list = 1;
            }
          if ($line_id and $value and !$in_list)
            {
              $sqlQuery = "SELECT name FROM ".GROUPS_TABLE." WHERE id='".$value."'";
              $result = sql_query($sqlQuery);
              if ($row = sql_fetch_assoc($result))
                $set.= "<option value='".$value."' selected>".$row['name']."</option>";
              else
                $set.= "<option value='".$value."' selected style='color:#afafaf'>- ".$lang['deleted_g']." -</option>";
            }
          $new_value['input'] = $set;
          $new_value['value'] = $value;
        }
    }
  else
    {
      die("Error: function 'form_input_type' - Unknown field type ($type)");
    }
  return $new_value;
}

function form_sql_type($field, $value, $type_out="", $term="")     //при занесении в базу
{
  global $user, $lang;

  $type = $field['type_field'];

  $value = default_tpl_replace($value);

  if ($type_out=="import") $value = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $value));

  if ($type==1 or $type==10)  //тип поля число
    {
      if ($type_out=="search")
        {
          for ($i=0;$i<strlen($value);$i++)
            {
              if (!(((ord($value[$i])>47)&&(ord($value[$i])<58))||($value[$i]==' ')||($value[$i]=='.')||($value[$i]==$lang["float_delimiter"]))) $not_is_number = 1;
              if ($i==0 && $value[$i]=='-') $not_is_number = 0;
            }
        }
      if (!$not_is_number) $new_value = form_eng_number($value);
    }
  elseif ($type==2 or $type==12)  //тип поля дата
    {
      if (substr($value,0,1)=="{") $new_value = $value; else $new_value = form_eng_time($value);
    }
  elseif ($type==3)   //тип поля текст
    {
      $new_value = $value;
    }
  elseif ($type==4)   //тип поля список
    {
      if ($type_out=="search")
        {
          $value = mb_strtolower($value); // приводим искомое значение к нижнему регистру
          if (!$field['list_values']) $field['list_values'] = explode("\r\n", $field['type_value']);
          if (!$field['int_name']) $field['int_name'] = form_int_name($field['id']);
          $field['list_values'][] = ""; // добавляем пустой элемент
          $new_value = array();
          foreach ($field['list_values'] as $list_value)
            {
              if ($lang['conf'][$list_value]) $f_value = mb_strtolower($lang['conf'][$list_value]); else $f_value = mb_strtolower($list_value);
              if ((($term=="=" or $term=="!=") and $f_value==$value) or (($term==" LIKE " or $term==" NOT LIKE ") and strpos($f_value, $value)!==false))
                {
                  if (($term==" LIKE " or $term==" NOT LIKE ") and strpos($f_value, $value)!==false and $field['mult_value'])
                      $new_value[] = $field['int_name'].$term."'%".form_sql($list_value)."%'";
                  else
                      $new_value[] = $field['int_name'].$term."'".form_sql($list_value)."'";
                }
              if (($term==" LIKE " or $term==" NOT LIKE ") and strpos($f_value, $value)!==false and $field['mult_value'])
                {
                  $new_value[] = $field['int_name'].$term."'".form_sql($list_value)."\\r\\n%'";
                  $new_value[] = $field['int_name'].$term."'%\\r\\n".form_sql($list_value)."'";
                  $new_value[] = $field['int_name'].$term."'%\\r\\n".form_sql($list_value)."\\r\\n%'";
                }
            }
          // Если не было явных совпадений и используется конструкция LIKE, то ищем прямо по тексту
          // Явные совпадения могут отсутвовать тк. например выбор удален из настроек
          if (!$new_value && ($term==" LIKE " or $term==" NOT LIKE "))
             {
               $value =mb_substr($value,0,250); // Обрезаем если слишком длинное значение
               $new_value[] = $field['int_name'].$term."'%".form_sql($value)."%'";
             }
          if ($term=="=" or $term==" LIKE ") $union = " or "; else $union = " and ";
          if ($new_value) $new_value = "(".implode($union, $new_value).")"; else $new_value = "'0'";
        }
        else
        {
          if (is_array($value)) $new_value = implode("\r\n",$value); else $new_value = $value;
        }
    }
  elseif ($type==5)   //тип поля связь
    {
      if ($type_out=="import")
        {
          if ($field['s_show_field_inline'])
            { // если значение поля связи состоит из нескольких полей, поиск по sql условию невозможен, проходимся по всем записям
              $result = data_select_field($field['s_table_id'], "id");
              while ($row = sql_fetch_assoc($result))
                {
                  $line = array($field['int_name']=>$row['id']);
                  $line_value = form_display_type($field, $line, "text");
                  if ($line_value === $value) {$new_value = $row['id']; break;}
                }
            }
            else
            {
              $result = data_select_field($field['s_table_id'], "id", form_int_name($field['s_field_id'])."='",$value,"'");
              if ($row = sql_fetch_assoc($result)) $new_value = $row['id'];
            }
        }
        elseif ($type_out=="search")
        {
          $new_value = get_link_lines($field, $term, $value);
        }
        else
        {
          $new_value = $value;
        }
    }
  elseif ($type==6)   //тип поля файл
    {
      $new_value = $value;
    }
  elseif ($type==7 or $type==11)   //тип поля пользователь
    {
      if ($type_out=="import")
        {
          $users = explode("\r\n", $value);
          foreach ($users as $i => $value)
            {
              $sqlQuery = "SELECT id FROM ".USERS_TABLE." WHERE fio='".form_sql($value)."'";
              $result = sql_query($sqlQuery);
              if ($row = sql_fetch_assoc($result)) $users[$i] = $row['id']; else unset($users[$i]);
            }
          $new_value = implode("-",$users);
          if ($field['mult_value'] and count($users)) $new_value = "-".$new_value."-";
        }
        elseif ($type_out!="search" and $field['mult_value'] and $value)
        {
          if (!is_array($value)) $value = explode("\r\n", $value);
          $new_value = "-".(is_array($value)?implode("-",$value):$value)."-";
        }
        else
        {
          $new_value = $value;
        }
    }
  elseif ($type==9)   //тип поля изображение
    {
      $new_value = $value;
    }
  elseif ($type==13)   //тип поля статус
    {
      if ($type_out=="import")
        {
          if ($value==$lang['active'])  $new_value = 0;
          if ($value==$lang['archive']) $new_value = 1;
          if ($value==$lang['deleted']) $new_value = 2;
        }
        else
        {
          $new_value = $value;
        }
    }
  elseif ($type==14)   //тип поля группа
    {
      if ($type_out=="import")
        {
          $groups = explode("\r\n", $value);
          foreach ($groups as $i => $value)
            {
              $sqlQuery = "SELECT id FROM ".GROUPS_TABLE." WHERE name='".form_sql($value)."'";
              $result = sql_query($sqlQuery);
              if ($row = sql_fetch_assoc($result)) $groups[$i] = $row['id']; else unset($groups[$i]);
            }
          $new_value = implode("-",$groups);
          if ($field['mult_value'] and count($groups)) $new_value = "-".$new_value."-";
        }
        elseif ($type_out!="search" and $field['mult_value'] and $value)
        {
          $new_value = "-".(is_array($value)?implode("-",$value):$value)."-";
        }
        else
        {
          $new_value = $value;
        }
    }
  else
    {
      die("Error: function 'form_sql_type' - Unknown field type ($type)");
    }
  return $new_value;
}

function form_filter_type($field, $value, $type_out="filter")     //при выборе значения фильтра
{
  global $lang, $user;

  $type = $field['type_field'];
  $type_value = $field['type_value'];

  if ($type==2 or $type==12)   //тип поля дата
    {
      if ($type_out=="filter")
        {
          $new_value = "<option value=\"0000-00-00 00:00:00\">{".$lang['empty_date']."}</option>".
                       "<option value=\"curdate()\">{".$lang['current_date']."}</option>".
                       "<option value=\"now()\">{".$lang['current_time']."}</option>";
        }
        elseif ($type_out=="format")
        {
          $new_value = "<option value=\"0000-00-00 00:00:00\">{".$lang['empty_date']."}</option>".
                       "<option value=\"date('Y-m-d')\">{".$lang['current_date']."}</option>".
                       "<option value=\"date('Y-m-d H:i:s')\">{".$lang['current_time']."}</option>";
        }
        else
        {
          $new_value = form_display($value);
        }
    }
  elseif ($type==4)   //тип поля список
    {
      $new_value = "<option value=''></option>";
      foreach ($field['list_values'] as $list_value)
        {
          if ($type_out=="search")
            $display_value = $lang['conf'][$list_value]?$lang['conf'][$list_value]:$list_value;
          else
            $display_value = $list_value;
          if ($field['reduce'] and mb_strlen($display_value)>35) $display_value = mb_substr($display_value,0,35)."...";
          $new_value .= "<option ".(($list_value==$value)?"selected ":"")."value='".$display_value."'>".$display_value."</option>";
        }
    }
  elseif ($type==7 or $type==11)   //тип поля пользователь
    {
      $group_id = $field['groupe'];
      if (is_array($group_id))
      {
        foreach ($group_id as $k=>$v)
        {
          if (!$v) unset($group_id[$k]);
          if ($v=='{current}' or $v=='{current_group}') $group_id[$k] = $user['group_id'];
        }
      }
      
      $new_value = "<option value=\"\"></option>";
      if ($type_out=="format")
        {
          $new_value.= "<option value=\"\$user['fio']\">{".$lang['current']."}</option>";
        }
        elseif ($type_out=="filter")
        {
          if ($field['mult_value']) $cur_user = "-{current}-"; else $cur_user = "{current}";
          $new_value.= "<option value=\"".$cur_user."\">{".$lang['current']."}</option>";
        }
      $sqlQuery = "SELECT id, fio FROM ".USERS_TABLE." WHERE arc=0".(($type_out=="search")&&($group_id)?" AND group_id in (".implode(", ", $group_id).")":"")." ORDER BY fio";
      $result = sql_query($sqlQuery);
      while ($row = sql_fetch_assoc($result))
        {
          if ($type_out=="format")
            {
              $new_value.= "<option ".(($row['fio']==$value)?"selected ":"")."value=\"".$row['fio']."\">".$row['fio']."</option>";
            }
            else
            {
              if ($field['mult_value']) $row['id'] = "-".$row['id']."-";
              $new_value.= "<option ".(($row['id']==$value)?"selected ":"")."value=\"".$row['id']."\">".$row['fio']."</option>";
            }
        }
    }
  elseif ($type==13)   //тип поля статус
    {
      if ($type_out=="format")
        {
          $new_value.= "<option ".(($value==$lang['active'])?"selected ":"")."value='".$lang['active']."'>".$lang['active']."</option>";
          $new_value.= "<option ".(($value==$lang['archive'])?"selected ":"")."value='".$lang['archive']."'>".$lang['archive']."</option>";
          $new_value.= "<option ".(($value==$lang['deleted'])?"selected ":"")."value='".$lang['deleted']."'>".$lang['deleted']."</option>";
        }
        else
        {
          $new_value.= "<option ".(($value==0)?"selected ":"")."value=0>".$lang['active']."</option>";
          $new_value.= "<option ".(($value==1)?"selected ":"")."value=1>".$lang['archive']."</option>";
          $new_value.= "<option ".(($value==2)?"selected ":"")."value=2>".$lang['deleted']."</option>";
        }
    }
  elseif ($type==14)   //тип поля группа
    {
      $new_value = "<option value=\"\"></option>";
      if ($type_out=="format")
        {
          $new_value.= "<option value=\"\$user['group_name']\">{".$lang['current_gr']."}</option>";
        }
        elseif ($type_out=="filter")
        {
          if ($field['mult_value']) $cur_group = "-{current_group}-"; else $cur_group = "{current_group}";
          $new_value.= "<option value=\"".$cur_group."\">{".$lang['current_gr']."}</option>";
        }
      $sqlQuery = "SELECT id, name FROM ".GROUPS_TABLE." ORDER BY name";
      $result = sql_query($sqlQuery);
      while ($row = sql_fetch_assoc($result))
        {
          if ($type_out=="format")
            {
              $new_value.= "<option ".(($row['name']==$value)?"selected ":"")."value=\"".$row['name']."\">".$row['name']."</option>";
            }
            else
            {
              if ($field['mult_value']) $row['id'] = "-".$row['id']."-";
              $new_value.= "<option ".(($row['id']==$value)?"selected ":"")."value=\"".$row['id']."\">".$row['name']."</option>";
            }
        }
    }
  else   //остальные типы
    {
      $new_value = form_display($value);
    }
  return $new_value;
}

function type_array()
{
  global $lang;
  return array("", $lang["digit"], $lang["date_time"], $lang["text"], $lang["list"], $lang["link"], $lang["file"], $lang["user"], $lang["number"], $lang["image"], $lang["digit"], $lang["user"], $lang["date_time"], $lang["list"], $lang["group"]);
}

function button_type_array()
{
  global $lang;
  return array("",$lang["in_current_window"],$lang["in_new_window"]);
}

function sql_type($type, $type_value, $mult_value, $size = 0)
{
  if ($type==1) $set_type = "DECIMAL(".str_replace("/",",",$type_value).")";
  if ($type==2 and $type_value) $set_type = "TIMESTAMP DEFAULT '0000-00-00 00:00:00'";
  if ($type==2 and !$type_value) $set_type = "DATETIME DEFAULT '0000-00-00 00:00:00'";
  if ($type==3 and !$mult_value) $set_type = "VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci";
  if ($type==3 and $mult_value) $set_type = "TEXT CHARACTER SET utf8 COLLATE utf8_general_ci";
  if ($type==4 and !$size) $set_type = "VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci";
  if ($type==4 and $size) $set_type = "VARCHAR(".$size.") CHARACTER SET utf8 COLLATE utf8_general_ci";
  if ($type==4 and $mult_value) $set_type = "TEXT CHARACTER SET utf8 COLLATE utf8_general_ci";
  if ($type==5) $set_type = "INTEGER";
  if ($type==6) $set_type = "TEXT CHARACTER SET utf8 COLLATE utf8_general_ci";
  if ($type==7 and !$mult_value) $set_type = "INTEGER";
  if ($type==7 and $mult_value) $set_type = "TEXT CHARACTER SET utf8 COLLATE utf8_general_ci";
  if ($type==8) $set_type = "INTEGER";
  if ($type==9) $set_type = "TEXT CHARACTER SET utf8 COLLATE utf8_general_ci";
  if ($type==10) $set_type = "INT(11) AUTO_INCREMENT";
  if ($type==11) $set_type = "INT(11)";
  if ($type==12) $set_type = "TIMESTAMP DEFAULT '0000-00-00 00:00:00'";
  if ($type==13) $set_type = "TINYINT(1)";
  if ($type==14 and !$mult_value) $set_type = "INTEGER";
  if ($type==14 and $mult_value) $set_type = "TEXT CHARACTER SET utf8 COLLATE utf8_general_ci";
  return $set_type;
}

function form_select($type_names, $id_type)
{
  $types_count = count($type_names);
  for ($i=1; $i<$types_count; $i++)
    {
      $select.="<option ";
      if ($i==$id_type) $select.="selected ";
      $select.="value='$i'>".$type_names[$i]."</option>";
    }
  return $select;
}

function form_num_select($all_fields, $field_num)
{
  global $lang;
  if (isset($_REQUEST['num']))
    {
      $new_num = $_REQUEST['num'];
    }
    elseif (!isset($field_num))
    {
      foreach ($all_fields as $one_field);
      $new_num = $one_field['num'] + 1;
    }
  if (isset($field_num)) $select = "<option value=".$field_num.">".$lang["no_change"]."</option>";
  $select.= "<option ".(($new_num==1)?"selected ":"")."value=1>".$lang["in_begin"]."</option>";
  foreach ($all_fields as $one_field)
    {
      if (($one_field['num'] != $field_num) and ($one_field['num'] + 1 != $field_num))
        {
          $select.= "<option ";
          if ($one_field['num'] + 1 == $new_num) $select.= "selected ";
          $select.= "value=".($one_field['num'] + 1).">".$lang["after"]." \"".$one_field['name']."\"</option>";
        }
    }
  return $select;
}

function form_type_select($id_type, $hidden)
{
  $serv = array(10, 11, 12, 13);
  $type_names = type_array();
  $types_count = count($type_names);
  for ($i=1; $i<$types_count; $i++)
    { 
      if ($i == 8) continue; // Пропуск бывшего поля "Номер".
      if ((!in_array($i,$serv) and !$hidden) or (in_array($i,$serv) and $hidden))
        {
          $select.="<option ";
          if ($i==$id_type) $select.="selected ";
          $select.="value='$i'>".$type_names[$i]."</option>";
        }
    }
  return $select;
}

function form_fields_fgroups($table_id)
{
  $sqlQuery = "SELECT * FROM ".FIELDS_TABLE. " WHERE table_id=".$table_id." ORDER BY field_num";
  $result = sql_query($sqlQuery);
  while ($row = sql_fetch_array($result))
    {
      $one_field['num'] = $row['field_num'];
      $one_field['name'] = $row['name_field'];
      $all_fields_fgroups[$one_field['num']] = $one_field;
    }
  $sqlQuery = "SELECT * FROM ".FIELD_GROUPS." WHERE table_id=".$table_id." ORDER BY num";
  $result = sql_query($sqlQuery);
  while ($row = sql_fetch_array($result))
    {
      $one_fgroup['num'] = $row['num'];
      $one_fgroup['name'] = $row['name'];
      $all_fields_fgroups[$one_fgroup['num']] = $one_fgroup;
    }
  ksort($all_fields_fgroups);
  return $all_fields_fgroups;
}

function form_field_num_select($all_fields_fgroups, $field_num)
{
  global $lang;
  if (isset($_REQUEST['field_num']))
    {
      $new_num = $_REQUEST['field_num'];
    }
    elseif (!isset($field_num))
    {
      foreach ($all_fields_fgroups as $one_elem);
      $new_num = $one_elem['num'] + 1;
    }

  if (isset($field_num)) $select = "<option value=".$field_num.">".$lang["no_change"]."</option>";
  $select.= "<option ".(($new_num==1)?"selected ":"")."value=1>".$lang["in_begin"]."</option>";
  foreach ($all_fields_fgroups as $one_elem)
    {
      if (($one_elem['num'] != $field_num) and ($one_elem['num'] + 1 != $field_num))
        {
          $select.= "<option ";
          if ($one_elem['num'] + 1 == $new_num) $select.= "selected ";
          $select.= "value=".($one_elem['num'] + 1).">".$lang["after"]." \"".$one_elem['name']."\"</option>";
        }
    }
  return $select;
}

function form_field_id_select($table_fields, $field_id, $type_fields="", $rel_fields_include=0)
{
  $select = "<option value=0></option>";
  foreach ($table_fields as $one_field)
    {
      if (!$type_fields or $one_field['type_field']==$type_fields)
        {
          $select.="<option ";
          if ($one_field['id']==$field_id) $select.="selected ";
          $select.="value=".$one_field['id'].">".$one_field['name_field']."</option>";
        }
      if ($rel_fields_include and $one_field['type_field']==5)
        {
          $rel_table = get_table($one_field['s_table_id']);
          $rel_table_fields = get_table_fields($rel_table);
          foreach ($rel_table_fields as $rel_field)
            {
              if (!$type_fields or $rel_field['type_field']==$type_fields)
                {
                  $select.="<option ";
                  if ($one_field['id'].".".$rel_field['id']==$field_id) $select.="selected ";
                  $select.="value=".$one_field['id'].".".$rel_field['id'].">".$one_field['name_field'].".".$rel_field['name_field']."</option>";
                }
            }
        }
    }
  return $select;
}

function form_type_table($link)
{
  $link = explode("|", $link);
  $all_tables = sql_type_table(0);
  foreach ($all_tables as $one_table)
    {
      if ($one_table['cat_id']!=$prev_cat)
        {
          if ($prev_cat) $select.="</optgroup>";
          $sqlQuery = "SELECT * FROM ".CATS_TABLE." WHERE id=".$one_table['cat_id'];
          $result = sql_query($sqlQuery);
          $row = sql_fetch_assoc($result);
          $select.="<optgroup label='".$row['name']."'>";
          $prev_cat = $one_table['cat_id'];
        }
      $select.="<option ";
      if ($one_table['id']==$link[0]) $select.="selected ";
      $select.="value='".$one_table['id']."'>".$one_table['name_table']."</option>";
    }
  return $select;
}

function form_type_field($link, $exception = 0)
{
  $link = explode("|", $link);
  $table=get_table($link[0]);
  if (!$table) return "";
  $all_fields = get_table_fields($table);
  foreach ($all_fields as $one_field)
    {
      if ($one_field['id'] == $exception) continue;
      $select.="<option ";
      if ($one_field['id']==$link[1]) $select.="selected ";
      $select.="value='".$one_field['id']."'>".$one_field['name_field']."</option>";
    }
  return $select;
}

function form_type_filter($link)
{
  global $lang;
  $select = "<option value='0'>(".$lang['default'].")</option>";
  $link = explode("|", $link);
  $all_filters = sql_type_filter($link[0]);
  foreach ($all_filters as $one_filter)
    {
      $select.="<option ";
      if ($one_filter['id']==$link[2]) $select.="selected ";
      $select.="value='".$one_filter['id']."'>".$one_filter['name']."</option>";
    }
  return $select;
}

function form_int_name($field_id)
{
  global $config;
  if (!$field_id) return "";
  // Т.к. функция используется в процессе bd_update, то используются прямые имена, без констант
  $row = sql_select_array($config['table_prefix'].'fields', 'id=',$field_id);
  if (!$row)
    {
      generate_error('Invalid form_int_name field: '.$field_id);
      exit;
    }
  if ($row['type_field']==10) return "id";
  if ($row['type_field']==11) return "user_id";
  if ($row['type_field']==12) return "add_time";
  if ($row['type_field']==13) return "status";
  return "f".$field_id;
}

function sql_type_table($cat_id, $cache=1)
{
  if ($cat_id)
    $sqlQuery = "SELECT * FROM ".TABLES_TABLE." WHERE cat_id=".$cat_id." ORDER BY table_num";
  else
    $sqlQuery = "SELECT * FROM ".CATS_TABLE." a, ".TABLES_TABLE." b WHERE a.id=b.cat_id ORDER BY a.num, b.table_num";
  $result = sql_query($sqlQuery);
  while ($row = sql_fetch_assoc($result))
    {
      $one_table=get_table($row['id'],$cache);
      $all_tables[] = $one_table;
    }
  if (!is_array($all_tables)) $all_tables = array();
  return $all_tables;
}

function sql_type_report($cat_id, $group_id=0)
{
  if ($cat_id)
    $sqlQuery = "SELECT * FROM ".REPORTS_TABLE." WHERE cat_id=".$cat_id." ORDER BY num";
  else
    $sqlQuery = "SELECT * FROM ".CATS_TABLE." a, ".REPORTS_TABLE." b WHERE a.id=b.cat_id ORDER BY a.num, b.num";
  $result = sql_query($sqlQuery);
  while ($row = sql_fetch_assoc($result))
    {
      $one_report['id'] = $row['id'];
      $one_report['num'] = $row['num'];
      $one_report['name'] = form_display($row['name']);
      $one_report['code'] = form_display($row['code']);
      $one_report['form'] = form_display($row['form']);
      $one_report['help'] = form_display($row['help']);
      $sqlQuery = "SELECT * FROM ".ACC_REPORTS_TABLE." WHERE group_id=".$group_id." AND report_id=".$one_report['id'];
      $result2 = sql_query($sqlQuery);
      $row2 = sql_fetch_assoc($result2);
      $one_report['acc'] = $row2['access'];
      $all_reports[] = $one_report;
    }
  if (!is_array($all_reports)) $all_reports = array();
  return $all_reports;
}

function sql_type_button($table_id, $group_id=0)
{
  $all_buttons = array();
  $sqlQuery = "SELECT * FROM ".BUTTONS_TABLE.($table_id?(" WHERE table_id=".$table_id):"")." ORDER BY num";
  $result = sql_query($sqlQuery);
  while ($row = sql_fetch_assoc($result))
    {
      $one_button['id'] = $row['id'];
      $one_button['num'] = $row['num'];
      $one_button['name'] = form_display($row['name']);
      $one_button['help'] = form_display($row['help']);
      $one_button['type'] = form_display($row['type']);
      $one_button['width'] = form_display($row['width']);
      $one_button['height'] = form_display($row['height']);
      $one_button['javascript'] = $row['javascript'];
      $one_button['php_code'] = $row['php_code'];
      $one_button['scroll_bar'] = intval($row["scroll_bar"]);

      $sqlQuery = "SELECT * FROM ".ACC_BUTTONS_TABLE." WHERE group_id=".$group_id." AND button_id=".$one_button['id'];
      $result2 = sql_query($sqlQuery);
      $row2 = sql_fetch_assoc($result2);
      $one_button['acc'] = $row2['access'];
      $all_buttons[] = $one_button;
    }
  if (!is_array($all_buttons)) $all_buttons = array();
  return $all_buttons;
}

function get_table_fields(& $table, $cache=1)
{
  global $user, $tables_cache, $config; // для вычисления прав пользователя
  $table_id=$table['id'];
  $group_id=$user['group_id'];
  if (!$table_id)
     {
       generate_error('Invalid table_id: '.$table_id);
       exit;
     }
  // Если есть в кеше возвращаем его
  if (($tables_cache[$table_id]['table_fields'])&&($cache))
     {
        $table['link_tables']=$tables_cache[$table_id]["link_tables"];
        $table['table_fields']=$tables_cache[$table_id]["table_fields"];
        $table['int_names']  =$tables_cache[$table_id]["int_names"];
        return $tables_cache[$table_id]['table_fields'];
     }

  $type_names = type_array();
  $sqlQuery = "SELECT * FROM ".FIELDS_TABLE. " WHERE table_id=".$table_id." ORDER BY field_num";
  $result = sql_query($sqlQuery);
  $table_fields = array();
  $i = 0;
  $js_repl_arr_src=array("\r\n",'\\',"'","</script>");
  $js_repl_arr_dst=array('\r\n','\\\\',"\\'","<\/script>");
  while ($row = sql_fetch_assoc($result))
    {
      $one_field=array();
      $one_field['id'] = $row['id'];
      $one_field['table_id'] = $row['table_id'];
      $one_field['field_num'] = $row['field_num'];
      $one_field['name_field'] = $row['name_field'];
      $one_field['type_field'] = $row['type_field'];
      $one_field['type_name'] = $type_names[$row['type_field']];
      $one_field['type_value'] = form_display($row['type_value']);
      $one_field['default_value']  = $row['default_value'];
      $one_field['type_value_js'] = str_replace($js_repl_arr_src,$js_repl_arr_dst,$one_field['type_value']);
      $one_field['default_value_js']  = str_replace($js_repl_arr_src,$js_repl_arr_dst,$one_field['default_value']);
      $one_field['javascript'] = $row['javascript'];
      $one_field['help'] = form_display($row['help']);
      $one_field['main'] = $row['main'];
      $one_field['uniq_field'] = $row['uniq_field'];
      $one_field['summa'] = $row['summa'];
      $one_field['reduce'] = $row['summa'];
      $one_field['hidden'] = $row['hidden'];
      $one_field['width']  = $row['width'];
      $one_field['prefix'] = $row['prefix'];
      $one_field['postfix']= $row['postfix'];
      $one_field['disable_link']= $row['disable_link'];
      if ($table['fixed_scroll']==$one_field['id'])
         {
           $one_field['fixed_scroll']=1;
           $one_field['fixed_scroll_width']=$table['fixed_scroll_width'];
         }
         else
         {
           $one_field['fixed_scroll']=0;
           $one_field['fixed_scroll_width']=0;
         }
      if ($table['group_field']==$one_field['id'])
           $one_field["group_field"]=1;
         else
           $one_field["group_field"]=0;
      $one_field['mult_value']= $row['mult_value'];
      $one_field['fast_edit'] = $row['fast_edit'];
      $one_field['pp'] = $i;$i++;
      $one_field['int_name'] = "f".$one_field['id'];
      $one_field['summa'] = 0;
      $one_field['fix_search_on'] = $row['fix_search_on'];
      $one_field['fix_search_mult'] = $row['fix_search_mult'];
      $one_field['scw_set'] = $row['scw_set']?unserialize($row['scw_set']):array();
      $one_field['owner_id'] = $row['owner_id'];

      // Заполняем свойства полей, зависящие от его типа
      if ($one_field['type_field']==1)
        { // Поле текст
          if (strpos($one_field['type_value'],'summa')) $one_field['summa']=1;
          if (strpos($one_field['type_value'],'autonumber')) $one_field['autonumber']=1;
          $ta=explode("\r\n", $one_field['type_value']);
          $ta=explode("|", $ta[0]);
          $one_field['type_value']=$ta[0]; // Приводим к старому виду - оставляем только формат числа
          $one_field['min_dig']=$ta[1];
          $ta=explode('/', $one_field['type_value']);
          $one_field['all_dig']=$ta[0];
          $one_field['dec_dig']=$ta[1];
        }
      if ($one_field['type_field']==2 or $one_field['type_field']==12)
         {
           $one_field['display_time']=$one_field["type_value"];
         }
      if ($one_field['type_field']==3)
        { // Поле текст
           if (strpos($one_field['type_value'],'f_v_t')) $one_field['full_value_table']=1;
           if (strpos($one_field['type_value'],'view_html')) $one_field['view_html']=1;
           if (strpos($one_field['type_value'],'html_editor')) $one_field['html_editor']=1;
           if (strpos($one_field['type_value'],'hyperlink')) $one_field['hyperlink']=1;
           if (strpos($one_field['type_value'],'template'))
              {
                $tmp = explode('{template:',form_display($row['type_value']));
                $tmp = explode("}\r\n",$tmp[1]);
                $tmp[0] = str_replace('replacement of an opening brace','{',$tmp[0]);
                $tmp[0] = str_replace('replacement of the closing brace','}',$tmp[0]);
                $one_field['template'] = $tmp[0];
                
                $tmp = explode('{split_templ_mask:',form_display($row['type_value']));
                $tmp = explode("}\r\n",$tmp[1]);
                $one_field['split_templ_mask'] = $tmp[0];                
                unset($tmp);
              }
        }
      if ($one_field['type_field']==4 or $one_field['type_field']==13)
        { // Поле список, либо статус
          $one_field['list_values'] = explode("\r\n", $one_field['type_value']);
        }
      if ($one_field['type_field']==5)
        {  // Если поле связь
           $ta=explode("|",$one_field['type_value']); list ($s_table_id, $s_field_id, $s_filter_id, $s_show_field_name, $s_show_field_inline) = $ta;
           $one_field["s_table_id"]=$s_table_id; // Таблица на которую указывает поле связи
           $one_field["s_field_id"]=$s_field_id; // Поле на которое указывает поле связи
           $one_field["s_filter_id"]=$s_filter_id; // Поле связи фильтруется с помощью фильтра или значения
           $one_field["s_show_field_name"]=$s_show_field_name;     // Флаг выводить имена информационных полей
           $one_field["s_show_field_inline"]=$s_show_field_inline; // Флаг выводить в одну строку, информационные поля
           $one_field["links_also_show"]=array();
           // Выбираем доп поля информацинные
           $sqlQuery2 = "SELECT * FROM ".FIELDS_LINKS_SHOW_TABLE." WHERE field_id='".$one_field["id"]."' order by id";
           $result2 = sql_query($sqlQuery2);
           while ($row2 = sql_fetch_assoc($result2))
                 {
                   $one_field["links_also_show"][]=$row2['show_field'];
                 }
        }
      if ($one_field['type_field']==6)
        {  // Если поле файл
           $ta=explode("|", $one_field['type_value']); list ($one_field['file_types'], $one_field['max_size']) = $ta;
           $file_types = $one_field['file_types']?explode(",", strtolower($one_field['file_types'])):"";
           unset($one_field['file_types']);
           if ($file_types) foreach ($file_types as $file_type) $one_field['file_types'][$file_type] = trim($file_type);

        }
      if ($one_field['type_field']==7 or $one_field['type_field']==11)
        { // Поле пользователь
           $one_field['groupe'] = explode("|", $one_field['type_value']);

           $one_field['s_list_values'] = array();
           if ($one_field['type_field']==7 or $one_field['type_field']==11)
               $v_result = sql_select_field(USERS_TABLE, "id, fio as value_name", "arc=0");
           else
               $v_result = sql_select_field(GROUPS_TABLE, "id, name as value_name", "hidden=0");
           while ($v_row = sql_fetch_assoc($v_result))
               $values[$v_row['id']] = $v_row['value_name'];
           $one_field['s_list_values'] = $values;
        }
      if ($one_field['type_field']==14)
         { // группа
           $ta = explode("|", $one_field['type_value']);
           $one_field['groupe']         = $ta[0];
           $one_field['use_rights']     = $ta[1];
           $one_field['display_groups'] = $ta[2];

           $one_field['s_list_values'] = array();
           if ($one_field['type_field']==7 or $one_field['type_field']==11)
               $v_result = sql_select_field(USERS_TABLE, "id, fio as value_name", "arc=0");
           else
               $v_result = sql_select_field(GROUPS_TABLE, "id, name as value_name", "hidden=0");
           while ($v_row = sql_fetch_assoc($v_result))
               $values[$v_row['id']] = $v_row['value_name'];
           $one_field['s_list_values'] = $values;
         }
      if ($one_field['type_field']==9)
         {
            $ta=explode("|", $one_field['type_value']); list ($one_field['img_size'],   $one_field['max_size']) = $ta;
            $ta=explode("x", $one_field['img_size']);   list ($one_field['img_size_x'], $one_field['img_size_y']) = $ta;
            $one_field['file_types']=array('jpg'=>'jpg','jpeg'=>'jpeg','gif'=>'gif','png'=>'png','tiff'=>'tiff','bmp'=>'bmp','raw'=>'raw');
         }
      if ($one_field['type_field']==10) $one_field['autonumber'] = 1;
      if ($one_field['type_field']==10) $one_field['int_name'] = "id";
      if ($one_field['type_field']==11) $one_field['int_name'] = "user_id";
      if ($one_field['type_field']==12) $one_field['int_name'] = "add_time";
      if ($one_field['type_field']==13) $one_field['int_name'] = "status";

      $table_fields[$one_field['id']] = $one_field;
    }

  // Выбираем доступ
  if ($table['access'])
     {
        $result2 = sql_select(ACC_FIELDS_TABLE, "group_id=",$user["group_id"]," and table_id=".$table_id);
        while ($row2 = sql_fetch_assoc($result2))
          {
            $field_id=$row2['field_id'];
            if (!$table_fields[$field_id]) continue; // Реально поля не существует
            $table_fields[$field_id]['view']     = $row2['view']>0;
            $table_fields[$field_id]['view_tb']  = $row2['view_tb']>0;
            $table_fields[$field_id]['view_edit']= $row2['view_edit']>0;
            $table_fields[$field_id]['view_add'] = $row2['view_add']>0;
            $table_fields[$field_id]['read']    = $row2['read_acc']>0;
            $table_fields[$field_id]['write']   = $row2['write_acc']>0;
          }
    }

  if (!is_array($table_fields)) $table_fields = array();

  // Заполнить информацию о связных таблицах
  $link_tables=array();
  foreach ($table_fields as $one_field)
    {
       if ($one_field['type_field']==5)
          { // Поле связи
            $link_tables[$one_field['id']]=$one_field['s_table_id'];
          }
    }

  // Заполнить связи между полями связи используемыми как фильтры
  foreach ($table_fields as $one_field)
    {
       $type_field=$one_field["type_field"];
       $type_value=$one_field["type_value"];
       if ($type_field==5)
          {
              if ($one_field['s_filter_id']<0) // Фильтр является полем
                 {
                   $f_fld=-$one_field['s_filter_id'];
                   $table_fields[$one_field["id"]]["parent_link_field"]=$f_fld; // Выставляем фильтруемому полю явное указание что оно фильтруется
                   $table_fields[$f_fld]["child_link_field"]=$one_field["id"]; // Выставляем полю фильтратору, что оно фильтрует определенное поле
 
                   if ($table_fields[$f_fld]['s_table_id']!=$one_field['s_table_id'])
                      {  // Фильтрация по коссвенной таблице, поле фильтратор находиться в другой таблице
                         $t_fld=$table_fields[$f_fld]['s_field_id']; // Поле на которое ссылается фильтрующее поле
                         // Выбираем id таблицы поля
                         $rlst = sql_select_field(FIELDS_TABLE, 'table_id', 'id=',$t_fld);
                         $t_tlb = sql_fetch_assoc($rlst);
                         $t_tlb = $t_tlb['table_id'];
 
                         // Ищем ссылку на данное поле среди полей таблицы $one_field['s_table_id']
                         $rlst = sql_query("SELECT id FROM ".FIELDS_TABLE." WHERE table_id=".$one_field['s_table_id']." and type_field=5 and type_value LIKE '$t_tlb|$t_fld|%'");
                         $filter_field = sql_fetch_assoc($rlst);
                         $table_fields[$one_field["id"]]["s_field_filter_id"]=$filter_field['id'];
                      }
                      else
                      {  // Фильтрация по той же самой таблице
                         $l_table  = get_table($one_field['s_table_id']); // Таблица на которое ссылается поле связь
                         $table_fields[$one_field["id"]]["s_field_filter_id"]=$l_table['first_link_field']; // Первое поле в таблице на которое ссылается поле связь
                      }
                 };
          }
    }
  // Заполнить информацию об обратной связи имени поля и его id
  $int_names_tables=array();
  foreach ($table_fields as $one_field)
    {
       $int_names_tables[$one_field["int_name"]]=$one_field['id'];
    }
  $table['int_names']  =$int_names_tables;
  $table['link_tables']=$link_tables;
  $table['table_fields']=$table_fields;
  if ($cache) // включен кеш
     {
       if (!$tables_cache[$table_id])
            $tables_cache[$table_id]=$table;
          else
          {
            $tables_cache[$table_id]['table_fields']=$table_fields;
            $tables_cache[$table_id]['link_tables']=$link_tables;
            $tables_cache[$table_id]['int_names']  =$int_names_tables;
         }
       if (($config['xcache'])&&(!$user["is_root"])) xcache_set("table_info_".$group_id."_".$table_id, $table, 360);
     }
  return $table_fields;
}

function sql_type_filter($table_id, $group_id=0)
{
  $sqlQuery = "SELECT * FROM ".FILTERS_TABLE. " WHERE table_id='$table_id' ORDER BY num";
  $result = sql_query($sqlQuery);
  while ($row = sql_fetch_assoc($result))
    {
      $one_filter['id'] = $row['id'];
      $one_filter['table_id'] = $row['table_id'];
      $one_filter['name'] = form_display($row['name']);
      $one_filter['value'] = form_display($row['value']);

      $sqlQuery = "SELECT * FROM ".ACC_FILTERS_TABLE." WHERE group_id=".$group_id." AND filter_id=".$one_filter['id'];
      $result2 = sql_query($sqlQuery);
      $row2 = sql_fetch_assoc($result2);
      $one_filter['acc'] = $row2['access'];
      $one_filter['def'] = $row2['def_use'];
      $all_filters[] = $one_filter;
    }
  if (!is_array($all_filters)) $all_filters = array();
  return $all_filters;
}

// Тест, является ли поле связи Фильтром?
function test_field_is_filter($field_id)
{
  $sqlQuery = "SELECT id, type_value FROM ".FIELDS_TABLE." WHERE type_value LIKE '%|-$field_id%' and type_field=5";
  $result = sql_query($sqlQuery);
  while ($row = sql_fetch_assoc($result))
     { // доп проверка
       // -$field_id должно быть третьим параметром
       $ta=explode("|",$row["type_value"]); list ($t1, $t2,$filter_id,$t3,$t4) = $ta;
       if ($filter_id=="-$field_id")
          {
             return 1;
          };
     }
  return 0;
};

// Отсортировать поля, в необходимом порядке
function sort_by_field_num($a,$b)
{
   if ($a["field_num"]==$b["field_num"]) return $a["id"]>$b["id"];
   return $a["field_num"]>$b["field_num"];
}


// function encrypt($str)
// {
    // $key = $_SESSION[$ses_id]['usr_key'];

    // $td = mcrypt_module_open (MCRYPT_RIJNDAEL_256, '', MCRYPT_MODE_ECB, '');
    // mcrypt_generic_init ($td, $key, $key);

    // return mcrypt_generic ($td, $str);

    // mcrypt_generic_deinit ($td);
    // mcrypt_module_close ($td);
// }

// function decrypt($str)
// {
    // $key = $_SESSION[$ses_id]['usr_key'];

    // $td = mcrypt_module_open (MCRYPT_RIJNDAEL_256, '', MCRYPT_MODE_ECB, '');
    // mcrypt_generic_init ($td, $key, $key);

    // return trim(mdecrypt_generic ($td, $str));

    // mcrypt_generic_deinit ($td);
    // mcrypt_module_close ($td);
// }

function encrypt_table($table_id)
{
    $sqlQuery = "SELECT * FROM ".DATA_TABLE.$table_id;
    $result = sql_query($sqlQuery);
    while ($row = sql_fetch_assoc($result)) {
        $form_set = "";
        for ($i=1; $i<=50; $i++) {
            if ($row["f$i"]!=="") {
                $form_set.="f$i='".form_sql(encrypt($row["f$i"]))."', ";
            }
        }
        if (strlen($form_set)) $form_set = substr($form_set,0,strlen($form_set)-2);
        sql_query("UPDATE ".DATA_TABLE.$table_id." SET ".$form_set." WHERE id=".$row["id"]);
    }
}

function decrypt_table($table_id)
{
    $sqlQuery = "SELECT * FROM ".DATA_TABLE.$table_id;
    $result = sql_query($sqlQuery) ;
    while ($row = sql_fetch_assoc($result)) {
        $form_set="";
        for ($i=1; $i<=50; $i++) {
            if ($row["f$i"]!=="") {
                $form_set.="f$i='".form_sql(decrypt($row["f$i"]))."', ";
            }
        }
        if (strlen($form_set)) $form_set = substr($form_set,0,strlen($form_set)-2);
        sql_query("UPDATE ".DATA_TABLE.$table_id." SET ".$form_set." WHERE id=".$row["id"]);
    }
}

include "propis.php";

function data2str($date, $date_format="")
{
  global $lang;
  if (!$date_format) $date_format = $lang['date_full_format'];
  return str_replace(date("F",$date), $lang[date("F",$date)], date($date_format, $date));
}

function utf2eng($str, $replace_symbols=1)
{
  $utf = array("а", "б", "в", "г", "д", "е", "ё", "ж",  "з", "и", "й", "к", "л", "м", "н", "о", "п", "р", "с", "т", "у", "ф", "х", "ц", "ч",  "ш",  "щ",   "ъ", "ь", "ы", "э", "ю",  "я",  "А", "Б", "В", "Г", "Д", "Е", "Ё", "Ж",  "З", "И", "Й", "К", "Л", "М", "Н", "О", "П", "Р", "С", "Т", "У", "Ф", "Х", "Ц", "Ч",  "Ш",  "Щ",   "Ь", "Ъ", "Ы", "Э", "Ю",  "Я");
  $eng = array("a", "b", "v", "g", "d", "e", "e", "zh", "z", "i", "y", "k", "l", "m", "n", "o", "p", "r", "s", "t", "u", "f", "h", "c", "ch", "sh", "sch", "",  "",  "y", "e", "yu", "ya", "A", "B", "V", "G", "D", "E", "E", "Zh", "Z", "I", "Y", "K", "L", "M", "N", "O", "P", "R", "S", "T", "U", "F", "H", "C", "Ch", "Sh", "Sch", "",  "",  "Y", "E", "Yu", "Ya");
  $str = str_replace($utf, $eng, $str);
  if ($replace_symbols)
    {
      $sym = array("`", "~", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "-", "+", "=", "[", "]", "{", "}", ":", ";", "\"", "'", "|", "\\", "/", "?", ",", ".", "<", ">", "№", " ");
      $str = str_replace($sym, "_", $str);
      while (strpos($str,"__")!==false) $str = str_replace("__", "_", $str);
      $str = trim($str,"_");
    }
  return $str;
}

function eng2utf($str)
{
  $eng = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x",  "y", "z", "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X",  "Y", "Z");
  $utf = array("а", "б", "ц", "д", "е", "f", "г", "х", "и", "й", "к", "л", "м", "н", "о", "п", "к", "р", "с", "т", "у", "в", "в", "кс", "й", "з", "А", "Б", "Ц", "Д", "Е", "F", "Г", "Х", "И", "Й", "К", "Л", "М", "Н", "О", "П", "К", "Р", "С", "Т", "У", "В", "В", "Кс", "Й", "З");
  $str = str_replace($eng, $utf, $str);
  return $str;
}

function run_query($sqlQuery)
{
  global $lang;
  $sqlQuery = back_apostrophe_replace($sqlQuery);
  $result = @sql_query($sqlQuery);
  if (!$result)
    {
      $err = debug_backtrace();
      $file = substr(str_replace("\\","/",$err[0]['file']),strrpos(str_replace("\\","/",$err[0]['file']),"/")+1);
      $line = $err[0]['line'];
      die("<b class='error_convert'>".$lang['error_convert']."</b><br><br><b>SQL</b>: ".$sqlQuery."<br><b>Error</b>: ".sql_error()."<br><b>File</b>: ".$file.", str ".$line);
    }
  return $result;
}

// Отправка E-mail
// Обязательные параметры
//   subject       - тема письма
//   body          - тело письма
//   to            - кому
// Необязательные
//   from          - от кого, адрес
//   from_name     - от кого, имя
//   reply_to      - кому отвечать
//   content_type  - тип контента
//   charset       - кодировка
//   headers       - массив заголовков письма
//   attach_files  - массив прикрепленных файлов
//   smtp_server_id - через какой smtp сервер проводить рассылку (по умолчанию - любой)
//   thread_id     - id потока писем (по умолчанию - системный), возвращается функцией create_mail_thread
//   line_id       - id записи таблицы
// Функция добавляет письмо в очередь рассылки (см. send_all_mail) и возвращает uid письма в очереди
function sendmail($subject, $body, $to, $from="", $from_name="", $reply_to="", $content_type="text/html", $charset="utf-8", $headers=array(), $attach_files=array(), $smtp_server_id=-1, $thread_id=1, $line_id=0)
{
  global $user;
  $ins_mail['thread_id'] = $thread_id;
  $ins_mail['add_time'] = date("Y-m-d H:i:s");
  $ins_mail['user_id'] = $user['id'];
  $ins_mail['line_id'] = $line_id;
  $ins_mail['email'] = $to;
  $ins_mail['subject'] = $subject;
  $ins_mail['body'] = $body;
  $ins_mail['from_mail'] = $from;
  $ins_mail['from_name'] = $from_name;
  $ins_mail['reply_to'] = $reply_to;
  $ins_mail['content_type'] = $content_type;
  $ins_mail['charset'] = $charset;
  $ins_mail['headers'] = serialize($headers);
  $ins_mail['smtp_server_id'] = $smtp_server_id;
  $mail_id = sql_insert(MAIL_QUEUE, $ins_mail);
  $uid = time().$mail_id;
  sql_update(MAIL_QUEUE, array('uid'=>$uid), "id=",$mail_id);
  foreach ($attach_files as $file)
    {
      while ($file['content'])
        {
          $ins_file['mail_id'] = $mail_id;
          $ins_file['name']    = $file['name'];
          $ins_file['type']    = $file['type'];
          $ins_file['disp']    = $file['disp'];
          $ins_file['content'] = substr($file['content'],0,500000);
          sql_insert(MAIL_FILES_TEMPORARY, $ins_file);
          $file['content'] = substr($file['content'],500000);
        }
    }
  sql_query("UPDATE ".MAIL_THREADS." SET wait=wait+1 WHERE id=".$thread_id);
  return $uid;
}

// Отправка SMS
//   text          - текст сообщения
//   to            - кому
//   from          - от кого (подпись)
// Необязательные
//   smsc_id       - через какой sms шлюз проводить рассылку (по умолчанию - текущий активный)
//   thread_id     - id потока смс (по умолчанию - системный), возвращается функцией create_sms_thread
//   line_id       - id записи таблицы
// Функция добавляет сообщение в очередь рассылки (см. send_all_sms) и возвращает uid сообщения в очереди
function sendsms($text, $to, $from, $smsc_id=-1, $thread_id=1, $line_id=0)
{
  global $user;
  $ins_mail['thread_id'] = $thread_id;
  $ins_mail['add_time'] = date("Y-m-d H:i:s");
  $ins_mail['user_id'] = $user['id'];
  $ins_mail['line_id'] = $line_id;
  $ins_mail['phone'] = $to;
  $ins_mail['text'] = $text;
  $ins_mail['sender'] = $from;
  $sms_id = sql_insert(SMS_QUEUE, $ins_mail);
  $uid = time().$sms_id;
  sql_update(SMS_QUEUE, array('uid'=>$uid), "id=",$sms_id);
  sql_query("UPDATE ".SMS_THREADS." SET wait=wait+1 WHERE id=".$thread_id);
  return $uid;
}

function send_sms_text($to, $text, $from)
{
  return sendsms($text, $to, $from);
}

function create_mail_thread($name, $form_id=0)
{
  global $user;
  $thread['add_time'] = date("Y-m-d H:i:s");
  $thread['user_id'] = $user['id'];
  $thread['form_id'] = $form_id;
  $thread['name'] = $name;
  $thread_id = sql_insert(MAIL_THREADS, $thread);
  return $thread_id;
}

function create_sms_thread($name, $form_id=0)
{
  global $user;
  $thread['add_time'] = date("Y-m-d H:i:s");
  $thread['user_id'] = $user['id'];
  $thread['form_id'] = $form_id;
  $thread['name'] = $name;
  $thread_id = sql_insert(SMS_THREADS, $thread);
  return $thread_id;
}

function image_preview($image, $w1, $h1, $w2, $h2, $file_name="", $white_to_transparent=0, $watermark="", $wmc="")
{
    global $config;
    @$image_src = imagecreatefromstring ($image); // если невалидное изображение будет просто квадрат

    $w1 = imagesx($image_src); $h1 = imagesy($image_src); // получаем ширину и высоту исходного изображения

    if ($white_to_transparent) {
        imagesavealpha ($image_src, true);
        imagealphablending ($image_src, false);
        $white = imagecolorallocate ($image_src, 255, 255, 255);
        $transparent = imagecolorallocatealpha ($image_src, 0, 0, 0, 127);
        for ($x=0; $x<$w1; $x++) for ($y=0; $y<$h1; $y++) {
            if (imagecolorat($image_src, $x, $y) == $white or 
                imagecolorat($image_src, $x, $y) == 15 or 
                imagecolorat($image_src, $x, $y) == 255) imagesetpixel($image_src, $x ,$y, $transparent);
        }
    }

    if (!$w2 and !$h2) {  // если не заданы оба размера, то выводим исходное изображение

        $image_out = $image_src;

    } else {  // иначе меняем размер

        $x1 = 0; $y1 = 0;  // координаты левого верхнего угла исходного изображения по умолчанию

        if (!$h2 || !$w2)
        {  // если задан только один размер, то сохраняем пропорции
          $w2=$w2?$w2:$h2/($h1/$w1);
          $h2=$h2?$h2:($h1/$w1)*$w2;

        } else {   // иначе обрезаем исходную картинку

            $p1 = $h1?($w1/$h1):1; //вычисляем отношение сторон исходной картинки, если h1=0(не изображение), то считаем что стороны равны (отношение=1),а warning в imagecopyresampled ниже будет подавлен.
            $p2 = $w2/$h2;  // и требуемой картинки

            if ($p1 > $p2) {
                $w1_new = $w2*$h1/$h2; // вычисляем уменьшенную ширину исходной картинки
                $x1 = ($w1 - $w1_new) / 2;
                $w1 = $w1_new;
            } else {
                $h1_new = $w1*$h2/$w2; // вычисляем уменьшенную высоту исходной картинки
                $y1 = ($h1 - $h1_new) / 2;
                $h1 = $h1_new;
            }
        }

        $image_out = imagecreatetruecolor ($w2, $h2);
        imagesavealpha ($image_out, true);
        imagealphablending ($image_out, false);
        @imagecopyresampled ($image_out, $image_src, 0, 0, $x1, $y1, $w2, $h2, $w1, $h1);
    }

    if (file_exists($watermark)) {
        $image_wm = imagecreatefromstring (file_get_contents($watermark));
        list($w_wm, $h_wm) = getimagesize($watermark);
        if (!$w2) $w_img = $w1; else $w_img = $w2;
        if (!$h2) $h_img = $h1; else $h_img = $h2;
        if     ($wmc=="lt") {$x_wm = 0; $y_wm = 0;} // расположение водяного знака - в левом верхнем углу
        elseif ($wmc=="rt") {$x_wm = $w_img-$w_wm; $y_wm = 0;} // расположение водяного знака - в правом верхнем углу
        elseif ($wmc=="lb") {$x_wm = 0; $y_wm = $h_img-$h_wm;} // расположение водяного знака - в левом нижнем углу
        elseif ($wmc=="rb") {$x_wm = $w_img-$w_wm; $y_wm = $h_img-$h_wm;} // расположение водяного знака - в правом нижнем углу
        else                {$x_wm = ($w_img-$w_wm)/2; $y_wm = ($h_img-$h_wm)/2;} // расположение водяного знака - по центру
        imagealphablending ($image_out, true);
        imagecopy ($image_out, $image_wm, $x_wm, $y_wm, 0, 0, $w_wm, $h_wm);
    }

    if ($image_out) if ($file_name) imagepng ($image_out, $config["site_path"]."/cache/".$file_name); else imagepng ($image_out);
}

function str2rtf($str)
{
  $str = iconv("utf-8", "cp1251", $str);
  for ($i=0; $i<strlen($str); $i++)
    {
      if (ord($str[$i])>126) $str_rtf.= "\\'".bin2hex($str[$i]); else $str_rtf.= $str[$i];
    }
  return $str_rtf;
}

// Получить короткое имя браузера по AgentId
function get_browser_name($agent)
{
 // Chrome
 if (preg_match("/Chrome\/([0-9]*\.[0-9]*)/i", $agent, $found )) $browser = "Chrome " . $found[ 1 ];
 else
 // Opera (Disguised as MSIE)
 if( preg_match("/Opera ([0-9]\.[0-9]{0,2})/i", $agent, $found ) &&  strstr( $agent, "MSIE" ) ) $browser = "Opera " . $found[ 1 ];
 // Opera (Disguised as Netscape/Mozilla)
 else if( preg_match("/Opera ([0-9]\.[0-9]{0,2})/i", $agent, $found ) && strstr( $agent, "Mozilla" ) ) $browser = "Opera " . $found[ 1 ];
 // Opera (Itself)
 else if( preg_match("/Opera\/([0-9]\.[0-9]{0,2})/i", $agent, $found ) ) $browser = "Opera " . $found[ 1 ];
 // Netscape 6.x
 else if( preg_match("/Netscape[0-9]\/([0-9]{1,2}\.[0-9]{1,2})/i", $agent, $found ) ) $browser = "Netscape " . $found[ 1 ];
 // Netscape 7.x
 else if( preg_match("/Netscape\/([0-9]{1,2}\.[0-9]{1,2})/i", $agent, $found ) ) $browser = "Netscape " . $found[ 1 ];
 // NetCaptor
 else if( preg_match("/NetCaptor ([0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2})/i", $agent, $found ) ) $browser = $found[0];
 // Crazy Browser
 else if( preg_match("/Crazy Browser ([0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2})/i", $agent, $found ) ) $browser = $found[0];
 // MyIE2
 else if( preg_match("/MyIE2/i", $agent ) ) $browser = "MyIE2";
 // MSIE
 else if( preg_match("/MSIE ([0-9]{1,2}\.[0-9]{1,2})/i", $agent, $found ) ) $browser = $found[0];
 // Konqueror
 else if( preg_match("/Konqueror/i", $agent ) ) $browser = "Konqueror";
 // Galeon
 else if( preg_match("/Galeon/i", $agent ) ) $browser = "Galeon";
 // Phoenix
 else if( preg_match("/Phoenix\/([0-9]{1}\.[0-9]{1,2})/i", $agent, $found ) ) $browser = "Phoenix " . $found[ 1 ];
 // Firebird
 else if( preg_match("/Firebird\/([0-9]{1}\.[0-9]{1,2})/i", $agent, $found ) ) $browser = "Firebird " . $found[ 1 ];
 // FireFox
 else if( preg_match("/Firefox\/([0-9]{1}\.[0-9]{1,2})/i", $agent, $found ) ) $browser = "Firefox " . $found[ 1 ];
 // Lynx
 else if( preg_match("/Lynx/i", $agent, $found ) ) $browser = "Lynx";
 // WebTV
 else if( preg_match("/WebTV/i", $agent, $found ) ) $browser = "WebTV";
 // Netscape 4.x
 else if( preg_match("/Mozilla\/([0-9]{1}\.[0-9]{1,2}) \[en\]/i", $agent, $found ) ) $browser = "Netscape " . $found[ 1 ];
 // A different definition of Mozilla browsers
 else if( preg_match("/Mozilla\/([0-9]{1,2}\.[0-9]{1,2})/i", $agent, $found ) ) $browser = "Mozilla " . $found[ 1 ];
 // Mozilla
 else if( preg_match("/(^Mozilla)(.)*\;\srv:([0-9]\.[0-9])/i", $agent, $found ) ) $browser = $found[ 1 ] . " " . $found[ 3 ];
 // Other (Dont know what it is)
 else $browser = "Other";

 return( $browser );
};

// Получить версию OS по AgentId
function get_os_name($agent)
{
  // Determine the platform they are on
  if( strstr( $agent, "Win") )
  {
     $platform = "Windows";
     if ( preg_match("/6\.1/i", $agent ) ) $platform = "Windows7";
        else
     if ( preg_match("/6\.0/i", $agent ) ) $platform = "Windows Vista";
        else
     if ( preg_match("/5\.1/i", $agent ) ) $platform = "Windows XP";
     else if( preg_match("/5\.2/i", $agent ) ) $platform = "Windows 2003";
     else if( preg_match("/5\.0/i", $agent ) ) $platform = "Windows 2000";
     else if( preg_match("/NT/i", $agent ) ) $platform = "Windows NT";
     else if( preg_match("/ME/i", $agent ) ) $platform = "Windows ME";
     else if( preg_match("/Win 9x 4.90/i", $agent ) ) $platform = "Windows ME";
     else if( preg_match("/Windows ME/i", $agent ) ) $platform = "Windows ME";
     else if( preg_match("/Windows CE/i", $agent ) ) $platform = "Windows CE";
     else if( preg_match("/98/i", $agent ) ) $platform = "Windows 98";
     else if( preg_match("/95/i", $agent ) ) $platform = "Windows 95";
     else if( preg_match("/Win16/i", $agent ) ) $platform = "Windows 3.1";
     else if( preg_match("/Windows 3\.1/i", $agent ) ) $platform = "Windows 3.1";
  }
  else if(strstr($agent, "Mac" ) ) $platform = "Macintosh";
  else if(strstr($agent, "PPC" ) ) $platform = "Macintosh";
  else if(strstr($agent, "FreeBSD" ) ) $platform = "FreeBSD";
  else if(strstr($agent, "SunOS" ) ) $platform = "SunOS";
  else if(strstr($agent, "IRIX" ) ) $platform = "IRIX";
  else if(strstr($agent, "BeOS" ) ) $platform = "BeOS";
  else if(strstr($agent, "OS/2" ) ) $platform = "OS/2";
  else if(strstr($agent, "AIX" ) ) $platform = "AIX";
  else if(strstr($agent, "Linux" ) ) $platform = "Linux";
  else if(strstr($agent, "Unix" ) ) $platform = "Unix";
  else if(strstr($agent, "Amiga" ) ) $platform = "Amiga";
  else $platform = "Other";
  return( $platform );
};

// Фильтр логина пользователя
function filter_login($login)
{ // В логине могут быть только маленкие латинские буквы и цифры, знак минус
  global $br;
  $login = strtolower($login);
  $login_len=strlen($login);
  if ($login_len>14) die($br."Error: Too long filter login '".htmlspecialchars(mb_substr($login,0,50))."'...");
  for ($i=0;$i<$login_len;$i++)
      {
         if (((ord($login[$i])>96)&&(ord($login[$i])<123))||((ord($login[$i])>47)&&(ord($login[$i])<58))||($login[$i]=='-'))
            {
              $new_login.=$login[$i];
            }
//              $new_login.=$login[$i];
      };
  return $new_login;
};

// Фильтр имени файла
function filter_file_name($login)
{ // Запрещенные последовательности /\:*?"<>|
  global $br;
  $new_login="";
  $login_len=strlen($login);
  if ($login_len>300) return("");
  $last_char='-=/-/-/-';
  for ($i=0;$i<$login_len;$i++)
      {
         if (($login[$i]=='/')||($login[$i]=='\\')||($login[$i]=='|')||($login[$i]==':')||($login[$i]=='*')||($login[$i]=='?')||($login[$i]=='"')||($login[$i]=='<')||($login[$i]=='>')||($login[$i]=='|')) continue;
         if (($login[$i]=='/')||($login[$i]=='\\')||($login[$i]=='|')||($login[$i]==':')||($login[$i]=='*')||($login[$i]=='?')||($login[$i]=='"')||($login[$i]=='<')||($login[$i]=='>')||($login[$i]=='|')) continue;
         if ((ord($login[$i])==0)&&(ord($last_char)==0)) return $new_login; // Конец строки
         if (($login[$i]=='\r')&&($last_char=='\n')) return $new_login; // Перевод строки
         $new_login.=$login[$i];
         $last_char=$login[$i];
      };
  return $new_login;
};

// Фильтр доменого имени
function filter_domain_name($login)
{ // Могут быть только маленкие латинские буквы и цифры, знак минус, знак точка
  global $br;
  $new_login="";
  $login_len=strlen($login);
  if ($login_len>60)
     {
       $login_len=60;
     }
  for ($i=0;$i<$login_len;$i++)
      {
         if (((ord($login[$i])>96)&&(ord($login[$i])<123))||((ord($login[$i])>47)&&(ord($login[$i])<58))||($login[$i]=='-')||($login[$i]=='.'))
            {
              $new_login.=$login[$i];
            }
      };
  return $new_login;
}

// Фильтр ip
function filter_ip($login)
{
// Могут быть только цифры, знак точка
  global $br;
  $new_login="";
  $login_len=strlen($login);
  if ($login_len>15) die($br."Error: Too long filter ip '".htmlspecialchars(mb_substr($login,0,50))."'...");
  for ($i=0;$i<$login_len;$i++)
      {
         if (((ord($login[$i])>47)&&(ord($login[$i])<58))||($login[$i]=='.'))
            {
              $new_login.=$login[$i];
            }
      };
  return $new_login;
};


// Фильтр почты
function filter_email($login)
{ // Могут быть только маленкие латинские буквы и цифры, знак минус, знак точка, знак собачка, подчеркивание
  global $br;
  $login = strtolower($login);
  $new_login="";
  $login_len=strlen($login);
  if ($login_len>254)
     {
       $login_len=254;
     }
  for ($i=0;$i<$login_len;$i++)
      {
         if (((ord($login[$i])>96)&&(ord($login[$i])<123))||((ord($login[$i])>47)&&(ord($login[$i])<58))||($login[$i]=='_')||($login[$i]=='-')||($login[$i]=='.')||($login[$i]=='@'))
            {
              $new_login.=$login[$i];
            }
      };
  return $new_login;
}


function mb_html_entity_decode($string)
{
  return mb_convert_encoding($string, 'UTF-8', 'HTML-ENTITIES');
}


function mb_ord($string)
{
  @ $result = unpack('N', mb_convert_encoding($string, 'UCS-4BE', 'UTF-8'));

  if (is_array($result) === true)
     {
          return $result[1];
     }
  return ord($string);
}

function mb_chr($string)
{
    return mb_html_entity_decode('&#' . intval($string) . ';');
}

// Фильтр ФИО
function filter_fio($login)
{ // Могут быть только русские, латинские буквы, пробелы, цифры
  global $br;
  $new_login="";
  $login_len=mb_strlen($login);
  if ($login_len>60) die($br."Error: Too long filter fio name '".htmlspecialchars(mb_substr($login,0,60))."'...");
  for ($i=0;$i<$login_len;$i++)
      {
         $c= mb_substr($login,$i,1);
         //echo "<br>'".$c."' ";
         $ord_c = mb_ord($c);
         //echo "'".$ord_c."' ";
         if ( (($ord_c>47)&&($ord_c<58))||(($ord_c>64)&&($ord_c<91))||(($ord_c>96)&&($ord_c<123))||($ord_c==32)||
              (($ord_c>=1040)&&($ord_c<=1103)) ) // русские буквы
            {
              //echo " '".$ord_c."' ";
              $new_login.=mb_chr($ord_c);
            }
      };
  return $new_login;
}

// Получить список ip адресов, на основе вывода ipconfig /all
function get_local_ips($out)
{
  global $config;
  $ips=array();
  $nets=array();
  foreach ($out as $str)
    {
      $str = strtoupper($str);
      if ($str[0]!=" ") $i++;
      if (substr(trim($str),0,2)=="IP" and substr(trim($str),0,4)!="IPV6") $nets[$i]['IP'] = trim(substr($str,strpos($str,":")+1,strpos($str,"(")?(strpos($str,"(")-strpos($str,":")-1):strlen($str)));
      if (substr(trim($str),0,4)=="DHCP") $nets[$i]['DHCP'] = trim(substr($str,strpos($str,":")+1));
    }
  foreach ($nets as $net)
    {
      if (substr($net['IP'],0,3)=="10." or substr($net['IP'],0,8)=="192.168." or substr($net['IP'],0,8)=="172.") $ips[] = $net['IP'];
    }
  return $ips;
}


function rev_search($str, $find, $pos)
{
  $str=substr($str,0,$pos);
  $rev_str=strrev($str);
  $rev_find=strrev($find);
  $str_len=strlen($str);
  $find_len=strlen($find);
  if ($str_len<1) return false;
  if ($find_len<1) return false;
  $f_pos=strpos($rev_str,$rev_find);
  if ($f_pos===false) return false;
//echo "<br>\nstrlen:'$str_len',fpos:'$f_pos',find_len'$find_len'";
  $ret_val=$str_len-$f_pos-$find_len;
  return $ret_val;
};

// сформировать гиперссылку на основе текста
function form_hyperlink($str,$short=1)
{
  global $lang;
  $str=trim($str);
  $last_word=$str;
  
  if ($str=='') return '';
  
  /*$last_word_short=substr($str,0,30); //убираем убираем обрезку, чтобы работала настройка "не сокращать в таблице"
  if (strlen($last_word)>35)
  $last_word=$last_word_short."...";*/

  if (!$short)
       $last_word=$str;
     else
  {
   // Выделяем само имя сайта
  if ((strpos($str,"http:")===0)||(strpos($str,"ftps:")===0))
     { $last_word=substr($str,7,strlen($str)); if ($last_word[strlen($last_word)-1]=='/') $last_word=substr($last_word,0,-1);}
  if  (strpos($str,"https:")===0)
     { $last_word=substr($str,8,strlen($str)); if ($last_word[strlen($last_word)-1]=='/') $last_word=substr($last_word,0,-1);}
  if  (strpos($str,"ftp:")===0)
     { $last_word=substr($str,6,strlen($str)); if ($last_word[strlen($last_word)-1]=='/') $last_word=substr($last_word,0,-1);}

  if ((strpos($str,"\\\\")===0)||(strpos($str,"//")===0))
     { // Выделяем последнее слово для file
        $p1=rev_search($str,"\\",strlen($str));
        $p2=rev_search($str,"/",strlen($str));
        if ($p1<$p2) $p1=$p2;
        if ($p1)
           { // ссылка на файл
             $p1++;
             $last_word=substr($str,$p1,255);
           }
     }
  }

  if ((!$last_word)&&($str)) $last_word=$lang["Open"];
  $str_url=$str;
  $str_url=str_replace(" ","%20",$str_url);
  $str_url=str_replace('"',"%22",$str_url);
  $str_url=str_replace("'","%27",$str_url);
  $str_url=str_replace("<","%3C",$str_url);
  $str_url=str_replace(">","%3E",$str_url);
  $str_text=htmlspecialchars(strip_tags($str));
  $last_word=htmlspecialchars(strip_tags($last_word));

  if ((strpos($str,"http:")===0)||(strpos($str,"https:")===0)||(strpos($str,"ftp:")===0)||
      (strpos($str,"ftps:")===0)||(strpos($str,"mailto:")===0)||(strpos($str,"\\\\")===0)||(strpos($str,"file:")===0))
      return "<a href=\"$str_url\" target=\"_blank\">$last_word</a>";

  // Возможно файл
  if ((strpos($str,"\\\\")===0)||(strpos($str,"//")===0))
      return "<a href=\"file://$str_url\" target=\"_blank\">$last_word</a>";

  // Возможно email?
  if (strpos($str,"@"))
      return "<a href=\"mailto:$str_url\">$str_text</a>";
  return "<a href=\"http://$str_url\" target=\"_blank\">$last_word</a>";
}

function htmlSubstr($html, $length)
{
    $out = '';$was_cut=0;
    $single = array('img');
    $arr = preg_split('/(<.+?>|&#?\\w+;)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $tagStack = array();
    for($i = 0, $l = 0; $i < count($arr); $i++) {
        if( $i & 1 ) {
            if ($arr[$i]=="<br>") {
               // если <br>, то обрезаем
               $out.="...";$was_cut=1;
               break;
            }
              elseif ( substr($arr[$i], 0, 2) == '</' ) {
                array_pop($tagStack);
            } elseif( $arr[$i][0] == '&' ) {
                $l++;
            } elseif (substr($arr[$i], -2) != '/>') {
                array_push($tagStack, $arr[$i]);
            }
            $out .= $arr[$i];
        } else {
            if( ($l += mb_strlen($arr[$i])) >= $length ) {
                $out.= mb_substr($arr[$i], 0, $length - $l + mb_strlen($arr[$i]));
                $out.="...";$was_cut=1;
                break;
            } else {
                $out .= $arr[$i];
            }
        }
    }
    while( ($tag = array_pop($tagStack)) !== NULL ) {
        $out .= '</' . strtok(substr($tag, 1), " \t>") . '>';
    }
    if ($was_cut) return $out;
             else return $html;
}

function htmlSubstrTrig($html, $length)
{
  $t1=htmlSubstr($html,$length);
  $t2=htmlSubstr($html,$length+5);
  if (($t1!=$html)&&($t2==$html)) return $html;
     else return $t1;
};


if (!function_exists('mb_ucfirst') && function_exists('mb_substr')) {
     function mb_ucfirst($string) {
          $string = mb_ereg_replace("^[\ ]+","", $string);
          $string = mb_strtoupper(mb_substr($string, 0, 1, "UTF-8"), "UTF-8").mb_substr($string, 1, mb_strlen($string), "UTF-8" );
          return $string;
     }
}

function move_files($source, $destination)
{
  $dh = opendir($source);
  while ($filename = readdir($dh))
    {
      if ($filename!="." and $filename!="..")
        {
          if (is_dir($source."/".$filename))
            {
              if (!file_exists($destination."/".$filename))
                {
                  if (!@rename($source."/".$filename, $destination."/".$filename)) return($destination);
                }
                else
                {
                  $ret_val=move_files($source."/".$filename, $destination."/".$filename);
                  if ($ret_val) return $ret_val;
                }
            }
            else
            {
              if (file_exists($destination."/".$filename)) if (!@unlink($destination."/".$filename)) return($destination."/".$filename);
              if (!@rename($source."/".$filename, $destination."/".$filename)) return($destination);
            }
        }
    }
  closedir($dh);
  rmdir($source);
  return "";
}

// Обновляем версию базы данных, если она устарела
// Перенесено из common.php для увеличения производительности
function check_version_update($table_prefix="")
{
  global $config, $lang, $sql_db_types, $sql_current_link;
  if ($sql_db_types[$sql_current_link]=="postgresql") $pg_db = 1;
  $orig_config=$config;
  include $config['site_path']."/include/config.php";
  $orig_config['version']=$config['version'];
  $config=$orig_config;
  // заменяем префикс
  $config['table_prefix'] = $table_prefix?$table_prefix:$config['table_prefix'];
  // Старый механизм, обновления, на основе версии
  while (true)
    {
      $sqlQuery = "SELECT * FROM ".$config["table_prefix"]."config";
      $result = sql_query($sqlQuery);
      $row = sql_fetch_assoc($result);
      $cur_version = $row['version'];
      if (!$cur_version)
        {
          $result = sql_query("SHOW COLUMNS FROM ".$config['table_prefix']."config LIKE 'key'");
          $old_field = sql_num_rows($result);
          if ($old_field and $pg_db) $key_field = '"key"'; elseif ($old_field) $key_field = "`key`"; else $key_field = "name";
          $sqlQuery = "SELECT * FROM ".$config["table_prefix"]."config WHERE $key_field = 'version'";
          $result = sql_query($sqlQuery);
          $row = sql_fetch_assoc($result);
          $cur_version = $row['value'];
        }
      if (!$cur_version) die("Invalid current version!");
      if ($cur_version < $config['version']) include "bd_update/$cur_version.php"; else break;
    }
  // Обновляем ревизии
  $need_update=array();
  $dir=$config["site_path"]."/bd_update";
  if ($dh = opendir($dir)) {
    while (($file = readdir($dh)) !== false) {
        if (($file=="..")||($file==".")) continue;
        $p=strpos($file,"revision_");
        if ($p!==FALSE)
            {
              $f_rev=intval(substr($file,strlen("revision_"),1024));
              if ($f_rev==0) die("Invalid bd_update file: ".$file);
              // Проверяем, проводился ли данный update
              $sqlQuery = "SELECT * FROM ".$config["table_prefix"]."update_log WHERE revision='$f_rev'";
              $result = sql_query($sqlQuery);
              if (!$result)
                 {
                   include $config["site_path"]."/bd_update/revision_1.php";
                   run_query("INSERT INTO ".$config["table_prefix"]."update_log (revision) VALUES ('1')");
                 }
                 else
              if ($row = sql_fetch_assoc($result))
                 { // UPDATE уже был произведен
                   continue;
                 }
                 else
                   $need_update[]=$f_rev;
            };
        }
    }
    closedir($dh);
  // Сортируем update-ы в порядке возрастания
  sort($need_update);
  foreach ($need_update as $one_rev)
     {
        include $config["site_path"]."/bd_update/revision_".$one_rev.".php";
        run_query("INSERT INTO ".$config["table_prefix"]."update_log (revision) VALUES ('$one_rev')");
     }
  // возвращаем префикс
  $config['table_prefix'] = $orig_config['table_prefix'];
};

// Формируем фильтр на основе прав доступа
function form_rights_filter($table, $user_id)
{
  echo "use depricated: form_rights_filter";
  return "";
}

function base64_url_encode($str) {
    return strtr(base64_encode($str), array('+'=>'-', '/'=>'_'));
}
function base64_url_decode($str) {
    return base64_decode(strtr($str, array('-'=>'+', '_'=>'/')));
}

// Обновить файл цветовой схемы
function update_cur_scheme()
{
  global $config, $smarty;

  // Составляем сайм файл схемы
  $sqlQuery = "SELECT * FROM ".SCHEMES_TABLE." WHERE active=1";
  $result = sql_query($sqlQuery);
  $color_scheme = sql_fetch_assoc($result);
  $smarty->assign("color1", $color_scheme['color1']);
  $smarty->assign("color2", $color_scheme['color2']);
  $smarty->assign("color3", $color_scheme['color3']);
  $smarty->assign("color_scheme", $color_scheme);
  $new_css=$smarty->fetch('templates/cur_scheme.tpl');

  $cur_css_name=$config['site_path'].'/cache/cur_scheme_'.$config['css_id'].'_'.$config['css_id_sheme'].'.css';
  @$cur_css=file_get_contents($cur_css_name);
  if ($cur_css!=$new_css)
     {
        // Удаляем текущий файл css
        @unlink($config['site_path'].'/cache/cur_scheme_'.$config['css_id'].'_'.$config['css_id_sheme'].'.css');
        @unlink($config['site_path'].'/cache/cur_scheme__1.css');

        // Увеличиваем номер последнего обновления
        $config['css_id_sheme']++;
        $sqlQuery = "UPDATE ".CONFIG_TABLE." SET value='".$config['css_id_sheme']."' WHERE name='css_id_sheme'";
        $result = sql_query($sqlQuery);
        $smarty->assign('config',$config);

        // Пишем новый файл
        @$f=fopen($config['site_path'].'/cache/cur_scheme_'.$config['css_id'].'_'.$config['css_id_sheme'].'.css','w');
        if ($f)
            {#000000
              fwrite($f,$new_css);
              fclose($f);
            }
     }
}

// Получить данные о таблице, результат получение кешируется
// Данные доступа формируются на основе текущего пользователя,
// данные кешируются
function get_table($table_id, $cache=1)
{
  global $tables_cache, $user, $config;
  $group_id=$user['group_id'];
  
  if ($cache)
     {
        // Локальный кеш
        if ($tables_cache[$table_id]) return $tables_cache[$table_id];
        // Проверяем кеш memcache
        if ($config['xcache'])
           {
              $table=xcache_get("table_info_".$group_id."_".$table_id);
              if ($table)
                {
                  $tables_cache[$table_id]=$table;
                  return $table;
                }
           }
     }

  $sqlQuery = "SELECT * FROM ".TABLES_TABLE." WHERE id='$table_id'";
  $result = sql_query($sqlQuery);
  $table = sql_fetch_assoc($result);
  if (!$table)
     {
       generate_error("Invalid table_id: ".$table_id);
       exit;
     }
  $table_id = $table['id'];
  $table['display']['name_table'] = form_display($lang['conf'][$table['name_table']]?$lang['conf'][$table['name_table']]:$table['name_table']);
  $table['display']['full_name']  = form_display($lang['conf'][$table['full_name']]?$lang['conf'][$table['full_name']]:$table['full_name']);
  $table['display']['add_text']   = form_display($lang['conf'][$table['add_text']]?$lang['conf'][$table['add_text']]:$table['add_text']);
  $table['display']['edit_text']  = form_display($lang['conf'][$table['edit_text']]?$lang['conf'][$table['edit_text']]:$table['edit_text']);
  $table['display']['def_sort']   = form_display($lang['conf'][$table['def_sort']]?$lang['conf'][$table['def_sort']]:$table['def_sort']);
  $table['display']['help']       = form_display($lang['conf'][$table['help']]?$lang['conf'][$table['help']]:$table['help']);

  $table['user_table_fields'] = $table['user_table_link']?unserialize($table['user_table_link']):array();
  $table['user_table_calcs'] = $table['user_table_fields']['calcs'];

  $table['LOP'] = $table['lop']; // дублируем в другом регистре, для совместимости
  
  // Права доступа
  $table['rule_filter']='';
  $table['rules']=array();
  $cat_acc = sql_select_array(ACC_CATS_TABLE, 'cat_id=',$table['cat_id'],' and group_id=',$group_id);
  if ($cat_acc['access'])
     { // Доступ на уровне категории разрешен
       $table_acc = sql_select_array(ACC_TABLES_TABLE, 'table_id=',$table_id,' and group_id=',$group_id);
       if ($table_acc['acc'])
          {
            $table['access'] = $table_acc['acc'];
            $table['vis'] = $table_acc['vis_acc'];
            $table['add'] = $table_acc['add_acc'];
            $table['del'] = $table_acc['del_acc'];
            $table['arc'] = $table_acc['arc_acc'];
            $table['imp'] = $table_acc['imp_acc'];
            $table['exp'] = $table_acc['exp_acc'];
            $table['adf'] = $table_acc['adf_acc'];
            $table['bed'] = $table_acc['bed_acc'];
            $table['rule_filter'] = $table_acc['rule_filter'];

            // Выбираем правила доступа
            $result3 = sql_select(ACC_RULES_TABLE, 'table_id=',$table_id,' AND (group_id=',$group_id,' OR global=1) ORDER BY num');
            while ($row = sql_fetch_assoc($result3))
              {
                 $rule['id']=$row['id'];
                 $rule['name']=$row['name'];
                 $rule['num']=$row['num'];
                 $rule['condition_php']=$row['condition_php'];
                 $rule['rights']=unserialize($row['rights']);
                 if (!is_array($rule['rights'])) $rule['rights']=array();
                 // Выбираем доступ к таблице по правилу
                 $table['rules'][$rule['id']]=$rule;
              }
          }
     }
  $table['rules_reverse']=array_reverse($table['rules'],1);

  // Проверка, есть ли, доступ к полям из данной таблиц
  $sqlQuery = "SELECT max(view_tb) as max_view_tb, max(view) as max_view FROM ".ACC_FIELDS_TABLE." WHERE group_id='$group_id' AND table_id='$table_id' GROUP BY table_id";
  $result2 = sql_query($sqlQuery);
  if ($row2 = sql_fetch_assoc($result2))
     {
       $table['view_tb'] = $row2['max_view_tb'];
       $table['view']    = $row2['max_view'];
     }

  // Разрешен просмотр по полю связи
  $sqlQuery = "SELECT (max(view)=1 or max(view_tb)=1) as view FROM ".ACC_FIELDS_TABLE." WHERE group_id='$group_id' AND table_id='$table_id' and (view_tb>0  or view>0)  GROUP BY field_id";
  $result2 = sql_query($sqlQuery);
  $table['view_lnk'] = sql_num_rows($result2)>1;
  
  if (is_array($table['rules']))
     {
       $cnt_fields = 0;
       foreach ($table['rules'] as $one_rule)
         { // Перебираем правила, смотрим, есть ли доступ к полю
           foreach ($one_rule['rights'] as $one_right)
             {
               if ( $one_right['field'] and ($one_right['view']==1 or $one_right['view_tb']==1) )
                  {
                    $cnt_fields++;
                  }
             }
         }
       if ($cnt_fields>1) $table['view_lnk'] = 1;
     }

  // Получить id первого поля связи, т.к. оно используется - для связи с подтаблицами, а также используется как новый фильтр в полях связи
  $sqlQuery = "SELECT id FROM ".FIELDS_TABLE. " WHERE table_id=".$table_id." and type_field=5 ORDER BY field_num LIMIT 1";
  $result = sql_query($sqlQuery);
  if ($row = sql_fetch_assoc($result))
     {
       $table['first_link_field']=$row['id'];
     }

  if ($cache)
     {
       $tables_cache[$table_id]=$table;
       if (($config['xcache'])&&(!$user["is_root"]))
            xcache_set("table_info_".$group_id."_".$table_id, $table, 360);
     }
  return $table;
};

function form_link_fields($table, $calculate)
{
  $link_fields=array();
  $table_fields=$table['table_fields'];
  for ($p=0;$p<strlen($calculate);$p++)
   {
     if (substr($calculate,$p,strlen('$line['))=='$line[')
        { // есть переменная
          $s=$p+strlen('$line');
          $t='';
          $l_f=&$link_fields;
          $t_table=$table;
          $t_fields=$table_fields;
          $f_a = array();$f_id=0;
          for (;$s<strlen($calculate);$s++)
              {
               if (((ord($calculate[$s])>96)&&(ord($calculate[$s])<123))||((ord($calculate[$s])>47)&&(ord($calculate[$s])<58))||($calculate[$s]=='-')||($calculate[$s]=='_'))
                   {
                     // наименование переменной побуквенно
                     $t.=$calculate[$s];
                   }
               elseif (($calculate[$s]=="'")||($calculate[$s]=='"'))
                   {

                   }
               elseif ($calculate[$s]=='[')
                   { // начало имени
                   }
               elseif ($calculate[$s]==']')
                   { // Конец имени поля
                     $field_id=$t_table["int_names"][$t];
                     $field=$t_fields[$field_id];
                     $t='';
                     if ($field['type_field']==5)
                        { // Поле связь
                          $l_f["sub_list"][$field['id']]=$field['s_table_id'];
                          if (!$l_f[$field['id']]) $l_f[$field['id']]=array();
                          $l_f=&$l_f[$field['id']];
                          $t_table=get_table($field['s_table_id']);
                          $t_fields=get_table_fields($t_table);
                        }
                   }
                   else
                   {
                     break;
                   }
              }
          $p=$s;
        }
   }
  return $link_fields;
}


// Выполнитль sql запрос
function catch_bk_trace($text)
{
  global $sql_log_trace_text;
  $sql_log_trace_text=$text;
  return "";
}

// ------ Функции работы с файлами
if (!function_exists("get_file_hash"))  // Если пользователь переопределил своими значениями
{
// Получить хеш имя файла таблицы
function get_file_hash($field_id,$line_id,$fname)
{
  return md5($field_id."_".$line_id."_".$fname)."_".$field_id."_".$line_id."_".ord($fname[0]);
}

// Получить полный путь к файлу таблицы
function get_file_path($field_id, $line_id, $fname)
{
  global $config;
  $h=get_file_hash($field_id,$line_id,$fname);
  return $config['site_path']."/files/".substr($h,0,2)."/".substr($h,2,2)."/".$h;
}

// Создать систему каталогов, если она необходимы, для заданного пути файла
function create_data_file_dirs($field_id, $line_id, $fname)
{
  global $config;
  $h=get_file_hash($field_id,$line_id,$fname);
  $d=$config['site_path']."/files/".substr($h,0,2)."/".substr($h,2,2);
  if (!file_exists($d))
     {
        $d1=$config['site_path']."/files/".substr($h,0,2);
        if (!file_exists($d1))
           {
              mkdir($d1,0777);
              // Если необходимы особые настройки прав сохраняем
              if ($config['chown']) chown($d1,$config['chown']);
              if ($config['chgrp']) chgrp($d1,$config['chgrp']);
           }
        mkdir($d,0777);
        // Если необходимы особые настройки прав сохраняем
        if ($config['chown']) chown($d,$config['chown']);
        if ($config['chgrp']) chgrp($d,$config['chgrp']);
     }
}

// Сохранить файл, автоматически будут созданны необходимые каталоги, если они необходимы
function save_data_file($field_id,$line_id,$fname,$data)
{
  global $config;
  create_data_file_dirs($field_id,$line_id,$fname);

  $fname=get_file_path($field_id,$line_id,$fname);
  if ($config['chown_group']) $f=fopen($fname,'w', 0666);
     else $f=fopen($fname,'w');
  fwrite($f,$data);
  fclose($f);
  if ($config['chown']) {chown($fname,$config['chown']&0666);} // Снимаем флаг запуска
  if ($config['chgrp']) {chgrp($fname,$config['chgrp']);}
}

// Удалить файл, автоматически будет удален каталог, если он окажется пуст
function drop_data_file($field_id,$line_id,$fname)
{
  global $config;
  $h=get_file_hash($field_id,$line_id,$fname);
  $d1=$config['site_path']."/files/".substr($h,0,2);
  $d2=$d1."/".substr($h,2,2);
  $fname=get_file_path($field_id,$line_id,$fname);
  @unlink($fname);
  @$dir_files=scandir($d2);
  if (count($dir_files)<3)
     {
       @rmdir($d2);
       @$dir_files=scandir($d1);
       if (count($dir_files)<3) @rmdir($d1);
     }
}

function get_file_type($fname)
{
  $ext_types=array('jpeg'=>'image/jpeg','jpg'=>'image/jpeg','gif'=>'image/gif','png'=>'image/png', 'txt'=>'text/plain', 'sql'=>'text/x-sql', 'php'=>'application/x-httpd-php', 'doc'=>'application/msword', 'odt'=>'application/vnd.oasis.opendocument.text', 'ods'=>'application/vnd.oasis.opendocument.spreadsheet', 'odp'=>'application/vnd.oasis.opendocument.presentation', 'odg'=>'application/vnd.oasis.opendocument.graphics', 'odc'=>'application/vnd.oasis.opendocument.chart', 'odf'=>'application/vnd.oasis.opendocument.formula', 'odi'=>'application/vnd.oasis.opendocument.image', 'odm'=>'application/vnd.oasis.opendocument.text-master', 'odb'=>'application/vnd.oasis.opendocument.base',
                    'ott'=>'application/vnd.oasis.opendocument.text-template', 'ots'=>'application/vnd.oasis.opendocument.spreadsheet-template', 'otp'=>'application/vnd.oasis.opendocument.presentation-template', 'otg'=>'application/vnd.oasis.opendocument.graphics-template', 'otc'=>'application/vnd.oasis.opendocument.chart-template', 'otf'=>'application/vnd.oasis.opendocument.formula-template', 'oti'=>'application/vnd.oasis.opendocument.image-template', 'oth'=>'application/vnd.oasis.opendocument.text-web',
                    'docm'=>'application/vnd.ms-word.document.macroEnabled.12', 'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'dotx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'potm'=>'application/vnd.ms-powerpoint.template.macroEnabled.12', 
                    'potx'=>'application/vnd.openxmlformats-officedocument.presentationml.template', 'ppam'=>'application/vnd.ms-powerpoint.addin.macroEnabled.12', 'ppsm'=>'application/vnd.ms-powerpoint.slideshow.macroEnabled.12', 'ppsx'=>'application/vnd.openxmlformats-officedocument.presentationml.slideshow', 'ppsx'=>'application/vnd.ms-powerpoint.presentation.macroEnabled.12', 'pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'xlam'=>'application/vnd.ms-excel.addin.macroEnabled.12', 'xlsb'=>'application/vnd.ms-excel.sheet.binary.macroEnabled.12', 'xlsm'=>'application/vnd.ms-excel.sheet.macroEnabled.12', 'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xltm'=>'application/vnd.ms-excel.template.macroEnabled.12', 'xltx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
                    'pdf'=>'application/pdf',
                    'zip'=>'application/zip'
                  );
  $ext_p=strrpos($fname,'.');
  if ($ext_p)
     {
       $ext=strtolower(substr($fname,$ext_p+1));
       if ($ext_types[$ext]) return $ext_types[$ext];
     }
  return "";
}

// Удалить все файлы
function drop_files()
{
  global $config;
  $fdir=$config['site_path']."/files";
  $dirs1 = scandir($fdir);
  foreach ($dirs1 as $d1_short)
    {
       if ($d1_short[0]=='.') continue;
       $d1=$fdir."/".$d1_short;
       if (!is_dir($d1)) continue;
       $dirs2 = scandir($d1);
       $dir2_count=count($dirs2)-2;
       foreach ($dirs2 as $d2_short)
           {
              if ($d2_short[0]=='.') continue;
              $d2=$d1."/".$d2_short;
              $fnames = scandir($d2);
              $files_count=count($fnames)-2;
              foreach ($fnames as $fname_short)
                  {
                     if ($fname_short[0]=='.') continue;
                     $fname=$d2."/".$fname_short;
                     unlink($fname);
                     $files_count--;
                  }
              if ($files_count==0)
                 { // Нет файлов в каталоге, удалем его
                   rmdir($d2);
                   $dir2_count--;
                 }
           };
       if ($dir2_count==0) // нет каталогов второго уровня, удаляем каталог первого уровня
           rmdir($d1);
    }
}


// Удалить все файлы по заданому полю
function drop_files_by_field($field_id)
{
  global $config;
  $fdir=$config['site_path']."/files";
  $dirs1 = scandir($fdir);
  foreach ($dirs1 as $d1_short)
    {
       if ($d1_short[0]=='.') continue;
       $d1=$fdir."/".$d1_short;
       if (!is_dir($d1)) continue;
       $dirs2 = scandir($d1);
       $dir2_count=count($dirs2)-2;
       foreach ($dirs2 as $d2_short)
           {
              if ($d2_short[0]=='.') continue;
              $d2=$d1."/".$d2_short;
              $fnames = scandir($d2);
              $files_count=count($fnames)-2;
              foreach ($fnames as $fname_short)
                  {
                     if ($fname_short[0]=='.') continue;
                     $efid=strpos($fname_short,'_',33);
                     if (!$efid) continue; // неформат имени файла
                     $fname=$d2."/".$fname_short;
                     $file_field_id=substr($fname_short,33,$efid-33);
                     if ($field_id==$file_field_id)
                        {  // Поле совпало, удаляем файл
                           unlink($fname);
                           $files_count--;
                        }
                  }
              if ($files_count==0)
                 { // Нет файлов в каталоге, удалем его
                   rmdir($d2);
                   $dir2_count--;
                 }
           };
       if ($dir2_count==0) // нет каталогов второго уровня, удаляем каталог первого уровня
           rmdir($d1);
    }
}

// Удалить все файлы в строке
function drop_files_by_line($table, $line)
{
  $table_fields = get_table_fields($table);
  foreach ($table_fields as $one_field)
    {
      if ($one_field['type_field']==6 or $one_field['type_field']==9)
          {
            $fnames=explode("\r\n",$line[$one_field['int_name']]);
            foreach ($fnames as $fname)
                {
                  drop_data_file($one_field['id'],$line['id'],$fname);
                }
          }
    }
}

// Удалить файл из базы, по пути на диске
function drop_file_by_path($path)
{
  global $config;
  @unlink($path);

  $last_slash=strrpos($path,'/');
  if (!$last_slash) return;
  $last_slash++;
  $fname_short=substr($path,$last_slash);
  $efid=strpos($fname_short,'_',33);
  if (!$efid) continue; // неформат имени файла
  $fname=$d2."/".$fname_short;
  $file_field_id=intval(substr($fname_short,33,$efid-33));
  $efid++;
  $elid=strpos($fname_short,'_',$efid);
  $file_line_id=intval(substr($fname_short,$efid,$elid-$efid));
  $f_char = chr(intval(substr($fname_short,$elid+1)));
  // Выбираем информацию по полю
  $sqlQuery = "SELECT * FROM ".FIELDS_TABLE. " WHERE id='".$file_field_id."' ORDER BY field_num";
  $result = sql_query($sqlQuery);
  $field_info = sql_fetch_assoc($result);
  $field_info['int_name']="f".$field_info['id'];
  if (!$field_info) return ;
  $sqlQuery = "SELECT * FROM ".TABLES_TABLE. " WHERE id='".$field_info['table_id']."'";
  $result = sql_query($sqlQuery);
  $table = sql_fetch_assoc($result);
  if (!$table) return;

  // Выбираем файлы в данной строке из базы
  $sqlQuery = "SELECT * FROM ".DATA_TABLE.$table['id']." WHERE id='$file_line_id'";
  $result2 = sql_query($sqlQuery);
  while ($line = sql_fetch_assoc($result2))
    {
      $fnames=explode("\r\n",$line[$field_info['int_name']]);
      $new_fnames=array();
      $dont_exists=0;
      foreach ($fnames as $fname)
          {
              if (!$fname) continue;
              $f_path=get_file_path($field_info['id'],$line['id'],$fname);
              if (file_exists($f_path))
                {
                  $new_fnames[]=$fname;
                }
                else
                  $dont_exists=1;
          }
      if ($dont_exists)
          {
            $fnames_line=implode("\r\n",$new_fnames);
            $sqlQuery = "UPDATE ".DATA_TABLE.$table['id']." SET ".$field_info['int_name']."='".form_sql($fnames_line)."' WHERE id='$file_line_id'";
            sql_query($sqlQuery);
          };
    }
}
}
// ====== Функции работы с файлами

// Удаление папки с файлами
function unlinkRecursive($dir, $deleteRootToo=0)
{
  if(!$dh = @opendir($dir)) return;
  while (false !== ($obj = readdir($dh)))
    {
      if($obj == '.' || $obj == '..')
         continue;

      if (!@unlink($dir . '/' . $obj))
          unlinkRecursive($dir.'/'.$obj, true);
  }

  closedir($dh);

  if ($deleteRootToo)
      @rmdir($dir);
  return;
}

// Сформировать каталоги для распаковки zip архива
// Является обходом бага Zip PHP 5.3 при safe_mode
function create_zip_dirs($base_dir_path, $files)
{
  // Считаем что в каждой папке существуют файлы
  foreach ($files as $one_fname)
    {
      $one_fname = str_replace('\\','/',$one_fname);
      if ($p=strrpos($one_fname,'/'))
         {
           $fname = substr($one_fname,0,$p);
           @mkdir($base_dir_path."/".$fname, 0777, 1);
         }
    }
}

// Сформировать полный путь до файла из имени
function form_path($file_name)
{
  global $config;
  $file_name=str_replace('\\','/',$file_name);
  if (($file_name[0]=='/')||($file_name[1]==':'))
     { // Абсолютный путь
       return $file_name;
     }
     else
     {
       return $config['site_path']."/".$file_name;
     }
}

function term_array($type_field, $mult_value)
{
  global $lang;
  if ($type_field==1 or $type_field==8 or $type_field==10)
    $term_arr = array('='=>$lang['='],'!='=>$lang['!='],'>'=>$lang['>'],'<'=>$lang['<'],'>='=>$lang['>='],'<='=>$lang['<=']);
  elseif ($type_field==2 or $type_field==12)
    $term_arr = array('='=>$lang['='],'!='=>$lang['!='],'>'=>$lang['>'],'<'=>$lang['<'],'>='=>$lang['>='],'<='=>$lang['<='],'period'=>$lang['period']);
  elseif ($type_field==3 or $type_field==4 or $type_field==5 or $type_field==6 or ($type_field==7 and $mult_value) or $type_field==9 or $type_field==11 or $type_field==14 or !$type_field)
    $term_arr = array('='=>$lang['='],'!='=>$lang['!='],' LIKE '=>$lang['LIKE'],' NOT LIKE '=>$lang['NOT LIKE']);
  elseif ($type_field==7 and !$mult_value)
    $term_arr = array('='=>$lang['='],'!='=>$lang['!=']);
  else
    $term_arr = array(''=>'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');
  return $term_arr;
}

function get_name_record($table, $table_fields, $line)
{
  if ($table['edit_text'])
    {
      $name_record = $table['edit_text'];
      foreach ($table_fields as $one_field)
        {
          $name_record = str_replace("{".$one_field['name_field']."}", form_display_type($one_field, $line, "text"), $name_record);
        }
      if ($name_record!=="") return $name_record;
    }
  return $line['id'];
}

function form_charset_select($charset)
{
 global $lang;
 $charsets=array(""=>array("utf-8"=>"UTF-8"), "russian"=>array("cp1251"=>"Windows-1251", "cp866"=>"CP-866", "koi8-r"=>"KOI8-R"), "ukrainian"=>array("koi8-u"=>"KOI8-U"));
 $res="<option value=''>".$lang['auto']."</option>";
 foreach ($charsets as $g_name=>$group)
   {
     $g_name=strtoupper($g_name[0]).substr($g_name,1);
     if ($g_name) $res.="<optgroup label='$g_name'>";
     foreach ($group as $chrst=>$descr)
        {
          $res.="<option value='$chrst' ";
          if ($chrst==$charset) $res.="selected";
          $res.=">$descr</option>";
        }
     if ($g_name) $res.="</optgroup>";
   }

 return $res;
}

function insert_tip($tip_id, $line, $user_id=-1)
{
  global $user;
  if ($user_id!=-1)
     {
       $old_user=$user;   
       $user = sql_select_array(USERS_TABLE, 'id=',$user_id);
       $tables_cache=array(); // Сбрасываем кеш таблиц, при переключении пользователя
     }
 
  $cur_date = date("Y-m-d H:i:s");
  $tip = sql_select_array(TIPS_TABLE, 'id=',$tip_id) ;
  if (!$tip) return;
  $table_id=$tip['table_id'];
  $table = get_table($table_id);
  $table_fields = get_table_fields($table);


  $shablons = array();
  $values   = array();

  foreach ($table_fields as $one_field)
  {
    $shablons[] = "{".$one_field['name_field']."}";
    $values[]   = form_display_type($one_field, $line, "tips");
  }

  $email_subject       = str_replace($shablons, $values, $tip['email_subject']);
  $message             = str_replace($shablons, $values, $tip['message']);
  $one_tip = array();
  $one_tip['tip_id']   = $tip['id'];
  $one_tip['table_id'] = $tip['table_id'];
  $one_tip['line_id']  = $line['id'];
  $one_tip['bg_color'] = $tip['bg_color'];
  $one_tip['message']  = $message;

  $result = sql_select(TIPS_SHOW_TABLE, "tip_id=",$tip['id']," and user_id=",$user['id']," and table_id='",$table_id,"' and line_id=",$line['id']) ;
  $tip_show = sql_fetch_assoc($result);
  if ($tip_show)
     { // Уже отображается, лишь обновляем текст
       $sqlQuery = "UPDATE ".TIPS_SHOW_TABLE." SET message='".form_sql($message)."', hidden='0', inactive='0' where id = '".$tip_show['id']."'";
       sql_update(TIPS_SHOW_TABLE, array('message'=>$message,
                                         'hidden' =>0,
                                         'inactive'=>0),
                                   'id = ',$tip_show['id']);
     }
     else
  if ($tip["head_on"])
     {
       $sqlQuery = "INSERT INTO ".TIPS_SHOW_TABLE." (user_id, tip_id,table_id, line_id, bg_color, message, date) VALUES ('".$user["id"]."','".$one_tip['tip_id']."', '".$one_tip['table_id']."', '".$one_tip['line_id']."', '".$one_tip['bg_color']."', '".form_sql($one_tip['message'])."', '$cur_date')";
       sql_insert(TIPS_SHOW_TABLE, array('user_id' =>$user['id'],
                                         'tip_id'  =>$one_tip['tip_id'],
                                         'table_id'=>$one_tip['table_id'],
                                         'line_id' =>$one_tip['line_id'],
                                         'bg_color'=>$one_tip['bg_color'],
                                         'message' =>$one_tip['message'],
                                         'date'    =>$cur_date));
     }
 
  if ($tip["email_on"])
     {
        if (!$tip["head_on"])
          { // Если только отсылаем по email и не сохраняем в шапке, то сохраняем в архиве всеравно
            sql_insert(TIPS_SHOW_ARHIVE_TABLE, array('user_id'  => $user["id"],
                                                     'tip_id'   => $one_tip['tip_id'],
                                                     'table_id' => $one_tip['table_id'],
                                                     'line_id'  => $one_tip['line_id'],
                                                     'bg_color' => $one_tip['bg_color'],
                                                     'message'  => $one_tip['message'],
                                                     'date'     => $cur_date));
          }
        // отсылать по email
        $record_link = $config["site_url"]."/view_line2.php?table=".$tip['table_id']."&line=".$line['id'];
        $m_t = sql_select_array(MAIL_TEMPLATES_TABLE, 'id=',$tip['email_template']);
        $mail_body = str_replace(array('{fio}','{tip_text}','{record_link}'),array($user['fio'],$message,$record_link),$m_t['body']);
        $mail_subj = $email_subject?$email_subject:$m_t['subject'];
        sendmail($mail_subj, $mail_body, $user['e_mail'], "", $m_t['sender']);
     }
  if ($tip["sms_on"])
     {
      if (!$tip["head_on"])
        { // Если только отсылаем по sms и не сохраняем в шапке, то сохраняем в архиве всеравно
          sql_insert(TIPS_SHOW_ARHIVE_TABLE, array('user_id'  => $user["id"],
                                                     'tip_id'   => $one_tip['tip_id'],
                                                     'table_id' => $one_tip['table_id'],
                                                     'line_id'  => $one_tip['line_id'],
                                                     'bg_color' => $one_tip['bg_color'],
                                                     'message'  => $one_tip['message'],
                                                     'date'     => $cur_date));
        }
      // отсылать по sms
      sendsms($message, $user['phone'],$tip['sms_sender']);
     }
  if ($user_id!=-1)
     {
        $user=$old_user;
        $tables_cache=array(); // Сбрасываем кеш таблиц, при переключении пользователя
     }
};

function generate_password($len_min=7, $len_max=10, $chars=0)
{
  $i=0;
  $ret_val="";
  for ($i=0;$i<$len_max;$i++)
      {
         if (($i>=$len_min)&&(rand(0,3)==2)) break;
         if ($chars&&(rand(0,10)==5))
              $ret_val.=chr(rand(97,122));
            else
         if ($chars&&(rand(0,20)==5))
            {
              $r=rand(0,5);
              if ($r==0) $ret_val.='*';
              if ($r==1) $ret_val.='+';
              if ($r==2) $ret_val.='-';
              if ($r==3) $ret_val.='=';
              if ($r==4) $ret_val.='#';
              if ($r==5) $ret_val.='!';
            }
            else
            {
              $r=rand(0,1);
              if ($r) $ret_val.=chr(rand(65,90));
                 else $ret_val.=chr(rand(97,122));
            }
      }
  return $ret_val;
}

// Сохранить в файл все данные синхронизации
function flush_sync($manual_export = false)
{
  global $sync_exp_list, $sync_exp_data, $sync_exp_fields, $sync_exp_rfields, $sync_exp_rtables, $config, $tables_cache, $config, $lang;
  if (!$sync_exp_data) $sync_exp_data=array();
  if (!$sync_exp_list)
    {
      // Считываем настройки синхронизации, для экспорта
      $sync_exp_list=array();
      if ($manual_export)
          $result = sql_select(SYNC_TABLE, "enabled=1 and id=",$manual_export);
      else
          $result = sql_select(SYNC_TABLE, "enabled=1");

      while ($one_sync = sql_fetch_assoc($result))
        {
           $one_sync['tables']=array();
           $sync_exp_list[$one_sync['id']]=$one_sync;
        }
      $sync_exp_fields=array();
      $sqlQuery = "SELECT a.table_id, b.* FROM ".FIELDS_TABLE." a, ".SYNC_FIELDS_TABLE." b WHERE a.id=b.field_id and b.enabled='1'";
      $result2 = sql_query($sqlQuery);
      while ($one_field = sql_fetch_assoc($result2))
        {
          if (!$sync_exp_list[$one_field['sync_id']]) continue;
          if ($one_field['direction'] == 0)
            {
              $sync_exp_rfields[$one_field['field_id']][$one_field['sync_id']] = 1;
              $sync_exp_rtables[$one_field['table_id']][$one_field['sync_id']] = 1;
            }
          else
            {
              $sync_exp_fields[$one_field['field_id']][$one_field['sync_id']]=array('sync_id'=>$one_field['sync_id'], 'field_id'=>$one_field['field_id'], 'c_field'=>($sync_exp_list[$one_field['sync_id']]['sync_mode']==0?$one_field['c_field']:0), 'filter_id'=>$one_field['filter_id']);
              $sync_exp_list[$one_field['sync_id']]['fields'][$one_field['field_id']]=array('field_id'=>$one_field['field_id'], 'table_id'=>$one_field['table_id']);
              $sync_exp_list[$one_field['sync_id']]['tables'][$one_field['table_id']]=$one_field['table_id'];
            }
        }
    }

  // Поворачиваем sync_exp_data чтобы базой стала строка
  $sync_exp_data_lines=array();
  foreach ($sync_exp_data as $sync_id=>$sync)
   {
      foreach ($sync as $field_id=>$line)
       {
        foreach ($line as  $line_id=>$value)
          {
            $sync_exp_data_lines[$sync_id][$line_id][$field_id]=$value;
          }
       }
   }

  // Выбираем также строки которые были добавлены в базу, без использования кб.
  $sync_talbes = array();
  if (!$manual_export)
    {
      foreach ($sync_exp_list as $sync_id=>$sync)
        {
          foreach ($sync['tables'] as $table_id=>$table_info)
            {
              $table=get_table($table_id);
              $table_fields = get_table_fields($table);
              $result2 = data_select($table_id, "s$sync_id='' and status<3");
              while ($one_line = sql_fetch_assoc($result2))
                {
                    $line_id=$one_line['id'];
                    $sync_line_id="^".$line_id;
                    // Cтавим отметку что строка была синхронизированна
                    data_update($table_id, array("s".$sync_id=>"S"), "id=",$line_id," and s$sync_id=''");
                    if ($sync_exp_data_lines[$sync_id][$sync_line_id]) continue ; // Строка уже добавлена в синхронизацию
                    foreach ($sync['fields'] as $field_id=>$field_info)
                        {
                          if (!$table_fields[$field_id]) continue;
                          $value = $one_line[$table_fields[$field_id]['int_name']];
                          if ($table_fields[$field_id]['type_field'] == 5 AND ($sync_exp_list[$sync_id]['tables'][$table_fields[$field_id]["s_table_id"]] OR $sync_exp_rtables[$table_fields[$field_id]["s_table_id"]][$sync_id]) AND $sync_exp_list[$sync_id]['sync_mode'])
                            { // Поле связи
                              $link_field_data = data_select_array($table_fields[$field_id]['s_table_id'], "id=",$value);
                              if (!$link_field_data) continue;
                              if ($link_field_data["s".$sync_id] == "S")
                                { // Целевая строка не засинхронизирована
                                  $cache_res = sql_select_field(SYNC_CACHE_TABLE, "id", "sync_id=",$sync_id," and field_id=",$table_fields[$field_id]['s_field_id']," and line_id=",$value);
                                  if (!sql_fetch_assoc($cache_res)) // В кэше на выгрузку записи тоже нет, убираем S для того, чтобы строка добавилась в список для синхронизации
                                      data_update($table_fields[$field_id]['s_table_id'], array("s".$sync_id=>""), "id=",$value);
                                  // Добавляем текущую строку в кэш для выгрузки
                                  sql_insert(SYNC_CACHE_TABLE, array("sync_id"=>$sync_id, "table_id"=>$table_id, "field_id"=>$field_id, "line_id"=>$line_id, "value"=>"^".$value, "upload_count"=>1, "last_upload"=>date("Y-m-d H:i:s")));
                                }
                              else // Целевая запись засинхронизирована, выгружаем поле связи
                                {
                                  $value = $link_field_data["s".$sync_id];
                                  $sync_exp_data[$sync_id][$field_id][$sync_line_id]=$value;
                                  sql_insert(SYNC_CACHE_TABLE, array("sync_id"=>$sync_id, "table_id"=>$table_id, "field_id"=>$field_id, "line_id"=>$line_id, "value"=>$value, "upload_count"=>1, "last_upload"=>date("Y-m-d H:i:s")));
                                }
                            }
                          else
                            {
                              $sync_exp_data[$sync_id][$field_id][$sync_line_id]=$value;
                              $sync_exp_data_lines[$sync_id][$sync_line_id][$field_id]=$value;
                              // Добавляем в кэш
                              sql_insert(SYNC_CACHE_TABLE, array("sync_id"=>$sync_id, "table_id"=>$table_id, "field_id"=>$field_id, "line_id"=>$line_id, "value"=>$value, "upload_count"=>1, "last_upload"=>date("Y-m-d H:i:s")));
                            }
                          // Если поле файл или изображение - необходимо засинхронизировать и файлы
                          if (($table_fields[$field_id]['type_field'] == 6 OR $table_fields[$field_id]['type_field'] == 9) AND $value != "")
                            {
                              $files_list = explode("\r\n", $value);
                              foreach ($files_list AS $one_filename)
                                  sync_files_export($sync_id, $line_id, $field_id, $one_filename);
                            }
                        };
                }
            }
        }
    }
  if (!$sync_exp_data) return false;

  foreach ($sync_exp_data as $sync_id=>$sync)
   {
      foreach ($sync as $field_id=>$line)
       {
        foreach ($line as  $line_id=>$value)
          {
              $value = str_replace("\r\n", "\\".chr(10)."\\".chr(13), $value);

              if ($sync_exp_list[$sync_id]['sync_mode'] OR $field_id == "ID" OR $field_id == "SYNC_COMMAND") $fld_id = $field_id;
              else $fld_id = $sync_exp_fields[$field_id][$sync_id]['c_field'];

              $sync_out[$sync_id][$line_id][$fld_id]=$value;

              if (strpos($line_id, "^") === 0) // Строка новая, проверям наличие поля ID для синхронизации
                {
                  $f_table_id = $sync_exp_list[$sync_id]['fields'][$field_id]['table_id'];
                  if ($id_fields[$f_table_id])
                      $id_field = $id_fields[$f_table_id];
                  else
                    {
                      $result = sql_select_field(FIELDS_TABLE, "id", "table_id=",$f_table_id," and type_field=10");
                      $row = sql_fetch_assoc($result);
                      $id_field = $row['id'];
                      $id_fields[$f_table_id] = $id_field;
                    }
                  if ($sync_exp_list[$sync_id]['fields'][$id_field] AND !$sync_exp_data[$sync_id][$id_field][$line_id])
                    {
                      $sync_exp_data[$sync_id][$id_field][$line_id] = substr($line_id, 1);
                      if ($sync_exp_list[$sync_id]['sync_mode'] OR $field_id == "ID" OR $field_id == "SYNC_COMMAND") $fld_id = $id_field;
                      else $fld_id = $sync_exp_fields[$id_field][$sync_id]['c_field'];
                      $sync_out[$sync_id][$line_id][$fld_id] = substr($line_id, 1);;
                    }
                }
          }
       }
   }

   $filters_cache=array();$fields_cache=array();
   foreach ($sync_out AS $s_id => $s_lines)
     {
       $file_num = 1;
       $line_num = 1;

       foreach ($s_lines AS $s_line => $s_fields)
         {
           $was_checked=0;
           foreach ($s_fields AS $s_field => $s_value)
             {
               if ($sync_exp_fields[$s_field][$s_id]['filter_id'] && $was_checked==0)
                  { // Экспорт по фильтру
                    $filter_id=$sync_exp_fields[$s_field][$s_id]['filter_id'];
                    if ($fields_cache[$s_field])
                       {
                         $table_id=$fields_cache[$s_field];
                       }
                       else
                       { // Проходимся по всему кешу таблиц и находим поле
                         $table_id=-1;
                         foreach ($tables_cache as $one_table)
                           {
                             if ($one_table['table_fields'][$s_field])
                                {
                                  $table_id=$one_table['id'];break;
                                }
                           }
                         if (!$table_id)
                            { // В кеше отсутвует, выбираем из базы
                              $result = sql_select(FIELDS_TABLE, "id=",$s_field);
                              $row = sql_fetch_assoc($result) or die("Sync invalid table id for field ".$s_field);
                              $table_id=$row['table_id'];
                            }
                         $fields_cache[$s_field]=$table_id;
                       }
                    if ($filters_cache[$filter_id])
                       {
                         $filter_cond=$filters_cache[$filter_id];
                       }
                       else
                       {
                          $result = sql_select(FILTERS_TABLE, "id=",$filter_id);
                          $row = sql_fetch_assoc($result);
                          $filter_cond=$row['value'];
                          $filters_cache[$filter_id]=$filter_cond;
                       }
                    if (!$filter_cond)
                       {
                          $was_checked=1;
                       }
                       else
                       {
                          $real_line_id=$s_line;
                          if ($real_line_id[0]=='^') $real_line_id=substr($real_line_id,1);

                          if ($sync_exp_list[$s_id]['sync_mode'])
                             // В случае активной синхронизации, если номер строки начинается на ^ то выдается локальный ID, если без шапки то выдается поле S
                             if ($s_line[0]!='^')
                                  $result = data_select_field($table_id, "count(*) as cnt", "s",$s_id,"='",$real_line_id,"' and ($filter_cond)");
                                else
                                  $result = data_select_field($table_id, "count(*) as cnt", "id=",$real_line_id," and ($filter_cond)");
                          else
                              // В случае пассивной синхронизации всегда передается id строки
                              $result = data_select_field($table_id, "count(*) as cnt", "id=",$real_line_id," and ($filter_cond)");


                          $row = sql_fetch_assoc($result);
                          if ($row['cnt']<1)
                             { // Ошибка чтения строки по фильтру, пропускаем
                               break;
                             }
                             else
                              $was_checked=1;
                       }
                  }

               if (!$sync_exp_list[$s_id]['data'][$file_num]) $sync_exp_list[$s_id]['data'][$file_num] = array();

               if (!in_array($s_field.";".$s_line.";".$s_value, $sync_exp_list[$s_id]['data'][$file_num]))
                 {
                   $sync_exp_list[$s_id]['data'][$file_num][] = $s_field.";".$s_line.";".$s_value;
                   $line_num += 1;
                 }
             }
           if ($line_num > SYNC_STRINGS)
            {
              $file_num += 1;
              $line_num = 1;
            }
         }
     }

  $fnames = array();

  foreach ($sync_exp_list as $sync_id=>$sync)
   {
     if (!$sync['data']) continue;

     if ($sync['sync_mode'] == 3 && !$config["modules"]['1csync'])
       {
          if ($sync['log_on']) insert_log("sync", " <a href='edit_sync.php?sync_id=$sync_id'>".$lang['Sync']."</a>: ".$lang['Sync_error']." 1C integration is not activated.", 0, 0, $sync_id);
          continue;
       }

     $fcount = count($sync['data']);

     foreach ($sync['data'] AS $sfile => $sync_part)
      {
        $fname =  strval(microtime(true));

        while (in_array($fname, $fnames)) $fname = strval(microtime(true));

        $fnames[] = $fname;

        while (strlen(substr($fname, strpos($fname, "."))) < 5) $fname .= "0";

        if (strpos($fname, ".") === false) $fname .= ".0000";

        if ($sync['sync_mode'] == 3)
            $fname .="82c1.log";
        else
             $fname .=".log";

        if ($sync['type_mode'])
            $sync['upload_dir'] = "temp/sync_".$sync_id."/export";

        if (!is_dir($sync['upload_dir']))
          {
            $fl_error = 'Sync_no_upl_dir';
            if ($config['event_on']['sync']) insert_log('sync', $lang['Sync_error']." ".$lang['Sync_no_upl_dir']." <a href=\"edit_sync.php?sync_id=$sync_id\">".$lang['Sync_set_check']."</a>");
            continue;
          }
        if (($f = fopen($sync['upload_dir']."/".$fname.".tmp", "w")) == false)
          {
            $fl_error = 'invalid_write_rights';
            if ($config['event_on']['sync']) insert_log('sync', $lang['Sync_error']." ".$lang['no_write_to_folder']." ".$sync['upload_dir']);
            continue;
          }
        fwrite($f, implode("\r\n", $sync_part)."\r\n");
        fclose($f);
        rename($sync['upload_dir']."/".$fname.".tmp", $sync['upload_dir']."/".$fname);
      }
      $sync_exp_list[$sync_id]['data'] = array();
   }

  $sync_exp_data = array(); // Чистим данные

  if ($fl_error) return $fl_error;
  else return false;
}

// Вычисление в ограниченной области видимости
function in_eval($str)
{
  global $config, $user, $smarty, $lang, $ses_id, $button_id, $send_report, $csrf;
  return eval($str);
}    

function mb_str_replace($needle, $replacement, $haystack) {
   return implode($replacement, mb_split($needle, $haystack));
}

function html_form($str)
{
  $str=str_replace('<br>',"\n",$str);
  $str=str_replace('<BR>',"\n",$str);
  $str=str_replace('</div><div>',"\n",$str);
  $str=str_replace('</DIV><DIV>',"\n",$str);
  $str=str_replace('<div>',"\n",$str);
  $str=str_replace('<DIV>',"\n",$str);
  $str=str_replace('</div>', "",$str);
  $str=str_replace('</DIV>', "",$str);
  $str=str_replace('<span>',"",$str);
  $str=str_replace('</span>',"",$str);
  $str=str_replace('<SPAN>',"",$str);
  $str=str_replace('</SPAN>',"",$str);;
  $str=str_replace('&nbsp;',' ',$str);
  $str=str_replace(' ',' ',$str); // Заменяем тонкий пробел Chrome, на обычный
  $str=html_entity_decode($str,ENT_QUOTES,"UTF-8");
  $str=htmlspecialchars($str);
  $str=trim($str);
  return $str;
}

function get_link_lines($field, $term, $value)
{
  $ta = explode("|",$field['type_value']); list ($s_table_id, $s_field_id, $s_filter_id, $s_show_field_name, $s_show_field_inline) = $ta;
  if ($field['links_also_show'])
    {
      $links_also_show = $field['links_also_show'];
    }
    else
    {
      $links_also_show = array();
      $result = sql_select(FIELDS_LINKS_SHOW_TABLE, "field_id='",$field['id'],"' ORDER BY id");
      while ($row = sql_fetch_assoc($result)) $links_also_show[] = $row['show_field'];
    }
    
  if ($links_also_show and $s_show_field_inline)
    { // если значение поля связи состоит из нескольких полей, поиск ведем без учета типа поля
      if ($term==" LIKE " or $term==" NOT LIKE ") $f_value = "%".form_sql($value)."%"; else $f_value = form_sql($value);
      $filter = "CONCAT(CAST(".form_int_name($s_field_id)." AS CHAR)";
      foreach ($links_also_show as $inf_field_id)
        {
          // проверяем не на поле связи ли указывает значение верхнего поля связи
          $result = sql_select(FIELDS_TABLE, "id='",$inf_field_id,"' LIMIT 1");
          $row = sql_fetch_assoc($result);
          if ($row['type_field']!=5)
             {
               $field_delimiter = " ";
               if ($s_show_field_name)
                  {
                     $s_table = get_table($s_table_id);
                     $s_table_fields = get_table_fields($s_table);
                     $field_delimiter = " ".$s_table_fields[$inf_field_id]['name_field'].": ";
                  };
               $filter.= ", '".$field_delimiter."', CAST(".form_int_name($inf_field_id)." AS CHAR)";
             }
          else
          {
            $tre_link_value = explode("|", $row['type_value']);
            $tre_link_table = $tre_link_value[0];
            $tre_link_field = $tre_link_value[1];
            $tre_link = $inf_field_id;
            break;
          }
        }
      $filter.= ") ".$term." '".$f_value."'";
    }
    else
    {
      $s_field = sql_select_array(FIELDS_TABLE, "id=",$s_field_id);
      if ($s_field['type_field']==7 or $s_field['type_field']==11)
        {
          if ($term==" LIKE " or $term==" NOT LIKE ") $filter = "fio LIKE '%".form_sql($value)."%'"; else $filter = "fio = '".form_sql($value)."'";
          $result = sql_select_field(USERS_TABLE, "id", $filter);
          while ($row = sql_fetch_assoc($result)) $f_users[] = $row['id'];
          if ($value==="") $f_users[] = 0; // если поиск по пустой строке, добавляем пустые поля (id=0)
          $f_users = $f_users?implode(",", $f_users):"";
          if ($term==" LIKE " or $term=="=") $f_term = " IN "; else $f_term = " NOT IN ";
          $filter = $f_users!==""?form_int_name($s_field_id)." ".$f_term." (".$f_users.")":"";
        }
        elseif ($s_field['type_field']==14)
        {
          if ($term==" LIKE " or $term==" NOT LIKE ") $filter = "name LIKE '%".form_sql($value)."%'"; else $filter = "name = '".form_sql($value)."'";
          $result = sql_select_field(GROUPS_TABLE, "id", $filter);
          while ($row = sql_fetch_assoc($result)) $f_groups[] = $row['id'];
          if ($value==="") $f_groups[] = 0; // если поиск по пустой строке, добавляем пустые поля (id=0)
          $f_groups = $f_groups?implode(",", $f_groups):"";
          if ($term==" LIKE " or $term=="=") $f_term = " IN "; else $f_term = " NOT IN ";
          $filter = $f_groups!==""?form_int_name($s_field_id)." ".$f_term." (".$f_groups.")":"";
        }
        elseif ($s_field['type_field']==4)
        {
          $filter = form_sql_type($s_field, $value, "search", $term);
        }
        elseif ($s_field['type_field']==5)
        {
          $f_lines = get_link_lines($s_field, $term, $value);
          $filter = $f_lines!==""?(form_int_name($s_field_id)." in (".$f_lines.")"):"";
        }
        elseif ($s_field['type_field']!=9 and $s_field['type_field']!=13)
        {
          $search_value = form_sql(form_sql_type($s_field, $value, "search"));
          if ($term==" LIKE " or $term==" NOT LIKE ") $f_value = "%".$search_value."%"; else $f_value = $search_value;
          $filter = $search_value!==""||$value===""?(form_int_name($s_field_id)." ".$term." '".$f_value."'"):"";
        }
    }
  $f_lines = array();
  if (!$tre_link) $result = data_select_field($s_table_id, "id", $filter?$filter:"0");
  else $result = data_select($s_table_id, "1=1");
  while ($row = sql_fetch_assoc($result))
    {
      if ($tre_link)
        {
          $tre_sum = form_sql($row[form_int_name($s_field_id)]);
          foreach ($links_also_show as $inf_field_id)
            {
              if ($inf_field_id==$tre_link)
                {
                  $q = data_select($tre_link_table, 'id='.$row[form_int_name($inf_field_id)]);
                  $d = sql_fetch_assoc($q);
                  $tre_link_value = $d[form_int_name($tre_link_field)];
                }
                else
                {
                  $tre_link_value = $row[form_int_name($inf_field_id)];
                }
              if ($tre_link_value=='0') $tre_sum.= " ";
              else $tre_sum.= " ".$tre_link_value;
            }
          if (trim($f_value) == trim($tre_sum)) $f_lines[] = $row['id'];
        }
        else $f_lines[] = $row['id'];
    }
  if (($term=="=" and $value==="") or ($term=="!=" and $value!=="")) $f_lines[] = 0; // добавляем пустые поля связи (id=0)
  $f_lines = $f_lines?implode(",", $f_lines):"";
  return $f_lines;
}

// Получение динамически формируемых полей
function get_control($field, $line, $value)
{
  global $tabindex_fast_edit, $lang;

  $field_id = $field['id'];
  $line_id = $line['id'];
  $f_table_id = $field['table_id'];
  $f_table    = get_table($f_table_id);
  $f_table_fields = get_table_fields($f_table);
  $field = $f_table_fields[$field_id];

  if ($field["type_field"] == 1 OR $field["type_field"] == 8 OR $field["type_field"] == 10 OR $field["type_field"] == 3) // Текст или число
    {
      if ($field["type_field"] == 3) $value = form_display($value);
      else $value = form_local_number($value, $field['dec_dig']);

      if (test_allow_read($field, $line, "read"))
        {
          if ($field['width']) $adds_style = "\nstyle='width:".$field['width']."px;'";
          if ($field['mult_value']) $mult_value = 1;
          else $mult_value = 0;

      $control = <<<EOD
<span tabindex="800" onfocus="var t=this.nextSibling; t.contentEditable=true; t.focus();"></span><div 
id="fast_edit_span_{$field_id}_{$line_id}"
tabindex="800"
contentEditable=false
class="fast_edit_text " 
$adds_style
field_id="{$field_id}"
line_id="{$line_id}"
mult_value="{$mult_value}";
>{$value}</div><script>addHandler_text(document.getElementById('fast_edit_span_{$field_id}_{$line_id}'));
</script>
EOD;
        }
      else $control = $value;
    }
  elseif ($field['type_field'] == 2 OR $field["type_field"] == 12) // тип поля дата
    {
      $value = form_local_time($value, $field['type_value'], 0);

      if (test_allow_read($field, $line, "read"))
        {
          if ($field["type_value"]) $input_lenght=19;
          else $input_lenght=10;

      $control = <<<EOD
<span class='datepicker_span'><input id="fast_edit_span_{$field_id}_{$line_id}" type=text 
SIZE=10
MAXLENGTH=10
class='datepicker '
tabindex="800"
value='{$value}'
field_id="{$field_id}"
line_id="{$line_id}"
></span><script>addHandler_date(document.getElementById('fast_edit_span_{$field_id}_{$line_id}'));
$("#fast_edit_span_{$field_id}_{$line_id}").datepicker({showOn:"button", buttonImage: "images/calbtn.png", buttonImageOnly: true, buttonText: "{$lang['Calendar']}", showAnim: (('\v'=='v')?"":"show")});
</script>
EOD;
        }
      else $control = $value;
    }
  elseif ($field["type_field"]==4 OR $field["type_field"]==13 OR $one_field["type_field"]==7 OR $one_field["type_field"]==11 OR $one_field["type_field"]==14) // списки, пользователи
    {
      $value = form_display($value);
      $field['form_fast_edit_flag'] = 1;
      $input_value = form_input_type($field, $line);

      if (test_allow_read($field, $line, "read"))
        {
          $input_value = $input_value["input"];

          if ($field['mult_value'])
            {
              $control = <<<EOD
<input type=hidden id='fast_edit_span_{$field_id}_{$line_id}' value='{$value}'>
EOD;

              foreach ($input_value['set'] as $pos=>$one_set)
                {
                  if ($pos==(count($input_value['set'])-1)) $is_last=1;
                  else $is_last=0;

              $control .= <<<EOD
<select tabindex="800"
id='fast_edit_span_{$field_id}_{$line_id}_{$pos}'
multi_select_group="{$field_id}_{$line_id}"
field_id="{$field_id}"
line_id="{$line_id}"
pos="{$pos}"
is_last="{$is_last}"
class="fast_edit_select sub_fast_edit_select"
>
EOD;
                  $control .= $one_set;
                  $control .= <<<EOD
</select>
EOD;
                  $onload_script.= "addHandler_mult_select(document.getElementById('fast_edit_span_{$field_id}_{$line_id}_{$pos}'));\n";
                }
              $control .= <<<EOD
<script>$onload_script $('.fast_edit_select').each(form_fast_select);</script>
EOD;
            }
          else
            {
              $control = <<<EOD
<select id='fast_edit_span_{$field_id}_{$line_id}'
tabindex="800"
field_id="{$field_id}"
line_id="{$line_id}"
class="fast_edit_select sub_fast_edit_select"
>
EOD;

              $control .= $input_value;
              $control .= "</select><script>addHandler_select(document.getElementById('fast_edit_span_{$field_id}_{$line_id}')); $('.fast_edit_select').each(form_fast_select);</script>";
            }
        }
      else $control = str_replace("\r\n", "<br />", $value);
    }
  elseif ($field['type_field']==5) // тип поля связь
    {
      $value = form_display($value);

      if (test_allow_read($field, $line, "read"))
        {
          $f_info = explode("|", $field['type_value']);
          $s_table = $f_info[0];
          if ($field['width']) $field_width = $field['width'];
          else $field_width = 300;

          $field_real_width = $field_width - 20;

          $control = <<<EOD
<a href='view_line2.php?table={$s_table}&line=0' onclick='if (!event.ctrlKey) return false;' class=fast_edit_link style="white-space: nowrap"><input
class="sub_edit_link_input"
tabindex="800"
type="text"
id="fast_edit_span_{$field_id}_{$line_id}"
value="{$value}"
field_id="{$field_id}"
line_id="{$line_id}"
f_value="0"
field_width="{$field_width}" style="width:{$field_real_width}px"
><span></span></a><script>addHandler_link(document.getElementById('fast_edit_span_{$field_id}_{$line_id}'));</script>
EOD;
        }
      else $control = $value;
    }
  else // тип поля неопределен - просто выводим значение $value
    {
      $control = $value;
    }
  return $control;
}

function channel_insert_id($link_identifier)
{
  global $config, $last_sql_request, $sql_current_link, $sql_db_types;
  if ($link_identifier==0) $link_identifier = $sql_current_link;
  $insert_id = sql_insert_id($link_identifier);
  $insert_table = substr($last_sql_request,12,strpos($last_sql_request," ",12)-12);
  $insert_table = str_replace('`','',form_sql($insert_table));
  $dev_channel = intval($config['dev_channel']);
  if (substr($insert_id,-1)!=$dev_channel) $new_insert_id = substr($insert_id,0,-1).$dev_channel; else $new_insert_id = $insert_id;
  if ($new_insert_id < $insert_id) $new_insert_id += 10;
  if ($new_insert_id != $insert_id)
     {
       $sqlQuery = "UPDATE $insert_table SET id=$new_insert_id WHERE id=$insert_id";
       sql_query($sqlQuery, $link_identifier);
     }
  // Фиксируем автоинкримент на следующем счетчике
  if ($sql_db_types[$link_identifier]=="mysql")
    {
      $sqlQuery = "ALTER TABLE ".$insert_table." AUTO_INCREMENT=".($new_insert_id+10);
      sql_query($sqlQuery, $link_identifier);
    }
  if ($sql_db_types[$link_identifier]=="postgresql")
    {
      $sqlQuery = "ALTER SEQUENCE ".$insert_table."_id_seq RESTART WITH ".($new_insert_id+10);
      sql_query($sqlQuery, $link_identifier);
    }
  return $new_insert_id;
}

// Формирует путь из локальной кодировки в utf8
function local_to_utf_path($path)
{
  $str=$path;
  global $lang;
  $t_path=strtolower($path);
  $t_path=str_replace('\\','/',$t_path);
  if (substr($t_path,1,2)==':/')
     {
       if (function_exists('iconv'))  $str = iconv($lang['charset'], "utf-8", $path);
     }
  return $str;
}

// Функция проверки состояния дистрибутива, возвращает массив ошибок
// ["php_module"]["zip"]["description"] - список не установленных модулей или работающих не правильно, в описании текст ошибки
// ["php_module"]["zip"]["help_url"]
// ["crc_check"]["files"]["common.php"] - список файлов с поврежденной контрольной суммой
// ["crc_check"]["description"]
// ["crc_check"]["help_url"]
// ["critical"] - критические ошибки, например изменен файл common.php
// ["critical"]["0"]["description"]
// ["critical"]["0"]["help_url"]
// ["critical"]["1"]["description"]="Ivalid zend"
// ["critical"]["1"]["help_url"]=
// ["critical"]["2"]["description"]="Invalid notice type"
// ["check_access"]["temp"]["full_path"]
// ["check_access"]["temp"]["description"]
// ["check_access"]["temp"]["help_url"]
// ["check_access"]["config.php"]["full_path"]
// ["check_access"]["config.php"][...]
// ["check_sync"]["sync_name"]["description"]
// ["check_sync"]["sync_name"]["help_url"]
//
// Параметр check_type - для медленных проверок, либо проверок только определенных модулей
// может принимать занчения "fast" - только быстрые проверки, не требующие много времени
//                          "sync" - проверяем только синхронизацию
//                          "smtp" - настройки сервера рассылки - пробуем отправить тестовое письмо
//                          "all"  - проверяем все медленные проверки.
function check_distr($check_type="fast")
{
  global $lang, $config;
  $checks=array();
  // Проверка контрольных сумм файлов
  $invalid_check_summ=array();
  if (!file_exists($config['site_path']."/include/checksum.php"))
     {
       $cr_err["description"]=$lang["no_checksum_file"];
       $cr_err["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#checksum";
       $checks["critical"][]=$cr_err;
     }
     else
     {
      include $config['site_path']."/include/checksum.php";
      $already_alarm = array();
      foreach ($files_check_summ as $f_name=>$info)
        {
          $f_path=str_replace('\\','/',$config['site_path'])."/".$f_name;
          if (!file_exists($f_path))
             {
               // Возможно не существует корневая папка, разворачиваем
               while (strlen($f_path)>1)
                 {
                   $bk_slash=strrpos($f_path,'/');
                   if (!$bk_slash)
                      {  // Нет больше папок, нельзя подняться выше
                         break;
                      }
                   $base_dir=substr($f_path,0,$bk_slash);
                   if (!file_exists($base_dir))
                      { // Не существует корневой каталог
                        $f_path=$base_dir;
                      }
                      else
                      {
                        break;
                      }
                 }
               if ($already_alarm[$f_path]) continue;
               $already_alarm[$f_path]=1;
               // Проверка на существование корня
               $cr_err["description"]=$lang["Not_exists"].$f_path;
               $cr_err["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#checksum";
               $checks["critical"][]=$cr_err;
             }
             else
             {
               if ($check_type!="all")
                  {  // Быстрая проверка на размер
                     $p1=strpos($f_name,'/');
                     if ($p1!==FALSE) if (strpos($f_name,'/',$p1+1)!==FALSE) continue; // Пропускаем проверку двойной вложенности
                     if (filesize($f_path)!=$info['size'])
                        {
                          $cr_err["description"]=$lang["checksum_error"].$f_path;
                          $cr_err["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#checksum";
                          $checks["critical"][]=$cr_err;
                          $invalid_check_summ[$f_name]=1;
                        }
                  }
                  else
                  {  // Полная проверка md5
                     $f_data=file_get_contents($f_path);
                     if ($f_data===FALSE)
                        {
                          $cr_err["description"]=$lang["Not_exists"].$f_path;
                          $cr_err["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#checksum";
                          $checks["critical"][]=$cr_err;
                        }
                        else
                        {
                          if ($info['md5']!=md5($f_data))
                             { // Ошибка контролькой суммы
                               $cr_err["description"]=$lang["checksum_error"].$f_path;
                               $cr_err["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#checksum";
                               $checks["critical"][]=$cr_err;
                               $invalid_check_summ[$f_name]=1;
                             }
                        }
                  }
             }

        };
      unset($already_alarm);
     }
  if ($config['type']=="SAAS") return ;

  // Проверка существования модулей
  $loaded_modules = get_loaded_extensions();
  $l_m=array(); // Переворачиваем и приводим к нижнему регистру
  foreach ($loaded_modules as $one_module)
    { $l_m[strtolower($one_module)]=1; }
  $loaded_modules=$l_m;
  // Разворачиваем массив, меняем местами ключи и значения
  $requare_modules = array('gd', 'mbstring', 'openssl', 'imap', 'iconv', 'zip', 'mhash');
  foreach ($requare_modules as $module)
    {
      if (!$loaded_modules[$module])
         {
           $checks["php_module"][$module]["description"]=$lang['no_php_module'].$module;
           $checks["php_module"][$module]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#php_module_".$module;

           // Марикируем критические модули
           if (($module=="gd")||($module=="mbstring")||($module=="zip")||($module=="iconv"))
              {
                $checks["critical"][]=$checks["php_module"][$module];
              }
         }
    }

  if ($invalid_check_summ['test_zend.php']) return $checks;

  if ((!$config['type']=='SAAS') && ini_get('session.use_trans_sid'))
     {
       $checks["sec"]['trans_sid']['description']=$lang['used_trans_sid'];
       $checks["sec"]['trans_sid']['help_url']=="http://clientbase.ru/help/for_admin_16/trebovaniya/#trans_sid";
       $checks["critical"][]=$checks["sec"]['trans_sid'];
     }

  // Выполняем проверку соответствия дистрибутива версии php
  $rev_file_info = @file_get_contents('revision');
  if ($rev_file_info)
     {
        $lns=explode("\n",$rev_file_info); $coder_inf=explode(": ",$lns[2]);
        $config['coded_by'] = $coder = $coder_inf[1];
     }
  $p_v = explode('.',PHP_VERSION);
  $p_v = $p_v[0].".".$p_v[1];

  if ($coder=='zended_52' and $p_v>'5.2')
     {
        $cr["description"]=$lang['invalid_coded_zend']."5.3 .";
        $cr["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#invalid_coded_zend";
        $checks["critical"][]=$cr;
     }
     else
  if ($coder=='zended_53' and $p_v<'5.3')
     {
        $cr["description"]=$lang['invalid_coded_zend']."5.2 .";
        $cr["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#invalid_coded_zend";
        $checks["critical"][]=$cr;
     }
     else
     {
        // Проверка zend (ioncube)
        // Тест выполнением файла test_zended.php
        // т.к. ряд хостингов падает при запуске зашифрованного файла, то перехватываем выброс
        global $zended_test;
        $zended_test="";
        function terminated_invalid_zend($buffer)
        {
           global $zended_test,$lang,$config;
           if ($zended_test!="fine")
              { // Программа упала на этапе тестирования zend, выводим ошибку обычным текстом, с ссылкой на описание проблемы
                $buffer="";
                $buffer.=$lang['no_zend_installed'].". ";
                $buffer.="<a href='http://clientbase.ru/help/for_admin_16/trebovaniya/#zend_module'>".$lang['Details']."...</a>";
              }
           return $buffer;
        }
        ob_start("terminated_invalid_zend");
        include "test_zend.php";
        ob_end_clean();
        if ($zended_test!="fine")
           { // Ошибка не установлен кодировщик
             $fd = fopen("common.php", "r"); $str1 = fgets($fd); $str2 = fgets($fd); fclose($fd);
             if ($str1=="<?php @Zend;\n")
              {
                 $cr["description"]=$lang['no_zend_installed'];
                 $cr["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#zend_module";
                 $checks["critical"][]=$cr;
                 if (!$config["coded_by"]) $config["coded_by"]="zended";
              }
              elseif (strpos($str2,"ionCube Loader"))
              {
                 $cr["description"]=$lang['no_ioncube_installed'];
                 $cr["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#ioncube_module";
                 $checks["critical"][]=$cr;
                 if (!$config["coded_by"]) $config["coded_by"]="ioncube";
              }
              else
              {
                 $cr["description"]=$lang['invalid_coded'].$module;
                 $cr["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#invalid_coded";
                 $checks["critical"][]=$cr;
              }
           }
     }

  // Проверка на включенные notice
  ob_start();
  include "test_notice.php";
  $notice_res=ob_get_contents();
  ob_end_clean();
  if (strpos($notice_res,'NoThisVarWasSet'))
     { // Включен вывод нотисов
       $cr["description"]=$lang['notice_not_disabled'];
       $cr["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#notice_enabled";
       $checks["critical"][]=$cr;
     }

  // Проверка на функцию fsockopen в список запрещенных, с включенным параметром остановки скрипта в случае срабатывания запрещенной функции
  function disabled_function_fscockopen()
  {
    global $lang;
    return $lang["disabled_function_fsockopen"];
  }
  ob_start('disabled_function_fscockopen');
  $f = fsockopen('127.0.0.1', 80, $errno, $errstr, 3);
  $str=ob_get_clean();
  if (strpos($str,'has been disabled for security reasons'))
     { // Установлен disable_functions, который не приводит к fatal_error
       $cr["description"]=$lang["disabled_function_fsockopen"];
       $cr["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#disabled_function_fsockopen";
       $checks["critical"][]=$cr;
     }

  // Проверка каталогов на доступ
  $requare_folders = array('cache','files', 'temp', 'templates_c', 'backup');
  foreach ($requare_folders as $one_folder)
    {
      $full_path=$config['site_path']."/".$one_folder;
      if (($one_folder=='backup')&&($config['backup_path']))
         {
           foreach ($config["backup_path"] as $one_path)
             { // Берем первую строку
              if (!$one_path)
                 {
                   $full_path=$one_path;
                   break;
                 }
             }
         }
      if (!file_exists($config['site_path']."/".$one_folder))
         { // Данная папка вообще не существует
           $checks["check_access"][$one_folder]["full_path"]=$full_path;
           $checks["check_access"][$one_folder]["description"]=$lang["no_folder_exists"].$full_path;
           $checks["check_access"][$one_folder]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#check_access";
         }
         else
         { // Проверяем права записи в нее
           $f=@fopen($full_path."/test_write.txt","w");
           if (!$f)
              {
                $checks["check_access"][$one_folder]["full_path"]=$full_path;
                $checks["check_access"][$one_folder]["description"]=$lang["invalid_write_rights"].$full_path;
                $checks["check_access"][$one_folder]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#check_access";
              }
              else
              {
                $rand=rand(0,999999);
                $err_rights=0;
                if (!@fwrite($f,$rand))
                     $err_rights=1;
                   else
                   {
                     if (!fclose($f))
                         $err_rights=1;
                        else
                        {
                          $txt=@file_get_contents($full_path."/test_write.txt");
                          if ($txt!=$rand) $err_rights=1;
                             else
                             {
                               if (!unlink($full_path."/test_write.txt")) $err_rights=1;
                             }
                        }
                   }
                if ($err_rights)
                   {
                     $checks["check_access"][$one_folder]["full_path"]=$full_path;
                     $checks["check_access"][$one_folder]["description"]=$lang["invalid_write_rights"].$full_path;
                     $checks["check_access"][$one_folder]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#check_access";
                   }
                   else
                   { // Если папка files то возможны доп проверки
                     
                     if (($check_type=="all")&&($one_folder=="files"))
                        { // Полная проверка, проверяем вложенные папки на доступ
                          $fdir=$config['site_path']."/files";
                          $dirs1 = scandir($fdir);
                          foreach ($dirs1 as $d1_short)
                            {
                               if ($d1_short[0]=='.') continue;
                               $d1=$fdir."/".$d1_short;
                               if (!is_dir($d1)) continue;
                               $f=@fopen($d1."/test_write.txt","w");
                               if (!$f)
                                  {
                                    $checks["check_access"][$one_folder]["full_path"]=$d1;
                                    $checks["check_access"][$one_folder]["description"]=$lang["invalid_write_rights"].$d1;
                                    $checks["check_access"][$one_folder]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#check_access";
                                    break;
                                  }
                                  else
                                  {
                                     fclose($f);
                                     @unlink($d1."/test_write.txt");
                                     $dirs2 = scandir($d1);
                                     $dir2_count=count($dirs2)-2;
                                     foreach ($dirs2 as $d2_short)
                                         {
                                            if ($d2_short[0]=='.') continue;
                                            $d2=$d1."/".$d2_short;
                                            $f=@fopen($d2."/test_write.txt","w");
                                            if (!$f)
                                               {
                                                 $checks["check_access"][$one_folder]["full_path"]=$d2;
                                                 $checks["check_access"][$one_folder]["description"]=$lang["invalid_write_rights"].$d2;
                                                 $checks["check_access"][$one_folder]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#check_access";
                                                 break;
                                               }
                                            @unlink($d2."/test_write.txt");
                                         }
                                     if ($checks["check_access"][$one_folder]) break;
                                  }
                            }
                        }
                   }
              }
         }
    }

  // Проверка на включенный basedir и его корректность
  if ($config['type']=='WEB')
     {
         $b_dir = @ini_get('open_basedir');
         if ($b_dir)
            {  // Установлен basedir, проверяем входят ли временные каталоги в список разрешенных
               // Раскладываем basedir в виде массива каталогов
               if (strpos($b_dir,';'))
                  { // Windows style
                    $b_dirs = explode(";",$b_dir);
                  }
                  else
               if (strpos($b_dir,':')>2)
                  { // Linux style
                    $b_dirs = explode(":",$b_dir);
                  }
                  else
                  {
                    $b_dirs = array($b_dir);
                  }
               $invalid_upload = 1;
               $invalid_temp = 1;
               foreach ($b_dirs as $one_dir)
                 {
                   if (substr($one_dir,-1,1)=='/') $one_dir=substr($one_dir,0,-1);
                   $u_dir = @ini_get('upload_tmp_dir');
                   if ($u_dir)
                      {
                         if (substr($u_dir, 0, strlen($one_dir))===$one_dir)
                            {
                               $invalid_upload = 0;
                            }
                      }
                   $t_dir = @sys_get_temp_dir();
                   if ($t_dir)
                      {
                         if (substr($t_dir, 0, strlen($one_dir))===$one_dir)
                            {
                              $invalid_temp=0;
                            }
                      }
                 }
              if ($invalid_upload || $invalid_temp)
                 {
                    $buffer=$lang['Ivalid_basedir'];
                    $buffer.=" <a href='http://clientbase.ru/help/for_admin_16/trebovaniya/#basedir'>".$lang['Details']."...</a>";
                    die($buffer);
                 }
            }
     }

  // Доп проверка на корректность работы zip модуля
  if (!$checks["php_module"]["zip"] && !$checks["check_access"]["temp"])
     {  // Пробуем создать архив а затем распаковать его
        $tmp_zip_name = $config['site_path']."/temp/test_arhive.zip";
        $zip = new ZipArchive;
        if (!$zip)
           {
              $checks["php_module"]["zip"]["description"]=$lang["invalid_php_module"]."Can`t create ZipArchive object";
              $checks["php_module"]["zip"]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#php_module_zip";
           }
           else
           {
              if (@$zip->open($tmp_zip_name, ZipArchive::CREATE) !== true)
                 {
                   $checks["php_module"]["zip"]["description"]=$lang["invalid_php_module"]."Can`t create zip file: ($tmp_zip_name)";
                   $checks["php_module"]["zip"]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#php_module_zip";
                 }
                 else
                 {
                   if (!@$zip->addFile($config['site_path']."/common.php",'common.php'))
                      {
                         $checks["php_module"]["zip"]["description"]=$lang["invalid_php_module"]."Can`t add to zip file: ($tmp_zip_name)";
                         $checks["php_module"]["zip"]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#php_module_zip";
                      }
                   $zip->close();
                 }
              unset($zip);
              // Распаковка
              $zip = new ZipArchive;
              if (@$zip->open($tmp_zip_name)!== true)
                 {
                   $checks["php_module"]["zip"]["description"]=$lang["invalid_php_module"]."Can`t open zip file: ($tmp_zip_name)";
                   $checks["php_module"]["zip"]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#php_module_zip";
                 }
                 else
                 {
                    // Список файлов в архиве
                    if ((!method_exists($zip,'addEmptyDir'))||(!($zip->getNameIndex(0))))
                       {
                         $checks["php_module"]["zip"]["description"]=$lang["invalid_php_module"]."Zip addEmptyDir or getNameIndex don't work.";
                         $checks["php_module"]["zip"]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#php_module_zip";
                       }
                       else
                   // Распаковка
                   if (!@$zip->extractTo($config["site_path"]."/temp"))
                      {
                        $checks["php_module"]["zip"]["description"]=$lang["invalid_php_module"]."Can`t extract zip file ($tmp_zip_name)";
                        $checks["php_module"]["zip"]["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#php_module_zip";
                      }
                   $zip->close();
                 };
              unset($zip);

              // Зачищаем файлы
              @unlink($config["site_path"]."/temp/common.php");
              @unlink($tmp_zip_name);
           }
     };
  if (!ini_get('short_open_tag'))
     { // Проверка включены ли короткие теги
       $cr["description"]=$lang['invalid_short_open_tag'];
       $cr["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#short_open_tag";
       $checks["critical"][]=$cr;
     };

  // Проверка коректности работы строковых функций
  if ((strlen("Счет MWE_самострой с регсбором.rtf") != 57) || (mb_strlen("Счет MWE_самострой с регсбором.rtf")!=34))
    {
      $cr["description"]=$lang['invalid_str_func_php'];
      $cr["help_url"]="http://clientbase.ru/help/for_admin_16/trebovaniya/#string_functions";
      $checks["critical"][]=$cr;
    }

  // Проверка синхронизаций
  if ($check_type=="all" OR $check_type=="sync")
    {
      $sqlQuery = "SELECT * FROM ".SYNC_TABLE." WHERE enabled='1'";
      $result = sql_query($sqlQuery);

      while ($row = sql_fetch_assoc($result))
        {
          $sync_long_desc = $lang['Sync']." <a href=\"edit_sync.php?sync_id=".$row['id']."\">".$row['sync_name']."</a>: ";

           if ($row['sync_mode'] == 3 AND !$config["modules"]["1csync"])
            {
              $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['1c_integration']." ".$lang['1c_not_aсtived'];
              $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['1c_integration']." ".$lang['1c_not_aсtived'];
              $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/";
              $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
              continue;
            }

          if ($row['type_mode'] == 1) // Режим FTP
            {
              $ta = explode(":", $row['server']); list($ftp_server, $ftp_port) = $ta;
              $ftp_login = $row['login'];
              $ftp_pass = $row['password'];

              if ($ftp_port == "") $ftp_port = 21;

              if ($ftp_connect = @ftp_connect($ftp_server, $ftp_port))
                {
                  if (@ftp_login($ftp_connect, $ftp_login, $ftp_pass))
                    {
                      ftp_pasv($ftp_connect, true);

                      if (!@ftp_chdir($ftp_connect, $row['download_dir']))
                        {
                          $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['Sync_no_dnl_dir'];
                          $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['Sync_no_dnl_dir'];
                          $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/#ftp";
                          $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                        }

                      if (@ftp_chdir($ftp_connect, $row['upload_dir']))
                        {
                          if (@ftp_mkdir($ftp_connect, "testsync"))
                              @ftp_rmdir($ftp_connect, "testsync");
                          else
                            {
                              $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['invalid_write_rights'].$row['upload_dir'];
                              $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['invalid_write_rights'].$row['upload_dir'];
                              $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/#ftp";
                              $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                            }
                        }
                      else
                        {
                          $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['Sync_no_upl_dir'];
                          $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['Sync_no_upl_dir'];
                          $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/#ftp";
                          $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                        }
                    }
                  else
                    {
                      $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['Ftp_login_error'];
                      $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['Ftp_login_error'];
                      $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/#ftp";
                      $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                    }
                }
              else
                {
                  $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['Ftp_connect_error'];
                  $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['Ftp_connect_error'];
                  $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/#ftp";
                  $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                }
            }
          elseif ($row['type_mode'] == 2 AND $row['http_type']) // Режим HTTP
            {
              if (strpos($row['server'], "http://") === false AND strpos($row['server'], "https://") === false)
                  $row['server'] = "http://".$row['server'];

              $snoopy = new Snoopy;
              $snoopy->proxy_host = $config["proxy_host"];
              $snoopy->proxy_port = $config["proxy_port"];
              $snoopy->proxy_user = $config["proxy_user"];
              $snoopy->proxy_pass = $config["proxy_pass"];

              $post_data['login'] = $row['login'];
              $post_data['password'] = $row['password'];
              $post_data['sync_id'] = $row['remote_sync'];

              if (@$snoopy->submit($row['server']."/sync.php", $post_data))
                {
                  if ($snoopy->status == 200)
                    {
                      $post_result = $snoopy->results;

                      if (strpos($post_result, "SYNC_COMMAND;SYNC_ERROR;INCORRECT_LOGIN") !== false)
                        {
                          $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['Http_login_error'];
                          $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['Http_login_error'];
                          $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/#http";
                          $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                        }

                      if (strpos($post_result, "SYNC_COMMAND;SYNC_ERROR;SYNC_NOT_FOUND") !== false)
                        {
                          $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['Http_sync_not_found'];
                          $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['Http_sync_not_found'];
                          $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/#http";
                          $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                        }
                    }
                  else
                    {
                      $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['Http_server_error'];
                      $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['Http_server_error'];
                      $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/#http";
                      $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                    }
                }
              else
                {
                  $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['Http_connect_error'];
                  $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['Http_connect_error'];
                  $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/#http";
                  $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                }
              unset($snoopy);
            }
          elseif ($row['type_mode'] == 0) // Local
            {
              if (!@is_dir($row['download_dir']))
                {
                  $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['Sync_no_dnl_dir'];
                  $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['Sync_no_dnl_dir'];
                  $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/";
                  $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                }

              if (@is_dir($row['upload_dir']))
                {
                  if ($ft = @fopen($row['upload_dir']."/testsync.tmp", "w"))
                    {
                      @fclose($ft);
                      @unlink($upl_dir."/testsync.tmp");
                    }
                  else
                    {
                      $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['invalid_write_rights'].$row['upload_dir'];
                      $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['invalid_write_rights'].$row['upload_dir'];
                      $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/";
                      $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                    }
                }
              else
                {
                  $checks["check_sync"][$row["sync_name"]]["description"] = $sync_long_desc.$lang['Sync_no_upl_dir'];
                  $checks["check_sync"][$row["sync_name"]]["short_descr"] = $lang['Sync_no_upl_dir'];
                  $checks["check_sync"][$row["sync_name"]]["help_url"] = "http://clientbase.ru/help/for_admin_16/sync/";
                  $checks["check_sync"][$row["sync_name"]]["sync_id"] = $row['id'];
                }
            }
        }
    }
  if ($config['invalid_cron'])
    {
      $checks["cron"]["setup"]["description"] = $lang['invalid_cron'];
      $checks["cron"]["setup"]["help_url"] = 'http://clientbase.ru/help/for_admin_16/cron/';
    }

  return $checks;
}

// Функция проверки состояния базы, возвращает 1 - если база исправна
// Также исравляет найденные ошибки, если включен параметр repair
function check_database($repair=0)
{
  global $config,$lang;
  $checks = array();
  // Проверяем таблицы на предмет ошибок битой базы и выполняем repair
  $sqlQuery = "SHOW TABLES";
  $result = sql_query($sqlQuery);
  $i=0;
  echo "<script>window.onload=function (){ document.getElementById('show_if_error').style.display='block'; }</script>\n";
  echo "<div id=show_if_error style='display:none'>\n";
  echo "If you see this text check database failed! Possible you have too little max_execution time or too slow database. <br>\n";flush();
  $cur_time = time();
  if ($config['type']!='SAAS')
  while ($table_raw = mysql_fetch_array($result))
    {
      $table_name=$table_raw[0];
      if (substr($table_name,0,strlen($config["table_prefix"]))==$config["table_prefix"])
         {
           echo (time()-$cur_time)." sec. Check table: ".$was_check_tables." - ".$table_name."<br>\n";flush();
           // Делаем REPAIR, если была ошибка пишем в отчет
           $sqlQuery = "select engine from information_schema.tables where table_name='$table_name'";
           $result2 = sql_query($sqlQuery);
           $table_engine = mysql_fetch_array($result2);
           $table_engine = $table_engine[0];
           if ($table_engine=="MyISAM")
              { // Ремонтируем
                $sqlQuery = "REPAIR TABLE $table_name";
                $result2 = sql_query($sqlQuery);
                while ($repair_res = sql_fetch_assoc($result2))
                   {
                     if ($repair_res["Msg_text"]!="OK")
                        {
                          if (strtolower($repair_res["Msg_type"])=="error")
                             {
                               $cr_err["description"]=$lang["Table_repair_fail"].' "'.$table_name.'": '.$repair_res["Msg_text"];
                               $cr_err["help_url"]="http://clientbase.ru/help/for_admin_16/db_errors/";
                               $checks["db"]["repair$i"]=$cr_err;
                             }
                             else
                             {
                               $cr_err["description"]=$lang["Table_repair_res"].' "'.$table_name.'": '.$repair_res["Msg_text"];
                               $cr_err["help_url"]="http://clientbase.ru/help/for_admin_16/db_errors/";
                               $checks["db"]["repair$i"]=$cr_err;
                             }
                          $i++;
                        }
                   }

              }
           // Оптимизируем
           $sqlQuery = "OPTIMIZE TABLE $table_name";
           sql_query($sqlQuery);
         }
    }

  // Проверяем наличие полей в data, описанных в fields
  $number_tables=array();
  $sqlQuery = "SELECT * FROM ".FIELDS_TABLE;
  $result = sql_query($sqlQuery);
  while ($field = mysql_fetch_array($result))
    {
      if ($field['type_field']==8)
         {
           $number_tables[$field['table_id']]=$field['table_id'];
           $numer_fields[$field['table_id']]=$field;
         }
      $was_check_tables++;
      echo (time()-$cur_time)." sec. Check fields exists in table: ".$was_check_tables." - ".DATA_TABLE.$field['table_id']."<br>\n";flush();
      $sqlQuery = "SHOW COLUMNS FROM ".DATA_TABLE.$field['table_id']." LIKE '".form_int_name($field['id'])."'";
      $result2 = sql_query($sqlQuery);
      if (!mysql_num_rows($result2))
        { // поле не найдено, восстанавливаем
          // Вычисляем sql-тип поля
          list($type_value) = explode("\r\n",$field['type_value']);
          $field_type = sql_type($field['type_field'], $type_value, $field['mult_value']);
          if ($field['type_field']==3)
            { // при очень большом количестве полей, заменяем на text
              $sqlQuery = "select count(*) as cnt from ".FIELDS_TABLE." WHERE table_id='".$field['table_id']."'";
              $result3 = sql_query($sqlQuery);
              $row = mysql_fetch_array($result3);
              if ($row['cnt']>30) $field_type = sql_type($field['type_field'], 0, 1);
            }
          // Вычисляем предыдущее поле
          $sqlQuery = "SELECT id FROM ".FIELDS_TABLE." WHERE id<".$field['id']." and table_id=".$field['table_id']." ORDER BY id DESC LIMIT 1";
          $result3 = sql_query($sqlQuery);
          $row = mysql_fetch_array($result3);
          $prev_field = form_int_name($row['id']);
          // Собственно добавление поля в data
          $sqlQuery = "ALTER TABLE ".DATA_TABLE.$field['table_id']." ADD f".$field['id']." ".$field_type." AFTER ".$prev_field;
          sql_query($sqlQuery);
          // Заполняем значения по умолчанию
          $default_value = "'".$field['default_value']."'";
          if ($field['autonumber'])
            {
              $result = data_select_field($field['table_id'], "min(id) as first_id, max(id) as last_id");
              $row = sql_fetch_array($result);
              $first_id = $row['first_id'];
              $last_id = $row['last_id'];
              $default_value = "id + ".($field['default_value'] - $first_id);
              $field['default_value'] = $last_id + $field['default_value'] - $first_id + 1;
              sql_update(FIELDS_TABLE, array('default_value'=>$field['default_value']), 'id=',$field_id);
            }
          if ($field['default_value']=="{current}")       $default_value = "user_id";
          if ($field['default_value']=="{cur.time}")      $default_value = "add_time";
          if ($field['default_value']=="{cur.date}")      $default_value = "date(add_time)";
          if ($field['default_value']=="{current_group}") $default_value = "(SELECT group_id FROM ".USERS_TABLE." b WHERE b.id=a.user_id)";
          sql_query("UPDATE ".DATA_TABLE.$field['table_id']." a SET f".$field['id']."=".$default_value);
        }
    }

  // Проверяем таблицы на существование полей типа Номер, в больших таблицах
  $was_check_tables++;
  echo (time()-$cur_time)." sec. Check for num fields.";flush();
  if ($number_tables)
     {
        $number_tables_str=implode(',',$number_tables);
        $result = sql_select(TABLES_TABLE,"id in ($number_tables_str)");
        while ($table = mysql_fetch_array($result))
          {
             $result2 = sql_select_field($config['table_prefix']."data".$table['id'], "count(*) as cnt","1=1");
             $count = mysql_fetch_array($result2);
             if ($count['cnt']>1500)
                { // При более 1500 записей, не рекомендуется использовать поле номер
                  $table_name=form_display($table['name_table']);
                  $err["description"]=$lang["use_number_field1"].' "<a href="edit_field.php?table='.$table['id'].'&field='.$numer_fields[$table['id']]['id'].'">'.form_display($numer_fields[$table['id']]['name_field']).'</a>" '.$lang["use_number_field2"].' "'.$table_name.'"';
                  $err["help_url"]="http://clientbase.ru/help/for_admin_16/Field_types/#big_tables_numer_field";
                  $checks["db"]["number_fields".$table['id']]=$err;
                }
          }
    }
  echo "Database check DONE.";
  echo "</div>\n";
  echo "<script>document.getElementById('show_if_error').outerHTML=''</script>\n";
  // Проверяем таблицы на совпадение правильной структуры
  // Правильная структура таблиц без данных описана в файле null_dump.zip
  // Проверка структыры таблиц с данными производиться на основе шаблона указанного прямо в коде
  return $checks;
}

// Функция построения графиков типа Bars, Pie, Line
// параметры:
// type_graph : Bars, Pie, Line1, LineDate
// div_id : id div тега, в котором будет размещен график
// title : текст, заголовок графика
// title_fontSize : число. Размер шрифта заголовка в pt, например 16
// title_fontFamily : текст. Название шрифта заголовка , например Arial
// x_label, y_label : текст. Надпись по соответствующей оси для Line1
// x_fontSize, y_fontSize : число. Размер шрифта подписи в pt, по соответствующей оси для Line1
// x_fontFamily, y_fontFamily : текст. Название шрифта подписи по соответствующей оси для Line1
// series_names : текст. Название серий данных
// min и max : текст или число. Для задания границ графика
// zoom : true/false
function draw_graph($data, $settings){
  $div_id = $settings['div_id'];
  $user_colors = $settings['user_colors'];
  $return_str = '';
  $title = '';
  if ($settings['title']) {
    $title = "title: {
        text:'".$settings['title']."'";
      if ($settings['title_fontSize']) $title .= ",\n        fontSize: '".$settings['title_fontSize']."pt'";
      if ($settings['title_fontFamily']) $title .= ",\n        fontFamily: '".$settings['title_fontFamily']."'";
    $title .= "\n      },";
  }
  $rand_g = rand(0,99999);
  $return_str .= "<script>
  function draw_graph$rand_g(){\n";
  $return_str .= "    $.jqplot.config.enablePlugins = true;\n";

  switch ($settings['type_graph']) {

    case 'Bars': // ----------------------------  Bars
      // ищем самую длинную серию данных
      $num_series = count($data); // кол-во серий данных
      $max_len_ser = 0;
      for ($i=0; $i<$num_series; $i++) {
        if (count($data[$i])>$max_len_ser) {
          $max_len_ser = count($data[$i]);
          $num_max_ser = $i;
        }
      }
      // формируем метки
      $tiks = array();
      $tiks_str = "    var ticks = [";
      foreach ($data[$num_max_ser] as $t=>$s) {
        $tiks[] = $t;
        $tiks_str .= "'".$t."', ";
      }
      $return_str .= substr($tiks_str,0,-2)."];\n";

      // Формируем серии с проверкой пропущенных значений
      $ticks_y_check = 1;
      $series_list = '';
      for ($i=0; $i<$num_series; $i++) {
        $series_list .= 's'.($i+1).', ';
        $series = "";
        for ($j=0; $j<$max_len_ser; $j++) {
          $element = each($data[$i]);
          if ($element) {
            if ($tiks[$j] == $element['key']) {
              if ($element['value']!=0.1) $ticks_y_check=0;
              $series .= $element['value'].', ';
            }
            else {
              $series .= '0, ';
              prev($data[$i]);
            }
          }
          else {
            $series .= '0, ';
          }
        }
        $return_str .= "    var s".($i+1)." = [".substr($series,0,-2)."];\n";
      }
      if ($ticks_y_check) $ticks_y = "ticks: [0,1,2,3,4]";
      else $ticks_y = "";
      $series_list = substr($series_list,0,-2);
      // строим график
      $return_str .= "    plot_bars = $.jqplot('".$div_id."', [".$series_list."], {
      ".$title."
      seriesDefaults: {
        shadow:false,
        renderer:$.jqplot.BarRenderer,
        pointLabels: { show: true }
      },
      grid:{
        shadow:false,
        borderWidth: 0,
        background: '#fff',
      },
      axesDefaults:{
        tickOptions:{
            showMark:false,
            fontSize:'12px',
            fontFamily:'Arial',
          },
      },
      axes: {
        xaxis: {
          renderer: $.jqplot.CategoryAxisRenderer,
          ticks: ticks,
        },
        yaxis: {
          showMark:false,
          tickOptions:{
            formatString: '%.0f',
          },
          $ticks_y
        }
      }
    });\n";

      break; // ---------------------------- end Bars

    case 'Pie': // ----------------------------  Pie
      echo "<style>.jqplot-data-label {color:white}</style>";

      $colors = array("'#3366cc'", "'#dc3912'", "'#ff9900'", "'#109618'", "'#990099'",
                      "'#4bb2c5'", "'#c5b47f'", "'#71c49b'", "'#579575'", "'#839557'", "'#958c12'",
                        "'#953579'", "'#4b5de4'", "'#d8b83f'", "'#ff5800'", "'#0085cc'");
      $colors = implode(",", $colors);

      $pie_sum = array_sum($data);
      foreach ($data as $k=>$v)
        {
          if (round(100 * $v / $pie_sum)>=1)
          {
            if ($user_colors[$k]) $n_colors["$v"] = "'".$user_colors[$k]."'";
            else $n_colors["$v"] = "'#".substr(md5($k),0,6)."'";
          }
        }
      krsort($n_colors);
      if (count($n_colors)>0)
         {
            $push_colors = "'#".substr(md5('Остальное'),0,6)."'";
            $colors = implode(",", $n_colors);
         }
      arsort($data);

      $return_str .= "    if ($('#".$div_id."').height() < $('#".$div_id."').width() && $('#".$div_id."').height() > 0)
                          {
                              var h_rows = $('#".$div_id."').height()/18/2;
                          }
                          else
                          {
                              var h_rows = $('#".$div_id."').width()/19/3;
                          }
                          var g_padding = $('#".$div_id."').width()/5;
                          var b_padding = -50;
                          var r_padding = g_padding/2;
                          var l_padding = g_padding/2;
                          var t_padding = 20;
                      \n";

      $series = '';
      $common_percent = 0;
      $common_count = 0;
      $common_var = 0;
      foreach ($data as $t=>$s) {
        $pie_percent = round(100 * $s / $pie_sum);
        if ($pie_percent < 1)
           {
             $common_count++;
             $common_percent+=(100 * $s / $pie_sum);
             $common_var+=$s;
           }
           else
           {
             $pie_labels[] = "'".$t." (".$pie_percent." %)'";
             $series .= "['".$t." ($pie_percent %)', ".$s."], ";
           }
      }
      if ($common_count>0)
         {
            $push_labels = "'Остальное (".round($common_percent)." %)'";
            $push = "['Остальное (".round($common_percent)." %)', ".round($common_var)."]";
         }

      $return_str .= "    var data = [".substr($series,0,-2)."];\n";
      $return_str .= "    data.splice((Number(h_rows)-1));\n";
      $return_str .= "    data.push($push);\n";

      $return_str .= "    var pie_labels = [".implode(",",$pie_labels)."];\n";
      $return_str .= "    pie_labels.splice((Number(h_rows)-1));\n";
      $return_str .= "    pie_labels.push($push_labels);\n";

      $return_str .= "    var colors = [".$colors."];\n";
      $return_str .= "    colors.splice((Number(h_rows)-1));\n";
      $return_str .= "    colors.push($push_colors);\n";

      $return_str .= "    var plot_pie = jQuery.jqplot ('".$div_id."', [data], {
      ".$title."
      grid: {
        shadow: false,
        borderWidth: 0,
        background: '#fff',
      },
      gridPadding: {left: l_padding, right: r_padding, bottom: b_padding, top: t_padding},
      seriesDefaults: {
        shadow:false,
        renderer: jQuery.jqplot.PieRenderer,
        rendererOptions: {
          showDataLabels: true,
          sliceMargin: 3,
          padding: 0,
        },
        seriesColors: colors
      },
      legend: {
        renderer: $.jqplot.EnhancedLegendRenderer,
        rendererOptions: {
          numberRows: h_rows,
          numberColumns: 1
        },
        show:true,
        location:'e',
        labels: pie_labels
      }
    });\n";

      break; // ---------------------------- end Pie

    case 'Line1': // ----------------------------  Line1
      // Формируем серии
      $num_series = count($data); // кол-во серий данных
      $series_list = '';
      for ($i=0; $i<$num_series; $i++) {
        $series_list .= 's'.($i+1).', ';
        $series = "";
        foreach ($data[$i] as $k=>$v) {
          $series .= "[".$k.",".$v."], ";
        }
        $return_str .= "    var s".($i+1)." = [".substr($series,0,-2)."];\n";
      }
      $series_list = substr($series_list,0,-2);

      $return_str .= "    var plot_line = $.jqplot('".$div_id."', [".$series_list."], {
      ".$title."
      legend: {show:true, location: 'se'},
      axes:{
        xaxis:{
          tickRenderer:$.jqplot.CanvasAxisTickRenderer,
          label:'".$settings['x_label']."',
          labelOptions:{
            fontFamily:'".$settings['x_fontFamily']."',
            fontSize: '".$settings['x_fontSize']."pt'
          },
          labelRenderer: $.jqplot.CanvasAxisLabelRenderer
        },
        yaxis:{
          tickRenderer:$.jqplot.CanvasAxisTickRenderer,
          labelRenderer: $.jqplot.CanvasAxisLabelRenderer,
          labelOptions:{
            fontFamily:'".$settings['y_fontFamily']."',
            fontSize: '".$settings['y_fontSize']."pt'
          },
          label:'".$settings['y_label']."'
        }
      }";
      if ($settings['zoom']) { // zoom
        $return_str .= ",
      cursor:{
        show: true,
        zoom: true
      }\n";
      }
      $return_str .= "    });\n";

      break; // ---------------------------- end Line1

    case 'LineDate': // ----------------------------  LineDate
      if (!isset($settings['markerOptions'])) $settings['markerOptions'] = array();
      if (!isset($settings['markerOptions']['shadow'])) $settings['markerOptions']['shadow']='false';
      $markerOptionsLine="";
      foreach ($settings['markerOptions'] as $k=>$v) $markerOptionsLine.=$k.":".$v.",";
      $markerOptionsLine=substr($markerOptionsLine,0,-1);

      //Формирует отметки по y
      if($settings['y_min']<0 && $settings['y_max']>0)
        {
          $ticks=array();
          $ymax=$settings['y_max'];
          $ymin=$settings['y_min'];

          $prep=$ymax/3;
          $prepy=-$ymin/3;
          if($prep>$prepy){
            $ticks[0]=0-$prep;
            if($ticks[0]>$ymin){
              $ticks[0]=$ymin;
              $prep=-$ymin;
            }
            $ticks[1]=0;
            $ticks[2]=0+$prep;
            for($i=3;$ymax>$s;$i++){
              $ticks[$i]=$ticks[$i-1]+$prep;
              $s=$ticks[$i];
            }
          }else{
            $s=-1;
            $prep=-$ymin/3;
            $ticks[0]=$ymin;

            for($i=1;$s<0;$i++){
              $ticks[$i]=$ticks[$i-1]+$prep;
              $s=$ticks[$i];
            }
            if(isset($ticks[$i+1]))$ticks[$i+1]=0;

            $ticks[$i+2]=$ticks[$i+1]+$prep;
          }
          $ticks=implode(',',$ticks);
        }

      // Смещение
      if(!$prep) $prep=$settings['y_max']/5 * 1;
      if($data[0])
        foreach($data[0] as $k=>$v){
          $data[0][$k]=$v+$prep*0.04;
        }
      if($data[1])
        foreach($data[1] as $k=>$v){
          $data[1][$k]=$v+$prep*0.01;
        }
      if($data[2])
        foreach($data[2] as $k=>$v){
          $data[2][$k]=$v+$prep*0.07;
        }
      if($data[3])
        foreach($data[3] as $k=>$v){
          $data[3][$k]=$v+$prep*0.1;
        }
      if($data[4])
        foreach($data[4] as $k=>$v){
          $data[4][$k]=$v-$prep*0.2;
        }

      // Формируем серии
      $sotkl=array();
      $num_series = count($data); // кол-во серий данных
      $series_list = '';
      $nullGraf=0;
      $nullColor="'#3366cc'";
      $nullLegend='';
      for ($i=0; $i<$num_series; $i++) {
        $series_list .= 's'.($i+1).', ';
        $series = "";
        foreach ($data[$i] as $k=>$v) {
          $series .= "['".$k."',".$v."], ";
          $sotkl[$i][$k]=$v;
          if($v!=0) $nullGraf=1;
        }
        $return_str .= "    var s".($i+1)." = [".substr($series,0,-2)."];\n";
      }
      $series_list = substr($series_list,0,-2);

      if(!$nullGraf){
        $nullColor = "(('\v'=='v')?'white':'transparent')";
        $nullLegend = 'showLabels:false,showSwatches:false,';
      }

      $return_str .= "     var myFormatter = function (formatString, value) {
        value = Math.ceil(value).toString();
        var cnVal = value.length;
        if(value>=0)
          {
            var val = value.charAt(0) + value.charAt(1);
            cnVal = cnVal - 2;
            for(i=0;i<cnVal;i++){
              val += '0';
            }
          }
        if(value<0)
          {
            var val = value.charAt(1) + value.charAt(2);
            cnVal = cnVal - 3;
            for(i=0;i<cnVal;i++){
              val += '0';
            }
            val=-val;
          }
        return val;
      };\n";
      $return_str .= "     var plot_line_date = $.jqplot('".$div_id."', [".$series_list."], {
      ".$title."
      legend: {
        ".$nullLegend."
        renderer: $.jqplot.EnhancedLegendRenderer,";
        if ($settings['series_names']) $return_str .= "\n        labels: [ ".$settings['series_names']." ],";
        $return_str .= "\n        show:true,
        location: 's',
        placement: 'outsideGrid',
        fontFamily:'Arial',
        fontSize:'20px',
        textColor:'#666',
        rendererOptions: {
          numberRows: 1,
          numberColumns: 10,
          seriesToggle: false,
          disableIEFading: true
        }
      },
      seriesDefaults: {
        shadow: false,
        shadowAngle: 0,
        shadowOffset:0,
        shadowDepth:0,
        shadowAlpha:0,
        lineWidth: 3.5,
        pointLabels: { show: false },
        markerOptions:{ $markerOptionsLine }";
      $return_str .= "},
      seriesColors: [".$nullColor.", '#dc3912', '#ff9900', '#109618', '#990099',
                      '#4bb2c5', '#c5b47f', '#EAA228', '#579575', '#839557', '#958c12',
                        '#953579', '#4b5de4', '#d8b83f', '#ff5800', '#0085cc'],
      grid: {
        shadow: false,
        backgroundColor: (('\\v'=='v')?'white':'transparent'),
        drawBorder:false
      },
      axes: {
        xaxis: {";
          if ($settings['x_max']) $return_str .= "\n          max: '".$settings['x_max']."',";
          if ($settings['x_min']) $return_str .= "\n          min: '".$settings['x_min']."',";
          if ($settings['view_time']) $time_format = "<br />%H:%M";
          if ($settings['view_sec'])  $sec_format = ":%S";
          $return_str .= "\n          renderer: $.jqplot.DateAxisRenderer,
          tickOptions: {
            formatString: '%d.%m.%y".$time_format.$sec_format."',
            showMark:false,
            fontSize:'12px',
            fontFamily:'Arial'
          }";
          if($settings['ticks_x']){
            $return_str .=",ticks:[".$settings['ticks_x']."]";
          }
          if($settings['ticks_x_interval']){
            $return_str .=",tickInterval:'".$settings['ticks_x_interval']."'";
          }
        $return_str .= "},
        yaxis: {";
        if ($settings['y_max']) $return_str .= "\n          max: ".$settings['y_max'].",";
        if ($settings['y_min']) $return_str .= "\n          min: ".$settings['y_min'];
        else $return_str .= "\n          min:0";
        $return_str .= ",
        tickOptions:{
          formatString: '%.0f',
          formatter: myFormatter,
          fontSize:'12px',
          fontFamily:'Arial',
          showMark:false
        }";
        if($settings['y_min']<0 && $settings['y_max']>0)
          {
            $return_str .= "
        ,
        ticks:[".$ticks."]";
          }
        $return_str .= "}
      }";
      if ($settings['zoom']) { // zoom
        $return_str .= ",
      cursor:{
        show: true,
        zoom: true
      }\n";
      }
      $return_str .= "    });\n";

    break; // ---------------------------- end LineDate

  } // end switch

  $return_str .= "  }; setTimeout(draw_graph$rand_g, 1000);
</script>\n";
  return $return_str;
}

function json_encode_visual($php_arr, $level=1)
{
  foreach ($php_arr as $key=>$value)
    {
      if (is_array($value))
        $value = json_encode_visual($value,$level+1);
      else
        $value = "\"".str_replace(array("\\","\r","\n","\t","\"",''),array("\\\\","\\r","\\n","\\t","\\\"",'\u000b'),$value)."\"";
      $txt_arr[] = str_repeat("  ",$level)."\"".$key."\"".": ".$value;
    }
  return "{\n".implode(",\n",$txt_arr)."\n".str_repeat("  ",$level-1)."}";
}


function is_mobile($w,$h) {
  global $config;
  $w = intval($_COOKIE['screen_width']);
  $h = intval($_COOKIE['screen_height']);

  $max_w = 641;
  $max_h =  481;
  $is_mob = false;
  
  if (($w && $h) && ($w <= $max_w or $h <= $max_h))
     { // проверка по разрешению
       $is_mob = true;
     }
     else
     { // проверка по данным $_SERVER
        $regex_match = "/(nokia|iphone|android|motorola|^mot\-|softbank|foma|docomo|kddi|up\.browser|up\.link|"
        . "htc|dopod|blazer|netfront|helio|hosin|huawei|novarra|CoolPad|webos|techfaith|palmsource|"
        ."blackberry|alcatel|amoi|ktouch|nexian|samsung|^sam\-|s[cg]h|^lge|ericsson|philips|sagem|wellcom|bunjalloo|maui|"
        . "symbian|smartphone|mmp|midp|wap|phone|windows ce|iemobile|^spice|^bird|^zte\-|longcos|pantech|gionee|^sie\-|portalmmm|"
        . "jig\s browser|hiptop|^ucweb|^benq|haier|^lct|opera\s*mobi|opera\*mini|320x320|240x320|176x220"
        . ")/i";

        if (preg_match($regex_match, strtolower($_SERVER['HTTP_USER_AGENT']))) $is_mob = true;
        elseif ((strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') > 0) or ((isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])))) $is_mob = true;

        $mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4));
        $mobile_agents = array(
            'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
            'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
            'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
            'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
            'newt','noki','palm','pana','pant','phil','play','port','prox',
            'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
            'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',
            'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
            'wapr','webc','winw','winw','xda ','xda-');

        if (in_array($mobile_ua,$mobile_agents)) $is_mob = true;

        if (isset($_SERVER['ALL_HTTP']) && strpos(strtolower($_SERVER['ALL_HTTP']),'OperaMini') > 0)
          $is_mob = true;

        if ($is_mob) {
          $w = $max_w-1;
          $h = $max_h-1;
        }
     }

  // устанавливаем соответствующие куки, если мобильная
  if ($is_mob)
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

// Список полей для мастера вычислений
function get_scw_fields($base_field, $table_id, $table_fields, &$scw_fields=array(), $level=1)
{
  foreach ($table_fields as $scw_field)
    {
      if ($scw_field['id']!=$base_field['id'] and ($scw_field['type_field']==5 or ($base_field['type_field']==1 and $scw_field['type_field']==1) or ($level>1 and $scw_field['type_field']==$base_field['type_field'])))
        {
          $scw_fields[$table_id][$scw_field['id']] = $scw_field;
          if ($scw_field['type_field']==5)
            {
              $rel_table = get_table($scw_field['s_table_id']);
              $rel_table_fields = get_table_fields($rel_table);
              $scw_fields[$table_id][$scw_field['id']]['s_name_table'] = $rel_table['name_table'];
              if (!$scw_fields[$scw_field['s_table_id']])
                {
                  get_scw_fields($base_field, $scw_field['s_table_id'], $rel_table_fields, $scw_fields, $level+1);
                  if (!$scw_fields[$scw_field['s_table_id']] and $scw_field['s_table_id']!=$base_field['s_table_id']) unset($scw_fields[$table_id][$scw_field['id']]);
                }
            }
        }
    }
  return $scw_fields;
}

// Прерывание работы скрипта с подключением шапки и подвала программы (свой аналог "die()" или "exit()");
// $die_text - текст сообщения
// $help_id  - идентификатор подсказки
// $cat - показывать ли категории при ошибке (нет по умолч)
//   0 - не показывать категории
//   1 - скрыть категории
//   2 - не показывать шапку вообще
function cb_die($die_text, $help_id="",$cat=-1, $return_string=0)
{
 global $user, $user_default_page, $cur_cat, $cur_table, $cur_report, $cur_calendar, $user_cats, $config, $lang, $script_name, $version;

 if ((isset($config['cb_die_show_cat']))&& ($cat==-1))
     {
        $cat=$config['cb_die_show_cat'];
     }
     else
 if ($cat==-1) $cat=0;

 if (($cat==2) || ($script_name=='cron.php')) // Вызов из крона, либо ajax
    {
      echo "\n $die_text";
      exit;
    }
 global $res_error_die;
 include $config['site_path']."/templates/cb_die.tpl";
 if ($return_string!==0)
       return $res_error_die;
     else
     {
       echo $res_error_die;
       include "index_bottom.php";
       exit;
     }
}

// Корректная альтернатива для basename() - выделение имени файла из пути
function alt_basename($path)
{
  $path = str_replace("\\", "/", $path);
  $file_name = mb_substr($path, mb_strrpos($path, "/")+1);
  return $file_name;
}

// На основе массива условий, создать вычисление на php
// обрабатывающего данное условие, вычисление формируется по умочанию с переменной $line
function form_php_condition($cond_array, $cond_var='$line')
{
  $php_condition="";
  foreach ($cond_array as $one_cond)
    {
      $cond_link_id = "";
      if ($one_cond['field']!=999999999) $field_name = form_int_name($one_cond['field']);
      else $field_name = '$user[\'id\']';
      if ($one_cond['term']=='=') $one_cond['term'] = '==';
      if ($one_cond['oper'] && $one_cond['interval'] && $one_cond['period'])
        {
          if ($one_cond['value']=='now()') $cond_date = '\''.substr($one_cond['oper'],0,3).$one_cond['interval'].$one_cond['period'].'\'';
          elseif ($one_cond['value']=='curdate()') $cond_date = ', strtotime(\''.substr($one_cond['oper'],0,3).$one_cond['interval'].$one_cond['period'].'\')';
        }
        else $cond_date = "";
      if ($one_cond['value']=='{current}') $one_cond['value'] = '$user[\'id\']';
         else
      if ($one_cond['value']=='-{current}-') $one_cond['value'] = '"-".$user[\'id\']."-"';
      elseif ($one_cond['value']=='{current_group}') $one_cond['value'] = '$user[\'group_id\']';
      elseif ($one_cond['value']=='now()') $one_cond['value'] = 'time('.$cond_date.')';
      elseif ($one_cond['value']=='curdate()') $one_cond['value'] = 'date(\'Y-m-d\''.$cond_date.')';
      elseif (isset($one_cond['value_link']))
      {
        if ($one_cond['value_link']=='') $one_cond['value_link']=0;
        $php_condition.= "((is_array(".$cond_var."['".$field_name."']) && (".$cond_var."['".$field_name."']['raw']".$one_cond['term']."'".$one_cond['value_link']."'))||(!is_array(".$cond_var."['".$field_name."']) && ".$cond_var."['".$field_name."']".$one_cond['term']."'".$one_cond['value_link']."'))".$one_cond['union'];
        continue;
      }
      else $one_cond['value'] = "'".$one_cond['value']."'";

      if (trim($one_cond['term'])=='like')
         {
            if (preg_match("#^'-([^-]*?)-'$#", $one_cond['value']))
              $php_condition.='(strpos('.$cond_var."['".$field_name."']".$cond_link_id.", ".$one_cond['value'].')!==false OR '.$cond_var."['".$field_name."']".$cond_link_id."==".str_replace('-', '', $one_cond['value']).')'.$one_cond['union'];
            else
              $php_condition.='(strpos('.$cond_var."['".$field_name."']".$cond_link_id.", ".$one_cond['value'].')!==false)'.$one_cond['union'];
         }
         else
         {
           // Обычное условие
           if ($one_cond['field']!=999999999) $php_condition.='('.($one_cond['value']=='date(\'Y-m-d\''.$cond_date.')'?'substr(':'').$cond_var."['".$field_name."']".$cond_link_id.($one_cond['value']=='date(\'Y-m-d\''.$cond_date.')'?',0,10)':'').$one_cond['term'].$one_cond['value'].')'.$one_cond['union'];
           else $php_condition.='('.($one_cond['value']=='date(\'Y-m-d\''.$cond_date.')'?'substr(':'').$field_name.($one_cond['value']=='date(\'Y-m-d\''.$cond_date.')'?',0,10)':'').$one_cond['term'].$one_cond['value'].')'.$one_cond['union'];
         }
    }
  return $php_condition;
}

// На основе массива условий, создать фильтр на sql
// обрабатывающего данное условие, вычисление формируется по умочанию с переменной $line
function form_sql_condition($cond_array, $table_prefix='')
{
  $sql_condition="";
  foreach ($cond_array as $one_cond)
    {
      if ($one_cond['field']!=999999999) $field_name = form_int_name($one_cond['field']);
      else $field_name = "'{current}'";
      if ($one_cond['term']==' like ') $one_cond['value'] = "%".$one_cond['value']."%";
      if ($one_cond['term']==' not like ') $one_cond['value'] = "%".$one_cond['value']."%";
      if ($one_cond['oper'] && $one_cond['interval'] && $one_cond['period']) $cond_date = $one_cond['oper'].$one_cond['interval'].$one_cond['period'];
      else $cond_date = "";
      if ($one_cond['value_link'])
         {
            $one_cond['value'] = "'".$one_cond['value_link']."'";
         }
         else
      if ($one_cond['value']!='now()' &&  $one_cond['value']!='curdate()') $one_cond['value'] = "'".$one_cond['value']."'";
      $sql_condition.='('.($table_prefix?$table_prefix.'.':'').($one_cond['value']=='curdate()'?"left(":"").$field_name.($one_cond['value']=='curdate()'?",10)":"").$one_cond['term'].$one_cond['value'].$cond_date.')'.$one_cond['union'];
    }
  return $sql_condition;
}


// Проверка полей перед удалением, используются ли они в отчётах/вычислениях/фильтрах по полю связи
// Возвращает сформированный массив из имён и ид вычислений, таблиц, доп. действий
// $del_table - если удаляется таблица, то проверять является ли поле фильтром не нужно
function check_field_actions($field_id, $del_table=0)
{
  $field_id = intval($field_id);
  $sel_limit = 20;
  if (!$field = sql_select_array(FIELDS_TABLE, "id=",$field_id))
      cb_die("Field not found");
  if ($del_table) $table_cond = " AND calc.table_id<>'{$field['table_id']}'";
  $output['buttons'] = array();
  $output['calcs'] = array();
  $output['reports'] = array();
  $output['fields'] = array();
  $output['subtables'] = array();
  $output['print_template'] = array();

  // Проверка поля в вычислениях и доп. действиях
  $sqlQuery = "SELECT tables.name_table AS parent_name, calc.name AS calc_name, calc.table_id AS link_id, calc.id AS calc_id
                FROM ".TABLES_TABLE." AS tables, ".CALC_TABLE." AS calc
                WHERE (calc.calculate LIKE '%[''f$field_id'']%' OR calc.calculate LIKE '%f$field_id%') AND tables.id=calc.table_id{$table_cond} AND calc.disabled='0' LIMIT $sel_limit";
  $result = sql_query($sqlQuery);
  while ($row = sql_fetch_assoc($result))
    {
      $pre_data = array();
      $sel_limit -= 1;
      if (strpos($row['calc_name'], "Button") !== false)
        { // Вычисление является кнопкой доп. действия
          $button_id = str_replace("Button ", "", $row['calc_name']);
          $subresult = sql_select_field(BUTTONS_TABLE, "name", "id=",$button_id);
          if ($subrow = sql_fetch_assoc($subresult))
            {
              $pre_data['action_name'] = $subrow['name'];
              $pre_data['action_id'] = $button_id;
              $pre_data['table_name'] = $row['parent_name'];
              $output['buttons'][$row['link_id']][] = $pre_data;
              continue;
            }
        }

      $pre_data['calc_name'] = $row['calc_name'];
      $pre_data['calc_id'] = $row['calc_id'];
      $pre_data['table_name'] = $row['parent_name'];
      $output['calcs'][$row['link_id']][] = $pre_data;
    }

  if ($sel_limit > 0)
    { // Участие в отчётах
      $sqlQuery = "SELECT cats.name AS cat_name, reports.name AS rep_name, cats.id AS cat_id, reports.id AS rep_id
                  FROM ".CATS_TABLE." AS cats, ".REPORTS_TABLE." AS reports
                  WHERE (reports.code LIKE '%[''f$field_id'']%' OR reports.code LIKE '%f$field_id%')
                  AND cats.id=reports.cat_id LIMIT $sel_limit";
      $result = sql_query($sqlQuery);
      while ($row = sql_fetch_assoc($result)) 
        {
          $pre_data = array();
          $sel_limit -= 1;
          $pre_data['report_name'] = $row['rep_name'];
          $pre_data['report_id']   = $row['rep_id'];
          $pre_data['cat_name']   = $row['cat_name'];
          $output['reports'][$row['cat_id']][] = $pre_data;
        }
    }

  if ($sel_limit > 0 AND !$del_table)
    { // Является ли фильтром по полю связи
      $result = sql_select_field(FIELDS_TABLE, "id, name_field", "table_id=",$field['table_id']," and type_value LIKE '","%|-".$field_id."|%","' LIMIT $sel_limit");
      while ($row = sql_fetch_assoc($result))
        {
          $sel_limit -= 1;
          $output['fields'][$row['id']] = $row['name_field'];
        }
    }

  if ($sel_limit > 0)
    { // Участие в подтаблицах
      $sqlQuery = "SELECT name_table, subs.name AS sub_name, tables.id AS table_id, subs.id AS sub_id
                  FROM ".TABLES_TABLE." AS tables, ".SUBTABLES_TABLE." AS subs
                  WHERE link_field_id=$field_id AND tables.id=subs.table_id LIMIT $sel_limit";
      $result = sql_query($sqlQuery);
      while ($row = sql_fetch_assoc($result))
        {
          $pre_data = array();
          $sel_limit -= 1;
          $pre_data['sub_name'] = $row['sub_name'];
          $pre_data['sub_id'] = $row['sub_id'];
          $pre_data['table_name'] = $row['name_table'];
          $output['subtables'][$row['table_id']][] = $pre_data;
        }
    }
  
  if($sel_limit >0)
      {
        //выбираем имена шаблонов которые используют текущее поле
        $sql = "SELECT name FROM ".FORMS_VARS_TABLE." WHERE table_id=". $field['table_id']." AND (eval LIKE '%[\\'f".$field_id."\\']%' OR eval LIKE '%[\"f".$field_id."\"]%')";
        $result = sql_query($sql);
        while($row = sql_fetch_assoc($result))
        {                           
          $pre_data = array();
          $sel_limit -= 1;          
          $pre_data['print_template_name']   = $row['name'];                
          $output['print_template'][$row1['print_template_id']][] = $pre_data;            
        }        
      }
  if ($output['buttons'] OR $output['calcs'] OR $output['reports'] OR $output['fields'] OR $output['subtables'] OR $output['print_template'])
      return $output;
  else
      return false;
}

// Установка прав доступа на элементы, с учетом вложенных подгрупп
function set_access($table, $set, $pid=0)
{
  global $user;
  $result = sql_select(GROUPS_TABLE, 'pid=',$pid);
  while ($row = sql_fetch_assoc($result))
    {
      $group_id = $row['id'];
      if ($group_id==1 or $pid or ($set['form_id'] and $user['group_id']==$group_id) || ($user['sub_admin']>0 && $group_id==$user['group_id']))
        {
          sql_insert($table, array_merge(array('group_id'=>$group_id), $set));
          if ($table==ACC_FILTERS_TABLE and sql_select_array($table."_par", "group_id=",$group_id," and table_id=",$set['table_id']))
            {
              sql_insert($table."_par", array('group_id'=>$group_id, 'table_id'=>$set['table_id'], 'filter_id'=>$set['filter_id'], 'access'=>0, 'def_use'=>1));
            }
          set_access($table, $set, $group_id);
        }
    }
}

// Набор вычислений для связи с пользователем
function get_user_link_calc($calc_type)
{
  $calc['import'] = <<<EOD
global \$table;
if (\$user['group_id']!=1)
{
  if (\$user['sub_admin']==1)
  {
    if (\$line['Группа доступа']==1)
    {
      if (\$table['user_table_fields']['group_id'])
      {
        data_update(\$event['table_id'], array("f".\$table['user_table_fields']['group_id']=>0), "id=".\$event['line_id']); 
      }
    }
  }
}
EOD;
  
  $calc['del'] = <<<EOD
\$login = \$line[form_int_name(\$table['user_table_fields']['login'])];
if (\$line['status']==2)
{ // delete record from trash -> delete user
  if (\$user['group_id']==1) sql_delete(USERS_TABLE, "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"'");
  elseif (\$user['sub_admin_rights']['access_subadmin']==1) sql_delete(USERS_TABLE, "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"'");
  else
  {
    \$query = sql_select(GROUPS_TABLE, "sub_admin=1");
    while (\$row = sql_fetch_assoc(\$query))
    {
      \$sub_admin_groups[] = \$row['id'];
      \$set=1;
    }
    if (\$set) sql_delete(USERS_TABLE, "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"' and id NOT IN (".implode(", ", \$sub_admin_groups).")");
    else sql_delete(USERS_TABLE, "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"'");
  }
}
else
{ // delete record to trash -> archive user
  if (\$user['group_id']==1) sql_update(USERS_TABLE, array('arc'=>1), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"'");
  elseif (\$user['sub_admin_rights']['access_subadmin']==1) sql_update(USERS_TABLE, array('arc'=>1), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"'");
  else
  {
    \$query = sql_select(GROUPS_TABLE, "sub_admin=1");
    while (\$row = sql_fetch_assoc(\$query))
    {
      \$sub_admin_groups[] = \$row['id'];
      \$set=1;
    }
    if (\$set) sql_update(USERS_TABLE, array('arc'=>1), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"' and id NOT IN (".implode(", ", \$sub_admin_groups).")");
    else sql_update(USERS_TABLE, array('arc'=>1), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"'");
  }
}
EOD;

  $calc['arc'] = <<<EOD
\$login = \$line[form_int_name(\$table['user_table_fields']['login'])];
if (\$line['status']==0)
{ // restore user
  if (\$user['group_id']==1) sql_update(USERS_TABLE, array('arc'=>0), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"'");
  elseif (\$user['sub_admin_rights']['access_subadmin']==1) sql_update(USERS_TABLE, array('arc'=>0), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"'");
  else
  {
    \$query = sql_select(GROUPS_TABLE, "sub_admin=1");
    while (\$row = sql_fetch_assoc(\$query))
    {
      \$sub_admin_groups[] = \$row['id'];
      \$set=1;
    }
    if (\$set) sql_update(USERS_TABLE, array('arc'=>0), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"' and id NOT IN (".implode(", ", \$sub_admin_groups).")");
    else sql_update(USERS_TABLE, array('arc'=>0), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"'");
  }
}
elseif (\$line['status']==1)
{ // archive user
  if (\$user['group_id']==1) sql_update(USERS_TABLE, array('arc'=>1), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"'");
  elseif (\$user['sub_admin_rights']['access_subadmin']==1) sql_update(USERS_TABLE, array('arc'=>1), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"'");
  else
  {
    \$query = sql_select(GROUPS_TABLE, "sub_admin=1");
    while (\$row = sql_fetch_assoc(\$query))
    {
      \$sub_admin_groups[] = \$row['id'];
      \$set=1;
    }
    if (\$set) sql_update(USERS_TABLE, array('arc'=>1), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"' and id NOT IN (".implode(", ", \$sub_admin_groups).")");
    else sql_update(USERS_TABLE, array('arc'=>1), "login='",\$login,"' and id!=1 and id!='",intval(\$config['master_sub_admin']),"' and id!='",\$user['id'],"'");
  }
}
EOD;

  $calc['add'] = <<<EOD
global \$double_cancel, \$archive, \$deleted;
if (!\$event_cancel and !\$double_cancel[\$ID])
{ // if not was a cancel of adding from external form
  foreach (\$table['user_table_fields'] as \$ulf_name=>\$ulf_id) if (\$ulf_name!="invite" and \$ulf_name!="change_mail" and \$ulf_name!="calcs") \$ulf[\$ulf_name] = form_int_name(\$ulf_id);
  \$old_fname = \$event['changed'][\$table['user_table_fields']['fio']]['old'];
  \$old_login = \$event['changed'][\$table['user_table_fields']['login']]['old'];
  \$old_email = \$event['changed'][\$table['user_table_fields']['e_mail']]['old'];
  \$old_passw = \$event['changed'][\$table['user_table_fields']['password']]['old'];
  \$old_group = \$event['changed'][\$table['user_table_fields']['group_id']]['old'];
  \$old_user_id = \$event['changed'][\$table['user_table_fields']['user_id']]['old'];
  if (\$old_group === "0") \$old_login = "";
  if (\$old_login) \$login = \$old_login; else \$login = \$line[\$ulf['login']];
  \$fname_field = \$table_fields[\$table['user_table_fields']['fio']]['name_field'];
  \$login_field = \$table_fields[\$table['user_table_fields']['login']]['name_field'];
  \$email_field = \$table_fields[\$table['user_table_fields']['e_mail']]['name_field'];
  \$save_exit = 1;
  if ((\$old_group==1 || \$line[\$ulf['group_id']]==1 || \$user['id']==\$line[\$ulf['user_id']] || \$user['id']==\$old_user_id || (\$user['group_id']==\$old_group && \$user['sub_admin_rights']['access_subadmin']!=1)) && \$user['group_id']!=1)
    {
      if (\$old_group==1 || \$line[\$ulf['group_id']]==1) \$calc_errors[] = \$lang['no_change_admin_group_settings'];
      elseif (\$user['id']==\$line[\$ulf['user_id']]) \$calc_errors[] = \$lang['no_change_my_group_settings'];
      if (\$old_fname) \$line[\$ulf['fio']] = \$old_fname;
      if (\$old_login) \$line[\$ulf['login']] = \$old_login;
      if (\$old_email) \$line[\$ulf['e_mail']] = \$old_email;
      if (\$old_passw) \$line[\$ulf['password']] = \$old_passw;
      if (\$old_group) \$line[\$ulf['group_id']] = \$old_group;
      if (\$old_user_id) \$line[\$ulf['user_id']] = \$old_user_id;
      \$save_exit = 0;
    }
  if (\$line[\$ulf['group_id']] && \$save_exit)
    { // group is not empty - check of user data
      if (!\$line[\$ulf['fio']])
        { // user name is empty
          if (\$old_fname)
            { // cancel changing
              \$calc_errors[] = \$lang['not_filed_field']." \"".\$fname_field."\". ".\$lang['return_previos_value'];
              \$line[\$ulf['fio']] = \$old_fname;
            }
            else
            { // clear group
              \$calc_errors[] = \$lang['not_filed_field']." \"".\$fname_field."\".";
              \$line[\$ulf['group_id']] = 0;
            }
        }
      if (!\$line[\$ulf['login']] and \$login_field!=\$fname_field)
        { // login is empty
          if (\$old_login)
            { // cancel changing
              \$calc_errors[] = \$lang['not_filed_field']." \"".\$login_field."\". ".\$lang['return_previos_value'];
              \$line[\$ulf['login']] = \$old_login;
            }
            else
            { // clear group
              \$calc_errors[] = \$lang['not_filed_field']." \"".\$login_field."\".";
              \$line[\$ulf['group_id']] = 0;
            }
        }
      if (!\$line[\$ulf['e_mail']] and \$email_field!=\$fname_field and \$email_field!=\$login_field)
        { // e-mail is empty
          if (\$old_email)
            { // cancel changing
              \$calc_errors[] = \$lang['not_filed_field']." \"".\$email_field."\". ".\$lang['return_previos_value'];
              \$line[\$ulf['e_mail']] = \$old_email;
            }
            else
            { // clear group
              \$calc_errors[] = \$lang['not_filed_field']." \"".\$email_field."\".";
              \$line[\$ulf['group_id']] = 0;
            }
        }
      \$data = data_select_array(\$table_id, \$ulf['login']."='",\$line[\$ulf['login']],"' and id!='",\$line['id'],"' and ".\$ulf['group_id']."!=''");
      if (\$data and \$line[\$ulf['login']])
        { // this login already have in another record
          if (\$old_login)
            { // cancel changing
              \$calc_errors[] = \$lang['Login']." \"".\$data[\$ulf['login']]."\" ".\$lang['in_use_another_user']." \"".\$data[\$ulf['fio']]."\". ".\$lang['return_previos_value'];
              \$line[\$ulf['login']] = \$old_login;
            }
            else
            { // clear group
              \$calc_errors[] = \$lang['Login']." \"".\$data[\$ulf['login']]."\" ".\$lang['in_use_another_user']." \"".\$data[\$ulf['fio']]."\".";
              \$line[\$ulf['group_id']] = 0;
            }
        }
      \$result = sql_select(USERS_TABLE, "arc=0 AND group_id!=777 AND login!='",\$login,"' ORDER BY id");
      if (sql_num_rows(\$result)>=\$config['max_users'] and \$line[\$ulf['group_id']]!="777")
        { // exceeded number of permitted users
          if (\$old_group)
            { // cancel changing
              \$calc_errors[] = \$lang['you_can_add_max']." ".\$config['max_users']." ".\$lang['m_users'].". ".\$lang['return_previos_value'];
              \$line[\$ulf['group_id']] = \$old_group;
            }
            else
            { // clear group
              \$calc_errors[] = \$lang['you_can_add_max']." ".\$config['max_users']." ".\$lang['m_users'].".";
              \$line[\$ulf['group_id']] = 0;
            }
        }
      if (!\$line[\$ulf['group_id']]) \$calc_errors[] = \$lang['login_is_not_saved'];
    }
    if (\$line[\$ulf['group_id']] && \$save_exit)
    { // group is not empty and user data is corrected - add/update user
      \$password = "";
      // looking for a user with this login
      \$result = sql_select(USERS_TABLE, "login='",\$login,"'");
      if (\$row = sql_fetch_assoc(\$result))
        { // user exists, update user data
          \$usr_id = \$row['id'];
          \$upd_data = array();
          foreach (\$ulf as \$ulf_name=>\$ulf_field) if (\$ulf_name!="user_id" and \$ulf_field) \$upd_data[\$ulf_name] = \$line[\$ulf_field];
          \$upd_data['fio'] = str_replace(array("\\r","\\n")," ",\$upd_data['fio']); // replace newline characters with a space
          if (!\$old_group and !\$archive and !\$deleted) \$upd_data['arc'] = 0; // if user has been inactive (empty group) - restore user
          if (\$usr_id==1 and \$upd_data['group_id']!=1)
            { // not change group of base admin
              \$calc_errors[] = \$lang['no_cnange_admin_group'];
              \$line[\$ulf['group_id']] = 1;
              \$upd_data['group_id'] = 1;
            }
          sql_update(USERS_TABLE, \$upd_data, "id=",\$usr_id);
          // send update info to user email
          if (\$table['user_table_fields']['change_mail'] and (\$line[\$ulf['login']]!=\$old_login or \$line[\$ulf['password']]!=\$old_passw))
            {
              if (\$mail_tmpl = sql_select_array(MAIL_TEMPLATES_TABLE, 'id=',\$table['user_table_fields']['change_mail']))
                {
                  \$shablons  = array('{program_link}','{fio}','{login}','{password}');
                  \$values = array(\$config['site_url'], \$line[\$ulf['fio']], \$line[\$ulf['login']], \$ulf['password']?\$line[\$ulf['password']]:"не менялся");
                  \$mail_tmpl['body'] = str_replace(\$shablons, \$values, \$mail_tmpl['body']);
                  sendmail(\$mail_tmpl['subject'], \$mail_tmpl['body'], \$line[\$ulf['e_mail']], "", \$mail_tmpl['sender']);
                }
            }
        }
        else
        { // user is not exists, add user data
          \$ins_data = array();
          foreach (\$ulf as \$ulf_name=>\$ulf_field) if (\$ulf_name!="user_id" and \$ulf_field) \$ins_data[\$ulf_name] = \$line[\$ulf_field];
          \$ins_data['fio'] = str_replace(array("\\r","\\n")," ",\$ins_data['fio']); // replace newline characters with a space
          \$ins_data['date_registr'] = date("Y-m-d");
          \$usr_id = sql_insert(USERS_TABLE, \$ins_data);
          if (!\$ulf['password'])
            { // autogeneration password, if password field is not set
              for (\$i=0;\$i<8;\$i++)
                {
                  switch (rand(1,3))
                    {
                      case 1: \$password.= rand(0,9); break;
                      case 2: \$password.= chr(rand(65,90)); break;
                      case 3: \$password.= chr(rand(97,122)); break;
                    }
                }
            }
            else
            {
              \$password = \$line[\$ulf['password']];
            }
          // send invite to user email
          if (\$table['user_table_fields']['invite'])
            {
              if (\$mail_tmpl = sql_select_array(MAIL_TEMPLATES_TABLE, 'id=',\$table['user_table_fields']['invite']))
                {
                  \$shablons  = array('{program_link}','{fio}','{login}','{password}');
                  \$values = array(\$config['site_url'], \$line[\$ulf['fio']], \$line[\$ulf['login']], \$password);
                  \$mail_tmpl['body'] = str_replace(\$shablons, \$values, \$mail_tmpl['body']);
                  sendmail(\$mail_tmpl['subject'], \$mail_tmpl['body'], \$line[\$ulf['e_mail']], "", \$mail_tmpl['sender']);
                }
            }
        }
      // update user link in this table
      \$line[\$ulf['user_id']] = \$usr_id;
      // update password in users table
      if (\$ulf['password'] or \$password)
        {
          if (!\$password) \$password = \$line[\$ulf['password']];
          \$upd_data = array();
          if (\$config['password_hash_coder'] == "md5")
            {
              \$upd_data['password'] = md5(\$password);
              \$upd_data['crypt_mode'] = "md5";
            }
            else
            {
              if (!\$config['password_hash_coder']) \$crypt_type = MHASH_GOST;
              else \$crypt_type = \$config['password_hash_coder'];
              \$upd_data['password'] = base64_encode(mhash_keygen_s2k(\$crypt_type, \$password, \$usr_id, 512));
              \$upd_data['crypt_mode'] = MHASH_GOST;
            }
          \$upd_data['user_hash'] = generate_password(32, 32, 1);
          sql_update(USERS_TABLE, \$upd_data, "id=",\$usr_id);
        }
    }
    elseif (\$old_group && \$save_exit)
    { // group is removed, move user in archive (except base admin)
      sql_update(USERS_TABLE, array('arc'=>1), "login='",\$login,"' and id!=1");
    }
    if (!\$line[\$ulf['group_id']])
    { // group is empty - clear user link in this table
      \$line[\$ulf['user_id']] = 0;
    }
  // cancel of double calculate
  \$double_cancel[\$ID] = 1;
}
EOD;

  $calc['qst'] = <<<EOD
if (\$table['user_table_fields'])
{ // user registration
  global \$qst, \$qst_id;
  foreach (\$table['user_table_fields'] as \$ulf_name=>\$ulf_id) if (\$ulf_name!="invite" and \$ulf_name!="change_mail" and \$ulf_name!="calcs") \$ulf[\$ulf_name] = form_int_name(\$ulf_id);
  \$fname_field = \$table_fields[\$table['user_table_fields']['fio']]['name_field'];
  \$login_field = \$table_fields[\$table['user_table_fields']['login']]['name_field'];
  \$email_field = \$table_fields[\$table['user_table_fields']['e_mail']]['name_field'];
  if(\$qst['allow_update'] != 1 OR empty(\$_REQUEST['hash'])) {
    if (!\$line[\$ulf['fio']])
      { // user name is empty
        // echo "<script>k_answer_hide_form\$qst_id=0; alert('".\$lang['not_filed_field']." \"".\$fname_field."\""."')</script>&nbsp;";
        \$_SESSION["k_qst_{\$qst_id}_answer"] = str_replace("'", "\'", json_encode(array('error'=>"<script>k_answer_hide_form\$qst_id=0; alert('".\$lang['not_filed_field']." \"".\$fname_field."\""."')</script>&nbsp;")));
        \$event_cancel = 1;
        return;
      }
    if (!\$line[\$ulf['login']] and \$login_field!=\$fname_field)
      { // login is empty
        // echo "<script>k_answer_hide_form\$qst_id=0; alert('".\$lang['not_filed_field']." \"".\$login_field."\""."')</script>&nbsp;";
        \$_SESSION["k_qst_{\$qst_id}_answer"] = str_replace("'", "\'", json_encode(array('error'=>"<script>k_answer_hide_form\$qst_id=0; alert('".\$lang['not_filed_field']." \"".\$login_field."\""."')</script>&nbsp;")));
        \$event_cancel = 1;
        return;
      }
    if (!\$line[\$ulf['e_mail']] and \$email_field!=\$fname_field and \$email_field!=\$login_field)
      { // e-mail is empty
        // echo "<script>k_answer_hide_form\$qst_id=0; alert('".\$lang['not_filed_field']." \"".\$email_field."\""."')</script>&nbsp;";
        \$_SESSION["k_qst_{\$qst_id}_answer"] = str_replace("'", "\'", json_encode(array('error'=>"<script>k_answer_hide_form\$qst_id=0; alert('".\$lang['not_filed_field']." \"".\$email_field."\""."')</script>&nbsp;")));
        \$event_cancel = 1;
        return;
      }
  }
  \$data = sql_select_array(USERS_TABLE, "login='",\$line[\$ulf['login']],"'");
  if (\$data and \$line[\$ulf['login']])
    { // this login already have in another record
      echo "<script>k_answer_hide_form\$qst_id=0; alert('".\$lang['Login']." \"".\$line[\$ulf['login']]."\" ".\$lang['already_used_another_user']."')</script>&nbsp;";
      \$_SESSION["k_qst_{\$qst_id}_answer"] = str_replace("'", "\'", json_encode(array('error'=>"<script>k_answer_hide_form\$qst_id=0; alert('".\$lang['Login']." \"".\$line[\$ulf['login']]."\" ".\$lang['already_used_another_user']."')</script>&nbsp;")));
      \$event_cancel = 1;
      return;
    }
  if (\$qst['reg_group']) \$line[\$ulf['group_id']] = \$qst['reg_group'];
  //echo str_replace("'", "\'", json_encode(array('done'=>"<script>k_answer_hide_form\$qst_id=1;</script>")));
}
EOD;

  return $calc[$calc_type];
}

//функция сравнения текста
//$text1,$text2 - текст или путь к файлу
//возвращает текстовое представление разности формата:
//[номер строки исходного файла]
//- что было (отсутствует если только добавление)
//+ что стало (отсутствует если только удаление)
function diff($text1,$text2)
{
  //БЛОК ОБРАБОТКИ ТЕКСТА ПЕРЕД СРАВНЕНИЕМ
  //проверяем на файл
  if (file_exists($text1)) $text1=file_get_contents($text1);
  if (file_exists($text2)) $text2=file_get_contents($text2);
  //формируем массив
  $regex = '/\r?\n/';
  $old = preg_split($regex, htmlspecialchars($text1));
  $new = preg_split($regex, htmlspecialchars($text2));
  //БЛОК ФУНКЦИИ СРАВНЕНИЯ
  function differ($old, $new)
  {
    $matrix = array();
    $maxlen = 0;
    foreach($old as $oindex => $ovalue)
    {
      $nkeys = array_keys($new, $ovalue);
      foreach($nkeys as $nindex)
      {
        $matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1])?$matrix[$oindex - 1][$nindex - 1] + 1 : 1;
        if($matrix[$oindex][$nindex] > $maxlen)
        {
          $maxlen = $matrix[$oindex][$nindex];
          $omax = $oindex + 1 - $maxlen;
          $nmax = $nindex + 1 - $maxlen;
        }
      }
    }
    if($maxlen == 0) return array(array('d'=>$old, 'i'=>$new));
    return array_merge(
        differ(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
        array_slice($new, $nmax, $maxlen),
        differ(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen))
    );
  }
  //БЛОК ФОРМИРОВАНИЯ ПРЕДСТАВЛЕНИЯ
  $result = differ($old, $new);
  $final_text = "";
  foreach ($result as $key => $value)
  {
    if (is_array($value) && (count($value['d']) || count($value['i'])))
    {
      $final_text .= ($key+1)."\n";
      if (count($value['d']))
      {
        foreach ($value['d'] as $v)
        {
          $final_text .= "- ".$v."\n";
        }
      }
      if (count($value['i']))
      {
        foreach ($value['i'] as $v)
        {
          $final_text .= "+ ".$v."\n";
        }
      }
      $final_text .= "";
    }
  }
  return $final_text;
}

function filter_compile_value($filter, $par_filter=array())
{
  if (!$par_filter) $par_filter = sql_select_array(FILTERS_TABLE, "id=",$filter['pid']);
  if (!$par_filter['value']) $par_filter['value'] = "1=1";
  $filter['value'] = str_replace("{parent}", "(".$par_filter['value'].")", $filter['expert']);
  sql_update(FILTERS_TABLE, $filter, 'id=',$filter['id']);
  $result = sql_select(FILTERS_TABLE, "pid=",$filter['id']);
  while ($sub_filter = sql_fetch_assoc($result)) filter_compile_value($sub_filter, $filter);
}

// Функция выгрузки файлов в папку для синхронизации
function sync_files_export($sync_id, $line_id, $field_id, $f_name)
  {
    global $config, $lang, $user, $sync_exp_list, $sync_exp_fields;
    if (!$sync_exp_list)
      { // Считываем настройки синхронизаций, если их нет
        $sync_exp_list = array();
        $result = sql_select(SYNC_TABLE, 'enabled=1');
        while ($one_sync = sql_fetch_assoc($result))
          {
            $one_sync['tables']=array();
            $sync_exp_list[$one_sync['id']]=$one_sync;
          }
        $sync_exp_fields=array();
        $sqlQuery = "SELECT a.table_id, b.* FROM ".FIELDS_TABLE." a, ".SYNC_FIELDS_TABLE." b WHERE a.id=b.field_id and b.enabled='1' and (b.direction=1 or b.direction=2)";
        $result2 = sql_query($sqlQuery);
        while ($one_field = sql_fetch_assoc($result2))
          {
            $sync_exp_fields[$one_field['field_id']][$one_field['sync_id']]=array('sync_id'=>$one_field['sync_id'], 'field_id'=>$one_field['field_id'], 'c_field'=>($sync_exp_list[$one_field['sync_id']]['sync_mode']==0?$one_field['c_field']:0), 'filter_id'=>$one_field['filter_id']);
            $sync_exp_list[$one_field['sync_id']]['fields'][$one_field['field_id']]=array('field_id'=>$one_field['field_id'], 'table_id'=>$one_field['table_id']);
            $sync_exp_list[$one_field['sync_id']]['tables'][$one_field['table_id']]=$one_field['table_id'];
          }
      }

    if ($sync_exp_list[$sync_id]['type_mode']) // Режимы удалённых синхронизаций, выгружаем в temp
        $sync_exp_list[$sync_id]['upload_dir'] = "temp/sync_".$sync_id."/export";

    if (!is_dir($sync_exp_list[$sync_id]['upload_dir']."/files"))
      { // В папке выгрузки отстутсвует папка files, пытаемся создать
        if (!@mkdir($sync_exp_list[$sync_id]['upload_dir']."/files"))
          { // Создание неуспешно
            insert_log("sync", $lang['no_write_to_folder']." &quot;".$sync_exp_list[$sync_id]['upload_dir']."&quot;");
            return;
          }
      }

    // Части имени файла
    if ($sync_exp_list[$sync_id]['sync_mode'])
      {
        $file_field = $field_id;
        $line_res = data_select_field($sync_exp_list[$sync_id]['fields'][$field_id]['table_id'], "s".$sync_id, "id=",$line_id);
        if ($line_row = sql_fetch_assoc($line_res))
            $file_line = $line_row["s".$sync_id];
        else
            return;
        if ($file_line == "" OR $file_line == "S")
            $file_line = "^".$line_id;
      }
    else
      {
        $file_field = $sync_exp_fields[$field_id][$sync_id]['c_field'];
        $file_line  = $line_id;
      }

    if (!copy(get_file_path($field_id, $line_id, $f_name), $sync_exp_list[$sync_id]['upload_dir']."/files/".$file_field."_".$file_line."_".encode_filename($f_name)))
      {
        insert_log("sync", $lang['no_write_to_folder']." &quot;".$sync_exp_list[$sync_id]['upload_dir']."/files&quot;");
        return;
      }
  }

function form_filters_list(&$all_filters, $pid=0, $level=-1, $filter_id=0)
{
  global $table_id;
  $result = sql_select(FILTERS_TABLE, 'table_id=',$table_id,' and pid=',$pid,' and id!=',$filter_id,' ORDER BY num');
  while ($one_filter = sql_fetch_assoc($result))
    {
      if ($level>=0) $one_filter['tab'] = str_repeat("&nbsp;",$level*3)." ∟ ";
      $all_filters[$one_filter['id']] = $one_filter;
      $is_parent = form_filters_list($all_filters, $one_filter['id'], $level+1, $filter_id);
      $all_filters[$one_filter['id']]['is_parent'] = $is_parent;
    }
  if (sql_num_rows($result)) return 1; else return 0;
}

// Генерация кода анкеты
// Входящий параметр - массив, результат выборки из QST_TABLE
function generate_qst_code($qst)
{
  global $config, $lang, $user;
  $qst_id = $qst['id'];
if ((substr($config["site_root"], 0, strlen("http://"))=="http://")||(substr($config["site_root"], 0, strlen("http://"))=="http://")) $site_url=$config["site_root"];
  else
  {
    $site_url="http://".$_SERVER["HTTP_HOST"].$config["site_root"];
  }
  
$items=array(); $required_exists=0;
$tables_cache = array();
$user['is_root'] = 1;
$table = get_table($qst['table_id']);
$table_fields = get_table_fields($table);
$sqlQuery = "SELECT a.*, b.text, b.def FROM ".FIELDS_TABLE." a LEFT JOIN ".QST_FIELDS_TABLE." b on b.field_id=a.id and b.qst_id='$qst_id' WHERE a.table_id='".$qst['table_id']."' ORDER BY field_num";
$result = sql_query($sqlQuery);
while ($field = sql_fetch_assoc($result))
  {
    if (($field['type_field']==1)||($field['type_field']==2)||($field['type_field']==3)||($field['type_field']==4)||
        ($field['type_field']==5)||($field['type_field']==6)||($field['type_field']==9))
        { // Простые поля - текст, число, дата
          $q_field=$table_fields[$field['id']];
          $q_field['default_value_orig']=$q_field['default_value']=$field['default_value']; // сохраняем оригинальное значение по умолчанию
          if ($field['def']) $q_field['default_value']=$field['def']; // Заменяем его нашим
          $q_field['def']=$field['def'];
          if (($q_field['type_field']==2)||($q_field['type_field']==12))
              { // Поле дата
                $q_field["default_value"]=str_replace('{',"[", $q_field["default_value"]);
                $q_field["default_value"]=str_replace('}',"]", $q_field["default_value"]);
                $q_field["def"]=str_replace('{',"[", $q_field["def"]);
                $q_field["def"]=str_replace('}',"]", $q_field["def"]);
              };
          if (($field['type_field']==4))
              { // Поле список
                $line=array($q_field['int_name']=>$q_field['def']);
                $inpt=form_input_type($q_field, $line);
                $q_field["def_select"]=$inpt["input"];
                $line=array($q_field['int_name']=>$q_field['default_value']);
                $inpt=form_input_type($q_field, $line);
                $q_field["default_select"]=$inpt["input"];
              }
          if ($field['type_field']==5)
              {
                $line=array($q_field['int_name']=>$q_field['def']);
                $q_field["display_edit_def"]=form_display_type($q_field, $line, 'edit');
                $line=array($q_field['int_name']=>$q_field['default_value']);
                $q_field["display_edit"]=form_display_type($q_field, $line, 'edit');
              }
          $q_field['required']=$field['main'];
          if ($q_field['required']) $required_exists=1;
          $q_field['item_type']='field';
          $q_field['name']=$field['name_field'];
          $q_field['read']=1;
          $q_field['write']=1;
          $q_field['text']=$field['text'];
          $items[$q_field['field_num']]=$q_field;
        }
  }
$user['is_root'] = 0;

$sqlQuery = "SELECT a.*, b.text FROM ".FIELD_GROUPS." a LEFT JOIN ".QST_GROUPS_TABLE." b on b.group_id=a.id and b.qst_id='$qst_id' WHERE table_id='".$qst['table_id']."' order by num";
$result = sql_query($sqlQuery);
while ($row = sql_fetch_array($result))
  {
    $row['item_type']='group';
    $row['name']=form_display($row['name']);
    $items[$row['num']]=$row;
  }
ksort($items);

$lng_s=$lang['file_wasnt_upload'];
$QST_SAVE_CODE=""; $link_fields_exists=0; $date_fields_exists=0; $file_field_exists=0;
$QST_VARS="";
foreach ($items as $pos=>$item)
{
  if (($item['text'])||($item['def']))
     {
        if  ($item['item_type']=='field')
            {
              $cur_l_date=date("d-m-Y",time());
              $cur_l_time=date("H:i:s d-m-Y",time());
              $shablons = array("{current}", "{current_group}", "[empty_date]",);
              $replace = array($user['id'], $user['group_id'], "0000-00-00 00:00:00");
              $def_val = str_replace($shablons, $replace, $item["default_value"]);
              $def_val_displ = form_display($def_val);
              $field_id = $item['id'];
              $input_control="";
              $i_c = "_".$qst_id."_".$item['id'];
              $single_text = trim(strip_tags(str_replace("<br>","\\n",$item['text'])));
              // Убираем знаки в конце строки
              $l_ch=$single_text[strlen($single_text)-1];
              if (($l_ch==':')||($l_ch=='?')||($l_ch=='.')||($l_ch==',')||($l_ch=='!')) $single_text=substr($single_text,0,-1);
         
              if (($item['def'])&&(!$item['text']))
                 { // Значение по умолчанию, которое не отображется
                   $QST_SAVE_CODE.="qst_input=document.createElement('input');qst_input.setAttribute('type','hidden');qst_input.setAttribute('name','k_input_field$i_c');qst_form.appendChild(qst_input);\n";
                   if($item['type_field']==12 || $item['type_field']==2)
                     {
                       $QST_SAVE_CODE.="qst_input.value='".(($item['def']=='[cur.date]' || $item['def']=='[current_date]')?date("d.m.Y"):(($item['def']=='[cur.time]' || $item['def']=='[current_time]')?date("d.m.Y H:i"):$item['def']))."';\n";
                     }
                     else
                     {
                       $QST_SAVE_CODE.="qst_input.value=k_qst".$qst_id."_def_value".$field_id.";\n";
                     }
                   $QST_VARS.="var k_qst".$qst_id."_def_value".$field_id."='".htmlspecialchars($item['def'])."';\n";
                   continue;
                 }
              if (($item['type_field']==1)||($item['type_field']==3)||($item['type_field']==2)||($item['type_field']==12))
                 {
                    if (($item['type_field']==2)||($item['type_field']==12))
                        {
                          if ($item['type_value']) $input_size = 19;
                          else $input_size = 10;
                          $input_control="<input type=text class='k_input k_input_date' id='k_input_field$i_c' value='$def_val_displ' size='$input_size' maxlength='$input_size'>";
                          $input_control.="\n<script>k_addHandler_date(document.getElementById('k_input_field$i_c'));</script>\n";
                          $date_fields_exists=1;
                        }
                        else
                        {
                          if (($item['type_field']==3)&&($item['mult_value']))
                              $input_control="<textarea class='k_textarea k_textarea$i_c' id='k_input_field$i_c'>".htmlspecialchars($def_val_displ)."</textarea>";
                             else
                              $input_control="<input type=text class='k_input' id='k_input_field$i_c' value='$def_val_displ'>";
                        }
                    $QST_SAVE_CODE.="qst_input=document.createElement('input');qst_input.setAttribute('type','hidden');qst_input.setAttribute('name','k_input_field$i_c');qst_form.appendChild(qst_input);\n";
                    $QST_SAVE_CODE.="qst_input.value=document.getElementById('k_input_field$i_c').value;\n";
                    if ($item['required']) $QST_SAVE_CODE.="if (!qst_input.value) {alert('".$lang['Qst_required_alert'].": \"".$single_text."\"'); document.getElementById('k_savebutton$qst_id').disabled=''; k_was_submited$qst_id=0; return;}\n";
                 }
                 else
              if ($item['type_field']==4)
                 { // Поле список
                    $line=array($item['int_name']=>$item["default_value"]);
                    if ($item['mult_value'])
                       {
                         $input_control="";
                         $i=0; $dc_val="''";
                         foreach ($item['default_select'] as $one_pt)
                           {
                              $input_control.="<input type=checkbox class='k_checkbox' id='k_input_field".$item['id']."_$i' value='".$one_pt['list_value']."' ".($one_pt['checked']?"checked":"")." >".$one_pt['display_value']."<br>\n";
                              $dc_val.="+(document.getElementById('k_input_field".$item['id']."_$i').checked?document.getElementById('k_input_field".$item['id']."_$i').value+".'"\r\n"'.":'')";
                              $i++;
                           };
                          $QST_SAVE_CODE.="qst_input=document.createElement('input');qst_input.setAttribute('type','hidden');qst_input.setAttribute('name','k_input_field$i_c');qst_form.appendChild(qst_input);\n";
                          $QST_SAVE_CODE.="qst_input.value=".$dc_val.";\n";
                          if ($item['required']) $QST_SAVE_CODE.="if (!qst_input.value) {alert('".$lang['Qst_required_alert'].": \"".$single_text."\"'); document.getElementById('k_savebutton$qst_id').disabled=''; k_was_submited$qst_id=0; return;}\n";
                       }
                       else
                       {
                         $input_control="<select class='k_select' id='k_input_field$i_c' value='$def_val_displ' >".$item['default_select']."</select>";
                         $QST_SAVE_CODE.="qst_input=document.createElement('input');qst_input.setAttribute('type','hidden');qst_input.setAttribute('name','k_input_field$i_c');qst_form.appendChild(qst_input);\n";
                         $QST_SAVE_CODE.="qst_input.value=document.getElementById('k_input_field$i_c').value;\n";
                         if ($item['required']) $QST_SAVE_CODE.="if (!qst_input.value) {alert('".$lang['Qst_required_alert'].": \"".$single_text."\"'); document.getElementById('k_savebutton$qst_id').disabled=''; k_was_submited$qst_id=0; return;}\n";
                       }
                 }
                 else
              if ($item['type_field']==5)
                 { // Поле связь
                   $line[$item['int_name']]=$def_val;
                   $def_val_displ = form_display_type($item, $line, "edit");
                   $parent_link_params="";
                   if ($item['parent_link_field'])
                      {
                        $parent_link_params=", additional_params: function() { return '&filter_value='+document.getElementById('k_input_field_".$qst_id."_".$item['parent_link_field']."').getAttribute('f_value')+'';}";
                      }
                   $input_control=<<<EOD
<input
type="text"
id='k_input_field$i_c'
f_value="$def_val";
value="$def_val_displ"
class="k_input_link_field"
onfocus="k_last_focus=this; this.nextSibling.className='k_drop_down_icon_hover';$(this).addClass('k_input_link_field_hover');"
onblur="k_last_focus=0; if (this.nextSibling!=k_under_mouse_object) {this.nextSibling.className='k_drop_down_icon'; $(this).removeClass('k_input_link_field_hover');}"
onmouseover="this.nextSibling.className='k_drop_down_icon_hover';$(this).addClass('k_input_link_field_hover');"
onmouseout="if (this!=k_last_focus) {this.nextSibling.className='k_drop_down_icon';$(this).removeClass('k_input_link_field_hover');}"><span
class="k_drop_down_icon"
onmouseover="k_under_mouse_object=this;$(this.previousSibling).addClass('k_input_link_field_hover');this.className='k_drop_down_icon_hover';"
onmouseout="k_under_mouse_object=0; if (this.previousSibling!=k_last_focus) {this.className='k_drop_down_icon'; $(this.previousSibling).removeClass('k_input_link_field_hover');}"
onclick="this.blur();this.previousSibling.focus();$(this.previousSibling).show_search();return false;"
onmousedown="$(this.previousSibling).prevent_blur();return false;"
onmouseup="$(this.previousSibling).enable_blur();"
ondragstart="event.returnValue=!1;"></span>
<script>
$("#k_input_field$i_c").f_autocomplete("$site_url/questionare.php?qst_id=$qst_id&sel=link_value&field=$field_id", {width: 280, scrollHeight: 300, formatItem: k_formatItem, formatResult: k_formatResult, max: 1000, alt_ajax: 1 $parent_link_params }).result(k_fix_result);
</script>
EOD;
                   $QST_SAVE_CODE.="qst_input=document.createElement('input');qst_input.setAttribute('type','hidden');qst_input.setAttribute('name','k_input_field$i_c');qst_form.appendChild(qst_input);\n";
                   $QST_SAVE_CODE.="qst_input.value=document.getElementById('k_input_field$i_c').getAttribute('f_value');\n";
                   if ($item['required']) $QST_SAVE_CODE.="if (!qst_input.value) {alert('".$lang['Qst_required_alert'].": \"".$single_text."\"'); document.getElementById('k_savebutton$qst_id').disabled=''; k_was_submited$qst_id=0; return}\n";
                   $link_fields_exists=1;
                 }
                 else
              if (($item['type_field']==6)||($item['type_field']==9))
                 { // Поле файл
                   // Поле файл требует рабочей формы, поэтому ставим закрывающий тег формы
                   $lng_add=$lang['Add'];
                   $input_control=<<<EOD
<span class="k_file_url" id='add_file_url$i_c'>
<div class="k_file_form">
<form method=post enctype="multipart/form-data"
action="$site_url/questionare.php?qst_id=$qst_id&k_rand="
id='k_sbmt_file$i_c'
was_upload=0
target='k_frame_upload_$qst_id'>
<input type="hidden" name="sel" value="save_file" />
<input type=hidden name=csrf value=$csrf>
<div class="k_file_form2">
<input type=file name="k_lnk_add_file[]" size=1
onclick ='if (k_upload_in_progress) {alert("$lng_s");return false;}'
onchange='k_add_file(this);'
i_c='$i_c'
field_id='$field_id'
multiple="multiple">
</div>
</form>
</div>
$lng_add
</span>
EOD;
                   if ($item['required']) $QST_SAVE_CODE.="if (!document.getElementById('k_sbmt_file$i_c').getAttribute('was_upload') == '0') {alert('".$lang['Qst_required_alert'].": \"".$single_text."\"'); document.getElementById('k_savebutton$qst_id').disabled=''; k_was_submited$qst_id=0; return;}\n";
                   $file_field_exists=1;
                 }
              $QST_HTML_CODE.="<tr class='k_tr_line$i_c'><td class='k_td_field1'><span class='k_field_name'>".$item['text']."</span>";
              if ($item['required']) $QST_HTML_CODE.="<span class='k_required'>*</span>";
              $QST_HTML_CODE.="</td><td class='k_td_field2'><span class='k_text'>$input_control</span></td></tr>";
            }
            else
        if  ($item['item_type']=='group')
            { // Группа
              $QST_HTML_CODE.="<tr class='k_tr_group_".$qst_id."_".$item['id']."'><td colspan=2 class='k_td_group'><span class='k_qst_group'>".$item['text']."</span></td></tr>";
            }
     }
};
        // $qst_res_text = $qst["res_text"];
        if (!$qst_res_text) $qst["res_text"]=$qst_res_text=$lang['def_qst_res_text'];
        $qst_res_text=str_replace("'","\\'",(str_replace("\n",'\n\\'."\n",$qst_res_text)));
        if (!$qst["button_text"]) $qst["button_text"]=$lang['Qst_def_button_text'];

        // Добавляем другую свою форму в конец документа чтобы не мешать существующим формам
        if ($qst['button_type'])
             $QST_SAVE_BUTTON="<a href='#' id='k_savebutton$qst_id' onclick='if (k_upload_in_progress) alert(\"$lng_s\"); else k_save_qst$qst_id(); return false;' class='k_save_href'>&nbsp;</a>";
           else
             $QST_SAVE_BUTTON="<input type='button' id='k_savebutton$qst_id' onclick='if (k_upload_in_progress) alert(\"$lng_s\"); else k_save_qst$qst_id();return false;' class='k_save_button' value='".$qst['button_text']."'>";

        $QST_ADD_HEADERS_CODE="";
        $QST_CALENDAR="";
        $QST_LINKS_CODE="";

        if ($date_fields_exists||$link_fields_exists||$file_field_exists)
           {
             if (($file_field_exists)&&(!$date_fields_exists)&&(!$link_fields_exists))
$QST_ADD_HEADERS_CODE=<<<EOD
<script type="text/javascript" src="$site_url/include/jquery/jquery.min.js"></script>
EOD;
                else
$QST_ADD_HEADERS_CODE=<<<EOD
<head>
<link rel="stylesheet" type="text/css" href="$site_url/include/jquery/autocomplete/jquery.autocomplete.css" />
<link rel="stylesheet" type="text/css" href="$site_url/include/jquery/autocomplete/lib/thickbox.css" />
<link rel="stylesheet" type="text/css" href="$site_url/include/jquery/jquery-ui.css" />
</head>

<script type="text/javascript" src="$site_url/include/jquery/jquery.min.js"></script>
<script type='text/javascript' src='$site_url/include/jquery/autocomplete/lib/jquery.ajaxQueue.js'></script>
<script type='text/javascript' src='$site_url/include/jquery/autocomplete/lib/thickbox-compressed.js'></script>
<script type='text/javascript' src='$site_url/include/jquery/autocomplete/jquery.autocomplete.js'></script>
<script type='text/javascript' src='$site_url/include/jquery/jquery-ui.min.js'></script>
<script type='text/javascript' src='$site_url/include/jquery/jquery.ui.datepicker.js'></script>
<script type='text/javascript' src='$site_url/include/jquery/i18n/jquery.ui.datepicker-ru.js'></script>

<script>
function k_addHandler(object, event, handler, useCapture) {
    if ((document.getElementById('edit_form'))&&(object==document.getElementById('edit_form'))&&(event=="onsubmit"))
       { // сохраняем события onsubmit формы edit_form
         edit_form_submits.push(handler);
       }

    if (object.addEventListener) {
        var t1;
        if (event.substr(0,2).toLowerCase()=="on") t1=event.substr(2,1024); // убираем приставку on
        object.addEventListener(t1, handler, useCapture ? useCapture : false);
    } else if (object.attachEvent) {
        object.attachEvent(event, handler);
    } else alert("Add handler is not supported");
}
</script>
EOD;
           }
        if ($date_fields_exists)
           {
             $QST_CALENDAR=<<<EOD
function k_addHandler_date(obj)
{
  var d=new Date();
  if ((obj.value=="[cur.date]")||(obj.value=="[current_date]")) 
  {
    day = d.getDate(); if (day<10) day = "0" + day;
    month = d.getMonth()+1; if (month<10) month = "0" + month;
    obj.value=day+"."+month+"."+d.getFullYear();
  }
  if ((obj.value=="[current_time]")||(obj.value=="[cur.time]")) 
  {
    day = d.getDate(); if (day<10) day = "0" + day;
    month = d.getMonth()+1; if (month<10) month = "0" + month;
    hours = d.getHours(); if (hours<10) hours = "0" + hours;
    minute = d.getMinutes(); if (minute<10) minute = "0" + minute;
    obj.value=day+"."+month+"."+d.getFullYear()+" "+hours+":"+minute+":00";
  }

  k_addHandler(obj,"onkeydown", k_onkeydown_date);
  $(obj).datepicker({
        showOn:"button",
        showAlways: true,
        buttonImage: "$site_url/images/calbtn.png",
        buttonImageOnly: true,
        buttonText: "Calendar",
        showAnim: (('\v'=='v')?"":"show"),  // в ie не включаем анимацию, тормозит
  })

  if ($(obj).attr('size') == "19")
    {
      $(obj).bind("change", function(){
        if (this.value.length < 11)
          {
            hours = d.getHours(); if (hours<10) hours = "0" + hours;
            minute = d.getMinutes(); if (minute<10) minute = "0" + minute;
            this.value+= " "+hours+":"+minute+":00";
          }
      });
    }
};

function k_onkeydown_date(event)
{
  var obj=event.target; if (!obj) obj=event.srcElement;
  if ((event.keyCode == 0xA)||(event.keyCode == 0xD))
     {
       if (window.event)
          {
            window.event.cancelBubble=true;
            window.event.returnValue = false;
          }
          else
          {
            event.stopPropagation();
            event.cancelBubble=true;
            event.returnValue = false;
          }
       // Если нажат enter отменяем событие, и сохраняем значение
       this.blur();
       return false;
     }
}

EOD;
           }
        if ($link_fields_exists)
           {
             $QST_LINKS_CODE='var k_under_mouse_object=0;'."\n".
'var k_last_focus="";'."\n".
'function k_formatItem(row) {'."\n".
'  return row[2].replace(/\r/g,"<br>\n");'."\n".
'}'."\n".
'function k_formatResult(row) {'."\n".
'  if (row[0]=="") return "";'."\n".
'  return row[1];'."\n".
'}'."\n".
'function k_fix_result(event, data, formatted) {'."\n".
'  this.setAttribute(\'f_value\',data[0]);'."\n".
'}'."\n";
           }
        if ($file_field_exists)
           {
            $QST_FILES_CODE=<<<EOD
// Загрузка файла
var k_upload_files_list;

function k_add_file(obj)
{
  var page_charset=window.document.charset;
  if (!page_charset) page_charset=window.document.characterSet;
  var i_c=obj.getAttribute('i_c');
  var field_id=obj.getAttribute('field_id');
  var value=obj.value;
  var progress_span="<span class='k_upload_progress k_upload_progress_img'></span>";
  k_upload_files_list=[];
  if (obj.files)
     { // Новый режим многофайловость
       var i;
       for (i=0;i<obj.files.length;i++)
           {
             value=obj.files[i].fileName;
             if (typeof(value)=='undefined') value=obj.files[i].name;
             var new_line=$("<div class='k_upload_res'>"+value+progress_span+"</div>");
             var m_p =document.getElementById('add_file_url'+i_c);
             $(new_line).insertBefore(m_p);
             var f_info=new Object();
             f_info.name=value;
             f_info.obj=new_line;
             f_info.field_id=field_id;
             k_upload_files_list.push(f_info);
           }
     }
     else
     {  // Старый режим
        // Если указан полный путь оставляем только имя файла
        var last_slash=-1;
        var last_slash_p1=0;
        var last_slash_p2=-1;
        while (1)
          {
            last_slash_p2=value.indexOf('\\\\',last_slash_p1);
            if (last_slash_p2==-1) break;
            last_slash_p1=last_slash_p2+1;
            last_slash=last_slash_p2;
          }
        if (last_slash!=-1)
          {
            value=value.substr(last_slash+1,1024*1024);
          };
        var new_line=$("<div class='k_upload_res'>"+value+progress_span+"</span>");
        var m_p =document.getElementById('add_file_url'+i_c);
        $(new_line).insertBefore(m_p);
        var f_info=new Object();
        f_info.name=value;
        f_info.obj=new_line;
        f_info.field_id=field_id;
        k_upload_files_list.push(value);
     }
  k_upload_in_progress=1;
  document.getElementById("k_sbmt_file"+i_c).action="$site_url/questionare.php?sel=save_file&qst_id=$qst_id&field_id="+field_id+"&page_charset="+page_charset+"&k_rand="+k_form_rand_$qst_id;
  document.getElementById("k_sbmt_file"+i_c).submit();
  obj.value="";
}
EOD;
              $close_form="</form>"; // Для полей типа файл необходимы валидные формы
           }
        if ($required_exists)
           {
             $QST_HTML_CODE.="<tr class='k_tr_required_$qst_id'><td colspan=2 class='k_required_text'><span class='k_required'>*</span> - ".$lang["Qst_required_text"]."</td></tr>";
           }
        $lang_invl_upload=$lang['Qst_invalid_upload'];
        $lang_failed=$lang['Failed'];

        $QST_HTML_CODE=<<<EOD

$QST_ADD_HEADERS_CODE

<script>

var k_form_rand_$qst_id=Math.random()+"_"+(new Date()).getTime(); // Уникальный id формы, используется в файлах и для получения ответа анкеты
var k_upload_in_progress=0;
var k_answer_hide_form$qst_id=1;
var k_was_submited$qst_id=0;
var k_{$qst_id}_curr_hash = '';

$QST_VARS

$QST_LINKS_CODE

$QST_CALENDAR

$QST_FILES_CODE

function k_save_qst$qst_id()
{
k_answer_hide_form$qst_id=1;
var page_charset=window.document.charset;
if (!page_charset) page_charset=window.document.characterSet;

if (typeof(custom_save_qst$qst_id) == 'function') {
      if (!custom_save_qst$qst_id()) return ;
   }
if (k_was_submited$qst_id) return;
k_was_submited$qst_id=1;
document.getElementById('k_savebutton$qst_id').disabled=true;

var qst_form=document.createElement("form");
qst_form.setAttribute('enctype', 'multipart/form-data');
qst_form.setAttribute('action',  '$site_url/questionare.php?page_charset='+page_charset+'&ts'+new Date().getTime());
qst_form.setAttribute('target',  'k_frame_upload_$qst_id');
qst_form.setAttribute('method',  'post');
document.body.appendChild(qst_form);
var qst_input;
$QST_SAVE_CODE;
if(k_{$qst_id}_curr_hash != '') {
  qst_input=document.createElement('input');qst_input.setAttribute('type','hidden');qst_input.setAttribute('name','hash');qst_form.appendChild(qst_input);
  qst_input.value=k_{$qst_id}_curr_hash;
} else {
  if(document.location.href.split('hash=')[1]) {
    if(document.location.href.split('hash=')[1].split('.').length>1) {
      qst_input=document.createElement('input');qst_input.setAttribute('type','hidden');qst_input.setAttribute('name','hash');qst_form.appendChild(qst_input);
      qst_input.value=document.location.href.split('hash=')[1].split('.')[0];
    }
  }
}

qst_input=document.createElement('input');qst_input.setAttribute('type','hidden');qst_input.setAttribute('name','k_rand_$qst_id');qst_form.appendChild(qst_input);
qst_input.value=k_form_rand_$qst_id;
qst_form.submit();
document.body.removeChild(qst_form);
};
function k_save_resp$qst_id(event)
{
  if (document.getElementById('k_frame_upload_$qst_id').src=='') return ; // Выходим если загрузки не было

  var page_charset=window.document.charset;
  if (!page_charset) page_charset=window.document.characterSet;
  if (k_upload_in_progress)
     { // Загрузка файлов
       var obj=event.target; if (!obj) obj=event.srcElement;
       // Убираем значек загрузки
       $(".k_upload_progress").removeClass('k_upload_progress_img');
       // Необходима проверка - загружен ли файл
       for (var i = 0; i < k_upload_files_list.length; i++)
           {
              var f_name=k_upload_files_list[i].name;
              var f_obj =k_upload_files_list[i].obj;
              var rnd_sess=Math.floor(Math.random()*10000);
              var ss = document.createElement('script');
              ss.src="$site_url/questionare.php?sel=test_upload&qst_id=$qst_id&k_rand="+k_form_rand_$qst_id+"&k_resp="+rnd_sess+"&f_name="+f_name+"&page_charset="+page_charset;
              ss.setAttribute('i_pos',i);
              ss.setAttribute('rnd_sess',rnd_sess);
              var done = false;
              ss.onload=ss.onreadystatechange=function(){
                 if ( !done && (!this.readyState || this.readyState === "loaded" || this.readyState === "complete") )
                    {
                        done = true;
                        var i=this.getAttribute("i_pos");
                        var rnd_sess=this.getAttribute("rnd_sess");
                        var f_name=k_upload_files_list[i].name;
                        var f_obj =k_upload_files_list[i].obj
                        var field_id =k_upload_files_list[i].field_id;
                        eval("var data=k_resp"+rnd_sess+";");
                        if (data.indexOf(' uploaded.')!=-1)
                           {
                              var size = parseInt(data)+"";
                              size=size.replace(/(\d)(?=(\d\d\d)+([^\d]|$))/g, '$1 ');
                              // Отображем размер
                              f_obj.find(".k_upload_progress").html(" - "+size+" B");
                           }
                           else
                           {
                              f_obj.find(".k_upload_progress").html(" - $lang_failed");
                           }
                        document.getElementById('k_sbmt_file_'+'$qst_id'+'_'+field_id).setAttribute('was_upload',1);

                        ss.onload = ss.onreadystatechange = null;
                        document.body.removeChild(ss);
                    }
                 }
              document.body.appendChild(ss);
            }
       k_upload_in_progress=0;
       return ;
     }
  if (document.getElementById('k_frame_upload_$qst_id').getAttribute('second_load'))
     {
      if (!k_was_submited$qst_id) return;
      k_was_submited$qst_id=0;
      document.getElementById('k_savebutton$qst_id').style.display='none';
      document.getElementById('k_savebutton$qst_id').disabled=false;
      if (typeof(custom_qst_res$qst_id) == 'function') {
          custom_qst_res$qst_id();
        }
        else
        {
          // Получаем результат формы
          var ss = document.createElement('script');
          ss.src="$site_url/questionare.php?sel=get_answer&qst_id=$qst_id&k_rand="+k_form_rand_$qst_id;
          var done = false;
          ss.onload=ss.onreadystatechange=function(){
             if ( !done && (!this.readyState || this.readyState === "loaded" || this.readyState === "complete") )
                {
                   done = true;
                   eval("var data=k_answer"+$qst_id+";delete k_answer"+$qst_id+";");
                   if (data.done !== undefined)
                      {
                        if (data.done != ' ')
                            $('#k_qst_res_$qst_id').append(data.done);
                            document.getElementById('k_savebutton$qst_id').style.display='';
                      }
                      else if(data.error !== undefined)
                      {
                        $('#k_qst_err_res_$qst_id').html('');
                        if (data.error != ' ') 
                            $('#k_qst_err_res_$qst_id').append(data.error);
                        data = false;
                        k_answer_hide_form$qst_id = 0;
                        document.getElementById('k_savebutton$qst_id').style.display = '';
                      }
                      else
                      {
                        document.getElementById('k_qst_res_$qst_id').innerHTML='$qst_res_text';
                      }
                   if (k_answer_hide_form$qst_id)
                      {
                        document.getElementById('k_qst_$qst_id').style.display='none';
                        location.href='#qst_res_link$qst_id';
                      }

                   ss.onload = ss.onreadystatechange = null;
                   document.body.removeChild(ss);
                }
             }
          document.body.appendChild(ss);
        }
      document.getElementById('k_frame_upload_$qst_id').src=""; // Четвертый уровень защиты от повторной загрузки
     }
     else
      document.getElementById('k_frame_upload_$qst_id').setAttribute('second_load',1);
}
$(function(){
  if(document.getElementById('k_frame_upload_$qst_id').onload == null) {
    $('#k_frame_upload_$qst_id').on('load', function(event) { k_save_resp$qst_id(event); });
  }
});
</script>
$close_form
<iframe name="k_frame_upload_$qst_id" id="k_frame_upload_$qst_id" style='width:1px;height:1px;display:none;' onload='k_save_resp$qst_id(event);'></iframe>
<script>
document.getElementById('k_frame_upload_$qst_id').src='';
</script>
<div class='k_qst_div_$qst_id'>
<div align="center" style="color:red; padding-bottom:15px;" id="k_qst_err_res_$qst_id"></div>
<table class=k_table id='k_qst_$qst_id'>
$QST_HTML_CODE
<tr class='k_tr_submit_$qst_id'><td colspan=2 class='k_td_save'>$QST_SAVE_BUTTON</td></tr>
</table>
<a name="qst_res_link$qst_id"></a>
<div class=k_result_text id='k_qst_res_$qst_id'></div>
</div>
EOD;

        $QST_HTML_CODE.="\n<!-- BEGIN OF CUSTOM JAVASCRIPT -->";
        if ($qst['javascript']) $QST_HTML_CODE.="<script>".$qst['javascript']."</script>";
        $QST_HTML_CODE.="<!-- END OF CUSTOM JAVASCRIPT -->";
        // Смотрим есть ли изображение кнопки, если нет используем стандартное
        $QST_DEFAULT_CSS=<<<EOD
.k_table {
  border: none;
}

.k_td_save {
}

.k_save_href{
 background: url("$site_url/images/def_qst_button.gif") repeat-x top left;
 text-decoration: none;
 display: inline-block;
 width: 98px;
 height: 23px;
}

.k_save_href:hover{
 background: url("$site_url/images/def_qst_button2.gif") repeat-x top left;
 text-decoration: none;
 display: inline-block;
 width: 98px;
 height: 23px;
}

.k_save_button{
}


.k_td_field1{
}

.k_td_field2{
}

.k_td_group{
}

.k_field_name{
}

.k_required{
 color: red;
 padding: 0px 0px 0px 3px;
}

.k_required_text{
}

.k_input{
}

.k_input_date{
}

.k_select{
}

.k_checkbox{
}

.k_textarea{
}

.k_td_group{
 padding: 5px 0px 0px 0px;
}

.k_qst_group{
 padding: 0px;
 font-size: 15px;
 font-weight: bold;
}

.k_result_text{
}

.k_drop_down_icon{
 display: inline-block;
 width: 17px;
 height: 19px;
 padding: 0px 0px 0px 0px;
 background:    transparent;
 border-left:   0px solid white;
 border-right:  1px solid #d0d0d0 !important;
 border-bottom: 1px solid #d0d0d0 !important;
 border-top:    1px solid #d0d0d0 !important;
 vertical-align: middle;
 background:    url("$site_url/images/select_b_noboder.gif") no-repeat top right;
}

.k_drop_down_icon_hover{
 display: inline-block;
 width: 17px;
 height: 19px;
 padding: 0px 0px 0px 0px;
 background:    transparent;
 border-left:   0px solid white;
 border-right:  1px solid #a0a0a0 !important;
 border-bottom: 1px solid #a0a0a0 !important;
 border-top:    1px solid #a0a0a0 !important;
 vertical-align: middle;
 background:    url("$site_url/images/select_b_noboder.gif") no-repeat top right;
}

.k_input_link_field{
 width: 260px;
 line-height: 17px;
 padding: 1px;
 margin: 0px;
 border-top:    1px solid #d0d0d0 !important;
 border-left:   1px solid #d0d0d0 !important;
 border-bottom: 1px solid #d0d0d0 !important;
 border-right:  0px solid #d0d0d0 !important;
 font-size: 13px;
 vertical-align: middle;
}

.k_input_link_field_hover{
 width: 260px;
 line-height: 17px;
 padding: 1px;
 margin: 0px;
 border-top:    1px solid #a0a0a0 !important;
 border-left:   1px solid #a0a0a0 !important;
 border-bottom: 1px solid #a0a0a0 !important;
 border-right:  0px solid #a0a0a0 !important;
 font-size: 13px;
 vertical-align: middle;
}

.ac_results {
  border: 1px solid #a0a0a0 !important;
}

.k_file_url{
 color: blue;
 cursor: pointer;
 text-decoration: underline;
}

.k_file_url:hover{
 color: red;
 cursor: pointer;
 text-decoration: underline;
}

.k_file_url_hover{
 color: green;
 cursor: pointer;
}

.k_file_form{
 position: absolute;
 width: 90px;
 height: 19px;
 overflow: hidden;
 cursor: pointer;
}

.k_file_form2{
 opacity: 0;
}

.k_file_form2 input {
 opacity: 0;
 filter:alpha(opacity=0);
 font-size: 60px;
 cursor: pointer;
 width: 200px;
}

@-moz-document url-prefix(){
 .k_file_form2 input {
 direction: rtl;
 width: auto;
}
}

@media all and (-webkit-min-device-pixel-ratio:0) {
.k_file_form2 input {
 direction: rtl;
 width: auto;
}
}

.k_upload_progress{
}

.k_upload_progress_img{
 background: url("{$site_url}/images/upload_progress.gif") no-repeat top left;
 width:14px;
 height:14px;
 display: inline-block;
 outline: none;
}

.k_upload_res{
}

.k_upload_size{
}

EOD;

  if (!$qst['css']) $qst['css']=$QST_DEFAULT_CSS;
  $QST_HTML_CODE="<style>\n".$qst['css']."</style>".$QST_HTML_CODE;

  return $QST_HTML_CODE;
}

function keygen_s2k($hash, $password, $salt, $bytes)
{
    $result = false;
    if (extension_loaded('hash') === true)
    {
        $times = $bytes / ($block = strlen(hash($hash, null, true)));
        if ($bytes % $block != 0)
        {
            ++$times;
        }
        for ($i = 0; $i < $times; ++$i)
        {
            $result .= hash($hash, str_repeat("\0", $i) . $salt . $password, true);
        }
    }
    return $result;
}

function generate_qst_hash() {
  global $config;
  if($config['password_hash_coder'] == "md5") {
    return base64_encode(keygen_s2k('sha512', uniqid(), '4X1.eJ?i]j=c%glrHEf9yG@P', 128));
  } else {
    return base64_encode(mhash_keygen_s2k(MHASH_GOST, uniqid(), '4X1.eJ?i]j=c%glrHEf9yG@P', 128));
  }
}

function encode_qst_hash($line_id, $qst_hash, $subtable_id = 0) {
  // $iv = "49182631";
  global $config;
  if($config['password_hash_coder'] == "md5") {
    return base64_url_encode(encrypt(serialize( array('lid'=>$line_id, 'sid'=>$subtable_id), base64_decode($qst_hash))));
  } else {
    return base64_url_encode(mcrypt_encrypt(MCRYPT_GOST, base64_decode($qst_hash), serialize( array('lid'=>$line_id, 'sid'=>$subtable_id) ), MCRYPT_MODE_CFB));
  }
  // return bin2hex(mcrypt_encrypt(MCRYPT_GOST, base64_decode($qst_hash), serialize( array('lid'=>$line_id, 'sid'=>$subtable_id) ), MCRYPT_MODE_CFB));
  // mcrypt_encrypt ( string $cipher , string $key , string $data , string $mode [, string $iv ] );
  // return bin2hex(mhash ( MHASH_GOST , serialize( array('lid'=>$line_id) ) , $qst_hash ));
  // return base64_encode(encrypt( serialize( array('lid'=>$line_id) ), $qst_hash ));
}

function decode_qst_hash($data, $qst_hash) {
  // $iv = "49182631";
  global $config;
  if($config['password_hash_coder'] == "md5") {
    return decrypt (base64_decode($qst_hash), base64_url_decode($data));
  } else{
    return mcrypt_decrypt (MCRYPT_GOST, base64_decode($qst_hash), base64_url_decode($data), MCRYPT_MODE_CFB);
  }
  // return mcrypt_decrypt (MCRYPT_GOST, base64_decode($qst_hash), hex2bin($data), MCRYPT_MODE_CFB);
  // return hex2bin(mhash ( MHASH_GOST , serialize( array('lid'=>$line_id) ) , $qst_hash ));
}

if (!function_exists('hex2bin'))
{
  function hex2bin($str)
    {
      for ($i=0; $i<strlen($str); $i+=2)
        {
          $res.= chr(hexdec(substr($str,$i,2)));
        }
      return $res;
    }
}

// Вывод сообщений через вычисления
// $text    - текст сообщения
// $status  - группировка сообщений по произвольному статусу
function calc_alerts($text, $status="error")
{
  global $config, $lang, $smarty, $calc_error_order, $script_name, $line, $ses_id;
  if ($script_name == "update_value.php")
    { // В режиме быстрого редактирования группируем сообщения
      $calc_error_order[$status] = intval($calc_error_order[$status]) + 1;
      echo "\r\nmessage|$status|".$calc_error_order[$status]."|".base64_encode($text)."\r\n";
    }
  elseif (!$line['id'] && $script_name == "calendar.php") 
      $_SESSION[$ses_id]['calc_errors']["_calendar_line_"][] = form_display($text);
  else // В режиме просмотра/редактирования записи (view_line2.php) выводим все сообщения без группировки
      $_SESSION[$ses_id]['calc_errors'][$line['id']][] = form_display($text);
}

// Функция формирует безопасный bb-code
function form_bbcode_safe($text)
{
  global $config, $lang, $allow_bbcode_tags;
  $text = form_display($text);

  foreach ($allow_bbcode_tags AS $one_code => $param)
    {
      $search_results = array();
      // Замена непарных тегов (квадратные скобки в непарных тегах заменяются на html-последовательности)
      $text = preg_replace('/\[('.$one_code.')(.*)\](.*)\[\/('.$one_code.')\]/Usm', "{{{\$1\$2}}}\$3{{{/\$4}}}", $text);
      $text = preg_replace('/\[(\/?)('.$one_code.')\s*\]/Usm', "&#91;\$1\$2&#93;", $text);
      $text = preg_replace('/\{\{\{('.$one_code.')(.*)\}\}\}(.*)\{\{\{\/('.$one_code.')\}\}\}/Usm', "[\$1\$2]\$3[/\$4]", $text);

      if ($one_code == "noparse")
        { // Замена квадратных скобок на html-последовательности
          preg_match_all('/\[noparse\](.*)\[\/noparse\]/Usm', $text, $search_results, PREG_SET_ORDER);
          foreach ($search_results AS $one_result)
              $text = str_replace($one_result[0], str_replace(array("[noparse]", "[/noparse]", "[", "]"), array("", "", "&#91;", "&#93;"), $one_result[1]), $text);
        }

      if ($param)
        { // Проверка тегов с параметрами
          if ($one_code == "color" || $one_code == "size")
            { // Цвет и размер шрифта
              preg_match_all('/\['.$one_code.'(.*)\](.*)\[\/'.$one_code.'\]/Usm', $text, $search_results, PREG_SET_ORDER);
              foreach ($search_results AS $one_result)
                {
                  if (str_replace("=", "", $one_result[1]) == "")
                      $text = str_replace($one_result[0], "[".$one_code."=".$param[$one_code]['default']."]".$one_result[2]."[/".$one_code."]", $text);
                  else
                    {
                      $filter_param = trim(str_replace("=", "", $one_result[1]));
                      $filter_param = form_display($filter_param);
                      $filter_param = str_replace(array("[", "]"), array("&#91;", "&#93;"), $filter_param);
                      $filter_param = preg_replace('/expression[\s*]\((.*)\)/Usm', "\$1", $filter_param);
                      $text = str_replace($one_result[0], "[".$one_code."=".$filter_param."]".$one_result[2]."[/".$one_code."]", $text);
                    }
                }
            }
          if ($one_code == "email" || $one_code == "url" || $one_code == "img")
            { // Ссылки, изображения
              preg_match_all('/\['.$one_code.'(.*)\](.*)\[\/'.$one_code.'\]/Usm', $text, $search_results, PREG_SET_ORDER);
              foreach ($search_results AS $one_result)
                {
                  if (strpos($one_result[1], "=") === 0 && str_replace("=", "", $one_result[1]) == "")
                      $url_param = $param[$one_code]['default'];
                  if (str_replace("=", "", $one_result[1]) == "")
                      $url_param = $one_result[2];
                  else
                      $url_param = substr($one_result[1], 1);
                  $url_param = trim($url_param);
                  $url_param = form_display($url_param);
                  $url_param = str_replace(array("[", "]"), array("&#91;", "&#93;"), $url_param);
                  $url_param = preg_replace('/expression[\s*]\((.*)\)/Usm', "\$1", $url_param);

                  if (strpos(strtolower($url_param), "javascript:") === 0 || strrpos(strtolower($url_param), ".js") == strlen($url_param)-3 || strpos(strtolower($url_param), "data:") === 0)
                      $text = str_replace($one_result[0], $one_result[2], $text);
                  else
                      $text = str_replace($one_result[0], "[".$one_code."=".$url_param."]".$one_result[2]."[/".$one_code."]", $text);
                }
            }
          if ($one_code == "list")
            { // Списки
              preg_match_all('/\[list(.*)\](.*)\[\/list\]/Usm', $text, $search_results, PREG_SET_ORDER);
              foreach ($search_results AS $one_result)
                {
                  if (strpos($one_result[2], "[*]") === false)
                      $one_result[2] = "[*]".$one_result[2];
                  if (!in_array(str_replace("=", "", $one_result[1]), $param[$one_code]['values']))
                      $text = str_replace($one_result[0], "[".$one_code."=".$param[$one_code]['default']."]".$one_result[2]."[/".$one_code."]", $text);
                }
            }
        }
    }

  return $text;
}

// Функция формирует html на основе bb-code
function form_bbcode_html($text)
{
  global $config, $lang, $allow_bbcode_tags;

  foreach ($allow_bbcode_tags AS $one_code => $param)
    {
      if ($one_code == "noparse") continue;
      $search_results = array();

      if ($param === false)
        {
          $regexp = '/\[('.$one_code.')\](.*)\[\/('.$one_code.')\]/Usm';
          if ($one_code == "strike" || $one_code == "s")
              $text = preg_replace($regexp, "<span style='text-decoration: line-through'>\$2</span>", $text);
          elseif ($one_code == "left" || $one_code == "right" || $one_code == "center")
              $text = preg_replace($regexp, "<div style='text-align: ".$one_code."'>\$2</div>", $text);
          else
              $text = preg_replace($regexp, "<\$1>\$2</\$3>", $text);
        }
      else
        {
          if ($one_code == "color")
              $text = preg_replace('/\[color=(.*)\](.*)\[\/color\]/Usm', "<span style='color: \$1'>\$2</span>", $text);
          if ($one_code == "size")
            {
              preg_match_all('/\[size=(.*)\](.*)\[\/size\]/Usm', $text, $search_results, PREG_SET_ORDER);
              foreach ($search_results AS $one_result)
                {
                  $fontsize = 13 + intval($one_result[1]);
                  $text = str_replace($one_result[0], "<span style='font-size: ".$fontsize."px'>".$one_result[2]."</span>", $text);
                }
            }
          if ($one_code == "url")
              $text = preg_replace('/\[url=(.*)\](.*)\[\/url\]/Usm', "<a href=\"\$1\">\$2</a>", $text);
          if ($one_code == "email")
              $text = preg_replace('/\[email=(.*)\](.*)\[\/email\]/Usm', "<a href=\"mailto:\$1\">\$2</a>", $text);
          if ($one_code == "img")
              $text = preg_replace('/\[img=(.*)\](.*)\[\/img\]/Usm', "<img src=\"\$1\" />", $text);
          if ($one_code == "list")
            {
              preg_match_all('/\[list=(.*)\](.*)\[\/list\]/Usm', $text, $search_results, PREG_SET_ORDER);
              foreach ($search_results AS $one_result)
                {
                  $list_option = "";
                  $list_content = array();
                  $list_items = "";
                  if ($one_result[1] == "*")
                      $list_tag = "ul";
                  else
                    {
                      $list_tag = "ol";
                      $list_option = " type='".$one_result[1]."'";
                    }
                  $list_content = explode("[*]", $one_result[2]);
                  foreach ($list_content AS $c => $one_item)
                    {
                      if (!$c) continue;
                      $list_items .= "<li>".$one_item."</li>";
                    }
                  $text = str_replace($one_result[0], "<".$list_tag.$list_option.">".$list_items."</".$list_tag.">", $text);
                }
            }
        }
    }
  return $text;
}

// Функция генерирует безопасный bb-code и формирует html
function form_bbcode_display($text)
{
  return form_bbcode_html(form_bbcode_safe($text));
}

// Функция замены шаблонов в sql условиях (фильтры, напоминания, etc)
function filter_tpl_replace($sql, $calculate="")
{
  global $user, $start_time;
  if (!$start_time) $start_time = time();
  $shablons = array("{current}", "{current_group}", "{new_record}", "{empty_date}", "'{current_date}'", "'{current_time}'", "'{calculate}'");
  $replace  = array($user['id'], $user['group_id'], "(r<>0 and r<'$start_time')", "0000-00-00 00:00:00", "concat(current_date,' 00:00:00')", "now()", in_eval($calculate));
  $sql = str_replace($shablons, $replace, $sql);

  global $sql_current_link, $sql_db_types;
  if ($link_identifier==0) $link_identifier = $sql_current_link;
  if ($sql_db_types[$link_identifier]=="postgresql")
    {
      $sql = str_ireplace("`", "", $sql);
      $sql = str_ireplace("day(", "extract(day from ", $sql);
      $sql = str_ireplace("month(", "extract(month from ", $sql);
      $sql = str_ireplace("year(", "extract(year from ", $sql);
      $sql = str_ireplace("curdate()", "current_date", $sql);
      $sql = preg_replace("!left\((.*?),10\)!i", "cast($1 as date)", $sql);
      $sql = preg_replace("!interval (.*?) (.*?) !i", "interval '$1 $2' ", $sql);
    }

  return $sql;
}

// Функция замены шаблонов в умолчаниях полей
function default_tpl_replace($value)
{
  global $user;
  $shablons = array("{current}", "{current_group}", "{cur.date}", "{cur.time}");
  $replace  = array($user['id'], $user['group_id'], date("Y-m-d 00:00:00"), date("Y-m-d H:i:s"));
  $value = str_replace($shablons, $replace, $value);
  return $value;
}

// Функция замены обратного апострофа для PostgreSQL
function back_apostrophe_replace($str)
{
  global $sql_current_link, $sql_db_types;
  if ($sql_db_types[$sql_current_link]=="postgresql")
    {
      for ($i=0; $i<strlen($str); $i++)
        {
          if (!$infields and $str[$i]=="`") $str[$i] = '"'; // замена ` на "
          if (!$infields and $str[$i]=="'") {$infields = 1; continue;}
          if ( $infields and $str[$i]=="'") {$infields = 0; continue;}
        }
    }
  return $str;
}

function file_size_formats($bytes)
{
  global $lang;
  
  if ($bytes >= 1073741824)
    {
      $size = number_format($bytes / 1073741824, 2).' '.$lang['Gigabytes'];
    }
    elseif ($bytes >= 1048576)
    {
      $size = number_format($bytes / 1048576, 2).' '.$lang['Megabytes'];
    }
    elseif ($bytes >= 1024)
    {
      $size = number_format($bytes / 1024, 2).' '.$lang['Kilobytes'];
    }
    elseif ($bytes > 0)
    {
      $size = $bytes.' '.$lang['Bytes'];
    }
    else
    {
      $size = '0 '.$lang['Bytes'];
    }

  return $size;
}

?>
