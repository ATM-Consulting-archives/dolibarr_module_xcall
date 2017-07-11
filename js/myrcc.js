var sendAuthorisation, authorization, isLoginDisplayed, errorDisplayed;
var restletApiSource = getLocalValue('restletApiSource');
var requestes = [];
var requestesNotLogged = [];
var canSend = true;

function sendPOST(path, data, type, onSuccess, onComplete, onError) {
	sendData(path, type, onSuccess, onComplete, onError, data);
}

function sendData(path, type, onSuccess, onComplete, onError, data) {
	if (canSend && requestesNotLogged.length == 0) {
		canSend = false;
		var params = {
			url: API_BASE_URL + path,
			type: type,
			beforeSend: beforeSend,
			success: onSuccess,
			complete: function (datas) {
				canSend = true;
				sendAuthorisation = false;
				if (typeof (onComplete) == typeof (function () {
				})) {
					onComplete(datas);
				}
				var next = requestes.pop();
				if (next) {
					sendData(next.path, next.type, next.onSuccess, next.onComplete, next.onError, next.data);
				}
			},
			error: onError || doOnError,
			statusCode: {
				401: function (response) {
					requestesNotLogged.push({
						path: path,
						type: type,
						onSuccess: onSuccess,
						onComplete: onComplete,
						onError: onError,
						data: data
					});
					if (!isLoginDisplayed) {
						displayLogin(response);
					}
				},
				412: function (response) {
					requestesNotLogged.push({
						path: path,
						type: type,
						onSuccess: onSuccess,
						onComplete: onComplete,
						onError: onError,
						data: data
					});
					try {
						var json = JSON.parse(response.responseText);
						if (json.message == 'two steps authentication') {
							displayTwoStepLogin(response);
						}
					} catch (e) {
						log(e);
					}
				}
			}
		};
		
		if (path == 'v1/service/Login') {
			params.cache = false;
			params.dataType = 'json';
			params.crossDomain = true;
			params.xhrFields = {withCredentials: true};
		}
		
		if (data) {
			params.data = data;
		}
		errorDisplayed = false;
		jQuery.ajax(params).fail(function (jqXHR, status, textStatus) {
			if (jqXHR && jqXHR.status != 401 && jqXHR.status != 412) {
				if (!errorDisplayed) {
					log(textStatus);
				}
				console.log(jqXHR, status, textStatus);
			}
		});
	} else {
		requestes.push({
			path: path,
			type: type,
			onSuccess: onSuccess,
			onComplete: onComplete,
			onError: onError,
			data: data
		});
	}
}

function beforeSend(jqXHR) {
	jqXHR.setRequestHeader('Content-Type', 'application/json');
	jqXHR.setRequestHeader('X-Application', WEB_APPLICATION_NAME);
	jqXHR.setRequestHeader('X-Authenticate', false);
	if (sendAuthorisation) {
		jqXHR.setRequestHeader('Authorization', authorization);
	}
	if (restletApiSource && restletApiSource.length) {
		jqXHR.setRequestHeader('X-RestletApiModuleEntityName', restletApiSource);
	}
}

function displayLogin(response) {
	isLoginDisplayed = true;
	html = '<table><tbody>';
	html += '<tr class="monitor_login_user">';
	html += '<td>user login</td>';
	html += '<td><input type="text" name="monitor_login_user" id="monitor_login_user" autocomplete="off" value="'+xcall_login+'" /></td>';
	html += '</tr>';
	html += '<tr class="monitor_password">';
	html += '<td>password</td>';
	html += '<td><input type="password" name="monitor_password" id="monitor_password" autocomplete="off" value="'+xcall_password+'" /></td>';
	html += '</tr>';
	html += '</tbody></table>';
	html += '<input type="submit" style="display:none"/>';
	if (restletApiSource && restletApiSource.length) {
		var moduleName = getLocalValue('restletApiName');
		if (moduleName && moduleName.length) {
			html += '<input type="checkbox" id="useRestletApiSource" value="' + restletApiSource + '" checked="checked" onclick="setRestletApiSource()" />';
			html += '<label for="useRestletApiSource">use RestletApi source "' + moduleName + '"</label>';
		}
	}
	var title = 'please, login to ' + WEB_APPLICATION_NAME;
	displayLoginDialog(title, html, 'monitor_login_user');
}

function displayTwoStepLogin(response) {
	var responseMode = response.getResponseHeader('X-Two-Level-Auth-Mechanism');
	var title = 'please, enter password confirmation';
	var html = '';
	if (responseMode) {
		html += 'password send by ';
		if (responseMode.indexOf('authBy') == 0) {
			html += responseMode.substring(6);
		} else {
			html += responseMode;
		}
	}
	html += '<table><tbody>';
	html += '<tr class="monitor_password"><td>2nd password</td>';
	html += '<td><input type="password" name="monitor_password" id="monitor_password" autocomplete="off"/></td></tr>';
	html += '</tbody></table><input type="submit" style="display:none"/>';
	displayLoginDialog(title, html, 'monitor_password');
}

