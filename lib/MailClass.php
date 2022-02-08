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
        return imap_headerinfo($this->inbox, $email);
    }

    public function reply($sender, $response = null) {
        $server = NC_SERVER;

        if(str_contains($server, "https://")) {
            $server = str_replace('https://', '', $server);
        } else if(str_contains($server, "http://")) {
            $server = str_replace('http://', '', $server);
        }

        $headers = array(
            'From' => 'no-reply@' . $server,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html'
        );

        if($response) {
            $body = "<h1>You created a new issue on board {$response->boardTitle}.</h1><p>Check out this <a href=\"" . NC_SERVER . "/index.php/apps/deck/#/board/{$response->board}/card/{$response->id}" . "\">link</a> to see your newly created card.</p>";
            $subject = 'An issue has been reported!';
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
