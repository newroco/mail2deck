<?php

function decodeIfNeeded($data) {
    $descarray = explode(" ", $data);
    $wEncoded = 0;
    foreach($descarray as $word) {
        if(base64_encode(base64_decode($word)) == $word) {
            $wEncoded++;
        }
    }

    if($wEncoded >= count($descarray) - 1) {
        $data = base64_decode($data);
    }

    return $data;
}

?>