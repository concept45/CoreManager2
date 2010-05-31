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


require_once("header.php");
valid_login($action_permission['update']);

//###########################################################################
// print mail form
function print_mail_form()
{
  global $output;

  $to = (isset($_GET['to'])) ? $_GET['to'] : NULL;
  $type = (isset($_GET['type'])) ? $_GET['type'] :"email";

  $output .= "
        <center>
          <form action=\"mail.php\" method=\"get\" name=\"form\">
            <input type='hidden' name='action' value='send_mail' />
            <fieldset id=\"mail_type_field\">
              <legend>".lang('mail', 'mail_type')."</legend>
              <br />
              <table class=\"top_hidden\" id=\"mail_type\">
                <tr>
                  <td align=\"left\">".lang('mail', 'recipient').": <input type=\"text\" name=\"to\" size=\"32\" value=\"$to\" maxlength=\"225\" /></td>
                  <td align=\"left\">".lang('mail', 'subject').": <input type=\"text\" name=\"subject\" size=\"32\" maxlength=\"50\" /></td>
                  <td width=\"1\" align=\"right\">
                    <select name=\"type\">";
  if ($type == "email")
    $output .= "
                      <option value=\"ingame_mail\">".lang('mail', 'ingame_mail')."</option>
                      <option value=\"email\">".lang('mail', 'email')."</option>";
  else
    $output .= "
                      <option value=\"email\">".lang('mail', 'email')."</option>
                      <option value=\"ingame_mail\">".lang('mail', 'ingame_mail')."</option>";
  $output .= "
                    </select>
                  </td>
                </tr>
                <tr><td colspan=\"3\"><hr /></td></tr>
                <tr>
                  <td colspan=\"3\">
                    ".lang('mail', 'dont_use_both_groupsend_and_to')."
                  </td>
                </tr>
                <tr>
                  <td colspan=\"3\">".lang('mail', 'group_send').":
                    <select name=\"group_send\">
                      <optgroup label=\"".lang('mail', 'both')."\">
                        <option value=\"gm_level\">".lang('mail', 'gm_level')."</option>
                      </optgroup>
                      <optgroup label=\"".lang('mail', 'email')."\">
                        <option value=\"locked\">".lang('mail', 'locked_accouns')."</option>
                        <option value=\"banned\">".lang('mail', 'banned_accounts')."</option>
                      </optgroup>
                      <optgroup label=\"".lang('mail', 'ingame_mail')."\">
                        <option value=\"char_level\">".lang('mail', 'char_level')."</option>
                        <option value=\"online\">".lang('mail', 'online')."</option>
                      </optgroup>
                    </select>
                    <select name=\"group_sign\">
                      <option value=\"=\">=</option>
                      <option value=\"&lt;\">&lt;</option>
                      <option value=\">\">&gt;</option>
                      <option value=\"!=\">!=</option>
                    </select>
                    <input type=\"text\" name=\"group_value\" size=\"20\" maxlength=\"40\" />
                  </td>
                </tr>
                <tr><td colspan=\"3\"><hr /></td></tr>
                <tr>
                  <td colspan=\"3\" align=\"left\">
                    ".lang('mail', 'attachments').":
                  </td>
                </tr>
                <tr>
                  <td colspan=\"3\" align=\"right\">
                    ".lang('mail', 'money')." : <input type=\"text\" name=\"money\" value=\"0\" size=\"10\" maxlength=\"10\" />
                    ".lang('mail', 'item')." : <input type=\"text\" name=\"att_item\" value=\"0\" size=\"10\" maxlength=\"10\" />
                    ".lang('mail', 'stack')." : <input type=\"text\" name=\"att_stack\" value=\"0\" size=\"10\" maxlength=\"10\" />
                  </td>
                </tr>
                <tr>
                  <td colspan=\"3\">
                  </td>
                </tr>
              </table>
            </fieldset>
            <fieldset id=\"mail_body_field\">
              <legend>".lang('mail', 'mail_body')."</legend>
              <br /><textarea name=\"body\" rows=\"14\" cols=\"92\"></textarea><br />
              <br />
              <table>
                <tr>
                  <td>";
                   makebutton(lang('mail', 'send'), "javascript:do_submit()",130);
  $output .= "
                  </td>
                </tr>
              </table>
            </fieldset>
            <br />
          </form>
        </center>
";
}


