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
            [
                "id" => "1",
                "userid" => "1",
                "name" => "user1",
                "email" => "email1",
                "roles" => ["admin", "hr"]
            ],
            [
                "id" => "2",
                "userid" => "2",
                "name" => "user2",
                "email" => "email2",
                "roles" => ["admin"]
            ]
        ]
    ]
);
