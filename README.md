# mail2deck
Provides an "email in" solution for the Nextcloud Deck app
# 🚀 A. For users
Follow the above steps to add a new card from email.

* Deck Bot is the user who will create the cards and it will be set up by your nextcloud admin.
* In this tutorial email address for Deck Bot will be: <code>bot@ncserver.com</code>

## 1) Assign Deck Bot to the board.
Deck Bot must be assigned and must have edit permission inside the board.

## 2) Mail subject & content
Let's assume you want to add a card with title "Update website logo" on board "Website" and stack "To do".
You can do this in two ways.

### 2.1: Set stack and board in the email subject
Here's what the email subject should look like:
<code>Update website logo b-'website' s-'to do'</code>

* *You can use single or double quotes.*

* *Case-insensitive for board and stack respectively.*

### 2.2: Set the board in the email address
At the end of the email address prefix (before @) add "+website"

Example: <code>bot+website@ncserver.com</code>

* *If board has multiple words e.g. <code>"some project"</code>, you'll have to send the email to <code>bot+some+project@ncserver.com</code>*

In this case, if you don't specify the stack in the email subject, the card will be added in the first stack (if it exists).

Note:
* Email content will be card description
* You can add attachments in the email and those will be integrated in the created card


### 2.3: Specify assignee

Here's what the email subject should look like:

`Update website logo b-'website' s-'to do' u-'bob'`

* *You can use single or double quotes.*
* *Case-insensitive for board, stack and user respectively.*

### 2.4: Specify due date
You can use the optional parameter `d-` to add a due date to a card.
Here's what the email subject should look like if you want to set a due date to the card:

`Update website logo b-'website' s-'to do' u-'bob' d-'2022-08-22T19:29:30+00:00'`

* *You can use single or double quotes.*

# ⚙️ B. For NextCloud admins to setup
## Requirements
This app requires php-curl, php-mbstring ,php-imap and some sort of imap server (e.g. Postfix with Courier).
## NC new user
Create a new user from User Management on your NC server, which shall to function as a bot to post cards received as mail. We chose to call it *deckbot*, but you can call it whatever you want.<br>
__Note__: that you have to give *deckbot* permissions on each board you want to add new cards from email.
## Configure Email
### Option 1 - Set up Postfix for incoming email
You can setup Posfix mail server folowing the instructions on [Posfix setup](https://docs.gitlab.com/ee/administration/reply_by_email_postfix_setup.html), and after that add "+" delimiter (which separates the user from the board name in the email address) using the command:<br>
```
sudo postconf -e "recipient_delimiter = +"
```
### Option 2 - Use an existing email server
This could be any hosted email service. The only requirement is that you can connect to it via the IMAP protocol.
*Please note this option may not be as flexible as a self-hosted server. For example your email service may not support the "+"delimiter for directing messages to a specific board.*
## Download and install
### Bare-metal installation
If using a self-hosted Postfix server, clone this repository into the home directory of the *incoming* user. If not self-hosting, you may need to create a new user on your system and adjust the commands in future steps to match that username.<br>
```
su - incoming
git clone https://github.com/newroco/mail2deck.git mail2deck
```
Create config.php file and edit it for your needs: 
```
cd /home/incoming/mail2deck
cp config.example.php config.php
sudo vim config.php
```
*You can refer to https://www.php.net/manual/en/function.imap-open.php for setting the value of MAIL_SERVER_FLAGS*
#### Add a cronjob to run mail2deck.
```
sudo crontab -u incoming -e
```
Add the following line in the opened file (in this example, it runs every 5 minutes):
<code>*/5 * * * * /usr/bin/php /home/incoming/mail2deck/index.php >/dev/null 2>&1</code>

### Docker installation
Clone and edit the config.example.php you find in this repository and move it as config.php
```
git clone https://github.com/newroco/mail2deck.git mail2deck
cd mail2deck
cp config.example.php config.php
nano config.php
```

Build your image locally
```
docker build -t mail2deck:latest .
```

Run the docker image mapping the config.json as volume
```
docker run -d --name mail2deck mail2deck:latest
```

Edit your crontab
```
crontab -e
```

And add this line
```
*/5 * * * *  /usr/bin/docker start mail2deck
```

## Finish
Now __mail2deck__ will add new cards every five minutes if new emails are received.
