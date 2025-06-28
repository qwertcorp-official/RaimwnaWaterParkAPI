<?php
define("ENV","DEV");
define("QC_PAY",
    ENV=="DEV"?
    [
        "RECHECK_URL"=>"https://qcpaydemo.qwertcorp.in/api/v1/transactions/clientRecheck",
        "PAYMENT_URL"=>"https://qcpaydemo.qwertcorp.in/page/makepayment?version=2",
        
        // "RECHECK_URL"=>"https://testpay.qwertcorp.com/api/v1/transactions/clientRecheck",
        // "RECHECK_URL"=>"https://testpay.qwertcorp.com/test.php",
        // "PAYMENT_URL"=>"https://testpay.qwertcorp.com/page/makepayment?version=2", 
        "CLIENTID"=> "8MKDLG2PE3",
        "HASH_KEY"=>"30ad81f8572e6d436060c8ad5c7c7f0b",
        "API_TOKEN"=>"eyJ0b2tlbiI6ImV6STVLREEzYVRCbGJESmlUekF3TURObGN6ZzFNek0yWVdOa1dUWTFkR1kyTVRJemRHRm1NRGxrVURFMFFtTmpNREpsV2pNeFRETmtPVE5pUkdGaU1UYzNTemt3T0RneFNUY3dSakl5VVRsak8yVTFPalE1S0RsaVRESmxPREU1IiwiY2xpZW50IjoicWNwYXktd2ViIn0"
    ]:
    [
        "RECHECK_URL"=>"https://qcpay.qwertcorp.co.in/transactions/clientRecheck?version=2",
        "PAYMENT_URL"=>"https://qcpay.qwertcorp.co.in/page/makepayment?version=2",
        "CLIENTID"=> "D1M5XZFC4G",
        "HASH_KEY"=>"6002a295862d34d790c514eda9a69709",
    ]
);