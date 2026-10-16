<?php
namespace Mail2Deck;

class CreateCardClass{

    function extractBoardName($overview){
        $board = null;
        if(isset($overview->{'X-Original-To'}) && strstr($overview->{'X-Original-To'}, '+')) {
            $board = strstr(substr($overview->{'X-Original-To'}, strpos($overview->{'X-Original-To'}, '+') + 1), '@', true);
        } else if(strstr($overview->to[0]->mailbox, '+')) {
            $board = substr($overview->to[0]->mailbox, strpos($overview->to[0]->mailbox, '+') + 1);
        }

        if(strstr($board, '+')) $board = str_replace('+', ' ', $board);
        return $board;
    }

    function createMailData($overview, $data) {
        $data->title = DECODE_SPECIAL_CHARACTERS ? mb_decode_mimeheader($overview->subject) : $overview->subject;
        $data->type = "plain";
        $data->order = -time();
        return $data;
    }

    function fetchMailBody($structure,$inbox, $emailId) {
        $plainTextFallback = null;
        $htmlBody = $this->getHtmlPart($inbox, $emailId, $structure, '', $plainTextFallback);

        return $htmlBody !== null ? $htmlBody : $plainTextFallback;
    }
    function getHtmlPart($inbox, $emailId, $structure, $prefix, &$plainTextFallback = null) {
        if (!isset($structure->parts)) {
            $partIndex = $prefix ?: 1;
            $body = $inbox->fetchMessageBody($emailId, $partIndex);
            $body = $this->decodeBody($body, $structure->encoding);

            if (isset($structure->subtype) && strtoupper($structure->subtype) == 'HTML') {
                return $body;
            }

            if ($plainTextFallback === null && isset($structure->subtype) && strtoupper($structure->subtype) == 'PLAIN') {
                $plainTextFallback = $body;
            }

            return null;
        }

        foreach ($structure->parts as $index => $part) {
            $partIndex = $prefix ? "$prefix." . ($index + 1) : ($index + 1);

            if ($this->isAttachmentDisposition($part)) {
                continue;
            }

            if (isset($part->subtype) && strtoupper($part->subtype) == 'HTML') {
                $body = $inbox->fetchMessageBody($emailId, $partIndex);
                return $this->decodeBody($body, $part->encoding);
            }

            if ($plainTextFallback === null && isset($part->subtype) && strtoupper($part->subtype) == 'PLAIN') {
                $body = $inbox->fetchMessageBody($emailId, $partIndex);
                $plainTextFallback = $this->decodeBody($body, $part->encoding);
            }

            if (isset($part->parts)) {
                $result = $this->getHtmlPart($inbox, $emailId, $part, $partIndex, $plainTextFallback);
                if ($result) {
                    return $result;
                }
            }
        }

        return null;
    }

    function isAttachmentDisposition($part) {
        return isset($part->disposition) && strtoupper($part->disposition) === 'ATTACHMENT';
    }

    function decodeBody($body, $encoding) {
        if ($encoding == 3) { // BASE64
            return base64_decode($body);
        } elseif ($encoding == 4) { // QUOTED-PRINTABLE
            return quoted_printable_decode($body);
        }
        return $body;
    }

    function mailDescription ($body, $base64encode, $attachments, $stripQuotedReplies = true) {
        $description = $body;
        if ($description != strip_tags($description)) {
            $description = (new ConvertToMD($description))->execute($stripQuotedReplies);
        }
        return $description;
    }

    function cleanedSubject($subject, $description, $newcard) {
        if (strpos($subject, "RE: ") !== false || strpos($subject, "Fwd") !== false || strpos($subject, "FW:") !== false || strpos($subject, "Re:") !== false) {
            $cleanedSubject = preg_replace('/^(Re: |RE: |Fwd: |FW: )\s*/i', '', $subject);
        }else{
            $cleanedSubject = $newcard->findOriginalSubject(strip_tags($description));
        }
        return $cleanedSubject;
    }

    function createCard($newcard, $data, $mailSender, $cleanedSubject, $inbox, $board = null){
        $existingCardId = null;
        if ($cleanedSubject !== null && trim($cleanedSubject) !== '') {
            $params = $newcard->getParameters($data->title, $board, $mailSender->host);
            if (!$params) {
                throw new \RuntimeException("Invalid board parameters or missing board in Nextcloud Deck.");
            }
            $existingCardId = $newcard->findCardBySubject($cleanedSubject, $params->board);
        }
        $mailSender->origin .= "{$mailSender->userId}@{$mailSender->host}";
        if (!$existingCardId) {
            $response = $newcard->addCard($data, $mailSender->origin, $mailSender->host, $board);
            error_log("New card created with response: " . json_encode($response));

            if ($response && defined('ROCKETCHAT_WEBHOOK') && ROCKETCHAT_WEBHOOK !== '') {
                $this->notifyRocketChat($response);
            }
            
            if($data->attachments){
                $imageUrls = $newcard->addAttachments($response, $data->attachments);
                if (!empty($imageUrls)) {
                    $newDescription =$newcard->updateCardDescription($response, $imageUrls);
                    $response->description= $newDescription;
                }
            }

            if (MAIL_NOTIFICATION) {
                if (!$response) {
                    $inbox->reply($mailSender->origin,$mailSender->userId);
                }
                $inbox->reply($mailSender->origin,$mailSender->userId, $response);
            }
        }else{
            $cleanedDescription = $newcard->extractForwardedContent($data->description);
            $cleanedDescription = (new ConvertToMD($cleanedDescription))->execute();

            if (!empty($data->attachments)) {
                $uploadedAttachments = $newcard->addAttachments($existingCardId, $data->attachments);
                $cleanedDescription = $newcard->replaceInlineAttachmentLinks($cleanedDescription, $uploadedAttachments);
            }

            $response = $newcard->addCommentToCard($existingCardId, $cleanedDescription);
            error_log("Comment added with response: " . json_encode($response));
        }
    }

    function notifyRocketChat($card){
        $cardUrl = NC_SERVER . "/index.php/apps/deck/board/{$card->board}/card/{$card->id}";
        $boardTitle = $card->boardTitle ?? "Deck";
                $payload = json_encode([
            'text' => "New ticket on **{$boardTitle}**\n[{$card->title}]({$cardUrl})"
        ]);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => ROCKETCHAT_WEBHOOK,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5,
        ]);
        
        $resp = curl_exec($curl);
        if (curl_errno($curl)) {
            error_log("RocketChat notify error: " . curl_error($curl));
        }
        
        curl_close($curl);
    }
}
