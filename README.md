spamshield
==========

A neat PHP script that scans the Postfix mail queue and produces nice reports and notifications about possible spam issues.

Requirements
============

This script requires:

* PHP >= 5.2 (and php as a CLI binary)
* Postfix >= 2.3
* The PHP extra mail headers script by [Choon.net](http://choon.net/php-mail-header.php) for bad script detection

Supported Operating Systems
===========================

This script supports (but is not limited to) the following Linux distributions:

* Ubuntu
* Debian
* Fedora
* CentOS
* RedHat

Usage
=====

Simply run the script as
		
		chmod 750 ./spamshield.php
		./spamshield.php

(or place it to `/usr/local/bin` and run it without a path) for it to scan the postfix queue and mail the defined administrators of any issues.

If you want a short report instead, simply supply the `-p` argument so that instead of mailing its findings, the script will display them on STDOUT instead.

The script does magic when uses via a cronjob every 15-30 minutes. 

You can also define arbitrary scripts to run (i.e. to notify an administrator via SMS). You will find the place to do just that near the end of file.

Script Variables
================

In order to operate SpamShield correctly you must update the following variables present in the script:

* `TOTAL_MAILS`: The threshold of mails in the queue that will issue an alert. This includes multi-recipient mails times the recipients defined.
* `TOTAL_FILES`: The threshold of mails in the queue as files that will issue an alert.
* `MAILS_PER_SENDER`: The maximum number of mails a sender may have in the queue before triggering an alert. Multi-recipient mails are accounted for by the number of their recipients.
* `MAILS_PER_SCRIPT`: The maximum number of mails a srcipt may have in the queue before triggering an alert. Multi-recipient mails are accounted for by the number of their recipients.
* `TOTALRCPTS_PER_SCRIPT`: The maximum number of recipients a script may have in the queue before triggering an alert. Multi-recipient mails are accounted for by the number of their recipients.
* `SPOOLDIR`: The Postfix spool directory, usually `/var/spool/postfix`.
* `$mailhost`: Define here the mail host that will be used to notify the administrators of triggered alerts.
* `$mailuser`: Define here the mail host username.
* `$mailpass`: Define here the mail host password.
* `$mailport`: Define here the mail host port.
* `$mailfrom`: Define here the envelope from of the notification mail
* `$mailname`: Define here the envelope from name of the notification mail
* `$mailsubj`: Define here the mail subject you want on notifications
* `adminemails`: Define here in an array the e-mails of the administrators you may want to notify of spam issues

The script also includes an HTML template for mail notifications that you can completely customize (but is nice enough as it is).

License
=======

Copyright 2012 Panagiotis Papadomitsos.

This script uses PHPMailer of its mailing purposes, Copyright (C) WorxWare - worxware.com

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.