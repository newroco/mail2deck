# mail2deck
Provides an "email in" solution for the Nextcloud Deck app
# 🚀 A. For users
Follow the above steps to add a new card from email.

* Deck Bot is the user who will create the cards and it will be set up by your nextcloud admin.
* In this tutorial email address for Deck Bot will be: <code>bot@ncserver.com</code>

## 1) Assign Deck Bot to the board.
## 2) Mail subject & content
Let's assume you want to add a card with title "Update website logo" on board "Website" and stack "To do".
You can do this in two ways.

### 2.1: Set stack and board in the email subject
Here's how the email subject should look like:
<code>Update website logo b-'website' s-'to do'</code>

*You can use single or double quotes.*
*Case-insensitive for board and stack respectively*

### 2.2: Set the board in the email address
At the end of the email address prefix (before @) add "+website"

Example: <code>bot+website@ncserver.com</code>

In this case, if you don't specify the stack in the email subject, the card will be added in the first stack (if it exists).

Note:
* Email content will be card description
* You can add attachments in the email and those will be integrated in the created card

# ⚙️ B. For NextCloud admins to setup
## Requirements
This app requires php-curl, php-mbstring ,php-imap and some sort of imap server (e.g. Postfix with Courier).
## NC new user
Create a new user from User Management on your NC server, which will have to function as a bot. We chose to call him *deckbot*, but you can call it however you want.<br>
__Note__: that you have to assign *deckbot* on each board you want to add new cards from email.
## Configure Email
### Option 1 - Set up Postfix for incoming email
You can setup Posfix mail server folowing the instructions on [Posfix setup](https://docs.gitlab.com/ee/administration/reply_by_email_postfix_setup.html), and after that add "+" delimiter (which separe the user from the board in the email address) using the command:<br>
```
sudo postconf -e "recipient_delimiter = +"
```
### Option 2 - Use an existing email server
This could be any hosted email service. The only requirement is that you can connect to it via the IMAP protocol.
*Please note this option may not be as flexible as a self-hosted server. For example your email service may not support the "+"delimiter for directing messages to a specific board.*
## Download and install
If using a self-hosted Postfix server, clone this repository into the home directory of the *incoming* user. If not self-hosting, you may need to create a new user on your system and adjust the commands in future steps to match that username.<br>
```
cd /home/incoming/
git clone https://github.com/putt1ck/mail2deck.git mail2deck
```
Edit the config file as you need: 
```
sudo nano /home/incoming/mail2deck/config.php
```
*You can refer to https://www.php.net/manual/en/function.imap-open.php for setting the value of MAIL_SERVER_FLAGS*
## Add a cronjob which will run mail2deck.
```
sudo crontab -u incoming -e
```
Add the following line in the opened file:
<code>*/5 * * * * /usr/bin/php /home/incoming/mail2deck/index.php >/dev/null 2>&1</code>

## Finish
Now __mail2deck__ will add new cards every five minutes if new emails are received.
