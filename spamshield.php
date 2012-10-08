#!/usr/bin/php
#
# SpamShield Postfix Queue monitoring script
#
# Copyright 2012, Panagiotis Papadomitsos
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#
<?php

// Script setup

ini_set('max_execution_time', '1200');
ini_set('max_input_time', '1200');
ini_set('memory_limit', '512M');
declare(ticks = 1);

if (PHP_SAPI !== 'cli')
	trigger_error('The script should run exclusively from the CLI', E_USER_ERROR);

require 'phpmailer/class.phpmailer.php';
require 'phpmailer/class.smtp.php';

error_reporting(E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_ERROR | E_CORE_ERROR);
$mailqfile = tempnam('/tmp', 'spamshield');
$senderoverquota = false;

function interruptHandler($signal) {
	global $mailqfile;
	unlink($mailqfile);
	exit(0);
}
pcntl_signal(SIGTERM, 'interruptHandler');

// Responsible Persons Setup

$adminemails = array( 
	// Insert here an array of the e-mails that you need to receive queue notifications
);

// Limits setup

define('TOTAL_MAILS', 1000);
define('TOTAL_FILES', 1000);
define('MAILS_PER_SENDER', 800);
define('MAILS_PER_SCRIPT', 500);
define('TOTALRCPTS_PER_SCRIPT', 500);

// Constants setup

define('SPOOLDIR', '/var/spool/postfix');
define('HOST', exec('hostname -f'));
define('DATER', date('r'));

// PHP Mailer Setup

$mailhost = ''; // Insert here the mail host that will be used to notify the administrators defined above of issues
$mailuser = ''; // Insert here the mail host username
$mailpass = ''; // Insert here the mail host password
$mailport = '587'; // Change to 25 for simple SMTP or 465 for SSL/TLS
$mailfrom = ''; // Set here the envelope from of the notification mail
$mailname = 'Operations'; // Set here the envelope from name of the notification mail
$mailsubj = 'SpamShield report for server '.HOST; // Set here the mail subject you want on notifications

$mail = new PHPMailer(true);
$mail->IsSMTP();
$mail->SMTPDebug 	= 0;		 // enables SMTP debug information (for testing)
$mail->SMTPAuth 	= true;	  // enable SMTP authentication
$mail->Host 		= $mailhost; // sets the SMTP server
$mail->Port         = $mailport; // set the SMTP port for the GMAIL server
$mail->Username   	= $mailuser; // SMTP account username
$mail->Password 	= $mailpass; // SMTP account password
$mail->CharSet 		= 'utf-8';
$mail->SetFrom($mailfrom, $mailname);
$mail->Subject = $mailsubj;

$empty = array();

// Check for older instance
exec('pgrep -f spamshield.php | wc -l', $instances, $exitstatus);
if ($instances[0] > 1) {
	echo 'Already running on another instance. Aborting...',"\n";
	exit(1);
}
unset($empty);

exec('postqueue -p > '.$mailqfile, $empty, $exitstatus);
if ($exitstatus > 0) {
	echo 'Could not execute the mail queue reader. Aborting...',"\n";
	unlink($mailqfile);
	exit(1);
}
unset($empty);

// Read the file and process statistics
$fh = fopen($mailqfile, 'r');
if ($fh === false) {
	echo 'Could not read the mail queue statistics. Aborting...',"\n";
	unlink($mailqfile);
	exit(1);
}

$mails = array(
	'total' => 0,
	'files' => array(),
	'active' => 0,
	'deferred' => 0,
	'hold' => 0,
);

$senders = array(); // Holds the senders array;
$scripts = array(); // Holds the script array

$in_mail = false;

