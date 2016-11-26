<?php

// Remembered procedural programming ;)

$err = [];

define('HOST', 'localhost');
define('USER', 'root');
define('PASSWORD', '');
define('DATABASE', 'auslogics');

/**
 * Show errors on screen
 * @param $err
 */
function showError($err) {
  for ($i = 0; $i < count($err); $i++) {
    echo $err[$i] . '<br />';
  }
}

/**
 * Generates hash with or without salt.
 * @param $str
 * @param bool $salt
 * @return string
 */
function generateHashWithSalt($str, $salt = true) {
  if ($salt) $salt = dechex(time()) . md5(uniqid($str));
  return hash("sha256", $str . $salt);
}

/**
 * It returns the correct URL to access this script
 * @return string
 */
function request_url() {
  $result = '';
  $default_port = 80;

  if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) {
    $result .= 'https://';
    $default_port = 443;
  }
  else {
    $result .= 'http://';
  }
  $result .= $_SERVER['SERVER_NAME'];

  if ($_SERVER['SERVER_PORT'] != $default_port) {
    $result .= ':' . $_SERVER['SERVER_PORT'];
  }
  $result .= $_SERVER['REQUEST_URI'];
  return $result;
}

/**------------- The request for registration -------------**/

if (isset($_POST['submit-registration'])) {

  if (empty($_POST['phone'])) {
    $err[] = 'The field with the phone can not be empty!';
  }

  if (empty($_POST['email'])) {
    $err[] = 'The Email can not be empty!';
  }

  if (count($err) > 0) {
    showError($err);
  }
  else {
    $mysqli = mysqli_connect(HOST, USER, PASSWORD, DATABASE) or die('Ошибка ' . mysqli_error($mysqli));
    $stmt = $mysqli->prepare("SELECT email FROM phones WHERE email = ?");
    $stmt->bind_param('s', generateHashWithSalt($_POST['email'], false));
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
      $err[] = 'Email busy: ' . $_POST['email'];
    }
    else {
      $key = substr(generateHashWithSalt($_POST['email'], false), 0, 56);
      $td = mcrypt_module_open(MCRYPT_BLOWFISH, '', MCRYPT_MODE_CFB, '');
      $iv_size = mcrypt_enc_get_iv_size($td);
      $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
      mcrypt_generic_init($td, $key, $iv);
      $phone = mcrypt_generic($td, $_POST['phone']);
      mcrypt_generic_deinit($td);
      mcrypt_module_close($td);
      $phone = base64_encode($iv . $phone);
      $stmt = $mysqli->prepare("INSERT INTO phones (phone, email) VALUES (?, ?)");
      $stmt->bind_param('ss', $phone, generateHashWithSalt($_POST['email'], false));
      $stmt->execute();
      if ($stmt->affected_rows == 0 || $stmt->affected_rows < 0) {
        $err[] = 'Error writing to the database!';
      }
    }
    $stmt->close();

    if (count($err) > 0) {
      showError($err);
    }
    else {
      echo 'Data saved';
    }
  }
}

/**------------- Request for recovery -------------**/

if (isset($_POST['submit-recovery'])) {

  if (empty($_POST['email'])) {
    $err[] = 'The Email can not be empty!';
  }

  if (count($err) > 0) {
    showError($err);
  }
  else {

    $hash = generateHashWithSalt($_POST['email'], true);
    $date = new DateTime();
    $date->add(new DateInterval('PT1H'));
    $mysqli = mysqli_connect(HOST, USER, PASSWORD, DATABASE) or die('Ошибка ' . mysqli_error($mysqli));
    $sql = "INSERT INTO hashes (hash_sum, phone_id, expiration_date) VALUES (?,(SELECT id FROM phones WHERE email = ?),?)";
    $stmt = $mysqli->prepare($sql);
    //var_dump(generateHashWithSalt($_POST['email'],false));
    $stmt->bind_param('sss', $hash, generateHashWithSalt($_POST['email'], false), $date->format('Y-m-d H:i:s'));
    $stmt->execute();
    $insert_id = $mysqli->insert_id;
    $stmt->close();

    if ($insert_id === 0) {
      $err[] = 'No such email!';
    }

    if (count($err) > 0) {
      showError($err);
    }
    else {
      $lnk = request_url() . '?id=' . $insert_id . '&hash=' . $hash . '&email=' . $_POST['email'];
      $to = $_POST['email'];
      $subject = 'Request for recovery';
      $message = '<html>
                    <head>
                      <title>' . $subject . '</title>
                    </head>
                    <body>
                      <h3>You asked for my phone number.</h3>
                      <p>Please click on the link: <a href="' . $lnk . '">' . $lnk . '</a></p>
                    </body>
                  </html>';

      $headers = 'MIME-Version: 1.0' . "\r\n";
      $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
      $headers .= 'To: Client <' . $to . '>, Copy <example@example.com>' . "\r\n";

      if (!mail($to, $subject, $message, $headers)) {
        $err[] = 'Email is not gone!';
      }

      if (count($err) > 0) {
        showError($err);
      }
      else {
        echo 'In your email has been sent a link to restore the phone number. The link time - 1 hour<br />';
        //echo $lnk;
      }
    }
  }
}

/**------------- Sending the phone number -------------**/

if (isset($_GET['id']) && isset($_GET['hash']) && isset($_GET['email'])) {
  $mysqli = mysqli_connect(HOST, USER, PASSWORD, DATABASE) or die('Ошибка ' . mysqli_error($mysqli));
  $sql = "SELECT t1.phone, t2.expiration_date FROM phones AS t1 
          INNER JOIN hashes AS t2 ON t2.phone_id = t1.id
          WHERE t2.id = ? AND t2.hash_sum = ?;";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('ss', $_GET['id'], $_GET['hash']);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($phone, $expiration_date);
  if (!$stmt->num_rows > 0) {
    $err[] = 'No data!';
  }
  if (count($err) > 0) {
    showError($err);
  }
  else {
    $date = new DateTime();
    while ($stmt->fetch()) {
      $phone = base64_decode($phone);
      $td = mcrypt_module_open(MCRYPT_BLOWFISH, '', MCRYPT_MODE_CFB, '');
      $iv_size = mcrypt_enc_get_iv_size($td);
      $iv = substr($phone, 0, $iv_size);
      $crypt_text = substr($phone, $iv_size);
      $key = substr(generateHashWithSalt($_GET['email'], false), 0, 56);
      mcrypt_generic_init($td, $key, $iv);
      $phone = mdecrypt_generic($td, $crypt_text);
      mcrypt_generic_deinit($td);
      mcrypt_module_close($td);
      if (strtotime($date->format('Y-m-d H:i:s')) <= strtotime($expiration_date)) {
        $to = $_POST['email'];
        $subject = 'Phone number data';
        $message = '<html>
                    <head>
                      <title>' . $subject . '</title>
                    </head>
                    <body>
                      <h3>' . $subject . '</h3>
                      <p>Your phone number: ' . $phone . '</p>
                    </body>
                  </html>';

        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        $headers .= 'To: Client <' . $to . '>, Copy <example@example.com>' . "\r\n";

        if (!mail($to, $subject, $message, $headers)) {
          $err[] = 'Email is not gone!';
        }
        if (count($err) > 0) {
          showError($err);
        }
        else {
          echo 'In your email your phone number has been sent to</br>';
          //echo $phone;
        }
      }
      else {
        $err[] = 'Period has expired!';
        showError($err);
      }
    }
  }
  $stmt->close();
}