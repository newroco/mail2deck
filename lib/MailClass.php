<?php

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
            $body = "<h1>You created a new card on board {$response->boardTitle}.</h1><p>Check out this <a href=\"" . NC_SERVER . "index.php/apps/deck/#/board/{$response->board}/card/{$response->id}" . "\">link</a> to see your newly created card.</p>";
            $subject = 'An new card has been created!';
        } else {
            $body = "<h1>There was a problem creating your new card.</h1><p>Make sure you set up the board correctly.</p>";
            $subject = "Your issue has not been reported!";
        }

        $message = "<html>";
        $message .= "<head><title>Mail2Deck response</title></head>";
        $message .= "<body>$body</body>";
        $message .= "</html>";

        mail($sender, $subject, $message, $headers);
    }
}
