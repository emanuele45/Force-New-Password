<?php
/**
 * Force new password (FNP)
 *
 * @package FNP
 * @author emanuele
 * @copyright 2012 emanuele, Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 0.1.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function FNP_addSettings ($subActions)
{
	global $txt;

	loadLanguage('ForceNewPwd');
	$subActions[] = array('int', 'force_change_password', 'postinput' => $txt['days_word']);
}

function FNP_check_new_pwd ()
{
	global $context, $modSettings, $sourcedir, $txt;

	$lastUpdate = FNP_getLastUpdate();

	if (!empty($lastUpdate) && $lastUpdate + ($modSettings['force_change_password'] * 24 * 60 * 60) < time())
	{
		if (!(isset($_REQUEST['action']) && $_REQUEST['action'] == 'profile' && isset($_REQUEST['area']) && $_REQUEST['area'] == 'account'))
		{
			redirectexit('action=profile;area=account;fnp=1', false);
		}
	}
}

function FNP_add_error ()
{
	global $context, $txt, $modSettings;

	if (isset($_REQUEST['fnp']))
	{
		loadLanguage('ForceNewPwd');
		loadLanguage('Errors');
		if (!isset($context['post_errors']))
			$context['post_errors'] = array();
		$context['post_errors'][] = sprintf($txt['ForceNewPwd_error'], $modSettings['force_change_password']);
	}

	if (isset($_SESSION['FNP_error']))
	{
		loadLanguage('Errors');
		loadLanguage('ForceNewPwd');
		$txt['profile_error_bad_password'] = $txt['FNP_' . $_SESSION['FNP_error']];
		unset($_SESSION['FNP_error']);
	}
}

function FNP_getLastUpdate ()
{
	global $modSettings, $user_info, $smcFunc;

	if (empty($modSettings['force_change_password']) || empty($user_info['id']))
		return false;

	if ($lastUpdate = cache_get_data('force_change_password_' . $user_info['id'], 60 * 60 * 6) === null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT last_pwd_update
			FROM {db_prefix}members
			WHERE id_member = {int:member_id}',
			array(
				'member_id' => $user_info['id'],
			)
		);
		list($lastUpdate) = $smcFunc['db_fetch_row']($request);

		if (empty($lastUpdate))
		{
			// Well, you have to change the password one day or another
			updateMemberData($user_info['id'], array('last_pwd_update' => time()));
			$lastUpdate = time();
		}
		$smcFunc['db_free_result']($request);
		cache_put_data('force_change_password_' . $user_info['id'], $lastUpdate, 60 * 60 * 6);
	}

	return $lastUpdate;
}

function FNP_updated_pwd ($username, $posted_pass, $false)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_member, passwd, password_salt
		FROM {db_prefix}members
		WHERE ' . ($smcFunc['db_case_sensitive'] ? 'LOWER(member_name) = LOWER({string:user_name})' : 'member_name = {string:user_name}') . '
		LIMIT 1',
		array(
			'user_name' => $smcFunc['db_case_sensitive'] ? strtolower($username) : $username,
		)
	);
	$user = $smcFunc['db_fetch_assoc']($request);
	// The old password must be correct,
	// the two passwords posted must be the same
	// the new password must be different from the old one
	if (sha1(strtolower($username) . un_htmlspecialchars($_POST['oldpasswrd'])) == $user['passwd']
		&& $_POST['passwrd1'] == $_POST['passwrd2']
		&& sha1(strtolower($username) . un_htmlspecialchars($_POST['passwrd1'])) != $user['passwd'])
	{
		updateMemberData($user['id_member'], array('last_pwd_update' => time()));
		return true;
	}
	else
	{
		// Set the password to empty so that the change fails
		$_POST['oldpasswrd'] = '';
		$_SESSION['FNP_error'] = 'same_as_old';
		return false;
	}
}
?>