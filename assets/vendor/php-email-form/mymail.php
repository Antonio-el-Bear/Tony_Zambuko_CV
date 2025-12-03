<?php
/**
 * PHP Email Form
 * Version: 3.6
 * Website: https://bootstrapmade.com/php-email-form/
 * Copyright: BootstrapMade.com
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (version_compare(phpversion(), '5.5.0', '<')) {
  die('PHP version 5.5.0 and up is required. Your PHP version is ' . phpversion());
}

class PHP_Email_Form {

  public $to = '';
  public $from_name = false;
  public $from_email = false;
  public $subject = false;
  public $mailer = false;
  public $smtp = false;
  public $message = '';

  public $content_type = 'text/html';
  public $charset = 'utf-8';
  public $ajax = false;

  public $options = [];
  public $cc = [];
  public $bcc = [];
  public $honeypot = '';
  public $recaptcha_secret_key = false;

  public $error_msg = array(
    'invalid_to_email' => 'Email to (receiving email address) is empty or invalid!',
    'invalid_from_name' => 'From Name is empty!',
    'invalid_from_email' => 'Email from: is empty or invalid!',
    'invalid_subject' => 'Subject is too short or empty!',
    'short' => 'is too short or empty!',
    'ajax_error' => 'Sorry, the request should be an Ajax POST',
    'invalid_attachment_extension' => 'File extension not allowed, please choose:',
    'invalid_attachment_size' => 'Max allowed attachment size is:'
  );

  private $error = false;
  private $attachments = [];

  public function __construct() {
    $this->mailer = "forms@" . @preg_replace('/^www\./','', $_SERVER['HTTP_HOST']);
  }

  public function add_message($content, $label = '', $length_check = false) {
    $message = filter_var($content, FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '<br>';
    if($length_check) {
      if(strlen($message) < $length_check + 4) {
        $this->error .= $label . ' ' . $this->error_msg['short'] . '<br>';
        return;
      }
    }
    $this->message .= !empty($label) ? '<strong>' . $label . ':</strong> ' . $message : $message;
  }

  public function option($name, $val) {
    $this->options[$name] = $val;
  }

  public function add_attachment($name, $max_size = 20, $allowed_extensions = ['jpeg','jpg','png','pdf','doc','docx']) {
    if(!empty($_FILES[$name]['name'])) {
      $file_extension = strtolower(pathinfo($_FILES[$name]['name'], PATHINFO_EXTENSION));
      if(!in_array($file_extension, $allowed_extensions)) {
        die('(' . $name . ') ' . $this->error_msg['invalid_attachment_extension'] . " ." . implode(", .", $allowed_extensions));
      }
  
      if($_FILES[$name]['size'] > (1024 * 1024 * $max_size)) {
        die('(' . $name . ') ' . $this->error_msg['invalid_attachment_size'] . " $max_size MB");
      }

      $this->attachments[] = [
        'path' => $_FILES[$name]['tmp_name'], 
        'name'=>  $_FILES[$name]['name']
      ];
    }
  }

  public function send() {

    if(!empty(trim($this->honeypot))) {
      return 'OK';
    }

    if($this->recaptcha_secret_key) {
      if(!isset($_POST['recaptcha-response'])) {
        return 'No reCaptcha response provided!';
      }

      $recaptcha_options = [
        'http' => [
          'header' => "Content-type: application/x-www-form-urlencoded\r\n",
          'method' => 'POST',
          'content' => http_build_query([
            'secret' => $this->recaptcha_secret_key,
            'response' => $_POST['recaptcha-response']
          ])
        ]
      ];

      $recaptcha_context = stream_context_create($recaptcha_options);
      $recaptcha_response = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $recaptcha_context);
      $recaptcha_response_keys = json_decode($recaptcha_response, true);

      if(!$recaptcha_response_keys['success']) {
        return 'Failed to validate the reCaptcha!';
      }
    }

    if($this->ajax) {
      if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        return $this->error_msg['ajax_error'];
      }
    }

    $to = filter_var($this->to, FILTER_VALIDATE_EMAIL);
    $from_name = filter_var($this->from_name, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $from_email = filter_var($this->from_email, FILTER_VALIDATE_EMAIL);
    $subject = filter_var($this->subject, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
    $message = nl2br($this->message);

    if(!$to) 
      $this->error .= $this->error_msg['invalid_to_email'] . '<br>';

    if(!$from_name) 
      $this->error .= $this->error_msg['invalid_from_name'] . '<br>';

    if(!$from_email) 
      $this->error .= $this->error_msg['invalid_from_email'] . '<br>';

    if(!$subject) 
      $this->error .= $this->error_msg['invalid_subject'] . '<br>';

    if(is_array($this->smtp)) {
      if(!isset($this->smtp['host'])) {
        $this->error .= 'SMTP host is empty!' . '<br>';
      }

      if(!isset($this->smtp['username'])) {
        $this->error .= 'SMTP username is empty!' . '<br>';
      }
      if(!isset($this->smtp['password'])) {
        $this->error .= 'SMTP password is empty!' . '<br>';
      }
    
      if(!isset($this->smtp['port'])) {
        $this->smtp['port'] = 587;
      }
      
      if(!isset($this->smtp['encryption'])) {
        $this->smtp['encryption'] = 'tls';
      }

      if(isset($this->smtp['mailer'])) {
        $this->mailer = $this->smtp['mailer'];
      } elseif(filter_var($this->smtp['username'], FILTER_VALIDATE_EMAIL)) {
        $this->mailer = $this->smtp['username'];
      }
    }

    if($this->error) {
      return $this->error;
    }

    // Initialize PHPMailer
    $mail = new PHPMailer(true);

    try {
      // Set timeout to 30 seconds
      $mail->Timeout = 30;

      // Check and set SMTP
      if(is_array($this->smtp)) {
        $mail->isSMTP();
        $mail->Host = $this->smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->smtp['username'];
        $mail->Password = $this->smtp['password'];
        $mail->Port = $this->smtp['port'];
        $mail->SMTPSecure = $this->smtp['encryption'];
      }

      // Headers
      $mail->CharSet = $this->charset;
      $mail->ContentType = $this->content_type;

      // Recipients
      $mail->setFrom($this->mailer, $from_name);
      $mail->addAddress($to);
      $mail->addReplyTo($from_email, $from_name);

      // cc
      if(count($this->cc) > 0)