//#############################################################################
// Send the actual mail(s)
function send_mail()
{
  global $output, $logon_db, $characters_db, $realm_id,
         $user_name, $from_mail, $mailer_type, $smtp_cfg, $GMailSender, $sqll, $sqlc;

  if ( empty($_GET['body']) || empty($_GET['subject']) || empty($_GET['type']) || empty($_GET['group_sign']) || empty($_GET['group_send']) )
  {
    redirect("mail.php?error=1");
  }

  $body = explode("\n",$_GET['body']);
  $subject = $sqlc->quote_smart($_GET['subject']);

  if(isset($_GET['to'])&&($_GET['to'] != ''))
    $to = $sqlc->quote_smart($_GET['to']);
  else
  {
    $to = 0;
    if(!isset($_GET['group_value'])||$_GET['group_value'] === '')
    {
      redirect("mail.php?error=1");
    }
    else
    {
      $group_value = $sqlc->quote_smart($_GET['group_value']);
      $group_sign = $sqlc->quote_smart($_GET['group_sign']);
      $group_send = $sqlc->quote_smart($_GET['group_send']);
    }
  }

  $type = addslashes($_GET['type']);
  $att_gold = $sqlc->quote_smart($_GET['money']);
  $att_item = $sqlc->quote_smart($_GET['att_item']);
  $att_stack = $sqlc->quote_smart($_GET['att_stack']);
  
  if ( ( $att_item <> 0 ) && ( $att_stack == 0 ) )
    $att_stack = 1;

  switch ($type)
  {
    case "email":

      require_once("libs/mailer/class.phpmailer.php");
      require_once("libs/mailer/authgMail_lib.php");
      $mail = new PHPMailer();
      $mail->Mailer = $mailer_type;
      if ($mailer_type == "smtp")
      {
        $mail->Host = $smtp_cfg['host'];
        $mail->Port = $smtp_cfg['port'];
        if($smtp_cfg['user'] != '')
        {
          $mail->SMTPAuth  = true;
          $mail->Username  = $smtp_cfg['user'];
          $mail->Password  =  $smtp_cfg['pass'];
        }
      }

      $value = NULL;
      for($i=0;$i<(count($body));$i++)
        $value .= $body[$i]."\r\n";
      $body=$value;

      $mail->From = $from_mail;
      $mail->FromName = $user_name;
      $mail->Subject = $subject;
      $mail->IsHTML(true);

      $body = str_replace("\n", "<br />", $body);
      $body = str_replace("\r", " ", $body);
      $body = str_replace(array("\r\n", "\n", "\r"), '<br />', $body);
      $body = preg_replace( "/([^\/=\"\]])((http|ftp)+(s)?:\/\/[^<>\s]+)/i", "\\1<a href=\"\\2\" target=\"_blank\">\\2</a>",  $body);
      $body = preg_replace('/([^\/=\"\]])(www\.)(\S+)/', '\\1<a href="http://\\2\\3" target="_blank">\\2\\3</a>', $body);

      $mail->Body = $body;
      $mail->WordWrap = 50;

      if($to)
      {
        if(!$GMailSender)
        {
          //single Recipient
          $mail->AddAddress($to);
          if(!$mail->Send())
          {
            $mail->ClearAddresses();
            redirect("mail.php?error=3&mail_err=".$mail->ErrorInfo);
          }
          else
          {
            $mail->ClearAddresses();
            redirect("mail.php?error=2");
          }
        }
        else
        {
          //single Recipient
          $mail_result = authgMail($from_mail, $user_name, $to, $to, $subject, $body, $smtp_cfg);
          if($mail_result['quitcode'] <> 221)
          {
            redirect("mail.php?error=3$mail_err=".$mail_result['die']);
          }
          else
          {
            redirect("mail.php?error=2");
          }
        }
      }
      elseif (isset($group_value))
      {
        //group send
        $email_array = array();
        switch ($group_send)
        {
          case "gm_level":
            $result = $sqll->query("SELECT email FROM accounts WHERE gm $group_sign '$group_value'");
            while($user = $sql->fetch_row($result))
            {
              if($user[0] != "") array_push($email_array, $user[0]);
            }
            break;
          case "locked":
            //this_is_junk: I'm going to pretend that locked is muted.
            $result = $sqll->query("SELECT email FROM accounts WHERE muted $group_sign '$group_value'");
            while($user = $sqll->fetch_row($result))
            {
              if($user[0] != "")
                array_push($email_array, $user[0]);
            }
            break;
          case "banned":
            //this_is_junk: sigh...
            $que = $sqll->query("SELECT id FROM account_banned");
            while ($banned = $sql->fetch_row($que))
            {
              $result = $sqll->query("SELECT email FROM accounts WHERE acct = '$banned[0]'");
              if($sqlr->result($result, 0, 'email'))
                array_push($email_array, $sql->result($result, 0, 'email'));
            }
            break;
          default:
            redirect("mail.php?error=5");
            break;
        }
        if(!$GMailSender)
        {
          foreach ($email_array as $mail_addr)
          {
            $mail->AddAddress($mail_addr);
            if(!$mail->Send())
            {
              $mail->ClearAddresses();
              redirect("mail.php?error=3&mail_err=".$mail->ErrorInfo);
            }
            else
            {
              $mail->ClearAddresses();
            }
          }
        }
        else
        {
          $mail_to = implode(",", $email_array);
          $mail_result = authgMail($from_mail, $user_name, $mail_to, "", $subject, $body, $smtp_cfg);
          if($mail_result['quitcode'] <> 221)
          {
            redirect("mail.php?error=3$mail_err=".$mail_result['die']);
          }
          else
          {
            redirect("mail.php?error=2");
          }
          
        }
        redirect("mail.php?error=2");
      }
      else
        redirect("mail.php?error=1");
      break;
    case "ingame_mail":
      $value = NULL;
      for($i=0;$i<(count($body));$i++)
      $value .= $body[$i]." ";
      $body=$value;
      $body = str_replace("\r", " ", $body);
      $body = $sqlc->quote_smart($body);

      if($to)
      {
        //single Recipient
        $result = $sqlc->query("SELECT guid FROM characters WHERE name = '$to'");
        if ($sqlc->num_rows($result) == 1)
        {
          $receiver = $sqlc->result($result, 0, 'guid');
          $mails = array();
          $mail['receiver'] = $receiver;
          $mail['subject'] = $subject;
          $mail['body'] = $body;
          $mail['att_gold'] = $att_gold;
          $mail['att_item'] = $att_item;
          $mail['att_stack'] = $att_stack;
          $mail['receiver_name'] = $to;
          //array_push($mails, array($receiver, $subject, $body, $att_gold, $att_item, $att_stack));
          array_push($mails, $mail);
          send_ingame_mail($realm_id, $mails);
        }
        else
        {
          redirect("mail.php?error=4");
        }
        redirect("mail.php?error=2");
        break;
      }
      elseif(isset($group_value))
      {
        //group send
        $char_array = array();
        switch ($group_send)
        {
          case "gm_level":
            $result = $sqll->query("SELECT acct FROM accounts WHERE gm $group_sign '$group_value'");
            while($acc = $sqlc->fetch_row($result))
            {
              $result_2 = $sqlc->query("SELECT name FROM `characters` WHERE acct = '$acc[0]'");
              while($char = $sqlc->fetch_row($result_2))
                array_push($char_array, $char[0]);
            }
            break;
          case "online":
            $result = $sqlc->query("SELECT name FROM `characters` WHERE online $group_sign '$group_value'");
            while($user = $sqlc->fetch_row($result))
              array_push($char_array, $user[0]);
            break;
          case "char_level":
            $result = $sqlc->query("SELECT name FROM `characters` WHERE level $group_sign '$group_value'");
            while($user = $sqlc->fetch_row($result))
              array_push($char_array, $user[0]);
            break;
          default:
            redirect("mail.php?error=5");
        }
        $mails = array();
        if($sqlc->num_rows($result))
        {
          foreach ($char_array as $receiver)
          {
            $result = $sqlc->query("SELECT guid FROM characters WHERE name = '".$receiver."'");
            $char_guid = $sqlc->fetch_row($result);
            $mail = array();
            $mail['receiver'] = $char_guid[0];
            $mail['subject'] = $subject;
            $mail['body'] = $body;
            $mail['att_gold'] = $att_gold;
            $mail['att_item'] = $att_item;
            $mail['att_stack'] = $att_stack;
            $mail['receiver_name'] = $receiver;
            //array_push($mails, array($receiver, $subject, $body, $att_gold, $att_item, $att_stack));
            array_push($mails, $mail);
          }
          send_ingame_mail($realm_id, $mails);
          redirect("mail.php?error=2");
        }
        else
        {
          redirect("mail.php?error=4");
        }
      }
      break;
    default:
      redirect("mail.php?error=1");
  }

}

