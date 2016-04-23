<?php

require_once "vendor/autoload.php";

function getDB() {
    $dbhost = "localhost";
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
    echo "Welcome to SMSHit API v1.0";
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

//    $senderId = $sname;
    $recipient = checkPhoneNumber($recipient);

    try {
        $db = getDB();
        $sth = $db->prepare("SELECT * 
            FROM users
            WHERE actkey = :acct_key");

        $sth->bindParam(':acct_key', $acct_key, PDO::PARAM_STR);
        $sth->execute();

        $user = $sth->fetch(PDO::FETCH_OBJ);

        if ($user) {
            if ((int) $user->credit > 0) {

                $status = 'Pending';
                $time = date('h:i:s');
                $ampm = date('A');
                $today_date = date('Y-m-d');

                // build api url
                $url = $gateway . "u=" . urlencode($username)
                        . "&p=" . urlencode($password)
                        . "&m=" . urlencode($message)
                        . "&r=" . urlencode($recipient)
                        . "&s=" . urlencode($senderId)
                        . "&t=1";

                //$response = do_send($url);
                $response = 'OK';


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
                $stmt = $db->prepare("INSERT INTO smsoutbox (pnumber, sname, date, message, status, sid, smscount, country, time, ampm, mid) VALUES ($recipient, '$senderId', $today_date, '$message', '$status', $user->id, 1, 'NG', '$time', '$ampm', null)");
                    $stmt->execute();
                    
                    // deduct SMS
                    //$sql = "UPDATE users SET credit=credit-1 WHERE id=:user_id";
                    $sql = $db->prepare("UPDATE users SET credit=credit-1 WHERE id=:user_id");
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
            throw new PDOException('No records found.');
        }
    } catch (Exception $ex) {
        $app->response()->setStatus(404);
        echo '{"error":{"text":' . $ex->getMessage() . '}}';
    }
});

$app->run();
?>