<?php
/**
 * --------------------------------------------------------------
 * File: mits_language_fixer.php
 * Date: 19.12.2025
 * Time: 11:51
 *
 * Author: Hetfield
 * Copyright: (c) 2025 - MerZ IT-SerVice
 * Web: https://www.merz-it-service.de
 * Contact: info@merz-it-service.de
 * --------------------------------------------------------------
 */

defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

switch ($_SESSION['language_code']) {
    case 'de':
        define('MITS_LANGUAGE_FIXER_BOXNAME', 'MITS Language Files Define Fixer');
        break;
    default:
        define('MITS_LANGUAGE_FIXER_BOXNAME', 'MITS Language Files Define Fixer');
        break;
}

if (is_file(DIR_FS_DOCUMENT_ROOT . 'mits_language_fixer.php')) {
    $add_contents[BOX_HEADING_TOOLS][] = array(
      'admin_access_name' => 'module_export',
      'filename'          => '../mits_language_fixer.php',
      'boxname'           => MITS_LANGUAGE_FIXER_BOXNAME,
      'parameters'        => '',
      'ssl'               => ''
    );
}
