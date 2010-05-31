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


$time_start = microtime(true);
// resuming login session if available, or start new one
if (ini_get('session.auto_start'));
else session_start();

//---------------------Load Default and User Configuration---------------------
if (file_exists('scripts/config.php'))
{
  if (!file_exists('scripts/config.dist.php'))
    exit('<center><br><code>\'scripts/config.dist.php\'</code> not found,<br>
          please restore <code>\'scripts/config.dist.php\'</code></center>');
  require_once 'scripts/config.php';
}
else
  exit('<center><br><code>\'scripts/config.php\'</code> not found,<br>
        please copy <code>\'scripts/config.dist.php\'</code> to
        <code>\'scripts/config.php\'</code> and make appropriate changes.');
        
//----------------- Make sure a valid core has been selected ------------------

if ( ( $core < 1 ) || ( $core > 3 ) )
  die("Invalid Core selected.");

//---------------------Error reports for Debugging-----------------------------
if ($debug) $tot_queries = 0;
if (1 < $debug)
  error_reporting (E_ALL);
else
  error_reporting (E_COMPILE_ERROR);

//---------------------Loading User Theme and Language Settings----------------
if (isset($_COOKIE['theme']))
{
  if (is_dir('themes/'.$_COOKIE['theme']))
    if (is_file('themes/'.$_COOKIE['theme'].'/'.$_COOKIE['theme'].'_1024.css'))
      $theme = $_COOKIE['theme'];
}

if (isset($_COOKIE['lang']))
{
  $lang = $_COOKIE['lang'];
  if (file_exists('lang/'.$lang.'.php'));
  else
    $lang = $language;
}
else
  $lang = $language;

//---------------------Loading Libraries---------------------------------------
// database library
require_once 'libs/db_lib.php';
require_once 'lang/'.$lang.'.php';

require_once 'libs/data_lib.php';
require_once 'libs/global_lib.php';
require_once 'libs/lang_lib.php';
require_once 'libs/get_lib.php';

