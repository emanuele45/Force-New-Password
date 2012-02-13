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

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	exit('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

$hooks = array(
	'integrate_pre_include' => '$sourcedir/Subs-ForceNewPwd.php',
	'integrate_verify_user' => 'FNP_check_new_pwd',
	'integrate_general_mod_settings' => 'FNP_addSettings',
	'integrate_verify_password' => 'FNP_updated_pwd',
	'integrate_profile_areas' => 'FNP_add_error',
);

if (SMF == 'SSI' && (!isset($_GET['action']) || (isset($_GET['action']) && !in_array($_GET['action'], array('install', 'uninstall')))))
	echo '
		Please select the action you want to perform:<br />
		<a href="' . $boardurl . '/installHooks.php?action=install">Install</a><br />
		<a href="' . $boardurl . '/installHooks.php?action=uninstall">Uninstall</a>';
else
{
	$context['uninstalling'] = isset($context['uninstalling']) ? $context['uninstalling'] : (isset($_GET['action']) && $_GET['action'] == 'uninstall' ? true : false);
	$integration_function = empty($context['uninstalling']) ? 'add_integration_function' : 'remove_integration_function';
	foreach ($hooks as $hook => $function)
		$integration_function($hook, $function);

	if (SMF == 'SSI')
		echo 'Database adaptation successful!';
}
?>