//##########################################################################################
//SEND INGAME MAIL
//
function send_ingame_mail($realm_id, $massmails)
{
  global $server, $characters_db, $realm_id, $from_char, $stationary, $sqlc;

  //$mess_str = '';
  $mess = 0;
  $result = '';
  foreach($massmails as $mails)
  {
    $sqlc->query("INSERT INTO mailbox_insert_queue (sender_guid, receiver_guid, subject, body, stationary, money, item_id, item_stack)
                  VALUES ('".$from_char."', '".$mails['receiver']."', '".$mails['subject']."', '".$mails['body']."', '".$stationary."', '".$mails['att_gold']."', '".$mails['att_item']."', '".$mails['att_stack']."')");
    if($sqlc->affected_rows())
    {
      //$mess_str .= "Successfully sent message sent to ". $mails['receiver_name']."<br />";
      $mess = 0; // success
      $result = "RESULT";
    }
    else
    {
      //$mess_str .= "Failed to send message to ".$mails['receiver_name']."<br />";
      $mess = -1; // failure
      $result = "RESULT";
    }
  }

  if (!isset($_GET['redirect']))
    //redirect("mail.php?action=result&error=6&mess=$mess_str");
    redirect("mail.php?action=result&error=6&mess=$mess&recipient=".$mails['receiver_name']);
  else
  {
    $redirect = $sqlc->quote_smart($_GET['redirect']);
    redirect($redirect);
  }

}

//########################################################################################################################
// InGame Mail Result
//########################################################################################################################
//
// Xiong Guoy
// 2009-08-08
// report page for send_ingame_mail
function result()
{
  global $output;
  $mess = (isset($_GET['mess'])) ? $_GET['mess'] : NULL;
  $recipient = $_GET['recipient'];
  switch ($mess)
  {
    case 0: // success
    {
      $mess = lang('mail', 'result_success');
      break;
    }
    case -1: //failure
    {
      $mess = lang('mail', 'result_failed');
      break;
    }
  }
  $mess .= ': '.$recipient.'<br>';
  $output .= '
        <center>
          <br />
          <table width="400" class="flat">
            <tr>
              <td align="left">
                <br />'.$mess.'<br />';
  unset($mess);
  $output .= '
              </td>
            </tr>
          </table>
          <br />
          <table width="400" class="hidden">
            <tr>
              <td align="center">';
                makebutton(lang('global', 'back'), 'mail.php', 130);
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

$output .= "
      <div class=\"bubble\">
        <div class=\"top\">";

//$lang_mail = lang_mail();

switch ($err)
{
  case 1:
    $output .= "
          <h1><font class=\"error\">".lang('global', 'empty_fields')."</font></h1>";
    break;
  case 2:
    $output .= "
          <h1><font class=\"error\">".lang('mail', 'mail_sent')."</font></h1>";
    break;
  case 3:
    $mail_err = (isset($_GET['mail_err'])) ? $_GET['mail_err'] : NULL;
    $output .= "
          <h1><font class=\"error\">".lang('mail', 'mail_err').": $mail_err</font></h1>";
    break;
  case 4:
    $output .= "
          <h1><font class=\"error\">".lang('mail', 'no_recipient_found')."</font></h1>
          ".lang('mail', 'use_name_or_email');
    break;
  case 5:
    $output .= "
          <h1><font class=\"error\">".lang('mail', 'option_unavailable')."</font></h1>
          ".lang('mail', 'use_currect_option');
    break;
  case 6:
    $output .= "
          <h1><font class=\"error\">".lang('mail', 'result')."</font></h1>";
    break;
  default: //no error
    $output .= "
          <h1>".lang('mail', 'send_mail')."</h1>";
}
unset($err);

$output .= "
        </div>";

$action = (isset($_GET['action'])) ? $_GET['action'] : NULL;

switch ($action)
{
  case "send_mail":
    send_mail();
    break;
  case "result":
    result();
    break;
  default:
    print_mail_form();
}

unset($action);
unset($action_permission);
//unset($lang_mail);

require_once("footer.php");

?>