while(($line = fgets($fh)) !== false) {
	if (preg_match('/^[0-9A-Z]+[\*\!]?/ ', $line) > 0) {
		$in_mail = true;
		
		// This is a queue file line, process the data		
		$fields = preg_split('/[\s]+/', $line);
		$mail_from = $fields[6];
		$queue_file = $fields[0];
		$queue_dir = substr($queue_file,0,1);
		switch(substr($queue_file,-1)) {
			case '*':
				$queue_state = 'active';
				$queue_file = substr($queue_file,0, strlen($queue_file) - 2); // Remove the * from the file
				$mails['active']++;
				break;
			case '!':
				$queue_state = 'hold';
				$queue_file = substr($queue_file,0, strlen($queue_file) - 2); // Remove the ! from the file
				$mails['hold']++;
				break;
			default:
				$queue_state = 'deferred';
				$mails['deferred']++;
				break;
		}
				
		$mails['files'][] = $queue_state.'/'.$queue_dir.'/'.$queue_file;
		
		if ($queue_state !== 'hold') {
		
			$senders[$mail_from]['files'][] = $queue_state.'/'.$queue_dir.'/'.$queue_file;
		
			// PHP script info				
			$scriptline = exec('postcat '.SPOOLDIR.'/'.$queue_state.'/'.$queue_dir.'/'.$queue_file.' 2> /dev/null | grep \'X-PHP-Script\'');
			if (empty($scriptline) === false) {
				$scriptdata = preg_split('/[\s]+/', $scriptline);
				$scripts[$scriptdata[1]]['ips'][] = $scriptdata[3];
				$scripts[$scriptdata[1]]['senders'][] = $mail_from;
				$scripts[$scriptdata[1]]['files'][] = $queue_state.'/'.$queue_dir.'/'.$queue_file;
				if ((isset($scripts[$scriptdata[1]]['recipients']) === false) || (is_array($scripts[$scriptdata[1]]['recipients']) === false))
					$scripts[$scriptdata[1]]['recipients'] = array();
			}
		}	
		continue;
	} elseif (($in_mail === true) && (preg_match('/^[\s]+[0-9a-zA-Z_\-\.]+@[0-9a-zA-Z_\-\.]+[\s]+/', $line) > 0)) {
		$mails['total']++;
		// This is a valid mail from entry and not a message on hold		
		if ($queue_state !== 'hold')
			$senders[$mail_from]['total']++;	
			
		if (isset($scriptdata[1]) === true)
			$scripts[$scriptdata[1]]['recipients'][] = trim($line);
		continue;
	}
	
	$doubleline = trim($line);
	if (($in_mail === true) && (empty($doubleline) === true)) {
		$in_mail = false; // This is the end of the mail queue entry	
		unset($mail_from);
		unset($queue_file);
		unset($queue_dir);
		unset($queue_state);
		unset($scriptline);
		unset($scriptdata);
	}
}
fclose($fh);

function report($senders, $scripts, $mails) {
	
	ob_clean();
	ob_start();
	echo 'SpamShield report for server ',HOST,' run on ',DATER,"\n\n";
	
	if ($mails['total'] >= TOTAL_MAILS)
		echo '[WARNING] The mail queue is over the limit on the total number of recipients: ', $mails['total'],"\n";
		
	if (count($mails['files']) >= TOTAL_FILES)
		echo '[WARNING] The mail queue is over the limit on the total number of files : ', count($mails['files']),"\n";		
	echo "\n";
	
	foreach($senders as $sender => $data) {
		if ($data['total'] >= MAILS_PER_SENDER)
			echo '[WARNING] Sender ',$sender,' is over the limit on the total number of recipients: ',$data['total'],"\n";		
	}
	
	echo "\n";	
	foreach($scripts as $script => $data) {
		if (count($data['files']) >= MAILS_PER_SCRIPT)
			echo '[WARNING] Script ',$script,' is over the limit on mails in the queue: ',count($data['files']),"\n";
		if (count($data['recipients']) >= TOTALRCPTS_PER_SCRIPT)
			echo '[WARNING] Script ',$script,' is over the limit on the total recipients: ', count($data['recipients']),"\n";
	}
	echo "\n";	

	echo ':: Queue Statistics ::',"\n",
		 ':::: Total number of recipients in queue:   ',$mails['total'],"\n",
		 ':::: Total number of mails in queue:		',count($mails['files']),"\n",
		 ':::: Number of mails in ACTIVE queue:	   ',$mails['active'],"\n",
		 ':::: Number of mails in DEFERRED queue:	 ',$mails['deferred'],"\n",
		 ':::: Number of mails in HOLD queue:		 ',$mails['hold'],"\n";
		 
		echo ob_get_clean();
}

