<?php
class Template
{
    
    private $buffer;    
    private $meta;
    public $vars;
    private $guard = "free";
    
    function __construct($template)
    {
        require ("_droneSettings.php");
        if ($debugMode)
            $this->startTime = microtime();
        if ($template{0}=="?")
        {   
            if (isset($templateDir) && file_exists($templateDir.substr($template,1)))
                $filename = $templateDir.substr($template,1);
            else
                if (file_exists("templates/".substr($template,1)))
                    $filename = "templates/".substr($template,1);
                else
                    $filename = "phpDrone/templates/".substr($template,1);
        }
        else
            if (isset($templateDir))
                $filename = $templateDir.$template;
            else
                $filename = "templates/".$template;
                
                
        $this->template = "";
        $this->buildTemplate($filename);                
        $this->vars = array();
    }

    function buildTemplate($templateFile)
    {
        $this->solveInheritance($templateFile);
        //clear the block-related tags
        $this->template = preg_replace('/{%block .*%}|{%end-block%}/',"",$this->template);
    }

    function solveInheritance($templateFile)
    {        
        $handle = fopen($templateFile, "r");
        $templateContent = fread($handle, filesize($templateFile));
        fclose($handle);

        //see if the templates extends another and if so, recursivly call the solveInheritance
        if (preg_match('/{%(?:\\s|)extends (?P<baseTemplate>.*)(?:\\s|)%}/', $templateContent, $result))
        {
            $baseTemplate = $result['baseTemplate'];
            $this->solveInheritance(dirname($templateFile)."/".$baseTemplate);

            //get the names of all the blocks in the template.
            preg_match_all('/\\{%(?:\s|)block (?P<blocName>[\\w]*)(?:\s|)%\\}/', $templateContent, $blocks);
            foreach($blocks['blocName'] as $item)
            {
                $item = trim($item);
                //get the content of the found block
                //note to self: ATENTIE!!!!! AM PUS AICI UN "?" DUPA ".*" FARA SA VERIFIC DACA MERE
                preg_match('/(?:\\{%(?:\s|)block '.$item.'(?:\s|)%\\})(?P<blockContent>.*?)\\{%(?:\s|)end-block(?:\s|)%\\}/s', $templateContent, $blocksContent);
                //replace the block content in the base template with the one from the child template
                $this->template = preg_replace ('/(.*){%(?:\s|)block '.$item.'(?:\s|)%}.*?{%(?:\s|)end-block(?:\s|)%}(.*)/s','\\1{%block '.$item.'%}'.$blocksContent['blockContent'].'{%end-block%}\\2',$this->template);
            }
        }
        else
            $this->template = $templateContent;
    }
    
    function addMeta($meta)
    {
        $this->writeVar("meta",$meta."\n");
    }

    function setGuard($guard)
    {
        $this->guard = $guard;
    }

    function injectVars($output)
    {
        $result = $output;
        foreach($this->vars as $var => $value)
            //this if is provisional. Will be fixed soon
            if (gettype($value)!="array" && gettype($value)!="object")
                $result = preg_replace ('/{%(?:[ ]*|)'.$var.'(?:[ ]*|)%}/',$value,$result);
        return $result;
    }

    function deltaTime()
    {
        $time = microtime();
        return $time-$this->startTime;
    }


