<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require ("PHPMailer/src/PHPMailer.php");
require ("PHPMailer/src/SMTP.php");
require ("PHPMailer/src/Exception.php");


//Edit below values with your own values.
$db_server 			= "localhost"; // Usually "localhost"		                                      
$db_name 			  = "dbname"; // Your database name
$db_user 			  = "dbuser"; // Your database user name
$db_pass 			  = "dbpassword"; // Your database user password
$site_url			  = "https://www.royaltechbd.com"; // Site url
$email_host     = "smtp.gmail.com"; //email sending domain
$smtp_username  = "info@royaltechbd.com";
$smtp_pass      = "emailpassword";
$from_email 		= "backup@royaltechbd.com";
$from_name 			= "Royal Technologies Backup";
$mail_to1  			= "info@royaltechbd.com"; // To which email db get sent.
$mail_to1_name  = 'Royal Technologies'; // To whom email get sent
$mail_to2       = ''; // Optional
$mail_to2_name  = ''; // Optional
$save_dir			      = ''; // Will be saved temporary file in same folder where this script is kept. keep empty for same folder where this file is..
$file_name_prefix	  = 'royaltech_billing_'; //prefix name to your backed up db file.. e.g. prefix_name_2022_06_14_19_31_00.sql.gz
$time_zone          = "Asia/Dhaka";
$compression        = true; // true or false. True for sql.gz (small file size of bkup) and false for .sql (bigger file size of bkup)
$date 				      = date('d_F_Y_H_i_s');
$delete_bkupfile_after_email  	= "Yes"; // Yes or No

/*
---- Do NOT EDIT BELOW -----
*/
$backup_config = array(
    'DB_HOST'       => $db_server,
    'DB_NAME'       => $db_name,
    'DB_USERNAME'   => $db_user,
    'DB_PASSWORD'   => $db_pass,
    'INCLUDE_DROP_TABLE' => false,
    'SAVE_DIR'      => $save_dir	,
    'SAVE_AS'       => $file_name_prefix,
    'APPEND_DATE_FORMAT' => 'Y_m_d_H_i_s',
    'TIMEZONE'      => ''.$time_zone.'',
    'COMPRESS'      => $compression,
);

$backup_db =  backupDB($backup_config);

if($backup_db){
    $files = array_merge(glob("./*.sql.gz"), glob("./*.sql"));
    $files = array_combine($files, array_map("filemtime", $files));
    arsort($files);
    $newest_file = key($files);
		$mail = new PHPMailer(true);		
		$mail->IsHTML(true); 
		$mail->Host = $email_host; // SMTP server
		$mail->AddAddress($mail_to1, $mail_to1_name);
        	if(!empty($mail_to2)){
          		$mail->AddAddress($mail_to2, $mail_to2_name);
        	}			
		$mail->IsSMTP();
		$mail->SMTPAuth = true;
		$mail->SMTPSecure = 'tls'; // tls or ssl
		$mail->Mailer = "smtp";
		$mail->SMTPDebug = false;
		$mail->Username = $smtp_username;
		$mail->Password = $smtp_pass;
		$mail->Port = 587;  // set the SMTP port , 587 if tls used, 465 if ssl used.
		$mail->AddReplyTo($from_email, $from_name);
		$mail->AddCustomHeader( "X-Confirm-Reading-To:".$from_email."" );
		$mail->AddCustomHeader( "Return-Receipt-To:".$from_email."" );
		$mail->ConfirmReadingTo = $from_email;
		$mail->From = $from_email; // Your Full Email ID on your Domain
		$mail->FromName = $from_name; // Your name or Domain
		$mail->WordWrap = 50; 
		$mail->Subject = $from_name.' billing MySQL backup on - ' . $date;
		$mail->Body    = $backup_db.' file is attached via cron';
		   if (!$mail->AddAttachment($newest_file)) {   
			  echo 'Erreur : ' . $mail->ErrorInfo . "\n";
			  $mail->Body .= "\n" . 'Erreur : ' . $mail->ErrorInfo;
		   }
		   if (!$mail->Send()){
			  echo 'Message could not be sent. <p>';
			  echo 'Mailer Error: ' . $mail->ErrorInfo;
			  exit;
		   }
		  echo 'Message has been sent';
          	if($delete_bkupfile_after_email=='Yes'){
		    unlink($newest_file); 
		}		
}

