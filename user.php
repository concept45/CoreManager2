<?php
/*
    CoreManager, PHP Front End for ArcEmu, MaNGOS, and TrinityCore
    Copyright (C) 2010  CoreManager Project

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


// page header, and any additional required libraries
require_once 'header.php';
require_once 'libs/char_lib.php';
// minimum permission to view page
valid_login($action_permission['view']);

//########################################################################################################################
// BROWSE USERS
//########################################################################################################################
function browse_users()
{
  global $output, $realm_id, $arcm_db, $logon_db, $arcm_db, $characters_db,
    $action_permission, $user_lvl, $user_name, $itemperpage, $showcountryflag, $expansion_select,
    $timezone, $sqlm, $sqlm, $sqll, $sqlc, $core;

  //-------------------SQL Injection Prevention--------------------------------
  $start = (isset($_GET['start'])) ? $sqll->quote_smart($_GET['start']) : 0;
  if (is_numeric($start)); else $start=0;

  $order_by = (isset($_GET['order_by'])) ? $sqll->quote_smart($_GET['order_by']) : 'acct';
  if (preg_match('/^[_[:lower:]]{1,15}$/', $order_by)); else $order_by='acct';

  $dir = (isset($_GET['dir'])) ? $sqll->quote_smart($_GET['dir']) : 1;
  if (preg_match('/^[01]{1}$/', $dir)); else $dir=1;

  $order_dir = ($dir) ? 'ASC' : 'DESC';
  $dir = ($dir) ? 0 : 1;

  //-------------------Search--------------------------------------------------
  $search_by = '';
  $search_value = '';
  // if we have a search request, if not we just return everything
  if(isset($_GET['search_value']) && isset($_GET['search_by']))
  {
    // injection prevention
    $search_value = $sqll->quote_smart($_GET['search_value']);
    $search_by = $sqll->quote_smart($_GET['search_by']);
    $search_menu = array('login', 'acct', 'gm', 'greater_gmlevel', 'email', 'lastip', 'lastlogin', 'banned', 'muted', 'flags');
    if (in_array($search_by, $search_menu));
    else $search_by = 'login';
    unset($search_menu);

    // special search cases
    // developer note: 'if else' is always faster then 'switch case'
    if ($search_by === 'greater_gmlevel')
    {
      //TODO
      $sql_query = 'SELECT acct,login,gm,email,lastip,muted,UNIX_TIMESTAMP(lastlogin) as lastlogin,flags
        FROM accounts WHERE gm > "%'.$search_value.'%" ORDER BY '.$order_by.' '.$order_dir.' LIMIT '.$start.', '.$itemperpage.'';
      $query_1 = $sqll->query('SELECT count(*) FROM accounts WHERE gm > "%'.$search_value.'%"');
    }
    elseif ($search_by === 'banned')
    {
      $sql_query = 'SELECT acct,login,gm,email,lastip,muted,UNIX_TIMESTAMP(lastlogin) as lastlogin,flags
        FROM accounts WHERE acct = 0 ';
      $count_query = 'SELECT count(*) FROM accounts WHERE banned <> 0 ';
      $que = $sqll->query('SELECT acct FROM accounts WHERE banned <> 0');
      while ($banned = $sqll->fetch_assoc($que))
      {
        $sql_query .= 'OR acct = '.$banned['acct'].'';
        $count_query .= 'OR acct = '.$banned['acct'].'';
      }
      $sql_query .= ' ORDER BY '.$order_by.' '.$order_dir.' LIMIT '.$start.', '.$itemperpage.'';
      $query_1 = $sqll->query($count_query);
      unset($count_query);
    }
    else
    {
      // default search case
      $sql_query = 'SELECT acct,login,gm,email,lastip,muted,UNIX_TIMESTAMP(lastlogin) as lastlogin,flags
        FROM accounts WHERE '.$search_by.' LIKE "%'.$search_value.'%" ORDER BY '.$order_by.' '.$order_dir.' LIMIT '.$start.', '.$itemperpage.'';
      $query_1 = $sqll->query('SELECT count(*) FROM accounts WHERE '.$search_by.' LIKE "%'.$search_value.'%"');
    }
    $query = $sqll->query($sql_query);
  }
  else
  {
    // get total number of items
    if ( $core == 1 )
    {
      $query_1 = $sqll->query('SELECT count(*) FROM accounts');
      $query = $sqll->query('SELECT acct,login,gm,email,lastip,muted,UNIX_TIMESTAMP(lastlogin) as lastlogin,flags
        FROM accounts ORDER BY '.$order_by.' '.$order_dir.' LIMIT '.$start.', '.$itemperpage.'');
    }
    else
    {
      $query_1 = $sqll->query('SELECT count(*) FROM account');
      $query = $sqll->query('SELECT account.id AS acct, username AS login, account_access.gmlevel AS gm, email, last_ip AS lastip, mutetime AS muted, UNIX_TIMESTAMP(last_login) AS lastlogin, expansion AS flags
        FROM account LEFT JOIN account_access ON account_access.id = account.id ORDER BY '.$order_by.' '.$order_dir.' LIMIT '.$start.', '.$itemperpage.'');
    }
  }
  // this is for multipage support
  $all_record = $sqll->result($query_1,0);
  unset($query_1);

  //==========================top tage navigaion starts here========================
  // we start with a lead of 10 spaces,
  //  because last line of header is an opening tag with 8 spaces
  //  keep html indent in sync, so debuging from browser source would be easy to read
  $output .='
          <!-- start of user.php -->
          <script type="text/javascript" src="libs/js/check.js"></script>
          <center>
            <table class="top_hidden">
              <tr>
                <td>';
  if ($user_lvl >= $action_permission['insert'])
  {
                  makebutton(lang('user', 'add_acc'), 'user.php?action=add_new', 130);
  // backup is broken
  //              makebutton($lang_user['backup'], 'backup.php', 130);
  }

  // cleanup unknown working condition
  //if($user_lvl >= $action_permission['delete'])
  //              makebutton($lang_user['cleanup'], 'cleanup.php', 130);
                  makebutton(lang('global', 'back'), 'javascript:window.history.back()', 130);
  if ($search_by && $search_value)
  {
                  makebutton(lang('user', 'user_list'), 'user.php', 130);
  }
  $output .= '
                </td>
                <td align="right" width="25%" rowspan="2">';

  // multi page links
  $output .=
                  lang('user', 'tot_acc').' : '.$all_record.'<br /><br />'.
                  generate_pagination('user.php?order_by='.$order_by.'&amp;dir='.(($dir) ? 0 : 1).( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ).'', $all_record, $itemperpage, $start);
  // this part for search
  $output .= '
                </td>
              </tr>
              <tr align="left">
                <td>
                  <table class="hidden">
                    <tr>
                      <td>
                        <form action="user.php" method="get" name="form">
                          <input type="hidden" name="error" value="3" />
                          <input type="text" size="24" maxlength="50" name="search_value" value="'.$search_value.'" />
                          <select name="search_by">
                            <option value="login"'.($search_by === 'login' ? ' selected="selected"' : '').'>'.lang('user', 'by_name').'</option>
                            <option value="acct"'.($search_by === 'id' ? ' selected="selected"' : '').'>'.lang('user', 'by_id').'</option>
                            <option value="gmlevel"'.($search_by === 'gmlevel' ? ' selected="selected"' : '').'>'.lang('user', 'by_gm_level').'</option>
                            <option value="greater_gmlevel"'.($search_by === 'greater_gmlevel' ? ' selected="selected"' : '').'>'.lang('user', 'greater_gm_level').'</option>
                            <option value="expansion"'.($search_by === 'expansion' ? ' selected="selected"' : '').'>'.lang('user', 'by_expansion').'</option>
                            <option value="email"'.($search_by === 'email' ? ' selected="selected"' : '').'>'.lang('user', 'by_email').'</option>
                            <!-- option value="joindate"'.($search_by === 'joindate' ? ' selected="selected"' : '').'>'.lang('user', 'by_join_date').'</option -->
                            <option value="last_ip"'.($search_by === 'last_ip' ? ' selected="selected"' : '').'>'.lang('user', 'by_ip').'</option>
                            <option value="last_login"'.($search_by === 'last_login' ? ' selected="selected"' : '').'>'.lang('user', 'by_last_login').'</option>
                            <option value="online"'.($search_by === 'online' ? ' selected="selected"' : '').'>'.lang('user', 'by_online').'</option>
                            <option value="locked"'.($search_by === 'locked' ? ' selected="selected"' : '').'>'.lang('user', 'by_locked').'</option>
                            <option value="banned"'.($search_by === 'banned' ? ' selected="selected"' : '').'>'.lang('user', 'by_banned').'</option>
                          </select>
                        </form>
                      </td>
                      <td>';
                       makebutton(lang('global', 'search'), 'javascript:do_submit()',80);
  $output .= '
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>';
  //==========================top tage navigaion ENDS here ========================
  $output .= '
            <form method="get" action="user.php" name="form1">
              <input type="hidden" name="action" value="del_user" />
              <input type="hidden" name="start" value="'.$start.'" />
              <input type="hidden" name="backup_op" value="0"/>
              <table class="lined">
                <tr>';
  // column headers, with links for sorting
  // first column is the  selection check box
  if($user_lvl >= $action_permission['insert'])
    $output.= '
                  <th width="1%">
                    <input name="allbox" type="checkbox" value="Check All" onclick="CheckAll(document.form1);" />
                  </th>';
  else
    $output .= '
                  <th width="1%"></th>';
  $output .='
                  <th width="1%"><a href="user.php?order_by=acct&amp;start='.$start.( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ).'&amp;dir='.$dir.'"'.($order_by=='acct' ? ' class="'.$order_dir.'"' : '').'>'.lang('user', 'acct').'</a></th>
                  <th width="1%"><a href="user.php?order_by=login&amp;start='.$start.( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ).'&amp;dir='.$dir.'"'.($order_by=='login' ? ' class="'.$order_dir.'"' : '').'>'.lang('user', 'login').'</a></th>
                  <th width="1%">'.lang('user', 'screenname').'</th>
                  <th width="1%"><a href="user.php?order_by=gm&amp;start='.$start.( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ).'&amp;dir='.$dir.'"'.($order_by=='gm' ? ' class="'.$order_dir.'"' : '').'>'.lang('user', 'gm_level').'</a></th>';
  if ($expansion_select)
    $output .='
                  <th width="1%"><a href="user.php?order_by=flags&amp;start='.$start.( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ).'&amp;dir='.$dir.'"'.($order_by=='flags' ? ' class="'.$order_dir.'"' : '').'>EXP</a></th>';
  $output .='
                  <th width="1%"><a href="user.php?order_by=email&amp;start='.$start.( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ).'&amp;dir='.$dir.'"'.($order_by=='email' ? ' class="'.$order_dir.'"' : '').'>'.lang('user', 'email').'</a></th>
                  <!-- <th width="1%"><a href="user.php?order_by=joindate&amp;start='.$start.( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ).'&amp;dir='.$dir.'"'.($order_by=='joindate' ? ' class="'.$order_dir.'"' : '').'>'.lang('user', 'join_date').'</a></th> -->
                  <th width="1%"><a href="user.php?order_by=lastip&amp;start='.$start.( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ).'&amp;dir='.$dir.'"'.($order_by=='lastip' ? ' class="'.$order_dir.'"' : '').'>'.lang('user', 'ip').'</a></th>
                  <th width="1%">'.lang('user', 'char_count').'</th>
                  <th width="1%"><a href="user.php?order_by=muted&amp;start='.$start.( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ).'&amp;dir='.$dir.'"'.($order_by=='muted' ? ' class="'.$order_dir.'"' : '').'>'.lang('user', 'locked').'</a></th>
                  <th width="1%"><a href="user.php?order_by=lastlogin&amp;start='.$start.( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ).'&amp;dir='.$dir.'"'.($order_by=='lastlogin' ? ' class="'.$order_dir.'"' : '').'>'.lang('user', 'last_login').'</a></th>
                  <th width="1%">'.lang('user', 'online').'</a></th>';
  if ($showcountryflag)
  {
    require_once 'libs/misc_lib.php';
    $output .= '
                  <th width="1%">'.lang('global', 'country').'</th>';
  }
  $output .= '
                </tr>';

  //---------------Page Specific Data Starts Here--------------------------
  while ($data = $sqll->fetch_assoc($query))
  {
    // get screen name for each account
    $sn_query = "SELECT * FROM config_accounts WHERE Login = '".$data['login']."'";
    $sn_result = $sqlm->query($sn_query);
    $screenname = $sqlm->fetch_assoc($sn_result);
    
    // clear character count from previous account
    $char_count = 0;

    foreach ($characters_db as $db)
    {
        $sql = new SQL;
        $sql->connect($db['addr'], $db['user'], $db['pass'], $db['name']);

        if ( $core == 1 )
          $char_query = "SELECT COUNT(*) FROM characters WHERE acct = '".$data['acct']."'";
        else
          $char_query = "SELECT COUNT(*) FROM characters WHERE account = '".$data['acct']."'";
        $char_result = $sql->query($char_query);
        $char_count_fields = $sql->fetch_assoc($char_result);
        $char_count += $char_count_fields['COUNT(*)'];
    }
    
    if ( ($user_lvl >= gmlevel($data['gm'])) || ($user_name == $data['login']) )
    {
      $output .= '
                <tr>';
      if ($user_lvl >= $action_permission['insert'])
        $output .= '
                  <td><input type="checkbox" name="check[]" value="'.$data['acct'].'" onclick="CheckCheckAll(document.form1);" /></td>';
      else
        $output .= '
                  <td>*</td>';
      if ( ($user_lvl >= gmlevel($data['gm'])) || ($user_name == $data['login']) )
        $output .= '
                  <td>'.$data['acct'].'</td>
                  <td>
                    <a href="user.php?action=edit_user&amp;error=11&amp;acct='.$data['acct'].'">'.$data['login'].'</a>
                  </td>';
      else
        $output .= '
                  <td>***</td>
                  <td>*****</td>';
      if ( ($user_lvl >= gmlevel($data['gm'])) || ($user_name == $data['login']) )
        $output .= '
                  <td>
                    <a href="user.php?action=edit_user&amp;error=11&amp;acct='.$data['acct'].'">'.$screenname['ScreenName'].'</a>
                  </td>';
      else
        $output .= '
                  <td>*****</td>';
      $output .= '
                  <td>'.gmlevel_short($data['gm']).'</td>';
      if ($expansion_select)
      {
        $exp_lvl_arr = id_get_exp_lvl();
        $output .= '
                  <td>'.$exp_lvl_arr[$data['flags']][2].'</td>';
        unset($exp_lvl_arr);
      }
      if ( ($user_lvl >= $action_permission['update']) || ($user_name === $data['login']) )
        $output .= '
                  <td><a href="mailto:'.$data['email'].'">'.substr($data['email'],0,15).'</a></td>';
      else
        $output .= '
                  <td>***@***.***</td>';
      if ( ($user_lvl >= $action_permission['update']) || ($user_name === $data['login']) )
        $output .= '
                  <td>'.$data['lastip'].'</td>';
      else
        $output .= '
                  <td>*******</td>';
      $output .= '
                  <td>'.$char_count.'</td>';

      $o_temp = 0;
      foreach ( $characters_db as $db )
      {
        $sql = new SQL;
        $sql->connect($db['addr'], $db['user'], $db['pass'], $db['name']);
        
        if ( $core == 1 )
          $sql_c_query = "SELECT SUM(online) FROM characters WHERE acct = '".$data['acct']."'";
        else
          $sql_c_query = "SELECT SUM(online) FROM characters WHERE account = '".$data['acct']."'";
        $c_query = $sql->query($sql_c_query);
        $c_result = $sql->fetch_row($c_query);
        $o_temp += $c_result[0];
      }
      
      $time_offset = $timezone * 3600;
      
      if ( $data['lastlogin'] <> 0 )
        $lastlog = date("F j, Y @ Hi", $data['lastlogin'] + $time_offset);
      else
        $lastlog = '-';

      $output .= '
                  <td>'.(($data['muted']) ? lang('global', 'yes_low') : lang('global', 'no_low')).'</td>
                  <td class="small">'.$lastlog.'</td>
                  <td>'.(($o_temp<>0) ? '<img src="img/up.gif" alt="" />' : '<img src="img/down.gif" alt="" />').'</td>';
      if ($showcountryflag)
      {
        $country = misc_get_country_by_ip($data['lastip']);
        $output .= '
                  <td>'.(($country['code']) ? '<img src="img/flags/'.$country['code'].'.png" onmousemove="toolTip(\''.($country['country']).'\', \'item_tooltip\')" onmouseout="toolTip()" alt="" />' : '-').'</td>';
      }
      $output .= '
                </tr>';
    }
    else
    {
      $output .= '
                <tr>
                  <td>*</td><td>***</td><td>You</td><td>Have</td><td>No</td>
                  <td class=\"small\">Permission</td><td>to</td><td>View</td><td>this</td><td>Data</td><td>***</td>';
    if ($expansion_select)
      $output .= '
                  <td>*</td>';
    if ($showcountryflag)
      $output .= '
                  <td>*</td>';
    $output .= '
                </tr>';
    }
  }
  $output .= '
                <tr>
                  <td  colspan="';
  if ($expansion_select || $showcountryflag)
  {
    if ($expansion_select && $showcountryflag)
      $output .= '13';
    else
      $output .= '12';
  }
  else
    $output .= '11';
  $output .= '" class="hidden" align="right" width="25%">';
  $output .= generate_pagination('user.php?order_by='.$order_by.'&amp;dir='.(($dir) ? 0 : 1).( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ).'', $all_record, $itemperpage, $start);
  $output .= '
                  </td>
                </tr>
                <tr>
                  <td colspan="8" align="left" class="hidden">';
  if($user_lvl >= $action_permission['delete'])
                    makebutton(lang('user', 'del_selected_users'), 'javascript:do_submit(\'form1\',0)" type="wrn',230);
// backup is broken
//if($user_lvl >= $action_permission['insert'])
//                  makebutton($lang_user['backup_selected_users'], 'javascript:do_submit(\'form1\',1)',230);
  $output .= '
                  </td>
                  <td colspan="';
  if ($expansion_select || $showcountryflag)
  {
    if ($expansion_select && $showcountryflag)
      $output .= '5';
    else
      $output .= '4';
  }
  else
    $output .= '3';
  $output .= '" align="right" class="hidden">'.lang('user', 'tot_acc').' : '.$all_record.'</td>
                </tr>
              </table>
            </form>
            <br />
          </center>
          <!-- end of user.php -->';

}


//#######################################################################################################
//  DELETE USER
//#######################################################################################################
function del_user()
{
  global $output, $logon_db, $action_permission, $sqll;

  valid_login($action_permission['delete']);
  if(isset($_GET['check'])) $check = $_GET['check'];
    else redirect("user.php?error=1");

  $pass_array = "";

  //skip to backup
  if (isset($_GET['backup_op'])&&($_GET['backup_op'] == 1))
  {
    for ($i=0; $i<count($check); $i++)
    {
      $pass_array .= "&check%5B%5D=$check[$i]";
    }
    redirect("user.php?action=backup_user$pass_array");
  }

  $output .= '
        <center>
          <img src="img/warn_red.gif" width="48" height="48" alt="" />
          <h1><font class="error">'.lang('global', 'are_you_sure').'</font></h1>
          <br />
          <font class="bold">'.lang('user', 'acc_ids').': ';

  for ($i=0; $i<count($check); $i++)
  {
    $login = $sqll->result($sqll->query("SELECT login FROM `accounts` WHERE acct = '".$check[$i]."'"),0);
    $output .= "
          <a href=\"user.php?action=edit_user&amp;acct=$check[$i]\" target=\"_blank\">$login, </a>";
    $pass_array .= "&amp;check%5B%5D=$check[$i]";
  }

  $output .= "
          <br />".lang('global', 'will_be_erased')."</font>
          <br /><br />
          <table width=\"300\" class=\"hidden\">
            <tr>
              <td>";
                makebutton(lang('global', 'yes'), "user.php?action=dodel_user$pass_array\" type=\"wrn" ,130);
                makebutton(lang('global', 'no'), "user.php\" type=\"def" ,130);
  $output .= "
              </td>
            </tr>
          </table>
        <br />
        </center>
";

}


//#####################################################################################################
//  DO DELETE USER
//#####################################################################################################
function dodel_user()
{
  global $output, $logon_db, $characters_db, $realm_id, $user_lvl,
    $tab_del_user_characters, $tab_del_user_realmd, $action_permission, $sqll;

  valid_login($action_permission['delete']);

  if(isset($_GET['check']))
    $check = $sqll->quote_smart($_GET['check']);
  else
    redirect("user.php?error=1");

  $deleted_acc = 0;
  $deleted_chars = 0;
  require_once("libs/del_lib.php");

  for ($i=0; $i<count($check); $i++)
  {
    if ($check[$i] != "" )
    {
      list($flag,$del_char) = del_acc($check[$i]);
      if ($flag)
      {
        $deleted_acc++;
        $deleted_chars += $del_char;
      }
    }
  }
  $output .= "
        <center>";
  if ($deleted_acc == 0)
    $output .= "
          <h1><font class=\"error\">".lang('user', 'no_acc_deleted')."</font></h1>";
  else
  {
    $output .= "
          <h1><font class=\"error\">".lang('user', 'total')." <font color=blue>$deleted_acc</font> ".lang('user', 'acc_deleted')."</font><br /></h1>";
    $output .= "
          <h1><font class=\"error\">".lang('user', 'total')." <font color=blue>$deleted_chars</font> ".lang('user', 'char_deleted')."</font></h1>";
  }
  $output .= "
          <br /><br />";
  $output .= "
          <table class=\"hidden\">
            <tr>
              <td>";
                makebutton(lang('user', 'back_browsing'), "user.php", 230);
  $output .= "
              </td>
            </tr>
          </table>
          <br />
        </center>
";

}


//#####################################################################################################
//  DO BACKUP USER
//#####################################################################################################
function backup_user()
{
//this_is_junk: TODO: Convert this to use $logon_db and ArcEmu data
  global $output, $logon_db, $characters_db, $realm_id, $user_lvl, $backup_dir, $action_permission;

  valid_login($action_permission['insert']);

  $sql = new SQL;
  $sql->connect($logon_db['addr'], $logon_db['user'], $logon_db['pass'], $logon_db['name']);

  if(isset($_GET['check'])) $check = $sql->quote_smart($_GET['check']);
    else redirect("user.php?error=1");

  require_once("libs/tab_lib.php");

    $subdir = "$backup_dir/accounts/".date("m_d_y_H_i_s")."_partial";
    mkdir($subdir, 0750);

    for ($t=0; $t<count($check); $t++)
    {
    if ($check[$t] != "" )
    {
      $sql->connect($logon_db['addr'], $logon_db['user'], $logon_db['pass'], $logon_db['name']);
      $query = $sql->query("SELECT acct FROM accounts WHERE acct = $check[$t]");
      $acc = $sql->fetch_array($query);
      $file_name_new = $acc[0]."_{$logon_db['name']}.sql";
      $fp = fopen("$subdir/$file_name_new", 'w') or die (error(lang('backup', 'file_write_err')));
      fwrite($fp, "CREATE DATABASE /*!32312 IF NOT EXISTS*/ {$logon_db['name']};\n")or die (error(lang('backup', 'file_write_err')));
      fwrite($fp, "USE {$logon_db['name']};\n\n")or die (error(lang('backup', 'file_write_err')));
      foreach ($tab_backup_user_realmd as $value) {
      $acc_query = $sql->query("SELECT * FROM $value[0] WHERE $value[1] = $acc[0]");
      $num_fields = $sql->num_fields($acc_query);
      $numrow = $sql->num_rows($acc_query);
      $result = "-- Dumping data for $value[0] ".date("m.d.y_H.i.s")."\n";
      $result .= "LOCK TABLES $value[0] WRITE;\n";
      $result .= "DELETE FROM $value[0] WHERE $value[1] = $acc[0];\n";

      if ($numrow)
      {
        $result .= "INSERT INTO $value[0] (";
        for($count = 0; $count < $num_fields; $count++)
        {
          $result .= "`".$sql->field_name($acc_query,$count)."`";
          if ($count < ($num_fields-1)) $result .= ",";
        }
        $result .= ") VALUES \n";
        for ($i =0; $i<$numrow; $i++)
        {
          $result .= "\t(";
          $row = $sql->fetch_row($acc_query);
          for($j=0; $j<$num_fields; $j++)
          {
            $row[$j] = addslashes($row[$j]);
            $row[$j] = ereg_replace("\n","\\n",$row[$j]);
            if (isset($row[$j]))
            {
              if ($sql->field_type($acc_query,$j) == "int")
                $result .= "$row[$j]";
              else
                $result .= "'$row[$j]'" ;
            }
            else
              $result .= "''";
            if ($j<($num_fields-1))
              $result .= ",";
            }
            if ($i < ($numrow-1))
              $result .= "),\n";
          }
          $result .= ");\n";
        }
        $result .= "UNLOCK TABLES;\n";
        $result .= "\n";
        fwrite($fp, $result)or die (error(lang('backup', 'file_write_err')));
      }
      fclose($fp);

      foreach ($characters_db as $db)
      {
        $file_name_new = $acc[0]."_{$db['name']}.sql";
        $fp = fopen("$subdir/$file_name_new", 'w') or die (error(lang('backup', 'file_write_err')));
        fwrite($fp, "CREATE DATABASE /*!32312 IF NOT EXISTS*/ {$db['name']};\n")or die (error(lang('backup', 'file_write_err')));
        fwrite($fp, "USE {$db['name']};\n\n")or die (error(lang('backup', 'file_write_err')));

        $sql->connect($db['addr'], $db['user'], $db['pass'], $db['name']);
        $all_char_query = $sql->query("SELECT guid,name FROM `characters` WHERE account = $acc[0]");

        while ($char = $sql->fetch_array($all_char_query))
        {
          fwrite($fp, "-- Dumping data for character $char[1]\n")or die (error(lang('backup', 'file_write_err')));
          foreach ($tab_backup_user_characters as $value)
          {
            $char_query = $sql->query("SELECT * FROM $value[0] WHERE $value[1] = $char[0]");
            $num_fields = $sql->num_fields($char_query);
            $numrow = $sql->num_rows($char_query);
            $result = "LOCK TABLES $value[0] WRITE;\n";
            $result .= "DELETE FROM $value[0] WHERE $value[1] = $char[0];\n";
            if ($numrow)
            {
              $result .= "INSERT INTO $value[0] (";
              for($count = 0; $count < $num_fields; $count++)
              {
                $result .= "`".$sql->field_name($char_query,$count)."`";
                if ($count < ($num_fields-1)) $result .= ",";
              }
              $result .= ") VALUES \n";
              for ($i =0; $i<$numrow; $i++)
              {
                $result .= "\t(";
                $row = $sql->fetch_row($char_query);
                for($j=0; $j<$num_fields; $j++)
                {
                  $row[$j] = addslashes($row[$j]);
                  $row[$j] = ereg_replace("\n","\\n",$row[$j]);
                  if (isset($row[$j]))
                  {
                    if ($sql->field_type($char_query,$j) == "int")
                      $result .= "$row[$j]";
                    else
                      $result .= "'$row[$j]'" ;
                  }
                  else
                    $result .= "''";
                  if ($j<($num_fields-1))
                    $result .= ",";
                }
                if ($i < ($numrow-1))
                  $result .= "),\n";
              }
              $result .= ");\n";
            }
            $result .= "UNLOCK TABLES;\n";
            $result .= "\n";
            fwrite($fp, $result)or die (error(lang('backup', 'file_write_err')));
          }
        }
        fclose($fp);
      }
    }
  }
  redirect("user.php?error=15");
}