//---------------------Headers' header-----------------------------------------
// sets encoding defined in config for language support
header('Content-Type: text/html; charset='.$site_encoding);
header('Expires: Tue, 01 Jan 2000 00:00:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
$output .= '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title>'.$title.'</title>
    <meta http-equiv="Content-Type" content="text/html; charset='.$site_encoding.'" />
    <meta http-equiv="Content-Type" content="text/javascript; charset='.$site_encoding.'" />
    <link rel="stylesheet" type="text/css" href="themes/'.$theme.'/'.$theme.'_1024.css" title="1024" />
    <link rel="stylesheet" type="text/css" href="themes/'.$theme.'/'.$theme.'_1280.css" title="1280" />
    <link rel="SHORTCUT ICON" href="img/favicon.ico" />
    <script type="text/javascript" charset="utf-8"></script>
    <script type="text/javascript" src="libs/js/general.js"></script>
    <script type="text/javascript" src="libs/js/layout.js"></script>
  </head>';

  // make wowhead tooltops generally available
  wowhead_tt();

$output .= '
  <body onload="dynamicLayout();">
    <center>
      <table class="table_top">
        <tr>
          <td class="table_top_left" valign="top">';
// this_is_junk: how did this site ever really work?  we can't clear a global!  idiots. <_<
//unset($title);

// check for host php script execution time limit,
//  warn user if it is not high enough for CoreManager to run
if (ini_get('max_execution_time') < 1800)
{
  if (ini_set('max_execution_time',0));
  else
    error('Error - max_execution_time not set.<br /> Please set it manually to 0, in php.ini for full functionality.');
}

//---------------------Guest login Predefines----------------------------------
if ($allow_anony && empty($_SESSION['logged_in']))
{
  $_SESSION['user_lvl'] = -1;
  $_SESSION['gm_lvl'] = '-1';
  $_SESSION['login'] = $anony_uname;
  $_SESSION['user_id'] = -1;
  $_SESSION['realm_id'] = $anony_realm_id;
  $_SESSION['client_ip'] = ( isset($_SERVER['REMOTE_ADDR']) ) ? $_SERVER['REMOTE_ADDR'] : getenv('REMOTE_ADDR');
}

$realm_id = ( isset($_GET['realm_id']) ) ? (int)$_GET['realm_id'] : $_SESSION['realm_id'];

$sqll = new SQL;
$sqll->connect($logon_db['addr'], $logon_db['user'], $logon_db['pass'], $logon_db['name']);

$sqld = new SQL;
$sqld->connect($dbc_db['addr'], $dbc_db['user'], $dbc_db['pass'], $dbc_db['name']);

$sqlm = new SQL;
$sqlm->connect($arcm_db['addr'], $arcm_db['user'], $arcm_db['pass'], $arcm_db['name']);

$sqlc = new SQL;
$sqlc->connect($characters_db[$realm_id]['addr'], $characters_db[$realm_id]['user'], $characters_db[$realm_id]['pass'], $characters_db[$realm_id]['name']);

$sqlw = new SQL;
$sqlw->connect($world_db[$realm_id]['addr'], $world_db[$realm_id]['user'], $world_db[$realm_id]['pass'], $world_db[$realm_id]['name']);

//----Check if a user has login, if Guest mode is enabled, code above will login as Guest
if (isset($_SESSION['user_lvl']) && isset($_SESSION['login']) && isset($_SESSION['realm_id']) && empty($_GET['err']))
{
  // check for host php script max memory allowed,
  // setting it higher if it is not enough for CoreManager to run
  if (ini_get('memory_limit') < 16)
    @ini_set('memory_limit', '16M');

  // resuming logged in user settings
  session_regenerate_id();
  $user_lvl = $_SESSION['user_lvl'];
  $user_name = $_SESSION['login'];
  $user_id = $_SESSION['user_id'];
  // for CoreManager security system, getting the users' account group name
  // switched to use $_SESSION['gmlvl']
  $user_lvl_name = gmlevel_name($_SESSION['gm_lvl']);

  // get the file name that called this header
  $array = explode ( '/', $_SERVER['PHP_SELF']);
  $lookup_file = $array[sizeof($array)-1];
  unset($array);

  //---------------------Top Menu----------------------------------------------
  $output .= '
            <div id="menuwrapper">
              <ul id="menubar">';
  //$lang_header = lang_header();
  $action_permission = array();
  foreach ($menu_array as $trunk)
  {
    // ignore "invisible array" this is for setting security read/write values
    // for not accessible elements not in the navbar!
    if ('invisible' == $trunk[1])
    {
      foreach ($trunk[2] as $branch)
      {
        if($branch[0] === $lookup_file)
        {
          $action_permission['view']   = $branch[2];
          $action_permission['insert'] = $branch[3];
          $action_permission['update'] = $branch[4];
          $action_permission['delete'] = $branch[5];
        }
      }
    }
    else
    {
      $output .= '
                <li><a href="'.$trunk[0].'">'.(isset($lang_header[$trunk[1]]) ? $lang_header[$trunk[1]] : $trunk[1]).'</a>';
      if(isset($trunk[2][0]))
        $output .= '
                  <ul>';
      foreach ($trunk[2] as $branch)
      {
        if($branch[0] === $lookup_file)
        {
          $action_permission['view']   = $branch[2];
          $action_permission['insert'] = $branch[3];
          $action_permission['update'] = $branch[4];
          $action_permission['delete'] = $branch[5];
        }
        if ($user_lvl >= $branch[2])
          $output .= '
                    <li><a href="'.$branch[0].'">'.(isset($lang_header[$branch[1]]) ? $lang_header[$branch[1]] : $branch[1]).'</a></li>';
      }
      if(isset($trunk[2][0]))
        $output .= '
                  </ul>';
      $output .= '
                </li>';
    }
  }
  unset($branch);
  unset($trunk);
  unset($lookup_file);
  unset($menu_array);

  $output .= '
                <li><a href="edit.php">'.lang('header', 'my_acc').'</a>
                  <ul>';

  $result = $sqlm->query('SELECT id, name FROM `realmlist` LIMIT 10');

  // we check how many realms are configured, this does not check if config is valid
  if ( ( 1 < $sqlm->num_rows($result)) && ( 1 < count($server)) && ( 1 < count($characters_db)) )
  {
    $output .= '
                    <li><a href="#">'.lang('header', 'realms').'</a></li>';
    while ($realm = $sqlm->fetch_assoc($result))
    {
      if(isset($server[$realm['id']]))
      {
        $set = ($realm_id === $realm['id']) ? '>' : '';
        $output .= '
                    <li><a href="realm.php?action=set_def_realm&amp;id='.$realm['id'].'&amp;url='.$_SERVER['PHP_SELF'].'">'.htmlentities($set.' '.$realm['name']).'</a></li>';
      }
    }
    unset($set);
    unset($realm);
  }

  // we have a different menu for guest account
  if($allow_anony && empty($_SESSION['logged_in']))
  {
    //$lang_login = lang_login();
    $output .= '
                    <li><a href="#">'.lang('header', 'account').'</a></li>
                    <li><a href="register.php">'.lang('login', 'not_registrated').'</a></li>
                    <li><a href="login.php">'.lang('login', 'login').'</a></li>';
    //unset($lang_login);
  }
  else
  {
    if ( $core == 1 )
      $result = $sqlc->query('SELECT guid, name, race, class, level, gender FROM characters WHERE acct = '.$user_id.'');
    else
      $result = $sqlc->query('SELECT guid, name, race, class, level, gender FROM characters WHERE account = '.$user_id.'');

    // this puts links to user characters of active realm in "My Account" menu
    if($sqlc->num_rows($result))
    {
      $output .= '
                    <li><a href="#">'.lang('header', 'my_characters').'</a></li>';
      while ($char = $sqlc->fetch_assoc($result))
      {
        $output .= '
                    <li>
                      <a href="char.php?id='.$char['guid'].'">
                        <img src="img/c_icons/'.$char['race'].'-'.$char['gender'].'.gif" alt="" /><img src="img/c_icons/'.$char['class'].'.gif" alt="" />'.
                        $char['name'].'
                      </a>
                    </li>';
      }
      unset($char);
    }
    $output .= '
                    <li><a href="#">'.lang('header', 'account').'</a></li>
                    <li><a href="edit.php">'.lang('header', 'edit_my_acc').'</a></li>
                    <li><a href="logout.php">'.lang('header', 'logout').'</a></li>';
  }
  unset($result);
  $output .= '
                  </ul>
                </li>
              </ul>
              <br class="clearit" />
            </div>
          </td>
          <td class="table_top_middle">';
  $web_admin_query = "SELECT * FROM config_accounts WHERE Login='".$user_name."'";
  $web_admin_result = $sqlm->query($web_admin_query);
  $web_admin = $sqlm->fetch_assoc($web_admin_result);
  $web_admin = $web_admin['WebAdmin'];
  //if (!$_SESSION['screenname'])
  //{
    $output .= '
            <div id="username">'.( ( isset($_SESSION['screenname']) ) ? $_SESSION['screenname'] : $user_name ).' .:'.( $web_admin ? '<a href="admin.php">' : '' ).$user_lvl_name.'\'s '.lang('header', 'menu').( $web_admin ? '</a>' : '' ).':.</div>';
  //}
  //else
  //{
    //$output .= '
    //        <div id="username">'..' .:'.( $web_admin ? '<a href="admin.php">' : '' ).$user_lvl_name.'\'s '.lang('header', 'menu').( $web_admin ? '</a>' : '' ).':.</div>';
  //}
  $output .= '
          </td>
          <td class="table_top_right"></td>
        </tr>
      </table>';
  //unset($lang_header);
}
else
{
  $output .= '
          </td>
          <td class="table_top_middle"></td>
          <td class="table_top_right"></td>
        </tr>
      </table>';
}

//---------------------Start of Body-------------------------------------------
$output .= '
      <div id="body_main">
          <!-- end of header.php -->';

?>