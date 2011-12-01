<?php/*    CoreManager, PHP Front End for ArcEmu, MaNGOS, and TrinityCore    Copyright (C) 2010-2011  CoreManager Project    This program is free software: you can redistribute it and/or modify    it under the terms of the GNU General Public License as published by    the Free Software Foundation, either version 3 of the License, or    (at your option) any later version.    This program is distributed in the hope that it will be useful,    but WITHOUT ANY WARRANTY; without even the implied warranty of    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the    GNU General Public License for more details.    You should have received a copy of the GNU General Public License    along with this program.  If not, see <http://www.gnu.org/licenses/>.*///##########################################################################################//  SEND INGAME MAIL ARCEMU//function send_ingame_mail_A($realm_id, $massmails, $return = false){  global $server, $characters_db, $realm_id, $from_char, $stationary, $sql;  //$mess_str = '';  $mess = 0;  $result = '';  $receivers = array();  foreach ( $massmails as $mails )  {    if ( count($mails["att_item"]) < 1 )    {      $mails["att_item"] = array(0);      $mails["att_stack"] = array(0);    }    // build insert query    $query = "INSERT INTO mailbox_insert_queue (sender_guid, receiver_guid, subject, body, stationary, money,               item_id, item_stack";    $att_item = $mails["att_item"];    $att_stack = $mails["att_stack"];    if ( count($att_item) > 1 )    {      for ( $i = 1; $i < count($att_item); $i++ )      {        $query .= ", item_id".($i+1).", item_stack".($i+1);      }    }    $query .= "              )              VALUES ('".$from_char."', '".$mails["receiver"]."', '".$mails["subject"]."', '".$mails["body"]."', '".$stationary."', '".$mails["att_gold"]."',               '".$att_item[0]."', '".$att_stack[0]."'";    if ( count($att_item) > 1 )    {      for ( $i = 1; $i < count($att_item); $i++ )      {        $query .= ", '".$att_item[$i]."', '".$att_stack[$i]."'";      }    }    $query .= "              )";    $sql["char"]->query($query);    if ( $sql["char"]->affected_rows() )    {      //$mess_str .= "Successfully sent message sent to ". $mails["receiver_name"]."<br />";      $mess = 0; // success      $result = "RESULT";      array_push($receivers, $mails["receiver_name"]);    }    else    {      //$mess_str .= "Failed to send message to ".$mails["receiver_name"]."<br />";      $mess = -1; // failure      $result = "RESULT";    }  }  $receiver_list = '';  foreach ( $receivers as $receiver )  {    $receiver_list .= ', '.$receiver;  }  $reveiver_list = substr($receiver_list, 2, strlen($receiver_list)-2);  if ( !$return )  {    if ( !isset($_GET["redirect"]) )      //redirect("mail.php?action=result&error=6&mess=$mess_str");      redirect("mail.php?action=result&error=6&mess=".$mess."&recipient=".$receiver_list);    else    {      $money_result = $sql["char"]->quote_smart($_GET["moneyresult"]);      $redirect = $sql["char"]->quote_smart($_GET["redirect"]);      redirect($redirect."?moneyresult=".$money_result."&mailresult=1");    }  }  else    return $mess;}//##########################################################################################//  SEND INGAME MAIL BY TELNET//// Xiong Guoy// 2009-08-08function send_ingame_mail_MT($realm_id, $massmails, $return = false){  require_once "libs/telnet_lib.php";  global $server, $sql;  $telnet = new telnet_lib();  $result = $telnet->Connect($server[$realm_id]["addr"], $server[$realm_id]["telnet_port"], $server[$realm_id]["telnet_user"], $server[$realm_id]["telnet_pass"]);  if ( $result == 0 )  {    $mess_str = '';    $result = '';    $receivers = array();    foreach( $massmails as $mails )    {      $att_item = $mails["att_item"];      $att_stack = $mails["att_stack"];      if ( $mails["att_gold"] && ( count($att_item) > 0 ) )      {        $mess_str1 = "send money ".$mails["receiver_name"]." \"".$mails["subject"]."\" \"".$mails["body"]."\" ".$mails["att_gold"]."";        $telnet->DoCommand($mess_str1, $result1);        $mess_str .= $mess_str1."<br >";        $result .= $result1."";        $mess_str1 = "send item ".$mails["receiver_name"]." \"".$mails["subject"]."\" \"".$mails["body"]."\" ";        for ( $i = 0; $i < count($att_item); $i++ )          $mess_str1 .= $att_item[$i].( ( $att_stack[$i] > 1 ) ? ":".$att_stack[$i]." " : " " );        $telnet->DoCommand($mess_str1, $result1);        $mess_str .= $mess_str1."<br >";        $result .= $result1."";      }      elseif ( $mails["att_gold"] )      {        $mess_str1 = "send money ".$mails["receiver_name"]." \"".$mails["subject"]."\" \"".$mails["body"]."\" ".$mails["att_gold"]."";        $telnet->DoCommand($mess_str1, $result1);        $mess_str .= $mess_str1."<br >";        $result .= $result1."";      }      elseif ( count($att_item) > 0 )      {        $mess_str1 = "send item ".$mails["receiver_name"]." \"".$mails["subject"]."\" \"".$mails["body"]."\" ";        for ( $i = 0; $i < count($att_item); $i++ )          $mess_str1 .= $att_item[$i].( ( $att_stack[$i] > 1 ) ? ":".$att_stack[$i]." " : " " );        $telnet->DoCommand($mess_str1, $result1);        $mess_str .= $mess_str1."<br >";        $result .= $result1."";      }      else      {        $mess_str1 = "send mail ".$mails["receiver_name"]." \"".$mails["subject"]."\" \"".$mails["body"]."\"";        $telnet->DoCommand($mess_str1, $result1);        $mess_str .= $mess_str1."<br >";        $result .= $result1."";      }      array_push($receivers, $mails["receiver_name"]);    }    if ( $core == 2 )      $core_prompt = "mangos";    elseif ( $core == 3 )      $core_prompt = "TC";    $result = str_replace($core_prompt.">","",$result);    $result = str_replace(array("\r\n", "\n", "\r"), '<br />', $result);    $mess_str .= "<br /><br />".$result;    $telnet->Disconnect();    $receiver_list = '';    foreach ( $receivers as $receiver )    {      $receiver_list .= ', '.$receiver;    }    $receiver_list = substr($receiver_list, 2, strlen($receiver_list)-2);  }  elseif ( $result == 1 )    $mess_str = lang("telnet", "unable");  elseif ( $result == 2 )    $mess_str = lang("telnet", "unknown_host");  elseif ( $result == 3 )    $mess_str = lang("telnet", "login_failed");  elseif ( $result == 4 )    $mess_str = lang("telnet", "not_supported");  if ( !$return )  {    if ( !isset($_GET["redirect"]) )    {      if ( count($massmails) == 1 )        redirect("mail.php?action=result&error=6&mess=".$mess_str."&mailresult=".$result."&recipient=".$receiver_list);      else        redirect("mail.php?action=result&error=6&mess=&mailresult=".$result."&recipient=".$receiver_list);    }    else    {      $money_result = $sql["char"]->quote_smart($_GET["moneyresult"]);      $redirect = $sql["char"]->quote_smart($_GET["redirect"]);      redirect($redirect."?moneyresult=".$money_result."&mailresult=".$result);    }  }  else    return $result;}?>