//#######################################################################################################
//  ADD NEW USER
//#######################################################################################################
function add_new()
{
  global $output, $action_permission, $expansion_select;

  valid_login($action_permission['insert']);

  $output .= "
    <center>
      <script type=\"text/javascript\" src=\"libs/js/sha1.js\">
      </script>
      <script type=\"text/javascript\">
        // <![CDATA[
        function do_submit_data ()
        {
          if (document.form.new_pass1.value != document.form.new_pass2.value)
          {
            alert('".lang('user', 'nonidentical_passes')."');
            return;
          }
          else
          {
            //document.form.pass.value = hex_sha1(document.form.new_user.value.toUpperCase()+':'+document.form.new_pass1.value.toUpperCase());
            document.form.pass.value = document.form.new_pass1.value;
            document.form.new_pass1.value = '0';
            document.form.new_pass2.value = '0';
            do_submit();
          }
        }
        // ]]>
      </script>
      <fieldset id=\"user_new_account\">
        <legend>".lang('user', 'create_new_acc')."</legend>
        <form method=\"get\" action=\"user.php\" name=\"form\">
          <input type=\"hidden\" name=\"pass\" value=\"\" maxlength=\"256\" />
          <input type=\"hidden\" name=\"action\" value=\"doadd_new\" />
          <table class=\"flat\">
            <tr>
              <td>".lang('user', 'login')."</td>
              <td>
                <input type=\"text\" name=\"new_user\" size=\"24\" maxlength=\"15\" value=\"New_Account\" />
              </td>
            </tr>
            <tr>
              <td>".lang('user', 'password')."</td>
              <td>
                <input type=\"text\" name=\"new_pass1\" size=\"24\" maxlength=\"25\" value=\"123456\" />
              </td>
            </tr>
            <tr>
              <td>".lang('user', 'confirm')."</td>
              <td>
                <input type=\"text\" name=\"new_pass2\" size=\"24\" maxlength=\"25\" value=\"123456\" />
              </td>
            </tr>
            <tr>
              <td>".lang('user', 'email')."</td>
              <td>
                <input type=\"text\" name=\"new_mail\" size=\"24\" maxlength=\"225\" value=\"none@mail.com\" />
              </td>
            </tr>
            <tr>
              <td>".lang('user', 'locked')."</td>
              <td>
                <input type=\"checkbox\" name=\"new_locked\" value=\"1\" />
              </td>
            </tr>";
  if ( $expansion_select )
    $output .= "
            <tr>
              <td>".lang('user', 'expansion_account')."</td>
              <td>
                <select name=\"new_expansion\">
                  <option value=\"24\">".lang('user', 'wotlktbc')."</option>
                  <option value=\"16\">".lang('user', 'wotlk')."</option>
                  <option value=\"8\">".lang('user', 'tbc')."</option>
                  <option value=\"0\">".lang('user', 'classic')."</option>
                </select>
              </td>
            </tr>";
  $output .="
            <tr>
              <td>";
  makebutton(lang('user', 'create_acc'), "javascript:do_submit_data()\" type=\"wrn",130);
  $output .= "
              </td>
              <td>";
  makebutton(lang('global', 'back'), "javascript:window.history.back()\" type=\"def",130);
  $output .= "
              </td>
            </tr>
          </table>
        </form>
      </fieldset>
      <br />
      <br />
    </center>
";
}


