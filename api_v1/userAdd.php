<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
$id = rand(100,999);
echo json_encode(
    [
        "status"=>"success",
         "id"=>$id,
         "userid"=>"U". $id,
         "message"=>"this is test message"
    ]
);
 