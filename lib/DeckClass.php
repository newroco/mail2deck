<?php

class DeckClass {
    private $responseCode;

    private function apiCall($request, $endpoint, $data = null, $attachment = false){
        $curl = curl_init();
        if($data && !$attachment) {
            $endpoint .= '?' . http_build_query($data);
        }
        curl_setopt_array($curl, array(
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $request,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . base64_encode(NC_USER . ':' . NC_PASSWORD),
                'OCS-APIRequest: true',
            ),
        ));

        if($request === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, (array) $data);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $this->responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        if($err) echo "cURL Error #:" . $err;

        return json_decode($response);
    }

    public function getParameters($params, $boardFromMail = null) {// get the board and the stack
	    if(!$boardFromMail) // if board is not set within the email address, look for board into email subject
        	if(preg_match('/b-"([^"]+)"/', $params, $m) || preg_match("/b-'([^']+)'/", $params, $m)) {
            		$boardFromMail = $m[1];
            		$params = str_replace($m[0], '', $params);
        	}
        if(preg_match('/s-"([^"]+)"/', $params, $m) || preg_match("/s-'([^']+)'/", $params, $m)) {
            $stackFromMail = $m[1];
            $params = str_replace($m[0], '', $params);
        }
        if(preg_match('/u-"([^"]+)"/', $params, $m) || preg_match("/u-'([^']+)'/", $params, $m)) {
            $userFromMail = $m[1];
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
            foreach($stacks as $key => $stack)
                if(strtolower($stack->title) == strtolower($stackFromMail)) {
                    $stackId = $stack->id;
                    break;
                }
                if($key == array_key_last($stacks) && !isset($stackId)) $stackId = $stacks[0]->id;
        } else {
            return false;
        }

        $boardStack = new stdClass();
        $boardStack->board = $boardId;
        $boardStack->stack = $stackId;
        $boardStack->newTitle = $params;
        $boardStack->boardTitle = $boardName;
        $boardStack->userId = $userFromMail;

        return $boardStack;
    }

    public function addCard($data, $user, $board = null) {
        $params = $this->getParameters($data->title, $board);

        if($params) {
            $data->title = $params->newTitle;
            $card = $this->apiCall("POST", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$params->board}/stacks/{$params->stack}/cards", $data);
            $card->board = $params->board;
            $card->stack = $params->stack;

            if ($params->userId !== null) $user->userId = $params->userId;

            if($this->responseCode == 200) {
                if(ASSIGN_SENDER || $params->userId !== null) $this->assignUser($card, $user);
                if($data->attachments) $this->addAttachments($card, $data->attachments);
                $card->boardTitle = $params->boardTitle;
            } else {
                return false;
            }
            return $card;
        }
        return false;
    }

    private function addAttachments($card, $attachments) {
        $fullPath = getcwd() . "/attachments/"; //get full path to attachments directory
        for ($i = 0; $i < count($attachments); $i++) {
            $file = $fullPath . $attachments[$i];
            $data = array(
                'file' => new CURLFile($file)
            );
            $this->apiCall("POST", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$card->board}/stacks/{$card->stack}/cards/{$card->id}/attachments?type=file", $data, true);
            unlink($file);
        }
    }

    public function assignUser($card, $mailUser)
    {
        $board = $this->apiCall("GET", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$card->board}");
        $boardUsers = array_map(function ($user) { return $user->uid; }, $board->users);

        foreach($boardUsers as $user) {
            if($user === $mailUser->userId) {
                $this->apiCall("PUT", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$card->board}/stacks/{$card->stack}/cards/{$card->id}/assignUser", $mailUser);
                break;
            }
        }
    }

    private function checkBotPermissions($board) {
        foreach($board->acl as $acl)
            if($acl->participant->uid == NC_USER && $acl->permissionEdit)
                return true;

        return false;
    }
}
?>
