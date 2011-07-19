<?php

$package->license = 'GPL';
$compatible->license = 'GPL';

$package->dependencies['required']->package['pear.erebot.net/Erebot_Module_Wordlists']->save();
$compatible->dependencies['required']->package['pear.erebot.net/Erebot_Module_Wordlists']->save();

/**
 * Extra package.xml settings such as dependencies.
 * More information: http://pear.php.net/manual/en/pyrus.commands.make.php#pyrus.commands.make.packagexmlsetup
 */
/**
 * for example:
$package->dependencies['required']->package['pear2.php.net/PEAR2_Exception']->save();
$package->dependencies['required']->package['pear2.php.net/PEAR2_MultiErrors']->save();
$package->dependencies['required']->package['pear2.php.net/PEAR2_HTTP_Request']->save();

$compatible->dependencies['required']->package['pear2.php.net/PEAR2_Exception']->save();
$compatible->dependencies['required']->package['pear2.php.net/PEAR2_MultiErrors']->save();
$compatible->dependencies['required']->package['pear2.php.net/PEAR2_HTTP_Request']->save();
*/

$deps = array(
    'required' => array(
        'pear.erebot.net/Erebot_Module_Wordlists',
        'pear.erebot.net/Erebot_Module_TriggerRegistry',
    ),
);

foreach (array($package, $compatible) as $obj) {
    $obj->dependencies['required']->php = '5.2.0';

    $obj->license['name'] = 'GPL';
    $obj->license['uri'] = 'http://www.gnu.org/licenses/gpl-3.0.txt';
    // Pyrus <= 2.0.0a3 has a bug with this, see:
    // https://github.com/saltybeagle/PEAR2_Pyrus/issues/12
#    $obj->license['path'] = 'LICENSE';

    foreach ($deps as $req => $data)
        foreach ($data as $dep)
            $obj->dependencies[$req]->package[$dep]->save();
}

