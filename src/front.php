<?php namespace jfusion\plugins\phpbb3;
/**
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage phpbb3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

/**
 * JFusion Front Class for phpbb3
 * For detailed descriptions on these functions please check Plugin_Front
 *
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage phpbb3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Front extends \JFusion\Plugin\Front
{
    /**
     * @return string
     */
    function getRegistrationURL() {
        return 'ucp.php?mode=register';
    }

    /**
     * @return string
     */
    function getLostPasswordURL() {
        return 'ucp.php?mode=sendpassword';
    }

    /**
     * @return string
     */
    function getLostUsernameURL() {
        return 'ucp.php?mode=sendpassword';
    }
}