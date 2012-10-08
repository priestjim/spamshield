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

$emailHTML = <<< EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US">
<head>
<style type="text/css">
body {
    margin-left: 0px;
    margin-top: 0px;
    margin-right: 0px;
    margin-bottom: 0px;
    font-family: Verdana, Arial, Helvetica, sans-serif;
    font-size: 12px;
    color: #000000;
}

.table_border_gray td,
.table_border_gray th {
    text-align: center;
	border: 1px solid #666666;
}

.table_border_gray tr {
	border: 1px solid #666666;
}

.main {
    font-family: Verdana, Arial, Helvetica, sans-serif;
    font-size: 12px;
    color: #000000;
}
.table_border_gray {
    font-family: Verdana, Arial, Helvetica, sans-serif;
    font-size: 12px;
    color: #000000;
    background-color: #EAEAEA;
    border: 1px solid #666666;
    padding: 5px;
	margin-bottom: 20px;
}
.links_main {
    font-family: Verdana, Arial, Helvetica, sans-serif;
    font-size: 12px;
    color: #0099CC;
    text-decoration: none;
}
.links_main:hover {
    font-family: Verdana, Arial, Helvetica, sans-serif;
    font-size: 12px;
    color: #FF6B13;
    text-decoration: none;
}

.red_font {
    color: #FF0000;
    font-weight: bold;
}
.bordeau_font {
    color: #FF0000;
    font-weight: bold;
}
.bordeau_font_nobold {
    color: #FF0000
}
.green_font {
    color: #006600;
    font-weight: bold;
}
</style>
</head>
<body>
<h2>Spam report for {$server} on {$date}</h2>
<table width="auto" border="0" cellpadding="3" cellspacing="0" class="table_border_gray">
    <thead>
        <tr>
            <th colspan="2"><h3>Queue Statistics</h3></th>
		</tr>
        <tr>
            <th align="center"><strong>Mail Queue</strong></th>                                    
            <th align="center"><strong>Message Count</strong></th>
        </tr>
    </thead>
    <tbody>
        {$mailstats}
    </tbody>
</table>
<p>&nbsp;&nbsp;</p>
<table width="auto" border="0" cellpadding="3" cellspacing="0" class="table_border_gray">
    <thead>
        <tr>
            <th colspan="2"><h3>Senders Over Quota</h3></th>
		</tr>
        <tr>
            <th align="center"><strong>Sender Mail</strong></th>                                    
            <th align="center"><strong>Message Count</strong></th>          
        </tr>
    </thead>
    <tbody>
        {$senderstats}
    </tbody>
</table>
<p>&nbsp;&nbsp;</p>
<table width="auto" border="0" cellpadding="3" cellspacing="0" class="table_border_gray">
    <thead>
        <tr>
            <th colspan="6"><h3>Scripts Over Quota</h3></th>
		</tr>
        <tr>
            <th align="center"><strong>Script Path</strong></th>                                    
            <th align="center"><strong>Message Count</strong></th>
			<th align="center"><strong>Recipient Count</strong></th>
            <th align="center"><strong>Sender List</strong></th>
            <th align="center"><strong>Recipient List</strong></th>			
            <th align="center"><strong>Calling IP List</strong></th>                        
        </tr>
    </thead>
    <tbody>
        {$scriptstats}
    </tbody>
</table>
</body>
</html>
EOT;
?>