function displayLoginDialog(title, html, id2focus) {
	html = $('<form id="monitor_login_form"/>').html(html).submit(function (event) {
		doLogin();
		$(event.target).closest('.ui-widget').remove();
		isLoginDisplayed = false;
		return false;
	});
	buttons = {};
	buttons['login'] = function (event) {
		doLogin();
		$(this).remove();
		isLoginDisplayed = false;
	};
	$('<div title="' + title + '" />').append(html).dialog({
		draggable: false,
		resizable: false,
		closeOnEscape: false,
		buttons: buttons
	});
	$('#' + id2focus).focus();
}

function doLogin() {
	var user = $('#monitor_login_user').val();
	var password = $('#monitor_password').val();
	var login = user;
	authorization = 'BASIC ' + btoa(login + ':' + password);
	sendAuthorisation = true;
	var next = requestesNotLogged.pop();
	if (next) {
		sendData(next.path, next.type, next.onSuccess, next.onComplete, next.onError);
	}
}

function doOnError(event) {
	try {
		var json = jQuery.parseJSON(event.responseText);
		var cause = json && json.cause || '';
		if (cause != '') {
			cause += ': ';
		}
		var message = (json && (json.firstMessage || json.message)) || event.responseText;
		if (!jQuery.trim(message).length) {
			message = 'unknown error';
		}
		errorDisplayed = true;
		log(cause + message);
	} catch (e) {
		setTimeout(function () {
			if (!errorDisplayed && !isLoginDisplayed || (event.status != 401 && event.status != 412)) {
				log('error ' + (event.status || '') + ' ' + (event.statusText || ''));
			}
		}, 0);
	}
}

