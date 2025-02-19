<?php

namespace Mail2Deck;

class AttachmentClass {

    function Attachment($structure,$inbox, $emails){
        $attachments = array();
        $attNames = array();

        if (isset($structure->parts) && count($structure->parts)) {
            $parentIndex = null;
        foreach ($structure->parts as $index => $part) {
            $partIndex = $parentIndex ? strval($parentIndex) . '.' . ($index + 1) : ($index + 1);

                if (isset($part->parts) && count($part->parts) > 1) {
                    $result= $this->formattingAttachment($part->parts, $inbox, $emails, $partIndex); //subpart exists in the structure
                }else{
                    $result= $this->formattingAttachment([$part], $inbox, $emails, $partIndex);
                }
                 if ($result) {
                    $attachments = array_merge($attachments, $result);
                }
            }
        }

        //Add formatted attachments
        for ($i = 0; $i < count($attachments); $i++) {
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


    function formattingAttachment($parts, $inbox, $emails, $index) {
        $attachments = array();
        foreach ($parts as $j => $part) {
            if ($part->ifdparameters || $part->ifparameters) {
                $parameters = $part->ifdparameters ? $part->dparameters : $part->parameters;
                $attributeName = $part->ifdparameters ? 'filename' : 'name';

                foreach ($parameters as $object) {
                    if (strtolower($object->attribute) == strtolower($attributeName)) {
                        $attachment['is_attachment'] = true;
                        $attachment[$attributeName] = $object->value;

                        if($part->disposition == "inline" || $part->disposition == "attachment"){
                            $partindex= $index;
                        }else{
                            $partindex= $index . "." . ($j + 1); //if the attachment is part of the subpart
                        }

                        $attachment['attachment']= $inbox->fetchMessageBody($emails,$partindex);

                        if ($part->encoding == 3) { // BASE64
                            $attachment['attachment'] = base64_decode( $attachment['attachment'],true);
                        } else if ($part->encoding == 4) { // QUOTED-PRINTABLE
                            $attachment['attachment'] = quoted_printable_decode( $attachment['attachment']);
                        }
                        $attachments[] = $attachment;
                    }
                }
            }
        }
        return $attachments;
    }
}