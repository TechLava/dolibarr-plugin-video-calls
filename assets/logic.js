if (navigator.mediaDevices === undefined) {
  navigator.mediaDevices = {};
}

// Some browsers partially implement mediaDevices. We can't just assign an object
// with getUserMedia as it would overwrite existing properties.
// Here, we will just add the getUserMedia property if it's missing.
if (navigator.mediaDevices.getUserMedia === undefined) {
  navigator.mediaDevices.getUserMedia = function(constraints) {

    // First get ahold of the legacy getUserMedia, if present
    var getUserMedia = navigator.webkitGetUserMedia || navigator.mozGetUserMedia;

    // Some browsers just don't implement it - return a rejected promise with an error
    // to keep a consistent interface
    if (!getUserMedia) {
      return Promise.reject(new Error('getUserMedia is not implemented in this browser'));
    }

    // Otherwise, wrap the call to the old navigator.getUserMedia with a Promise
    return new Promise(function(resolve, reject) {
      getUserMedia.call(navigator, constraints, resolve, reject);
    });
  }
}

// Define usable constants

var localStream;
var room;
var peers = {};

var localVideo;
var remoteVideo;
var text;
var users;
var isHost = false;

const peer = new Peer(USER, {
	key: '17b9864c-f92b-4882-bacc-0a1bf22d3f8d',
	debug: 1
});

$(document).ready(()=>{

	localVideo = document.getElementById('local-video');
	remoteVideo = document.getElementById('remote-video');
	text = document.getElementById('txt');

	text.oninput = function() {
		text.style.height = "";
		text.style.height = text.scrollHeight + "px"
	};

	updateTime();

	window.copyToClipboard = (function initClipboardText() {
	  const textarea = document.createElement('textarea');

	  // Move it off-screen.
	  textarea.style.cssText = 'position: absolute; left: -99999em';

	  // Set to readonly to prevent mobile devices opening a keyboard when
	  // text is .select()'ed.
	  textarea.setAttribute('readonly', true);

	  document.body.appendChild(textarea);

	  return function setClipboardText(text) {
	    textarea.value = text;

	    // Check if there is any content selected previously.
	    const selected = document.getSelection().rangeCount > 0 ?
	      document.getSelection().getRangeAt(0) : false;

	    // iOS Safari blocks programmatic execCommand copying normally, without this hack.
	    // https://stackoverflow.com/questions/34045777/copy-to-clipboard-using-javascript-in-ios
	    if (navigator.userAgent.match(/ipad|ipod|iphone/i)) {
	      const editable = textarea.contentEditable;
	      textarea.contentEditable = true;
	      const range = document.createRange();
	      range.selectNodeContents(textarea);
	      const sel = window.getSelection();
	      sel.removeAllRanges();
	      sel.addRange(range);
	      textarea.setSelectionRange(0, 999999);
	      textarea.contentEditable = editable;
	    }
	    else {
	      textarea.select();
	    }

	    try {
	      const result = document.execCommand('copy');

	      // Restore previous selection.
	      if (selected) {
	        document.getSelection().removeAllRanges();
	        document.getSelection().addRange(selected);
	      }

	      return result;
	    }
	    catch (err) {
	      console.error(err);
	      return false;
	    }
	  }
	})();
});

var handleData = function(data){
	data = data.data;

	if( data.type="chat" ){
		$('#chats').append($('\
			<div class="text-left mb-3">\
				<div class="text-left rounded p-2 bg-light text-secondary d-inline-block" style="font-size: small; max-width: 80%;">\
					' + data.data + '<br/>\
					<i class="mt-2 d-inline-block"><a href="javascript:void(0)">'+ data.from +'</a> @ <span class="times" data-value="'+ (data.time-0) +'">' + timeago(data.time) + '</span></i>\
				</div>\
			</div>'));

		const t = $('#unread-counter');
		t.html(Number(t.html()||0)+1);
	}
};

/* Host a call function */