function addRestletApiSource(datas, status, jqXHR) {
	var restletApiSource = jqXHR && jqXHR.getResponseHeader('X-RestletApiModuleEntityName');
	var servers = {};
	jQuery(datas).each(function (cpt, element) {
		var serverName = (element.server || {}).name;
		var modules = servers[serverName];
		if (!modules) {
			modules = [];
			servers[serverName] = modules;
		}
		modules.push(element);
	});
	var html = '<table style="float:left;"><tbody>';
	html += '<tr><td>';
	html += '<select id="selectRestletApiSource" onchange="setRestletApiSource()">';
	html += '<option value="">--- select RestletApi source ---</option>';
	var isModuleEntityNotFound = true;
	for (var serverName in servers) {
		var modules = servers[serverName];
		var title = JSON.stringify(modules[0].server).replace(/"/g, '').replace(/,/g, '\n');
		title = title.substring(1, title.length - 1);
		html += '<optgroup label="' + serverName + '" title="' + title + '">';
		jQuery(modules).each(function (cpt, element) {
			delete(element.server);
			var title = JSON.stringify(element).replace(/"/g, '').replace(/,/g, '\n');
			title = title.substring(1, title.length - 1);
			var value = element.name + '_' + element.serverName;
			html += '<option value="' + value + '"';
			html += ' name="' + element.name + '"';
			if (restletApiSource == value) {
				isModuleEntityNotFound = false;
				html += ' selected="selected"';
			}
			if (element.status != 'active') {
				html += ' disabled="disabled"';
			}
			if (!element.type) {
				if (element.status == 'active') {
					html += ' style="color:red;font-weight:bold"';
				} else {
					html += ' style="color:red"';
				}
			}
			html += ' title="' + title + '">';
			html += element.name + ' (' + element.status + ')</option>';
		});
		html += '</optgroup>';
	}
	if (isModuleEntityNotFound) {
		restletApiSource = void(0);
	}
	html += '</select>';
	html += '</td></tr>';
	html += '<tr><td>';
	html += '<div style="float:right">';
	html += '<label for="filterRestletApiSource">filter RestletApi sources</label>';
	html += '<input type="checkbox" id="filterRestletApiSource"';
	if (getLocalValue('restletApiFilter') != 'false') {
		html += ' checked="checked"';
	}
	html += ' onclick="setRestletApiSource();" autocomplete="OFF" />';
	html += '</div>';
	html += '</td></tr>';
	html += '</tbody></table>';
	jQuery('#headerZone').prepend(html);
}

function setRestletApiSource() {
	var select = jQuery('#selectRestletApiSource');
	if (select.length) {
		var filter = jQuery('#filterRestletApiSource');
		restletApiSource = select.val();
		setLocalValue('restletApiName', select.find('option:selected').attr('name'));
		setLocalValue('restletApiFilter', filter.is(':checked'));
		document.location.reload();
	} else if (restletApiSource) {
		restletApiSource = '';
	} else {
		restletApiSource = jQuery('#useRestletApiSource').val();
	}
	setLocalValue('restletApiSource', restletApiSource);
}

function log(txt) {
	humanMsg.displayMsg(txt);
}


function setLocalValue(name, value) {
	if (typeof (localStorage) == typeof (void(0))) {
		document.cookie = name + "=" + escape(value);
	} else {
		localStorage.setItem(name, value);
	}
}

function getLocalValue(name) {
	var result;
	if (typeof (localStorage) == typeof (void(0))) {
		var dc = document.cookie;
		var prefix = name + "=";
		var begin = dc.indexOf("; " + prefix);
		if (begin == -1) {
			begin = dc.indexOf(prefix);
		}
		if (begin == 0) {
			begin += 2;
			var end = document.cookie.indexOf(";", begin);
			if (end == -1)
				end = dc.length;
			result = unescape(dc.substring(begin + prefix.length, end));
		} else {
			result = '';
		}
	} else {
		result = localStorage.getItem(name);
	}
	return result;
}

var server = {
	connect: function () {
		this.useFallback = false;
		try {
			if (this.ws) {
				this.ws.close();
			}
			if (this.keepAliveTimeout) {
				clearTimeout(this.keepAliveTimeout);
			}
			$.cometd.disconnect();
		} catch (e) {
			console.log(e);
		}
		try {
			if (window.WebSocket) {
				var url = API_BASE_URL.replace('http', 'ws');
				this.ws = new WebSocket(url + 'ws-service/myRCC');
				this.ws.onopen = this.onOpen;
				this.ws.onmessage = this.onMessage;
				this.ws.onclose = this.onClose;
			} else {
				this.useFallback = true;
			}
		} catch (e) {
			this.useFallback = false;
		}
		if (this.useFallback) {
			this.channel = channel = '/myRCC';
			var url = API_BASE_URL + 'cometd';
			$.cometd.configure({
				url: url
						/*, logLevel: 'debug'*/
			});
			var onMessage = this.onMessage;
			console.log('init');
			$.cometd.handshake({appName: 'myRCC'}, function (handshakeReply) {
				server.onMessage(handshakeReply);
				if (handshakeReply.successful) {
					console.log('handshake successful', handshakeReply);
					$.cometd.subscribe(channel, onMessage,
							function (subscribeReply) {
								server.onMessage(handshakeReply);
								if (subscribeReply.successful) {
									console.log('subscribe successful', subscribeReply);
								} else {
									console.log('subscribe error', subscribeReply);
								}
							});
				} else {
					console.log('handshake error', handshakeReply);
				}
			});
		}
	},
	onOpen: function (m) {
		console.log('onopen', m);
		var map = {};
		if (m && m.data) {
			try {
				map = JSON.parse(m.data);
			} catch (e) {
				console.log(e);
			}
		}
		var timeout = Number(map.timeout_milliseconds || 300000) / 3;
		if (timeout < 0) {
			timeout = 0;
		}
		console.log('keepAlive', timeout);
		server.keepAliveTimeout = setInterval(server.keepAlive, timeout);
	},
	send: function (message) {
		if (server.useFallback) {
			console.log('sending ' + message);
			$.cometd.publish(this.channel, message);
		} else if (this.ws) {
			console.log('sending ' + message);
			this.ws.send(message);
		}
	},
	process: function (text) {
		if (text != null && text.length > 0) {
			server.send(text);
		}
	},
	onMessage: function (m) {
		console.log('onmessage', m);
		var msg = m.data || m.error;
		try {
			if (msg != 'ping' && typeof (msg) == typeof ('')/* && !$('#xcall_lineId').val().length*/) {
				var json = JSON.parse(msg);
				if (json.item && json.item.length) {
					for (var i = 0; i < json.item.length; i++) {
						var lineId = json.item[i].lineId;
						if (lineId && json.item[i].state != 'dropped') {
							$('#xcall_lineId').val(lineId);
						}
					}
				}
			}
		} catch (e) {
			console.log(e);
		}
		if (msg) {
			var txt = '[';
			var date = new Date();
			var h = date.getHours();
			txt += h + ':';
			var m = date.getMinutes();
			if (m < 10) {
				txt += '0';
			}
			txt += m + ':';
			var s = date.getSeconds();
			if (s < 10) {
				txt += '0';
			}
			txt += s + '.' + date.getMilliseconds();
			txt += '] ' + msg;
			displayMessage(txt);
		}
	},
	onClose: function (m) {
		console.log('onclose', m);
		displayMessage('(' + m.type + ') ' + m.code + ': ' + m.reason);
		delete(this.ws);
	},
	keepAlive: function () {
		server.send('ping');
	}
};

function displayMessage(m) {
	var messageBox = $('#messageBox');
	messageBox.append('<span class="text">' + m + '</span><br/>');
	messageBox[0].scrollTop = messageBox[0].scrollHeight - messageBox[0].clientHeight;
}
