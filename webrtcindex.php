<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       webrtc/webrtcindex.php
 *	\ingroup    webrtc
 *	\brief      Home page of webrtc top menu
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
// Try main.inc.php using relative path
if (! $res && file_exists("../main.inc.php")) $res=@include "../main.inc.php";
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

// Load translation files required by the page
$langs->loadLangs(array("webrtc@webrtc"));

$action=GETPOST('action', 'alpha');


// Securite acces client
#if (! $user->rights->webrtc->read) accessforbidden();
$socid=GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0)
{
	$action = '';
	$socid = $user->socid;
}

?>

<!DOCTYPE html>
<html>
<head>
	<title>Video Call Application</title>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width,initial-scale=1.0" />

	<link rel="stylesheet" href="assets/bootstrap.min.css" type="text/css" />
	<link rel="stylesheet" href="assets/font-awesome.min.css" type="text/css" />

	<script type="text/javascript" src="assets/jquery.min.js"></script>
	<script type="text/javascript" src="assets/bootstrap.bundle.min.js"></script>

	<script type="text/javascript" src="https://cdn.webrtc.ecl.ntt.com/skyway-latest.js"></script>
	<!--script type="text/javascript" src="assets/peerjs.min.js"></script-->
	<script type="text/javascript">
		const USER = '<?php echo sha1(sha1(md5(md5($user->id)))); ?>';

		const USERS = <?php
			$res = $db->query("SELECT rowid, login FROM `llx_user` WHERE `rowid`!=$user->id ORDER BY `login` ASC");

			$users = array();

			if( $res && $res->num_rows>0 ){
				while($r=$res->fetch_assoc()){
					$users[sha1(sha1(md5(md5($r['rowid']))))] = $r['login'];
				}
			}

			echo json_encode($users);
		?>;
	</script>
	<script type="text/javascript" src="assets/logic.js"></script>

	<style type="text/css">
		.nobody {
			display: none !important;
		}

		.nobody:only-child {
			display: flex !important;
		}
	</style>
