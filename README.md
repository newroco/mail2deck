# mail2deck
Provides an "email in" solution for the Nextcloud Deck app
## A. For users
Retains that the email subject is the card title, and the email body is the card description! <br/>
Also, you can add even attachments to the email and they will be transposed as attachments of the card.<br/>
To have the feature as your email to be transformed in a Deck card you have to follow some easy steps.
### 1) Deck Bot needs to be assigned as a user of the board that you want to add the cards.
The card will be assigned to the Deck Bot. In case you aren't the board owner or you haven't rights to add users to the board, we consider that mail2deck for that board is disabled.
### 2) Send the email.
You have two posibilities to choose the board that you want to add the card:
#### 1) Set the board in the email subject
We interprate that is mail subject:
<code>mail subject b-'personalBoard'</code><br/>
You can use single or double quotes, and the word in quotes have to be exactly the board name.
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
### Set up Postfix for incoming email
You can setup Posfix mail server folowing the instructions on [Posfix setup](https://docs.gitlab.com/ee/administration/reply_by_email_postfix_setup.html) . <br>
After setup, add "+" delimiter (which separe the user from the board in the email address) using the command:<br>
```
sudo postconf -e "recipient_delimiter = +"
```

### Download and install
Clone this repository into 
### NC new user
Create a new user from User Management on your NC server, which will have to function as a bot.
For this tutorial we will use a user called "*deckbot*".
### 
