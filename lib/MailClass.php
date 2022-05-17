<?php

namespace Mail2Deck;

class MailClass {
    private $inbox;

    public function __construct()
    {
        $this->inbox = imap_open("{" . MAIL_SERVER . ":" . MAIL_SERVER_PORT . MAIL_SERVER_FLAGS . "}INBOX", MAIL_USER, MAIL_PASSWORD)
        or die("can't connect:" . imap_last_error());
    }

    public function __destruct()
    {
        imap_close($this->inbox);
    }

    public function getNewMessages() {
        return imap_search($this->inbox, 'UNSEEN');
    }

    public function fetchMessageStructure($email) {
        return imap_fetchstructure($this->inbox, $email);
    }

    public function fetchMessageBody($email, $section) {
        return imap_fetchbody($this->inbox, $email, $section);
    }

    public function headerInfo($email) {
        $headerInfo = imap_headerinfo($this->inbox, $email);
        $additionalHeaderInfo = imap_fetchheader($this->inbox, $email);
        $infos = explode("\n", $additionalHeaderInfo);

        foreach($infos as $info) {
            $data = explode(":", $info);
            if( count($data) == 2 && !isset($head[$data[0]])) {
                if(trim($data[0]) === 'X-Original-To') {
                    $headerInfo->{'X-Original-To'} = trim($data[1]);
                    break;
                }
            }
        }

        return $headerInfo;
    }

    public function reply($sender, $response = null) {
        $server = NC_SERVER;

        if(strstr($server, "https://")) {
            $server = str_replace('https://', '', $server);
        } else if(strstr($server, "http://")) {
            $server = str_replace('http://', '', $server);
        }

        $headers = array(
            'From' => 'no-reply@' . $server,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html'
        );

        if($response) {
            $body = "<h1>A new card has been created on board <a href=\"" . NC_SERVER . "/index.php/apps/deck/#/board/{$response->board}" . "\">{$response->boardTitle}</a>.</h1>
                    <p>Check out this <a href=\"" . NC_SERVER . "/index.php/apps/deck/#/board/{$response->board}/card/{$response->id}" . "\">link</a> to see the newly created card.</p>
                    <p>Card ID is {$response->id}</p>";
            $subject = 'A new card has been created!';
        } else {
            $body = "<h1>There was a problem creating a new card.</h1><p>Make sure the board was setup correctly.</p>";
            $subject = "A new card could not be created!";
        }

        $message = "<html>";
        $message .= "<head><title>mail2deck response</title></head>";
        $message .= "<body>$body</body>";
        $message .= "</html>";

        mail($sender, $subject, $message, $headers);
    }

    /**
     * Deletes a mail
     * 
     * @param $email email id that you want to delete
     * 
     * @return void
     */
    public function delete(int $email)
    {
        imap_delete($this->inbox, $email);
        imap_expunge($this->inbox);
    }
}
