<?php

class DeckClass {
    protected function apiCall($request, $endpoint, $data){
        $curl = curl_init();

        $headers = [
            "OCS-APIRequest: true"
				];
				
				// set CURLOPTs commmon to all HTTP methods
				$options = [
					  CURLOPT_USERPWD => NC_USER . ":" . NC_PASSWORD,
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSLVERSION => "all",
				];

				// set HTTP request specific headers and options/data
				if ($request == '') {// an empty request value is used for attachments
					// add data without JSON encoding or JSON Content-Type header
					$options[CURLOPT_POST] = true;
					$options[CURLOPT_POSTFIELDS] = $data;
				} elseif ($request == "POST") {
					array_push($headers, "Content-Type: application/json");
					$options[CURLOPT_POST] = true;
					$options[CURLOPT_POSTFIELDS] = json_encode($data);
				}	elseif ($request == "GET") {
					array_push($headers, "Content-Type: application/json");
				}

				// add headers to options
				$options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        }

        return json_decode($response);
    }

    public function getParameters() {// get the board and the stack
        global $mailData;
        global $boardId;

        if(preg_match('/b-"([^"]+)"/', $mailData->mailSubject, $m) || preg_match("/b-'([^']+)'/", $mailData->mailSubject, $m)) {
            $boardFromMail = $m[1];
            $mailData->mailSubject = str_replace($m[0], '', $mailData->mailSubject);
        }
        if(preg_match('/s-"([^"]+)"/', $mailData->mailSubject, $m) || preg_match("/s-'([^']+)'/", $mailData->mailSubject, $m)) {
            $stackFromMail = $m[1];
            $mailData->mailSubject = str_replace($m[0], '', $mailData->mailSubject);
        }

        global $boardName;
        $boards = $this->apiCall("GET", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards", '');
        foreach($boards as $board) {
            if($board->title == $boardFromMail || $board->title == $boardName) {
                $boardId = $board->id;
            } else {
                echo "Board not found\n";
            }
        }

        $stacks = $this->apiCall("GET", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/$boardId/stacks", '');
        foreach($stacks as $stack) {
            if($stack->title == $stackFromMail) {
                global $stackId;
                $stackId = $stack->id;
            } else if (!is_numeric($stackId)) {
                global $stackId;
                $stackId = $stacks[0]->id;
            }
        }
    }

    public function addCard($data) {
        global $mailData;
        global $stackId;

        $data = new stdClass();
        $data->stackId = $stackId;
        $data->title = $mailData->mailSubject;
        $data->description =
"$mailData->mailMessage
***
### $mailData->from
";
        $data->type = "plain";
        $data->order = "-" . time(); // put the card to the top

        //create card
        $response = $this->apiCall("POST", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/1/stacks/1/cards", $data);
        global $cardId;
        $cardId = $response->id;
    }

    public function addAttachment($data) {
        global $mailData;
        global $cardId;
        $fullPath = 'D:/projects/Deck API'; // /var/www/nextcloud/apps/mailtodeck

        for ($i = 1; $i < count($mailData->fileAttached); $i++) {
            $data = array(
                'file' => new CURLFile("$fullPath/attachments/" . $mailData->fileAttached[$i])
              );
            $this->apiCall("", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/1/stacks/1/cards/$cardId/attachments?type=deck_file", $data);
        }
    }
}
?>
