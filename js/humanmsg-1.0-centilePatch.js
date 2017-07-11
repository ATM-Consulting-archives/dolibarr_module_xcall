/*
	HUMANIZED MESSAGES 1.0
	idea - http://www.humanized.com/weblog/2006/09/11/monolog_boxes_and_transparent_messages
	home - http://humanmsg.googlecode.com
*/

var humanMsg = {
	setup: function(appendTo, logName, msgOpacity) {
		humanMsg.msgID = 'humanMsg';
		humanMsg.logID = 'humanMsgLog';

		// appendTo is the element the msg is appended to
		if (appendTo == undefined)
			appendTo = 'body';

		// The text on the Log tab
		if (logName == undefined)
			logName = 'Message Log';

		// Opacity of the message
		humanMsg.msgOpacity = .8;

		if (msgOpacity != undefined)
			humanMsg.msgOpacity = parseFloat(msgOpacity);

		// Inject the message structure
		$(appendTo).append('<div id="'+humanMsg.msgID+'" class="humanMsg"><p></p></div> <div id="'+humanMsg.logID+'"><p>'+logName+'</p><ul></ul></div>');

		$('#'+humanMsg.logID+' p').click(
			function() { $(this).siblings('ul').slideToggle(); }
		);
	},

	displayMsg: function(msg) {
		if (msg == '') return;
		$('#'+humanMsg.msgID).stop(false, true);
		// Inject message
		$('#'+humanMsg.msgID+' p').html(msg);
		// Show message
		$('#'+humanMsg.msgID+'').show().animate({ opacity: humanMsg.msgOpacity}, 200);
		if (humanMsg.t1) clearTimeout(humanMsg.t1);
		if (humanMsg.t2) clearTimeout(humanMsg.t2);
		// Watch for mouse & keyboard in .5s
		humanMsg.t1 = setTimeout("humanMsg.bindEvents()", 700);
		// Remove message after 5s
		humanMsg.t2 = setTimeout("humanMsg.removeMsg()", 5000);
	},

	bindEvents: function() {
	// Remove message if mouse is moved or key is pressed
		$(window)
//			.mousemove(humanMsg.removeMsg)
			.click(humanMsg.removeMsg)
			.keypress(humanMsg.removeMsg);
	},

	removeMsg: function(withoutFX) {
		// Unbind mouse & keyboard
		$(window)
//			.unbind('mousemove', humanMsg.removeMsg)
			.unbind('click', humanMsg.removeMsg)
			.unbind('keypress', humanMsg.removeMsg);

		// If message is fully transparent, fade it out
		if (withoutFX) {
		  $('#'+humanMsg.msgID).stop(false, true).hide();
		}
		else if ($('#'+humanMsg.msgID).css('opacity') == humanMsg.msgOpacity) {
			$('#'+humanMsg.msgID).stop(false, true).animate({ opacity: 0 }, 500, function() {
			  $(this).hide();
			});
		}
	},

	reset: function(){
		$(document).ready(function(){
			humanMsg.removeMsg();
			$("#" + humanMsg.msgID).remove();
			$("#" + humanMsg.logID).remove();
			humanMsg.setup();
		});
	}
};

$(document).ready(function(){
	humanMsg.setup();
});