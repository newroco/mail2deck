<?php

class DeckClass {
    protected function apiCall($request, $endpoint, $data){
        $curl = curl_init();

        $headers = [
            "OCS-APIRequest: true"
        ];
        if ($request !== '') {// adding attachments doesn't support Content-Type: application/json.
            array_push($headers, "Content-Type: application/json");
            $options = [
                CURLOPT_USERPWD => NC_USER . ":" . NC_PASSWORD,
                CURLOPT_URL => $endpoint,
                CURLOPT_CUSTOMREQUEST => $request,
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSLVERSION => "all",
            ];
        } else {
            $options = [
                CURLOPT_USERPWD => NC_USER . ":" . NC_PASSWORD,
                CURLOPT_URL => $endpoint,
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSLVERSION => "all",
            ];
        }
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
        $fullPath = getcwd() . "/attachments/"; //get full path to attachments dirctory

        for ($i = 1; $i < count($mailData->fileAttached); $i++) {
            $data = array(
                'file' => new CURLFile($fullPath . $mailData->fileAttached[$i])
              );
            $this->apiCall("", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/1/stacks/1/cards/$cardId/attachments?type=deck_file", $data);
        }
    }
}
?>
