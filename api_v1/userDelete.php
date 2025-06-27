<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
// $id = rand(100,999);
$data = json_decode(file_get_contents("php://input"), true);
// print_r($data);
// die();
echo json_encode(
    [
        "status"=>"success",
         "id"=>$data["userid"], 
         "message"=>"this is test message"
    ]
);
 