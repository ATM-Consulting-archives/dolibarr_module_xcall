<?php
/* Copyright (C) 2005		Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2005-2012	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2012		Regis Houssin			<regis.houssin@capnetworks.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/user/clicktodial.php
 *       \brief      Page for Click to dial datas
 */

require './config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

$langs->load("users");
$langs->load("admin");
$langs->load("xcall@xcall");

$action=GETPOST('action','alpha');
$id=GETPOST('id','int');

// Security check
$socid=0;
if ($user->societe_id > 0) $socid = $user->societe_id;

if (empty($user->rights->xcall->call)) accessforbidden();

if ($id > 0)
{
	$object = new User($db);
	$object->fetch($id);
}
		
// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('usercard','globalcard'));

/*
 * Actions
 */

$parameters=array('id'=>$socid);
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
    if ($action == 'update' && !GETPOST('cancel'))
	{
		if ($user->admin)
		{
			// TODO FIXME est-ce que ce login/mdp doit être configuré depuis une fiche utilisateur ? Est-ce pas un login/mdp générique à tous ?
			$object->array_options['options_xcall_login'] = GETPOST('xcall_login');
			$object->array_options['options_xcall_pwd'] = GETPOST('xcall_pwd');
		}
		
		$object->array_options['options_xcall_address_number'] = GETPOST('xcall_address_number');
		
        $result=$object->insertExtraFields();
        if ($result < 0) 
        {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }
}


/*
 * View
 */

$form = new Form($db);

llxHeader('','XCall_conf');

if ($id > 0)
{
	$head = user_prepare_head($object);

	$title = $langs->trans('User');

	
	print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" method="post">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="update">';
	
	dol_fiche_head($head, 'xcall_user_conf', $title, 0, 'user');

	$linkback = '<a href="'.DOL_URL_ROOT.'/user/index.php">'.$langs->trans("BackToList").'</a>';
	
    dol_banner_tab($object,'id',$linkback,$user->rights->user->user->lire || $user->admin);
	
    print '<div class="underbanner clearboth"></div>';
    
    // Edit mode
    if ($action == 'edit')
    {
		print '<table class="border" width="100%">';
        
		if ($user->admin)
		{
			print '<tr><td class="titlefield">XCall '.$langs->trans('Login').'</td>';
			print '<td width="25%" class="valeur">';
			print '<input type="text" name="xcall_login" value="'.(! empty($object->array_options['options_xcall_login'])?$object->array_options['options_xcall_login']:'').'" size="92">';
			print '</td>';
			print '</tr>';

			print '<tr><td class="titlefield">XCall '.$langs->trans('Password').'</td>';
			print '<td width="25%" class="valeur">';
			print '<input type="password" name="xcall_pwd" value="'.(! empty($object->array_options['options_xcall_pwd'])?$object->array_options['options_xcall_pwd']:'').'" size="92">';
			print '</td>';
			print '</tr>';
		}

		print '<tr><td class="titlefield">'.$form->textwithpicto('XCall '.$langs->trans('PostNumber'), $langs->trans('PostNumberHelp')).'</td>';
		print '<td width="25%" class="valeur">';
		print '<input type="text" name="xcall_address_number" value="'.(! empty($object->array_options['options_xcall_address_number'])?$object->array_options['options_xcall_address_number']:'').'" size="92">';
		print '</td>';
		print '</tr>';
			
        print '</table>';
    }
    else	// View mode
    {
        print '<table class="border" width="100%">';

		if ($user->admin)
		{
			print '<tr><td width="25%">XCall '.$langs->trans('Login').'</td>';
			print '<td class="valeur">'.(! empty($object->array_options['options_xcall_login'])?$object->array_options['options_xcall_login']:'').'</td>';
			print '</tr>';

			print '<tr id="phh"><td>XCall '.$langs->trans('Password').'</td>';
			print '<td class="valeur">'.preg_replace('/./','*',(! empty($object->array_options['options_xcall_pwd'])?$object->array_options['options_xcall_pwd']:'')).'</a></td>';
			print "</tr>\n";
		}
		
		print '<tr><td width="25%">'.$form->textwithpicto('XCall '.$langs->trans('PostNumber'), $langs->trans('PostNumberHelp')).'</td>';
		print '<td class="valeur">'.(! empty($object->array_options['options_xcall_address_number'])?$object->array_options['options_xcall_address_number']:'').'</td>';
		print '</tr>';
		
        print "</table>\n";
    }

    dol_fiche_end();

    if ($action == 'edit')
    {
        print '<div align="center"><input class="button" type="submit" value="'.$langs->trans("Save").'">';
        print '&nbsp;&nbsp;&nbsp;&nbsp&nbsp;';
        print '<input class="button" type="submit" name="cancel" value="'.$langs->trans("Cancel").'">';
        print '</div>';
    }    
    
    print '</form>';
    
    /*
     * Barre d'actions
     */
    print '<div class="tabsAction">';

    if (! empty($user->admin) && $action <> 'edit')
    {
        print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&amp;action=edit">'.$langs->trans("Modify").'</a>';
    }

    print "</div>\n";

}


llxFooter();

$db->close();
