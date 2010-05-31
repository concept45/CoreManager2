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

require_once 'header.php';
require_once 'libs/char_lib.php';
require_once("libs/map_zone_lib.php");
valid_login($action_permission['view']);

//########################################################################################################################
//  BROWSE CHARS
//########################################################################################################################
function browse_chars()
{
  global $output, $logon_db, $arcm_db, $arcm_db, $characters_db, $realm_id,
    $action_permission, $user_lvl, $user_name, $showcountryflag, $itemperpage, $timezone, $sqlm, $sqlm, $sqld,
    $sqll, $sqlc, $core;

  //==========================$_GET and SECURE========================
  $start = (isset($_GET['start'])) ? $sqll->quote_smart($_GET['start']) : 0;
  if (is_numeric($start)); else $start=0;

  $order_by = (isset($_GET['order_by'])) ? $sqll->quote_smart($_GET['order_by']) : 'guid';
  if (preg_match('/^[_[:lower:]]{1,12}$/', $order_by)); else $order_by = 'guid';

  $dir = (isset($_GET['dir'])) ? $sqll->quote_smart($_GET['dir']) : 1;
  if (preg_match('/^[01]{1}$/', $dir)); else $dir=1;

  $order_dir = ($dir) ? 'ASC' : 'DESC';
  $dir = ($dir) ? 0 : 1;
  //==========================$_GET and SECURE end========================

  if ($order_by == 'mapid')
    $order_by = 'mapid '.$order_dir.', zoneid';
  elseif ($order_by == 'zoneid')
    $order_by = 'zoneid '.$order_dir.', mapid';

  $search_by = '';
  $search_value = '';
  if(isset($_GET['search_value']) && isset($_GET['search_by']))
  {
    $search_value = $sqll->quote_smart($_GET['search_value']);
    $search_by = (isset($_GET['search_by'])) ? $sqll->quote_smart($_GET['search_by']) : 'name';
    $search_menu = array('name', 'guid', 'account', 'level', 'greater_level', 'guild', 'race', 'class', 'mapid', 'highest_rank', 'greater_rank', 'online', 'gold', 'item');
    if (in_array($search_by, $search_menu));
    else $search_by = 'name';
    unset($search_menu);

    switch ($search_by)
    {
      //need to get the acc id from other table since input comes as name
      case "account":
        if (preg_match('/^[\t\v\b\f\a\n\r\\\"\'\? <>[](){}_=+-|!@#$%^&*~`.,0123456789\0]{1,30}$/', $search_value)) redirect("charlist.php?error=2");
        $result = $sqll->query("SELECT acct FROM accounts WHERE login LIKE '%$search_value%' LIMIT $start, $itemperpage");

        $where_out = " acct IN (0 ";
        while ($char = $sqll->fetch_row($result))
        {
          $where_out .= " ,";
          $where_out .= $char[0];
        };
        $where_out .= ") ";
        unset($result);

        $sql_query = "SELECT guid, name, acct, race, class, zoneid, mapid, online, level, gender, timestamp,
        CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank
        FROM `characters`
        WHERE $where_out ORDER BY $order_by $order_dir LIMIT $start, $itemperpage";
      break;

      case "level":
        if (is_numeric($search_value)); else $search_value = 1;
        $where_out ="level = $search_value";

        $sql_query = "SELECT guid,name,acct,race,class,zoneid,mapid,
        CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank,
        online, level, gender, timestamp FROM `characters`
        WHERE $where_out ORDER BY $order_by $order_dir LIMIT $start, $itemperpage";
      break;

      case "greater_level":
        if (is_numeric($search_value)); else $search_value = 1;
        $where_out ="level > $search_value";

        $sql_query = "SELECT guid,name,acct,race,class,zoneid,mapid,
        CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank,
        online, level, gender, timestamp FROM `characters`
        WHERE $where_out ORDER BY 'level' $order_dir LIMIT $start, $itemperpage";
      break;

      case "gold":
        if (is_numeric($search_value)); else $search_value = 1;
        $where_out ="gold > $search_value";

        $sql_query = "SELECT guid,name,acct,race,class,zoneid,mapid,
        CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank,
        online, level, gender, timestamp FROM `characters`
        WHERE $where_out ORDER BY $order_by $order_dir LIMIT $start, $itemperpage";
      break;

      case "guild":
        if (preg_match('/^[\t\v\b\f\a\n\r\\\"\'\? <>[](){}_=+-|!@#$%^&*~`.,0123456789\0]{1,30}$/', $search_value)) redirect("charlist.php?error=2");
        $result = $sqlc->query("SELECT guildid FROM guilds WHERE guildname LIKE '%$search_value%'");
        $guildid = $sqlc->result($result, 0, 'guildid');

        if (!$search_value)
          $guildid = 0;
        $Q1 = "SELECT playerid FROM guild_data WHERE guildid = ";
        $Q1 .= $guildid;

        $result = $sqlc->query($Q1);
        unset($guildid);
        unset($Q1);
        $where_out = "guid IN (0 ";
        while ($char = $sqlc->fetch_row($result))
        {
          $where_out .= " ,";
          $where_out .= $char[0];
        };
        $where_out .= ") ";
        unset($result);

        if ( $core == 1 )
          $sql_query = "SELECT guid, name, acct, race, class, zoneid, mapid,
            CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank,
            online, level, gender, timestamp FROM `characters`
            WHERE $where_out ORDER BY $order_by $order_dir LIMIT $start, $itemperpage";
        else
          $sql_query = "SELECT guid, name, account AS acct, race, class, zone AS zoneid, map AS mapid,
            CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank,
            online, level, gender, timestamp FROM `characters`
            WHERE $where_out ORDER BY $order_by $order_dir LIMIT $start, $itemperpage";
      break;

      case "item":
        if (is_numeric($search_value)); else $search_value = 0;
        $result = $sqlc->query("SELECT ownerguid FROM playeritems WHERE entry = '$search_value'");

        $where_out = "guid IN (0 ";
        while ($char = $sqlc->fetch_row($result))
        {
          $where_out .= " ,";
          $where_out .= $char[0];
        };
        $where_out .= ") ";
        unset($result);

        $sql_query = "SELECT guid,name,acct,race,class,zoneid,mapid,
        CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank,
        online, level, gender, timestamp FROM `characters`
        WHERE $where_out ORDER BY $order_by $order_dir LIMIT $start, $itemperpage";
      break;

      case "greater_rank":
        if (is_numeric($search_value)); else $search_value = 0;
        $where_out ="SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) > $search_value";

        $sql_query = "SELECT guid,name,acct,race,class,zoneid,mapid,
        CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank,
        online, level, gender, timestamp FROM `characters`
        WHERE $where_out ORDER BY 'highest_rank' $order_dir LIMIT $start, $itemperpage";
      break;

      case "highest_rank":
        if (is_numeric($search_value)); else $search_value = 0;
        $where_out ="SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) = $search_value";

        $sql_query = "SELECT guid,name,acct,race,class,zoneid,mapid,
        CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank,
        online, level, gender, timestamp FROM `characters`
        WHERE $where_out ORDER BY $order_by $order_dir LIMIT $start, $itemperpage";
      break;

      default:
        if (preg_match('/^[\t\v\b\f\a\n\r\\\"\'\? <>[](){}_=+-|!@#$%^&*~`.,0123456789\0]{1,30}$/', $search_value)) redirect("charlist.php?error=2");
        $where_out ="$search_by LIKE '%$search_value%'";

        if ( $core == 1 )
          $sql_query = "SELECT guid, name, acct, race, class, zoneid, mapid,
            CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank,
            online, level, gender, timestamp FROM `characters`
            WHERE $where_out ORDER BY $order_by $order_dir LIMIT $start, $itemperpage";
        else
          $sql_query = "SELECT guid, name, acct, race, class, zoneid, mapid,
            CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank,
            online, level, gender, timestamp FROM `characters`
            WHERE $where_out ORDER BY $order_by $order_dir LIMIT $start, $itemperpage";
    }

    $query_1 = $sqlc->query("SELECT count(*) FROM `characters` where $where_out");
    $query = $sqlc->query($sql_query);
  }
  else
  {
    $query_1 = $sqlc->query("SELECT count(*) FROM `characters`");
    if ( $core == 1 )
      $query = $sqlc->query("SELECT guid, name, acct, race, class, zoneid, mapid,
        online,level, gender, timestamp,
        CAST( SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ';', ".(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS+1)."), ';', -1) AS UNSIGNED) AS highest_rank
        FROM `characters` ORDER BY $order_by $order_dir LIMIT $start, $itemperpage");
    else
      $query = $sqlc->query("SELECT guid, name, account AS acct, race, class, zone AS zoneid, map AS mapid,
        online, level, gender, logout_time AS timestamp,
        totalHonorPoints AS highest_rank
        FROM `characters` ORDER BY $order_by $order_dir LIMIT $start, $itemperpage");
  }

  $all_record = $sqlc->result($query_1,0);
  unset($query_1);

  $this_page = $sqlc->num_rows($query) or die(error(lang('global', 'err_no_result')));

  //==========================top tage navigaion starts here========================
  $output .= '
        <script type="text/javascript" src="libs/js/check.js"></script>
        <center>
          <table class="top_hidden">
            <tr>
              <td>';
  // cleanup unknown working condition
  //if($user_lvl >= $action_permission['delete'])
  //              makebutton($lang_char_list['cleanup'], 'cleanup.php', 130);
                makebutton(lang('global', 'back'), 'javascript:window.history.back()', 130);
  ($search_by && $search_value) ? makebutton(lang('char_list', 'characters'), 'char_list.php" type="def', 130) : $output .= '';
  $output .= '
              </td>
              <td align="right" width="25%" rowspan="2">';
  $output .= generate_pagination('char_list.php?order_by='.$order_by.'&amp;dir='.(($dir) ? 0 : 1).( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ), $all_record, $itemperpage, $start);
  $output .= "
              </td>
            </tr>
            <tr align=\"left\">
              <td>
                <table class=\"hidden\">
                  <tr>
                    <td>
                      <form action=\"char_list.php\" method=\"get\" name=\"form\">
                        <input type=\"hidden\" name=\"error\" value=\"3\" />
                        <input type=\"text\" size=\"24\" maxlength=\"50\" name=\"search_value\" value=\"{$search_value}\" />
                        <select name=\"search_by\">
                          <option value=\"name\"".($search_by == 'name' ? " selected=\"selected\"" : "").">".lang('char_list', 'by_name')."</option>
                          <option value=\"guid\"".($search_by == 'guid' ? " selected=\"selected\"" : "").">".lang('char_list', 'by_id')."</option>
                          <option value=\"account\"".($search_by == 'account' ? " selected=\"selected\"" : "").">".lang('char_list', 'by_account')."</option>
                          <option value=\"level\"".($search_by == 'level' ? " selected=\"selected\"" : "").">".lang('char_list', 'by_level')."</option>
                          <option value=\"greater_level\"".($search_by == 'greater_level' ? " selected=\"selected\"" : "").">".lang('char_list', 'greater_level')."</option>
                          <option value=\"guild\"".($search_by == 'guild' ? " selected=\"selected\"" : "").">".lang('char_list', 'by_guild')."</option>
                          <option value=\"race\"".($search_by == 'race' ? " selected=\"selected\"" : "").">".lang('char_list', 'by_race_id')."</option>
                          <option value=\"class\"".($search_by == 'class' ? " selected=\"selected\"" : "").">".lang('char_list', 'by_class_id')."</option>
                          <option value=\"mapid\"".($search_by == 'mapid' ? " selected=\"selected\"" : "").">".lang('char_list', 'by_map_id')."</option>
                          <option value=\"highest_rank\"".($search_by == 'highest_rank' ? " selected=\"selected\"" : "").">".lang('char_list', 'by_honor_kills')."</option>
                          <option value=\"greater_rank\"".($search_by == 'greater_rank' ? " selected=\"selected\"" : "").">".lang('char_list', 'greater_honor_kills')."</option>
                          <option value=\"online\"".($search_by == 'online' ? " selected=\"selected\"" : "").">".lang('char_list', 'by_online')."</option>
                          <option value=\"gold\"".($search_by == 'gold' ? " selected=\"selected\"" : "").">".lang('char_list', 'chars_gold')."</option>
                          <option value=\"item\"".($search_by == 'item' ? " selected=\"selected\"" : "").">".lang('char_list', 'by_item')."</option>
                        </select>
                      </form>
                    </td>
                    <td>";
                      makebutton(lang('global', 'search'), 'javascript:do_submit()', 80);
  $output .= '
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>';
  //==========================top tage navigaion ENDS here ========================
  $output .= "
          <form method=\"get\" action=\"char_list.php\" name=\"form1\">
            <input type=\"hidden\" name=\"action\" value=\"del_char_form\" />
            <input type=\"hidden\" name=\"start\" value=\"$start\" />
            <table class=\"lined\">
              <tr>
                <th width=\"1%\"><input name=\"allbox\" type=\"checkbox\" value=\"Check All\" onclick=\"CheckAll(document.form1);\" /></th>
                <th width=\"1%\"><a href=\"char_list.php?order_by=guid&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='guid' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'id')."</a></th>
                <th width=\"1%\"><a href=\"char_list.php?order_by=name&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='name' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'char_name')."</a></th>
                <th width=\"1%\"><a href=\"char_list.php?order_by=acct&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='account' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'account')."</a></th>
                <th width=\"1%\"><a href=\"char_list.php?order_by=race&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='race' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'race')."</a></th>
                <th width=\"1%\"><a href=\"char_list.php?order_by=class&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='class' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'class')."</a></th>
                <th width=\"1%\"><a href=\"char_list.php?order_by=level&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='level' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'level')."</a></th>
                <th width=\"10%\"><a href=\"char_list.php?order_by=mapid&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='map '.$order_dir.', zoneid' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'map')."</a></th>
                <th width=\"10%\"><a href=\"char_list.php?order_by=zoneid&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='zone '.$order_dir.', mapid' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'zone')."</a></th>
                <th width=\"1%\"><a href=\"char_list.php?order_by=highest_rank&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='highest_rank' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'honor_kills')."</a></th>
                <th width=\"10%\"><a href=\"char_list.php?order_by=gname&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='gname' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'guild')."</a></th>
                <th width=\"1%\"><a href=\"char_list.php?order_by=timestamp&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='logout_time' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'lastseen')."</a></th>
                <th width=\"1%\"><a href=\"char_list.php?order_by=online&amp;start=$start".( $search_value && $search_by ? "&amp;search_by=$search_by&amp;search_value=$search_value" : "" )."&amp;dir=$dir\">".($order_by=='online' ? "<img src=\"img/arr_".($dir ? "up" : "dw").".gif\" alt=\"\" /> " : "")."".lang('char_list', 'online')."</a></th>";

  if ($showcountryflag)
  {
    require_once 'libs/misc_lib.php';
    $output .= '
                <th width="1%">'.lang('global', 'country').'</th>';
  }

  $output .='
              </tr>';

  $looping = ($this_page < $itemperpage) ? $this_page : $itemperpage;

  for ($i=1; $i<=$looping; $i++)
  {
    // switched to fetch_assoc because using record indexes is for morons
    $char = $sqlc->fetch_assoc($query, 0) or die(error(lang('global', 'err_no_user')));
    // to disalow lower lvl gm to  view accounts of other gms
    if ( $core == 1 )
      $result = $sqll->query("SELECT gm, login FROM accounts WHERE acct ='".$char['acct']."'");
    else
      $result = $sqll->query("SELECT gmlevel AS gm, username AS login FROM account LEFT JOIN account_access ON account.id = account_access.id WHERE account.id ='".$char['acct']."'");
    $owner_gmlvl = gmlevel($sqll->result($result, 0, 'gm'));
    $owner_acc_name = $sqll->result($result, 0, 'login');
      
    $time_offset = $timezone * 3600;
      
    if ( $char['timestamp'] <> 0 )
      $lastseen = date("F j, Y @ Hi", $char['timestamp'] + $time_offset);
    else
      $lastseen = '-';

    if ( $core == 1 )
    {
      $guild_id = $sqlc->result($sqlc->query("SELECT guildid FROM guild_data WHERE playerid='".$char['guid']."'"), 0);
      $guild_name = $sqlc->result($sqlc->query("SELECT guildName FROM guilds WHERE guildid='".$guild_id."'"));
    }
    else
    {
      $guild_name = $sqlc->result($sqlc->query("SELECT name FROM guild WHERE guildid = '".$char['guid']."'"));
    }

    // we need the screen name here
    // but first, we need the user name
    if ( $core == 1 )
      $un_query = "SELECT * FROM accounts WHERE acct = '".$char['acct']."'";
    else
      $un_query = "SELECT * FROM account WHERE id = '".$char['acct']."'";
    $un_results = $sqll->query($un_query);
    $un = $sqll->fetch_assoc($un_results);
    $sn_query = "SELECT * FROM config_accounts WHERE Login = '".$un['login']."'";
    $sn_result = $sqlm->query($sn_query);
    $sn = $sqlm->fetch_assoc($sn_result);    

    if ( ($user_lvl >= $owner_gmlvl) || ($owner_acc_name == $user_name) || ($user_lvl == gmlevel('4')) )
    {
      $output .= '
              <tr>
                <td>';
      if (($user_lvl >= $action_permission['delete'])||($owner_acc_name == $user_name))
        $output .= '
                  <input type="checkbox" name="check[]" value="'.$char['guid'].'" onclick="CheckCheckAll(document.form1);" />';
      $output .= "
                </td>
                <td>".$char['guid']."</td>
                <td><a href='char.php?id=".$char['guid']."'>".htmlentities($char['name'])."</a></td>";
      if ($sn['ScreenName'])
        $output .= "
                <td><a href='user.php?action=edit_user&amp;error=11&amp;acct=".$char['acct']."'>".htmlentities($sn['ScreenName'])."</a></td>";
      else
        $output .= "
                <td><a href='user.php?action=edit_user&amp;error=11&amp;acct=".$char['acct']."'>".htmlentities($owner_acc_name)."</a></td>";
      $output .= "
                <td><img src='img/c_icons/".$char['race']."-".$char['gender'].".gif' onmousemove='toolTip(\"".char_get_race_name($char['race'])."\",\"item_tooltip\")' onmouseout='toolTip()' alt=\"\" /></td>
                <td><img src='img/c_icons/{$char['class']}.gif' onmousemove='toolTip(\"".char_get_class_name($char['class'])."\",\"item_tooltip\")' onmouseout='toolTip()' alt=\"\" /></td>
                <td>".char_get_level_color($char['level'])."</td>
                <td class=\"small\"><span onmousemove='toolTip(\"MapID:".$char['mapid']."\",\"item_tooltip\")' onmouseout='toolTip()'>".get_map_name($char['mapid'], $sqld)."</span></td>
                <td class=\"small\"><span onmousemove='toolTip(\"ZoneID:".$char['zoneid']."\",\"item_tooltip\")' onmouseout='toolTip()'>".get_zone_name($char['zoneid'], $sqld)."</span></td>
                <td>".$char['highest_rank']."</td>
                <td class=\"small\"><a href='guild.php?action=view_guild&amp;error=3&amp;id=".$guild_id."'>".htmlentities($guild_name)."</a></td>
                <td class=\"small\">$lastseen</td>
                <td>".(($char['online']) ? "<img src=\"img/up.gif\" alt=\"\" />" : "<img src=\"img/down.gif\" alt=\"\" />")."</td>";
      if ($showcountryflag)
      {
        $country = misc_get_country_by_account($char['acct']);
        $output .= "
                <td>".(($country['code']) ? "<img src='img/flags/".$country['code'].".png' onmousemove='toolTip(\"".($country['country'])."\",\"item_tooltip\")' onmouseout='toolTip()' alt=\"\" />" : "-")."</td>";
      }
      $output .= '
              </tr>';
    }
    else
    {
      $output .= '
              <tr>
                <td>*</td><td>***</td><td>***</td><td>You</td><td>Have</td><td>No</td><td class=\"small\">Permission</td><td>to</td><td>View</td><td>this</td><td>Data</td><td>***</td><td>*</td>';
      if ($showcountryflag)
        $output .= '<td>*</td>';
      $output .= '
              </tr>';
    }
  }
  unset($char);
  unset($result);

  $output .= '
              <tr>
                <td colspan="13" align="right" class="hidden" width="25%">';
  $output .= generate_pagination('char_list.php?order_by='.$order_by.'&amp;dir='.(($dir) ? 0 : 1).( $search_value && $search_by ? '&amp;search_by='.$search_by.'&amp;search_value='.$search_value.'' : '' ), $all_record, $itemperpage, $start);
  $output .= '
                </td>
              </tr>
              <tr>
                <td colspan="6" align="left" class="hidden">';
  if (($user_lvl >= $action_permission['delete']) || ($owner_acc_name == $user_name))
                  makebutton(lang('char_list', 'del_selected_chars'), 'javascript:do_submit(\'form1\',0)" type="wrn', 220);
  $output .= '
                </td>
                <td colspan="7" align="right" class="hidden">'.lang('char_list', 'tot_chars').' : '.$all_record.'</td>
              </tr>
            </table>
          </form>
        </center>';

}


