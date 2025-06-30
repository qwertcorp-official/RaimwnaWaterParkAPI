<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
echo json_encode(
    [
        "status" => "success",
        "messsage" => "success",
        "data" =>
        [
          "message"=>"this is test message recheck transaction"

        ]
    ]
);
