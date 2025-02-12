<?php
define("NC_SERVER", "localhost"); // https://server.domain (without "https://" attachments will not be created)
define("NC_HOST", "host"); //server.domain
define("NC_USER", "deckbot");
define("NC_PASSWORD", "****"); // if your Nextcloud instance uses Two-Factor-Authentication, use generated token here instead of password.
define("NC_ADMIN_USER", "admin"); // to access the nextcloud users list you need an admin
define("NC_ADMIN_PASSWORD", "admin");
define("MAIL_SERVER", "localhost"); // server.domain
define("MAIL_SERVER_FLAGS", "/novalidate-cert"); // flags needed to connect to server. Refer to https://www.php.net/manual/en/function.imap-open.php for a list of valid flags.
define("MAIL_SERVER_PORT", "143");
define("MAIL_SERVER_SMTPPORT", "587"); // port for outgoing smtp server. Actually only used to configure Docker image outgoing SMTP Server
define("MAIL_USER", "incoming");
define("MAIL_PASSWORD", "****");
define("DECODE_SPECIAL_CHARACTERS", true); //requires mbstring, if false special characters (like öäüß) won't be displayed correctly
define("ASSIGN_SENDER", true); // if true, sender will be assigned to card if has NC account
define("MAIL_NOTIFICATION", true); // if true, send notifications when a new card was created or an error occured
define("DELETE_MAIL_AFTER_PROCESSING", true);
define("NC_BOARD",["DEFAULT_BOARD"=>"Support"]); //default Board to use in case it's not sent via email
define("MAIL_SUPPORT","");//If not empty, use this value to send to the Support team: card url, the description and who submitted the request.