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

for ($j = 0; $j < count($emails) && $j < 5; $j++) {
    $structure = $inbox->fetchMessageStructure($emails[$j]);
    $base64encode = false;
    if($structure->encoding == 3) {
        $base64encode = true; // BASE64
    }

    //Check for attachment
    $attclass= new AttachmentClass();
    $attNames = $attclass->Attachment($structure,$inbox, $emails[$j]);
    $overview = $inbox->headerInfo($emails[$j]);

    //Create Card
    $createCardClass= new CreateCardClass();
    $board = $createCardClass->extractBoardName($overview);

    $mailSender = new stdClass();
    $data = $createCardClass->createMailData($overview, $mailSender);
    $body = $createCardClass->fetchMailBody($inbox, $emails[$j]);

    $newcard = new DeckClass();
    $data->attachments = $attNames;
    $data->description = $createCardClass->mailDescription($body, $base64encode, $attNames);

    $mailSender->userId = $overview->reply_to[0]->mailbox;
    $mailSender->host = $overview->reply_to[0]->host;

    $cleanedSubject = $createCardClass->cleanedSubject($data->title, $data->description, $newcard);
    $createCardClass->createCard($newcard, $data, $mailSender, $cleanedSubject, $inbox);

    //remove email after processing
    if(DELETE_MAIL_AFTER_PROCESSING) {
        $inbox->delete($emails[$j]);
    }
}
?>
