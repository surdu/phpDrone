<?php
require_once ("Input.php"); //I know captcha already contains Input.php, but maby later I'll want to get it separated somewhow
require_once ("Captcha.php");
class Form
{
	const VALID_USERNAME = '/^[\\w_.$]*$/';
	const VALID_NUMBER = '/^[-+]?(?:\\b[0-9]+(?:\\.[0-9]*)?|\\.[0-9]+\\b)(?:[eE][-+]?[0-9]+\\b)?$/';
	const VALID_EMAIL = '/^[A-Z0-9._%-]+@[A-Z0-9.-]+\\.[A-Z]{2,4}$/i';
	const VALID_PASSCOMPLEX = '/\\A(?=[-_a-zA-Z0-9]*?[A-Z])(?=[-_a-zA-Z0-9]*?[a-z])(?=[-_a-zA-Z0-9]*?[0-9])\\S{6,}\\z/';
    const VALID_URL = '/^(ftp|http|https):\\\/\\\/(\\w+:{0,1}\\w*@)?(\\S+)(:[0-9]+)?(\\\/|\\\/([\\w#!:.?+=&%@!\\-\\\/]))?$/i';
    const VALID_IP = '/^\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b$/';
    const VALID_CC_AMERICANEXPRESS = '/^3[47][0-9]{13}$/';
    const VALID_CC_DISCOVER = '/^6011[0-9]{14}$/';
    const VALID_CC_MASTERCARD = '/^5[1-5][0-9]{14}$/';
    const VALID_CC_VISA = '/^4[0-9]{12}(?:[0-9]{3})?$/';

    function __construct($onSuccess,$defaults=NULL,$method="both")
    {
        $this->onSuccess = $onSuccess;
        $this->inputs = array();
        $this->defaults =$defaults;
        $this->madatoryMarker = "*";
        $this->submitTriggers = array();
        $this->isValid = false;
        $this->allowHtml = false;
        $this->filter = array();
        
        switch ($method)
        {
            case "post":
                $this->request = $_POST;
                $this->addFilesData($this->request);
                break;
            case "get":
                $this->request = $_GET;
                break;
            default:
                $this->request = array_merge_recursive($_GET,$_POST);
                $this->addFilesData($this->request);
                break;
        }
        $this->cleanData($this->request);
    }

    //DEPRECATED
	function allowHTML($bool=True)
	{
		$this->allowHtml = $bool;
	}

    function addFilter($filter)
    {
        $this->filter[$filter] = "";
    }
    
    function removeFilter($filter)
    {
        unset($this->filter[$filter]);
    }

	private function addFilesData(&$requestObj)
	{
		foreach ($_FILES as $key=>$item)
		    $requestObj[$key] = $item['name'];
	}

	private function cleanData(&$data)
	{
		foreach ($data as $key=>$value)
		    if (gettype($value)=="string")
				$data[$key] = stripcslashes($value);
			else if (gettype($value)=="array")
				$this->cleanData($data[$key]);
	}

	private function clearHTML(&$data)
	{
		foreach ($data as $key=>$value)
		    if (gettype($value)=="string")
				if (!$this->inputs[$key]->allowHtml)
				    $this->request[$key] = strtr($this->request[$key],Input::$safeChars);
			else if (gettype($value)=="array")
				$this->clearHTML($data[$key]);
	}


    private function addInput_p($args)
    {
        if (count($args)>1)
        {
            $label = $args[0];
            $type = $args[1];
            $name = $args[2];
            $validator = $args[3];
            $maxLen = $args[4];

            if ($type!="captcha")
                $this->inputs[$name] = new Input($label,$type,$name,$this->madatoryMarker);
            else
                $this->inputs[$name] = new Captcha($label,$name);

            if (isset($this->defaults[$name]))
                if ($type!="select" && $type!="radio")
                	$this->inputs[$name]->defaultValue = $this->defaults[$name];
				else
				    $this->inputs[$name]->initial = $this->defaults[$name];

            if ($validator!="")
                if (is_array($validator))
                {
                    $this->inputs[$name]->setValidator($validator[0],$validator[1]);
                }
                else
                    $this->inputs[$name]->setValidator($validator,_("Invalid value"));
                    
                    
            if ($maxLen)
                $this->inputs[$name]->attributes['maxlength'] = intval($maxLen);

            $this->inputs[$name]->setRequestData($this->request);
            if  ($type=="submit")
                $this->submitTriggers[$this->inputs[$name]->attributes['name']]="";
            $this->inputs[$name]->allowHTML($this->allowHtml);
            foreach ($this->filter as $filter=>$data)
                $this->inputs[$name]->addFilter($filter);
        }
        else
        if (count($args)==1)
        {
            $input = $args[0];

            $input->addedLater = true;
            $input->setRequestData($this->request);
            $this->inputs[$name] = $input;
            $this->inputs[$name]->allowHTML($this->allowHtml);
            foreach ($this->filter as $filter=>$data)
                $this->inputs[$name]->addFilter($filter);
        }
        else
        {
            //this wil be replaced later with a nicer error
            Utils::throwDroneError("Method addInput takes at least one argument.");
        }
        
    }
    
    private function __call($method, $args)
    {
        if (method_exists($this,$method."_p"))
            eval("\$this->".$method."_p(\$args);");
        else
            //this wil be replaced later with a nicer error
            Utils::throwDroneError("Call to undefined method: Form->".$method."()");
    }
    
    function validateForm()
    {
        $isValid = true;
        
        $trigger = array_intersect_key($this->submitTriggers,$this->request);
        if (array_key_exists("droneSubmitTrigger",$this->request) || count($trigger)!=0)
        {
            unset($this->request['droneSubmitTrigger']);
            unset($_POST['droneSubmitTrigger']);
            unset($_GET['droneSubmitTrigger']);

            foreach ($this->inputs as $item)
            {
                $result = $item->validate();
                $item->filterInput();
                if (!$result)
                    $isValid = false;
            }

            if ($isValid)
            {
                if ($this->onSuccess)
                {
                    $this->clearHTML($this->request);
                    $this->valueFlag = true;
                    $meth = $this->onSuccess;
                    $txtresult .= $meth($this->request);
                }
            }
        }

        if (count($this->submitTriggers)==0)
        {
            $validate_trigger = new Input("needed for phpDrone form validation","hidden","droneSubmitTrigger");
            $validate_trigger->setValidator("required","required");
            array_push($this->inputs,$validate_trigger);
        }
        $this->isValid = $isValid;
    }

	function isValid()
	{
		return $this->isValid;
	}

    function getHTML($upperTemplate=False)
    {
        $this->validateForm();
        $htmlResult = "";
        foreach ($this->inputs as $item)
            if (!$item->addedLater)
                if (isset($this->valueFlag))
                    $htmlResult .= $item->writeValueless($upperTemplate);
                else
                    $htmlResult .= $item->write($upperTemplate);
        return $htmlResult;
    }
    
    function getHTMLinputs($upperTemplate=False)
    {
        $this->validateForm();
        $arrayResult = array();
        foreach ($this->inputs as $item)
            if (!$item->addedLater)
                if (isset($this->valueFlag))
                    $arrayResult[$item->attributes['name']] = $item->writeValueless($upperTemplate);
                else
                    $arrayResult[$item->attributes['name']] = $item->write($upperTemplate);
        return $arrayResult;

    }
}


?>
