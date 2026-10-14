<?php

namespace Mail2Deck;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

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

    public function getUid($email) {
        return imap_uid($this->inbox, $email);
    }

    public function headerInfo($email) {
        $headerInfo = imap_headerinfo($this->inbox, $email);
        $additionalHeaderInfo = imap_fetchheader($this->inbox, $email);
        $infos = explode("\n", $additionalHeaderInfo);

        foreach($infos as $info) {
            $data = explode(":", $info, 2);
            if( count($data) == 2 && !isset($head[$data[0]])) {
                if(trim($data[0]) === 'X-Original-To') {
                    $headerInfo->{'X-Original-To'} = trim($data[1]);
                    // break;
                } else if(trim($data[0]) === 'Message-ID') {
                    $headerInfo->{'Message-ID'} = trim($data[1]);
                } else if(trim($data[0]) === 'In-Reply-To') {
                    $headerInfo->{'In-Reply-To'} = trim($data[1]);
                }
            }
        }

        return $headerInfo;
    }

    public function reply($sender, $sendername, $response = null) {
        $server = NC_SERVER;

        if(strstr($server, "https://")) {
            $server = str_replace('https://', '', $server);
        } else if(strstr($server, "http://")) {
            $server = str_replace('http://', '', $server);
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = MAIL_SERVER;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USER;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = MAIL_SERVER_SMTPPORT;
            $mail->setFrom(MAIL_FROM, 'Mail2Deck Notification');
            $mail->isHTML(true);
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            if($response) {
                $body = "<p>We received your request and opened a support ticket. You can see it here: <a href=\"" . NC_SERVER . "/index.php/apps/deck/board/{$response->board}/card/{$response->id}" . "\">Newroco Support</a>.</p>"
                    ."<p>To access the card, you will be asked to login, please use your company usual account details.</p>"
                        ."<p>Please add more details or respond to any comments from our team, so we can address the issue as fast as possible.</p><br><br>"
                        ."<p>Thank you for reaching out.</p><br>"
                        ."<span class='name' style='font-weight: bold; padding-right: 5px;'>Newroco</span>"
                        ."<span class='positionSeparator' style='color: #000; font-size: 15pt; line-height: 13px; width: 10px;'>|</span>"
                        ."<span class='position' style='font-weight: normal; padding-left: 5px;'>Support Team</span><br>"
                        ."<a href='https://newro.co' target='_blank' style='color: #00aeef; text-decoration: none;'>"
                            ."<img src='https://newro.co/logo.png' width='200' height='23' alt='newroco' style='margin: 0; padding: 0;'>"
                        ."</a><br><br>";
                $subject = 'We received your support request';
            } else {
                $body = "<h1>There was a problem creating a new card.</h1><p>Make sure the board was setup correctly.</p>";
                $subject = "A new card could not be created!";
            }

            $bodySupport = null;
            if ($response) {
                $descriptionFormatted = $this->renderSupportDescription($response->description);
                $cardTitle = htmlspecialchars($response->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeSender = htmlspecialchars($sender, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $bodySupport = "<p><a href=\"" . NC_SERVER . "/index.php/apps/deck/board/{$response->board}/card/{$response->id}" . "\">{$cardTitle}</a></p>"
                    ."<p>{$descriptionFormatted}</p>"
                    ."<p>Sent by: {$safeSender} </p>";
            }


            /**
             * EMAIL 1: email to sender
             */
            $mail->addAddress($sender);
            $mail->Subject = $subject;
            $mail->Body =  "<html>"
                        ."<head><title>mail2deck response</title></head>"
                        ."<body>$body</body>"
                        ."</html>";

            $mail->send();
                echo "Mail sent successfully to $sender.";

            if(MAIL_SUPPORT != '' && $bodySupport !== null){
                // Clean recipients and attachments for the next email
                $mail->clearAddresses();
                $mail->clearAttachments();

                /**
                 * EMAIL 2: email to support team
                 */
                $mail->addAddress(MAIL_SUPPORT);
                $mail->addReplyTo($sender, $sendername);
                $mail->Subject = "New Card Issue Notification";
                $mail->Body =  "<html>"
                        ."<head><title>mail2deck response</title></head>"
                        ."<body>$bodySupport</body>"
                        ."</html>";
                $mail->send();
                echo "Mail sent successfully to support team.";
            }
        } catch (Exception $e) {
            echo "Mail could not be sent. PHPMailer Error: {$mail->ErrorInfo}";
        }

    }

    /**
     * Mark emails for deletion
     *
     * @param $email email number that you want to delete
     *
     * @return void
     */
    public function delete(int $email)
    {
        imap_delete($this->inbox, imap_uid($this->inbox, $email), FT_UID);
    }

    /**
     * Delete all messages marked for deletion
     */
    public function expunge()
    {
        imap_expunge($this->inbox);
    }

    /**
     * Mark email as read
     *
     * @param $email email number that you want to mark as read
     * @return void
     */
    public function markAsRead(int $email)
    {
        imap_setflag_full($this->inbox, $email, "\\Seen");
    }

    private function renderSupportDescription($description)
    {
        $description = str_replace("\r\n", "\n", (string) $description);
        $output = '';
        $offset = 0;
        $pattern = '/\[([^\]]*)\]\(([^)]+)\)/';

        if (preg_match_all($pattern, $description, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $matchText = $match[0];
                $matchOffset = $match[1];
                $label = $matches[1][$index][0];
                $url = $matches[2][$index][0];

                $output .= htmlspecialchars(substr($description, $offset, $matchOffset - $offset), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $normalizedUrl = $this->normalizeSupportUrl($url);
                if ($normalizedUrl !== null) {
                    $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $safeUrl = htmlspecialchars($normalizedUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $output .= '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $safeLabel . '</a>';
                } else {
                    $output .= htmlspecialchars($matchText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }

                $offset = $matchOffset + strlen($matchText);
            }
        }

        $output .= htmlspecialchars(substr($description, $offset), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return nl2br($output);
    }

    private function normalizeSupportUrl($url)
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if ($url[0] === '<' && substr($url, -1) === '>') {
            $url = substr($url, 1, -1);
        }

        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['scheme'])) {
            return null;
        }

        $scheme = strtolower($parsedUrl['scheme']);
        if (in_array($scheme, ['http', 'https'], true)) {
            return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
        }

        if ($scheme === 'mailto') {
            $address = substr($url, 7);
            return filter_var($address, FILTER_VALIDATE_EMAIL) ? $url : null;
        }

        if ($scheme === 'tel') {
            $number = substr($url, 4);
            return preg_match('/^[0-9+(). -]+$/', $number) ? $url : null;
        }

        return null;
    }
}
