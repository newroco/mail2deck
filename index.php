<?php
error_reporting(E_ERROR | E_PARSE);
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/config.php');

use Mail2Deck\MailClass;
use Mail2Deck\DeckClass;
use Mail2Deck\ConvertToMD;
use Mail2Deck\AttachmentClass;
use Mail2Deck\CreateCardClass;

$inbox = new MailClass();
$emails = $inbox->getNewMessages();

if(!$emails) {
    // delete all messages marked for deletion and return
    $inbox->expunge();
    return;
}

$defaultBoard = NC_DEFAULT_BOARD;

for ($j = 0; $j < count($emails) && $j < 5; $j++) {
    try {
        $structure = $inbox->fetchMessageStructure($emails[$j]);
        $base64encode = false;
        if($structure->encoding == 3) {
            $base64encode = true; // BASE64
        }

        // Check for attachments
        $attclass = new AttachmentClass();
        $attNames = $attclass->Attachment($structure, $inbox, $emails[$j]);
        $overview = $inbox->headerInfo($emails[$j]);

        // Create Card
        $createCardClass = new CreateCardClass();
        $board = $createCardClass->extractBoardName($overview) ?? $defaultBoard;

        $mailSender = new stdClass();
        $data = $createCardClass->createMailData($overview, $mailSender);
        $body = $createCardClass->fetchMailBody($structure, $inbox, $emails[$j]);

        $newcard = new DeckClass();
        $data->attachments = $attNames;
        $data->description = $createCardClass->mailDescription($body, $base64encode, $attNames);

        $mailSender->userId = $overview->reply_to[0]->mailbox ?? "unknown";
        $mailSender->host = $overview->reply_to[0]->host ?? "unknown";

        $cleanedSubject = $createCardClass->cleanedSubject($data->title, $data->description, $newcard);
        $createCardClass->createCard($newcard, $data, $mailSender, $cleanedSubject, $inbox, $board);

    } catch (\Throwable $e) {
        $newcard = new DeckClass();
        $errorData = new stdClass();
        $overview = $inbox->headerInfo($emails[$j]);

        $createCardClass = new CreateCardClass();
        $cleanedSubject = $createCardClass->cleanedSubject($data->title, $data->description, $newcard);

        $errorData->title = "Error processing email  #" . $emails[$j];
        $errorData->description = "An error occurred:\n" ."\n\n"
                                . "Subject: " . ($cleanedSubject ?? 'Unknown') . "\n"
                                . "From: " . ($overview->fromaddress ?? 'Unknown') . "\n"
                                . "Date: " . ($overview->date ?? date('Y-m-d H:i:s')) . "\n\n"
                                . "Error: " . ($e->getMessage() ?? 'Unknown error') . "\n\n"
                                . "Original message:\n" . ($body ?? 'No content');

        $errorData->attachments = [];


        $mailSender = new stdClass();
        $mailSender->userId = "system";
        $mailSender->host = $overview->reply_to[0]->host ?? "unknown";

        $createCardClass = new CreateCardClass();
        $errorBoard = NC_DEFAULT_BOARD;

        $createCardClass->createCard($newcard, $errorData, $mailSender, $errorData->title, $inbox, $errorBoard);

    } finally {
         $inbox->markAsRead($emails[$j]);

        if (DELETE_MAIL_AFTER_PROCESSING) {
            $inbox->delete($emails[$j]);
        }
    }
}
?>