</head>
<body class="hidebar bg-white">
	<div id="create_call" class="modal fade bg-white text-dark">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content shadow-sm border">
				<div class="modal-header">
					<h4 class="modal-title">Create call</h4>
				</div>
				<div class="modal-body">
					<label>Display Name (should be a nice name and not contain explicit contents)</label>
					<input id="name" placeholder="Display Name" class="form-control mb-4" value="<?php echo isset($user) ? $user->login : '' ?>" />

					<!--label>Recepients</label>
					<select id="users" class="form-control" multiple>
						<?php
							#$res = $db->query("SELECT rowid, login FROM `llx_user` WHERE `rowid`!=$user->id ORDER BY `login` ASC");

							#if( $res && $res->num_rows>0 ){
							#	while($r=$res->fetch_assoc()){
						?>
	                    <option value="<?php echo sha1(sha1(md5(md5($r['rowid'])))); ?>"><?php echo $r['login']; ?></option>
	                    <?php #}} ?>
					</select-->
					<p align="center">
						<a href="javascript:void(0)" onclick="$('#create_call').modal('hide'); $('#join_call').modal({backdrop: 'static', keyboard: false});">join a call</a>
					</p>
				</div>
				<div class="modal-footer">
					<button onclick="createCall(this);" class="btn btn-success w-100">
						CALL
					</button>
					<button class="btn btn-success w-100 d-none" disabled>
						<i class="fa fa-spinner fa-spin fa-sm"></i>
						&nbsp;calling...
					</button>
				</div>
			</div>
		</div>
	</div>

	<div id="join_call" class="modal fade bg-white text-dark">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content shadow-sm border">
				<div class="modal-header">
					<h4 class="modal-title">Join a call</h4>
				</div>
				<div class="modal-body">
					<label>Call Id:</label>
					<input id="join_id" placeholder="ID of call" class="form-control mb-4" />

					<label>Display Name (should be a nice name and not contain explicit contents)</label>
					<input id="join-name" placeholder="Display Name" class="form-control mb-4" value="<?php echo isset($user) ? $user->login : '' ?>" />

					<!--label>Recepients</label>
					<select id="users" class="form-control" multiple>
						<?php
							#$res = $db->query("SELECT rowid, login FROM `llx_user` WHERE `rowid`!=$user->id ORDER BY `login` ASC");

							#if( $res && $res->num_rows>0 ){
							#	while($r=$res->fetch_assoc()){
						?>
	                    <option value="<?php echo sha1(sha1(md5(md5($r['rowid'])))); ?>"><?php echo $r['login']; ?></option>
	                    <?php #}} ?>
					</select-->
					<p align="center">
						<a href="javascript:void(0)" onclick="$('#join_call').modal('hide'); $('#create_call').modal({backdrop: 'static', keyboard: false});">create call</a>
					</p>
				</div>
				<div class="modal-footer">
					<button onclick="v = $('#join_id').val(); if(!v){return;} createCall(this, v);" class="btn btn-success w-100">
						JOIN
					</button>
					<button class="btn btn-success w-100 d-none" disabled>
						<i class="fa fa-spinner fa-spin fa-sm"></i>
						&nbsp;joining...
					</button>
				</div>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		$('#create_call').modal({
			backdrop: 'static',
			keyboard: false
		});
	</script>

	<div class="p-3">
		<div class="fixed-top bgh text-center text-white p-0 d-flex align-items-center justify-content-center p-4">
			<div class="">
				<span class="font-weight-light">Video Call Id:</span><br/>
				<b id="video-id" title="Copy to clipboard" onclick="copyToClipboard(this.innerText.trim());"></b>
			</div>
		</div>

		<div class="position-lg-fixed video-container bg-transparent mw-100 mb-4" style="z-index: 100; overflow-x: auto; top: 20px; right: 20px; bottom: 50px;">
			<div id="remote-video" class="row flex-nowrap flex-lg-wrap justify-content-lg-end align-items-lg-end h-100">
				<div class="nobody col text-center d-flex align-items-center justify-content-center text-dark mx-auto" style="max-width: 300px; height: 150px">
					<h5 class="">You are the only one in the call, add others by clicking on the user icon below &darr;</h5>
				</div>
			</div>
		</div>

		<div class="position-lg-fixed w-100 h-100 mw-100" style="top: 0; left: 0;">
			<video id="local-video" class="w-100 rounded-0 mb-2" autoplay></video>
		</div>

		<div class="d-flex fixed-bottom p-4">
			<button onclick="this.children[1].innerHTML = ''; showBar();" class="btn btn-light mr-auto">
				<i class="fa fa-commenting-o fa-lg"></i>
				<small id="unread-counter" class="badge badge-pill badge-danger"></small>
			</button>

			<button onclick="mute(1, this)" class="btn btn-light text-secondary">
				<i class="fa fa-microphone fa-lg"></i>
			</button>
			<button onclick="closeCall();" class="btn btn-danger mx-3">
				<i class="fa fa-phone fa-lg"></i>
			</button>
			<button onclick="mute(2, this)" class="btn btn-light text-secondary">
				<i class="fa fa-video-camera fa-lg"></i>
			</button>

			<button class="btn btn-light ml-auto">
				<i class="fa fa-user-o fa-lg"></i>
			</button>
		</div>
	</div>
	<style type="text/css">
		@media (min-width: 992px) {
			.position-lg-fixed {
				position: fixed;
			}

			.video-container {
				width: 300px;
				max-width: 300px !important;
				overflow-x: hidden !important;
				overflow-y: auto;
			}

			.video-control .nobody {
				margin-bottom: 10px;
			}
		}
	</style>

		<div class="sidebar d-flex flex-column position-fixed" style="top: 0; left: 0; height: 100vh; width: 330px; z-index: 999999; background-color: #333;">
			<div class="d-flex align-items-center justify-content-between p-3 border-bottom border-secondary">
				<h4 class="m-0 ml-3" style="color: #f5f5f5">
					Chat
				</h4>
				<a not-ajax href="javascript:hideBar();" class="btn btn-link text-danger" >
					<i class="fa fa-times"></i>
				</a>
			</div>

			<div id="chats" class="flex-grow-1 p-3 text-white" style="overflow-x: auto;">
				<!--div class="text-left mb-3">
					<div class="text-left rounded p-2 bg-light text-secondary d-inline-block" style="font-size: small; max-width: 80%;">
						The quick brown fox jumps over the lazy dog<br/>
						<i class="mt-2 d-inline-block"><a href="">Username</a> @ 20 mins ago</i>
					</div>
				</div>
				<div class="text-left mb-3">
					<div class="text-left rounded p-2 bg-light text-secondary d-inline-block" style="font-size: small; max-width: 80%;">
						Equinox du shenginki<br/>
						<i class="mt-2 d-inline-block"><a href="">Username</a> @ 20 mins ago</i>
					</div>
				</div>
				<div class="text-right mb-3">
					<div class="text-left rounded p-2 bg-light text-secondary d-inline-block" style="font-size: small; max-width: 80%;">
						Equinox du shenginki<br/>
						<i class="mt-2 d-inline-block"><a href="javascript:void(0)">Me</a> @ 20 mins ago</i>
					</div>
				</div-->
			</div>
			
			<div class="d-flex p-3 align-items-center border-top border-secondary">
				<div class="flex-grow-1 px-2">
					<textarea id="txt" class="form-control" placeholder="Type your message" rows="1" style="border-radius: 15px; background-color: #f2f2f2; max-height: 5rem;" ></textarea>
				</div>
				<button onclick="sendMessage();" class="btn btn-link">
					<i class="fa fa-paper-plane text-warning"></i>
				</button>
			</div>
		</div>

	<style type="text/css">
		body:not(.hidebar) {
			position: relative;
			eft: 260px;
			idth: calc( 100% - 260px );
		}

		.sidebar .nav-link {
			transition: padding-left .4s;
		}

		.sidebar .nav-link:hover {
			padding-left: 30px;
			background-color: rgba( 0,0,0,0.2 );
		}

		.hidebar .mainbar {
			left: 0 !important;
		}

		.mainbar {
			transition: left .2s;
		}

		.sidebar {
			transition: width .1s;
		}

		.hidebar .sidebar {
			width: 0 !important;
			overflow: hidden;
			padding: 0 !important;
		}

		body:not(.hidebar) .sidebar::after {
			position: fixed;
			top: 0; left: 330px;
			width: calc(100% - 330px);
			height: 100%;
			background: rgba(0,0,0,0.1);
			z-index: 999999;
			display: block;
			content: '';
		}

		body:not(.hidebar) .mainbar::before {
			content: "";
			display: block;
			position: fixed;
			background: rgba(0,0,0,0.4);
			width: 100%;
			height: 100%;
			top: 0; left: 0;
		}

		body:not(.hidebar) {
			overflow-y: hidden !important;
			max-height: 100vh;
		}

		#unread-counter:empty {
			display: none !important;
		}

		.bgh {
			background-image: linear-gradient(to bottom, rgba(0,0,0,0.7), rgba(0,0,0,0));
			z-index: 100;
		}
	</style>

	<script type="text/javascript">
		function hideBar(){
			$('body').addClass('hidebar');
		}

		function showBar(){
			$('body').removeClass('hidebar');
		}
	</script>
</body>
</html>

<?php

$db->close();