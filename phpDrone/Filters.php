<?php
function filter_trunc($input,$count=1)
{
    if (strlen($input)>$count)
        return substr($input, 0, $count)." ...";
    return $input;
}

function filter_toLower($input)
{
    return strtolower($input);
}

function filter_capitalize($input)
{
    return ucwords($input);
}


function filter_toUpper($input)
{
    return strtolower($input);
}


function filter_inc($input)
{
    return $input+=1;
}

function filter_dec($input)
{
    return $input+=1;
}


function filter_obfuscate($input)
{
    $text = preg_split("//",$input);
    $text = implode("'+'",$text);
    $result = "<script type='text/javascript' src='?phpDroneRequestResource=scripts/obfuscater.js'></script>\n";
    $result .= "<script type='text/javascript'>obfuscate('{$text}')</script><noscript>You need to have JavaScript enabled to see this text</noscript>";
    return $result;
}

function filter_formatTime($input,$format)
{
    return date($format,(int)$input);
}

function filter_htmlSafe($input)
{
    $trans = get_html_translation_table(HTML_ENTITIES);
    return str_replace("\n","<br />",str_replace(" ","&nbsp;",strtr($input,$trans)));
}

function filter_droneSafe($input)
{
    //temporary
    return str_replace("{","<span>{</span>",$input);
}

function filter_phpSafe($input)
{
    //temporary
    return str_replace("$","<span>$</span>",$input);
}

function filter_stripTags($input)
{
    return strip_tags($input);
}

function filter_translate($input,$internal=false)
{
    if (!isset($input))
        $input = " ";
    if (!$internal)
        return _($input);
    else
        return dgettext('phpDrone',$input);
}

function filter_count($input)
{
    return strlen($input);
}

function filter_nonFalse($input,$replacement = null)
{
    if (!$replacement)
        $replacement = _("Not defined");
    if (!$input)
        return $replacement;
    return $input;
}

function filter_replaceRegexp($input,$needle, $replacement)
{
    return preg_replace("/{$needle}/",$replacement,$input);
}

function filter_replace($input,$needle, $replacement)
{
    return str_replace($needle,$replacement,$input);
}

function filter_mask($input, $maskArray)
{
    return DroneUtils::array_get($input,$maskArray,$input);
}



?>