var createCall = function(button, joinCallId){

	userName = ( joinCallId ? $('#join-name').val() : $('#name').val() ) || 'anonymous';

    button.classList.toggle('d-none');
    button.nextElementSibling.classList.toggle('d-none');

    navigator.mediaDevices
        .getUserMedia({ audio: true, video: true })
        .then(stream => {
            localStream = stream;
            localVideo.srcObject = stream;

            room = peer.joinRoom(joinCallId ? joinCallId : USER, {
            	mode: "sfu",
            	stream: localStream
            });

            room.on("open", () => {
            	button.classList.toggle('d-none');
			    button.nextElementSibling.classList.toggle('d-none');
			    $('#create_call').modal('hide');
			    $('#join_call').modal('hide');
			    $('#video-id')[0].innerHTML = (joinCallId ? joinCallId : USER);

			    window.isHost = joinCallId ? false : true;
            });

			room.on("stream", handleStream);

            room.on('peerJoin', (peerId) => {
            	handleData({
            		data: {
						type: 'chat',
						time: (new Date()) - 0,
						data: 'Joined the call',
						from: USERS[peerId]
            		}
            	});

            	console.log(peerId+' JOINED');
            });

            room.on('peerLeave', (peerId) => {
            	handleData({
            		data: {
						type: 'chat',
						time: (new Date()) - 0,
						data: 'Left the call',
						from: USERS[peerId]
            		}
            	});

				$('#'+peerId).remove();
            	console.log(peerId+' LEFT');
            });

            room.on('data', handleData);
        })
        .catch(err => {
        	button.classList.toggle('d-none');
		    button.nextElementSibling.classList.toggle('d-none');

		    localStream.getTracks().forEach(track => track.stop());
            console.log('An error ocurred: '+err);
        });
};

var handleStream = function(stream){
	$('#'+stream.peerId).remove();

    var x = document.createElement('video');
    x.height = 200;
    x.width = 300;
    x.controls = true;
    x.srcObject = stream;
    x.autoplay = true;

    x.classList.toggle('w-100');
    x.classList.toggle('rounded');

    var d = document.createElement('div');
    d.id = stream.peerId;
    d.classList.add('col');
    d.classList.add('text-center');
	//d.classList.toggle('bg-white');
  	d.classList.toggle('shadow-sm');

    d.style.minWidth = '300px';

    var h = document.createElement('strong');
    h.innerHTML = USERS[stream.peerId] + (room.name==stream.peerId ? ' (Host)' : '');
    h.style.color = 'white';

    d.appendChild(x);
    d.appendChild(h);

    remoteVideo.appendChild(d);
};

var mute = function( type, element ){
	switch(type) {
		case 1:
			var x = localStream.getAudioTracks()[0];
			x.enabled = !x.enabled;

			element.children[0].classList.replace(!x.enabled ? 'fa-microphone' : 'fa-microphone-slash', !x.enabled ? 'fa-microphone-slash' : 'fa-microphone');
			break
		case 2:
			var x = localStream.getVideoTracks()[0];
			x.enabled = !x.enabled;
			
			element.children[0].classList.toggle('text-danger');
			break
	}
};

var sendMessage = function(){
	if( text.value ){
		let chat = {
			type: 'chat',
			time: (new Date()) - 0,
			data: text.value,
			from: userName
		};

		text.value = '';

		$('#chats').append($('\
			<div class="text-right mb-3">\
				<div class="text-left rounded p-2 bg-light text-secondary d-inline-block" style="font-size: small; max-width: 80%;">\
					' + chat.data + '<br/>\
					<i class="mt-2 d-inline-block"><a href="javascript:void(0)">Me</a> @ <span class="times" data-value="'+ (chat.time-0) +'">' + timeago(chat.time) + '</span></i>\
				</div>\
			</div>'));

		room.send(chat);
	}
};

var closeCall = function(){
	if( confirm('Do you really wish to end this call') ){
		if( isHost ){
			room.close();
		} else {
			peer.disconnect();
		}
		
		localVideo.srcObject.getTracks().forEach(track => track.stop());
		$('#create_call').modal({backdrop: 'static', keyboard: false});
	}
};

var timeago = function(previous) {
	var msPerMinute = 60 * 1000;
	var msPerHour = msPerMinute * 60;
	var msPerDay = msPerHour * 24;
	var msPerMonth = msPerDay * 30;
	var msPerYear = msPerDay * 365;

	var elapsed = (new Date()) - previous;

	if (elapsed < msPerMinute) {
		return Math.round(elapsed/1000) + ' secs ago';   
	}

	else if (elapsed < msPerHour) {
		return Math.round(elapsed/msPerMinute) + ' mins ago';   
	}

	else if (elapsed < msPerDay ) {
		return Math.round(elapsed/msPerHour ) + ' hrs ago';   
	}

	else if (elapsed < msPerMonth) {
		return Math.round(elapsed/msPerDay) + ' days ago';   
	}

	else if (elapsed < msPerYear) {
		return Math.round(elapsed/msPerMonth) + ' months ago';   
	}

	else {
		return Math.round(elapsed/msPerYear ) + ' years ago';   
	}
};

var updateTime = function(){
	$('.times').each(function(i, x){
		x.innerHTML = timeago(x.getAttribute('data-value'));
	});

	setTimeout(updateTime, 20000);
};

window.addEventListener('beforeunload', function (e) {
  e.preventDefault();
  e.returnValue = '';
});

window.addEventListener('unload', function (e) {
  console.log(e);
});