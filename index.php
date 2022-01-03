<?php
error_reporting(E_ERROR | E_PARSE);
require_once("config.php");
require_once('lib/DeckClass.php');

$inbox = imap_open("{" . MAIL_SERVER . "/imap" . MAIL_SERVER_FLAGS . "}INBOX", MAIL_USER, MAIL_PASSWORD)
        or die("can't connect:" . imap_last_error());

$emails = imap_search($inbox, 'UNSEEN');

if ($emails)
    for ($j = 0; $j <= count($emails) && $j <= 4; $j++) {
        $structure = imap_fetchstructure($inbox, $emails[$j]);
        $attachments = array();
        if (isset($structure->parts) && count($structure->parts)) {
            for ($i = 0; $i < count($structure->parts); $i++) {
                $attachments[$i] = array(
                    'is_attachment' => false,
                    'filename' => '',
                    'name' => '',
                    'attachment' => ''
                );

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
                    $attachments[$i]['attachment'] = imap_fetchbody($inbox, $emails[$j], $i+1);
                    if ($structure->parts[$i]->encoding == 3) { // 3 = BASE64
                        $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                    }
                    elseif ($structure->parts[$i]->encoding == 4) { // 4 = QUOTED-PRINTABLE
                        $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                    }
                }
            }
        }
        for ($i = 1; $i < count($attachments); $i++) {
            if ($attachments[$i]['is_attachment'] == 1) {
                $filename = $attachments[$i]['name'];
                if (empty($filename)) $filename = $attachments[$i]['filename'];

                $fp = fopen("./attachments/" . $filename, "w+");
                fwrite($fp, $attachments[$i]['attachment']);
                fclose($fp);
            }
        }

        $hasAttachment = false;
        for ($i = 0; $i < count($attachments); $i++) {
            if ($attachments[$i]['is_attachment'] != '') {
                $hasAttachment = true;
            }
        }

        $overview = imap_headerinfo($inbox, $emails[$j]);
        $toAddress = strrev($overview->toaddress);
        if(preg_match('/@([^+]+)/', $toAddress, $m)) {
            global $boardName;
            $boardName = strrev($m[1]);
        }
        if ($hasAttachment) {
            $message = imap_fetchbody($inbox, $emails[$j], 1.1);
        } else {
            $message = imap_fetchbody($inbox, $emails[$j], 1);
        }
        $mailData = new stdClass();
        $mailData->mailSubject = $overview->subject;
        $mailData->mailMessage = $message;
        $mailData->from = $overview->from[0]->mailbox . '@' . $overview->from[0]->host;

        $newcard = new DeckClass();
        $newcard->getParameters();
        $newcard->addCard($data);

        if ($hasAttachment) {
            for ($i = 1; $i <= count($attachments); $i++) {
                $mailData->fileAttached[$i] = $attachments[$i]['name'];
            }
            $newcard->addAttachment($data);
        }
    }

imap_close($inbox);
?>
