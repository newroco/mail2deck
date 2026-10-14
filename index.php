<?php
error_reporting(E_ERROR | E_PARSE);
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/config.php');

use Mail2Deck\MailClass;
use Mail2Deck\DeckClass;
use Mail2Deck\AttachmentClass;
use Mail2Deck\CreateCardClass;

function failedEmailStatePath() {
    return defined('FAILED_EMAIL_STATE_FILE') ? FAILED_EMAIL_STATE_FILE : __DIR__ . '/var/failed_emails.json';
}

function loadFailedEmailState() {
    $path = failedEmailStatePath();
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false || $contents === '') {
        return [];
    }

    $state = json_decode($contents, true);

    return is_array($state) ? $state : [];
}

function saveFailedEmailState(array $state) {
    $path = failedEmailStatePath();
    $directory = dirname($path);

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT));
}

function buildFailedEmailKey($overview, MailClass $inbox, $email) {
    if (isset($overview->{'Message-ID'}) && trim($overview->{'Message-ID'}) !== '') {
        return 'message-id:' . trim($overview->{'Message-ID'});
    }

    return 'uid:' . $inbox->getUid($email);
}

function failedEmailRetryDelay($attempts) {
    $baseDelay = defined('FAILED_EMAIL_RETRY_COOLDOWN') ? FAILED_EMAIL_RETRY_COOLDOWN : 900;
    $maxDelay = defined('FAILED_EMAIL_RETRY_MAX_COOLDOWN') ? FAILED_EMAIL_RETRY_MAX_COOLDOWN : 3600;

    return min($baseDelay * max(1, (int) $attempts), $maxDelay);
}

function shouldDelayFailedEmail(array $state, $key) {
    return isset($state[$key]['retry_after']) && $state[$key]['retry_after'] > time();
}

function recordFailedEmailAttempt(array &$state, $key, \Throwable $primaryError, \Throwable $persistenceError = null) {
    $attempts = ($state[$key]['attempts'] ?? 0) + 1;
    $state[$key] = [
        'attempts' => $attempts,
        'retry_after' => time() + failedEmailRetryDelay($attempts),
        'last_error' => $primaryError->getMessage(),
        'last_error_persist_error' => $persistenceError ? $persistenceError->getMessage() : null,
        'updated_at' => date(DATE_ATOM),
    ];
}

function clearFailedEmailAttempt(array &$state, $key) {
    unset($state[$key]);
}

$inbox = new MailClass();
$emails = $inbox->getNewMessages();
$failedEmailState = loadFailedEmailState();

if(!$emails) {
    // delete all messages marked for deletion and return
    $inbox->expunge();
    return;
}

$defaultBoard = NC_BOARD["DEFAULT_BOARD"];

$processedEmails = 0;
$maxEmailsPerRun = 5;

foreach ($emails as $emailNumber) {
    $overview = $inbox->headerInfo($emailNumber);
    $emailKey = buildFailedEmailKey($overview, $inbox, $emailNumber);

    if (shouldDelayFailedEmail($failedEmailState, $emailKey)) {
        continue;
    }

    if ($processedEmails >= $maxEmailsPerRun) {
        break;
    }

    $processedEmails++;
    $processedSuccessfully = false;
    $body = null;
    $data = new stdClass();
    $data->title = null;
    $data->description = null;

    try {
        $structure = $inbox->fetchMessageStructure($emailNumber);
        $base64encode = false;
        if($structure->encoding == 3) {
            $base64encode = true; // BASE64
        }

        // Check for attachments
        $attclass = new AttachmentClass();
        $attNames = $attclass->Attachment($structure, $inbox, $emailNumber);

        // Create Card
        $createCardClass = new CreateCardClass();
        $board = $createCardClass->extractBoardName($overview);

        $mailSender = new stdClass();
        $data = $createCardClass->createMailData($overview, new stdClass());
        $body = $createCardClass->fetchMailBody($structure, $inbox, $emailNumber);

        $newcard = new DeckClass();
        $data->attachments = $attNames;
        $data->description = $createCardClass->mailDescription($body, $base64encode, $attNames);

        $mailSender->userId = $overview->reply_to[0]->mailbox ?? "unknown";
        $mailSender->host = $overview->reply_to[0]->host ?? "unknown";

        $cleanedSubject = $createCardClass->cleanedSubject($data->title, $data->description, $newcard);
        $createCardClass->createCard($newcard, $data, $mailSender, $cleanedSubject, $inbox, $board);
        $processedSuccessfully = true;

    } catch (\Throwable $e) {
        try {
            $newcard = new DeckClass();
            $errorData = new stdClass();

            $createCardClass = new CreateCardClass();
            $cleanedSubject = $data->title && $data->description
                ? $createCardClass->cleanedSubject($data->title, $data->description, $newcard)
                : ($overview->subject ?? 'Unknown');

            $errorData->title = "Error processing email  #" . $emailNumber;
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

            $createCardClass->createCard($newcard, $errorData, $mailSender, $errorData->title, $inbox, $defaultBoard);
            $processedSuccessfully = true;
        } catch (\Throwable $persistenceError) {
            recordFailedEmailAttempt($failedEmailState, $emailKey, $e, $persistenceError);
            error_log("mail2deck failed to process email {$emailNumber}: {$e->getMessage()} | error persistence failed: {$persistenceError->getMessage()}");
        }
    }

    if ($processedSuccessfully) {
        clearFailedEmailAttempt($failedEmailState, $emailKey);
        $inbox->markAsRead($emailNumber);

        if (DELETE_MAIL_AFTER_PROCESSING) {
            $inbox->delete($emailNumber);
        }
    }
}

saveFailedEmailState($failedEmailState);
?>
