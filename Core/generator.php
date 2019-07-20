<?php
    
    /*
     Copyright (c) 2015-2018, Maximilian Doerr
     
     This file is part of IABot's Framework.
     
     IABot is free software: you can redistribute it and/or modify
     it under the terms of the GNU General Public License as published by
     the Free Software Foundation, either version 3 of the License, or
     (at your option) any later version.
     
     IABot is distributed in the hope that it will be useful,
     but WITHOUT ANY WARRANTY; without even the implied warranty of
     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     GNU General Public License for more details.
     
     You should have received a copy of the GNU General Public License
     along with IABot.  If not, see <http://www.gnu.org/licenses/>.
     */
    
    /**
     * @file
     * Generator object
     * @author Maximilian Doerr (Cyberpower678)
     * @license https://www.gnu.org/licenses/gpl.txt
     * @copyright Copyright (c) 2015-2018, Maximilian Doerr
     */
    
    /**
     * Generator class
     * Generates objects, strings, and arrays for the Parser class
     * @author Maximilian Doerr (Cyberpower678)
     * @license https://www.gnu.org/licenses/gpl.txt
     * @copyright Copyright (c) 2015-2018, Maximilian Doerr
     */
    class DataGenerator {
        
        /**
         * The API class
         *
         * @var API
         * @access public
         */
        public $commObject;
        /**
         * The Regex for fetching templates with parameters being optional
         *
         * @var string
         * @access protected
         */
        protected $templateRegexOptional = '/\{\{(?:[\s\n]*({{{{templates}}}})[\s\n]*(?:\|((?:(\{\{(?:[^{}]*|(?3))*?\}\})|[^{}]*|(?3))*?))?)\}\}/i';
        /**
         * The Regex for fetching templates with parameters being mandatory
         *
         * @var string
         * @access protected
         */
        protected $templateRegexMandatory = '/\{\{(?:[\s\n]*({{{{templates}}}})[\s\n]*\|((?:(\{\{(?:[^{}]*|(?3))*?\}\})|[^{}]*|(?3))*?))\}\}/i';
        
        /**
         * Parser class constructor
         *
         * @param API $commObject
         *
         * @access public
         * @author Maximilian Doerr (Cyberpower678)
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2018, Maximilian Doerr
         */
        public function __construct( API $commObject ) {
            $this->commObject = $commObject;
        }
        
        /**
         * Parse/Generate cite template configuration data
         *
         * @param array $params A list of parameters from the TemplateData doc element
         * @param array $citoid A citoid map
         * @param string $mapString The user's defined template map string
         *
         * @static
         * @access public
         * @return array|bool Rendered example string, template data, or false on failure.
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2017, Maximilian Doerr
         * @author Maximilian Doerr (Cyberpower678)
         */
        public static function processCiteTemplateData( $params, $citoid, $mapString ) {
            $returnArray = [];
            
            if( !empty( $params ) ) {
                $returnArray['template_params'] = $params;
                $id = 0;
                foreach( $params as $param => $paramData ) {
                    $mapTo = [];
                    $returnArray['template_map']['params'][] = $param;
                    $mapTo[] =
                    array_search( $param, $returnArray['template_map']['params']
                                 );
                    if( isset( $paramData['aliases'] ) ) foreach( $paramData['aliases'] as $paramAlias ) {
                        $returnArray['template_map']['params'][] = $paramAlias;
                        $mapTo[] = array_search( $paramAlias,
                                                $returnArray['template_map']['params']
                                                );
                    }
                    
                    if( isset( $paramData['required'] ) && $paramData['required'] === true ) {
                        
                        $groupParams = [ $param ];
                        if( isset( $paramData['aliases'] ) ) $groupParams =
                            array_merge( $groupParams, $paramData['aliases'] );
                        
                        if( !empty( $citoid ) ) {
                            $returnArray['citoid'] = $citoid;
                            $mapped = false;
                            foreach( $groupParams as $param ) {
                                $toCheck = [ 'url', 'accessDate', 'archiveLocation', 'archiveDate', 'title' ];
                                foreach( $toCheck as $check ) {
                                    if( isset( $citoid[$check] ) && $citoid[$check] == $param ) {
                                        $mapped = true;
                                        switch( $check ) {
                                            case "url":
                                                $mapTarget = "url";
                                                $mapValue = "{url}";
                                                break 2;
                                            case "accessDate":
                                                $mapTarget = "access_date";
                                                $mapValue = "{accesstimestamp:automatic}";
                                                break 2;
                                            case "archiveLocation":
                                                $mapTarget = "archive_url";
                                                $mapValue = "{archiveurl}";
                                                break 2;
                                            case "archiveDate":
                                                $mapTarget = "archive_date";
                                                $mapValue = "{archivetimestamp:automatic}";
                                                break 2;
                                            case "title":
                                                $mapTarget = "title";
                                                $mapValue = "{title}";
                                                break 2;
                                        }
                                    }
                                }
                                
                            }
                            
                            if( $mapped === false ) {
                                $mapTarget = "other";
                                $mapValue = "&mdash;";
                            }
                            
                            $returnArray['template_map']['data'][$id]['mapto'] = $mapTo;
                            $returnArray['template_map']['data'][$id]['valueString'] =
                            $mapValue;
                            if( strpos( $mapTarget, "date" ) !== false ) {
                                $returnArray['template_map']['services']['@default'][$mapTarget][] =
                                [ 'index' => $id, 'type' => 'timestamp', 'format' => 'automatic' ];
                            } else {
                                $returnArray['template_map']['services']['@default'][$mapTarget][] =
                                $id;
                            }
                        }
                    }
                    $id++;
                }
            }
            
            if( isset( $citoid['url'] ) || isset( $params['url'] ) || isset( $params['URL'] ) ) {
                if( !empty( $citoid ) ) $returnArray['citoid'] = $citoid;
                
                $criticalCitoidPieces = [ 'url', 'accessDate', 'archiveLocation', 'archiveDate', 'title' ];
                foreach( $criticalCitoidPieces as $piece ) {
                    if( isset( $citoid[$piece] ) ) {
                        $groupParams = [ $citoid[$piece] ];
                        switch( $piece ) {
                            case "url":
                                $mapTarget = "url";
                                $mapValue = "{url}";
                                break;
                            case "accessDate":
                                $mapTarget = "access_date";
                                $mapValue = "{accesstimestamp:automatic}";
                                break;
                            case "archiveLocation":
                                $mapTarget = "archive_url";
                                $mapValue = "{archiveurl}";
                                break;
                            case "archiveDate":
                                $mapTarget = "archive_date";
                                $mapValue = "{archivetimestamp:automatic}";
                                break;
                            case "title":
                                $mapTarget = "title";
                                $mapValue = "{title}";
                                break;
                        }
                        if( isset( $returnArray['template_map']['services']['@default'][$mapTarget] ) ) continue;
                        if( isset( $params[$citoid[$piece]]['aliases'] ) ) {
                            $groupParams = array_merge( $groupParams, $params[$citoid[$piece]]['aliases'] );
                        }
                        
                        $mapTo = [];
                        foreach( $groupParams as $param ) {
                            $mapTo[] = array_search( $param, $returnArray['template_map']['params'] );
                        }
                        
                        $returnArray['template_map']['data'][$id]['mapto'] = $mapTo;
                        $returnArray['template_map']['data'][$id]['valueString'] =
                        $mapValue;
                        if( strpos( $mapTarget, "date" ) !== false ) {
                            $returnArray['template_map']['services']['@default'][$mapTarget][] =
                            [ 'index' => $id, 'type' => 'timestamp', 'format' => 'automatic' ];
                        } else {
                            $returnArray['template_map']['services']['@default'][$mapTarget][] =
                            $id;
                        }
                        $id++;
                    }
                }
            }
            
            $citeMap = DataGenerator::renderTemplateData( $mapString, "citeMap", true, "cite" );
            
            if( !empty( $returnArray['template_map']['params'] ) ) {
                $matchStatistics['templateDataParams'] = $returnArray['template_map']['params'];
                $noMiss = false;
            } else {
                $noMiss = true;
                $matchStatistics['templateDataParams'] = [];
            }
            $matchStatistics['mapStringParams'] = $citeMap['params'];
            $matchStatistics['missingParams'] = 0;
            $matchStatistics['matchedParams'] = 0;
            $matchStatistics['matchPercentage'] = 100;
            
            if( !empty( $citeMap['params'] ) ) foreach( $citeMap['params'] as $param ) {
                
                if( empty( $returnArray['template_map']['params'] ) ||
                   !in_array( $param, $returnArray['template_map']['params'] ) ) {
                    if( $noMiss === false ) $matchStatistics['missingParams']++;
                    $returnArray['template_map']['params'][] = $param;
                } else {
                    $matchStatistics['matchedParams']++;
                }
            }
            
            if( $matchStatistics['missingParams'] + $matchStatistics['matchedParams'] > 0 ) {
                $matchStatistics['matchPercentage'] = $matchStatistics['matchedParams'] /
                ( $matchStatistics['matchedParams'] +
                 $matchStatistics['missingParams'] ) * 100;
            }
            
            if( !empty( $citeMap['data'] ) ) foreach( $citeMap['data'] as $dataIndex => $data ) {
                $toMap = [];
                $mappedParams = [];
                $valueString = $data['valueString'];
                
                $preMapped = false;
                
                if( !empty( $returnArray['template_map']['data'] ) ) foreach(
                                                                             $returnArray['template_map']['data'] as $data2Index => $data2
                                                                             ) {
                    if( $data2['valueString'] == $valueString ) {
                        $preMapped = $data2Index;
                        break;
                    }
                }
                
                foreach( $data['mapto'] as $paramIndex ) {
                    $param = $citeMap['params'][$paramIndex];
                    if( in_array( $param, $mappedParams ) ) continue;
                    
                    $foundParam = false;
                    if( !empty( $params ) ) foreach( $params as $templateDataParam => $templateDataParamData ) {
                        if( $param == $templateDataParam || ( !empty( $templateDataParamData['aliases'] ) &&
                                                             in_array( $param, $templateDataParamData['aliases'] ) ) ) {
                            $foundParam = true;
                            $toMap[] = array_search( $templateDataParam, $returnArray['template_map']['params'] );
                            $mappedParams[] = $templateDataParam;
                            if( !empty( $templateDataParamData['aliases'] ) ) foreach(
                                                                                      $templateDataParamData['aliases'] as $templateDataParam
                                                                                      ) {
                                $toMap[] = array_search( $templateDataParam, $returnArray['template_map']['params'] );
                                $mappedParams[] = $templateDataParam;
                            }
                        }
                    }
                    
                    if( $foundParam === false ) {
                        $toMap[] = array_search( $param, $returnArray['template_map']['params'] );
                        $mappedParams[] = $param;
                    }
                }
                
                
                if( $preMapped !== false ) {
                    foreach( $returnArray['template_map']['data'][$preMapped]['mapto'] as $paramIndex ) {
                        $param = $returnArray['template_map']['params'][$paramIndex];
                        if( in_array( $param, $mappedParams ) ) continue;
                        if( !empty( $params ) ) foreach( $params as $templateDataParam => $templateDataParamData ) {
                            if( $param == $templateDataParam || ( !empty( $templateDataParamData['aliases'] ) &&
                                                                 in_array( $param, $templateDataParamData['aliases']
                                                                          ) ) ) {
                                                                     $toMap[] = array_search( $templateDataParam, $returnArray['template_map']['params'] );
                                                                     $mappedParams[] = $templateDataParam;
                                                                     if( !empty( $templateDataParamData['aliases'] ) ) foreach(
                                                                                                                               $templateDataParamData['aliases'] as $templateDataParam
                                                                                                                               ) {
                                                                         $toMap[] = array_search( $templateDataParam, $returnArray['template_map']['params'] );
                                                                         $mappedParams[] = $templateDataParam;
                                                                     }
                                                                 }
                        }
                    }
                    
                    $returnArray['template_map']['data'][$preMapped]['mapto'] = $toMap;
                } else {
                    $returnArray['template_map']['data'][] = [ 'mapto' => $toMap, 'valueString' => $valueString ];
                }
            }
            
            if( !empty( $citeMap['services']['@default'] ) ) foreach(
                                                                     $citeMap['services']['@default'] as $mapTarget => $targetData
                                                                     ) {
                if( !isset( $returnArray['template_map']['services']['@default'][$mapTarget] ) ) {
                    $toMap = [];
                    foreach( $targetData as $dataIndex ) {
                        if( is_array( $dataIndex ) ) {
                            $mapData = $dataIndex;
                            unset( $mapData['index'] );
                            $dataIndex = $dataIndex['index'];
                        } else {
                            $mapData = false;
                        }
                        $valueString = $citeMap['data'][$dataIndex]['valueString'];
                        
                        foreach( $returnArray['template_map']['data'] as $dataIndex2 => $data ) {
                            if( $data['valueString'] == $valueString ) {
                                if( $mapData === false ) $toMap[] = $dataIndex2;
                                else {
                                    $toMap[] = array_merge( [ 'index' => $dataIndex2 ], $mapData );
                                }
                                break;
                            }
                        }
                    }
                    
                    $returnArray['template_map']['services']['@default'][$mapTarget] = $toMap;
                }
            }
            
            $returnArray['matchStats'] = $matchStatistics;
            
            if( $matchStatistics['matchedParams'] < 2 && $matchStatistics['matchPercentage'] <= 50 ) $returnArray['class'] =
                "warning";
            else $returnArray['class'] = "success";
            
            return $returnArray;
        }
        
        /**
         * Parse/Generate template configuration data
         *
         * @param array|string $data Details about the archive templates
         * @param string $name The name of the template to return in the string output
         * @param bool $parseString Convert string input into mapped array data instead
         *
         * @static
         * @access public
         * @return array|bool Rendered example string, template data, or false on failure.
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2017, Maximilian Doerr
         * @author Maximilian Doerr (Cyberpower678)
         */
        public static function renderTemplateData( $data, $name = "templatename", $parseString = false,
                                                  $templateType = "archive"
                                                  ) {
            $returnArray = [];
            
            if( !is_array( $data ) && $parseString === false ) return false;
            elseif( !is_array( $data ) && $parseString === true ) {
                if( preg_match_all( '/\{(\@(?:\{.*?\}|.)*?)\}/i', $data, $identifiers ) ) {
                    $data = preg_replace( '/\{(\@(?:\{.*?\}|.)*?)\}/i', "", $data );
                }
                $data = explode( "|", $data );
                array_map( 'trim', $data );
                $idCounter = 0;
                $toMap = [];
                $mapAddress = 0;
                $returnArray['services'] = [];
                foreach( $data as $id => $set ) {
                    if( empty( $set ) ) {
                        if( isset( $identifiers[1][$idCounter] ) ) {
                            $identifier = array_map( 'trim', explode( "|", $identifiers[1][$idCounter] ) );
                            if( $templateType == "archive" ) $serviceIdentifier = "";
                            foreach( $identifier as $subId => $subset ) {
                                if( $subId == 0 ) {
                                    if( $templateType == "archive" ) {
                                        $serviceIdentifier = $subset;
                                        $returnArray['services'][$subset] = [];
                                    }
                                } else {
                                    if( strpos( $subset, "=" ) !== false ) {
                                        $toMap[] = $mapAddress;
                                        $tmp = array_map( "trim", explode( "=", $subset, 2 ) );
                                        $returnArray['params'][$mapAddress] = $tmp[0];
                                        if( $templateType == "archive" ) $returnArray['data'][] = [
                                            'serviceidentifier' => $serviceIdentifier, 'mapto' => $toMap,
                                            'valueString'       => $tmp[1]
                                            ];
                                        else $returnArray['data'][] = [
                                            'mapto' => $toMap, 'valueString' => $tmp[1]
                                            ];
                                        $toMap = [];
                                    } else {
                                        $toMap[] = $mapAddress;
                                        $returnArray['params'][$mapAddress] = $subset;
                                    }
                                    $mapAddress++;
                                }
                            }
                            $idCounter++;
                            continue;
                        }
                    } elseif( strpos( $set, "=" ) !== false ) {
                        $toMap[] = $mapAddress;
                        $tmp = array_map( "trim", explode( "=", $set, 2 ) );
                        $returnArray['params'][$mapAddress] = $tmp[0];
                        $returnArray['data'][] = [ 'mapto' => $toMap, 'valueString' => $tmp[1] ];
                        $toMap = [];
                        $returnArray['services']['@default'] = [];
                    } else {
                        $toMap[] = $mapAddress;
                        $returnArray['params'][$mapAddress] = $set;
                    }
                    $mapAddress++;
                }
                
                if( !isset( $returnArray['data'] ) ) return false;
                
                foreach( $returnArray['data'] as $id => $set ) {
                    $tmp = [];
                    if( substr( $set['valueString'], 0, 1 ) != "{" ||
                       substr( $set['valueString'], strlen( $set['valueString'] ) - 1, 1 ) != "}" ||
                       strpos( $set['valueString'], "{", 1 ) !== false ||
                       strpos( substr( $set['valueString'], 0, strlen( $set['valueString'] ) - 1 ), "}", 0 ) !== false ) {
                        $set['valueString'] = "{others}";
                    }
                    $set['valueString'] = trim( $set['valueString'], " \t\n\r\0\x0B{}" );
                    $set['valueString'] = str_replace( '\:', "ESCAPEDCOLON", $set['valueString'] );
                    $set['valueString'] = explode( ":", $set['valueString'] );
                    $set['valueString'] = str_replace( 'ESCAPEDCOLON', ':', $set['valueString'] );
                    
                    switch( $set['valueString'][0] ) {
                        case "url":
                            $tmp['url'][] = $id;
                            break;
                        case "epochbase62":
                            $tmp['archive_date'][] = [ 'index' => $id, 'type' => 'epochbase62' ];
                            break;
                        case "epoch":
                            $tmp['archive_date'][] = [ 'index' => $id, 'type' => 'epoch' ];
                            break;
                        case "microepochbase62":
                            $tmp['archive_date'][] = [ 'index' => $id, 'type' => 'microepochbase62' ];
                            break;
                        case "microepoch":
                            $tmp['archive_date'][] = [ 'index' => $id, 'type' => 'microepoch' ];
                            break;
                        case "archivetimestamp":
                            if( !isset( $set['valueString'][1] ) ) return false;
                            $tmp['archive_date'][] =
                            [ 'index' => $id, 'type' => 'timestamp', 'format' => $set['valueString'][1] ];
                            break;
                        case "accesstimestamp":
                            if( !isset( $set['valueString'][1] ) ) return false;
                            $tmp['access_date'][] =
                            [ 'index' => $id, 'type' => 'timestamp', 'format' => $set['valueString'][1] ];
                            break;
                        case "archiveurl":
                            $tmp['archive_url'][] = $id;
                            break;
                        case "title":
                            $tmp['title'][] = $id;
                            break;
                        case "permadead":
                            if( !isset( $set['valueString'][1] ) || !isset( $set['valueString'][2] ) ) return false;
                            $tmp['permadead'][] = [
                            'index' => $id, 'valueyes' => $set['valueString'][1], 'valueno' => $set['valueString'][2]
                            ];
                            break;
                        case "deadvalues":
                            if( !isset( $set['valueString'][1] ) || !isset( $set['valueString'][2] ) ) return false;
                            if( !isset( $set['valueString'][3] ) ) $set['valueString'][3] = "";
                            if( !isset( $set['valueString'][4] ) ||
                               ( $set['valueString'][4] != "yes" && $set['valueString'][4] != "no" &&
                                $set['valueString'][4] != "usurp" ) ) $set['valueString'][4] = "yes";
                            $tmp['deadvalues'][] = [
                            'index'        => $id, 'valueyes' => $set['valueString'][1],
                            'valueno'      => $set['valueString'][2], 'valueusurp' => $set['valueString'][3],
                            'defaultvalue' => $set['valueString'][4]
                            ];
                            break;
                        case "paywall":
                            if( !isset( $set['valueString'][1] ) || !isset( $set['valueString'][2] ) ) return false;
                            $tmp['paywall'][] = [
                            'index' => $id, 'valueyes' => $set['valueString'][1], 'valueno' => $set['valueString'][2]
                            ];
                            break;
                        case "linkstring":
                            $tmp['linkstring'][] = $id;
                            break;
                        case "remainder":
                            $tmp['remainder'][] = $id;
                            break;
                        default:
                            $tmp['others'][] = $id;
                    }
                    if( $templateType == "archive" ) {
                        if( isset( $set['serviceidentifier'] ) ) $returnArray['services'][$set['serviceidentifier']] =
                            array_merge_recursive( $returnArray['services'][$set['serviceidentifier']], $tmp );
                        else {
                            foreach( $returnArray['services'] as $service => $junk ) {
                                $returnArray['services'][$service] = array_merge_recursive( $junk, $tmp );
                            }
                        }
                    } else {
                        $returnArray['services']['@default'] =
                        array_merge_recursive( $returnArray['services']['@default'], $tmp );
                    }
                }
                
                if( $templateType == "archive" ) {
                    foreach( $returnArray['services'] as $service => $data ) {
                        if( !isset( $data['archive_url'] ) ) {
                            if( $service == "@default" ) return false;
                            if( !isset( $data['url'] ) || !isset( $data['archive_date'] ) ) return false;
                        }
                    }
                }
                
                return $returnArray;
            }
            
            if( isset( $data['services'] ) ) foreach( $data['services'] as $servicename => $service ) {
                $string = "{{{$name}|";
                $tout = [];
                foreach( $data['data'] as $id => $subData ) {
                    $tString = "";
                    if( $templateType == "archive" && isset( $subData['serviceidentifier'] ) &&
                       $subData['serviceidentifier'] != "$servicename" ) {
                        continue;
                    }
                    $counter = 0;
                    foreach( $subData['mapto'] as $paramIndex ) {
                        $counter++;
                        if( $counter == 2 ) {
                            $tString .= "[|";
                        } elseif( $counter > 2 ) {
                            $tString .= "|";
                        }
                        $tString .= $data['params'][$paramIndex] . "=";
                    }
                    if( $counter > 1 ) {
                        $tString .= "]";
                    }
                    $tString .= $subData['valueString'];
                    $tout[] = $tString;
                }
                $string .= implode( "|", $tout );
                $string .= "}}";
                if( $templateType == "archive" ) $returnArray[$servicename] = $string;
                else $returnArray['rendered_string'] = $string;
            }
            
            if( empty( $returnArray ) ) $returnArray['rendered_string'] = "{{{$name}}}";
            
            return $returnArray;
        }
        
        /**
         * Convert strptime outputs to a unix epoch
         *
         * @param array $strptime A strptime generated array
         *
         * @access public
         * @static
         * @return int|false A unix timestamp or false on failure.
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2017, Maximilian Doerr
         * @author Maximilian Doerr (Cyberpower678)
         */
        public static function strptimetoepoch( $strptime ) {
            return mktime( $strptime['tm_hour'], $strptime['tm_min'], $strptime['tm_sec'], $strptime['tm_mon'] + 1,
                          $strptime['tm_mday'], $strptime['tm_year'] + 1900
                          );
        }
        
        /**
         * A customized strftime function that automatically bridges the gap between Windows, Linux, and Mac OSes.
         *
         * @param string $format Formatting string in the Linux format
         * @param int|bool $time A unix epoch.  Default current time.
         * @param bool|string Passed in recursively.  Ignore this value.
         *
         * @access public
         * @static
         * @return int|false A unix timestamp or false on failure.
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2017, Maximilian Doerr
         * @author Maximilian Doerr (Cyberpower678)
         */
        public static function strftime( $format, $time = false, $botLanguage = true, $convertValue = false ) {
            global $locales;
            if( $time === false ) $time = time();
            
            $output = "";
            
            if( $convertValue !== false ) {
                $format = explode( "%$convertValue", $format );
                
                $noPad = false;
                
                switch( $convertValue ) {
                    case "C":
                        $convertValue = ceil( strftime( "%Y", $time ) / 100 );
                        break;
                    case "D":
                        $convertValue = strftime( "%m/%d/%y", $time );
                        break;
                    case "F":
                        $convertValue = strftime( "%m/%d/%y", $time );
                        break;
                    case "G":
                        $convertValue = date( "o", $time );
                        break;
                    case "P":
                        $convertValue = strtolower( strftime( "%p", $time ) );
                        break;
                    case "R":
                        $convertValue = strftime( "%H:%M", $time );
                        break;
                    case "T":
                        $convertValue = strftime( "%H:%M:%S", $time );
                        break;
                    case "V":
                        $convertValue = date( "W", $time );
                        break;
                    case "e":
                    case "-e":
                        if( strlen( $convertValue ) == 2 ) $noPad = true;
                        $convertValue = strftime( "%d", $time );
                        if( (int) $convertValue < 10 ) {
                            $convertValue = " " . (int) $convertValue;
                        }
                        if( $noPad === true ) {
                            $convertValue = trim( $convertValue );
                        }
                        break;
                    case "g":
                        $convertValue = substr( date( "o", $time ), 2 );
                        break;
                    case "h":
                        $convertValue = strftime( "%b", $time );
                        break;
                    case "k":
                    case "-k":
                        if( strlen( $convertValue ) == 2 ) $noPad = true;
                        $convertValue = strftime( "%H", $time );
                        if( (int) $convertValue < 10 ) {
                            $convertValue = " " . (int) $convertValue;
                        }
                        if( $noPad === true ) {
                            $convertValue = trim( $convertValue );
                        }
                        break;
                    case "l":
                    case "-l":
                        if( strlen( $convertValue ) == 2 ) $noPad = true;
                        $convertValue = strftime( "%I", $time );
                        if( (int) $convertValue < 10 ) {
                            $convertValue = " " . (int) $convertValue;
                        }
                        if( $noPad === true ) {
                            $convertValue = trim( $convertValue );
                        }
                        break;
                    case "m":
                    case "-m":
                        if( strlen( $convertValue ) == 2 ) $noPad = true;
                        $convertValue = strftime( "%m", $time );
                        if( $noPad === true ) {
                            $convertValue = (string) (int) $convertValue;
                        }
                        break;
                    case "n":
                        $convertValue = "\n";
                        break;
                    case "r":
                        $convertValue = strftime( "%I:%M:%S %p", $time );
                        break;
                    case "s":
                        $convertValue = $time;
                        break;
                    case "t":
                        $convertValue = "\t";
                        break;
                    case "u":
                        $convertValue = date( "N", $time );
                        break;
                    default:
                        return false;
                }
                
                if( !is_array( $format ) ) return false;
                
                foreach( $format as $segment => $string ) {
                    if( !empty( $string ) ) {
                        $temp = self::strftime( $string, $time, $botLanguage );
                        if( $temp === false ) {
                            return false;
                        }
                        $output .= $temp;
                    }
                    
                    if( $segment !== count( $format ) - 1 ) {
                        $output .= $convertValue;
                    }
                }
            } else {
                if( preg_match( '/\%(\-?[CDFGPRTVeghklnrstiu])/', $format, $match ) ) {
                    $output = self::strftime( $format, $time, $botLanguage, $match[1] );
                } else {
                    $output = strftime( $format, $time );
                }
            }
            if( $botLanguage === true ) {
                if( !isset( $locales[BOTLANGUAGE] ) && method_exists( "IABotLocalization", "localize_" . BOTLANGUAGE ) ) {
                    $tmp = "localize_" . BOTLANGUAGE;
                    $output = IABotLocalization::$tmp( $output, false );
                }
                if( method_exists( "IABotLocalization", "localize_" . BOTLANGUAGE . "_extend" ) ) {
                    $tmp = "localize_" . BOTLANGUAGE . "_extend";
                    $output = IABotLocalization::$tmp( $output, false );
                }
            } elseif( defined( 'USERLANGUAGE' ) ) {
                if( !isset( $locales[USERLANGUAGE] ) && method_exists( "IABotLocalization", "localize_" . USERLANGUAGE ) ) {
                    $tmp = "localize_" . USERLANGUAGE;
                    $output = IABotLocalization::$tmp( $output, false );
                }
                if( method_exists( "IABotLocalization", "localize_" . USERLANGUAGE . "_extend" ) ) {
                    $tmp = "localize_" . USERLANGUAGE . "_extend";
                    $output = IABotLocalization::$tmp( $output, false );
                }
            }
            
            return $output;
        }
        
        /**
         * Verify that newdata is actually different from old data
         *
         * @access public
         * @static
         *
         * @param mixed $link
         *
         * @return bool Whether the data in the link array contains new data from the old data.
         * @copyright Copyright (c) 2015-2017, Maximilian Doerr
         *
         * @author Maximilian Doerr (Cyberpower678)
         * @license https://www.gnu.org/licenses/gpl.txt
         */
        public static function newIsNew( $link ) {
            $t = false;
            if( $link['link_type'] == "reference" ) {
                foreach( $link['reference'] as $tid => $tlink ) {
                    if( isset( $tlink['newdata'] ) ) {
                        foreach( $tlink['newdata'] as $parameter => $value ) {
                            if( !isset( $tlink[$parameter] ) || $value != $tlink[$parameter] ) $t = true;
                        }
                    }
                }
            } elseif( isset( $link[$link['link_type']]['newdata'] ) ) {
                foreach(
                        $link[$link['link_type']]['newdata'] as $parameter => $value
                        ) {
                    if( !isset( $link[$link['link_type']][$parameter] ) ||
                       $value != $link[$link['link_type']][$parameter]
                       ) {
                        $t = true;
                    }
                }
            }
            
            return $t;
        }
        
        /**
         * Generate a string to replace the old string
         *
         * @param array $link Details about the new link including newdata being injected.
         *
         * @param Parser $this
         *
         * @return string New source string
         * @access public
         * @author Maximilian Doerr (Cyberpower678)
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2017, Maximilian Doerr
         */
        public function generateString( $link ) {
            $out = "";
            if( $link['link_type'] != "reference" ) {
                if( strpos( $link[$link['link_type']]['link_string'], "\n" ) !== false ) $multiline = true;
                $mArray = DataGenerator::mergeNewData( $link[$link['link_type']] );
                if( isset( $link[$link['link_type']]['redundant_archives'] ) ) $tArray =
                    array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['ignore_tags'] );
                else $tArray =
                    array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'],
                                $this->commObject->config['ignore_tags']
                                );
                $regex = $this->fetchTemplateRegex( $tArray );
                //Clear the existing archive, dead, and ignore tags from the remainder.
                //Why ignore?  It gives a visible indication that there's a bug in IABot.
                $remainder = trim( preg_replace( $regex, "", $mArray['remainder'] ) );
                if( isset( $mArray['archive_string'] ) ) {
                    $remainder =
                    trim( str_replace( $mArray['archive_string'], "", $remainder ) );
                }
            }
            //Beginning of the string
            //For references...
            if( $link['link_type'] == "reference" ) {
                //If we have a multiline reference then make sure the ref tags have a line break.
                if( strpos( $link['reference']['open'], "<ref" ) === false ) $refMultiline = true;
                else $refMultiline = false;
                //Build the opening reference tag with parameters, when dealing with references.
                if( $refMultiline ) $out .= "{$link['reference']['open']}\n";
                else $out .= $link['reference']['open'];
                //Store the original link string in sub output buffer.
                $tout = trim( $link['reference']['link_string'] );
                //Process each individual source in the reference
                $offsetAdd = 0 - strpos( $link['reference']['link_string'], $tout );
                //Delete it, to avoid confusion when processing the array.
                unset( $link['reference']['link_string'] );
                foreach( $link['reference'] as $tid => $tlink ) {
                    if( !is_numeric( $tid ) ) continue;
                    if( strpos( $tlink['link_string'], "\n" ) !== false ) $multiline = true;
                    //Create an sub-sub-output buffer.
                    $ttout = "";
                    //If the ignore tag is set on this specific source, move on to the next.
                    if( isset( $tlink['ignore'] ) && $tlink['ignore'] === true ) continue;
                    if( !is_int( $tid ) ) continue;
                    //Merge the newdata index with the link array.
                    $mArray = DataGenerator::mergeNewData( $tlink );
                    if( isset( $tlink['redundant_archives'] ) ||
                       ( $tlink['has_archive'] === true && $tlink ['archive_type'] == "template-swallow" ) ) $tArray =
                        array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['ignore_tags'] );
                    else $tArray =
                        array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'],
                                    $this->commObject->config['ignore_tags']
                                    );
                    $regex = $this->fetchTemplateRegex( $tArray );
                    //Clear the existing archive, dead, and ignore tags from the remainder.
                    //Why ignore?  It gives a visible indication that there's a bug in IABot.
                    $remainder = trim( preg_replace( $regex, "", $mArray['remainder'] ) );
                    //Clear the archive string if it exists
                    if( isset( $mArray['archive_string'] ) ) $remainder =
                        str_replace( $mArray['archive_string'], "", $remainder );
                    //If handling a plain link, or a plain archive link...
                    if( $mArray['link_type'] == "link" ||
                       ( $mArray['is_archive'] === true && $mArray['archive_type'] == "link" )
                       ) {
                        //Store source link string into sub-sub-output buffer.
                        $ttout .= $mArray['link_string'];
                        //For other archives that don't have archive templates or there is no suitable template, replace directly.
                        if( $tlink['is_archive'] === false && $mArray['is_archive'] === true ) {
                            $ttout =
                            str_replace( $mArray['original_url'],
                                        DataGenerator::wikiSyntaxSanitize( $mArray['archive_url'] ),
                                        $ttout
                                        );
                        } elseif( $tlink['is_archive'] === true && $mArray['is_archive'] === true ) {
                            $ttout =
                            str_replace( $mArray['old_archive'],
                                        DataGenerator::wikiSyntaxSanitize( $mArray['archive_url'] ),
                                        $ttout
                                        );
                        } elseif( $tlink['is_archive'] === true && $mArray['is_archive'] === false ) {
                            $ttout =
                            str_replace( $mArray['old_archive'], DataGenerator::wikiSyntaxSanitize( $mArray['url'] ),
                                        $ttout
                                        );
                        }
                    } //If handling a cite template...
                    elseif( $mArray['link_type'] == "template" ) {
                        //Build a clean cite template with the set parameters.
                        $ttout .= "{{" . $mArray['link_template']['name'];
                        if( $mArray['link_template']['format'] == "multiline-pretty" ) $ttout .= "\n";
                        else $ttout .= substr( $mArray['link_template']['format'],
                                              strpos( $mArray['link_template']['format'], "{value}" ) + 7
                                              );
                        foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
                            $mArray['link_template']['parameters'][$parameter] = $value;
                        }
                        if( $mArray['link_template']['format'] == "multiline-pretty" ) {
                            $strlen = 0;
                            foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
                                $strlen = max( $strlen, strlen( $parameter ) );
                            }
                            foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
                                $ttout .= " |" . str_pad( $parameter, $strlen, " " ) . " = $value\n";
                            }
                        } else foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
                            $ttout .= "|" . str_replace( "{key}", $parameter,
                                                        str_replace( "{value}", $value, $mArray['link_template']['format']
                                                                    )
                                                        );
                        }
                        $ttout .= "}}";
                    }
                    //If the detected archive is invalid, replace with the original URL.
                    if( $mArray['is_archive'] === true && isset( $mArray['invalid_archive'] ) ) {
                        $ttout = str_replace( $mArray['iarchive_url'], $mArray['url'], $ttout );
                    }
                    //If tagged dead, and set as a template, add tag.
                    if( $mArray['tagged_dead'] === true && $mArray['tag_type'] == "template" ) {
                        foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) {
                            $mArray['tag_template']['parameters'][$parameter] = $value;
                        }
                        if( $mArray['tag_type'] == "template" ) {
                            $ttout .= "{{" . $mArray['tag_template']['name'];
                            foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) {
                                $ttout .= "|$parameter=$value ";
                            }
                            $ttout .= "}}";
                        } elseif( $mArray['tag_type'] == "template-swallow" ) {
                            $tttout = "{{" . $mArray['tag_template']['name'];
                            foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) {
                                $tttout .= "|$parameter=$value ";
                            }
                            $tttout .= "}}";
                            $ttout = str_replace( $mArray['link_string'], $tttout, $ttout );
                        }
                    }
                    //Attach the cleaned remainder.
                    $ttout .= $remainder;
                    //Attach archives as needed
                    if( $mArray['has_archive'] === true ) {
                        //For archive templates.
                        if( $mArray['archive_type'] == "template" || $mArray['archive_type'] == "template-swallow" ) {
                            foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) {
                                $mArray['archive_template']['parameters'][$parameter] = $value;
                            }
                            if( $tlink['has_archive'] === true && $tlink['archive_type'] == "link" ) {
                                $ttout =
                                str_replace( $mArray['old_archive'],
                                            DataGenerator::wikiSyntaxSanitize( $mArray['archive_url'] ),
                                            $ttout
                                            );
                            } else {
                                $tttout = " {{" . $mArray['archive_template']['name'];
                                foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) {
                                    $tttout .= "|$parameter=$value ";
                                }
                                $tttout .= "}}";
                                if( isset( $mArray['archive_string'] ) ) {
                                    $ttout = str_replace( $mArray['archive_string'], trim( $tttout ), $ttout );
                                } else {
                                    if( $mArray['archive_type'] == "template" ) $ttout .= $tttout;
                                    elseif( $mArray['archive_type'] == "template-swallow" ) $ttout =
                                    str_replace( $tlink['link_string'], $tttout, $ttout );
                                }
                            }
                            
                            $ttout = trim( $ttout );
                        }
                        if( $mArray['is_archive'] === true && isset( $mArray['archive_string'] ) &&
                           $mArray['archive_type'] != "link" ) {
                            $ttout =
                            str_replace( $mArray['archive_string'], "", $ttout );
                        }
                    }
                    //Search for source's entire string content, and replace it with the new string from the sub-sub-output buffer, and save it into the sub-output buffer.
                    $tout =
                    DataGenerator::str_replace( $tlink['string'], $ttout, $tout, $count, 1,
                                               $tlink['offset'] + $offsetAdd
                                               );
                    $offsetAdd += strlen( $ttout ) - strlen( $tlink['string'] );
                }
                
                //Attach contents of sub-output buffer, to main output buffer.
                $out .= $tout;
                //Close reference.
                if( $refMultiline ) $out .= "\n{$link['reference']['close']}";
                else $out .= $link['reference']['close'];
                
                return $out;
                
            } elseif( $link['link_type'] == "externallink" ) {
                //Attach the external link string to the output buffer.
                $out .= $link['externallink']['link_string'];
            } elseif( $link['link_type'] == "stray" && !empty( $mArray['link_string'] ) ) {
                if( $mArray['link_type'] == "link" ) $out .= $mArray['link_string'];
                elseif( $mArray['link_type'] == "template" ) {
                    $out .= "{{" . $mArray['link_template']['name'];
                    if( $mArray['link_template']['format'] == "multiline-pretty" ) $out .= "\n";
                    else $out .= substr( $mArray['link_template']['format'],
                                        strpos( $mArray['link_template']['format'], "{value}" ) + 7
                                        );
                    if( $mArray['link_template']['format'] == "multiline-pretty" ) {
                        $strlen = 0;
                        foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
                            $strlen = max( $strlen, strlen( $parameter ) );
                        }
                        foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
                            $out .= " |" . str_pad( $parameter, $strlen, " " ) . " = $value\n";
                        }
                    } else foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
                        $out .= "|" . str_replace( "{key}", $parameter,
                                                  str_replace( "{value}", $value, $mArray['link_template']['format']
                                                              )
                                                  );
                    }
                    $out .= "}}";
                }
            } elseif( $link['link_type'] == "template" || $link['link_type'] == "stray" ) {
                //Create a clean cite template
                if( $link['link_type'] == "template" ) {
                    $out .= "{{" . $link['template']['name'];
                } elseif( $link['link_type'] == "stray" ) $out .= "{{" . $mArray['link_template']['name'];
                if( $mArray['link_template']['format'] == "multiline-pretty" ) $out .= "\n";
                else $out .= substr( $mArray['link_template']['format'],
                                    strpos( $mArray['link_template']['format'], "{value}" ) + 7
                                    );
                if( $mArray['link_template']['format'] == "multiline-pretty" ) {
                    $strlen = 0;
                    foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
                        $strlen = max( $strlen, strlen( $parameter ) );
                    }
                    foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
                        $out .= " |" . str_pad( $parameter, $strlen, " " ) . " = $value\n";
                    }
                } else foreach( $mArray['link_template']['parameters'] as $parameter => $value ) {
                    $out .= "|" . str_replace( "{key}", $parameter,
                                              str_replace( "{value}", $value, $mArray['link_template']['format']
                                                          )
                                              );
                }
                $out .= "}}";
            }
            //Add dead link tag if needed.
            if( $mArray['tagged_dead'] === true ) {
                if( $mArray['tag_type'] == "template" ) {
                    $out .= "{{" . $mArray['tag_template']['name'];
                    foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
                    $out .= "}}";
                } elseif( $mArray['tag_type'] == "template-swallow" ) {
                    $tout = "{{" . $mArray['tag_template']['name'];
                    foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) {
                        $tout .= "|$parameter=$value ";
                    }
                    $tout .= "}}";
                    $out = str_replace( $mArray['link_string'], $tout, $out );
                }
            }
            //Add remainder
            $out .= $remainder;
            //Add the archive if needed.
            if( $mArray['has_archive'] === true ) {
                if( $link['link_type'] == "externallink" ) {
                    if( isset( $mArray['old_archive'] ) ) {
                        $out =
                        str_replace( $mArray['old_archive'], DataGenerator::wikiSyntaxSanitize( $mArray['archive_url'] ),
                                    $out
                                    );
                    } else $out =
                        str_replace( $mArray['original_url'], DataGenerator::wikiSyntaxSanitize( $mArray['archive_url'] ), $out
                                    );
                } elseif( $mArray['archive_type'] == "template" ) {
                    $out .= " {{" . $mArray['archive_template']['name'];
                    foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) {
                        $out .= "|$parameter=$value ";
                    }
                    $out .= "}}";
                }
            }
            
            return $out;
        }
        
        /**
         * Merge the new data in a custom array_merge function
         *
         * @param array $link An array containing details and newdata about a specific reference.
         * @param bool $recurse Is this function call a recursive call?
         *
         * @static
         * @access public
         * @return array Merged data
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2018, Maximilian Doerr
         * @author Maximilian Doerr (Cyberpower678)
         */
        public static function mergeNewData( $link, $recurse = false ) {
            $returnArray = [];
            if( $recurse !== false ) {
                foreach( $link as $parameter => $value ) {
                    if( isset( $recurse[$parameter] ) && !is_array( $recurse[$parameter] ) && !is_array( $value ) ) {
                        $returnArray[$parameter] = $recurse[$parameter];
                    } elseif( isset( $recurse[$parameter] ) && is_array( $recurse[$parameter] ) && is_array( $value ) ) {
                        $returnArray[$parameter] = self::mergeNewData( $value, $recurse[$parameter] );
                    } elseif( isset( $recurse[$parameter] ) ) $returnArray[$parameter] = $recurse[$parameter];
                    else $returnArray[$parameter] = $value;
                }
                foreach( $recurse as $parameter => $value ) {
                    if( !isset( $returnArray[$parameter] ) ) $returnArray[$parameter] = $value;
                }
                
                return $returnArray;
            }
            if( isset( $link['newdata'] ) ) {
                $newdata = $link['newdata'];
                unset( $link['newdata'] );
            } else $newdata = [];
            foreach( $link as $parameter => $value ) {
                if( isset( $newdata[$parameter] ) && !is_array( $newdata[$parameter] ) && !is_array( $value ) ) {
                    $returnArray[$parameter] = $newdata[$parameter];
                } elseif( isset( $newdata[$parameter] ) && is_array( $newdata[$parameter] ) && is_array( $value ) ) {
                    $returnArray[$parameter] = self::mergeNewData( $value, $newdata[$parameter] );
                } elseif( isset( $newdata[$parameter] ) ) $returnArray[$parameter] = $newdata[$parameter];
                else $returnArray[$parameter] = $value;
            }
            foreach( $newdata as $parameter => $value ) {
                if( !isset( $returnArray[$parameter] ) ) $returnArray[$parameter] = $value;
            }
            
            return $returnArray;
        }
        
        /**
         * Generates a regex that detects the given list of escaped templates.
         *
         * @param array $escapedTemplateArray A list of bracketed templates that have been escaped to search for.
         * @param bool $optional Make the reqex not require additional template parameters.
         *
         * @param Parser $this
         *
         * @return string Generated regex
         * @author Maximilian Doerr (Cyberpower678)
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2018, Maximilian Doerr
         */
        public function fetchTemplateRegex( $escapedTemplateArray, $optional = true ) {
            if( $optional === true ) {
                $returnRegex = $this->templateRegexOptional;
            } else $returnRegex = $this->templateRegexMandatory;
            
            if( !empty( $escapedTemplateArray ) ) {
                $escapedTemplateArray = implode( '|', $escapedTemplateArray );
                $returnRegex = str_replace( "{{{{templates}}}}", $escapedTemplateArray, $returnRegex );
            } else {
                $returnRegex = str_replace( "{{{{templates}}}}", "nullNULLfalseFALSE", $returnRegex );
            }
            
            
            return $returnRegex;
        }
        
        /**
         * Sanitize wikitext to render correctly
         *
         * @access public
         * @static
         *
         * @param string $input Input string
         * @param bool $isInTemplate Whether string is in a template
         * @param bool $sanitizeTemplates Whether to sanitize template brackets
         *
         * @return string Sanitized string
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2017, Maximilian Doerr
         *
         * @author Maximilian Doerr (Cyberpower678)
         */
        public static function wikiSyntaxSanitize( $input, $isInTemplate = false, $sanitizeTemplates = false ) {
            $output = str_replace( "[", "&#91;", $input );
            $output = str_replace( "]", "&#93;", $output );
            
            if( $isInTemplate ) {
                $output = str_replace( "|", "{{!}}", $output );
            }
            
            if( $sanitizeTemplates ) {
                $output = str_replace( "{{", "{{((}}", $output );
                $output = str_replace( "}}", "{{))}}", $output );
            }
            
            return $output;
        }
        
        /**
         * A custom str_replace function with more dynamic abilities such as a limiter, and offset support, and alternate
         * replacement strings.
         *
         * @param $search String to search for
         * @param $replace String to replace with
         * @param $subject Subject to search
         * @param int|null $count Number of replacements made
         * @param int $limit Number of replacements to limit to
         * @param int $offset Where to begin string searching in the subject
         * @param string $replaceOn Try to make the replacement on this string with the string obtained at the offset of
         *     subject
         *
         * @access public
         * @return Replacement string
         * @author Maximilian Doerr (Cyberpower678)
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2018, Maximilian Doerr
         */
        public static function str_replace( $search, $replace, $subject, &$count = null, $limit = -1, $offset = 0,
                                           $replaceOn = null
                                           ) {
            if( !is_null( $replaceOn ) ) {
                $searchCounter = 0;
                $t1Offset = -1;
                if( ( $tenAfter = substr( $subject, $offset + strlen( $search ), 10 ) ) !== false ) {
                    $t1Offset = strpos( $replaceOn, $search . $tenAfter );
                } elseif( $offset - 10 > -1 && ( $tenBefore = substr( $subject, $offset - 10, 10 ) ) !== false ) {
                    $t1Offset = strpos( $replaceOn, $tenBefore . $search ) + 10;
                }
                
                $t2Offset = -1;
                while( ( $t2Offset = strpos( $subject, $search, $t2Offset + 1 ) ) !== false && $offset >= $t2Offset ) {
                    $searchCounter++;
                }
                $t2Offset = -1;
                for( $i = 0; $i < $searchCounter; $i++ ) {
                    $t2Offset = strpos( $replaceOn, $search, $t2Offset + 1 );
                    if( $t2Offset === false ) break;
                }
                if( $t1Offset !== false && $t2Offset !== false ) $offset = max( $t1Offset, $t2Offset );
                elseif( $t1Offset === false ) $offset = $t2Offset;
                elseif( $t2Offset === false ) $offset = $t1Offset;
                else return $replaceOn;
                
                $subjectBefore = substr( $replaceOn, 0, $offset );
                $subjectAfter = substr( $replaceOn, $offset );
            } else {
                $subjectBefore = substr( $subject, 0, $offset );
                $subjectAfter = substr( $subject, $offset );
            }
            
            $pos = strpos( $subjectAfter, $search );
            
            $count = 0;
            while( ( $limit == -1 || $limit > $count ) && $pos !== false ) {
                $subjectAfter = substr_replace( $subjectAfter, $replace, $pos, strlen( $search ) );
                $count++;
                $pos = strpos( $subjectAfter, $search );
            }
            
            return $subjectBefore . $subjectAfter;
        }
        
        /**
         * Generates an appropriate archive template if it can.
         *
         * @access protected
         *
         * @param $link Current link being modified
         * @param $temp Current temp result from fetchResponse
         *
         * @param Parser $parser
         *
         * @return bool If successful or not
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2017, Maximilian Doerr
         *
         * @author Maximilian Doerr (Cyberpower678)
         */
        public function generateNewArchiveTemplate( &$link, &$temp, Parser $parser ) {
            //We need the archive host, to pick the right template.
            if( !isset( $link['newdata']['archive_host'] ) ) $link['newdata']['archive_host'] =
                self::getArchiveHost( $temp['archive_url'] );
            
            //If the archive template is being used improperly, delete the parameters, and start fresh.
            if( $link['has_archive'] === true &&
               $link['archive_type'] == "invalid"
               ) unset( $link['archive_template']['parameters'] );
            
            $archives = [];
            
            foreach( $this->commObject->config['using_archives'] as $archive ) {
                if( @in_array( $archive, $parser->commObject->config['deprecated_archives'] ) ) continue;
                
                foreach(
                        $this->commObject->config['all_archives'][$archive]['archivetemplatedefinitions']['services'] as
                        $service => $junk
                        ) {
                    $archives[$service] = $archive;
                }
            }
            
            if( isset( $archives["@{$link['newdata']['archive_host']}"] ) ) {
                $useArchive = $archives["@{$link['newdata']['archive_host']}"];
            } elseif( isset( $archives["@default"] ) ) {
                $useArchive = $archives['@default'];
            } else return false;
            
            if( isset( $parser->commObject->config["darchive_$useArchive"] ) ) {
                $link['newdata']['archive_template']['name'] =
                trim( DB::getConfiguration( WIKIPEDIA, "wikiconfig", "darchive_$useArchive" )[0], "{}" );
                
                $magicwords = [];
                if( isset( $link['url'] ) ) {
                    $magicwords['url'] = $link['url'];
                    if( !empty( $link['fragment'] ) ) $magicwords['url'] .= "#" . $link['fragment'];
                    $magicwords['url'] = self::wikiSyntaxSanitize( $magicwords['url'], true );
                }
                if( isset( $link['newdata']['archive_time'] ) ) $magicwords['archivetimestamp'] =
                    $link['newdata']['archive_time'];
                if( isset( $link['newdata']['archive_url'] ) ) {
                    $magicwords['archiveurl'] = $link['newdata']['archive_url'];
                    /*if( !empty( $link['newdata']['archive_fragment'] ) ) $magicwords['archiveurl'] .= "#" .
                     $link['newdata']['archive_fragment'];
                     elseif( !empty( $link['fragment'] ) ) $magicwords['archiveurl'] .= "#" . $link['fragment'];*/
                    $magicwords['archiveurl'] = self::wikiSyntaxSanitize( $magicwords['archiveurl'], true );
                }
                $magicwords['timestampauto'] = $this->retrieveDateFormat( $link['string'], $parser );
                $magicwords['linkstring'] = $link['link_string'];
                $magicwords['remainder'] = $link['remainder'];
                $magicwords['string'] = $link['string'];
                
                if( empty( $link['title'] ) ) {
                    if( ( $tmp = $parser->commObject->config['template_definitions'][WIKIPEDIA]->get() ) &&
                       !empty( $tmp['default-title'] ) )
                        $magicwords['title'] = $tmp['default-title'];
                    else $magicwords['title'] = "—";
                } else $magicwords['title'] = self::wikiSyntaxSanitize( $link['title'], true );
                
                if( $link['newdata']['archive_host'] == "webcite" ) {
                    if( preg_match( '/\/\/(?:www\.)?webcitation.org\/(\S*?)\?(\S+)/i', $link['newdata']['archive_url'],
                                   $match
                                   ) ) {
                        if( strlen( $match[1] ) === 9 ) {
                            $magicwords['microepochbase62'] = $match[1];
                            $microepoch = $magicwords['microepoch'] = API::to10( $match[1], 62 );
                            $magicwords['epoch'] = floor( $microepoch / 1000000 );
                            $magicwords['epochbase62'] = API::toBase( floor( $microepoch / 1000000 ), 62 );
                        } else {
                            $magicwords['microepochbase62'] = API::toBase( $match[1], 62 );
                            $magicwords['microepoch'] = $match[1];
                            $magicwords['epoch'] = floor( $magicwords['microepoch'] / 1000000 );
                            $magicwords['epochbase62'] = API::toBase( floor( $magicwords['microepoch'] / 1000000 ), 62 );
                        }
                    }
                } else {
                    $magicwords['epoch'] = $link['newdata']['archive_time'];
                    $magicwords['epochbase62'] = API::toBase( $link['newdata']['archive_time'], 62 );
                    $magicwords['microepoch'] = $link['newdata']['archive_time'] * 1000000;
                    $magicwords['microepochbase62'] = API::toBase( $link['newdata']['archive_time'] * 1000000, 62 );
                }
                
                if( !isset( $parser->commObject->config['all_archives'][$useArchive]['archivetemplatedefinitions']['services']["@{$link['newdata']['archive_host']}"] ) )
                    $useService = "@default";
                else $useService = "@{$link['newdata']['archive_host']}";
                
                if( $parser->commObject->config['all_archives'][$useArchive]['templatebehavior'] == "swallow" )
                    $link['newdata']['archive_type'] = "template-swallow";
                else $link['newdata']['archive_type'] = "template";
                
                foreach(
                        $parser->commObject->config['all_archives'][$useArchive]['archivetemplatedefinitions']['services'][$useService]
                        as $category => $categoryData
                        ) {
                    if( $link['newdata']['archive_type'] == "template" ) {
                        if( $category == "title" ) continue;
                    }
                    if( is_array( $categoryData[0] ) ) $dataIndex = $categoryData[0]['index'];
                    else $dataIndex = $categoryData[0];
                    
                    $paramIndex =
                    $parser->commObject->config['all_archives'][$useArchive]['archivetemplatedefinitions']['data'][$dataIndex]['mapto'][0];
                    
                    $link['newdata']['archive_template']['parameters'][$parser->commObject->config['all_archives'][$useArchive]['archivetemplatedefinitions']['params'][$paramIndex]] =
                    $parser->commObject->config['all_archives'][$useArchive]['archivetemplatedefinitions']['data'][$dataIndex]['valueString'];
                }
                
                if( isset( $link['newdata']['archive_template']['parameters'] ) )
                    foreach( $link['newdata']['archive_template']['parameters'] as $param => $value ) {
                        $link['newdata']['archive_template']['parameters'][$param] =
                        $parser->commObject->getConfigText( $value, $magicwords );
                    }
            } else return false;
            
            return true;
        }
        
        public static function getArchiveHost( $url, &$data = [] ) {
            $value = API::isArchive( $url, $data );
            if( $value === false ) {
                return "unknown";
            } else return $data['archive_host'];
        }
        
        /**
         * Get page date formatting standard
         *
         * @param bool|string $default Return default format, or return supplied date format of timestamp, provided a page
         *     tag doesn't override it.
         *
         * @param Parser $parser
         *
         * @return string Format to be fed in time()
         * @access protected
         * @author Maximilian Doerr (Cyberpower678)
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2018, Maximilian Doerr
         */
        public function retrieveDateFormat( $default = false ) {
            if( $default === true ) return $this->commObject->config['dateformat']['syntax']['@default']['format'];
            else {
                foreach( $this->commObject->config['dateformat']['syntax'] as $index => $rule ) {
                    if( isset( $rule['regex'] ) &&
                       preg_match( '/' . $rule['regex'] . '/i', $this->commObject->content ) ) return $rule['format'];
                    elseif( !isset( $rule['regex'] ) ) {
                        if( !is_bool( $default ) &&
                           DataGenerator::strptime( $default, $rule['format'] ) !== false ) return $rule['format'];
                        elseif( !is_bool( $default ) || $default === false ) {
                            if( $default === false ) $default = $this->commObject->content;
                            
                            $searchRegex = $rule['format'];
                            
                            $searchRegex = preg_quote( $searchRegex, "/" );
                            
                            $searchRegex = str_replace( "%j", "\d{3}", $searchRegex );
                            $searchRegex = str_replace( "%r", "%I:%M:%S %p", $searchRegex );
                            $searchRegex = str_replace( "%R", "%H:%M", $searchRegex );
                            $searchRegex = str_replace( "%T", "%H:%M:%S", $searchRegex );
                            $searchRegex = str_replace( "%D", "%m/%d/%y", $searchRegex );
                            $searchRegex = str_replace( "%F", "%Y-%m-%d", $searchRegex );
                            
                            $searchRegex = preg_replace( '/\%(?:\\\-)?[uw]/', '\\d', $searchRegex );
                            $searchRegex = preg_replace( '/\%(?:\\\-)?[deUVWmCgyHkIlMS]/', '\\d\\d?', $searchRegex );
                            $searchRegex = preg_replace( '/\%(?:\\\-)?[GY]/', '\\d{4}', $searchRegex );
                            $searchRegex = preg_replace( '/\%[aAbBhzZ]/', '\\p{L}+', $searchRegex );
                            
                            if( preg_match( '/' . $searchRegex . '/', $default, $match ) &&
                               DataGenerator::strptime( $match[0], str_replace( "%-", "%", $rule['format'] ) ) !==
                               false ) return $rule['format'];
                            elseif( DataGenerator::strptime( $default, "%c" ) !== false ) return "%c";
                            elseif( DataGenerator::strptime( $default, "%x" ) !== false ) return "%x";
                        }
                    }
                }
                
                return $this->commObject->config['dateformat']['syntax']['@default']['format'];
            }
        }
        
        /**
         * A customized strptime function that automatically bridges the gap between Windows, Linux, and Mac OSes.
         *
         * @param string $format Formatting string in the Linux format
         * @param int|bool $time A unix epoch.  Default current time.
         * @param bool|string Passed in recursively.  Ignore this value.
         *
         * @access public
         * @static
         * @return int|false A parsed time array or false on failure.
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2017, Maximilian Doerr
         * @author Maximilian Doerr (Cyberpower678)
         */
        public static function strptime( $date, $format, $botLanguage = true ) {
            global $locales;
            
            $format = str_replace( "%-", "%", $format );
            
            if( $botLanguage === true ) {
                if( !isset( $locales[BOTLANGUAGE] ) && method_exists( "IABotLocalization", "localize_" . BOTLANGUAGE ) ) {
                    $tmp = "localize_" . BOTLANGUAGE;
                    $date = IABotLocalization::$tmp( $date, true );
                } elseif( method_exists( "IABotLocalization", "localize_" . BOTLANGUAGE . "_extend" ) ) {
                    $tmp = "localize_" . BOTLANGUAGE . "_extend";
                    $date = IABotLocalization::$tmp( $date, true );
                }
            } elseif( defined( 'USERLANGUAGE' ) ) {
                if( !isset( $locales[USERLANGUAGE] ) && method_exists( "IABotLocalization", "localize_" . USERLANGUAGE ) ) {
                    $tmp = "localize_" . USERLANGUAGE;
                    $date = IABotLocalization::$tmp( $date, true );
                } elseif( method_exists( "IABotLocalization", "localize_" . USERLANGUAGE . "_extend" ) ) {
                    $tmp = "localize_" . USERLANGUAGE . "_extend";
                    $date = IABotLocalization::$tmp( $date, true );
                }
            }
            
            return strptime( $date, $format );
        }
        
        /**
         * Generates an appropriate citation template without altering existing parameters.
         *
         * @access protected
         *
         * @param $link Current link being modified
         * @param Parser $parser
         *
         * @return bool If successful or not
         * @author Maximilian Doerr (Cyberpower678)
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2018, Maximilian Doerr
         *
         */
        public function generateNewCitationTemplate( &$link, Parser $parser ) {
            if( !isset( $link['link_template']['template_map'] ) ) {
                $tmp = $this->commObject->config['template_definitions'][WIKIPEDIA]->get();
                if( empty( $tmp['default-template'] ) ) return false;
                $link['newdata']['link_template']['format'] = "{key}={value} ";
                $link['newdata']['link_template']['name'] = $tmp['default-template'];
                
                $link['newdata']['link_template']['template_map'] =
                DataGenerator::getCiteMap( $link['newdata']['link_template']['name'],
                                          $this->commObject->config['template_definitions']
                                          );
                if( empty( $link['newdata']['link_template']['template_map'] ) ) return false;
                else $map = $link['newdata']['link_template']['template_map'];
                
            } else $map = $link['link_template']['template_map'];
            
            //If the template doesn't support archive URLs, then exit out.
            if( !isset( $map['services']['@default']['archive_url'] ) ) return false;
            
            //If there was no link template array, then create an empty one.
            if( !isset( $link['link_template'] ) ) $link['link_template'] = [];
            
            $link['newdata']['archive_type'] = "parameter";
            //We need to flag it as dead so the string generator knows how to behave, when assigning the deadurl parameter.
            if( $link['tagged_dead'] === true || $link['is_dead'] === true ) $link['newdata']['tagged_dead'] = true;
            else $link['newdata']['tagged_dead'] = false;
            $link['newdata']['tag_type'] = "parameter";
            
            $magicwords = [];
            if( isset( $link['url'] ) ) {
                $magicwords['url'] = $link['url'];
                if( !empty( $link['fragment'] ) ) $magicwords['url'] .= "#" . $link['fragment'];
                $magicwords['url'] = DataGenerator::wikiSyntaxSanitize( $magicwords['url'], true );
            }
            if( isset( $link['newdata']['archive_time'] ) ) $magicwords['archivetimestamp'] =
                $link['newdata']['archive_time'];
            $magicwords['accesstimestamp'] = $link['access_time'];
            if( isset( $link['newdata']['archive_url'] ) ) {
                $magicwords['archiveurl'] = $link['newdata']['archive_url'];
                /*if( !empty( $link['newdata']['archive_fragment'] ) ) $magicwords['archiveurl'] .= "#" .
                 $link['newdata']['archive_fragment'];
                 elseif( !empty( $link['fragment'] ) ) $magicwords['archiveurl'] .= "#" . $link['fragment'];*/
                $magicwords['archiveurl'] = DataGenerator::wikiSyntaxSanitize( $magicwords['archiveurl'], true );
            }
            $magicwords['timestampauto'] = $this->retrieveDateFormat( $link['string'], $parser );
            $magicwords['linkstring'] = $link['link_string'];
            $magicwords['remainder'] = $link['remainder'];
            $magicwords['string'] = $link['string'];
            if( !empty( $link['title'] ) ) $magicwords['title'] =
                DataGenerator::wikiSyntaxSanitize( $link['title'], true );
            elseif( ( $tmp = $this->commObject->config['template_definitions'][WIKIPEDIA]->get() ) && !empty( $tmp['default-title'] ) )
            $magicwords['title'] = $tmp['default-title'];
            else $magicwords['title'] = "—";
            $magicwords['epoch'] = $link['newdata']['archive_time'];
            $magicwords['epochbase62'] = API::toBase( $link['newdata']['archive_time'], 62 );
            $magicwords['microepoch'] = $link['newdata']['archive_time'] * 1000000;
            $magicwords['microepochbase62'] = API::toBase( $link['newdata']['archive_time'] * 1000000, 62 );
            
            //When we know we are adding an archive to a dead url, or merging an archive template to a citation template, we can set the deadurl flag to yes.
            //In cases where the original URL was no longer visible, like a template being used directly, are the archive URL being used in place of the original, we set the deadurl flag to "bot: unknown" which keeps the URL hidden, if supported.
            //The remaining cases will receive a deadurl=no.  These are the cases where dead_only is set to 0.
            if( ( $link['tagged_dead'] === true || $link['is_dead'] === true ) ) {
                $magicwords['is_dead'] = "yes";
            } elseif( ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
                     $link['link_type'] == "stray" ) {
                $magicwords['is_dead'] = "usurp";
            } else {
                $magicwords['is_dead'] = "no";
            }
            
            foreach( $map['services']['@default'] as $category => $categoryData ) {
                if( $category == "paywall" ) continue;
                $categoryIndex = 0;
                do {
                    if( is_array( $categoryData[$categoryIndex] ) ) $dataIndex = $categoryData[$categoryIndex]['index'];
                    else $dataIndex = $categoryData[$categoryIndex];
                    
                    $parameter = null;
                    
                    foreach( $map['data'][$dataIndex]['mapto'] as $paramIndex ) {
                        if( is_null( $parameter ) ) $parameter = $map['params'][$paramIndex];
                        
                        if( isset( $link['link_template']['parameters'][$map['params'][$paramIndex]] ) ) {
                            switch( $category ) {
                                case "url":
                                case "access_date":
                                case "title":
                                    goto genCiteLoopBreakout;
                                    break;
                                default:
                                    $parameter = $map['params'][$paramIndex];
                                    if( $map['data'][$dataIndex]['valueString'] == "&mdash;" ) goto genCiteLoopBreakout;
                                    break 2;
                            }
                        }
                    }
                    
                    if( $map['data'][$dataIndex]['valueString'] != "&mdash;" )
                        $link['newdata']['link_template']['parameters'][$parameter] =
                        $map['data'][$dataIndex]['valueString'];
                    else $link['newdata']['link_template']['parameters'][$parameter] = "";
                genCiteLoopBreakout:
                    $categoryIndex++;
                    
                } while( $category == "other" && isset( $categoryData[$categoryIndex] ) );
            }
            
            if( isset( $link['newdata']['link_template']['parameters'] ) )
                foreach( $link['newdata']['link_template']['parameters'] as $param => $value ) {
                    $link['newdata']['link_template']['parameters'][$param] =
                    $this->commObject->getConfigText( $value, $magicwords );
                }
            
            if( empty( $link['link_template'] ) ) unset( $link['link_template'] );
            
            return true;
        }
        
        /**
         * Fetches the correct mapping information for the given Citation template
         *
         * @param string $templateName The name of the template to get the mapping data for
         *
         * @static
         * @access public
         * @return array The template mapping data to use.
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2017, Maximilian Doerr
         * @author Maximilian Doerr (Cyberpower678)
         */
        public static function getCiteMap( $templateName, $templateDefinitions = [], $templateParameters = [],
                                          &$matchValue = 0
                                          ) {
            $templateName = trim( $templateName, "{}" );
            
            $matchValue = 0;
            $templateList = "";
            $templateData = "";
            
            $templateList = $templateDefinitions['template-list']->get();
            
            if( !in_array( "{{{$templateName}}}", $templateList ) ) {
                $templateName = API::getRedirectRoot( API::getTemplateNamespaceName() . ":$templateName" );
                $templateName = substr( $templateName, strlen( API::getTemplateNamespaceName() ) + 1 );
            }
            
            if( isset( $templateDefinitions[$templateName] ) ) $templateData = $templateDefinitions[$templateName]->get();
            else {
                echo "Uh-oh '$templateName' isn't registered in the definitions\n";
                $templateData = [];
            }
            
            if( !empty( $templateParameters ) ) {
                $toTest = [];
                
                if( isset( $templateData[WIKIPEDIA] ) ) $toTest['default'] =
                    $templateData[WIKIPEDIA];
                
                if( isset( $templateData ) ) foreach(
                                                     $templateData as $wiki => $definitions
                                                     ) {
                    if( $wiki == "existsOn" ) continue;
                    if( $wiki == WIKIPEDIA ) continue;
                    if( isset( $definitions['template_map'] ) ) $toTest[] = $definitions;
                }
                
                $bestMatches = [];
                foreach( $toTest as $id => $test ) {
                    $bestMatches[$id] = 0;
                    
                    foreach( $test['template_map']['params'] as $param ) {
                        if( isset( $templateParameters[$param] ) ) $bestMatches[$id]++;
                    }
                }
                
                
                if( empty( $bestMatches ) ) {
                    echo "Found a missing template! ($templateName)\n";
                }
                
                if( isset( $bestMatches['default'] ) ) {
                    if( $bestMatches['default'] > 1 ) return $toTest['default']['template_map'];
                }
                
                $mostMatches = @max( $bestMatches );
                if( $mostMatches === false ) return [];
                else {
                    $bestMatch = array_search( $mostMatches, $bestMatches );
                    
                    if( isset( $toTest[$bestMatch]['matchStats'] ) ) $matchValue =
                        $toTest[$bestMatch]['matchStats']['matchPercentage'];
                    
                    return $toTest[$bestMatch]['template_map'];
                }
                
            } elseif( isset( $templateData['existsOn'] ) &&
                     in_array( WIKIPEDIA, $templateData['existsOn'] ) ) {
                if( isset( $templateData[WIKIPEDIA] ) ) {
                    $test = $templateData[WIKIPEDIA];
                    
                    if( isset( $test['matchStats'] ) ) $matchValue = $test['matchStats']['matchPercentage'];
                    
                    if( isset( $test['template_map'] ) ) return $test['template_map'];
                    else return [];
                }
            } elseif( isset( $templateData['existsOn'][0] ) &&
                     isset( $templateData[$templateData['existsOn'][0]] ) ) {
                $test = $templateData[$templateData['existsOn'][0]];
                
                if( isset( $test['matchStats'] ) ) $matchValue = $test['matchStats']['matchPercentage'];
                
                return $test['template_map'];
            }
            
            return [];
        }
        
        /**
         * Destroys the class
         *
         * @access public
         * @return void
         * @license https://www.gnu.org/licenses/gpl.txt
         * @copyright Copyright (c) 2015-2018, Maximilian Doerr
         * @author Maximilian Doerr (Cyberpower678)
         */
        public function __destruct() {
            $this->commObject = null;
        }
    }
