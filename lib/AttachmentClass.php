<?php

namespace Mail2Deck;

class AttachmentClass {

function Attachment($structure,$inbox, $emails){
    $attachments = array();
    $attNames = array();
    if (isset($structure->parts) && count($structure->parts)) {
        for ($i = 0; $i < count($structure->parts); $i++) {
            $parts =$structure->parts[$i];
            if ($parts->ifdparameters || $parts->ifparameters) {
                $parameters = $parts->ifdparameters ? $parts->dparameters : $parts->parameters;
                $attributeName = $parts->ifdparameters ? 'filename' : 'name';

                foreach ($parameters as $object) {
                    if (strtolower($object->attribute) == strtolower($attributeName)) {
                        $attachments[$i]['is_attachment'] = true;
                        $attachments[$i][$attributeName] = $object->value;
                    }
                }
            }

            if ($attachments[$i]['is_attachment']) {
                $attachments[$i]['attachment'] = $inbox->fetchMessageBody($emails, $i+1);
                if ($parts->encoding == 3) { // 3 = BASE64
                    $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                }
                elseif ($parts->encoding == 4) { // 4 = QUOTED-PRINTABLE
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
    return $attNames;
}
}