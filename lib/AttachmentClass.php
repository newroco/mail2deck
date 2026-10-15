<?php

namespace Mail2Deck;

class AttachmentClass {

    function Attachment($structure,$inbox, $emails){
        $attachments = array();
        $attNames = array();

        if (isset($structure->parts) && count($structure->parts)) {
            $attachments = array_merge($attachments, $this->collectAttachments($structure->parts, $inbox, $emails, ''));
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
                $filePath = getcwd() . '/attachments/' . uniqid('att_', true);
                $fp = fopen($filePath, "wb");
                fwrite($fp, $attachments[$i]['attachment']);
                fclose($fp);

                $attNames[] = [
                    'path' => $filePath,
                    'name' => $safeFilename,
                    'cid' => $attachments[$i]['cid'] ?? null,
                ];
            }
        }
        return $attNames;
    }

    function collectAttachments($parts, $inbox, $emails, $prefix) {
        $attachments = array();
        foreach ($parts as $j => $part) {
            $partIndex = $prefix ? $prefix . '.' . ($j + 1) : ($j + 1);

            if (isset($part->parts) && count($part->parts)) {
                $attachments = array_merge($attachments, $this->collectAttachments($part->parts, $inbox, $emails, $partIndex));
                continue;
            }

            if ($part->ifdparameters || $part->ifparameters) {
                $parameters = $part->ifdparameters ? $part->dparameters : $part->parameters;
                $attributeName = $part->ifdparameters ? 'filename' : 'name';
                $contentId = $this->normalizeContentId($part->id ?? null);

                foreach ($parameters as $object) {
                    if (strtolower($object->attribute) == strtolower($attributeName)) {
                        $attachment = ['is_attachment' => true];
                        $attachment[$attributeName] = $object->value;
                        if ($contentId !== null) {
                            $attachment['cid'] = $contentId;
                        }

                        $attachment['attachment'] = $inbox->fetchMessageBody($emails, $partIndex);
                        if (! $attachment['attachment']) {
                            $attachment['attachment'] = $inbox->fetchMessageBody($emails, $partIndex);
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

    private function normalizeContentId($contentId) {
        if (!is_string($contentId)) {
            return null;
        }

        $contentId = trim($contentId);
        if ($contentId === '') {
            return null;
        }

        if ($contentId[0] === '<' && substr($contentId, -1) === '>') {
            $contentId = substr($contentId, 1, -1);
        }

        $contentId = trim($contentId);

        return $contentId !== '' ? $contentId : null;
    }
}