function backupDB(array $config): string
{
    $db = new PDO("mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']}; charset=utf8", $config['DB_USERNAME'], $config['DB_PASSWORD']);
    $db->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);

    date_default_timezone_set($config['TIMEZONE']);
    $do_compress = $config['COMPRESS'];

    if ($do_compress) {
        $save_string = $config['SAVE_AS'] . $config['SAVE_DIR'] . date($config['APPEND_DATE_FORMAT']) . '.sql.gz';
        $zp = gzopen($save_string, "a9");
    } else {
        $save_string = $config['SAVE_AS'] . $config['SAVE_DIR'] . date($config['APPEND_DATE_FORMAT']) . '.sql';
        $handle = fopen($save_string, 'a+');
    }

    //array of all database field types which just take numbers
    $numtypes = array('tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double', 'decimal', 'real');

    $return = "";
    $return .= "CREATE DATABASE `{$config['DB_NAME']}`;\n";
    $return .= "USE `{$config['DB_NAME']}`;\n";

    //get all tables
    $pstm1 = $db->query('SHOW TABLES');
    while ($row = $pstm1->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    //cycle through the table(s)
    foreach ($tables as $table) {
        $result = $db->query("SELECT * FROM $table");
        $num_fields = $result->columnCount();
        $num_rows = $result->rowCount();

        if ($config['INCLUDE_DROP_TABLE']) {
            $return .= 'DROP TABLE IF EXISTS `' . $table . '`;';
        }

        //table structure
        $pstm2 = $db->query("SHOW CREATE TABLE $table");
        $row2 = $pstm2->fetch(PDO::FETCH_NUM);
        $ifnotexists = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $row2[1]);
        $return .= "\n\n" . $ifnotexists . ";\n\n";

        if ($do_compress) {
            gzwrite($zp, $return);
        } else {
            fwrite($handle, $return);
        }
        $return = "";

        //insert values
        if ($num_rows) {
            $return = 'INSERT INTO `' . $table . '` (';
            $pstm3 = $db->query("SHOW COLUMNS FROM $table");
            $count = 0;
            $type = array();

            while ($rows = $pstm3->fetch(PDO::FETCH_NUM)) {
                if (stripos($rows[1], '(')) {
                    $type[$table][] = stristr($rows[1], '(', true);
                } else {
                    $type[$table][] = $rows[1];
                }

                $return .= "`" . $rows[0] . "`";
                $count++;
                if ($count < ($pstm3->rowCount())) {
                    $return .= ", ";
                }
            }

            $return .= ")" . ' VALUES';

            if ($do_compress) {
                gzwrite($zp, $return);
            } else {
                fwrite($handle, $return);
            }
            $return = "";
        }
        $counter = 0;
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $return = "\n\t(";

            for ($j = 0; $j < $num_fields; $j++) {

                if (isset($row[$j])) {

                    //if number, take away "". else leave as string
                    if ((in_array($type[$table][$j], $numtypes)) && (!empty($row[$j]))) {
                        $return .= $row[$j];
                    } else {
                        $return .= $db->quote($row[$j]);
                    }
                } else {
                    $return .= 'NULL';
                }
                if ($j < ($num_fields - 1)) {
                    $return .= ',';
                }
            }
            $counter++;
            if ($counter < ($result->rowCount())) {
                $return .= "),";
            } else {
                $return .= ");";
            }
            if ($do_compress) {
                gzwrite($zp, $return);
            } else {
                fwrite($handle, $return);
            }
            $return = "";
        }
        $return = "\n\n-- ------------------------------------------------ \n\n";
        if ($do_compress) {
            gzwrite($zp, $return);
        } else {
            fwrite($handle, $return);
        }
        $return = "";
   
	
	}

    $error1 = $pstm2->errorInfo();
    $error2 = $pstm3->errorInfo();
    $error3 = $result->errorInfo();
    echo $error1[2];
    echo $error2[2];
    echo $error3[2];

    if ($do_compress) {
        gzclose($zp);
    } else {
        fclose($handle);
    }
   
	return "{$config['DB_NAME']} saved as $save_string";
}


?>
