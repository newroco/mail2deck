<?php
error_reporting(E_ERROR | E_PARSE);
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/config.php');

use Mail2Deck\MailClass;
use Mail2Deck\DeckClass;
use Mail2Deck\ConvertToMD;

$inbox = new MailClass();
$emails = $inbox->getNewMessages();

if(!$emails) {
    // delete all messages marked for deletion and return
    $inbox->expunge();
    return;
}

for ($j = 0; $j < count($emails) && $j < 5; $j++) {
    $structure = $inbox->fetchMessageStructure($emails[$j]);
    $base64encode = false;
    if($structure->encoding == 3) {
        $base64encode = true; // BASE64
    }
    if( isset($structure->parts) && 
        isset($structure->parts[0]) && 
        $structure->parts[0]->encoding == 3) {
        $base64encode = true; // BASE64
    }
    if(isset($structure->parts) && 
        isset($structure->parts[0]) && 
        isset($structure->parts[0]->parts) && 
        isset($structure->parts[0]->parts[0]) && 
        $structure->parts[0]->parts[0]->encoding == 3) {
       // this handles parts with inline attachments
        $base64encode = true; // BASE64
    }
    $attachments = array();
    $attNames = array();
    if (isset($structure->parts) && count($structure->parts)) {
        for ($i = 0; $i < count($structure->parts); $i++) {
            if ($structure->parts[$i]->ifdparameters) {
                foreach ($structure->parts[$i]->dparameters as $object) {
                    if (strtolower($object->attribute) == 'filename') {
                        $attachments[$i]['is_attachment'] = true;
                        $attachments[$i]['filename'] = $object->value;
                    }
                }
            }

            if ($structure->parts[$i]->ifparameters) {
                foreach ($structure->parts[$i]->parameters as $object) {
                    if (strtolower($object->attribute) == 'name') {
                        $attachments[$i]['is_attachment'] = true;
                        $attachments[$i]['name'] = $object->value;
                    }
                }
            }

            if ($attachments[$i]['is_attachment']) {
                $attachments[$i]['attachment'] = $inbox->fetchMessageBody($emails[$j], $i+1);
                if ($structure->parts[$i]->encoding == 3) { // 3 = BASE64
                    $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                }
                elseif ($structure->parts[$i]->encoding == 4) { // 4 = QUOTED-PRINTABLE
                    $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                }
            }
        }
    }
    for ($i = 1; $i <= count($attachments); $i++) {
        if(! file_exists(getcwd() . '/attachments')) {
            mkdir(getcwd() . '/attachments');
        }
        if ($attachments[$i]['is_attachment'] == 1) {
            $filename = $attachments[$i]['name'];
            if (empty($filename)) $filename = $attachments[$i]['filename'];

            $fp = fopen(getcwd() . '/attachments/' . $filename, "w+");
            fwrite($fp, $attachments[$i]['attachment']);
            fclose($fp);
            array_push($attNames, $attachments[$i]['filename']);
        }
    }

    $overview = $inbox->headerInfo($emails[$j]);
    $board = null;
    if(isset($overview->{'X-Original-To'}) && strstr($overview->{'X-Original-To'}, '+')) {
        $board = strstr(substr($overview->{'X-Original-To'}, strpos($overview->{'X-Original-To'}, '+') + 1), '@', true);
    } else {
        if(strstr($overview->to[0]->mailbox, '+')) {
            $board = substr($overview->to[0]->mailbox, strpos($overview->to[0]->mailbox, '+') + 1);
        }
    };

    if(strstr($board, '+')) $board = str_replace('+', ' ', $board);

    $data = new stdClass();
    $data->title = DECODE_SPECIAL_CHARACTERS ? mb_decode_mimeheader($overview->subject) : $overview->subject;
    $data->type = "plain";
    $data->order = -time();
    $body = $inbox->fetchMessageBody($emails[$j], 1.1);
    if ($body == "") {
        $body = $inbox->fetchMessageBody($emails[$j], 1);
    }
    if(count($attachments)) {
        $data->attachments = $attNames;
        $description = DECODE_SPECIAL_CHARACTERS ? quoted_printable_decode($body) : $body;
    } else {
        $description = DECODE_SPECIAL_CHARACTERS ? quoted_printable_decode($body) : $body;
    }
    if($base64encode) {
        $description = base64_decode($description);
    }
    if($description != strip_tags($description)) {
        $description = (new ConvertToMD($description))->execute();
    }
    $data->description = $description;
    $mailSender = new stdClass();
    $mailSender->userId = $overview->reply_to[0]->mailbox;

    $newcard = new DeckClass();
    $response = $newcard->addCard($data, $mailSender, $board);
    $mailSender->origin .= "{$overview->reply_to[0]->mailbox}@{$overview->reply_to[0]->host}";

    if(MAIL_NOTIFICATION) {
        if($response) {
            $inbox->reply($mailSender->origin, $response);
        } else {
            $inbox->reply($mailSender->origin);
        }
    }
    if(!$response) {
        foreach($attNames as $attachment) unlink(getcwd() . "/attachments/" . $attachment);
    }

    //remove email after processing
    if(DELETE_MAIL_AFTER_PROCESSING) {
        $inbox->delete($emails[$j]);
    }
}
?>
