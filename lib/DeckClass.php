<?php

class DeckClass {
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
            CURLOPT_POSTFIELDS => (array) $data,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . base64_encode(NC_USER . ':' . NC_PASSWORD),
                'OCS-APIRequest: true',
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        if($err) echo "cURL Error #:" . $err;

        return json_decode($response);
    }

    public function getParameters($params) {// get the board and the stack
        if(preg_match('/b-"([^"]+)"/', $params, $m) || preg_match("/b-'([^']+)'/", $params, $m)) {
            $boardFromMail = $m[1];
            $params = str_replace($m[0], '', $params);
        }
        if(preg_match('/s-"([^"]+)"/', $params, $m) || preg_match("/s-'([^']+)'/", $params, $m)) {
            $stackFromMail = $m[1];
            $params = str_replace($m[0], '', $params);
        }

        $boards = $this->apiCall("GET", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards");
        foreach($boards as $board) {
            if(strtolower($board->title) == strtolower($boardFromMail)) {
                $boardId = $board->id;
            }
        }

        if($boardId) {
            $stacks = $this->apiCall("GET", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/$boardId/stacks");
            foreach($stacks as $stack)
                (strtolower($stack->title) == strtolower($stackFromMail)) ? $stackId = $stack->id : $stackId = $stacks[0]->id;
        }

        $boardStack = new stdClass();
        $boardStack->board = $boardId;
        $boardStack->stack = $stackId;
        $boardStack->newTitle = $params;

        return $boardStack;
    }

    public function addCard($data) {
        $params = $this->getParameters($data->title);
        $data->title = $params->newTitle;
        $card = $this->apiCall("POST", NC_SERVER . "/index.php/apps/deck/api/v1.0/boards/{$params->board}/stacks/{$params->stack}/cards", $data);
        $card->board = $params->board;
        $card->stack = $params->stack;
        if($data->attachments) $this->addAttachments($card, $data->attachments);

        return $card;
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
}
?>
