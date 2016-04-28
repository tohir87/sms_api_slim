<?php

require_once "vendor/autoload.php";

function getDB() {
    $dbhost = "localhost";
//    $dbuser = "smshitne_tundsm"; //"root";
//    $dbpass = "smsTunde?#123_olajire"; //"root";
    $dbuser = "root";
    $dbpass = "root";
    $dbname = "smshitne_smshit";

    $mysql_conn_string = "mysql:host=$dbhost;dbname=$dbname";
    $dbConnection = new PDO($mysql_conn_string, $dbuser, $dbpass);
    $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbConnection;
}

function do_send($url) {
    $headers = array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.0.8) Gecko/20061025 Firefox/1.5.0.8");
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, 1); // Make sure GET method it used
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return the result
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
        $res = curl_exec($ch); // Run the request
    } catch (Exception $ex) {
        $res = 'NOK';
    }
    return $res;
}

function checkPhoneNumber($pn) {
    $pnum = '';
    if (substr($pn, 0, 3) !== '234' && (substr($pn, 0, 2) == trim('08') || substr($pn, 0, 2) == trim('07') || substr($pn, 0, 2) == trim('09'))) {
        if (substr($pn, 1, 11) != "") {
            $pnum = '234' . substr($pn, 1, 11);
        }
    } elseif (substr($pn, 0, 3) === '234' && strlen($pn) == 13) {
        $pnum = $pn;
    }

    return $pnum;
}

$app = new \Slim\Slim(array(
    'debug' => TRUE,
    'mode' => 'development'
        )
);

$app->get('/', function() use($app) {
    getDB();
    echo "Welcome to SMSHit API v1.0<br><br> "
    . "<b>Simple Documentation</b> <br>"
    . "Use <b><i>http://api.smshit.net/index.php/push_sms/:account_key/:message/:destination/:sender_id</i></b> to send SMS<br><br>"
    . " <b><u>Parameters</u></b><br>"
    . "<ul>"
    . "<li>account_key: - you account key on smshit"
    . "<li>message: - Message to be sent"
    . "<li>destination: - Receiver's phone number"
    . "<li>sender_id: - Sender ID"
    . "</ul>"
    . "<p style='color:red'><b>Note: All parameters must be url encoded</b></p>"
    . "Example: http://api.smshit.net/index.php/push_sms/a4f85b620e/Welcome%20to%20SMSHit/08037816587/Testing";
});

// add new Route 
$app->get("/", function () {
    echo "<h1>Hello Slim World</h1>";
});

// Push sms route
$app->get("/push_sms/:acct_key/:message/:recipient/:sname", function($acct_key, $message, $recipient, $senderId) {

    $app = \Slim\Slim::getInstance();
    $app->response()->header("Content-Type", "application/json");

    $username = "tundeaminu@gmail.com";
    $password = "olatunde2711";
    $gateway = "http://www.v2nmobile.co.uk/api/httpsms.php?";

    $phoneNumber = '';
    $splitNumbers = explode(":", $recipient);
    $recipientCount = count($splitNumbers);

    if ((int) $recipientCount > 1) {
        foreach ($splitNumbers as $phone) {
            $phoneNumber .= checkPhoneNumber($phone) . ":";
        }
        $phoneNumber = rtrim($phoneNumber, ":");
    } else {
        $phoneNumber = checkPhoneNumber($recipient);
    }


    try {
        $db = getDB();
        $sth = $db->prepare("SELECT * 
            FROM users
            WHERE actkey = :acct_key");

        $sth->bindParam(':acct_key', $acct_key, PDO::PARAM_STR);
        $sth->execute();

        $user = $sth->fetch(PDO::FETCH_OBJ);

        // Compute SMS count
        $sms_count = 1;
        $meslen = strlen($message);
        if ($meslen >160 && $meslen < 306 )
	  {
		  $sms_count = 2;
	  }
	  else if ($meslen >305 && $meslen < 458)
	  {
		 $sms_count = 3;
	  }
	  elseif($meslen >457 && $meslen < 611)
	  {
		  $sms_count = 4;
	  }
	  elseif($meslen >610 && $meslen < 751)
	  {
		  $sms_count = 5;
	  }
	  elseif($meslen >750 && $meslen < 891)
	  {
		  $sms_count = 6;
	  }
          
        $sms_count = $sms_count * $recipientCount;

        if ($user) {
            if ((int) $user->credit > $sms_count) {

                $status = 'Pending';
                $time = date('h:i:s');
                $ampm = date('A');
                $today_date = date('Y-m-d');
                $mid = rand(10, 20000);

                // build api url
                $url = $gateway . "u=" . urlencode($username)
                        . "&p=" . urlencode($password)
                        . "&m=" . urlencode($message)
                        . "&r=" . urlencode($phoneNumber)
                        . "&s=" . urlencode($senderId)
                        . "&t=1";

                $response = do_send($url);


                if ($response !== "NOK") {
                    $status = 'Delivered';
                    $respnse_str = json_encode(array(
                        "status" => true,
                        "message" => "Message sent successfully"
                    ));
                } else {

                    $respnse_str = json_encode(array(
                        "status" => false,
                        "message" => "Message in queue and will be sent shortly"
                    ));
                }


                // log inside outbox
                try {
                    $db = getDB();
                    if ((int) $recipientCount > 1) {
                        foreach ($splitNumbers as $phone) {
                            $stmt = $db->prepare("INSERT INTO smsoutbox (pnumber, sname, date, message, status, sid, smscount, country, time, ampm, mid) VALUES ($phone, '$senderId', '$today_date', '$message', '$status', $user->id, 1, 'NG', '$time', '$ampm', '$mid')");
                            $stmt->execute();
                        }
                    } else {
                        $stmt = $db->prepare("INSERT INTO smsoutbox (pnumber, sname, date, message, status, sid, smscount, country, time, ampm, mid) VALUES ($recipient, '$senderId', '$today_date', '$message', '$status', $user->id, 1, 'NG', '$time', '$ampm', '$mid')");
                        $stmt->execute();
                    }

                    // deduct SMS
                    $sql = $db->prepare("UPDATE users SET credit=credit-{$sms_count} WHERE id=:user_id");
                    $sql->bindParam(':user_id', $user->id, PDO::PARAM_INT);
                    $sql->execute();
                    
                } catch (Exception $ex) {
                    echo $ex->getMessage();
                    exit;
                }


                echo $respnse_str;
            } else {
                echo json_encode(array(
                    "status" => false,
                    "message" => "Insufficient credit"
                ));
            }
        } else {
            echo json_encode(array(
                "status" => false,
                "message" => "Invalid account key"
            ));
            //throw new PDOException('No records found.');
        }
    } catch (Exception $ex) {
        $app->response()->setStatus(404);
        echo '{"error":{"text":' . $ex->getMessage() . '}}';
    }
});

$app->run();
?>