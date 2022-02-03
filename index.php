<?php
error_reporting(E_ERROR | E_PARSE);
require_once("config.php");
require_once('lib/DeckClass.php');

$inbox = imap_open("{" . MAIL_SERVER . ":" . MAIL_SERVER_PORT . MAIL_SERVER_FLAGS . "}INBOX", MAIL_USER, MAIL_PASSWORD)
        or die("can't connect:" . imap_last_error());

$emails = imap_search($inbox, 'UNSEEN');

if ($emails)
    for ($j = 0; $j < count($emails) && $j < 5; $j++) {
        $structure = imap_fetchstructure($inbox, $emails[$j]);
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

        $overview = imap_headerinfo($inbox, $emails[$j]);
        
        $data = new stdClass();
        $data->title = DECODE_SPECIAL_CHARACTERS ? mb_decode_mimeheader($overview->subject) : $overview->subject;
        $data->type = "plain";
        if(count($attachments)) {
            $data->attachments = $attNames;
            $description = DECODE_SPECIAL_CHARACTERS ? quoted_printable_decode(imap_fetchbody($inbox, $emails[$j], 1.1)) : imap_fetchbody($inbox, $emails[$j], 1.1);
        } else {
            $description = DECODE_SPECIAL_CHARACTERS ? quoted_printable_decode(imap_fetchbody($inbox, $emails[$j], 1)) : imap_fetchbody($inbox, $emails[$j], 1);
        }
        $data->description = $description;

        $newcard = new DeckClass();
        $newcard->addCard($data);
    }
    
    imap_close($inbox);
?>
