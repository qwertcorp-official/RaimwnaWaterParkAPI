<?php
header('Content-Type: application/json'); 
echo json_encode(
    [
        "status" => "success",
        "messsage" => "success",
        "data" =>
        [
            [
                "id" => "1",
                "transactionid" => "t1D005",
                "paidby" => "Bb Test",
                "date" => "27-06-2025",
                "type" => "sale",
                "amount" => "500",
                "status" => "pending",
            ],
            [
                "id" => "2",
                "transactionid" => "t1D006",
                "paidby" => "Tester",
                "date" => "27-06-2025",
                "type" => "payment",
                "amount" => "600",
                "status" => "completed",
            ]
        ]
    ]
);
