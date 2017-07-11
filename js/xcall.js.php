<?php 
require '../config.php';
//global $langs;
$langs->load('xcall@xcall');

?>
console.log('<?php echo json_encode($conf->global->XCALL_DEBUG); ?>');
$(function() {
	
	var TNodeValue = new Array;
	var xcall_regex_a = new RegExp('^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$', 'im');
	var numbers = new PhoneNumberParser();
	
	xcallReadDOM(document.getElementById('id-right'));
	
	// Récupération de tous les noeuds texte
	function xcallReadDOM(current_dom)
	{
		for (var xcall_i in current_dom.childNodes)
		{
			if (current_dom.childNodes[xcall_i].nodeType == Node.TEXT_NODE) // Node.TEXT_NODE
			{
				var val = current_dom.childNodes[xcall_i].nodeValue.replace(/\s+/g, '');
				if (val.length >= 10 && xcall_regex_a.test(val) === true) TNodeValue.push({phone: val, node: current_dom.childNodes[xcall_i]});
			}
			else if (current_dom.childNodes[xcall_i].nodeType == Node.ELEMENT_NODE)
			{
				xcallReadDOM(current_dom.childNodes[xcall_i]);
			}
		}
	}

	numbers.parse(TNodeValue);

	for (var xcall_i in numbers.items) numbers.placeCall(xcall_i);
	
});


var PhoneNumberParser = function() {

    var minimum = 10;            // typical minimum phone number length
    this.items = [];

    var public = PhoneNumberParser.prototype;
	
    public.parse = function(TStr) {
        var items = this.items = [];
		
		for (var j in TStr)
		{
			var i = 0, n = '', min = minimum, str = TStr[j].phone, node = TStr[j].node;
			
			while(i < str.length) {
				switch(str[i]) {
					case '+':                                   // start of international number
						if (n.length >= min) items.push({phone: n, node: node});
						n = str[i];
						min = minimum + 2;                      // at least 2 more chars in number
						break;
					case '-': case '.': case '(': case ')':     // ignore punctuation
						break;
					case ' ':
						if (n.length >= min) {              // space after consuming enough digits is end of number
							items.push({phone: n, node: node});
							n = '';
						}
						break;
					default:
						if (str[i].match(/[0-9]/)) {            // add digit to number
							n += str[i];
							if (n.length == 1 && n != '0') {
								min = 3;                        // local number (extension possibly)
							}
						} else {
							if (n.length >= min) {
								items.push({phone: n, node: node});                  // else end of number
							}
							n = '';
						}
						break;
				}
				i++;
			}
			
			if (n.length >= min) {              // EOF
				items.push({phone: n, node: node});
			}
		}
    }

	// Création de l'icone à côté du texte concerné
    public.placeCall = function(i) {
        if (i < this.items.length) {
			<?php if ((float) DOL_VERSION >= 6.0) { ?>
			var el = document.createElement('span');
			el.className = 'xcall_placeCall fa fa-phone-square';
			el.style.marginLeft = '5px';
			<?php } else { ?>
			var el = document.createElement('img');
			el.className = 'xcall_placeCall';
			el.src = '<?php echo dol_buildpath('/xcall/img/xcall_icone.png', 1); ?>';
			<?php } ?>
			
			el.style.cursor = 'pointer';
			el.title = '<?php echo $langs->trans('xcall_placeCall'); ?> '+this.items[i].phone;
			el.dataset.phone = this.items[i].phone;
			
			$(el).click(function() {
				$('#placeCallDestination').val( $(this).data('phone') );
				xcallPlaceCall();
			});	
			
			this.items[i].node.parentNode.appendChild(el);
			
        }
    }
};



var xcall_login = '<?php echo !empty($user->array_options['xcall_login']) ? $user->array_options['xcall_login'] : $conf->global->XCALL_DEFAULT_LOGIN; ?>';
var xcall_password = '<?php echo !empty($user->array_options['xcall_pwd']) ? $user->array_options['xcall_pwd'] : $conf->global->XCALL_DEFAULT_PWD; ?>';
var xcall_id;
var API_BASE_URL = '<?php echo !empty($conf->global->XCALL_URL) ? $conf->global->XCALL_URL : 'https://myistra.centrex9.fingerprint.fr/restletrouter/'; ?>';
var WEB_APPLICATION_NAME = 'myRCC';
var isReady;