//########################################################################################################################
//  DELETE CHAR
//########################################################################################################################
function del_char_form()
{
  global $output, $characters_db, $realm_id, $action_permission, $sqlc;

  valid_login($action_permission['delete']);

  if(isset($_GET['check'])) $check = $_GET['check'];
    else redirect('char_list.php?error=1');

  $output .= '
          <center>
            <img src="img/warn_red.gif" width="48" height="48" alt="" />
              <h1>
                <font class="error">'.lang('global', 'are_you_sure').'</font>
              </h1>
              <br />
              <font class="bold">'.lang('char_list', 'char_ids').': ';

  $pass_array = '';
  $n_check = count($check);
  for ($i=0; $i<$n_check; ++$i)
  {
    $name = $sqlc->result($sqlc->query('SELECT name FROM characters WHERE guid = '.$check[$i].''), 0);
    $output .= '
                <a href="char.php?id='.$check[$i].'" target="_blank">'.$name.', </a>';
    $pass_array .= '&amp;check%5B%5D='.$check[$i].'';
  }
  unset($name);
  unset($n_check);
  unset($check);

  $output .= '
                <br />'.lang('global', 'will_be_erased').'
              </font>
              <br /><br />
              <table width="300" class="hidden">
                <tr>
                  <td>';
                    makebutton(lang('global', 'yes'), 'char_list.php?action=dodel_char'.$pass_array.'" type="wrn', 130);
                    makebutton(lang('global', 'no'), 'char_list.php" type="def', 130);
  unset($pass_array);
  $output .= '
                  </td>
                </tr>
              </table>
            </center>';
}