//#########################################################################################################
// DO ADD NEW USER
//#########################################################################################################
function doadd_new()
{
  global $logon_db, $action_permission, $sqll;

  valid_login($action_permission['insert']);

  if ( empty($_GET['new_user']) || empty($_GET['pass']) )
    redirect("user.php?action=add_new&error=4");

  $new_user = $sqll->quote_smart(trim($_GET['new_user']));
  $password = $sqll->quote_smart($_GET['pass']);

  //make sure username/pass at least 4 chars long and less than max
  if ((strlen($new_user) < 4) || (strlen($new_user) > 15))
    redirect("user.php?action=add_new&error=8");

  require_once("libs/valid_lib.php");
  //make sure it doesnt contain non english chars.
  if (!valid_alphabetic($new_user))
    redirect("user.php?action=add_new&error=9");
  $result = $sqll->query("SELECT login FROM accounts WHERE login = '$new_user'");

  //there is already someone with same username
  if ($sqll->num_rows($result))
    redirect("user.php?action=add_new&error=7");
  else
    $last_ip = "0.0.0.0";
  $new_mail = (isset($_GET['new_mail'])) ? $sqll->quote_smart(trim($_GET['new_mail'])) : NULL;
  $locked = (isset($_GET['new_locked'])) ? $sqll->quote_smart($_GET['new_locked']) : 0;
  $expansion = (isset($_GET['new_expansion'])) ? $sqll->quote_smart($_GET['new_expansion']) : 0;

  $result = $sqll->query("INSERT INTO accounts (login,password,gm,email,lastip,muted,lastlogin,flags,banned)
    VALUES ('$new_user','$password',0 ,'$new_mail','$last_ip', $locked ,NULL, $expansion,0)");
  if ($result)
    redirect("user.php?error=5");

}


//###########################################################################################################
//  EDIT USER
//###########################################################################################################
function edit_user()
{
  global $output, $logon_db, $characters_db, $realm_id, $arcm_db, $arcm_db, $realm_id,
    $user_lvl, $user_name, $gm_level_arr, $action_permission, $expansion_select, $developer_test_mode,
    $multi_realm_mode, $server, $timezone, $sqll, $sqlm, $sqlm, $sqlc, $core;

  if (empty($_GET['acct']))
    redirect("user.php?error=10");

  $acct = $sqll->quote_smart($_GET['acct']);

  if ( $core == 1 )
    $result = $sqll->query("SELECT acct, login, gm, email, lastip, muted, UNIX_TIMESTAMP(lastlogin) AS lastlogin, flags FROM accounts WHERE acct = '$acct'");
  else
    $result = $sqll->query("SELECT account.id AS acct, username AS login, account_access.gmlevel AS gm, email, last_ip AS lastip, mutetime AS muted, UNIX_TIMESTAMP(last_login) AS lastlogin, expansion AS flags FROM account LEFT JOIN account_access ON account.id = account_access.id WHERE account.id = '$acct'");
  $data = $sqll->fetch_assoc($result);
  
  $o_temp = 0;
  foreach ($characters_db as $db)
  {
    $sql = new SQL;
    $sql->connect($db['addr'], $db['user'], $db['pass'], $db['name']);
    
    if ( $core == 1 )
      $online_res = $sql->query("SELECT SUM(online) FROM characters WHERE acct='".$data['acct']."'");
    else
      $online_res = $sql->query("SELECT SUM(online) FROM characters WHERE account='".$data['acct']."'");
    $online_fields = $sql->fetch_assoc($online_res);
    $o_temp += $online_fields['SUM(online)'];
  }
  if($o_temp <> 0)
    $acct_online = 1;
  else
    $acct_online = 0;

  $query = "SELECT * FROM config_accounts WHERE Login = '".$data['login']."'";
  $sn_result = $sqlm->query($query);
  $screenname = $sqlm->fetch_assoc($sn_result);

  $refguid = $sqlm->fetch_assoc($sqlm->query('SELECT InvitedBy FROM point_system_invites WHERE PlayersAccount = '.$data['acct'].''));
  $refguid = $refguid['InveitedBy'];
  $referred_by = $sqlc->fetch_assoc($sqlc->query("SELECT name FROM characters WHERE guid = '$refguid'"));
  unset($refguid);
  $referred_by = $referred_by['name'];
      
  $time_offset = $timezone * 3600;
      
  if ( $data['lastlogin'] <> 0 )
    $lastlog = date("F j, Y @ Hi", $data['lastlogin'] + $time_offset);
  else
    $lastlog = '-';

  if ($sqll->num_rows($result))
  {
    $output .= '
        <center>
          <script type="text/javascript" src="libs/js/sha1.js"></script>
          <script type="text/javascript">
            // <![CDATA[
              function do_submit_data ()
              {';
    if ( $core == 1 )
      $output .= '
                document.form.pass.value = document.form.new_pass.value;';
    else
      $output .= '
                document.form.pass.value = hex_sha1(document.form.username.value.toUpperCase()+":"+document.form.new_pass.value.toUpperCase());';
    $output .= '
                document.form.new_pass.value = "0";
                do_submit();
              }
            // ]]>
          </script>
          <fieldset id="user_edit_account">
            <legend>'.lang('user', 'edit_acc').'</legend>
            <form method="post" action="user.php?action=doedit_user" name="form">
            <input type="hidden" name="pass" value="" maxlength="256" />
            <input type="hidden" name="acct" value="'.$acct.'" />
            <input type="hidden" name="oldscreenname" value="'.$screenname['ScreenName'].'" />
            <input type="hidden" name="oldlogin" value="'.$data['login'].'" />
            <table class="flat">
              <tr>
                <td>'.lang('user', 'acct').'</td>
                <td>'.$data['acct'].'</td>
              </tr>
              <tr>
                <td>'.lang('user', 'login').'</td>';
    if($user_lvl >= $action_permission['update'])
    {
      $output .= '
                <td><input type="text" name="login" size="42" maxlength="15" value="'.$data['login'].'" /></td>';
    }
    else
    {
      if ($screenname['ScreenName'])
        $output .= '
                <td>********</td>';
      else
        $output .= '
                <td>'.$data['login'].'</td>';
    }
    $output .= '
              </tr>
              <tr>
                <td>'.lang('user', 'screenname').'</td>';
    if($user_lvl >= $action_permission['update'])
    {
      $output .='
                <td><input type="text" name="screenname" size="42" maxlength="15" value="'.$screenname['ScreenName'].'" /></td>';
    }
    else
    {
      $output.='
                <td>'.$screenname['ScreenName'].'</td>';
    }
    $output .= '
              </tr>
              <tr>
                <td>'.lang('user', 'password').'</td>';
    if($user_lvl >= $action_permission['update'])
    {
      $output .="
                <td><input type=\"text\" name=\"new_pass\" size=\"42\" maxlength=\"40\" value=\"******\" /></td>";
    }
    else
    {
      $output.="
                <td>********</td>";
    }
    $output .= "
              </tr>
              <tr>
                <td>".lang('user', 'email')."</td>";
    if($user_lvl >= $action_permission['update'])
    {
      $output .= '
                <td><input type="text" name="mail" size="42" maxlength="225" value="'.$data['email'].'" /></td>';
    }
    else
    {
      $output.="
                <td>***@***.***</td>";
    }
    $output .= "
              </tr>
              <tr>
                <td>".lang('user', 'invited_by').":</td>
                <td>";
    if($user_lvl >= $action_permission['update'] && !$referred_by !=NULL)
    {
      $output .="
                  <input type=\"text\" name=\"referredby\" size=\"42\" maxlength=\"12\" value=\"$referred_by\" />";
    }
    else
    {
      $output .="
                  $referred_by";
    }
    $output .="
                </td>
              </tr>
              <tr>
                <td>".lang('user', 'gm_level_long')."</td>";
    if($user_lvl >= $action_permission['update'])
    {
      $output .="
                <td>
                  <input type='text' name='gm' value='".$data['gm']."'>
                  <!-- select name=\"gm\">";
      foreach ($gm_level_arr as $level)
      {
        if (($level[0] > -1) && ($level[0] <= $user_lvl))
        {
          $output .= "
                    <option value=\"{$level[0]}\" ";
          if (gmlevel($data['gm']) == $level[0])
            $output .= "selected=\"selected\" ";
          $output .= ">{$level[1]}</option>";
        }
      }
      $output .= "
                  </select -->
                </td>";
    }
    else
    {
      $output .= '
                <td>'.id_get_gm_level($data['gm']).' ( '.$data['gm'].' )</td>';
    }
    $output .= '
              </tr>
              <tr>
                <td>'.lang('user', 'last_ip').'</td>';
    if($user_lvl >= $action_permission['update'])
    {
      $output .= '
                <td>'.$data['lastip'].'<a href="banned.php?action=do_add_entry&amp;entry='.$data['lastip'].'&amp;bantime=3600&amp;ban_type=ip_banned"> &lt;- '.lang('user', 'ban_this_ip').'</a></td>';
    }
    else
    {
      $output .= "
                <td>***.***.***.***</td>";
    }
    $output .= "
              </tr>
              <tr>
                <td>".lang('user', 'banned')."</td>";
    if ( $core == 1 )
      $que = $sqll->query("SELECT banned FROM accounts WHERE banned <> 0 AND acct = $acct");
    else
      $que = $sqll->query("SELECT bandate, unbandate, bannedby, banreason FROM account_banned WHERE id = $acct");
    if ($sqll->num_rows($que))
    {
      $banned = $sqll->fetch_row($que);
      $ban_info = " From:".date('d-m-Y G:i', $banned[0])." till:".date('d-m-Y G:i', $banned[1])."<br />by $banned[2]";
      $ban_checked = " checked=\"checked\"";
    }
    else
    {
      $ban_checked = "";
      $ban_info    = "";
      $banned[3]   = "";
    }
    if($user_lvl >= $action_permission['update'])
    {
      $output .= "
                <td><input type=\"checkbox\" name=\"banned\" value=\"1\" $ban_checked/>$ban_info</td>";
    }
    else
    {
      $output .= "
                <td>$ban_info</td>";
    }
    $output .="
              </tr>
              <tr>
                <td>".lang('user', 'banned_reason')."</td>";
    if($user_lvl >= $action_permission['update'])
    {
      $output .="
                <td><input type=\"text\" name=\"banreason\" size=\"42\" maxlength=\"255\" value=\"$banned[3]\" /></td>";
    }
    else
    {
      $output .= "
                <td>$banned[3]</td>";
    }
    if ($expansion_select)
    {
      $output .="
              </tr>
              <tr>";
      if($user_lvl >= $action_permission['update'])
      {
        $output .="
                <td>".lang('user', 'client_type')."</td>";
        $output .="
                <td>
                  <select name=\"expansion\">";
        if ( $core == 1 )
        {
          $output .= "
                    <option value=\"0\">".lang('user', 'classic')."</option>
                    <option value=\"8\" ";
          if ($data['flags'] == 8)
            $output .= "selected=\"selected\" ";
          $output .= ">".lang('user', 'tbc')."</option>
                   <option value=\"16\" ";
          if ($data['flags'] ==16)
            $output .= "selected=\"selected\" ";
          $output .= ">".lang('user', 'wotlk')."</option>
                   <option value=\"24\" ";
          if ($data['flags'] ==24)
            $output .= "selected=\"selected\" ";
          $output .= ">".lang('user', 'wotlktbc')."</option>";
        }
        else
        {
          $output .= "
                    <option value=\"0\">".lang('user', 'classic')."</option>
                    <option value=\"1\" ";
          if ($data['flags'] == 1)
            $output .= "selected=\"selected\" ";
          $output .= ">".lang('user', 'tbc')."</option>
                   <option value=\"2\" ";
          if ($data['flags'] == 2)
            $output .= "selected=\"selected\" ";
          $output .= ">".lang('user', 'wotlktbc')."</option>";
        }
        $output .= "
                  </select>
                </td>";
      }
      else
      {
        $output .= "
                <td>".lang('user', 'classic')."</td>";
      }
    }
    $output .="
              </tr>";
    $output .="<tr>
                <td>".lang('user', 'locked')."</td>";
    $lock_checked = ($data['muted']) ? " checked=\"checked\"" : "";
    if($user_lvl >= $action_permission['update'])
    {
      $output .= "
                <td><input type=\"checkbox\" name=\"locked\" value=\"1\" $lock_checked/></td>";
    }
    else
    {
      $output .="
                <td></td>";
    }
    $output.= '
              </tr>
              <tr>
                <td>'.lang('user', 'last_login').'</td>
                <td>'.$lastlog.'</td>
              </tr>
              <tr>
                <td>'.lang('user', 'online').'</td>';
    $output .= "
                <td>".(($acct_online) ? "<img src=\"img/up.gif\" alt=\"\" />" : "<img src=\"img/down.gif\" alt=\"\" />")."</td>
              </tr>";
              
    //$realms = $sqlm->query('SELECT id, name FROM realmlist');
    //while ( $realm = $sqlm->fetch_assoc($realms) )
    foreach ( $characters_db as $db )
    {
      $sql = new SQL;
      $sql->connect($db['addr'], $db['user'], $db['pass'], $db['name']);
      
      if ( $core == 1 )
        $query = "SELECT COUNT(*) FROM characters WHERE acct='".$acct."'";
      else
        $query = "SELECT COUNT(*) FROM characters WHERE account='".$acct."'";
      $result = $sql->query($query);
      $fields = $sql->fetch_assoc($result);
      
      $tot_chars += $fields['COUNT(*)'];
    }
    
    if ( $core == 1 )
      $query = $sqlc->query("SELECT COUNT(*) FROM `characters` WHERE acct = $acct");
    else
      $query = $sqlc->query("SELECT COUNT(*) FROM `characters` WHERE account = $acct");
    $chars_on_realm = $sqlc->result($query, 0);
    $output .= "
              <tr>
                <td>".lang('user', 'tot_chars')."</td>
                <td>$tot_chars</td>
              </tr>";
    $realms = $sqlm->query("SELECT id, name FROM realmlist");
    if ( $sqlm->num_rows($realms) > 1 && ( count($server) > 1 ) && ( count($characters_db) > 1 ) )
    {
      require_once("libs/get_lib.php");
      while ($realm = $sqlm->fetch_array($realms))
      {
        $sqlc->connect($characters_db[$realm[0]]['addr'], $characters_db[$realm[0]]['user'], $characters_db[$realm[0]]['pass'], $characters_db[$realm[0]]['name']);
        if ( $core == 1 )
          $query = $sqlc->query("SELECT COUNT(*) FROM `characters` WHERE acct = $acct");
        else
          $query = $sqlc->query("SELECT COUNT(*) FROM `characters` WHERE account = $acct");
        $chars_on_realm = $sqlc->result($query, 0);
        $output .= "
              <tr>
                <td>".lang('user', 'chars_on_realm')." ".get_realm_name($realm[0])."</td>
                <td>$chars_on_realm</td>
              </tr>";
        if ($chars_on_realm)
        {
          if ( $core == 1 )
            $char_array = $sqlc->query("SELECT guid, name, race, class, level, gender
              FROM `characters` WHERE acct = $acct");
          else
            $char_array = $sqlc->query("SELECT guid, name, race, class, level, gender
              FROM `characters` WHERE account = $acct");
          while ($char = $sqlc->fetch_array($char_array))
          {
            $output .= "
              <tr>
                <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'---></td>
                <td>
                      <a href=\"char.php?id=$char[0]&amp;realm=$realm[0]\">$char[1]</a> - <img src='img/c_icons/{$char[2]}-{$char[5]}.gif' onmousemove='toolTip(\"".char_get_race_name($char[2])."\",\"item_tooltip\")' onmouseout='toolTip()' alt=\"\" />
                      <img src='img/c_icons/{$char[3]}.gif' onmousemove='toolTip(\"".char_get_class_name($char[3])."\",\"item_tooltip\")' onmouseout='toolTip()' alt=\"\"/> - ".lang('char', 'level_short').char_get_level_color($char[4])."
                </td>
              </tr>";
          }
        }
      }
    }
    else
    {
      if ( $core == 1 )
        $query = $sqlc->query("SELECT COUNT(*) FROM `characters` WHERE acct = $acct");
      else
        $query = $sqlc->query("SELECT COUNT(*) FROM `characters` WHERE account = $acct");
      $chars_on_realm = $sqlc->result($query, 0);
      $output .= "
              <tr>
                <td>".lang('user', 'chars_on_realm')."</td>
                <td>$chars_on_realm</td>
              </tr>";
      if ($chars_on_realm)
      {
        $char_array = $sqlc->query("SELECT guid,name,race,class, level, gender FROM `characters` WHERE acct = $acct");
        while ($char = $sqlc->fetch_array($char_array))
        {
          $output .= "
                <tr>
                  <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'---></td>
                  <td>
                    <a href=\"char.php?id=$char[0]\">$char[1]</a> - <img src='img/c_icons/{$char[2]}-{$char[5]}.gif' onmousemove='toolTip(\"".char_get_race_name($char[2])."\",\"item_tooltip\")' onmouseout='toolTip()' alt=\"\" />
                    <img src='img/c_icons/{$char[3]}.gif' onmousemove='toolTip(\"".char_get_class_name($char[3])."\",\"item_tooltip\")' onmouseout='toolTip()' alt=\"\"/> - ".lang('char', 'level_short').char_get_level_color($char[4])."
                  </td>
                </tr>";
        }
      }
    }
    $output .= "
              <tr>
                <td>";
    if($user_lvl >= $action_permission['delete'])
      makebutton(lang('user', 'del_acc'), "user.php?action=del_user&amp;check%5B%5D=$acct\" type=\"wrn",130);
    $output .= "
                </td>
                <td>";
    if($user_lvl >= $action_permission['update'])
      makebutton(lang('user', 'update_data'), "javascript:do_submit_data()",130);
    makebutton(lang('global', 'back'), "javascript:window.history.back()\" type=\"def",130);
    $output .= "
                </td>
                </tr>
              </table>
            </form>
          </fieldset>

          <br /><br />
        </center>";
  }
  else
    error(lang('global', 'err_no_user'));

}


//############################################################################################################
//  DO   EDIT   USER
//############################################################################################################
function doedit_user()
{
  global $logon_db, $arcm_db, $arcm_db, $user_lvl, $user_name, $action_permission, $sqll, $sqlm;

  valid_login($action_permission['update']);

  if ( (!isset($_POST['pass'])||($_POST['pass'] === ''))
    && (!isset($_POST['mail'])||($_POST['mail'] === ''))
    && (!isset($_POST['expansion'])||($_POST['expansion'] === ''))
    && (!isset($_POST['referredby'])||($_POST['referredby'] === '')) )
    redirect("user.php?action=edit_user&acct={$_POST['acct']}&error=1");

  $acct = $sqll->quote_smart($_POST['acct']);
  $login = $sqll->quote_smart($_POST['login']);
  $screenname = $sqlm->quote_smart($_POST['screenname']);
  $banreason = $sqll->quote_smart($_POST['banreason']);
  $password = $sqll->quote_smart($_POST['pass']);
  //$user_password_change = ($password != sha1(strtoupper($login).":******")) ? "login='$login',password='$login'," : "";

  $mail = (isset($_POST['mail']) && $_POST['mail'] != '') ? $sqll->quote_smart($_POST['mail']) : "";
  $failed = (isset($_POST['failed'])) ? $sqll->quote_smart($_POST['failed']) : 0;
  $gmlevel = (isset($_POST['gm'])) ? $sqll->quote_smart($_POST['gm']) : 0;
  $expansion = (isset($_POST['expansion'])) ? $sqll->quote_smart($_POST['expansion']) : 1;
  $banned = (isset($_POST['banned'])) ? $sqll->quote_smart($_POST['banned']) : 0;
  $locked = (isset($_POST['locked'])) ? $sqll->quote_smart($_POST['locked']) : 0;
  $referredby = $sqll->quote_smart(trim($_POST['referredby']));

  //make sure username/pass at least 4 chars long and less than max
  if ((strlen($login) < 4) || (strlen($login) > 15))
    redirect("user.php?action=edit_user&acct='.$acct.'&error=8");

  // if we received a Screen Name, make sure it does not conflict with other Screen Names or with
  // ArcEmu login names.
  if ($screenname <> $_POST['oldscreenname'])
  {
    $query = "SELECT * FROM config_accounts WHERE ScreenName = '".$screenname."'";
    $sn_result = $sqlm->query($query);
    if ($sqlm->num_rows($sn_result) <> 0)
      redirect('user.php?action=edit_user&acct='.$acct.'&error=7&');
    $query = "SELECT * FROM accounts WHERE login = '".$screenname."'";
    $sn_result = $sqll->query($query);
    if ($sqll->num_rows($sn_result) <> 0)
      redirect('user.php?action=edit_user&acct='.$acct.'&error=7');

    //make sure screen name is at least 4 chars long and less than max
    if ($screenname)
      if ((strlen($screenname) < 4) || (strlen($screenname) > 15))
        redirect("user.php?action=edit_user&acct='.$acct.'&error=8");
  }

  if (($gmlevel <= $user_lvl) || ($user_lvl <> gmlevel('4')))
    redirect("user.php?action=edit_user&acct={$_POST['acct']}&error=16");
  require_once("libs/valid_lib.php");
  if (!valid_alphabetic($login))
    redirect("user.php?action=edit_user&error=9&acct=".$acct);
  //restricting access to lower gmlvl
  $result = $sqll->query("SELECT gm,login FROM accounts WHERE acct = '$acct'");
  if (($user_lvl <= $sqll->result($result, 0, 'gm')) && ($user_name != $sqll->result($result, 0, 'login')))
    redirect("user.php?error=14");
  if (!$banned)
    $sqll->query("UPDATE accounts SET banned=0 WHERE acct='$acct'");
  else
  {
    $result = $sqll->query("SELECT count(*) FROM accounts WHERE banned <> 0 AND acct = '$acct'");
    if(!$sqll->result($result, 0))
      $sqll->query("INSERT INTO account_banned (id, bandate, unbandate, bannedby, banreason, active)
                 VALUES ($acct, ".time().",".(time()+(365*24*3600)).",'$user_name','$banreason', 1)");
  }

  if (($screenname <> $_POST['oldscreenname']) || ($login <> $_POST['oldlogin']))
  {
    if ($login == $_POST['oldlogin'])
      $temp_login = $_POST['oldlogin'];
    else
      $temp_login = $login;
    $query = "SELECT * FROM config_accounts WHERE Login = '".$_POST['oldlogin']."'";
    $sn_result = $sqlm->query($query);
    if ($sqlm->num_rows($sn_result))
      $s_result = $sqlm->query("UPDATE config_accounts SET Login='".$temp_login."', ScreenName = '".$screenname."' WHERE Login = '".$_POST['oldlogin']."'");
    else
      $s_result = $sqlm->query("INSERT INTO config_accounts (Login, ScreenName) VALUES ('".$login."', '".$screenname."')");
  }
  else
      $s_result = true;

  if ($password == "******")
    $a_result = $sqll->query("UPDATE accounts SET login='$login', email='$mail', muted='$locked', gm='$gmlevel', flags='$expansion' WHERE acct=$acct");
  else
    $a_result = $sqll->query("UPDATE accounts SET login='$login', email='$mail', password='$password', muted='$locked', gm='$gmlevel', flags='$expansion' WHERE acct=$acct");

  $result = $s_result && $a_result;

  if (doupdate_referral($referredby, $acct) || $result)
    redirect("user.php?action=edit_user&error=13&acct={$acct}");
  else
    redirect("user.php?action=edit_user&error=12&acct={$acct}");
}

function doupdate_referral($referredby, $user_id)
{
  global $logon_db, $arcm_db, $characters_db, $realm_id, $sqlm, $sqlc, $sqll;

  $result = $sqlm->fetch_row($sqlm->query("SELECT InvitedBy FROM point_system_invites WHERE PlayersAccount = '".$user_id."'"));
  $result = $result[0];

  if ($result == NULL)
  {
    $referred_by = $sqlc->fetch_row($sqlc->query("SELECT guid FROM characters WHERE name = '$referredby'"));
    $referred_by = $referred_by[0];

    if ($referred_by != NULL)
    {
      $char = $sqlc->fetch_row($sqlc->query("SELECT acct FROM characters WHERE guid = '$referred_by'"));
      $result = $sqll->fetch_row($sqll->query("SELECT acct FROM accounts WHERE acct = '$char'"));
      $result = $result[0];
      if ($result != $user_id)
      {
        $sqlm->query("INSERT INTO point_system_invites (PlayersAccount, InvitedBy, InviterAccount) VALUES ('$user_id', '$referred_by', '$result')");
        return true;
      }
      else
        return false;
    }
  }
}


//########################################################################################################################
// MAIN
//########################################################################################################################
$err = (isset($_GET['error'])) ? $_GET['error'] : NULL;

$output .= "
      <div class=\"bubble\" id='user_bubble'>
        <div class=\"top\">";

// load language
//$lang_user = lang_user();

// defines the title header in error cases
switch ($err)
{
  case 1:
    $output .= "
          <h1><font class=\"error\">".lang('global', 'empty_fields')."</font></h1>";
    break;
  case 2:
    $output .= "
          <h1><font class=\"error\">".lang('global', 'err_no_search_passed')."</font></h1>";
    break;
  case 3:
    $output .= "
          <h1><font class=\"error\">".lang('user', 'search_results')."</font></h1>";
    break;
  case 4:
    $output .= "
          <h1><font class=\"error\">".lang('user', 'acc_creation_failed')."</font></h1>";
    break;
  case 5:
    $output .= "
          <h1>".lang('user', 'acc_created')."</h1>";
    break;
  case 6:
    $output .= "
          <h1><font class=\"error\">".lang('user', 'nonidentical_passes')."</font></h1>";
    break;
  case 7:
    $output .= "
          <h1><font class=\"error\">".lang('user', 'user_already_exist')."</font></h1>";
    break;
  case 8:
    $output .= "
          <h1><font class=\"error\">".lang('user', 'username_pass_too_long')."</font></h1>";
    break;
  case 9:
    $output .= "
          <h1><font class=\"error\">".lang('user', 'use_only_eng_charset')."</font></h1>";
    break;
  case 10:
    $output .= "
          <h1><font class=\"error\">".lang('user', 'no_value_passed')."</font></h1>";
    break;
  case 11:
    $output .= "
          <h1>".lang('user', 'edit_acc')."</h1>";
    break;
  case 12:
    $output .= "
          <h1><font class=\"error\">".lang('user', 'update_failed')."</font></h1>";
    break;
  case 13:
    $output .= "
          <h1>".lang('user', 'data_updated')."</h1>";
    break;
  case 14:
    $output .= "
          <h1><font class=\"error\">".lang('user', 'you_have_no_permission')."</font></h1>";
    break;
  case 15:
    $output .= "
          <h1><font class=\"error\">".lang('user', 'acc_backedup')."</font></h1>";
    break;
  case 16:
    $output .= "
          <h1><font class=\"error\">".lang('user', 'you_have_no_permission_to_set_gmlvl')."</font></h1>";
    break;
  default: //no error
    $output .= "
          <h1>".lang('user', 'browse_acc')."</h1>";
}
unset($err);

$output .= "
        </div>";

$action = (isset($_GET['action'])) ? $_GET['action'] : NULL;

switch ($action)
{
  case "add_new":
    add_new();
    break;
  case "doadd_new":
    doadd_new();
    break;
  case "edit_user":
    edit_user();
    break;
  case "doedit_user":
    doedit_user();
    break;
  case "del_user":
    del_user();
    break;
  case "dodel_user":
    dodel_user();
    break;
  case "backup_user":
    backup_user();
    break;
  default:
    browse_users();
}

unset($action);
unset($action_permission);
//unset($lang_user);

require_once("footer.php");

?>