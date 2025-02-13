<?php

namespace Mail2Deck;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

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
            $mail->setFrom(MAIL_USER, 'Mail2Deck Notification');
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

             //Inline image
             $description = $response->description;
             $pattern = '/\[(.*?)\]\((https?:\/\/.*\.(?:jpg|jpeg|png|gif))\)/i';
             if (preg_match($pattern, $description)) {
                 $descriptionFormatted = preg_replace('/\[(.*?)\]\((.*?)\)/', '<img src="$2" alt="$1">', $description);
             }else{
                 $descriptionFormatted = $description;

                 //Attachements
                 $attachments = $response->attachments;
                 if (!empty($attachments)) {
                     foreach ($attachments as $attachment) {
                         $descriptionFormatted .= "<br><a href=\"".NC_SERVER ."/remote.php/dav/files/".NC_USER."/Deck/".$attachment. "\" target='_blank'>$attachment</a><br>";
                     }
                 }
             }

            $bodySupport="<p><a href=\"" . NC_SERVER . "/index.php/apps/deck/board/{$response->board}/card/{$response->id}" . "\">{$response->title}</a></p>"
                ."<p>{$descriptionFormatted}</p>"
                ."<p>Sent by: {$sender} </p>";


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

            if(MAIL_SUPPORT != ''){
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
}