//########################################################################################################################
//  DO DELETE CHARS
//########################################################################################################################
function dodel_char()
{
  global $output, $characters_db, $realm_id, $action_permission, $tab_del_user_characters, $sqlc;

  valid_login($action_permission['delete']);

  if(isset($_GET['check'])) $check = $sqlc->quote_smart($_GET['check']);
    else redirect('char_list.php?error=1');

  $deleted_chars = 0;
  require_once 'libs/del_lib.php';

  $n_check = count($check);
  for ($i=0; $i<$n_check; ++$i)
  {
    if ($check[$i] == '');
    else
      if (del_char($check[$i], $realm_id))
        $deleted_chars++;
  }
  unset($n_check);
  unset($check);

  $output .= '
          <center>';
  if ($deleted_chars)
    $output .= '
            <h1><font class="error">'.lang('char_list', 'total').' <font color=blue>'.$deleted_chars.'</font> '.lang('char_list', 'chars_deleted').'</font></h1>';
  else
    $output .= '
            <h1><font class="error">'.lang('char_list', 'no_chars_del').'</font></h1>';
  unset($deleted_chars);
  $output .= '
            <br /><br />';
  $output .= '
            <table class="hidden">
              <tr>
                <td>';
                  makebutton(lang('char_list', 'back_browse_chars'), 'char_list.php', 220);
  $output .= '
                </td>
              </tr>
            </table>
            <br />
          </center>';
}


//########################################################################################################################
// MAIN
//########################################################################################################################

$err = (isset($_GET['error'])) ? $_GET['error'] : NULL;

$output .= '
      <div class="bubble">
          <div class="top">';

//$lang_char_list = lang_char_list();

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
    $output .="
          <h1><font class=\"error\">".lang('char_list', 'search_results').":</font></h1>";
    break;
  default:
    $output .= "
          <h1>".lang('char_list', 'browse_chars')."</h1>";
}

unset($err);

$output .= '
          </div>';

$action = (isset($_GET['action'])) ? $_GET['action'] : NULL;

switch ($action)
{
  case "del_char_form":
    del_char_form();
    break;
  case "dodel_char":
    dodel_char();
    break;
  default:
    browse_chars();
}

unset($action);
unset($action_permission);
//unset($lang_char_list);

require_once("footer.php");

?>