function reportHTML($senders, $scripts, $mails) {
	
	global $senderoverquota;

	// Total stats
	
	if ($mails['total'] >= TOTAL_MAILS)
		$mails['total'] = '<span class="red_font">'.$mails['total'].'</span>';
				
	if (count($mails['files']) >= TOTAL_FILES)
		$mails['files'] = '<span class="red_font">'.count($mails['files']).'</span>';	
	else
		$mails['files'] = count($mails['files']);

	$mailstats  = '<tr><td style="text-align:left">Total number of recipients</td><td>'.$mails['total'].'</td></tr>';
	$mailstats .= '<tr><td style="text-align:left">Total number of mails</td><td>'.$mails['files'].'</td></tr>';
	$mailstats .= '<tr><td style="text-align:left">Number of mails in <strong>ACTIVE</strong> queue</td><td>'.$mails['active'].'</td></tr>';
	$mailstats .= '<tr><td style="text-align:left">Number of mails in <strong>DEFERRED</strong> queue</td><td>'.$mails['deferred'].'</td></tr>';
	$mailstats .= '<tr><td style="text-align:left">Number of mails in <strong>HOLD</strong> queue</td><td>'.$mails['hold'].'</td></tr>';
		
	// Sender Stats	
	
	$senderstats = '';
	foreach($senders as $sender => $data) {
		if ($data['total'] >= MAILS_PER_SENDER) {
			$senderstats .= '<tr><td style="text-align:left">'.$sender.'</td><td><span class="red_font">'.$data['total'].'</td></tr>';
			$senderoverquota = true;
		}
	}
	
	if (empty($senderstats) === true)
		$senderstats = '<tr><td colspan="3"><strong>None</strong></td></tr>';
	
	// Script stats
	
	$scriptstats = '';	
	foreach($scripts as $script => $data) {
		$scriptfilecount = count($data['files']);
		$scriptreccount = count($data['recipients']);
		if (($scriptfilecount >= MAILS_PER_SCRIPT) || ($scriptreccount >= TOTALRCPTS_PER_SCRIPT)) {
			if ($scriptfilecount >= MAILS_PER_SCRIPT)
				$scriptfilecount = '<span class="red_font">'.$scriptfilecount.'</span>';

			if ($scriptreccount >= TOTALRCPTS_PER_SCRIPT)
				$scriptreccount = '<span class="red_font">'.$scriptreccount.'</span>';
				
			$scriptstats .= '<tr><td style="text-align:left">'.$script.'</td><td>'.$scriptfilecount.'</td><td>'.$scriptreccount.'</td><td style="text-align:left">'.implode('<br />', $data['senders']).'</td><td style="text-align:left">'
				.implode('<br />', $data['recipients']).'</td><td style="text-align:left">'.implode('<br />', $data['ips']).'</td></tr>';
		}
	}
	
	if (empty($scriptstats) === true)
		$scriptstats = '<tr><td colspan="6"><strong>None</strong></td></tr>';	
	
	$server = HOST;
	$date = DATER;
	
	require 'report.inc.php';
	return $emailHTML;
}

if ($argv[1] === '-p')
	report($senders, $scripts, $mails); // Just display a short report
else {
	// If we are over the limits send a notification
	if (($mails['total'] >= TOTAL_MAILS) || ($senderoverquota === true)) {

		$mail->MsgHTML(reportHTML($senders, $scripts, $mails));
		$mail->ClearReplyTos();
		$mail->ClearAllRecipients();
		foreach($adminemails as $adminemail)
			$mail->AddAddress($adminemail, $adminemail);
		try {
			$mail->Send();
		} catch(Exception $e) { }
	}

	// Here you can put additional logic to automatically clean offending items off the queue using tools such as postqueue
	
}

unlink($mailqfile);
