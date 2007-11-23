<?php
	if (version_compare(phpversion(),"5")>-1)
    {
    	$validModules = array('database'=>'Database.php',
    					      'form'=>'Form.php',
                              'template'=>'Template.php',
                              'mail'=>'DroneMail.php',
                              'widgets'=>'HTMLwidgets.php',
                              'utils'=>'Utils.php',
                              'i18n'=>'i18n.php'
    					     );
        session_start();
        ob_start();
        
        require("DroneConfig.php");
        
        $customModules = DroneConfig::getSection('Modules');
        if ($customModules)
        {
    	    foreach ($customModules as $key=>$loadIt)
    	        if (array_key_exists($key,$validModules) && $loadIt)
    	    		require $validModules[$key];
        }
        else
    	    foreach ($validModules as $module)
    	    		require $module;
	    		
        @include 'droneEnv/drone.php';
        require("DroneCore.php");
	}
	else
	{
	    die("<b>phpDrone error:</b> phpDrone runs only on php5 or above. Your php version is: ".phpversion());
	}
?>
