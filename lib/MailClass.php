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

    public function reply($sender, $response) {
        $serverName = parse_url(NC_SERVER);

        $headers = array(
            'From' => 'no-reply@' . $serverName['host'],
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html'
        );

        $message = "<html>";
        $message .= "<head><title>You created a new issue on board {$response->boardTitle}.</title></head>";
        $message .= "<body>";
        $message .= "<h1>You created a new issue on board {$response->boardTitle}.</h1>";
        $message .= "<p>Check out this <a href=\"" . NC_SERVER . "/index.php/apps/deck/#/board/{$response->board}/card/{$response->id}" . "\">link</a> to see your newly created card.</p>";
        $message .= "</body>";
        $message .= "</html>";

        mail($sender, 'An issue has been reported!', $message, $headers);
    }
}
