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
                    $result= $this->processAttachment($part->parts, $inbox, $emails, $partIndex);//subpart exists in the structure
                }else{
                    $result= $this->processAttachment([$part], $inbox, $emails, $partIndex);
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

                //ignore small images from signature
                $minSize = defined('MIN_ATTACHMENT_SIZE') ? MIN_ATTACHMENT_SIZE : 5120;
                if (preg_match('/\.(png|jpe?g|gif|bmp|webp|svg)$/i', $filename) && strlen($attachments[$i]['attachment']) < $minSize) {
                    continue;
                }
                
                $safeFilename = basename(str_replace('\\', '/', $filename));
                if ($safeFilename === '' || $safeFilename === '.' || $safeFilename === '..') {
                    continue;
                }
                $filePath = getcwd() . '/attachments/' . $safeFilename;
                $fp = fopen($filePath, "w+");
                fwrite($fp, $attachments[$i]['attachment']);
                fclose($fp);

                $attNames[] = $safeFilename;
            }
        }
        return $attNames;
    }

    function processAttachment($parts, $inbox, $emails, $index) {
        $attachments = array();
        foreach ($parts as $j => $part) {
            if ($part->ifdparameters || $part->ifparameters) {
                $parameters = $part->ifdparameters ? $part->dparameters : $part->parameters;
                $attributeName = $part->ifdparameters ? 'filename' : 'name';

                foreach ($parameters as $object) {
                    if (strtolower($object->attribute) == strtolower($attributeName)) {
                        $attachment['is_attachment'] = true;
                        $attachment[$attributeName] = $object->value;

                        $partindex = count($parts) > 1
                            ? $index . "." . ($j + 1)
                            : (string) $index;

                        $attachment['attachment']= $inbox->fetchMessageBody($emails,$partindex);
                        if(! $attachment['attachment']){
                            $attachment['attachment']= $inbox->fetchMessageBody($emails,$index);
                        }

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