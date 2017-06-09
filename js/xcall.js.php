<?php 
require '../config.php';
//global $langs;
$langs->load('xcall@xcall');

?>

$(function() {
	
	var TNodeValue = new Array;
	var xcall_regex_a = new RegExp('[jQuery|\$\(]+', 'g');
	var xcall_regex_b = new RegExp('[\\+|00&1-9]?[0-9|\\-|\\ ]{10,16}', 'g');
	var numbers = new PhoneNumberParser();
	
	xcallReadDOM(document.getElementById('id-right'));
	
	// Récupération de tous les noeuds texte
	function xcallReadDOM(current_dom)
	{
		for (var xcall_i in current_dom.childNodes)
		{
			if (current_dom.childNodes[xcall_i].nodeType == Node.TEXT_NODE && xcall_regex_a.test(current_dom.childNodes[xcall_i].nodeValue) !== true) // Node.TEXT_NODE
			{
				TNodeValue.push({phone: current_dom.childNodes[xcall_i].nodeValue.replace(/\s+/g, ''), node: current_dom.childNodes[xcall_i]});
			}
			else if (current_dom.childNodes[xcall_i].nodeType == Node.ELEMENT_NODE)
			{
				xcallReadDOM(current_dom.childNodes[xcall_i]);
			}
		}
	}

	//console.log(TNodeValue);
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
			var span = document.createElement('span');
			span.className = 'xcall_placeCall fa fa-phone-square';
			span.style.marginLeft = '5px';
			span.style.cursor = 'pointer';
			span.title = '<?php echo $langs->trans('xcall_placeCall'); ?> '+this.items[i].phone;
			span.dataset.phone = this.items[i].phone;
			
			$(span).click(function() {
				$.ajax({
					url: '<?php echo dol_buildpath('/xcall/script/interface.php', 1); ?>'
					,data: {
						action : 'placeCall'
						,json: true
						,destination: $(this).data('phone')
					}
					,method: 'POST'
					,dataType: 'json'
				}).done(function(data) {
					console.log(data);
					if (data.TError.length > 0)
					{
						for (var i in data.TError)
						{
							alert(data.TError[i]);
						}
					}
				}).fail(function() {
					//alert( "error" );
				}).always(function() {
					//alert( "complete" );
				});
			});	
			
			this.items[i].node.parentNode.appendChild(span);
			
        }
    }
};