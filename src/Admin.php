<?php namespace JFusion\Plugins\phpbb3;
/**
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage phpbb3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

use JFusion\Application\Application;
use JFusion\Factory;
use JFusion\Framework;

use JFusion\User\Groups;
use Joomla\Database\DatabaseFactory;
use Joomla\Filesystem\File;
use Joomla\Language\Text;

use Psr\Log\LogLevel;

use \Exception;

/**
 * JFusion Admin Class for phpbb3
 * For detailed descriptions on these functions please check Plugin_Admin
 *
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage phpbb3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class Admin extends \JFusion\Plugin\Admin
{
    /**
     * @return string
     */
    function getTablename()
    {
        return 'users';
    }

    /**
     * @param string $softwarePath
     *
     * @return array
     */
    function setupFromPath($softwarePath)
    {
	    $myfile = $softwarePath . 'config.php';

        $params = array();
	    $lines = $this->readFile($myfile);
        if ($lines === false) {
            Framework::raise(LogLevel::WARNING, Text::_('WIZARD_FAILURE') . ': ' . $myfile . ' ' . Text::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
        } else {
            //parse the file line by line to get only the config variables
            $config = array();
	        foreach ($lines as $line) {
		        if (strpos($line, '$') === 0) {
			        //extract the name and value, it was coded to avoid the use of eval() function
			        $vars = explode("'", $line);
			        $name = trim($vars[0], ' $=');
			        $value = trim($vars[1], ' $=');
			        $config[$name] = $value;
		        }
	        }

            //save the parameters into array
            $params['database_host'] = isset($config['dbhost']) ? $config['dbhost'] : '';
            $params['database_name'] = isset($config['dbname']) ? $config['dbname'] : '';
            $params['database_user'] = isset($config['dbuser']) ? $config['dbuser'] : '';
            $params['database_password'] = isset($config['dbpasswd']) ? $config['dbpasswd'] : '';
            $params['database_prefix'] = isset($config['table_prefix']) ? $config['table_prefix'] : '';
            $params['database_type'] = isset($config['dbms']) ? $config['dbms'] : '';
            //create a connection to the database
            $options = array('driver' => $params['database_type'], 'host' => $params['database_host'], 'user' => $params['database_user'], 'password' => $params['database_password'], 'database' => $params['database_name'], 'prefix' => $params['database_prefix']);
            //Get configuration settings stored in the database
	        try {
		        $db = DatabaseFactory::getInstance($options)->getDriver($params['database_type'], $options);

		        if (!$db) {
			        Framework::raise(LogLevel::WARNING, Text::_('NO_DATABASE'), $this->getJname());
			        return false;
		        } else {
			        $query = $db->getQuery(true)
				        ->select('config_name, config_value')
				        ->from('#__config')
				        ->where('config_name IN (\'script_path\', \'cookie_path\', \'server_name\', \'cookie_domain\', \'cookie_name\', \'allow_autologin\')');

			        $db->setQuery($query);
			        $rows = $db->loadObjectList();
			        foreach ($rows as $row) {
				        $config[$row->config_name] = $row->config_value;
			        }
			        //store the new found parameters
			        $params['cookie_path'] = isset($config['cookie_path']) ? $config['cookie_path'] : '';
			        $params['cookie_domain'] = isset($config['cookie_domain']) ? $config['cookie_domain'] : '';
			        $params['cookie_prefix'] = isset($config['cookie_name']) ? $config['cookie_name'] : '';
			        $params['allow_autologin'] = isset($config['allow_autologin']) ? $config['allow_autologin'] : '';
			        $params['source_path'] = $softwarePath;
		        }
		        $params['source_url'] = '';
		        if (isset($config['server_name'])) {
			        //check for trailing slash
			        if (substr($config['server_name'], -1) == '/' && substr($config['script_path'], 0, 1) == '/') {
				        //too many slashes, we need to remove one
				        $params['source_url'] = $config['server_name'] . substr($config['script_path'], 1);
			        } else if (substr($config['server_name'], -1) == '/' || substr($config['script_path'], 0, 1) == '/') {
				        //the correct number of slashes
				        $params['source_url'] = $config['server_name'] . $config['script_path'];
			        } else {
				        //no slashes found, we need to add one
				        $params['source_url'] = $config['server_name'] . '/' . $config['script_path'];
			        }
		        }
	        } catch (Exception $e) {
		        Framework::raise(LogLevel::WARNING, Text::_('NO_DATABASE') . ' ' . $e->getMessage(), $this->getJname());
		        return false;
	        }
        }
        //return the parameters so it can be saved permanently
        return $params;
    }

    /**
     * Returns the a list of users of the integrated software
     *
     * @param int $limitstart start at
     * @param int $limit number of results
     *
     * @return array
     */
    function getUserList($limitstart = 0, $limit = 0)
    {
	    try {
		    //getting the connection to the db
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('username, user_email as email, user_id as userid')
			    ->from('#__users')
		        ->where('user_email NOT LIKE ' . $db->quote(''))
			    ->where('user_email IS NOT null');

		    $db->setQuery($query, $limitstart, $limit);
		    //getting the results
		    $userlist = $db->loadObjectList();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $userlist = array();
	    }
        return $userlist;
    }

    /**
     * @return int
     */
    function getUserCount()
    {
	    try {
		    //getting the connection to the db
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__users')
			    ->where('user_email NOT LIKE ' . $db->quote(''))
			    ->where('user_email IS NOT null');

		    $db->setQuery($query);
		    //getting the results
		    $no_users = $db->loadResult();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $no_users = 0;
	    }
        return $no_users;
    }

    /**
     * @return array
     */
    function getUsergroupList()
    {
	    //get the connection to the db
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->select('group_id as id, group_name as name')
		    ->from('#__groups');

	    $db->setQuery($query);
	    //getting the results
	    return $db->loadObjectList();
    }

    /**
     * @return string|array
     */
    function getDefaultUsergroup()
    {
	    $usergroup = Groups::get($this->getJname(), true);

	    $group = array();
	    if ($usergroup !== null) {
		    //we want to output the usergroup name
		    $db = Factory::getDatabase($this->getJname());

		    if (!isset($usergroup->groups)) {
			    $usergroup->groups = array($usergroup->defaultgroup);
		    } else if (!in_array($usergroup->defaultgroup, $usergroup->groups)) {
			    $usergroup->groups[] = $usergroup->defaultgroup;
		    }

		    foreach ($usergroup->groups as $g) {
			    $query = $db->getQuery(true)
				    ->select('group_name')
				    ->from('#__groups')
				    ->where('group_id = ' . $db->quote($g));

			    $db->setQuery($query);
			    $group[] = $db->loadResult();
		    }
	    }
	    return $group;
    }

    /**
     * @return bool
     */
    function allowRegistration()
    {
	    $result = false;
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('config_value')
			    ->from('#__config')
			    ->where('config_name = ' . $db->quote('require_activation'));

		    $db->setQuery($query);
		    //getting the results
		    $new_registration = $db->loadResult();
		    if ($new_registration != 3) {
			    $result = true;
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
	    return $result;
    }

    /**
     * @param $name
     * @param $value
     * @param $node
     * @param $control_name
     * @return string
     */
    function showQuickMod($name, $value, $node, $control_name)
    {
	    /**
	     * TODO: REMOVE/MOVE ? or fix image path?
	     */
        $error = 0;
        $reason = '';
        $mod_file = $this->getPluginFile('mcp.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            $file_data = file_get_contents($mod_file);
            preg_match_all('/global \$action/', $file_data, $matches);
            //compare it with our joomla path
            if (!isset($matches[0][0])) {
                $error = 1;
                $reason = Text::_('MOD') . ' ' . Text::_('DISABLED');
            }
        }
        //add the javascript to enable buttons
        if ($error == 0) {
            //return success
            $text = Text::_('QUICKTOOLS') . ' ' . Text::_('ENABLED');
            $disable = Text::_('MOD_DISABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('quickMod', 'disable')">{$disable}</a>
HTML;
            return $output;
        } else {
            $text = Text::_('QUICKTOOLS') . ' ' . Text::_('DISABLED') . ': ' . $reason;
            $enable = Text::_('MOD_ENABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('quickMod', 'enable')">{$enable}</a>
HTML;
            return $output;
        }
    }

	/**
	 * @param $action
	 *
	 * @return int
	 */
	function quickMod($action)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getPluginFile('mcp.php', $error, $reason);
		switch($action) {
			case 'reenable':
			case 'disable':
				if ($error == 0) {
					//get the joomla path from the file
					$file_data = file_get_contents($mod_file);
					$search = '/global \$action\;/si';
					$file_data = preg_replace($search, '', $file_data);
					if (!File::write($mod_file, $file_data)) {
						$error = 1;
					}
				}
				if ($action == 'disable') {
					break;
				}
			case 'enable':
				if ($error == 0) {
					//get the joomla path from the file
					$file_data = file_get_contents($mod_file);
					$search = '/\$action \= request_var/si';
					$replace = 'global $action; $action = request_var';
					$file_data = preg_replace($search, $replace, $file_data);
					File::write($mod_file, $file_data);
				}
				break;
		}
		return $error;
	}

    /**
     * @return array
     */
    function uninstall()
    {
        $return = true;
        $reasons = array();

        //doesn't really matter if the quick mod is not disabled so don't return an error
        $this->quickMod('disable');

        return array($return, $reasons);
    }

    /**
     * do plugin support multi usergroups
     *
     * @return bool
     */
    function isMultiGroup()
    {
        return false;
    }

    /**
     * do plugin support multi usergroups
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
	{
		return 'DEPENDS';
	}

	/**
	 * create the render group function
	 *
	 * @return string
	 */
	function getRenderGroup()
	{
		$jname = $this->getJname();

		Application::getInstance()->loadScriptLanguage(array('MAIN_USERGROUP', 'MEMBERGROUPS'));

		$js = <<<JS
		JFusion.renderPlugin['{$jname}'] = function(index, plugin, pair, usergroups) {
			return (function( $ ) {
				var root = $('<div></div>');

				var defaultgroup = $(pair).prop('defaultgroup');
				var groups = $(pair).prop('groups');

				// render default group
				root.append($('<div>' + JFusion.Text._('MAIN_USERGROUP') + '</div>'));

				var defaultselect = $('<select></select>');
				defaultselect.attr('name', 'usergroups['+plugin.name+']['+index+'][defaultgroup]');
				defaultselect.attr('id', 'usergroups_'+plugin.name+index+'defaultgroup');

				defaultselect.change(function() {
	                var value = $(this).val();

					$('#'+'usergroups_'+plugin.name+index+'groups'+' option').each(function() {
						if ($(this).val() == value) {
							$(this).prop('selected', false);
							$(this).prop('disabled', true);

							$(this).trigger('chosen:updated').trigger('liszt:updated');
		                } else if ($(this).prop('disabled') === true) {
							$(this).prop('disabled', false);
							$(this).trigger('chosen:updated').trigger('liszt:updated');
						}
					});
				});

	            $.each(usergroups, function( key, group ) {
	                var options = $('<option></option>');
					options.val(group.id);
	                options.html(group.name);

			        if (pair && defaultgroup && defaultgroup == group.id) {
						options.attr('selected','selected');
			        }

					defaultselect.append(options);
	            });

			    root.append(defaultselect);

				// render default member groups
				root.append($('<div>' + JFusion.Text._('MEMBERGROUPS') + '</div>'));

				var membergroupsselect = $('<select></select>');
				membergroupsselect.attr('name', 'usergroups['+plugin.name+']['+index+'][groups][]');
				membergroupsselect.attr('id', 'usergroups_'+plugin.name+index+'groups');
				membergroupsselect.attr('multiple', 'multiple');

	            $.each(usergroups, function( i, group ) {
	                var options = $('<option></option>');
					options.val(group.id);
	                options.html(group.name);

			        if (pair && defaultgroup == group.id) {
			            options.attr('disabled', 'disabled');
			        } else if (!pair && i === 0) {
			            options.attr('disabled', 'disabled');
			        } else {
			            if (pair && groups && $.inArray(group.id, groups) >= 0) {
			                options.attr('selected', 'selected');
			            }
			        }

					membergroupsselect.append(options);
	            });

			    root.append(membergroupsselect);
			    return root;
			})(jQuery);
		};
JS;
		return $js;
	}
}
