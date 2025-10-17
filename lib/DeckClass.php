<?php

namespace Mail2Deck;

class DeckClass {
    private $responseCode;

    private function apiCall($request, $endpoint, $data = null, $attachment = false, $userapi = false){
        $curl = curl_init();

        // POST and PUT
        if ($data) {
            $data = (array) $data;
            $jsonData = $attachment ? $data : json_encode($data, JSON_PRETTY_PRINT);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $request,
            CURLOPT_HTTPHEADER => $this->setRequestHeaders($curl, $attachment),
        ]);
        if ($request === 'POST' || $request === 'PUT') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        }

        $response = curl_exec($curl);
        $err = curl_error($curl);
        if($userapi) return simplexml_load_string($response);
        $this->responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        if($err) echo "cURL Error #:" . $err;

        return json_decode($response);
    }

    private function setRequestHeaders($curl, $attachment) {
        $headers = [
            'Authorization: Basic ' . base64_encode(NC_ADMIN_USER . ':' . NC_ADMIN_PASSWORD),
            'Accept: application/json',
            'OCS-APIRequest: true',
        ];

        if ($attachment) {
            $headers[] = 'Content-Type: multipart/form-data';
        } else {
            $headers[] = 'Content-Type: application/json';
        }
        return $headers;
    }

    public function getParameters($params, $boardFromMail = null, $mail_domain) {// get the board and the stack
	    if(!$boardFromMail) // if board is not set within the email address, look for board into email subject
        	if(preg_match('/b-"([^"]+)"/', $params, $m) || preg_match("/b-'([^']+)'/", $params, $m)) {
            		$boardFromMail = $m[1];
            		$params = str_replace($m[0], '', $params);
        	}else{
                $emailSenderDomain = '@'.strtolower($mail_domain);
                if($emailSenderDomain === SUBMITTER_ADDRESS["DOMAIN_OA"]){
                    $boardFromMail = NC_BOARD["OA_BOARD"];
                }else if($emailSenderDomain === SUBMITTER_ADDRESS["DOMAIN_CA"]){
                    $boardFromMail = NC_BOARD["CA_BOARD"];
                }else{
                    $boardFromMail = NC_BOARD["DEFAULT_BOARD"];
                }
            }
        if(preg_match('/s-"([^"]+)"/', $params, $m) || preg_match("/s-'([^']+)'/", $params, $m)) {
            $stackFromMail = $m[1];
            $params = str_replace($m[0], '', $params);
        }
        if(preg_match('/u-"([^"]+)"/', $params, $m) || preg_match("/u-'([^']+)'/", $params, $m)) {
            $userFromMail = $m[1];
            $params = str_replace($m[0], '', $params);
        }
        if(preg_match('/d-"([^"]+)"/', $params, $m) || preg_match("/d-'([^']+)'/", $params, $m)) {
            $duedateFromMail = $m[1];
            $params = str_replace($m[0], '', $params);
        }

        $boards = $this->apiCall("GET", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards");
        $boardId = $boardName = null;
        foreach($boards as $board) {
            if(strtolower($board->title) == strtolower($boardFromMail)) {
                if(!$this->checkBotPermissions($board)) {
                    return false;
                }
                $boardId = $board->id;
                $boardName = $board->title;
                break;
            }
        }

        if($boardId) {
            $stacks = $this->apiCall("GET", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/$boardId/stacks");
            if (!empty($stacks)) {
                usort($stacks, function ($a, $b) {
                    return $a->order <=> $b->order;
                });
                $stackId = $stacks[0]->id;
            }
        } else {
            return false;
        }

        $boardStack = new \stdClass();
        $boardStack->board = $boardId;
        $boardStack->stack = $stackId;
        $boardStack->newTitle = $params;
        $boardStack->boardTitle = $boardName;
        $boardStack->userId = strtolower($userFromMail);
        $boardStack->dueDate = $duedateFromMail;


        return $boardStack;
    }

    //Add a new card
    public function addCard($data, $user,$mail_domain, $board = null) {
        $params = $this->getParameters($data->title, $board, $mail_domain);
        if($params) {
            $data->title = $params->newTitle;
            $data->duedate = $params->dueDate;
            $card = $this->apiCall("POST", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$params->board}/stacks/{$params->stack}/cards",$data);
            $card->board = $params->board;
            $card->stack = $params->stack;
            if($this->responseCode == 200) {
                if(ASSIGN_SENDER || $user) $this->assignUser($card, $user);
                $card->boardTitle = $params->boardTitle;
                $card->description =  $data->description;
            }
            else {
                throw new \Exception("Failed to create card on board '{$params->boardTitle}'");
            }
            return $card;
        }
       throw new \Exception("Invalid board parameters or missing board in Nextcloud Deck.");
    }

    //Add a new attachment
    public function addAttachments($card, $attachments) {
        $fullPath = getcwd() . "/attachments/"; //get full path to attachments directory
        $imageUrls = [];
        for ($i = 0; $i < count($attachments); $i++) {
            $file = $fullPath . $attachments[$i];
            if (!file_exists($file)) {
                die("Eroare: Fișierul $file nu există!");
            }
            $data = array(
                'file' => new \CURLFile($file)
            );
            $response = $this->apiCall("POST", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$card->board}/stacks/{$card->stack}/cards/{$card->id}/attachments?type=file", $data, true);
            if (isset($response->extendedData->fileid)) {
                $imageUrls[] = [
                    'fileid' => $response->extendedData->fileid,
                    'filename' => $attachments[$i]
                ];
            }
            unlink($file);
        }
        return $imageUrls;
    }

    public function updateCardDescription($card, $imageUrls) {
        $newDescription = $card->description . "\n\n";
        //remove any HTML comments
        $newDescription = preg_replace('/<!--.*?-->/s', '', $newDescription);
        $matches = [];
        preg_match_all('/!\[.*?\]\(cid:[^)]+\)/', $newDescription, $matches);

        foreach ($imageUrls as $img) {
            $fileid = $img['fileid'];
            $filename = $img['filename'];
            $url = NC_SERVER . "/f/{$fileid}";

            $newDescription = preg_replace('/!\[.*?\]\(cid:[^)]+\)/', "[$filename]($url)", $newDescription);
        }

        $data = [
            'title' => $card->title,
            'description' => $newDescription,
            'type' => $card->type,
            'order' =>$card->order,
            'duedate'=>$card->duedate,
            'owner'=>$card->owner
        ];

        $this->apiCall("PUT",NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$card->board}/stacks/{$card->stack}/cards/{$card->id}",$data);
        return $newDescription;
    }

    //Assign a user to the card
    public function assignUser($card, $mailUser)
    {
        $adminUser = urlencode(NC_ADMIN_USER);
        $adminPassword = urlencode(NC_ADMIN_PASSWORD);

        $url = "https://{$adminUser}:{$adminPassword}@" . NC_HOST;
        $allUsers= $this->apiCall("GET",$url."/ocs/v1.php/cloud/users"); //nextcloud user list
        foreach ($allUsers->ocs->data->users as $userId) {
            $userDetails = $this->apiCall("GET",$url."/ocs/v1.php/cloud/users/{$userId}");//search in the nextcloud user list
            if (isset($userDetails->ocs->data->email) && $userDetails->ocs->data->email == $mailUser) {
                $this->apiCall("PUT", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$card->board}/stacks/{$card->stack}/cards/{$card->id}/assignUser", ['userId' => (string)$userId]);
                break;
            }
        }
    }

    private function checkBotPermissions($board) {
        if ($board->title === NC_BOARD["DEFAULT_BOARD"]) {
            return true;
        }

        foreach($board->acl as $acl) {
            if($acl->participant->uid == NC_ADMIN_USER && $acl->permissionEdit) {
                return true;
            }
        }
        return false;
    }

    public function getAllBoards() {
        return $this->apiCall("GET", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards");
    }
    public function getStacksByBoard($boardId) {
        return $this->apiCall("GET", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$boardId}/stacks");
    }
    public function getCardsByStack($stack) {
        return $stack->cards ?? [];
    }
    //Identify the card by email subject
    public function findCardBySubject($subject) {
        $boards = $this->getAllBoards();
        $cleanSubject = preg_replace("/\s[b|s|u]-'.*?'/", '', $subject);
        foreach ($boards as $board) {
            $stacks =$this->getStacksByBoard($board->id);
            foreach ($stacks as $stack) {
                $cards = $this->getCardsByStack($stack);
                foreach ($cards as $card) {
                    if (strtolower(trim($card->title)) == strtolower(trim($cleanSubject))) {
                        return [
                            'cardId' => $card->id,
                            'boardId' => $board->id
                        ];
                    }
                }
            }
        }
        return null;
    }

    //Add new comment
    public function addCommentToCard($result, $comment) {
        $cardId = $result['cardId'];
        $boardId = $result['boardId'];

        $url = NC_SERVER . "/ocs/v2.php/apps/deck/api/v1.0/cards/{$cardId}/comments";
        $data = [
            'comment' => $comment,
            'actorType' => 'users',
            'message' => $comment,
             'verb' => 'create',
        ];

        $response = $this->apiCall("POST", $url, $data);
        if ($response) {
            echo "Comment added successfully to card ID: $cardId on board ID: $boardId.";
            return true;
        } else {
            echo "Failed to add comment to card ID: $cardId.";
            return false;
        }
    }

    //Extract the initial message from all forwarded content
    public function extractForwardedContent($body) {
        $separator = "---------- Forwarded message ---------";
        $position = strpos($body, $separator);
        if ($position !== false) {
            $body = substr($body, 0, $position);
        }
        $body = trim($body);
        return $body;
    }

    //Extract the initial subject from the description
    public function findOriginalSubject($message) {
        preg_match_all('/\bSubject:\s*([^\n\r]*)/i', $message, $matches);
        if (!empty($matches[1])) {
            $subject = trim($matches[1][0]);
            $subject = preg_replace('/\s+To:.*$/', '', $subject);
            return $subject;
        }
        return null;
    }
}