$(document).ready(function() {
    sendData('v1/service/Login', 'POST', function(datas, type, response) {
        if (type == 'success' && response && response.getResponseHeader) {
            authorization = void(0);
            WEB_APPLICATION_NAME = response.getResponseHeader('X-Application');
            displayMessage('>>> init websocket');
            var oldOnMessage = server.onMessage;
            server.onMessage = function(msg) {
                if (oldOnMessage) {
                    oldOnMessage(msg);
                }
                if (!isReady) {
                    isReady = true;
                    ready();
                }
            }
            server.connect();
        }
    });
});

function ready() {
    sendData('v1/rcc/Extension', 'GET', function(datas, type, response) {
        $(datas.items).each(function (cpt, element) {
			if (element.addressNumber == '<?php echo $user->array_options['options_xcall_address_number']; ?>') {
				xcall_id = element.restUri;
			}
			
            var id = element.restUri;
            var label = element.addressNumber + ' ' + element.displayName;
			
			<?php if (!empty($conf->global->XCALL_DEBUG)) { ?>
            $('#placeCallExtensionID').append($('<option value="' + id + '">' + label + '</option>'));
			<?php } ?>
            displayMessage('>>> ' + cpt + ') ' + label + '<' + id + '>');
        });
    });
	
	sendPOST('v1/service/EventListener/bean', '{"name":"myRCCListener"}', 'POST', function() {
		displayMessage('>>> GET v1/rcc/CallLine');
		sendData('v1/rcc/CallLine?listenerName=myRCCListener', 'GET', function(datas) {
			displayResponse(datas);
		});
	});
		
    doOnActionChange();
}

function xcallPlaceCall(){
	if (typeof xcall_id === 'undefined') {
		displayMessage('##### ERROR: post number is probably not defined on user card');
	} else {
		var id = xcall_id;
		<?php if (!empty($conf->global->XCALL_DEBUG)) { ?>
		id = $('#placeCallExtensionID').val();
		<?php } ?>
		var destination = $('#placeCallDestination').val();
		if (destination.length) {
			sendPOST(id + '/placeCall', '{"destination":"' + destination + '"}', 'POST', function(datas) {
				displayResponse(datas);
			});
		}
	}
}

function sendAction(element){
    var lineId = $('#xcall_lineId').val();
    var action = $('#xcall_action').val();
    var actionValue = $('#xcall_actionValue').val();
    if (lineId.length) {
        var key = $('#xcall_actionValueLabel').text().split(" ")[0];
        if (key.length) {
            sendPOST('v1/rcc/CallLine/' + lineId + '/' + action, '{"' + key + '":"' + actionValue + '"}', 'POST', function(datas) {
                displayResponse(datas);
            });
        }
        else {
            sendData('v1/rcc/CallLine/' + lineId + '/' + action, 'GET', function(datas) {
                displayResponse(datas);
            });
        }
    }
}

function doOnActionChange() {
    var action = $('#xcall_action').val();
    if (action == 'addParty') {
        $('#xcall_actionValueLabel').text('destination (free string number)');
        $('#xcall_actionValue').show();
    }
    else if (action == 'merge') {
        $('#xcall_actionValueLabel').text('line (uri callLine)');
        $('#xcall_actionValue').show();
    }
    else if (action == 'redirect') {
        $('#xcall_actionValueLabel').text('destination (free string number)');
        $('#xcall_actionValue').show();
    }
    else if (action == 'sendDtmfs') {
        $('#xcall_actionValueLabel').text('dtmfs (string dtmfs)');
        $('#xcall_actionValue').show();
    }
    else if (action == 'transfer') {
        $('#xcall_actionValueLabel').text('line (uri callLine)');
        $('#xcall_actionValue').show();
    }
    else {
        $('#xcall_actionValueLabel').text('');
        $('#xcall_actionValue').hide();
    }
}

function displayResponse(datas) {
    try {
//        displayMessage(JSON.stringify(datas, null, 2).replace(/\n/g, '<br/>').replace(/ /g, '&nbsp;'));
        displayMessage(JSON.stringify(datas));
    }
    catch(e) {
        displayMessage(datas);
    }
}