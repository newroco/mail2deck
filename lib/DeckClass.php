<?php

namespace Mail2Deck;

class DeckClass {
    private $responseCode;

    private function apiCall($request, $endpoint, $data = null, $attachment = false, $userapi = false){
        $curl = curl_init();
        $jsonData = null;

        // POST and PUT
        if ($data !== null) {
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
            CURLOPT_HTTPHEADER => $this->setRequestHeaders($attachment),
        ]);
        if (($request === 'POST' || $request === 'PUT') && $jsonData !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        }

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $this->responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false || $err) {
            throw new \RuntimeException("cURL request failed: " . ($err ?: 'Unknown transport error'));
        }

        if ($this->responseCode < 200 || $this->responseCode >= 300) {
            $message = "HTTP {$this->responseCode} returned by {$endpoint}";
            $decodedError = json_decode($response);

            if (json_last_error() === JSON_ERROR_NONE && is_object($decodedError)) {
                if (isset($decodedError->message) && is_string($decodedError->message)) {
                    $message .= ": {$decodedError->message}";
                } elseif (isset($decodedError->ocs->meta->message) && is_string($decodedError->ocs->meta->message)) {
                    $message .= ": {$decodedError->ocs->meta->message}";
                }
            } elseif (trim($response) !== '') {
                $message .= ": " . trim($response);
            }

            throw new \RuntimeException($message);
        }

        if ($userapi) {
            $trimmedResponse = trim($response);
            if ($trimmedResponse !== '') {
                $jsonResponse = json_decode($trimmedResponse);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $jsonResponse;
                }
            }

            $xmlResponse = simplexml_load_string($trimmedResponse);
            if ($xmlResponse !== false) {
                return $xmlResponse;
            }

            throw new \RuntimeException("Failed to decode OCS response from {$endpoint}");
        }

        if (trim($response) === '') {
            return null;
        }

        $decodedResponse = json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to decode JSON response from {$endpoint}: " . json_last_error_msg());
        }

        return $decodedResponse;
    }

    private function setRequestHeaders($attachment) {
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
        $userFromMail = null;
        $duedateFromMail = null;
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
                if (isset($stackFromMail)) {
                    foreach ($stacks as $stack) {
                        if (strcasecmp($stack->title, $stackFromMail) === 0) {
                            $stackId = $stack->id;
                            break;
                        }
                    }
                }
            }
        } else {
            return false;
        }

        $boardStack = new \stdClass();
        $boardStack->board = $boardId;
        $boardStack->stack = $stackId;
        $boardStack->newTitle = $params;
        $boardStack->boardTitle = $boardName;
        $boardStack->userId = $userFromMail !== null ? strtolower($userFromMail) : null;
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
            $createResponseCode = $this->responseCode;
            if ($createResponseCode >= 200 && $createResponseCode < 300) {
                if (ASSIGN_SENDER && $user) {
                    $this->assignUser($card, $user);
                }
                $card->boardTitle = $params->boardTitle;
                $card->description = $data->description;
            } else {
                throw new \Exception("Failed to create card on board '{$params->boardTitle}'");
            }
            return $card;
        }
       throw new \Exception("Invalid board parameters or missing board in Nextcloud Deck.");
    }

    //Add a new attachment
    public function addAttachments($card, $attachments) {
        $imageUrls = [];
        for ($i = 0; $i < count($attachments); $i++) {
            $attachment = $attachments[$i];
            $file = is_array($attachment) ? $attachment['path'] : getcwd() . "/attachments/" . $attachment;
            $displayName = is_array($attachment) ? $attachment['name'] : $attachment;

            if (!is_file($file)) {
                throw new \RuntimeException("Attachment file does not exist: $file");
            }
            $data = array(
                'file' => new \CURLFile($file, mime_content_type($file) ?: 'application/octet-stream', $displayName)
            );
            $response = $this->apiCall("POST", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$card->board}/stacks/{$card->stack}/cards/{$card->id}/attachments?type=file", $data, true);
            if (isset($response->extendedData->fileid)) {
                $imageUrls[] = [
                    'fileid' => $response->extendedData->fileid,
                    'filename' => $displayName,
                    'cid' => $attachment['cid'] ?? null,
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

        foreach ($imageUrls as $img) {
            if (!isset($img['cid']) || $img['cid'] === '') {
                continue;
            }

            $fileid = $img['fileid'];
            $filename = $img['filename'];
            $url = NC_SERVER . "/f/{$fileid}";
            $cidPattern = '/!\[[^\]]*\]\(cid:' . preg_quote($img['cid'], '/') . '\)/';

            $newDescription = preg_replace($cidPattern, "[$filename]($url)", $newDescription, 1);
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
        $board = $this->getBoardById($card->board);
        if ($board === null) {
            return;
        }

        $userId = $this->resolveUserIdByEmail($mailUser, $board);
        if ($userId === null) {
            return;
        }

        $this->apiCall("PUT", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$card->board}/stacks/{$card->stack}/cards/{$card->id}/assignUser", ['userId' => (string) $userId]);
    }

    private function resolveUserIdByEmail($mailUser, $board = null)
    {
        $mailUser = strtolower(trim((string) $mailUser));
        if ($mailUser === '') {
            return null;
        }

        $boardUserIds = $board !== null ? $this->getAssignableUserIdsForBoard($board) : null;

        $cachedUserId = $this->lookupUserIdFromCache($mailUser);
        if ($cachedUserId !== null && ($boardUserIds === null || in_array($cachedUserId, $boardUserIds, true))) {
            return $cachedUserId;
        }

        $searchResults = $this->apiCall("GET", NC_SERVER . "/ocs/v1.php/cloud/users?search=" . rawurlencode($mailUser), null, false, true);
        foreach (($searchResults->ocs->data->users ?? []) as $userId) {
            $userId = (string) $userId;
            if ($boardUserIds !== null && !in_array($userId, $boardUserIds, true)) {
                continue;
            }

            $userDetails = $this->apiCall("GET", NC_SERVER . "/ocs/v1.php/cloud/users/{$userId}", null, false, true);
            if (isset($userDetails->ocs->data->email) && strtolower(trim((string) $userDetails->ocs->data->email)) === $mailUser) {
                $this->storeUserEmailCache($mailUser, $userId);
                return $userId;
            }
        }

        return null;
    }

    private function getBoardById($boardId)
    {
        foreach ($this->getAllBoards() as $board) {
            if ((string) $board->id === (string) $boardId) {
                return $board;
            }
        }

        return null;
    }

    private function getAssignableUserIdsForBoard($board)
    {
        $boardDetails = $this->apiCall("GET", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$board->id}");
        $userIds = array_map(function ($user) { return (string) $user->uid; }, $boardDetails->users);

        return array_values(array_unique($userIds));
    }

    private function lookupUserIdFromCache($mailUser)
    {
        $cache = $this->loadUserEmailCache();
        return $cache[$mailUser] ?? null;
    }

    private function storeUserEmailCache($mailUser, $userId)
    {
        $cache = $this->loadUserEmailCache();
        $cache[$mailUser] = $userId;
        $this->saveUserEmailCache($cache);
    }

    private function loadUserEmailCache()
    {
        $cacheFile = __DIR__ . '/../var/user-email-cache.json';
        if (!is_file($cacheFile)) {
            return [];
        }

        $contents = file_get_contents($cacheFile);
        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function saveUserEmailCache(array $cache)
    {
        $cacheFile = __DIR__ . '/../var/user-email-cache.json';
        $directory = dirname($cacheFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
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
        $cleanSubject = preg_replace('/\s+[bsud]-([\'\"]).*?\1/i', '', $subject);
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
