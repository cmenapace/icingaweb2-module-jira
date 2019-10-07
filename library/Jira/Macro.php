<?php
/* Icinga Web 2 | (c) 2014 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Jira;

use Exception;
use Icinga\Application\Logger;
/**
 * Expand macros in string in the context of MonitoredObjects
 */
class Macro
{
    /**
     * Known icinga macros
     *
     * @var array
     */
    private static $icingaMacros = array(
        'HOSTNAME'         => 'host_name',
        'HOSTADDRESS'      => 'host_address',
        'HOSTADDRESS6'     => 'host_address6',
        'SERVICEDESC'      => 'service_description',
        'host.state_id'    => 'host_state',
        'service.state_id' => 'service_state',
        'host.output'      => 'host_long_output',
        'service.output'   => 'service_long_output',
    );
    
    
    /**
     * Return the given string with macros being resolved
     *
     * @param   string                      $input      The string in which to look for macros
     * @param   MonitoredObject|stdClass    $object     The host or service used to resolve macros
     *
     * @return  string                                  The substituted or unchanged string
     */
    public static function resolveMacros($input, $object)
    {
        $matches = array();
        if (preg_match_all('@\$([^\$\s]+)\$@', $input, $matches)) {
            foreach ($matches[1] as $key => $value) {
                $newValue = self::resolveMacro($value, $object);
                if ($newValue !== $value) {
                    $input = str_replace($matches[0][$key], $newValue, $input);
                }
            }
        }
        return $input;
    }
    /**
     * Resolve a macro based on the given object
     *
     * @param   string                      $macro      The macro to resolve
     * @param   MonitoredObject|stdClass    $object     The object used to resolve the macro
     *
     * @return  string                                  The new value or the macro if it cannot be resolved
     */
    public static function resolveMacro($macro, $object)
    {
        if (isset(self::$icingaMacros[$macro]) && isset($object->{self::$icingaMacros[$macro]})) {
            return $object->{self::$icingaMacros[$macro]};
        }
        
        if(preg_match('/(^.+)\.vars\.(.+$)/', $macro, $matches)) {
            $vars = self::getCustomVars($object, $matches[1], $matches[2]);
            
            if (isset($vars[$matches[2]])) {
                return $vars[$matches[2]];
            }
        }
        
        if(strpos($macro, '.') !== false) {
            $macro = str_replace(".", "_", $macro);
        }
        
        try {
            $value = $object->$macro;
        } catch (Exception $e) {
            $value = null;
            Logger::debug('Unable to resolve macro "%s". An error occured: %s', $macro, $e);
        }
        return $value !== null ? $value : $macro;
    }
    
    protected static function getCustomVars($object, $type, $var) 
    {
        $vars = [];
        
        if($type === 'host') {
            $vars = $object->hostVariables;
        }       
        elseif ($type === 'service') {
            $vars = $object->serviceVariables;
        } else {
            return null;
        }
        
        return $vars;    
    }
}