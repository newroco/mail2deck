# mail2deck
Provides an "email in" solution for the Nextcloud Deck app
## A. For users
Retains the following:
1) Email subject is the card title, and email body is the card description! <br/>
2) You can add even attachments to the email and they will be transposed as attachments of the card.<br/>
3) __mail2deck__ doesn't add stacks (for now), so you must to have at least one stack on the board that you want to add the cards.<br>

To have the feature as your email to be transformed in a Deck card you have to follow some easy steps.
### 1) Deck Bot needs to be assigned as a user of the board that you want to add the cards.
The card will be assigned to the Deck Bot. In case you aren't the board owner or you haven't rights to add users to the board, we consider that mail2deck for that board is disabled.
### 2) Send the email.
You have two posibilities to choose the board that you want to add the card:
#### 1) Set the board in the email subject
We interprate that is mail subject:
<code>mail subject b-'personalBoard'</code><br/>
You can use single or double quotes, and the word from inside the quotes has to be exactly the board name.
For the stack you can use:
<code>mail subject b-'personalBoard' s-'important stuff'</code>
or not, because the default stack is the left one.
#### 2) Set the board in the email address
For that, we have to introduce you the structure of the email address.<br/>
The email address is composed like:
<code>incoming+boardname@server.domain</code>, and string between the plus (**+**) and the at (**@**) needs to be exactly the board name that you want to add the card.

## B. For NextCloud admins to setup
### Requirements
This app requires cURL, imap and Postfix.
### NC new user
Create a new user from User Management on your NC server, which will have to function as a bot. We chose to call him *deckbot*, but you can call it however you want.<br>
__Note__: that you have to assign *deckbot* on each board you want to add new cards from email.
### Set up Postfix for incoming email
You can setup Posfix mail server folowing the instructions on [Posfix setup](https://docs.gitlab.com/ee/administration/reply_by_email_postfix_setup.html), and after that add "+" delimiter (which separe the user from the board in the email address) using the command:<br>
```
sudo postconf -e "recipient_delimiter = +"
```
### Download and install
Clone this repository into *incoming* user.<br>
```
cd /home/incoming/
git clone https://github.com/putt1ck/mail2deck.git mail2deck
```
Edit as you need the config file: 
```
sudo nano /home/incoming/mail2deck/config.php
```
### Add a cronjob which will run mail2deck.
```
sudo crontab -u incoming -e
```
Add the following line in the opened file:
<code>*/5 * * * * /usr/bin/php /home/incoming/mail2deck/index.php >/dev/null 2>&1</code>
### Finish
Now __mail2deck__ will add new cards every five minutes if new emails are received.
