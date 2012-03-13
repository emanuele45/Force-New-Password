<?php
/**
 * Force new password (FNP)
 *
 * @package FNP
 * @author emanuele
 * @copyright 2012 emanuele, Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 0.1.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function FNP_addSettings ($config_vars)
{
	global $txt;

	loadLanguage('ForceNewPwd');
	$config_vars[] = array('int', 'force_change_password', 'postinput' => $txt['days_word']);
	$config_vars[] = array('check', 'force_change_onactivate');
}

function FNP_check_new_pwd ()
{
	global $context, $modSettings, $sourcedir, $txt;

	$lastUpdate = FNP_getLastUpdate();
	$timeToChange = !empty($modSettings['force_change_password']) ? $lastUpdate + ($modSettings['force_change_password'] * 24 * 60 * 60) : 0;

	if (!empty($_SESSION['FNP_override_check']) || (!empty($lastUpdate) && $timeToChange < time()))
	{
		if ($lastUpdate == -1 && !isset($_SESSION['FNP_override_check']))
			$_SESSION['FNP_override_check'] = 'FNP_new_pwd';

		$loop = isset($_REQUEST['action']) && ($_REQUEST['action'] == 'profile' && isset($_REQUEST['area']) && $_REQUEST['area'] == 'account');
		$loginout = isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('login', 'login2', 'logout'));
		if (!$loop && !$loginout)
			redirectexit('action=profile;area=account;fnp=1', false);
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

		if (empty($_SESSION['FNP_override_check']))
			$context['post_errors'][] = sprintf($txt['FNP_error'], $modSettings['force_change_password']);
		else
			$context['post_errors'][] = isset($txt[$_SESSION['FNP_override_check']]) ? $txt[$_SESSION['FNP_override_check']] : $txt['FNP_new_user'];
	}
}

function FNP_force_change ($regOptions, $theme_vars)
{
	global $modSettings;

	// This is a registration from the admin panel
	if ($regOptions['interface'] == 'admin' && !empty($modSettings['force_change_onactivate']))
		$regOptions['register_vars']['last_pwd_update'] = -1;
}

function FNP_check_first_login ($member_name, $hash_password, $cookieTime)
{
	global $smcFunc, $context;

	$request = $smcFunc['db_query']('', '
		SELECT last_pwd_update
		FROM {db_prefix}members
		WHERE member_name = {string:member_name}
		LIMIT 1',
		array(
			'member_name' => $member_name,
		)
	);
	$last_pwd_update = FNP_retrieveLastUpdate($member_name);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		list($last_pwd_update) = $smcFunc['db_fetch_row']($request);
		if ($last_pwd_update == -1 && !isset($_SESSION['FNP_override_check']))
			$_SESSION['FNP_override_check'] = 'FNP_new_user';
	}
}

function FNP_getLastUpdate ()
{
	global $modSettings, $user_info, $smcFunc;

	// Guests, get out of here
	if (empty($user_info['id']))
		return false;

	$lastUpdate = false;

	if (!empty($modSettings['force_change_password']) || !empty($modSettings['force_change_onactivate']))
		if ($lastUpdate = cache_get_data('force_change_password_' . $user_info['id'], 60 * 60 * 6) === null)
		{
			$lastUpdate = FNP_retrieveLastUpdate($user_info['username']);
			cache_put_data('force_change_password_' . $user_info['id'], $lastUpdate, 60 * 60 * 6);
		}

	return $lastUpdate;
}

function FNP_retrieveLastUpdate($username)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT last_pwd_update
		FROM {db_prefix}members
		WHERE member_name = {string:member_name}',
		array(
			'member_name' => $username,
		)
	);
	list($lastUpdate) = $smcFunc['db_fetch_row']($request);

	// Well, you have to change the password one day or another
	if (empty($lastUpdate) && !empty($modSettings['force_change_password']))
	{
		updateMemberData($user_info['id'], array('last_pwd_update' => time()));
		$lastUpdate = time();
	}
	$smcFunc['db_free_result']($request);

	return $lastUpdate;
}

function FNP_updated_pwd ($username, $posted_pass, $false)
{
	global $smcFunc, $txt, $modSettings;

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

	if ($user['last_pwd_update'] == -1)
		return false;

	if (empty($_POST['passwrd1']) && empty($_POST['passwrd2']))
		return false;
	// The old password must be correct
	// the two passwords posted must be the same
	// the new password must be different from the old one

	if (sha1(strtolower($username) . un_htmlspecialchars($_POST['oldpasswrd'])) == $user['passwd']
		&& $_POST['passwrd1'] == $_POST['passwrd2']
		&& sha1(strtolower($username) . un_htmlspecialchars($_POST['passwrd1'])) != $user['passwd'])
	{
		// Everything is fine, let's come back to the normal routine
		$last_pwd = empty($modSettings['force_change_password']) ? 0 : time();
		updateMemberData($user['id_member'], array('last_pwd_update' => $last_pwd));
		if (isset($_SESSION['FNP_override_check']))
			unset($_SESSION['FNP_override_check']);
		return false;
	}
	elseif (sha1(strtolower($username) . un_htmlspecialchars($_POST['oldpasswrd'])) == $user['passwd']
		&& $_POST['passwrd1'] == $_POST['passwrd2']
		&& sha1(strtolower($username) . un_htmlspecialchars($_POST['passwrd1'])) == $user['passwd'])
	{
		// Set the password to empty so that the change fails
		loadLanguage('Errors');
		loadLanguage('ForceNewPwd');
		$txt['profile_error_bad_password'] = $txt['FNP_same_as_old'];
		$_POST['oldpasswrd'] = '';
		return false;
	}
}
?>