    function compileTemplate()
    {
        //parse the ifs
        preg_match_all('/{%([ ]*|)if (?P<ifStatement>[^\\}]*)%}/', $this->template, $ifs);
        foreach($ifs['ifStatement'] as $ifStatement)
        {
            $statement = trim($ifStatement);
            $v = addslashes($this->vars[$statement]);
            eval("\$result='$v';");
            if (!$result)
                //{%(?:[ ]*|)if inputError%}(?:[\s]*|.*)*{%(?:[ ]*|)end-if(?:[ ]*|)%}
                $this->template = preg_replace ('/{%(?:[ ]*|)if '.$ifStatement.'%}(?:[^\\x00]*){%(?:[ ]*|)end-if(?:[ ]*|)%}/','',$this->template);
        }
        
        //now let's parse the fors
        preg_match_all('/{%(?:[ ]|)for (?P<item>.*) in (?P<bunch>.*)%}/', $this->template, $fors);
        $pas = 0;
        foreach($fors['bunch'] as $bunch)
        {
            $item = $fors['item'][$pas];
            //{%(?:[ ]|)for item in bunch%}(?P<ifblock>[^\x00]*){%end-for%} - get the block
            // get the if block content
            preg_match('/{%(?:[ ]|)for '.$item.' in '.$bunch.'%}(?P<forblock>[^\\x00]*){%end-for%}/', $this->template, $forBlocksContent);
            $blockContent = $forBlocksContent['forblock'];
            if (isset($this->vars[trim($bunch)]))
            {
                $type = gettype($this->vars[trim($bunch)]);
                if ($type=="array" || $type=="object" || $type=="string")
                {
                    if ($type=="string")
                        $pacient = preg_split('//', $this->vars[trim($bunch)], -1, PREG_SPLIT_NO_EMPTY);
                    else
                        $pacient = $this->vars[trim($bunch)];

                    $newContent = "";
                    foreach ($pacient as $key => $value)
                    {
                        $builtBlock = preg_replace('/{%(?:[ ]*|)'.$item.'(?:[ ]*|)%}/',$value,$blockContent);
                        $newContent .= $builtBlock;
                    }
                    $blockContent = preg_replace('/([\\\\<>*\/])/','\\\\\1',$blockContent);                    
                    $this->template = preg_replace('/{%(?:[ ]*|)for '.$item.' in '.$bunch.'%}'.$blockContent.'{%end-for%}/',$newContent,$this->template);
                }
            }
            else
            {
                $this->template = preg_replace ('/{%(?:[ ]|)for '.$item.' in '.$bunch.'%}(?:[\\s]*|.*)*{%end-for%}/','',$this->template,1);
            }
            $pas++;
        }
    }

    function getBuffer()
    {
        require ("_droneSettings.php");
        $this->compileTemplate();
        $output = $this->template;
        $output = $this->injectVars($output);
        //take out comments
        $output = preg_replace('/{%(?:[ ]*|)comment(?:[ ]*|)%}(?:[^\\x00]*){%(?:[ ]*|)end-comment(?:[ ]*|)%}/', '', $output);
        //delete the rest of unused vars from template
        $output = preg_replace ('/{%[^\\}]*%}/',"",$output);
        return $output;
    }

    private function render_p($args)
    {
        require ("_droneSettings.php");
        if ($this->guard!="free")
            require ($this->guard);
        if ($this->guard=="free" || ($this->guard!="free") && __guard__())
        {
            $output = $this->getBuffer();
            if ($debugMode)
                $output .= "<!--This will apear only in debug mode -->\n<div style='font-size:0.8em;width:100%;border-top:1px solid silver;padding-left:4px;'>Built in <b>".$this->deltaTime()."</b> seconds.<br />___________<br /><b>phpDrone</b> v1.0 BETA</div>";
            print $output;
        }
        else
        {
            if (isset($guardFailPage))
                $guarFailPage = new Template($guardFailPage);
            else
                $guarFailPage = new Template("phpDrone/templates/gurd-failure.tmpl");
            $guarFailPage->write("title","Unauthorized - phpDrone");
            $guarFailPage->render();
        }
    }

    private function write_p($args)
    {
        if (count($args)>1)
            $this->vars[$args[0]] = $args[1];
        else
            if (count($args)==1)
                $this->vars["body"] .= $args[0];
            else
                die('phpDrone error: Function <b>Template->write()</b> takes at least one argument.');
    }

    private function __call($method, $args)
    {
        
        if (method_exists($this,$method."_p"))
            eval("\$this->".$method."_p(\$args);");
        else
            //this wil be replaced later with a nicer error
            die("phpDrone error: Call to undefined method Template->".$method."()");
    }
}

?>
