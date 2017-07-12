<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_xcall.class.php
 * \ingroup xcall
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class Actionsxcall
 */
class Actionsxcall
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		/*$error = 0; // Error counter
		$myvalue = 'test'; // A result value

		print_r($parameters);
		echo "action: " . $action;
		print_r($object);

		if (in_array('somecontext', explode(':', $parameters['context'])))
		{
		  // do something only for the context 'somecontext'
		}

		if (! $error)
		{
			$this->results = array('myreturn' => $myvalue);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		}
		else
		{
			$this->errors[] = 'Error message';
			return -1;
		}*/
	}
	
	
	function printLeftBlock($parameters, &$object, &$action, $hookmanager)
	{
		global $langs,$conf,$user;
		
		if (!empty($user->array_options['options_xcall_address_number']))
		{
			$langs->load('xcall@xcall');
		
			// TODO Factoriser
			$hookmanager->resPrint = '<hr />
				<div id="xcall_panel" class=" vmenu">
					<div align="center" class="titre">'.$langs->trans('xcallPanel').'</div>

					<!-- TODO remove and force the ID with $user->array_options["options_xcall_address_number"] -->
					'.(!empty($conf->global->XCALL_DEBUG) ? '<select id="placeCallExtensionID" autocomplete="OFF"></select>' : '' ).'
					<form action="#" onsubmit="xcallPlaceCall();return false;">
						<!-- TODO use $form->textwithpicto()  -->
						<label class="fieldrequired" for="placeCallDestination">'.$langs->trans('xcall_placeCall').'</label>
						<input id="placeCallDestination" value="" autocomplete="OFF" type="text">
						<div align="center"><input class="button" value="'.$langs->trans('PlaceCall').'" type="submit"></div>
					</form>
					<br />
					<form action="#" onsubmit="sendAction(this);return false;">
						<input id="xcall_lineId" value="" autocomplete="OFF" type="text">
						<div align="center">
							<select id="xcall_action" autocomplete="OFF" onchange="doOnActionChange();">
								<option>addParty</option>
								<option>answer</option>
								<option selected="selected">disconnect</option>
								<option>hold</option>
								<option>unhold</option>
								<option>leave</option>
								<option>merge</option>
								<option>redirect</option>
								<option>sendDtmfs</option>
								<option>transfer</option>
							</select>
							<span id="xcall_actionValueLabel"></span>

							<input id="xcall_actionValue" value="" autocomplete="OFF" style="display:none" type="text">
							<input class="button" value="'.$langs->trans('xcallSendAction').'" type="submit">
						</div>
					</form>

					<div id="messageBox" class="'.(!empty($conf->global->XCALL_DEBUG) ? '' : 'hidden').'"></div>
				</div>
			';
		}
		
	}
}