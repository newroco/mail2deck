<?php
define("NC_SERVER", "localhost"); // server.domain
define("NC_USER", "deckbot");
define("NC_PASSWORD", "****");
define("MAIL_SERVER", "localhost"); // server.domain
define("MAIL_SERVER_FLAGS", "/novalidate-cert"); // flags needed to connect to server. Refer to https://www.php.net/manual/en/function.imap-open.php for a list of valid flags.
define("MAIL_SERVER_PORT", "143");
define("MAIL_USER", "incoming");
define("MAIL_PASSWORD", "****");
define("DECODE_SPECIAL_CHARACTERS", true); //requires mbstring, if false special characters (like öäüß) won't be displayed correctly
define("ASSIGN_SENDER", true); // if true, sender will be assigned to card if has NC account
?>
