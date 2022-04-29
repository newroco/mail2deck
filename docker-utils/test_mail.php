<?php
// Run it as > php docker-utils/test_mail.php test@example.com

$to      = $argv[1];
$subject = 'An issue has been reported!';
$message = '<html><head><title>Mail2Deck response</title></head><body><h1>You created a new issue on board XXXXX.</h1><p>Check out this <a href="https://example.com/">link</a> to see your newly created card.</p></body></html>';
$headers = array(
    'From' => 'no-reply@example.com',
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/html'
);

mail($to, $subject, $message, $headers);
