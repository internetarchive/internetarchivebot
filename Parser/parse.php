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
 * Parser object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */

/**
 * Parser class
 * Allows for the parsing on project specific wiki pages
 * @abstract
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */

use Wikimedia\DeadlinkChecker\CheckIfDead;

class Parser {

	/**
	 * The API class
	 *
	 * @var API
	 * @access public
	 */
	public $commObject;

	/**
	 * The DB2 class
	 *
	 * @var DB2
	 * @access public
	 */
	public $dbObject;

	/**
	 * The CheckIfDead class
	 *
	 * @var CheckIfDead
	 * @access protected
	 */
	protected $deadCheck;

	/**
	 * The Regex for fetching templates with parameters being optional
	 *
	 * @var string
	 * @access protected
	 */
	protected $templateRegexOptional = '/({{{{templates}}}})[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\}/i';

	/**
	 * The Regex for fetching templates with parameters being mandatory
	 *
	 * @var string
	 * @access protected
	 */
	protected $templateRegexMandatory = '/({{{{templates}}}})[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)\}\}/i';

	/**
	 * The regex for detecting proper RfC compliant URLs, with UTF-8 support.
	 * The scheme is not required to match.
	 *
	 * @var string
	 * @access protected
	 */
	protected $schemelessURLRegex = '(?:[a-z0-9\+\-\.]*:)?\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\?\#\[\]]+)*\/?(?:[\?\;][^\s\#\[\]]*)?(?:\#([^\s\#\[\]]*))?';

	/**
	 * The regex for detecting proper RfC compliant URLs, with UTF-8 support.
	 * The scheme is required to match.
	 *
	 * @var string
	 * @access protected
	 */
	protected $schemedURLRegex = '(?:[a-z0-9\+\-\.]*:)\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\?\#\[\]]+)*\/?(?:[\?\;][^\s\#\[\]]*)?(?:\#([^\s\#\[\]]*))?';

	/**
	 * Caches template strings already parsed
	 *
	 * @var array
	 * @access protected
	 */
	protected $templateParamCache = [];

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
		$this->deadCheck = new CheckIfDead( 30, 60, CIDUSERAGENT, true, true );
		if( AUTOFPREPORT === true ) $this->dbObject = new DB2();
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
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array|bool Rendered example string, template data, or false on failure.
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

		$citeMap = Parser::renderTemplateData( $mapString, "citeMap", true, "cite" );

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
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array|bool Rendered example string, template data, or false on failure.
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
	 * Master page analyzer function.  Analyzes the entire page's content,
	 * retrieves specified URLs, and analyzes whether they are dead or not.
	 * If they are dead, the function acts based on onwiki specifications.
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 *
	 * @param array $modifiedLinks Pass back a list of links modified
	 *
	 * @return array containing analysis statistics of the page
	 */
	public function analyzePage( &$modifiedLinks = [] ) {
		if( DEBUG === false || LIMITEDRUN === true ) {
			file_put_contents( IAPROGRESS . WIKIPEDIA . UNIQUEID, serialize( [
				                                                                 'title' => $this->commObject->page,
				                                                                 'id'    => $this->commObject->pageid
			                                                                 ]
			                                                    )
			);
		}
		$dumpcount = 0;
		unset( $tmp );
		echo "Analyzing {$this->commObject->page} ({$this->commObject->pageid})...\n";
		//Tare statistics variables
		$modifiedLinks = [];
		$archiveProblems = [];
		$archived = 0;
		$rescued = 0;
		$notrescued = 0;
		$tagged = 0;
		$waybackadded = 0;
		$otheradded = 0;
		$analyzed = 0;
		$newlyArchived = [];
		$timestamp = date( "Y-m-d\TH:i:s\Z" );
		$history = [];
		$newtext = $this->commObject->content;
		$toCheck = [];
		$toCheckMeta = [];
		if( AUTOFPREPORT === true ) {
			echo "Fetching previous bot revisions...\n";
			$lastRevIDs = $this->commObject->getBotRevisions();
			$lastRevTexts = [];
			$lastRevLinks = [];
			$oldLinks = [];
			if( !empty( $lastRevIDs ) ) {
				$temp = API::getRevisionText( $lastRevIDs );
				foreach( $temp['query']['pages'][$this->commObject->pageid]['revisions'] as $lastRevText ) {
					$lastRevTexts[$lastRevText['revid']] =
						API::openFile( "dump$dumpcount", true, serialize( $lastRevText['*'] ) );
					$dumpcount++;
				}
				unset( $temp );
			}
		}

		if( $this->commObject->config['link_scan'] == 0 ) {
			echo "Fetching all external links...\n";
			$links = $this->getExternalLinks();
			if( isset( $lastRevTexts ) ) foreach( $lastRevTexts as $id => $lastRevText ) {
				$lastRevLinks[$id] = API::openFile( "dump$dumpcount", true, serialize( $this->getExternalLinks( false,
				                                                                                                unserialize( API::readFile( $lastRevText
				                                                                                                )
				                                                                                                )
				)
				)
				);
				$dumpcount++;
			}
		} else {
			echo "Fetching all references...\n";
			$links = $this->getReferences();
			if( isset( $lastRevTexts ) ) foreach( $lastRevTexts as $id => $lastRevText ) {
				$lastRevLinks[$id] = API::openFile( "dump$dumpcount", true, serialize( $this->getReferences( false,
				                                                                                                unserialize( API::readFile( $lastRevText
				                                                                                                )
				                                                                                                )
				                                                    )
				                                                    )
				);
				$dumpcount++;
			}
		}
		$analyzed = $links['count'];
		unset( $links['count'] );

		//Process the links
		$checkResponse = $archiveResponse = $fetchResponse = $toArchive = $toFetch = [];
		//Perform a 3 phase process.
		//Phases 1 and 2 collect archive information based on the configuration settings on wiki, needed for further analysis.
		//Phase 3 does the actual rescuing.
		for( $i = 0; $i < 3; $i++ ) {
			switch( $i ) {
				case 0:
					echo "Phase 1: Checking what's available and what needs archiving...\n";
					break;
				case 1:
					echo "Phase 2: Submitting requests for archives...\n";
					break;
				case 2:
					echo "Phase 3: Applying necessary changes to page...\n";
			}
			foreach( $links as $tid => $link ) {
				if( $link['link_type'] == "reference" ) {
					$reference = true;
				} else $reference = false;
				$id = 0;
				do {
					if( $reference === true ) {
						$link = $links[$tid]['reference'][$id];
					} else $link = $link[$link['link_type']];
					if( isset( $link['ignore'] ) && $link['ignore'] === true ) break;

					//Create a flag that marks the source as being improperly formatting and needing fixing
					$invalidEntry = ( ( $link['has_archive'] === true && ( ( $link['archive_type'] == "invalid" &&
					                                                         !isset( $link['ignore_iarchive_flag'] ) ) ||
					                                                       ( $this->commObject->config['convert_archives'] ==
					                                                         1 &&
					                                                         isset( $link['convert_archive_url'] ) &&
					                                                         ( !isset( $link['converted_encoding_only'] ) ||
					                                                           $this->commObject->config['convert_archives_encoding'] ==
					                                                           1 ) ) ) ) ||
					                  ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" ) ) &&
					                $link['link_type'] != "x";
					//Create a flag that determines basic clearance to edit a source.
					$linkRescueClearance =
						( ( ( $this->commObject->config['touch_archive'] == 1 || $link['has_archive'] === false ) &&
						    $link['permanent_dead'] === false ) || $invalidEntry === true ) &&
						$link['link_type'] != "x";
					//DEAD_ONLY = 0; Modify ALL links clearance flag
					$dead0 = $this->commObject->config['dead_only'] == 0 &&
					         !( $link['tagged_dead'] === true && $link['is_dead'] === false &&
					            $this->commObject->config['tag_override'] == 0 );
					//DEAD_ONLY = 1; Modify only tagged links clearance flag
					$dead1 = $this->commObject->config['dead_only'] == 1 && ( $link['tagged_dead'] === true &&
					                                                          ( $link['is_dead'] === true ||
					                                                            $this->commObject->config['tag_override'] ==
					                                                            1 ) );
					//DEAD_ONLY = 2; Modify all dead links clearance flag
					$dead2 = $this->commObject->config['dead_only'] == 2 &&
					         ( ( $link['tagged_dead'] === true && $this->commObject->config['tag_override'] == 1 ) ||
					           $link['is_dead'] === true );
					//Tag remove clearance flag
					$tagremoveClearance = $link['tagged_dead'] === true && $link['is_dead'] === false &&
					                      $this->commObject->config['tag_override'] == 0;
					//Forced update clearance
					$forceClearance = ( isset( $link['force'] ) ) ||
					                  ( isset( $link['force_when_dead'] ) && $link['is_dead'] === true ) ||
					                  ( isset( $link['force_when_alive'] ) && $link['is_dead'] === false );

					if( $i == 0 && ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) &&
					    $this->commObject->config['archive_alive'] == 1
					) {
						//Populate a list of URLs to check, if an archive exists.
						if( $reference === false ) {
							$toArchive[$tid] = $link['url'];
						} else $toArchive["$tid:$id"] = $link['url'];
					} elseif( $i >= 1 && $reference === true &&
					          ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) &&
					          $this->commObject->config['archive_alive'] == 1 && $checkResponse["$tid:$id"] !== true
					) {
						//Populate URLs to submit for archiving.
						if( $i == 1 ) {
							$toArchive["$tid:$id"] = $link['url'];
						} else {
							//If it archived, then tally the success, otherwise, note it.
							if( $archiveResponse["$tid:$id"] === true ) {
								$archived++;
							} elseif( $archiveResponse["$tid:$id"] === false ) {
								$archiveProblems["$tid:$id"] = $link['url'];
							}
						}
					} elseif( $i >= 1 && $reference === false &&
					          ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) &&
					          $this->commObject->config['archive_alive'] == 1 && $checkResponse[$tid] !== true
					) {
						//Populate URLs to submit for archiving.
						if( $i == 1 ) {
							$toArchive[$tid] = $link['url'];
						} else {
							//If it archived, then tally the success, otherwise, note it.
							if( $archiveResponse[$tid] === true ) {
								$archived++;
							} elseif( $archiveResponse[$tid] === false ) {
								$archiveProblems[$tid] = $link['url'];
							}
						}
					}

					if( $i >= 1 && ( ( $linkRescueClearance === true &&
					                   ( $dead0 === true || $dead1 === true || $dead2 === true ) ) ||
					                 $invalidEntry === true || $forceClearance === true )
					) {
						//Populate URLs that need we need to retrieve an archive for
						if( $i == 1 ) {
							if( $reference === false ) {
								$toFetch[$tid] = [
									$link['url'], ( $this->commObject->config['archive_by_accessdate'] == 1 ?
										( $link['access_time'] != "x" ? $link['access_time'] : null ) : null )
								];
							} else {
								$toFetch["$tid:$id"] = [
									$link['url'], ( $this->commObject->config['archive_by_accessdate'] == 1 ?
										( $link['access_time'] != "x" ? $link['access_time'] : null ) : null )
								];
							}
						} elseif( $i == 2 ) {
							//Do actual work
							if( ( ( $reference === false && ( $temp = $fetchResponse[$tid] ) !== false ) ||
							      ( $reference === true && ( $temp = $fetchResponse["$tid:$id"] ) !== false ) ) &&
							    !is_null( $temp )
							) {
								if( $reference !== false || $link['link_type'] != "stray" ||
								    $link['archive_type'] != "invalid"
								) {
									if( $this->rescueLink( $link, $modifiedLinks, $temp, $tid, $id ) ===
									    true ) $rescued++;
								}
							} elseif( $temp === false && empty( $link['archive_url'] ) && $link['is_dead'] === true ) {
								$notrescued++;
								if( $link['tagged_dead'] !== true ) {
									$link['newdata']['tagged_dead'] = true;
								} else continue;
								$tagged++;
								$this->noRescueLink( $link, $modifiedLinks, $tid, $id );
							}
						}
					} elseif( $i == 2 && $tagremoveClearance ) {
						//This removes the tag.  When tag override is off.
						$rescued++;
						$modifiedLinks["$tid:$id"]['type'] = "tagremoved";
						$modifiedLinks["$tid:$id"]['link'] = $link['url'];
						$link['newdata']['tagged_dead'] = false;
					}

					//If the original URL was generated from a template, put it back in the URL field.
					if( $i == 2 && isset( $link['template_url'] ) ) {
						$link['url'] = $link['template_url'];
						unset( $link['template_url'] );
					}
					if( $i == 2 && isset( $modifiedLinks["$tid:$id"] ) ) {
						if( $reference === false ) {
							if( $this->commObject->config['notify_on_talk_only'] == 2 ) {
								switch( $modifiedLinks["$tid:$id"]['type'] ) {
									case "addarchive":
									case "modifyarchive":
									case "fix":
										$modifiedLinks["$tid:$id"]['talkonly'] = true;
										unset( $link['newdata'] );
								}
							}
						} elseif( in_array( parse_url( $link['url'], PHP_URL_HOST ),
						                    $this->commObject->config['notify_domains']
						) ) {
							$modifiedLinks["$tid:$id"]['talkonly'] = true;
							unset( $link['newdata'] );
						}
					}
					if( $i == 2 && $reference === true ) {
						$links[$tid]['reference'][$id] = $link;
					} elseif( $i == 2 ) {
						$links[$tid][$links[$tid]['link_type']] = $link;
					}
				} while( $reference === true && isset( $links[$tid]['reference'][++$id] ) );

				//Check if the newdata index actually contains newdata and if the link should be touched.  Avoid redundant work and edits this way.
				if( $i == 2 && Parser::newIsNew( $links[$tid] ) ) {
					//If it is new, generate a new string.
					$links[$tid]['newstring'] = $this->generateString( $links[$tid] );
					if( AUTOFPREPORT === true && !empty( $lastRevTexts ) &&
					    $botID = self::isEditReversed( $links[$tid], $lastRevLinks ) ) {
						echo "A revert has been detected.  Analyzing previous " .
						     count( $this->commObject->getRevTextHistory( $botID ) ) . " revisions...\n";
						foreach( $this->commObject->getRevTextHistory( $botID ) as $revID => $text ) {
							echo "\tAnalyzing revision $revID...\n";
							if( $this->commObject->config['link_scan'] == 0 && !isset( $oldLinks[$revID] ) ) {
								$oldLinks[$revID] = API::openFile( "dump$dumpcount", true, serialize( $this->getExternalLinks( false, $text['*'] ) ) );
								$dumpcount++;
							} elseif( !isset( $oldLinks[$revID] ) ) {
								$oldLinks[$revID] = API::openFile( "dump$dumpcount", true, serialize( $this->getReferences( false, $text['*'] ) ) );
								$dumpcount++;
							}
						}

						echo "Attempting to identify reverting user...";
						$reverter = $this->commObject->getRevertingUser( $links[$tid], $oldLinks, $botID );
						if( $reverter !== false ) {
							$userDataAPI = API::getUser( $reverter['userid'] );
							$userData =
								$this->dbObject->getUser( $userDataAPI['centralids']['CentralAuth'], WIKIPEDIA );
							if( empty( $userData ) ) {
								$wikiLanguage = str_replace( "wiki", "", WIKIPEDIA );
								$this->dbObject->createUser( $userDataAPI['centralids']['CentralAuth'], WIKIPEDIA,
								                             $userDataAPI['name'], 0, $wikiLanguage, serialize( [
									                                                                                'registration_epoch' => strtotime( $userDataAPI['registration']
									                                                                                ),
									                                                                                'editcount'          => $userDataAPI['editcount'],
									                                                                                'wikirights'         => $userDataAPI['rights'],
									                                                                                'wikigroups'         => $userDataAPI['groups'],
									                                                                                'blockwiki'          => isset( $userDataAPI['blockid'] )
								                                                                                ]
								                             )
								);
								$userData =
									$this->dbObject->getUser( $userDataAPI['centralids']['CentralAuth'], WIKIPEDIA );
								echo $userData['user_name'] . "\n";
							}
						} else echo "Failed!\n";
						echo "Attempting to ascertain reason for revert...\n";
						if( $links[$tid]['link_type'] == "reference" ) {
							$makeModification = true;
							foreach( $links[$tid]['reference'] as $id => $link ) {
								if( !is_numeric( $id ) ) continue;
								if( $this->isLikelyFalsePositive( "$tid:$id", $link, $modifyLink ) ) {
									if( $reverter !== false ) {
										$toCheck["$tid:$id"] = $link['url'];
										$toCheckMeta["$tid:$id"] = $userData;
									}
								}
								$makeModification = $modifyLink && $makeModification;
								if( $modifyLink === false ) {
									switch( $modifiedLinks["$tid:$id"]['type'] ) {
										case "fix":
										case "modifyarchive":
										case "tagremoved":
										case "addarchive":
											$rescued--;
											break;
										case "tagged":
											$tagged--;
											$notrescued--;
											break;
									}
									unset( $modifiedLinks["$tid:$id"] );
								}
							}
							if( $makeModification === true ) $newtext =
								self::str_replace( $links[$tid]['string'], $links[$tid]['newstring'],
								                   $this->commObject->content, $count, 1,
								                   $links[$tid][$links[$tid]['link_type']]['offset'], $newtext
								);
						} else {
							if( $this->isLikelyFalsePositive( $tid, $links[$tid][$links[$tid]['link_type']],
							                                  $makeModification
							) ) {
								if( $reverter !== false ) {
									$toCheck[$tid] = $link['url'];
									$toCheckMeta[$tid] = $userData;
								}
							} elseif( $makeModification === true ) {
								$newtext = self::str_replace( $links[$tid]['string'], $links[$tid]['newstring'],
								                              $this->commObject->content, $count, 1,
								                              $links[$tid][$links[$tid]['link_type']]['offset'],
								                              $newtext
								);
							}

							if( $makeModification === false ) {
								switch( $modifiedLinks["$tid:0"]['type'] ) {
									case "fix":
									case "modifyarchive":
									case "tagremoved":
									case "addarchive":
										$rescued--;
										break;
									case "tagged":
										$tagged--;
										$notrescued--;
										break;
								}
								unset( $modifiedLinks["$tid:0"] );
							}
						}
					} else {
						//Yes, this is ridiculously convoluted but this is the only makeshift str_replace expression I could come up with the offset start and limit support.
						$newtext = self::str_replace( $links[$tid]['string'], $links[$tid]['newstring'],
						                              $this->commObject->content, $count, 1,
						                              $links[$tid][$links[$tid]['link_type']]['offset'], $newtext
						);
					}
				}
			}

			//Check if archives exist for the provided URLs
			if( $i == 0 && !empty( $toArchive ) ) {
				$checkResponse = $this->commObject->isArchived( $toArchive );
				$checkResponse = $checkResponse['result'];
				$toArchive = [];
			}
			$errors = [];
			//Submit provided URLs for archiving
			if( $i == 1 && !empty( $toArchive ) ) {
				$archiveResponse = $this->commObject->requestArchive( $toArchive );
				$errors = $archiveResponse['errors'];
				$archiveResponse = $archiveResponse['result'];
			}
			//Retrieve snapshots of provided URLs
			if( $i == 1 && !empty( $toFetch ) ) {
				$fetchResponse = $this->commObject->retrieveArchive( $toFetch );
				$fetchResponse = $fetchResponse['result'];
			}
		}

		if( !empty( $toCheck ) ) {
			$escapedURLs = [];
			foreach( $toCheck as $url ) {
				$escapedURLs[] = $this->dbObject->sanitize( $url );
			}
			$sql =
				"SELECT * FROM externallinks_fpreports LEFT JOIN externallinks_global ON externallinks_fpreports.report_url_id = externallinks_global.url_id WHERE `url` IN ( '" .
				implode( "', '", $escapedURLs ) . "' ) AND `report_status` = 0;";
			$res = $this->dbObject->queryDB( $sql );
			$alreadyReported = [];
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$alreadyReported[] = $result['url'];
			}

			$toCheck = array_diff( $toCheck, $alreadyReported );
		}

		if( !empty( $toCheck ) ) {
			$results = $this->deadCheck->areLinksDead( $toCheck );
			$errors = $this->deadCheck->getErrors();
			$whitelisted = [];
			if( USEADDITIONALSERVERS === true ) {
				$toValidate = [];
				foreach( $toCheck as $tid => $url ) {
					if( $results[$url] === true ) {
						$toValidate[] = $url;
					}
				}
				if( !empty( $toValidate ) ) foreach( explode( "\n", CIDSERVERS ) as $server ) {
					$serverResults = API::runCIDServer( $server, $toValidate );
					$toValidate = array_flip( $toValidate );
					foreach( $serverResults['results'] as $surl => $sResult ) {
						if( $surl == "errors" ) continue;
						if( $sResult === false ) {
							$whitelisted[] = $surl;
							unset( $toValidate[$surl] );
						} else {
							$errors[$surl] = $serverResults['results']['errors'][$surl];
						}
					}
					$toValidate = array_flip( $toValidate );
				}
			}

			$toReset = [];
			$toWhitelist = [];
			$toReport = [];
			foreach( $toCheck as $id => $url ) {
				if( $results[$url] !== true ) {
					$toReset[] = $url;
				} else {
					if( !in_array( $url, $whitelisted ) ) $toReport[] = $url;
					else $toWhitelist[] = $url;
				}
			}
			foreach( $toReport as $report ) {
				$tid = array_search( $report, $toCheck );
				if( $this->dbObject->insertFPReport( WIKIPEDIA, $toCheckMeta[$tid]['user_link_id'],
				                                     $this->commObject->db->dbValues[$tid]['url_id'],
				                                     CHECKIFDEADVERSION, $errors[$report]
				) ) {
					$this->dbObject->insertLogEntry( "global", WIKIPEDIA, "fpreport", "report",
					                                 $this->commObject->db->dbValues[$tid]['url_id'], $report,
					                                 $toCheckMeta[$tid]['user_link_id']
					);
				}
			}

			$escapedURLs = [];
			$domains = [];
			$tids = [];
			foreach( $toReset as $report ) {
				$tid = array_search( $report, $toCheck );
				if( $this->commObject->db->dbValues[$tid]['paywall_status'] == 3 ) {
					continue;
				} elseif( $this->commObject->db->dbValues[$tid]['live_state'] != 0 ) {
					continue;
				} elseif( in_array( $this->commObject->db->dbValues[$tid]['paywall_id'], $escapedURLs ) ) {
					continue;
				} else {
					$escapedURLs[] = $this->commObject->db->dbValues[$tid]['paywall_id'];
					$domains[] = $this->deadCheck->parseURL( $report )['host'];
					$tids[] = $tid;
				}
			}
			if( !empty( $escapedURLs ) ) {
				$sql = "UPDATE externallinks_global SET `live_state` = 3 WHERE `paywall_id` IN ( " .
				       implode( ", ", $escapedURLs ) . " );";
				if( $this->dbObject->queryDB( $sql ) ) {
					foreach( $escapedURLs as $id => $paywallID ) {
						$this->dbObject->insertLogEntry( "global", WIKIPEDIA, "domaindata", "changestate", $paywallID,
						                                 $domains[$id],
						                                 $toCheckMeta[$tids[$id]]['user_link_id'], -1, 3
						);
					}
				}
			}
			$escapedURLs = [];
			$domains = [];
			$paywallStatuses = [];
			$tids = [];
			foreach( $toWhitelist as $report ) {
				$tid = array_search( $report, $toCheck );
				if( $this->commObject->db->dbValues[$tid]['paywall_status'] == 3 ) {
					continue;
				} elseif( in_array( $this->commObject->db->dbValues[$tid]['paywall_id'], $escapedURLs ) ) {
					continue;
				} else {
					$escapedURLs[] = $this->commObject->db->dbValues[$tid]['paywall_id'];
					$domains[] = $this->deadCheck->parseURL( $report )['host'];
					$paywallStatuses[] = $this->commObject->db->dbValues[$tid]['paywall_status'];
					$tids[] = $tid;
				}
			}
			if( !empty( $escapedURLs ) ) {
				$sql = "UPDATE externallinks_paywall SET `paywall_status` = 3 WHERE `paywall_id` IN ( " .
				       implode( ", ", $escapedURLs ) . " );";
				if( $this->dbObject->queryDB( $sql ) ) {
					foreach( $escapedURLs as $id => $paywallID ) {
						$this->dbObject->insertLogEntry( "global", WIKIPEDIA, "domaindata", "changeglobalstate",
						                                 $paywallID,
						                                 $domains[$id], $toCheckMeta[$tids[$id]]['user_link_id'],
						                                 $paywallStatuses[$id], 3
						);
					}
				}
			}
			if( !empty( $toReport ) ) {
				$sql =
					"SELECT * FROM externallinks_user LEFT JOIN externallinks_userpreferences ON externallinks_userpreferences.user_link_id= externallinks_user.user_link_id WHERE `user_email_confirmed` = 1 AND `user_email_fpreport` = 1 AND `wiki` = '" .
					WIKIPEDIA . "';";
				$res = $this->dbObject->queryDB( $sql );
				while( $result = mysqli_fetch_assoc( $res ) ) {
					$mailObject = new HTMLLoader( "emailmain", $result['language'], PUBLICHTML . "Templates/",
					                              PUBLICHTML . "i18n/"
					);
					$body = "{{{fpreportedstartermultiple}}}:<br>\n";
					$body .= "<ul>\n";
					foreach( $toReport as $report ) {
						$body .= "<li><a href=\"$report\">" . htmlspecialchars( $report ) . "</a></li>\n";
					}
					$body .= "</ul>";
					$mailObject->assignElement( "body", $body );
					$mailObject->assignAfterElement( "rooturl", ROOTURL );
					$mailObject->finalize();
					$subjectObject =
						new HTMLLoader( "{{{fpreportedsubject}}}", $result['language'], false, PUBLICHTML . "i18n/" );
					$subjectObject->finalize();
					mailHTML( $result['user_email'], $subjectObject->getLoadedTemplate(),
					          $mailObject->getLoadedTemplate(), true
					);
				}
			}
		}

		$archiveResponse = $checkResponse = $fetchResponse = null;
		unset( $archiveResponse, $checkResponse, $fetchResponse );
		echo "Rescued: $rescued; Tagged dead: $tagged; Archived: $archived; Memory Used: " .
		     ( memory_get_usage( true ) / 1048576 ) . " MB; Max System Memory Used: " .
		     ( memory_get_peak_usage( true ) / 1048576 ) . " MB\n";
		//Talk page stuff.  This part leaves a message on archives that failed to save on the wayback machine.
		if( !empty( $archiveProblems ) && $this->commObject->config['notify_error_on_talk'] == 1 ) {
			$out = "";
			foreach( $archiveProblems as $id => $problem ) {
				$magicwords = [];
				$magicwords['problem'] = $problem;
				$magicwords['error'] = $errors[$id];
				$out .= "* " . $this->commObject->getConfigText( "plerror", $magicwords ) . "\n";
			}
			$body = $this->commObject->getConfigText( "talk_error_message", [ 'problematiclinks' => $out ] ) . "~~~~";
			API::edit( "Talk:{$this->commObject->page}", $body,
			           $this->commObject->getConfigText( "errortalkeditsummary", [] ), false, true, "new",
			           $this->commObject->getConfigText( "talk_error_message_header", [] )
			);
		}
		foreach( $modifiedLinks as $link ) {
			if( $link['type'] == "addarchive" ) {
				if( self::getArchiveHost( $link['newarchive'], $data ) == "wayback" ) {
					$waybackadded++;
				} else $otheradded++;
			}
		}
		$pageModified = false;
		//This is the courtesy message left behind when it edits the main article.
		if( $this->commObject->content != $newtext ||
		    ( $this->commObject->config['notify_on_talk_only'] == 2 && !empty( $modifiedLinks ) ) ) {
			$pageModified = $this->commObject->content != $newtext;
			$magicwords = [];
			$magicwords['namespacepage'] = $this->commObject->page;
			$magicwords['linksmodified'] = $tagged + $rescued;
			$magicwords['linksrescued'] = $rescued;
			$magicwords['linksnotrescued'] = $notrescued;
			$magicwords['linkstagged'] = $tagged;
			$magicwords['linksarchived'] = $archived;
			$magicwords['linksanalyzed'] = $analyzed;
			$magicwords['pageid'] = $this->commObject->pageid;
			$magicwords['title'] = urlencode( $this->commObject->page );
			$magicwords['logstatus'] = "fixed";
			// Make some adjustments for the message describing the changes.
			$addTalkOnly = false;
			if( $this->commObject->config['notify_on_talk_only'] == 2 && $this->leaveTalkOnly() == false &&
			    $pageModified ) {
				foreach( $modifiedLinks as $link ) {
					if( isset( $link['talkonly'] ) ) {
						$addTalkOnly = true;
						switch( $link['type'] ) {
							case "fix":
							case "modifyarchive":
								$rescued--;
							case "tagremoved":
							case "addarchive":
								$magicwords['linksmodified']--;
								$magicwords['linksrescued']--;
								break;
							case "tagged":
								$magicwords['linkstagged']--;
								break;
						}
					}
				}
			}
			if( ( $this->commObject->config['notify_on_talk_only'] == 0 ||
			      $this->commObject->config['notify_on_talk_only'] == 2 ) && $this->leaveTalkOnly() == false &&
			    $pageModified ) {
				$revid =
					API::edit( $this->commObject->page, $newtext,
					           $this->commObject->getConfigText( "maineditsummary", $magicwords ), false, $timestamp
					);
			} else $magicwords['logstatus'] = "posted";
			if( isset( $revid ) ) {
				$magicwords['diff'] = str_replace( "api.php", "index.php", API ) . "?diff=prev&oldid=$revid";
				$magicwords['revid'] = $revid;
			} else {
				$magicwords['diff'] = "";
				$magicwords['revid'] = "";
			}
			if( ( ( $this->commObject->config['notify_on_talk'] == 1 && isset( $revid ) && $revid !== false ) ||
			      $this->commObject->config['notify_on_talk_only'] == 1 ||
			      $this->commObject->config['notify_on_talk_only'] == 2 || $this->leaveTalkOnly() == true ) &&
			    $this->leaveTalkMessage() == true
			) {
				for( $talkOnlyFlag = 0; $talkOnlyFlag <= (int) $addTalkOnly; $talkOnlyFlag++ ) {
					$out = "";
					$editTalk = false;
					$talkOnly = $this->commObject->config['notify_on_talk_only'] == 1 || $this->leaveTalkOnly() ||
					            (bool) $talkOnlyFlag == true ||
					            ( $this->commObject->config['notify_on_talk_only'] == 2 && !$pageModified );
					if( (bool) $talkOnlyFlag === true ) {
						//Reverse the numbers
						$magicwords['linksmodified'] = $rescued + $tagged - $magicwords['linksmodified'];
						$magicwords['linksrescued'] = $rescued - $magicwords['linksrescued'];
						$magicwords['linkstagged'] = $tagged - $magicwords['linkstagged'];
					}
					foreach( $modifiedLinks as $tid => $link ) {
						if( isset( $link['talkonly'] ) && $talkOnly === false ) continue;
						if( (bool) $talkOnlyFlag === true && !isset( $link['talkonly'] ) ) continue;
						$magicwords2 = [];
						$magicwords2['link'] = $link['link'];
						if( isset( $link['oldarchive'] ) ) $magicwords2['oldarchive'] = $link['oldarchive'];
						if( isset( $link['newarchive'] ) ) $magicwords2['newarchive'] = $link['newarchive'];
						$tout = "*";
						switch( $link['type'] ) {
							case "addarchive":
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mladdarchive",
									                                           $magicwords2
									);
								} else $tout .= $this->commObject->getConfigText( "mladdarchivetalkonly", $magicwords2
								);
								$editTalk = true;
								break;
							case "modifyarchive":
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mlmodifyarchive", $magicwords2 );
									$editTalk = true;
								}
								break;
							case "fix":
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mlfix", $magicwords2
									);
									if( $this->commObject->config['talk_message_verbose'] == 1 ) $editTalk = true;
								}
								break;
							case "tagged":
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mltagged",
									                                           $magicwords2
									);
									if( $this->commObject->config['talk_message_verbose'] == 1 ) $editTalk = true;
								} else {
									$tout .= $this->commObject->getConfigText( "mltaggedtalkonly", $magicwords2 );
									$editTalk = true;
								}
								break;
							case "tagremoved":
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mltagremoved",
									                                           $magicwords2
									);
									if( $this->commObject->config['talk_message_verbose'] == 1 ) $editTalk = true;
								} else {
									$tout .= $this->commObject->getConfigText( "mltagremovedtalkonly", $magicwords2 );
									$editTalk = true;
								}
								break;
							default:
								if( $talkOnly === false ) {
									$tout .= $this->commObject->getConfigText( "mldefault", $magicwords2 );
									$editTalk = true;
								}
								break;
						}
						$tout .= "\n";
						if( $talkOnly === true &&
						    !$this->commObject->db->setNotified( $tid )
						) {
							continue;
						} else {
							if( $tout != "*\n" ) $out .= $tout;
						}
					}
					$magicwords['modifiedlinks'] = $out;
					if( empty( $out ) ) $editTalk = false;
					if( $talkOnly === false && $this->commObject->config['notify_on_talk'] == 0 ) $editTalk = false;
					if( $talkOnly === false ) {
						$header =
							$this->commObject->getConfigText( "talk_message_header", $magicwords );
					} else $header = $this->commObject->getConfigText( "talk_message_header_talk_only", $magicwords );
					if( $talkOnly === false ) {
						$body =
							$this->commObject->getConfigText( "talk_message", $magicwords ) . "~~~~";
					} else $body = $this->commObject->getConfigText( "talk_message_talk_only", $magicwords ) . "~~~~";
					if( $editTalk === true ) {
						API::edit( "Talk:{$this->commObject->page}", $body,
						           $this->commObject->getConfigText( "talkeditsummary", $magicwords ),
						           false, false, true, "new", $header
						);
					}
				}
			}
			$this->commObject->logCentralAPI( $magicwords );
		}
		$this->commObject->db->updateDBValues();

		echo "\n";

		$newtext = $history = null;

		array_map( [ "API", "closeFileHandle" ], $lastRevLinks );
		array_map( [ "API", "closeFileHandle" ], $lastRevTexts );
		array_map( [ "API", "closeFileHandle" ], $oldLinks );

		unset( $this->commObject, $newtext, $history, $res, $db );
		$returnArray = [
			'linksanalyzed' => $analyzed, 'linksarchived' => $archived, 'linksrescued' => $rescued,
			'linkstagged'   => $tagged, 'pagemodified' => $pageModified, 'waybacksadded' => $waybackadded,
			'othersadded'   => $otheradded, 'revid' => ( isset( $revid ) ? $revid : false )
		];

		return $returnArray;
	}

	/**
	 * Fetch all links in an article
	 *
	 * @param bool $referenceOnly Fetch references only
	 * @param string $text Page text to analyze
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array Details about every link on the page
	 */
	public function getExternalLinks( $referenceOnly = false, $text = false ) {
		$linksAnalyzed = 0;
		$returnArray = [];
		$toCheck = [];
		//Parse all the links
		$parseData = $this->parseLinks( $referenceOnly, $text );
		$lastLink = [ 'tid' => null, 'id' => null ];
		$currentLink = [ 'tid' => null, 'id' => null ];
		//Run through each captured source from the parser
		foreach( $parseData as $tid => $parsed ) {
			//If there's nothing to work with, move on.
			if( empty( $parsed['link_string'] ) && empty( $parsed['remainder'] ) ) continue;
			if( $parsed['type'] == "reference" && empty( $parsed['contains'] ) ) continue;
			$returnArray[$tid]['link_type'] = $parsed['type'];
			$returnArray[$tid]['string'] = $parsed['string'];
			if( $parsed['type'] == "reference" ) {
				$returnArray[$tid]['reference']['offset'] = $parsed['offset'];
				foreach( $parsed['contains'] as $parsedlink ) {
					$returnArray[$tid]['reference'][] =
						array_merge( $this->getLinkDetails( $parsedlink['link_string'],
						                                    $parsedlink['remainder'] . $parsed['remainder']
						), [ 'string' => $parsedlink['string'], 'offset' => $parsedlink['offset'] ]
						);
				}
				$tArray = array_merge( $this->commObject->config['deadlink_tags'],
				                       $this->commObject->config['ignore_tags'],
				                       $this->commObject->config['paywall_tags'],
				                       $this->commObject->config['archive_tags']
				);
				$regex = $this->fetchTemplateRegex( $tArray, true );
				if( count( $parsed['contains'] == 1 ) && !isset( $returnArray[$tid]['reference'][0]['ignore'] ) &&
				    empty( trim( preg_replace( $regex, "",
				                               str_replace( $parsed['contains'][0]['link_string'], "",
				                                            $parsed['link_string']
				                               )
				                 )
				    )
				    ) ) {
					$returnArray[$tid]['reference'][0]['converttocite'] = true;
				}
			} else {
				$returnArray[$tid][$parsed['type']] =
					array_merge( $this->getLinkDetails( $parsed['link_string'], $parsed['remainder'] ),
					             [ 'string' => $parsed['string'], 'offset' => $parsed['offset'] ]
					);
			}
			if( $parsed['type'] == "reference" ) {
				if( !empty( $parsed['parameters'] ) ) {
					$returnArray[$tid]['reference']['parameters'] =
						$parsed['parameters'];
				}
				$returnArray[$tid]['reference']['link_string'] = $parsed['link_string'];
			}
			if( $parsed['type'] == "template" ) {
				$returnArray[$tid]['template']['name'] = $parsed['name'];
			}
			if( !isset( $returnArray[$tid][$parsed['type']]['ignore'] ) ||
			    $returnArray[$tid][$parsed['type']]['ignore'] === false
			) {
				if( $parsed['type'] == "reference" ) {
					//In instances where the main function runs through references, it uses a while loop incrementing the id by 1.
					//Gaps in the indexes, ie a missing index 2 for example, will cause the while loop to prematurely stop.
					//We fix this by not allowing gaps like this to happen.
					$indexOffset = 0;
					foreach( $returnArray[$tid]['reference'] as $id => $link ) {
						if( !is_int( $id ) || isset( $link['ignore'] ) ) {
							//This will create a gap, so increment the offset.
							if( is_int( $id ) && $id !== 0 ) unset( $returnArray[$tid]['reference'][$id] );
							if( is_int( $id ) ) $indexOffset++;
							continue;
						}
						$currentLink['tid'] = $tid;
						//Compensate for skipped indexes.
						$currentLink['id'] = $id;
						//Check if the neighboring source has some kind of connection to each other.
						if( $this->isConnected( $lastLink, $currentLink, $returnArray ) ) {
							unset( $returnArray[$tid]['reference'][$id] );
							//If so, update $toCheck at the respective index, with the new information.
							$toCheck["{$lastLink['tid']}:{$lastLink['id']}"] =
								$returnArray[$lastLink['tid']]['reference'][$lastLink['id']];
							$indexOffset++;
							if( $text ===
							    false ) $this->commObject->db->retrieveDBValues( $returnArray[$lastLink['tid']]['reference'][$lastLink['id']],
							                                                     "{$lastLink['tid']}:{$lastLink['id']}"
							);
							continue;
						}
						$linksAnalyzed++;
						//Load respective DB values into the active cache.
						if( $text ===
						    false ) $this->commObject->db->retrieveDBValues( $returnArray[$tid]['reference'][$id],
						                                                     "$tid:" . ( $id - $indexOffset )
						);
						$toCheck["$tid:" . ( $id - $indexOffset )] = $returnArray[$tid]['reference'][$id];
						$lastLink['tid'] = $tid;
						$lastLink['id'] = $id - $indexOffset;
						if( $indexOffset !== 0 ) {
							$returnArray[$tid]['reference'][$id - $indexOffset] = $returnArray[$tid]['reference'][$id];
							unset( $returnArray[$tid]['reference'][$id] );
						}
					}
				} else {
					$currentLink['tid'] = $tid;
					$currentLink['id'] = null;
					//Check if the neighboring source has some kind of connection to each other.
					if( $this->isConnected( $lastLink, $currentLink, $returnArray ) ) {
						$returnArray[$lastLink['tid']]['string'] =
							$returnArray[$lastLink['tid']][$parseData[$lastLink['tid']]['type']]['string'];
						$toCheck[$lastLink['tid']] =
							$returnArray[$lastLink['tid']][$parseData[$lastLink['tid']]['type']];
						if( $text ===
						    false ) $this->commObject->db->retrieveDBValues( $returnArray[$lastLink['tid']][$parsed['type']],
						                                                     $lastLink['tid']
						);
						continue;
					}
					$linksAnalyzed++;
					//Load respective DB values into the active cache.
					if( $text === false ) $this->commObject->db->retrieveDBValues( $returnArray[$tid][$parsed['type']],
					                                                               $tid
					);
					$toCheck[$tid] = $returnArray[$tid][$parsed['type']];
					$lastLink['tid'] = $tid;
					$lastLink['id'] = null;
				}
			}
		}
		//Retrieve missing access times that couldn't be extrapolated from the parser.
		if( $text === false ) $toCheck = $this->updateAccessTimes( $toCheck );
		//Set the live states of all the URL, and run a dead check if enabled.
		if( $text === false ) $toCheck = $this->updateLinkInfo( $toCheck );
		//Transfer data back to the return array.
		foreach( $toCheck as $tid => $link ) {
			if( is_int( $tid ) ) {
				$returnArray[$tid][$returnArray[$tid]['link_type']] = $link;
			} else {
				$tid = explode( ":", $tid );
				$returnArray[$tid[0]][$returnArray[$tid[0]]['link_type']][$tid[1]] = $link;
			}
		}
		$returnArray['count'] = $linksAnalyzed;

		return $returnArray;
	}

	/**
	 * Parses the pages for refences, citation templates, and bare links.
	 *
	 * @param bool $referenceOnly
	 * @param string $text Page text to analyze
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array All parsed links
	 */
	protected function parseLinks( $referenceOnly = false, $text = false ) {
		$returnArray = [];
		$tArray = array_merge( $this->commObject->config['deadlink_tags'],
		                       $this->commObject->config['ignore_tags'],
		                       $this->commObject->config['paywall_tags']
		);
		if( $text === false ) $pageText = $this->commObject->content;
		else $pageText = $text;

		$scrapText = $pageText;
		//Filter out the comments and plaintext rendered markup.
		if( $text === false ) $filteredText = $this->filterText( $pageText );
		else $filteredText = $this->filterText( $text );
		//Detect tags lying outside of the closing reference tag.
		$regex = '/<\/ref\s*?>\s*?((\s*(' .
		         str_replace( "\{\{", "\{\{\s*", str_replace( "\}\}", "", implode( '|', $tArray ) ) ) .
		         ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})*)/i';
		$tid = 0;
		//Look for all opening reference tags
		$refCharRemoved = 0;
		$pageStartLength = strlen( $scrapText );
		while( preg_match( '/<ref(\s+.*?)?(\/)?\s*>/i', $scrapText, $match, PREG_OFFSET_CAPTURE ) ) {
			//Note starting positing of opening reference tag
			$offset = $match[0][1];
			//If there is no closing tag after the opening tag, abort.  Malformatting detected.
			//Otherwise, record location
			if( isset( $match[2] ) && $match[2][0] == "/" ) {
				$scrapText = preg_replace( '/' . preg_quote( $match[0][0], '/' ) . '/', "", $scrapText, 1 );
				$refCharRemoved += $pageStartLength - strlen( $scrapText );
				$pageStartLength = strlen( $scrapText );
				continue;
			}
			if( ( $endoffset = stripos( $scrapText, "</ref", $offset ) ) === false ) break;
			//Use the detection regex on this closing reference tag.
			if( preg_match( $regex, $scrapText, $match1, PREG_OFFSET_CAPTURE, $endoffset ) ) {
				//Redundancy, not location of closing tag.
				$endoffset = $match1[0][1];
				//Grab string from opening tag, up to closing tag.
				$scrappy = substr( $scrapText, $offset, $endoffset - $offset );
				//Merge the string from opening tag, and attach closing tag, with additional tags that were detected.
				$fullmatch = $scrappy . $match1[0][0];
				//string is the full match
				$returnArray[$tid]['string'] = $fullmatch;
				//Remainder is the group of inline tags detected in the capture group.
				$returnArray[$tid]['remainder'] = $match1[1][0];
				//Mark as reference.
				$returnArray[$tid]['type'] = "reference";
				$returnArray[$tid]['offset'] = $offset;
			} else break;

			//Some reference opening tags have parameters embedded in there.
			if( isset( $match[1] ) ) {
				$refValues = $match[1][0];
			} else $refValues = "";
			$returnArray[$tid]['parameters'] = $this->getReferenceParameters( $refValues );
			//Trim tag from start.  Link_string contains the body of reference.
			$returnArray[$tid]['link_string'] = str_replace( $match[0][0], "", $scrappy );
			//Save it back into $scrappy
			$scrappy = $returnArray[$tid]['link_string'];
			$returnArray[$tid]['contains'] = [];
			//References can sometimes have more than one source inside.  Fetch all of them.
			$charRemoved = 0;
			$startLength = strlen( $scrappy );
			while( ( $temp = $this->getNonReference( $scrappy ) ) !== false ) {
				//Store each source in here.
				$temp['offset'] += $charRemoved;
				$charRemoved += $startLength - strlen( $scrappy );
				$startLength = strlen( $scrappy );
				$returnArray[$tid]['contains'][] = $temp;
			}
			//If the filtered match is no where to be found, then it's being rendered in plaintext or is a comment
			//We want to leave those alone.
			if( strpos( $filteredText, $this->filterText( $fullmatch ) ) !== false ) {
				$returnArray[$tid]['offset'] += $refCharRemoved;
				$tid++;
				//Large regexes break things, so if we exceed 30000 characters, use a simple str_replace as large
				//strings like that are most likely unique.
				if( strlen( $this->filterText( $fullmatch ) ) > 30000 ) {
					$filteredText = str_replace( $this->filterText( $fullmatch ), "", $filteredText );
				} else {
					$filteredText =
						preg_replace( '/' . preg_quote( $this->filterText( $fullmatch ), '/' ) . '/', "", $filteredText,
						              1
						);
				}
			} else {
				unset( $returnArray[$tid] );
			}
			//Large regexes break things, so if we exceed 30000 characters, use a simple str_replace as large
			//strings like that are most likely unique.
			if( strlen( $fullmatch ) > 30000 ) {
				$scrapText = str_replace( $fullmatch, "", $scrapText );
			} else {
				$scrapText = preg_replace( '/' . preg_quote( $fullmatch, '/' ) . '/', "", $scrapText, 1 );
			}
			$refCharRemoved += $pageStartLength - strlen( $scrapText );
			$pageStartLength = strlen( $scrapText );
		}
		//If we are looking for everything, then...
		if( $referenceOnly === false ) {
			//scan the rest of the page text for non-reference sources.
			$lastOffset = 0;
			while( ( $temp = $this->getNonReference( $scrapText ) ) !== false ) {
				if( strpos( $filteredText, $this->filterText( $temp['string'] ) ) !== false ) {

					if( substr( $scrapText, $temp['offset'], 10 ) !== false && strpos( $pageText,
					                                                                   substr( $scrapText,
					                                                                           $temp['offset'], 10
					                                                                   )
					                                                           ) !== false
					) {
						$lastOffset = $temp['offset'] = strpos( $pageText, $temp['string'],
						                                        max( strpos( $pageText,
						                                                     substr( $scrapText, $temp['offset'], 10 ),
						                                                     $temp['offset']
						                                             ) - strlen( $temp['string'] ), $lastOffset
						                                        )
						);
					} elseif( strlen( $pageText ) - 5 - strlen( $temp['string'] ) > 0 &&
					          strpos( $pageText, $temp['string'],
					                  strlen( $pageText ) - 5 - strlen( $temp['string'] )
					          ) !== false
					) {
						$lastOffset = $temp['offset'] = strpos( $pageText, $temp['string'],
						                                        strlen( $pageText ) - 5 -
						                                        strlen( $temp['string'] )
						);
					} else {
						$lastOffset =
						$temp['offset'] = strpos( $pageText, $temp['string'], $lastOffset );
					}
					$lastOffset += strlen( $temp['string'] );
					$returnArray[] = $temp;
					//We need preg_replace since it has a limiter whereas str_replace does not.
					$filteredText =
						preg_replace( '/' . preg_quote( $this->filterText( $temp['string'] ), '/' ) . '/', "",
						              $filteredText, 1
						);
				}
				$refCharRemoved += $pageStartLength - strlen( $scrapText );
				$pageStartLength = strlen( $scrapText );
			}
		}

		return $returnArray;
	}

	/**
	 * Filters out the text that does not get rendered normally.
	 * This includes comments, and plaintext formatting.
	 *
	 * @param string $text String to filter
	 * @param bool $trim Trim the output
	 *
	 * @return string Filtered text.
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 */
	protected function filterText( $text, $trim = false ) {
		//FIXME: This is caused by RW issues to the dump files
		if( !is_string( $text ) ) {
			sleep( 1 );
		}
		$text = preg_replace( '/\<\!\-\-(?:.|\n)*?\-\-\>/i', "", $text );
		if( preg_match( '/\<\s*source[^\/]*?\>/i', $text, $match, PREG_OFFSET_CAPTURE ) &&
		    preg_match( '/\<\/source\s*\>/i', $text, $match, PREG_OFFSET_CAPTURE, $match[0][1] ) ) {
			$text =
				preg_replace( '/\<\s*source[^\/]*?\>(?:.|\n)*?\<\/source\s*\>/i', "", $text );
		}
		if( preg_match( '/\<\s*syntaxhighlight[^\/]*?\>/i', $text, $match, PREG_OFFSET_CAPTURE ) &&
		    preg_match( '/\<\/syntaxhighlight\s*\>/i', $text, $match, PREG_OFFSET_CAPTURE, $match[0][1] )
		) {
			$text = preg_replace( '/\<\s*syntaxhighlight[^\/]*?\>(?:.|\n)*?\<\/syntaxhighlight\s*\>/i', "", $text );
		}
		if( preg_match( '/\<\s*code[^\/]*?\>/i', $text, $match, PREG_OFFSET_CAPTURE ) &&
		    preg_match( '/\<\/code\s*\>/i', $text, $match, PREG_OFFSET_CAPTURE, $match[0][1] ) ) {
			$text =
				preg_replace( '/\<\s*code[^\/]*?\>(?:.|\n)*?\<\/code\s*\>/i', "", $text );
		}
		if( preg_match( '/\<\s*nowiki[^\/]*?\>/i', $text, $match, PREG_OFFSET_CAPTURE ) &&
		    preg_match( '/\<\/nowiki\s*\>/i', $text, $match, PREG_OFFSET_CAPTURE, $match[0][1] ) ) {
			$text =
				preg_replace( '/\<\s*nowiki[^\/]*?\>(?:.|\n)*?\<\/nowiki\s*\>/i', "", $text );
		}
		if( preg_match( '/\<\s*pre[^\/]*?\>/i', $text, $match, PREG_OFFSET_CAPTURE ) &&
		    preg_match( '/\<\/pre\s*\>/i', $text, $match, PREG_OFFSET_CAPTURE, $match[0][1] ) ) {
			$text =
				preg_replace( '/\<\s*pre[^\/]*?\>(?:.|\n)*?\<\/pre\s*\>/i', "", $text );
		}

		if( $trim ) return trim( $text );
		else return $text;
	}

	/**
	 * Read and parse the reference string.
	 * Extract the reference parameters
	 *
	 * @param string $refparamstring reference string
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array Contains the parameters as an associative array
	 */
	public function getReferenceParameters( $refparamstring ) {
		$returnArray = [];
		preg_match_all( '/(\S*)\s*=\s*(".*?"|\'\'.*?\'\'|\'.*?\'|\S*)/i', $refparamstring, $params );
		foreach( $params[0] as $tid => $tvalue ) {
			$returnArray[$params[1][$tid]] = $params[2][$tid];
		}

		return $returnArray;
	}

	/**
	 * Fetches the first non-reference it finds in the supplied text and returns it.
	 * This function will remove the text it found in the passed parameter.
	 *
	 * @param string $scrapText Text to look at.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array Details of the first non-reference found.  False on failure.
	 */
	protected function getNonReference( &$scrapText = "" ) {
		$returnArray = [];
		$tArray =
			array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'],
			             $this->commObject->config['ignore_tags'],
			             $this->commObject->config['paywall_tags']
			);
		//This is a giant regex to capture citation tags and the other tags that follow it.
		$regex = '/((' . str_replace( "\{\{", "\{\{\s*", str_replace( "\}\}", "", implode( '|',
		                                                                                   $this->commObject->config['citation_tags']
		                                                                    )
		                                    )
			) . ')[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)\}\})/i';
		$remainderRegex = '/((' . str_replace( "\{\{", "\{\{\s*",
		                                       str_replace( "\}\}", "", implode( '|', $tArray ) )
			) . ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i';
		//Match giant regex for the presence of a citation template.
		$citeTemplate = preg_match( $regex, $scrapText, $citeMatch, PREG_OFFSET_CAPTURE );
		//Match for the presence of an archive template
		$remainder = preg_match( $remainderRegex,
		                         $scrapText, $remainderMatch, PREG_OFFSET_CAPTURE
		);
		//Match for the presence of a bare URL
		$bareLink =
			preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch, PREG_OFFSET_CAPTURE );
		beginparsing:
		$offsets = [];
		//Collect all the offsets of all matches regex patterns
		if( $citeTemplate ) $offsets[] = $citeMatch[0][1];
		if( $remainder ) $offsets[] = $remainderMatch[0][1];
		if( $bareLink ) $offsets[] = $bareMatch[0][1];
		//We want to handle the match that comes first in an article.  This is necessary for the isConnected function to work right.
		if( !empty( $offsets ) ) {
			$firstOffset = min( $offsets );
		} else $firstOffset = 0;
		$characterChopped = false;

		//If a complete citation template with remainder was matched first, then...
		if( $citeTemplate && $citeMatch[0][1] == $firstOffset ) {
			//string is the full match, citation template and respective inline templates
			$returnArray['string'] = $citeMatch[0][0];
			//link_string is the citation template
			$returnArray['link_string'] = $citeMatch[1][0];
			$returnArray['type'] = "template";
			//Name of the citation template
			$returnArray['name'] = trim( str_replace( "{{", "", $citeMatch[2][0] ) );
			$returnArray['offset'] = $citeMatch[0][1];
			$start = $citeMatch[0][1];
			$end = strlen( $citeMatch[0][0] ) + $start;
		} //If we matched a bare link first, then...
		elseif( ( $remainder && $bareLink && $remainderMatch[0][1] > $bareMatch[0][1] ) ||
		        ( $bareLink && !$remainderMatch )
		) {
			$returnArray['type'] = "externallink";
			//Record starting string offset of URL
			$start = $bareMatch[0][1];
			//Detect if this is a bracketed external link
			if( substr( $bareMatch[0][0], 0, 1 ) == "[" && strpos( $scrapText, "]", $start ) !== false &&
			    strpos( $scrapText, "]", $start ) > $start
			) {
				//Record offset of the end of string.  That is one character past the closing bracket location.
				$end = strpos( $scrapText, "]", $start ) + 1;
				//Make sure we're not disrupting an embedded wikilink.
				while( substr( $scrapText, $end - 1, 2 ) == "]]" ) {
					//If so, move past double closing bracket
					$end++;
					//Record new offset of closing bracket.
					$end = strpos( $scrapText, "]", $end ) + 1;
				}
				$recheck = true;
				while( $recheck ) {
					$recheck = false;
					//Let's make sure the closing bracket isn't inside a nowiki tag.
					do {
						$beforeOpen = strrpos( strtolower( substr( $scrapText, 0, $end ) ), "<nowiki" );
						$beforeClose = strrpos( strtolower( substr( $scrapText, 0, $end ) ), "</nowiki" );
						if( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
						    $end !== false
						) {
							$end = strpos( $scrapText, "]", $end ) + 1;
						}
					} while( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
					         $end !== false );
					//Let's make sure the closing bracket isn't inside a comment tag.
					do {
						$beforeOpen = strrpos( strtolower( substr( $scrapText, 0, $end ) ), "<!--" );
						$beforeClose = strrpos( strtolower( substr( $scrapText, 0, $end ) ), "-->" );
						if( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
						    $end !== false
						) {
							$end = strpos( $scrapText, "]", $end ) + 1;
							$recheck = true;
						}
					} while( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
					         $end !== false );
				}
				//A sanity check to make sure we are capturing a bracketed URL
				//In the event we have an end offset that suggests no closing bracket, default to bracketless parsing.
				//The goto in this statement sends execution to the else block of this if statement, which handles
				//bracketless URL parsing.
				if( $end === false || $end <= $start ) goto processPlainURL;
			} else {
				processPlainURL:
				//Record starting point of plain URL
				$start = strpos( $scrapText, $bareMatch[1][0], ( isset( $start ) ? $start : 0 ) );
				//The end is easily calculated by simply taking the string length of the url and adding it to the starting offset.
				$end = $start + strlen( $bareMatch[1][0] );
				//Make sure we're not absorbing a template into the URL.  Curly braces are valid characters.
				if( ( $toffset = strpos( $bareMatch[1][0], "{{" ) ) !== false ) {
					$toffset += $start;
					if( preg_match( '/((\{\{.*?)[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i',
					                $scrapText, $garbage, PREG_OFFSET_CAPTURE, $start
					) ) {
						if( $toffset == $garbage[0][1] ) $end = $toffset;
					}
				}
				//Make sure we don't absorb an HTML tag into the URL, as they are valid URL characters too.
				if( ( $toffset = strpos( $bareMatch[1][0], "<" ) ) !== false ) {
					$toffset += $start;
					if( $end > $toffset ) $end = $toffset;
				}
				//Since this is an unbracketed link, if the URL ends with one of .,:;?!)”<>[]\, then chop off that character.
				while( preg_match( '/[\.\,\:\;\?\!\)\"\>\<\[\]\\\\]/i',
				                   $char = substr( substr( $scrapText, $start, $end - $start ),
				                                   strlen( substr( $scrapText, $start, $end - $start ) ) - 1, 1
				                   )
				) ) {
					if( $char == ")" ) {
						if( strpos( substr( $scrapText, $start, $end - $start ), "(" ) !== false ) {
							break;
						}
					}
					$end--;
					$characterChopped = (int) $characterChopped + 1;
				}
			}
			//Let's make sure we're not inside an unknown template or comments, that could break when modified.
			$toTest = [
				[ [ "{{", "}}" ], [ "", "}}" ] ], [ [ "<!--", "-->" ], [ "--", "-->" ] ],
				[ [ "[[", "]]" ], [ "[[", "]]" ] ]
			];
			foreach( $toTest as $test ) {
				$beforeOpen = strrpos( substr( $scrapText, 0, $start + 1 ), $test[0][0] );
				$beforeClose = strrpos( substr( $scrapText, 0, $start + 1 ), $test[0][1] );
				$afterOpen = strpos( substr( $scrapText, $end ), $test[0][0] );
				$afterClose = strpos( substr( $scrapText, $end ), $test[0][1] );
				embedteststart:
				if( ( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
				      $afterClose !== false && ( $afterOpen === false || $afterOpen > $afterClose ) )
				) {
					//We're inside something we shouldn't touch, let's move on.
					//Look for the next instance of a plain link, starting from the end of the restricted area.
					$afterClose += $end;
					$bareLink =
						preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch,
						            PREG_OFFSET_CAPTURE, $afterClose + strlen( $test[0][1] )
						);
					//Restart parsing analysis at new offset.
					$returnArray = [];
					goto beginparsing;
				} elseif( ( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) &&
				            ( substr( $scrapText, $end - strlen( $test[1][1] ) + (int) $characterChopped,
				                      strlen( $test[0][1] )
				              ) == $test[0][1] ||
				              substr( $scrapText, $end - strlen( $test[1][1] ), strlen( $test[0][1] ) ) ==
				              $test[0][1] ) ) ) {
					$bareLink =
						preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch,
						            PREG_OFFSET_CAPTURE, $end
						);
					//Restart parsing analysis at new offset.
					$returnArray = [];
					goto beginparsing;
				} elseif( ( $afterClose !== false && ( $afterOpen === false || $afterOpen > $afterClose ) &&
				            substr( $scrapText, $start - strlen( $test[0][0] ) + strlen( $test[1][0] ),
				                    strlen( $test[0][0] )
				            ) == $test[0][0] )
				) {
					$bareLink =
						preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch,
						            PREG_OFFSET_CAPTURE, $end
						);
					//Restart parsing analysis at new offset.
					$returnArray = [];
					goto beginparsing;
				} elseif( ( $afterClose === false && $afterOpen === false ) &&
				          substr( $scrapText, $start - strlen( $test[0][0] ) + strlen( $test[1][0] ),
				                  strlen( $test[0][0] )
				          ) == $test[0][0] &&
				          ( substr( $scrapText, $end - strlen( $test[1][1] ) + (int) $characterChopped,
				                    strlen( $test[0][1] )
				            ) == $test[0][1] ||
				            substr( $scrapText, $end - strlen( $test[1][1] ), strlen( $test[0][1] ) ) ==
				            $test[0][1] ) ) {
					$bareLink =
						preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch,
						            PREG_OFFSET_CAPTURE, $end
						);
					//Restart parsing analysis at new offset.
					$returnArray = [];
					goto beginparsing;
				} elseif( ( $beforeOpen !== false && ( $beforeClose === false || $beforeClose < $beforeOpen ) ) &&
				          ( $afterClose !== false && !( $afterOpen === false || $afterOpen > $afterClose ) ) ) {
					$afterOpen = strpos( substr( $scrapText, $end ), $test[0][0], $afterClose );
					$afterClose = strpos( substr( $scrapText, $end ), $test[0][1], $afterClose );
					goto embedteststart;
				} elseif( ( $beforeOpen !== false && !( $beforeClose === false || $beforeClose < $beforeOpen ) &&
				            $afterClose !== false && ( $afterOpen === false || $afterOpen > $afterClose ) ) ) {
					$beforeClose = strrpos( substr( $scrapText, 0, $beforeOpen + 1 ), $test[0][1] );
					$beforeOpen = strrpos( substr( $scrapText, 0, $beforeOpen + 1 ), $test[0][0] );
					goto embedteststart;
				} elseif( $beforeOpen !== false &&
				          substr( $scrapText, $end - strlen( $test[0][1] ), strlen( $test[0][1] ) ) == $test[0][1] ) {
					while( $beforeClose !== false && $beforeOpen !== false && $beforeOpen < $beforeClose ) {
						$beforeClose = strrpos( substr( $scrapText, 0, $beforeOpen + 1 ), $test[0][1] );
						$beforeOpen = strrpos( substr( $scrapText, 0, $beforeOpen + 1 ), $test[0][0] );
					}
					goto embedteststart;
				}
			}
			embedtestend:
			//Grab the URL with or without brackets, and save it to link_string
			$returnArray['link_string'] = substr( $scrapText, $start, $end - $start );
			$returnArray['offset'] = $start;
			//Transfer entire string to the string index
			$returnArray['string'] = trim( substr( $scrapText, $start, $end - $start ) );
		} //If we detected an inline tag on it's own, then...
		elseif( ( $remainder && $bareLink && $remainderMatch[0][1] < $bareMatch[0][1] ) ||
		        ( !$bareLink && $remainder )
		) {
			$returnArray['remainder'] = $remainderMatch[0][0];
			$returnArray['link_string'] = "";
			$returnArray['string'] = $remainderMatch[0][0];
			$returnArray['type'] = "stray";
			$returnArray['name'] = str_replace( "{{", "", $remainderMatch[2][0] );
			$returnArray['offset'] = $remainderMatch[0][1];
			$start = $remainderMatch[0][1];
			$end = strlen( $remainderMatch[0][0] ) + $start;
		}

		if( isset( $returnArray['remainder'] ) && preg_match( '/((' . str_replace( "\{\{", "\{\{\s*",
		                                                                           str_replace( "\}\}", "",
		                                                                                        implode( '|',
		                                                                                                 $this->commObject->config['archive_tags']
		                                                                                        )
		                                                                           )
		                                                      ) .
		                                                      ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i',
		                                                      $returnArray['remainder'], $garbage
			)
		) {
			//Remove archive tags from the search array.
			$tArray = array_merge( $this->commObject->config['deadlink_tags'],
			                       $this->commObject->config['ignore_tags'],
			                       $this->commObject->config['paywall_tags']
			);
			$remainderRegex = '/((' . str_replace( "\{\{", "\{\{\s*",
			                                       str_replace( "\}\}", "", implode( '|', $tArray ) )
				) . ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i';
		}

		//Look for more remainder stuff.
		while( !empty( $offsets ) && ( $remainder = preg_match( $remainderRegex,
		                                                        $scrapText, $remainderMatch, PREG_OFFSET_CAPTURE, $end
			) ) ) {
			//Match giant regex for the presence of a citation template.
			$citeTemplate = preg_match( $regex, $scrapText, $citeMatch, PREG_OFFSET_CAPTURE, $end );
			//Match for the presence of a bare URL
			$bareLink =
				preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $scrapText, $bareMatch, PREG_OFFSET_CAPTURE,
				            $end
				);
			$offsets = [];
			//Collect all the offsets of all matches regex patterns
			if( $citeTemplate ) $offsets[] = $citeMatch[0][1];
			if( $remainder ) $offsets[] = $remainderMatch[0][1];
			if( $bareLink ) $offsets[] = $bareMatch[0][1];
			//We want to handle the match that comes first in an article.  This is necessary for the isConnected function to work right.
			if( !empty( $offsets ) ) {
				$firstOffset = min( $offsets );
			} else $firstOffset = 0;

			if( $firstOffset !== 0 && $firstOffset == $remainderMatch[0][1] ) {
				$rStart = $remainderMatch[0][1];
				$rEnd = $rStart + strlen( $remainderMatch[0][0] );
				$inBetween = substr( $scrapText, $end, $rStart - $end );
				if( !isset( $returnArray['remainder'] ) ) $returnArray['remainder'] = "";
				if( strpos( $inBetween, "\n\n" ) === false && strlen( $inBetween ) < 50 &&
				    ( strpos( $inBetween, "\n" ) === false || !preg_match( '/\S/i', $inBetween ) ) &&
				    !preg_match( '/[\[]?(' . $this->schemelessURLRegex . ')/i', $inBetween, $garbage ) &&
				    ( isset( $garbage[0] ) ? API::resolveExternalLink( $garbage[0] ) : false ) === false
				) {
					$returnArray['remainder'] .= $inBetween . $remainderMatch[0][0];
					$end = $rEnd;
				} else break;
			} else break;

			if( isset( $returnArray['remainder'] ) && preg_match( '/((' . str_replace( "\{\{", "\{\{\s*",
			                                                                           str_replace( "\}\}", "",
			                                                                                        implode( '|',
			                                                                                                 $this->commObject->config['archive_tags']
			                                                                                        )
			                                                                           )
			                                                      ) .
			                                                      ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i',
			                                                      $returnArray['remainder'], $garbage
				)
			) {
				//Remove archive tags from the search array.
				$tArray = array_merge( $this->commObject->config['deadlink_tags'],
				                       $this->commObject->config['ignore_tags'],
				                       $this->commObject->config['paywall_tags']
				);
				$remainderRegex = '/((' . str_replace( "\{\{", "\{\{\s*",
				                                       str_replace( "\}\}", "", implode( '|', $tArray ) )
					) . ')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?))?\}\})+/i';
			}
		}

		if( !empty( $returnArray ) ) {
			if( !isset( $returnArray['remainder'] ) ) $returnArray['remainder'] = "";
			$returnArray['string'] = substr( $scrapText, $start, $end - $start );
			//We need preg_replace since it has a limiter whereas str_replace does not.
			$scrapText = preg_replace( '/' . preg_quote( $returnArray['string'], '/' ) . '/', "", $scrapText, 1 );

			return $returnArray;
		}

		return false;
	}

	/**
	 * Parses a given refernce/external link string and returns details about it.
	 *
	 * @param string $linkString Primary reference string
	 * @param string $remainder Left over stuff that may apply
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array    Details about the link
	 */
	public function getLinkDetails( $linkString, $remainder ) {
		$returnArray = [];
		$returnArray['link_string'] = $linkString;
		$returnArray['remainder'] = $remainder;
		$returnArray['has_archive'] = false;
		$returnArray['link_type'] = "x";
		$returnArray['tagged_dead'] = false;
		$returnArray['is_archive'] = false;
		$returnArray['access_time'] = false;
		$returnArray['tagged_paywall'] = false;
		$returnArray['is_paywall'] = false;
		$returnArray['permanent_dead'] = false;

		//Check if there are tags flagging the bot to ignore the source
		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['ignore_tags'] ), $remainder, $params ) ||
		    preg_match( $this->fetchTemplateRegex( $this->commObject->config['ignore_tags'] ), $linkString, $params )
		) {
			return [ 'ignore' => true ];
		}
		if( !preg_match( $this->fetchTemplateRegex( $this->commObject->config['citation_tags'], false ), $linkString,
		                 $params
			) && preg_match( '/' . $this->schemelessURLRegex . '/i', $this->filterText( $linkString ), $params )
		) {
			$this->analyzeBareURL( $returnArray, $params );
		} elseif( preg_match( $this->fetchTemplateRegex( $this->commObject->config['citation_tags'], false ),
		                      $linkString, $params
		) ) {
			if( $this->analyzeCitation( $returnArray, $params ) ) return [ 'ignore' => true ];
		}
		//Check the source remainder
		$this->analyzeRemainder( $returnArray, $remainder );

		//Check for the presence of a paywall tag
		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['paywall_tags'] ), $remainder, $params ) ||
		    preg_match( $this->fetchTemplateRegex( $this->commObject->config['paywall_tags'] ), $linkString, $params )
		) {
			$returnArray['tagged_paywall'] = true;
		}

		//If there is no url after this then this source is useless.
		if( !isset( $returnArray['url'] ) ) return [ 'ignore' => true ];

		//A hacky checky for HTML encoded pipes
		$returnArray['url'] = str_replace( "&#124;", "|", $returnArray['url'] );
		if( isset( $returnArray['archive_url'] ) ) $returnArray['archive_url'] =
			str_replace( "&#124;", "|", $returnArray['archive_url'] );

		//Resolve templates, into URLs
		//If we can't resolve them, then ignore this link, as it will be fruitless to handle them.
		if( strpos( $returnArray['url'], "{{" ) !== false ) {
			preg_match( '/\{\{[\s\S\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)\}\}/i', $returnArray['url'],
			            $params
			);
			$returnArray['template_url'] = $returnArray['url'];
			$returnArray['url'] = API::resolveExternalLink( $returnArray['template_url'] );
			if( $returnArray['url'] === false ) {
				$returnArray['url'] =
					API::resolveExternalLink( "https:" . $returnArray['template_url'] );
			}
			if( $returnArray['url'] === false ) return [ 'ignore' => true ];
		}

		if( empty( $returnArray['original_url'] ) ) $returnArray['original_url'] = $returnArray['url'];

		if( $returnArray['is_archive'] === false ) $tmp = str_replace( "&#124;", "|", $returnArray['original_url'] );
		else $tmp = str_replace( "&#124;", "|", $returnArray['url'] );
		//Extract nonsense stuff from the URL, probably due to a misuse of wiki syntax
		//If a url isn't found, it means it's too badly formatted to be of use, so ignore
		if( ( ( $returnArray['link_type'] === "template" || ( strpos( $tmp, "[" ) &&
		                                                      strpos( $tmp, "]" ) ) ) &&
		      preg_match( '/' . $this->schemelessURLRegex . '/i', $tmp, $match ) ) ||
		    preg_match( '/' . $this->schemedURLRegex . '/i', $tmp, $match )
		) {
			//Sanitize the URL to keep it consistent in the DB.
			$returnArray['url'] =
				$this->deadCheck->sanitizeURL( $match[0], true );
			//If the sanitizer can't handle the URL, ignore the reference to prevent a garbage edit.
			if( $returnArray['url'] == "https:///" ) return [ 'ignore' => true ];
			if( $returnArray['url'] == "https://''/" ) return [ 'ignore' => true ];
			if( $returnArray['url'] == "http://''/" ) return [ 'ignore' => true ];
			if( isset( $match[1] ) ) {
				$returnArray['fragment'] = $match[1];
			} else $returnArray['fragment'] = null;
			if( isset( $returnArray['archive_url'] ) ) {
				$parts = $this->deadCheck->parseURL( $returnArray['archive_url'] );
				if( isset( $parts['fragment'] ) ) {
					$returnArray['archive_fragment'] = $parts['fragment'];
				} else $returnArray['archive_fragment'] = null;
				$returnArray['archive_url'] = preg_replace( '/#.*/', '', $returnArray['archive_url'] );
			}
		} else {
			return [ 'ignore' => true ];
		}

		if( $returnArray['access_time'] === false ) {
			$returnArray['access_time'] = "x";
		}

		if( isset( $returnArray['original_url'] ) &&
		    $this->deadCheck->sanitizeURL( $returnArray['original_url'], true ) !=
		    $this->deadCheck->sanitizeURL( $returnArray['url'], true ) &&
		    $returnArray['is_archive'] === false && $returnArray['has_archive'] === true &&
		    !isset( $returnArray['template_url'] )
		) {
			$returnArray['archive_mismatch'] = true;
			$returnArray['url'] = $this->deadCheck->sanitizeURL( $returnArray['original_url'], true );
			unset( $returnArray['original_url'] );
		}

		if( isset( $returnArray['archive_template'] ) ) {
			if( isset( $returnArray['archive_template']['parameters']['__FORMAT__'] ) ) {
				$returnArray['archive_template']['format'] =
					$returnArray['archive_template']['parameters']['__FORMAT__'];
				unset( $returnArray['archive_template']['parameters']['__FORMAT__'] );
			}
		}

		if( isset( $returnArray['tag_template'] ) ) {
			if( isset( $returnArray['tag_template']['parameters']['__FORMAT__'] ) ) {
				$returnArray['tag_template']['format'] =
					$returnArray['tag_template']['parameters']['__FORMAT__'];
				unset( $returnArray['tag_template']['parameters']['__FORMAT__'] );
			}
		}

		return $returnArray;
	}

	//Parsing engine of templates.  This parses the body string of a template, respecting embedded templates and wikilinks.

	/**
	 * Generates a regex that detects the given list of escaped templates.
	 *
	 * @param array $escapedTemplateArray A list of bracketed templates that have been escaped to search for.
	 * @param bool $optional Make the reqex not require additional template parameters.
	 *
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return string Generated regex
	 */
	protected function fetchTemplateRegex( $escapedTemplateArray, $optional = true ) {
		if( $optional === true ) {
			$returnRegex = $this->templateRegexOptional;
		} else $returnRegex = $this->templateRegexMandatory;

		if( !empty( $escapedTemplateArray ) ) {
			$escapedTemplateArray = implode( '|', $escapedTemplateArray );
			$escapedTemplateArray = str_replace( "\{\{", "\{\{\s*", str_replace( "\}\}", "", $escapedTemplateArray ) );
			$returnRegex = str_replace( "{{{{templates}}}}", $escapedTemplateArray, $returnRegex );
		} else {
			$returnRegex = str_replace( "{{{{templates}}}}", "nullNULLfalseFALSE", $returnRegex );
		}


		return $returnRegex;
	}

	/**
	 * Analyzes the bare link
	 *
	 * @param array $returnArray Array being generated
	 * @param string $linkString Link string being parsed
	 * @param array $params Extracted URL from link string
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return void
	 */
	protected function analyzeBareURL( &$returnArray, &$params ) {

		if( strpos( $params[0], "''" ) !== false ) $params[0] = substr( $params[0], 0, strpos( $params[0], "''" ) );
		if( stripos( $params[0], "%c2" ) === false && stripos( urlencode( $params[0] ), "%c2" ) !== false ) {
			$params[0] = urldecode( substr( urlencode( $params[0] ), 0, stripos( urlencode( $params[0] ), "%c2" ) ) );
		}
		if( stripos( $params[0], "%e3" ) === false && stripos( urlencode( $params[0] ), "%e3" ) !== false ) {
			$params[0] = urldecode( substr( urlencode( $params[0] ), 0, stripos( urlencode( $params[0] ), "%e3" ) ) );
		}
		if( strpos( $params[0], "\"" ) !== false ) $params[0] = substr( $params[0], 0, strpos( $params[0], "\"" ) );

		$returnArray['original_url'] =
		$returnArray['url'] = htmlspecialchars_decode( $params[0], true );
		$returnArray['link_type'] = "link";
		$returnArray['access_time'] = "x";
		$returnArray['is_archive'] = false;
		$returnArray['tagged_dead'] = false;
		$returnArray['has_archive'] = false;

		if( preg_match( '/\[.*?\s+(.*?)\]/', $returnArray['link_string'], $match ) && !empty( $match[1] ) ) {
			$returnArray['title'] = htmlspecialchars_decode( $match[1] );
		}

		//If this is a bare archive url
		if( API::isArchive( $returnArray['url'], $returnArray ) ) {
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = true;
			if( !isset( $returnArray['archive_type'] ) || $returnArray['archive_type'] != "invalid" ) {
				$returnArray['archive_type'] = "link";
			}
			//$returnArray['link_type'] = "x";
			$returnArray['access_time'] = $returnArray['archive_time'];
		}
	}

	/**
	 * Analyze the citation template
	 *
	 * @param array $returnArray Array being generated in master function
	 * @param string $params Citation template regex match breakdown
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return void
	 */
	protected function analyzeCitation( &$returnArray, &$params ) {
		$returnArray['tagged_dead'] = false;
		$returnArray['url_usurp'] = false;
		$returnArray['link_type'] = "template";
		$returnArray['link_template'] = [];
		$returnArray['link_template']['parameters'] = $this->getTemplateParameters( $params[2] );
		$returnArray['link_template']['format'] = $returnArray['link_template']['parameters']['__FORMAT__'];
		unset( $returnArray['link_template']['parameters']['__FORMAT__'] );
		$returnArray['link_template']['name'] = str_replace( "{{", "", $params[1] );
		$returnArray['link_template']['string'] = $params[0];
		$returnArray['link_template']['template_map'] =
			self::getCiteMap( $returnArray['link_template']['name'], $this->commObject->config['template_definitions'],
			                  $returnArray['link_template']['parameters']
			);

		$mappedObjects = $returnArray['link_template']['template_map']['services']['@default'];
		$toLookFor = [
			'url'   => true, 'access_date' => false, 'archive_url' => false, 'deadvalues' => false, 'paywall' => false,
			'title' => false
		];

		foreach( $toLookFor as $mappedObject => $required ) {
			if( $required && !isset( $mappedObjects[$mappedObject] ) ) return false;

			$mapFound = false;
			if( isset( $mappedObjects[$mappedObject] ) ) foreach( $mappedObjects[$mappedObject] as $sID => $dataObject )
			{
				if( is_array( $dataObject ) ) $dataIndex = $dataObject['index'];
				else $dataIndex = $dataObject;

				foreach( $returnArray['link_template']['template_map']['data'][$dataIndex]['mapto'] as $paramIndex ) {
					if( !empty( $returnArray['link_template']['parameters'][$returnArray['link_template']['template_map']['params'][$paramIndex]] ) ) {
						$mapFound = true;
						$value =
							htmlspecialchars_decode( $this->filterText( str_replace( "{{!}}", "|",
							                                                         str_replace( "{{=}}", "=",
							                                                                      $returnArray['link_template']['parameters'][$returnArray['link_template']['template_map']['params'][$paramIndex]]
							                                                         )
							                                            ), true
							)
							);

						switch( $mappedObject ) {
							case "title":
								$returnArray['title'] = $value;
								break;
							case "url":
								$returnArray['original_url'] = $returnArray['url'] = $value;
								break;
							case "access_date":
								$time = self::strptime( $value, $this->retrieveDateFormat( $value ) );
								if( is_null( $time ) || $time === false ) {
									$timestamp =
										$this->filterText( API::resolveWikitext( $value ) );
									$time = self::strptime( $timestamp, $this->retrieveDateFormat( $timestamp ) );
								}
								if( $time === false || is_null( $time ) ) $time = "x";
								else {
									$time = self::strptimetoepoch( $time );
								}
								$returnArray['access_time'] = $time;
								break;
							case "archive_url":
								$returnArray['archive_url'] = $value;
								if( API::isArchive( $returnArray['archive_url'], $returnArray ) ) {
									$returnArray['archive_type'] = "parameter";
									$returnArray['has_archive'] = true;
									$returnArray['is_archive'] = false;
								}
								break;
							case "deadvalues":
								$valuesYes = explode( ";;", $dataObject['valueyes'] );
								if( strpos( $dataObject['valueyes'], '$$TIMESTAMP' ) !== false ) $timestampYes = true;
								else $timestampYes = false;
								$valuesNo = explode( ";;", $dataObject['valueno'] );
								$valuesUsurp = explode( ";;", $dataObject['valueusurp'] );
								$defaultValue = $dataObject['defaultvalue'];
								if( $timestampYes || in_array( $value, $valuesYes ) ) {
									$returnArray['tagged_dead'] = true;
									$returnArray['tag_type'] = "parameter";
								} elseif( in_array( $value, $valuesNo ) ) {
									$returnArray['force_when_dead'] = true;
								} elseif( in_array( $value, $valuesUsurp ) ) {
									$returnArray['tagged_dead'] = true;
									$returnArray['tag_type'] = "parameter";
									$returnArray['url_usurp'] = true;
								} elseif( $defaultValue == "yes" && $returnArray['has_archive'] === true ) {
									$returnArray['tagged_dead'] = true;
									$returnArray['tag_type'] = "implied";
								}
								break;
							case "paywall":
								$valuesYes = explode( ";;", $dataObject['valueyes'] );
								$valuesNo = explode( ";;", $dataObject['valueno'] );
								if( in_array( $value, $valuesYes ) ) {
									$returnArray['tagged_paywall'] = true;
								} elseif( in_array( $value, $valuesNo ) ) {
									$returnArray['tagged_paywall'] = false;
								} else continue;
						}
						break;
					}
				}
				if( $mapFound ) continue 2;
				if( $required && !$mapFound ) return false;
			}
		}
	}

	/**
	 * Fetch the parameters of the template
	 *
	 * @param string $templateString String of the template without the {{example bit
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array Template parameters with respective values
	 */
	public function getTemplateParameters( $templateString ) {
		if( isset( $this->templateParamCache[$templateString] ) ) {
			return $this->templateParamCache[$templateString];
		}
		$errorSetting = error_reporting();
		$returnArray = [];
		$formatting = [];
		$tArray = [];
		if( empty( $templateString ) ) return $returnArray;
		//Suppress errors for this functions.  While it almost never throws an error,
		//some mis-formatted templates cause the template parser to throw up.
		//In all cases however, a failure to properly parse the template will always
		//result in false being returned, error or not.  No sense in cluttering the output.
		error_reporting( 0 );
		while( true ) {
			$offset = 0;
			$loopcount = 0;
			$pipepos = strpos( $templateString, "|", $offset );
			$tstart = strpos( $templateString, "{{", $offset );
			$tend = strpos( $templateString, "}}", $offset );
			$lstart = strpos( $templateString, "[[", $offset );
			$lend = strpos( $templateString, "]]", $offset );
			$nstart = strpos( strtolower( $templateString ), "<nowiki", $offset );
			$nend = strpos( strtolower( $templateString ), "</nowiki", $offset );
			$cstart = strpos( $templateString, "<!--", $offset );
			$cend = strpos( $templateString, "-->", $offset );
			while( true ) {
				$loopcount++;
				$offsets = [];
				if( $lend !== false ) $offsets[] = $lend;
				if( $tend !== false ) $offsets[] = $tend;
				if( $cend !== false ) $offsets[] = $cend;
				if( $nend !== false ) $offsets[] = $nend;
				if( !empty( $offsets ) ) $offset = min( $offsets ) + 1;
				//Make sure we're not inside an embedded wikilink or template, or nowiki and comment tags.
				while( ( $tstart < $pipepos && $tend > $pipepos ) || ( $lstart < $pipepos && $lend > $pipepos ) ||
				       ( $cstart < $pipepos && $cend > $pipepos ) || ( $nstart < $pipepos && $nend > $pipepos ) ) {
					$pipepos = strpos( $templateString, "|", $pipepos + 1 );
				}
				$tstart = strpos( $templateString, "{{", $offset );
				$tend = strpos( $templateString, "}}", $offset );
				$lstart = strpos( $templateString, "[[", $offset );
				$lend = strpos( $templateString, "]]", $offset );
				$nstart = strpos( strtolower( $templateString ), "<nowiki", $offset );
				$nend = strpos( strtolower( $templateString ), "</nowiki", $offset );
				$cstart = strpos( $templateString, "<!--", $offset );
				$cend = strpos( $templateString, "-->", $offset );
				if( ( $pipepos < $tstart || $tstart === false ) && ( $pipepos < $lstart || $lstart === false ) &&
				    ( $pipepos < $nstart || $nstart === false ) && ( $pipepos < $cstart || $cstart === false )
				) break;
				if( $loopcount >= 500 ) {
					//re-enable error reporting
					error_reporting( $errorSetting );

					//We've looped more than 500 times, and haven't been able to parse the template.  Likely won't be able to.  Return false.
					$this->templateParamCache[$templateString] = false;

					return false;
				}
			}
			if( $pipepos !== false ) {
				$tArray[] = substr( $templateString, 0, $pipepos );
				$templateString = substr_replace( $templateString, "", 0, $pipepos + 1 );
			} else {
				$tArray[] = $templateString;
				break;
			}
		}
		$count = 0;
		foreach( $tArray as $tid => $tstring ) $tArray[$tid] = self::parameterExplode( '=', $tstring, $formatting );
		foreach( $tArray as $array ) {
			$count++;
			if( count( $array ) == 2 ) {
				$returnArray[$this->filterText( $array[0], true )] = trim( $array[1] );
			} else $returnArray[$count] = trim( $array[0] );
		}
		//re-enable error reporting
		error_reporting( $errorSetting );

		if( !empty( $formatting ) ) {
			$returnArray['__FORMAT__'] = array_search( max( $formatting ), $formatting );
			if( count( $formatting > 4 ) && strpos( $returnArray['__FORMAT__'], "\n" ) !== false )
				$returnArray['__FORMAT__'] = "multiline-pretty";
		} else $returnArray['__FORMAT__'] = "{key}={value} ";

		$this->templateParamCache[$templateString] = $returnArray;

		return $returnArray;
	}

	/**
	 * Break the parameters and values apart respecting HTML comments and nowiki tags
	 *
	 * @param string $delimiter The value to explode
	 * @param string $string String to explode
	 * @param array $formatting An array of formatting styles the template is formatted in.
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array Exploded string
	 */
	public static function parameterExplode( $delimeter, $string, &$formatting = [] ) {
		$errorSetting = error_reporting();
		//Suppress errors for this functions.  While it almost never throws an error,
		//some mis-formatted templates cause the template parser to throw up.
		//In all cases however, a failure to properly parse the template will always
		//result in false being returned, error or not.  No sense in cluttering the output.
		error_reporting( 0 );
		$returnArray = [];
		$offset = 0;
		$delimPos = strpos( $string, $delimeter, $offset );
		$nstart = strpos( strtolower( $string ), "<nowiki", $offset );
		$nend = strpos( strtolower( $string ), "</nowiki", $offset );
		$tstart = strpos( $string, "{{", $offset );
		$tend = strpos( $string, "}}", $offset );
		$lstart = strpos( $string, "[[", $offset );
		$lend = strpos( $string, "]]", $offset );
		$cstart = strpos( $string, "<!--", $offset );
		$cend = strpos( $string, "-->", $offset );

		while( true ) {
			if( $lend !== false ) $offsets[] = $lend;
			if( $tend !== false ) $offsets[] = $tend;
			if( $cend !== false ) $offsets[] = $cend;
			if( $nend !== false ) $offsets[] = $nend;
			if( !empty( $offsets ) ) $offset = min( $offsets ) + 1;
			//Make sure we're not inside an embedded wikilink or template, or nowiki and comment tags.
			while( ( $tstart < $delimPos && $tend > $delimPos ) || ( $lstart < $delimPos && $lend > $delimPos ) ||
			       ( $cstart < $delimPos && $cend > $delimPos ) || ( $nstart < $delimPos && $nend > $delimPos ) ) {
				$delimPos = strpos( $string, $delimeter, $delimPos + 1 );
			}
			$nstart = strpos( strtolower( $string ), "<nowiki", $offset );
			$nend = strpos( strtolower( $string ), "</nowiki", $offset );
			$cstart = strpos( $string, "<!--", $offset );
			$cend = strpos( $string, "-->", $offset );
			$tstart = strpos( $string, "{{", $offset );
			$tend = strpos( $string, "}}", $offset );
			$lstart = strpos( $string, "[[", $offset );
			$lend = strpos( $string, "]]", $offset );
			if( $delimPos === false ||
			    ( ( $delimPos < $tstart || $tstart === false ) && ( $delimPos < $lstart || $lstart === false ) &&
			      ( $delimPos < $nstart || $nstart === false ) && ( $delimPos < $cstart || $cstart === false ) )
			) break;
		}

		if( $delimPos !== false ) {
			preg_match( '/^(\s*).+?(\s*)$/iu', substr( $string, 0, $delimPos ), $fstring1 );
			$returnArray[] = substr( $string, 0, $delimPos );
			preg_match( '/^(\s*).+?(\s*)$/iu', substr( $string, $delimPos + 1 ), $fstring2 );
			$returnArray[] = substr( $string, $delimPos + 1 );
			if( isset( $formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] . '{value}' .
			                       $fstring2[2]]
			) ) $formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] . '{value}' . $fstring2[2]]++;
			else $formatting[$fstring1[1] . '{key}' . $fstring1[2] . '=' . $fstring2[1] . '{value}' . $fstring2[2]] = 1;
		} else {
			$returnArray[] = $string;
		}

		//re-enable error reporting
		error_reporting( $errorSetting );

		return $returnArray;
	}

	/**
	 * Fetches the correct mapping information for the given Citation template
	 *
	 * @param string $templateName The name of the template to get the mapping data for
	 *
	 * @static
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return array The template mapping data to use.
	 */
	public static function getCiteMap( $templateName, $templateDefinitions = [], $templateParameters = [],
	                                   &$matchValue = 0
	) {
		$templateName = trim( $templateName, "{}" );

		$matchValue = 0;
		$templateList = "";
		$templateData = "";

		if( is_int( $templateDefinitions['template-list'] ) ) {
			$templateList = unserialize( API::readFile( $templateDefinitions['template-list'] ) );
		} else {
			$templateList = $templateDefinitions['template-list'];
		}

		if( !in_array( "{{{$templateName}}}", $templateList ) ) {
			$templateName = API::getRedirectRoot( API::getTemplateNamespaceName() . ":$templateName" );
			$templateName = substr( $templateName, strlen( API::getTemplateNamespaceName() ) + 1 );
		}

		if( is_int( $templateDefinitions[$templateName] ) ) {
			$templateData = unserialize( API::readFile( $templateDefinitions[$templateName] ) );
		} else {
			$templateData = $templateDefinitions[$templateName];
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

			$mostMatches = max( $bestMatches );
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
	 * A customized strptime function that automatically bridges the gap between Windows, Linux, and Mac OSes.
	 *
	 * @param string $format Formatting string in the Linux format
	 * @param int|bool $time A unix epoch.  Default current time.
	 * @param bool|string Passed in recursively.  Ignore this value.
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return int|false A parsed time array or false on failure.
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
	 * Get page date formatting standard
	 *
	 * @param bool|string $default Return default format, or return supplied date format of timestamp, provided a page
	 *     tag doesn't override it.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return string Format to be fed in time()
	 */
	protected function retrieveDateFormat( $default = false ) {
		if( $default === true ) return $this->commObject->config['dateformat']['syntax']['@default']['format'];
		else {
			foreach( $this->commObject->config['dateformat']['syntax'] as $index => $rule ) {
				if( isset( $rule['regex'] ) &&
				    preg_match( '/' . $rule['regex'] . '/i', $this->commObject->content ) ) return $rule['format'];
				elseif( !isset( $rule['regex'] ) ) {
					if( !is_bool( $default ) &&
					    self::strptime( $default, $rule['format'] ) !== false ) return $rule['format'];
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

						$searchRegex = preg_replace( '/\%\-?[uw]/', '\\d', $searchRegex );
						$searchRegex = preg_replace( '/\%\-?[deUVWmCgyHkIlMS]/', '\\d\\d?', $searchRegex );
						$searchRegex = preg_replace( '/\%\-?[GY]/', '\\d{4}', $searchRegex );
						$searchRegex = preg_replace( '/\%[aAbBhzZ]/', '\\p{L}+', $searchRegex );

						if( preg_match( '/' . $searchRegex . '/', $default, $match ) &&
						    self::strptime( $match[0], str_replace( "%-", "%", $rule['format'] ) ) !==
						    false ) return $rule['format'];
						elseif( self::strptime( $default, "%c" ) !== false ) return "%c";
						elseif( self::strptime( $default, "%x" ) !== false ) return "%x";
					}
				}
			}

			return $this->commObject->config['dateformat']['syntax']['@default']['format'];
		}
	}

	/**
	 * Convert strptime outputs to a unix epoch
	 *
	 * @param array $strptime A strptime generated array
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return int|false A unix timestamp or false on failure.
	 */
	public static function strptimetoepoch( $strptime ) {
		return mktime( $strptime['tm_hour'], $strptime['tm_min'], $strptime['tm_sec'], $strptime['tm_mon'] + 1,
		               $strptime['tm_mday'], $strptime['tm_year'] + 1900
		);
	}

	/**
	 * Analyze the remainder string
	 *
	 * @param array $returnArray Array being generated in master function
	 * @param string $remainder Remainder string
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return string The language code of the template.
	 */
	protected function analyzeRemainder( &$returnArray, &$remainder ) {
		//If there's an archive tag, then...
		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['archive_tags'] ), $remainder, $params2
		) ) {
			if( $returnArray['has_archive'] === false ) {
				$returnArray['archive_type'] = "template";
				$returnArray['archive_template'] = [];
				$returnArray['archive_template']['parameters'] = $this->getTemplateParameters( $params2[2] );
				$returnArray['archive_template']['name'] = str_replace( "{{", "", $params2[1] );
				$returnArray['archive_template']['string'] = $params2[0];
			}

			//If there already is an archive in this source, it's means there's an archive template attached to a citation template.  That's needless confusion when sourcing.
			if( $returnArray['link_type'] == "template" && $returnArray['has_archive'] === false ) {
				$returnArray['archive_type'] = "invalid";
				$returnArray['tagged_dead'] = true;
				$returnArray['tag_type'] = "implied";
			} elseif( $returnArray['has_archive'] === true ) {
				$returnArray['redundant_archives'] = true;

				return;
			}

			$returnArray['has_archive'] = true;

			//Process all the defined tags
			foreach( $this->commObject->config['all_archives'] as $archiveName => $archiveData ) {
				$archiveName2 = str_replace( " ", "_", $archiveName );
				if( isset( $this->commObject->config["darchive_$archiveName2"] ) ) {
					if( preg_match( $this->fetchTemplateRegex( $this->commObject->config["darchive_$archiveName2"] ),
					                $remainder
					) ) {
						$tmpAnalysis = [];
						foreach( $archiveData['archivetemplatedefinitions']['services'] as $service => $mappedObjects )
						{
							$tmpAnalysis[$service] = [];
							if( !isset( $mappedObjects['archive_url'] ) ) {
								foreach( $mappedObjects['archive_date'] as $id => $mappedArchiveDate ) {
									foreach(
										$archiveData['archivetemplatedefinitions']['data'][$mappedArchiveDate['index']]['mapto']
										as $paramIndex
									) {
										if( isset( $returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]] ) ) {
											switch( $mappedArchiveDate['type'] ) {
												case "microepochbase62":
													$webciteTimestamp =
														$returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]];
													$decodedTimestamp =
														API::to10( $returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]],
														           62
														);
												case "microepoch":
													if( !isset( $decodedTimestamp ) ) $decodedTimestamp =
														floor( $returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]]
														);
													else $decodedTimestamp = floor( $decodedTimestamp / 1000000 );
													goto epochCheck;
												case "epochbase62":
													$decodedTimestamp =
														API::to10( $returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]],
														           62
														);
												case "epoch":
													epochCheck:
													if( !isset( $decodedTimestamp ) ) $decodedTimestamp =
														$returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]];
													if( !is_numeric( $decodedTimestamp ) ) {
														unset( $decodedTimestamp );
														break 2;
													}
													if( $decodedTimestamp > time() ||
													    $decodedTimestamp < 831859200 ) {
														unset( $decodedTimestamp );
														break 2;
													}
													$tmpAnalysis[$service]['timestamp'] = $decodedTimestamp;
													unset( $decodedTimestamp );
													break;
												case "timestamp":
													$decodedTimestamp =
														self::strptime( $returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]],
														                $mappedArchiveDate['format']
														);
													if( $decodedTimestamp === false || is_null( $decodedTimestamp ) ) {
														$decodedTimestamp =
															strtotime( $returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]]
															);
														if( $decodedTimestamp === false ) break 2;
														$returnArray['archive_type'] = 'invalid';
													} else {
														$decodedTimestamp = self::strptimetoepoch( $decodedTimestamp );
													}
													break;
											}
											if( $decodedTimestamp ) {
												$tmpAnalysis[$service]['timestamp'] =
													$decodedTimestamp;
												$archiveURLTimestamp =
													self::strftime( "%Y%m%d%H%M%S", $decodedTimestamp );
											}
											break;
										}
									}
								}
								foreach(
									$archiveData['archivetemplatedefinitions']['data'][$mappedObjects['url'][0]]['mapto']
									as $paramIndex
								) {
									if( isset( $returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]] ) ) {
										$tmpAnalysis[$service]['url'] =
											$returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]];
										break;
									}
								}
								if( isset( $tmpAnalysis[$service]['timestamp'] ) &&
								    isset( $tmpAnalysis[$service]['url'] ) ) {
									$tmpAnalysis[$service]['complete'] = true;
								} else {
									$tmpAnalysis[$service]['complete'] = false;
								}
							} else {
								foreach(
									$archiveData['archivetemplatedefinitions']['data'][$mappedObjects['archive_url'][0]]['mapto']
									as $paramIndex
								) {
									if( isset( $returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]] ) ) {
										$tmpAnalysis[$service]['archive_url'] =
											$returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]];
										break;
									}
								}
								if( isset( $tmpAnalysis[$service]['archive_url'] ) ) {
									$tmpAnalysis[$service]['complete'] = true;
								} else {
									$tmpAnalysis[$service]['complete'] = false;
								}
							}
						}
						foreach( $tmpAnalysis as $service => $templateData ) {
							if( $templateData['complete'] === true ) {
								if( !isset( $templateData['archive_url'] ) ) {
									$originalURL = htmlspecialchars_decode( $templateData['url'] );
									switch( $service ) {
										case "@wayback":
											$archiveURL =
												"https://web.archive.org/web/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@europarchive":
											$archiveURL =
												"http://collection.europarchive.org/nli/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@archiveis":
											$archiveURL = "https://archive.is/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@memento":
											$archiveURL =
												"https://timetravel.mementoweb.org/memento/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@webcite":
											$archiveURL =
												"https://www.webcitation.org/{$webciteTimestamp}?url={$originalURL}";
											break;
										case "@archiveit":
											$archiveURL =
												"https://wayback.archive-it.org/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@arquivo":
											$archiveURL =
												"http://arquivo.pt/wayback/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@loc":
											$archiveURL =
												"http://webarchive.loc.gov/all/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@warbharvest":
											$archiveURL =
												"https://www.webharvest.gov/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@bibalex":
											$archiveURL =
												"http://web.archive.bibalex.org/web/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@collectionscanada":
											$archiveURL =
												"https://www.collectionscanada.gc.ca/webarchives/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@veebiarhiiv":
											$archiveURL =
												"http://veebiarhiiv.digar.ee/a/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@vefsafn":
											$archiveURL =
												"http://wayback.vefsafn.is/wayback/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@proni":
											$archiveURL =
												"http://webarchive.proni.gov.uk/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@spletni":
											$archiveURL =
												"http://nukrobi2.nuk.uni-lj.si:8080/wayback/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@stanford":
											$archiveURL =
												"https://swap.stanford.edu/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@nationalarchives":
											$archiveURL =
												"http://webarchive.nationalarchives.gov.uk/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@parliamentuk":
											$archiveURL =
												"http://webarchive.parliament.uk/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@was":
											$archiveURL =
												"http://eresources.nlb.gov.sg/webarchives/wayback/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@permacc":
											$archiveURL =
												"https://perma-archives.org/warc/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@ukwebarchive":
											$archiveURL =
												"https://www.webarchive.org.uk/wayback/archive/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@wikiwix":
											$archiveURL =
												"http://archive.wikiwix.com/cache/{$archiveURLTimestamp}/{$originalURL}";
											break;
										case "@catalonianarchive":
											$archiveURL =
												"http://padi.cat:8080/wayback/{$archiveURLTimestamp}/{$originalURL}";
											break;
										default:
											$archiveURL = false;
											break;
									}
								} else {
									$archiveURL = htmlspecialchars_decode( $templateData['archive_url'] );
								}
								break;
							}
						}

						$tmp = [];
						if( isset( $archiveURL ) ) {
							$validArchive = API::isArchive( $archiveURL, $tmp );

							//If the original URL isn't present, then we are dealing with a stray archive template.
							if( !isset( $returnArray['url'] ) ) {
								if( $validArchive === true && $archiveData['templatebehavior'] == "swallow" ) {
									$returnArray['archive_type'] = "template-swallow";
									$returnArray['link_type'] = "stray";
									$returnArray['is_archive'] = true;
									if( isset( $archiveData['archivetemplatedefinitions']['services'][$service]['linkstring'] ) ) {
										foreach(
											$archiveData['archivetemplatedefinitions']['data'][$archiveData['archivetemplatedefinitions']['services'][$service]['linkstring'][0]]['mapto']
											as $paramIndex
										) {
											if( isset( $returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]] ) ) {
												$returnArray['archive_type'] = "template-swallow";
												$returnArray2 =
													$this->getLinkDetails( $returnArray['archive_template']['parameters'][$archiveData['archivetemplatedefinitions']['params'][$paramIndex]],
													                       ""
													);

												unset( $returnArray2['tagged_dead'], $returnArray2['permanent_dead'], $returnArray2['remainder'] );

												$returnArray = array_replace( $returnArray, $returnArray2 );
												unset( $returnArray2 );
												break;
											} else {
												$returnArray['archive_type'] = "invalid";
											}
										}
									}
								} else {
									$returnArray['archive_type'] = "invalid";
									$returnArray['link_type'] = "stray";
									$returnArray['is_archive'] = true;
								}
							}

							$returnArray = array_replace( $returnArray, $tmp );
						}

						unset( $tmp );

						if( isset( $originalURL ) && API::isArchive( $originalURL, $junk ) &&
						    $junk['archive_host'] == $service ) {
							//We detected an improper use of the template.  Let's fix it.
							$returnArray['archive_type'] = "invalid";
							$returnArray = array_replace( $returnArray, $junk );
						} elseif( !isset( $archiveURL ) || $archiveURL === false ) {
							//Whoops, this template isn't filled out correctly.  Let's fix it.
							$returnArray['archive_url'] = "x";
							$returnArray['archive_time'] = "x";
							$returnArray['archive_type'] = "invalid";
						} elseif( $validArchive === false ) {
							//Whoops, this template is pointing to an invalid archive.  Let's make it valid.
							$returnArray['archive_type'] = "invalid";
						}

						//Check if the archive template is deprecated.
						if( isset( $this->commObject->config['deprecated_archives'] ) &&
						    in_array( $archiveName2, $this->commObject->config['deprecated_archives'] ) ) {
							$returnArray['archive_type'] = "invalid";
						}
					}
				}
			}

			//If we have multiple archives, we can't handle these correctly, so remove any force markers that may force the editing of the citations.
			if( $returnArray['link_type'] == "template" && $returnArray['has_archive'] === true &&
			    $returnArray['archive_type'] == "template"
			) {
				unset( $returnArray['convert_archive_url'] );
				unset( $returnArray['force_when_dead'] );
				unset( $returnArray['force'] );
				unset( $returnArray['force_when_alive'] );
			}
		}

		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['deadlink_tags'] ), $remainder, $params2
		) ) {
			$returnArray['tagged_dead'] = true;
			$returnArray['tag_type'] = "template";
			if( isset( $params2[2] ) ) $returnArray['tag_template']['parameters'] =
				$this->getTemplateParameters( $params2[2] );
			else $returnArray['tag_template']['parameters'] = [];

			if( !empty( $this->commObject->config['deadlink_tags_data'] ) ) {
				$templateData = $this->commObject->config['deadlink_tags_data'];
				//Flag those that can't be fixed.
				if( isset( $templateData['services']['@default']['permadead'] ) ) {
					foreach( $templateData['services']['@default']['permadead'] as $valueData ) {
						foreach( $templateData['data'][$valueData['index']]['mapto'] as $mapAddress ) {
							if( isset( $returnArray['tag_template']['parameters'][$templateData['params'][$mapAddress]] ) &&
							    ( !isset( $returnArray['tag_template']['paramaters']['bot'] ) ||
							      $returnArray['tag_template']['paramaters']['bot'] != USERNAME ) ) {
								$returnArray['permanent_dead'] = true;
							}
						}
					}
				}
			}

			if( $this->commObject->config['templatebehavior'] == "swallow" ) {
				$returnArray['tag_type'] = "template-swallow";
				if( isset( $templateData['services']['@default']['linkstring'] ) ) {
					foreach(
						$templateData['data'][$templateData['services']['@default']['linkstring'][0]]['mapto']
						as $paramIndex
					) {
						if( isset( $returnArray['tag_template']['parameters'][$templateData['params'][$paramIndex]] ) ) {
							$returnArray['tag_type'] = "template-swallow";
							$returnArray2 =
								$this->getLinkDetails( $returnArray['tag_template']['parameters'][$templateData['params'][$paramIndex]],
								                       ""
								);

							unset( $returnArray2['tagged_dead'], $returnArray2['permanent_dead'], $returnArray2['remainder'] );

							$returnArray = array_replace( $returnArray, $returnArray2 );
							unset( $returnArray2 );
							break;
						} else {
							$returnArray['tag_type'] = "invalid";
						}
					}
				}
			}

			$returnArray['tag_template']['name'] = str_replace( "{{", "", $params2[1] );
			$returnArray['tag_template']['string'] = $params2[0];
		}
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
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return int|false A unix timestamp or false on failure.
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
	 * Determines if 2 separate but close together links have a connection to each other.
	 * If so, the link contained in $currentLink will be merged to the previous one.
	 *
	 * @param array $lastLink Index information of last link looked at
	 * @param array $currentLink index of the current link looked at
	 * @param array $returnArray The array of links to look at and modify
	 *
	 * @return bool True if the 2 links are related.
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 */
	public function isConnected( $lastLink, $currentLink, &$returnArray ) {
		//If one is in a reference and the other is not, there can't be a connection.
		if( ( !is_null( $lastLink['id'] ) xor !is_null( $currentLink['id'] ) ) === true ) return false;
		//If the reference IDs are different, also no connection.
		if( ( !is_null( $lastLink['id'] ) && !is_null( $currentLink['id'] ) ) &&
		    $lastLink['tid'] !== $currentLink['tid']
		) {
			return false;
		}
		//If this is the first link being analyzed, wait for it to be the second run.
		if( is_null( $lastLink['tid'] ) ) return false;
		//Recall the previous link that was analyzed.
		if( !is_null( $lastLink['id'] ) ) {
			$link = $returnArray[$lastLink['tid']]['reference'][$lastLink['id']];
		} else {
			$link = $returnArray[$lastLink['tid']][$returnArray[$lastLink['tid']]['link_type']];
		}
		//Recall the current link being analyzed
		if( !is_null( $currentLink['id'] ) ) {
			$temp = $returnArray[$currentLink['tid']]['reference'][$currentLink['id']];
		} else {
			$temp = $returnArray[$currentLink['tid']][$returnArray[$currentLink['tid']]['link_type']];
		}

		//If the original URLs of both links match, and the archive is located in the current link, then merge into previous link
		if( $this->deadCheck->cleanURL( $link['url'] ) ==
		    $this->deadCheck->cleanURL( $temp['url'] ) && $temp['is_archive'] === true
		) {
			//An archive template initially detected on it's own, is flagged as a stray.  Attached to the original URL, it's flagged as a template.
			//A stray is usually in the remainder only.
			//Define the archive_string to help the string generator find the original archive.
			if( $temp['link_type'] != "stray" ) {
				$link['archive_string'] = $temp['link_string'];
			} else $link['archive_string'] = $temp['remainder'];
			//Expand original string and remainder indexes of previous link to contain the body of the current link.
			if( ( $tstart = strpos( $this->commObject->content, $link['archive_string'] ) ) !== false &&
			    ( $lstart = strpos( $this->commObject->content, $link['link_string'] ) ) !== false
			) {
				if( $tstart - strlen( $link['link_string'] ) - $lstart > 200 ) return false;
				$link['string'] = substr( $this->commObject->content, $lstart,
				                          $tstart - $lstart + strlen( $temp['remainder'] . $temp['link_string'] )
				);
				$link['remainder'] = str_replace( $link['link_string'], "", $link['string'] );
			}

			//Merge the archive information.
			$link['has_archive'] = true;
			//Transfer the archive type.  If it was a stray, redefine it as a template.
			if( $temp['link_type'] != "stray" ) {
				$link['archive_type'] = $temp['archive_type'];
			} else $link['archive_type'] = "template";
			//Transfer template information from current link to previous link.
			if( $link['archive_type'] == "template" ) {
				$link['archive_template'] = $temp['archive_template'];
				$link['tagged_dead'] = true;
				$link['tag_type'] = "implied";
			}
			$link['archive_url'] = $temp['archive_url'];
			$link['archive_time'] = $temp['archive_time'];
			if( !isset( $temp['archive_host'] ) ) $link['archive_host'] = $temp['archive_host'];
			//If the previous link is a citation template, but the archive isn't, then flag as invalid, for later merging.
			if( $link['link_type'] == "template" && $link['archive_type'] != "parameter" ) {
				$link['archive_type'] =
					"invalid";
			}

			//Transfer the remaining tags.
			if( $temp['tagged_paywall'] === true ) {
				$link['tagged_paywall'] = true;
			}
			if( $temp['is_paywall'] === true ) {
				$link['is_paywall'] = true;
			}
			if( $temp['permanent_dead'] === true ) {
				$link['permanent_dead'] = true;
			}
			if( $temp['tagged_dead'] === true ) {
				$link['tag_type'] = $temp['tag_type'];
				if( $link['tag_type'] == "template" ) {
					$link['tag_template'] = $temp['tag_template'];
				}
			}
			//Save previous link back into the passed array.
			if( !is_null( $lastLink['id'] ) ) {
				$returnArray[$lastLink['tid']]['reference'][$lastLink['id']] = $link;
			} else {
				$returnArray[$lastLink['tid']][$returnArray[$lastLink['tid']]['link_type']] = $link;
			}
			//Unset the current link.  It's been merged into the previous link.
			if( !is_null( $currentLink['id'] ) ) {
				unset( $returnArray[$currentLink['tid']]['reference'][$currentLink['id']] );
			} else {
				unset( $returnArray[$currentLink['tid']] );
			}

			return true;
		} //Else if the original URLs in both links match and the archive is in the previous link, then merge into previous link
		elseif( $this->deadCheck->cleanURL( $link['url'] ) ==
		        $this->deadCheck->cleanURL( $temp['url'] ) && $link['is_archive'] === true
		) {
			//Raise the reversed flag for the string generator.  Archive URLs are usually in the remainder.
			$link['reversed'] = true;
			//Define the archive_string to help the string generator find the original archive.
			if( $link['link_type'] != "stray" ) {
				$link['archive_string'] = $link['link_string'];
			} else $link['archive_string'] = $link['remainder'];
			//Expand original string and remainder indexes of previous link to contain the body of the current link.
			if( ( $tstart = strpos( $this->commObject->content, $temp['string'] ) ) !== false &&
			    ( $lstart = strpos( $this->commObject->content, $link['archive_string'] ) ) !== false
			) {
				if( $tstart - $lstart - strlen( $link['archive_string'] ) > 200 ) return false;
				$link['string'] =
					substr( $this->commObject->content, $lstart, $tstart - $lstart + strlen( $temp['string'] ) );
				$link['link_string'] = $link['archive_string'];
				$link['remainder'] = str_replace( $link['archive_string'], "", $link['string'] );
			}
			//We now know that the previous link is only an attachment to the original URL.
			$link['is_archive'] = false;

			//If the previous link was thought to be a stray archive template, redefine it to the type "template"
			if( $link['link_type'] == "stray" ) $link['archive_type'] = "template";

			//Transfer the link type to the previous link
			$link['link_type'] = $temp['link_type'];
			//If it's a cite template, copy the template data over, and check for an invalid combination of archive and link usage.
			if( $link['link_type'] == "template" ) {
				if( $link['archive_type'] != "parameter" ) $link['archive_type'] = "invalid";
				$link['link_template'] = $temp['link_template'];
			}

			//Transfer access time
			$link['access_time'] = $temp['access_time'];

			//Transfer the miscellaneous tags
			if( $temp['tagged_paywall'] === true ) {
				$link['tagged_paywall'] = true;
			}
			if( $temp['is_paywall'] === true ) {
				$link['is_paywall'] = true;
			}
			if( $temp['permanent_dead'] === true ) {
				$link['permanent_dead'] = true;
			}
			if( $temp['tagged_dead'] === true ) {
				$link['tag_type'] = $temp['tag_type'];
				if( $link['tag_type'] == "template" ) {
					$link['tag_template'] = $temp['tag_template'];
				}
			}
			//Save new previous link data back into it's original location
			if( !is_null( $lastLink['id'] ) ) {
				$returnArray[$lastLink['tid']]['reference'][$lastLink['id']] = $link;
			} else {
				$returnArray[$lastLink['tid']][$returnArray[$lastLink['tid']]['link_type']] = $link;
			}
			//Delete the index of the current link.
			if( !is_null( $currentLink['id'] ) ) {
				unset( $returnArray[$currentLink['tid']]['reference'][$currentLink['id']] );
			} else {
				unset( $returnArray[$currentLink['tid']] );
			}

			return true;
		}

		//No connection
		return false;
	}

	/**
	 * Look for stored access times in the DB, or update the DB with a new access time
	 * Adds access time to the link details.
	 *
	 * @param array $links A collection of links with respective details
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array Returns the same array with the access_time parameters updated
	 */
	public function updateAccessTimes( $links ) {
		$toGet = [];
		foreach( $links as $tid => $link ) {
			if( !isset( $this->commObject->db->dbValues[$tid]['createglobal'] ) && $link['access_time'] == "x" ) {
				if( strtotime( $this->commObject->db->dbValues[$tid]['access_time'] ) > time() ||
				    strtotime( $this->commObject->db->dbValues[$tid]['access_time'] ) < 978307200 ) {
					$toGet[$tid] = $link['url'];
				} else {
					$links[$tid]['access_time'] = $this->commObject->db->dbValues[$tid]['access_time'];
				}
			} elseif( $link['access_time'] == "x" ) {
				$toGet[$tid] = $link['url'];
			} else {
				if( $link['access_time'] > time() || $link['access_time'] < 978307200 ) {
					$toGet[$tid] = $link['url'];
				} else {
					$this->commObject->db->dbValues[$tid]['access_time'] = $link['access_time'];
				}
			}
		}
		if( !empty( $toGet ) ) $toGet = $this->commObject->getTimesAdded( $toGet );
		foreach( $toGet as $tid => $time ) {
			$this->commObject->db->dbValues[$tid]['access_time'] = $links[$tid]['access_time'] = $time;
		}

		return $links;
	}

	/**
	 * Update the link details array with values stored in the DB, and vice versa
	 * Updates the dead status of the given link
	 *
	 * @param array $link Array of link with details
	 * @param int $tid Array key to preserve index keys
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array Returns the same array with updated values, if any
	 */
	public function updateLinkInfo( $links ) {
		$toCheck = [];
		foreach( $links as $tid => $link ) {
			if( $this->commObject->config['verify_dead'] == 1 &&
			    $this->commObject->db->dbValues[$tid]['live_state'] != 0 &&
			    $this->commObject->db->dbValues[$tid]['live_state'] < 5 &&
			    ( $this->commObject->db->dbValues[$tid]['paywall_status'] == 0 ||
			      $this->commObject->db->dbValues[$tid]['paywall_status'] == 1 ) &&
			    ( time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 259200 ) &&
			    ( $this->commObject->db->dbValues[$tid]['live_state'] != 3 ||
			      ( time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 604800 ) )
			) {
				$toCheck[$tid] = $link['url'];
			}
		}
		$results = $this->deadCheck->areLinksDead( $toCheck );
		$errors = $this->deadCheck->getErrors();

		$whitelisted = [];
		if( USEADDITIONALSERVERS === true ) {
			$toValidate = [];
			foreach( $toCheck as $tid => $url ) {
				if( $results[$url] === true && $this->commObject->db->dbValues[$tid]['live_state'] == 1 ) {
					$toValidate[] = $url;
				}
			}
			if( !empty( $toValidate ) ) foreach( explode( "\n", CIDSERVERS ) as $server ) {
				if( empty( $toValidate ) ) break;
				$serverResults = API::runCIDServer( $server, $toValidate );
				$toValidate = array_flip( $toValidate );
				if( !is_null( $serverResults ) ) foreach( $serverResults['results'] as $surl => $sResult ) {
					if( $surl == "errors" ) continue;
					if( $sResult === false ) {
						$whitelisted[] = $surl;
						unset( $toValidate[$surl] );
					} else {
						$errors[$surl] = $serverResults['results']['errors'][$surl];
					}
				} elseif( is_null( $serverResults ) ) {
					echo "ERROR: $server did not respond!\n";
				}
				$toValidate = array_flip( $toValidate );
			}
		}
		foreach( $links as $tid => $link ) {
			if( array_search( $link['url'], $whitelisted ) !== false ) {
				$this->commObject->db->dbValues[$tid]['paywall_status'] = 3;
				$link['is_dead'] = false;
				$links[$tid] = $link;
				continue;
			}

			$link['is_dead'] = null;
			if( $this->commObject->config['verify_dead'] == 1 ) {
				if( $this->commObject->db->dbValues[$tid]['live_state'] != 0 &&
				    $this->commObject->db->dbValues[$tid]['live_state'] < 5 &&
				    ( $this->commObject->db->dbValues[$tid]['paywall_status'] == 0 ||
				      $this->commObject->db->dbValues[$tid]['paywall_status'] == 1 ) &&
				    ( time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 259200 ) &&
				    ( $this->commObject->db->dbValues[$tid]['live_state'] != 3 ||
				      ( time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 604800 ) )
				) {
					$link['is_dead'] = $results[$link['url']];
					$this->commObject->db->dbValues[$tid]['last_deadCheck'] = time();
					if( $link['tagged_dead'] === false && $link['is_dead'] === true ) {
						if( $this->commObject->db->dbValues[$tid]['live_state'] ==
						    4 ) $this->commObject->db->dbValues[$tid]['live_state'] = 2;
						else $this->commObject->db->dbValues[$tid]['live_state']--;
					} elseif( $link['tagged_dead'] === false && $link['is_dead'] === false &&
					          $this->commObject->db->dbValues[$tid]['live_state'] != 3
					) {
						$this->commObject->db->dbValues[$tid]['live_state'] = 3;
					} elseif( $link['tagged_dead'] === true && $link['is_dead'] === true ) {
						$this->commObject->db->dbValues[$tid]['live_state'] = 0;
					} else {
						$this->commObject->db->dbValues[$tid]['live_state'] = 3;
					}

					if( $link['is_dead'] === true && $this->commObject->db->dbValues[$tid]['paywall_status'] == 1 &&
					    preg_match( '/4\d\d/i', $errors[$link['url']], $code ) &&
					    array_search( $code[0], [ 401, 402, 403, 412, 428, 440, 449 ] ) ) {
						$this->commObject->db->dbValues[$tid]['live_state'] = 5;
					}
				}
				if( $this->commObject->db->dbValues[$tid]['live_state'] != 0 ) $link['is_dead'] = false;
				if( !isset( $this->commObject->db->dbValues[$tid]['live_state'] ) ||
				    $this->commObject->db->dbValues[$tid]['live_state'] == 4 ||
				    $this->commObject->db->dbValues[$tid]['live_state'] == 5
				) {
					$link['is_dead'] = null;
				}
				if( $this->commObject->db->dbValues[$tid]['live_state'] == 7 ) {
					$link['is_dead'] = false;
				}
				if( $this->commObject->db->dbValues[$tid]['live_state'] == 0 ||
				    $this->commObject->db->dbValues[$tid]['live_state'] == 6
				) {
					$link['is_dead'] = true;
				}

				if( $this->commObject->db->dbValues[$tid]['paywall_status'] == 3 ) {
					$link['is_dead'] = false;
				}
				if( ( $this->commObject->db->dbValues[$tid]['paywall_status'] == 2 ||
				      ( isset( $link['invalid_archive'] ) && !isset( $link['ignore_iarchive_flag'] ) ) ) ||
				    ( $this->commObject->config['tag_override'] == 1 && $link['tagged_dead'] === true )
				) {
					$link['is_dead'] = true;
				}
			}
			$links[$tid] = $link;
		}

		return $links;
	}

	/**
	 * Fetches all references only
	 *
	 * @param string Page text to analyze
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array Details about every reference found
	 */
	public function getReferences( $text = false ) {
		return $this->getExternallinks( true, $text );
	}

	/**
	 * Rescue a link
	 *
	 * @param array $link Link being analyzed
	 * @param array $modifiedLinks Links that were modified
	 * @param array $temp Cached result value from archive retrieval function
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return void
	 */
	protected function rescueLink( &$link, &$modifiedLinks, &$temp, $tid, $id ) {
		//The initial assumption is that we are adding an archive to a URL.
		$modifiedLinks["$tid:$id"]['type'] = "addarchive";
		$modifiedLinks["$tid:$id"]['link'] = $link['url'];
		$modifiedLinks["$tid:$id"]['newarchive'] = $temp['archive_url'];

		//The newdata index is all the data being injected into the link array.  This allows for the preservation of the old data for easier manipulation and maintenance.
		$link['newdata']['has_archive'] = true;
		$link['newdata']['archive_url'] = $temp['archive_url'];
		if( !empty( $link['archive_fragment'] ) ) $link['newdata']['archive_url'] .= "#" . $link['archive_fragment'];
		elseif( !empty( $link['fragment'] ) ) $link['newdata']['archive_url'] .= "#" . $link['fragment'];
		$link['newdata']['archive_time'] = $temp['archive_time'];

		//Set the conversion to cite templates bit
		$convertToCite = $this->commObject->config['convert_to_cites'] == 1 && ( isset( $link['converttocite'] ) || $link['link_type'] == "stray" );

		//Set the cite template bit
		$useCiteGenerator = ( ( $link['link_type'] == "link" || $link['link_type'] == "stray" ) && $convertToCite ) || $link['link_type'] == "template";

		//Set the archive template bit
		$useArchiveGenerator = $link['is_archive'] === false && $link['link_type'] != "stray";

		//Set the plain link bit
		$usePlainLink = $link['link_type'] == "link";

		if( !$useCiteGenerator || !$this->generateNewCitationTemplate( $link ) ) {
			if( !$useArchiveGenerator || !$this->generateNewArchiveTemplate( $link, $temp ) ) {
				if( !$usePlainLink ) {
					unset( $link['newdata']['archive_url'], $link['newdata']['archive_time'], $link['newdata']['has_archive'] );
					unset( $modifiedLinks["$tid:$id"], $link['newdata'] );

					return false;
				} else {
					$link['newdata']['archive_type'] = "link";
					$link['newdata']['is_archive'] = true;
					$link['newdata']['tagged_dead'] = false;
				}
			} else {
				if( empty( $link['newdata']['archive_type'] ) ) $link['newdata']['archive_type'] = "template";
				$link['newdata']['tagged_dead'] = false;
				$link['newdata']['is_archive'] = false;
			}
		} else {
			//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
			if( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) {
				if( !empty( $link['newdata']['link_template']['template_map'] ) ) $map =
					$link['newdata']['link_template']['template_map'];
				elseif( !empty( $link['link_template']['template_map'] ) ) $map =
					$link['link_template']['template_map'];


				if( !empty( $map['services']['@default']['url'] ) )
					foreach( $map['services']['@default']['url'] as $dataIndex ) {
						foreach( $map['data'][$dataIndex]['mapto'] as $paramIndex ) {
							if( isset( $link['link_template']['parameters'][$map['params'][$paramIndex]] ) ||
							    isset( $link['newdata']['link_template']['parameters'][$map['params'][$paramIndex]] ) ) break 2;
						}
					}

				if( !isset( $link['template_url'] ) )
					$link['newdata']['link_template']['parameters'][$map['params'][$paramIndex]] = $link['url'];
				else $link['newdata']['link_template']['parameters'][$map['params'][$paramIndex]] =
					$link['template_url'];

				$modifiedLinks["$tid:$id"]['type'] = "fix";
			}

			//Force change the link type to a template.  This part is not within the scope of the array merger, as it's too high level.
			if( $convertToCite ) $link['link_type'] = "template";
		}

		//If any invalid flags were raised, then we fixed a source rather than added an archive to it.
		if( isset( $link['convert_archive_url'] ) ||
		    ( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) ||
		    ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" )
		) {
			$modifiedLinks["$tid:$id"]['type'] = "fix";
			if( isset( $link['convert_archive_url'] ) ) $link['newdata']['converted_archive'] = true;
		}
		//If we ended up changing the archive URL despite invalid flags, we should mention that change instead.
		if( $link['has_archive'] === true && $link['archive_url'] != $temp['archive_url'] &&
		    !isset( $link['convert_archive_url'] )
		) {
			$modifiedLinks["$tid:$id"]['type'] = "modifyarchive";
			$modifiedLinks["$tid:$id"]['oldarchive'] = $link['archive_url'];
		}
		unset( $temp );

		return true;
	}

	/**
	 * Generates an appropriate citation template without altering existing parameters.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 *
	 * @param $link Current link being modified
	 * @param $temp Current temp result from fetchResponse
	 *
	 * @return bool If successful or not
	 */
	protected function generateNewCitationTemplate( &$link ) {
		if( !isset( $link['link_template']['template_map'] ) ) {
			$tmp = unserialize( API::readFile( $this->commObject->config['template_definitions'][WIKIPEDIA] ) );
			if( empty( $tmp['default-template'] ) ) return false;
			$link['newdata']['link_template']['format'] = "{key}={value} ";
			$link['newdata']['link_template']['name'] = $tmp['default-template'];

			$link['newdata']['link_template']['template_map'] =
				self::getCiteMap( $link['newdata']['link_template']['name'],
				                  $this->commObject->config['template_definitions']
				);
			if( empty( $link['newdata']['link_template']['template_map'] ) ) return false;
			else $map = $link['newdata']['link_template']['template_map'];

		} else $map = $link['link_template']['template_map'];

		//If there was no link template array, then create an empty one.
		if( !isset( $link['link_template'] ) ) $link['link_template'] = [];

		$link['newdata']['archive_type'] = "parameter";
		//We need to flag it as dead so the string generator knows how to behave, when assigning the deadurl parameter.
		if( $link['tagged_dead'] === true || $link['is_dead'] === true ) $link['newdata']['tagged_dead'] = true;
		else $link['newdata']['tagged_dead'] = false;
		$link['newdata']['tag_type'] = "parameter";

		$magicwords = [];
		if( isset( $link['url'] ) ) $magicwords['url'] = $link['url'];
		if( isset( $link['newdata']['archive_time'] ) ) $magicwords['archivetimestamp'] =
			$link['newdata']['archive_time'];
		$magicwords['accesstimestamp'] = $link['access_time'];
		if( isset( $link['newdata']['archive_url'] ) ) $magicwords['archiveurl'] = $link['newdata']['archive_url'];
		$magicwords['timestampauto'] = $this->retrieveDateFormat( $link['string'] );
		$magicwords['linkstring'] = $link['link_string'];
		$magicwords['remainder'] = $link['remainder'];
		$magicwords['string'] = $link['string'];
		if( !empty( $link['title'] ) ) $magicwords['title'] = $link['title'];
		elseif( !empty( $this->commObject->config['template_definitions'][WIKIPEDIA]['default-title'] ) )
			$magicwords['title'] = $this->commObject->config['template_definitions'][WIKIPEDIA]['default-title'];
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
	 * Generates an appropriate archive template if it can.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 *
	 * @param $link Current link being modified
	 * @param $temp Current temp result from fetchResponse
	 *
	 * @return bool If successful or not
	 */
	protected function generateNewArchiveTemplate( &$link, &$temp ) {
		//We need the archive host, to pick the right template.
		if( !isset( $link['newdata']['archive_host'] ) ) $link['newdata']['archive_host'] =
			$this->getArchiveHost( $temp['archive_url'] );

		//If the archive template is being used improperly, delete the parameters, and start fresh.
		if( $link['has_archive'] === true &&
		    $link['archive_type'] == "invalid"
		) unset( $link['archive_template']['parameters'] );

		$archives = [];

		foreach( $this->commObject->config['using_archives'] as $archive ) {
			if( @in_array( $archive, $this->commObject->config['deprecated_archives'] ) ) continue;

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

		if( isset( $this->commObject->config["darchive_$useArchive"] ) ) {
			$link['newdata']['archive_template']['name'] =
				trim( DB::getConfiguration( WIKIPEDIA, "wikiconfig", "darchive_$useArchive" )[0], "{}" );

			$magicwords = [];
			if( isset( $link['url'] ) ) $magicwords['url'] = $link['url'];
			if( isset( $link['newdata']['archive_time'] ) ) $magicwords['archivetimestamp'] =
				$link['newdata']['archive_time'];
			if( isset( $link['newdata']['archive_url'] ) ) $magicwords['archiveurl'] = $link['newdata']['archive_url'];
			$magicwords['timestampauto'] = $this->retrieveDateFormat( $link['string'] );
			$magicwords['linkstring'] = $link['link_string'];
			$magicwords['remainder'] = $link['remainder'];
			$magicwords['string'] = $link['string'];

			$text = $link['link_string'];
			$text = str_replace( $link['original_url'], "", $text );
			$text = trim( $text, "[] " );
			$text = str_replace( "|", "{{!}}", $text );
			if( empty( $text ) ) $magicwords['title'] = "&mdash;";
			else $magicwords['title'] = $text;

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

			if( !isset( $this->commObject->config['all_archives'][$useArchive]['archivetemplatedefinitions']['services']["@{$link['newdata']['archive_host']}"] ) )
				$useService = "@default";
			else $useService = "@{$link['newdata']['archive_host']}";

			if( $this->commObject->config['all_archives'][$useArchive]['templatebehavior'] == "swallow" )
				$link['newdata']['archive_type'] = "template-swallow";
			else $link['newdata']['archive_type'] = "template";

			foreach(
				$this->commObject->config['all_archives'][$useArchive]['archivetemplatedefinitions']['services'][$useService]
				as $category => $categoryData
			) {
				if( $link['newdata']['archive_type'] == "template" ) {
					if( $category == "title" ) continue;
				}
				if( is_array( $categoryData[0] ) ) $dataIndex = $categoryData[0]['index'];
				else $dataIndex = $categoryData[0];

				$paramIndex =
					$this->commObject->config['all_archives'][$useArchive]['archivetemplatedefinitions']['data'][$dataIndex]['mapto'][0];

				$link['newdata']['archive_template']['parameters'][$this->commObject->config['all_archives'][$useArchive]['archivetemplatedefinitions']['params'][$paramIndex]] =
					$this->commObject->config['all_archives'][$useArchive]['archivetemplatedefinitions']['data'][$dataIndex]['valueString'];
			}

			if( isset( $link['newdata']['archive_template']['parameters'] ) )
				foreach( $link['newdata']['archive_template']['parameters'] as $param => $value ) {
					$link['newdata']['archive_template']['parameters'][$param] =
						$this->commObject->getConfigText( $value, $magicwords );
				}
		} else return false;

		return true;
	}

	protected function getArchiveHost( $url, &$data = [] ) {
		$value = API::isArchive( $url, $data );
		if( $value === false ) {
			return "unknown";
		} else return $data['archive_host'];
	}

	/**
	 * Modify link that can't be rescued
	 *
	 * @param array $link Link being analyzed
	 * @param array $modifiedLinks Links modified array
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return void
	 */
	protected function noRescueLink( &$link, &$modifiedLinks, $tid, $id ) {
		$modifiedLinks["$tid:$id"]['type'] = "tagged";
		$modifiedLinks["$tid:$id"]['link'] = $link['url'];
		if( $link['link_type'] == "template" &&
		    ( $this->commObject->config['tag_cites'] == 1 || $link['has_archive'] === true ) ) {

			$magicwords['is_dead'] = "yes";
			$map = $link['link_template']['template_map'];

			if( isset( $map['services']['@default']['deadvalues'] ) ) {
				$link['newdata']['tag_type'] = "parameter";
				$parameter = null;

				$dataIndex = $map['services']['@default']['deadvalues'][0];

				foreach( $map['data'][$dataIndex['index']]['mapto'] as $paramIndex ) {
					if( is_null( $parameter ) ) $parameter = $map['params'][$paramIndex];

					if( isset( $link['link_template']['parameters'][$map['params'][$paramIndex]] ) ) {
						$parameter = $map['params'][$paramIndex];
						break;
					}
				}

				$link['newdata']['link_template']['parameters'][$parameter] =
					$map['data'][$dataIndex['index']]['valueString'];

				if( !empty( $map['services']['@default']['others'] ) ) foreach(
					$map['services']['@default']['others'] as $dataIndex
				) {
					$parameter = null;
					foreach( $map['data'][$dataIndex]['mapto'] as $paramIndex ) {
						if( is_null( $parameter ) ) $parameter = $map['params'][$paramIndex];

						if( isset( $link['link_template']['parameters'][$map['params'][$paramIndex]] ) ) {
							$parameter = $map['params'][$paramIndex];
							break;
						}
					}

					$link['newdata']['link_template']['parameters'][$parameter] =
						$map['data'][$dataIndex]['valueString'];
				}

				if( isset( $link['newdata']['link_template']['parameters'] ) )
					foreach( $link['newdata']['link_template']['parameters'] as $param => $value ) {
						$link['newdata']['link_template']['parameters'][$param] =
							$this->commObject->getConfigText( $value, $magicwords );
					}
			} else {
				return false;
			}
		} else {
			$deadlinkTags = DB::getConfiguration( WIKIPEDIA, "wikiconfig", "deadlink_tags" );

			if( empty( $deadlinkTags ) ) return false;

			if( $this->commObject->config['templatebehavior'] == "append" ) $link['newdata']['tag_type'] = "template";
			elseif( $this->commObject->config['templatebehavior'] == "swallow" ) $link['newdata']['tag_type'] =
				"template-swallow";

			$link['newdata']['tag_template']['name'] = trim( $deadlinkTags[0], "{}" );

			if( !empty( $this->commObject->config['deadlink_tags_data'] ) ) {
				foreach(
					$this->commObject->config['deadlink_tags_data']['services']['@default'] as $category => $categorySet
				) {
					foreach( $categorySet as $dataIndex ) {
						if( $category == "permadead" ) {
							$dataIndex = $dataIndex['index'];
						}
						if( is_array( $dataIndex ) ) continue;

						$paramIndex =
							$this->commObject->config['deadlink_tags_data']['data'][$dataIndex]['mapto'][0];

						$link['newdata']['tag_template']['parameters'][$this->commObject->config['deadlink_tags_data']['params'][$paramIndex]] =
							$this->commObject->config['deadlink_tags_data']['data'][$dataIndex]['valueString'];
					}
				}

				$magicwords = [];
				if( isset( $link['url'] ) ) $magicwords['url'] = $link['url'];
				$magicwords['timestampauto'] = $this->retrieveDateFormat( $link['string'] );
				$magicwords['linkstring'] = $link['link_string'];
				$magicwords['remainder'] = $link['remainder'];
				$magicwords['string'] = $link['string'];
				$magicwords['permadead'] = true;
				$magicwords['url'] = $link['url'];

				$text = $link['link_string'];
				$text = str_replace( $link['original_url'], "", $text );
				$text = trim( $text, "[] " );
				$text = str_replace( "|", "{{!}}", $text );
				if( empty( $text ) ) $magicwords['title'] = "&mdash;";
				else $magicwords['title'] = $text;

				if( isset( $link['newdata']['tag_template']['parameters'] ) )
					foreach( $link['newdata']['tag_template']['parameters'] as $param => $value ) {
						$link['newdata']['tag_template']['parameters'][$param] =
							$this->commObject->getConfigText( $value, $magicwords );
					}
			} else $link['newdata']['tag_template']['parameters'] = [];
		}
	}

	/**
	 * Verify that newdata is actually different from old data
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 *
	 * @param mixed $link
	 *
	 * @return bool Whether the data in the link array contains new data from the old data.
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
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2017, Maximilian Doerr
	 * @return string New source string
	 */
	public function generateString( $link ) {
		$out = "";
		if( $link['link_type'] != "reference" ) {
			if( strpos( $link[$link['link_type']]['link_string'], "\n" ) !== false ) $multiline = true;
			$mArray = Parser::mergeNewData( $link[$link['link_type']] );
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
			//Build the opening reference tag with parameters, when dealing with references.
			$out .= "<ref";
			if( isset( $link['reference']['parameters'] ) ) {
				foreach( $link['reference']['parameters'] as $parameter => $value ) {
					$out .= " $parameter=$value";
				}
				unset( $link['reference']['parameters'] );
			}
			$out .= ">";
			//Store the original link string in sub output buffer.
			$tout = trim( $link['reference']['link_string'] );
			//Process each individual source in the reference
			$offsetAdd = 0 - strpos( $link['reference']['link_string'], $tout );
			//Delete it, to avoid confusion when processing the array.
			unset( $link['reference']['link_string'] );
			foreach( $link['reference'] as $tid => $tlink ) {
				if( strpos( $tlink['link_string'], "\n" ) !== false ) $multiline = true;
				//Create an sub-sub-output buffer.
				$ttout = "";
				//If the ignore tag is set on this specific source, move on to the next.
				if( isset( $tlink['ignore'] ) && $tlink['ignore'] === true ) continue;
				if( !is_int( $tid ) ) continue;
				//Merge the newdata index with the link array.
				$mArray = Parser::mergeNewData( $tlink );
				if( isset( $tlink['redundant_archives'] ) ) $tArray =
					array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['ignore_tags'] );
				else $tArray =
					array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'],
					             $this->commObject->config['ignore_tags']
					);
				$regex = $this->fetchTemplateRegex( $tArray );
				//Clear the existing archive, dead, and ignore tags from the remainder.
				//Why ignore?  It gives a visible indication that there's a bug in IABot.
				$remainder = trim( preg_replace( $regex, "", $mArray['remainder'] ) );
				//If handling a plain link, or a plain archive link...
				if( $mArray['link_type'] == "link" ||
				    ( $mArray['is_archive'] === true && $mArray['archive_type'] == "link" )
				) {
					//Store source link string into sub-sub-output buffer.
					$ttout .= $mArray['link_string'];
					//For other archives that don't have archive templates or there is no suitable template, replace directly.
					if( $tlink['is_archive'] === false && $mArray['is_archive'] === true ) {
						$ttout = str_replace( $mArray['original_url'], $mArray['archive_url'], $ttout );
					} elseif( $tlink['is_archive'] === true && $mArray['is_archive'] === true ) {
						$ttout = str_replace( $mArray['old_archive'], $mArray['archive_url'], $ttout );
					} elseif( $tlink['is_archive'] === true && $mArray['is_archive'] === false ) {
						$ttout = str_replace( $mArray['old_archive'], $mArray['url'], $ttout );
					}
				} //If handling a cite template...
				elseif( $mArray['link_type'] == "template" ) {
					//Build a clean cite template with the set parameters.
					$ttout .= "{{" . $mArray['link_template']['name'];
					if( $mArray['link_template']['format'] == "multiline-pretty" ) $ttout .= "\n";
					else $ttout .= substr( $mArray['link_template']['format'],
					                       strpos( $mArray['link_template']['format'], "{value}" ) + 7
					);
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
				if( $mArray['tagged_dead'] === true ) {
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
						if( $tlink['has_archive'] === true && $tlink['archive_type'] == "link" ) {
							$ttout = str_replace( $mArray['old_archive'], $mArray['archive_url'], $ttout );
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
					if( isset( $mArray['archive_string'] ) && $mArray['archive_type'] != "link" ) {
						$ttout =
							str_replace( $mArray['archive_string'], "", $ttout );
					}
				}
				//Search for source's entire string content, and replace it with the new string from the sub-sub-output buffer, and save it into the sub-output buffer.
				$tout =
					self::str_replace( $tlink['string'], $ttout, $tout, $count, 1, $tlink['offset'] + $offsetAdd );
				$offsetAdd += strlen( $ttout ) - strlen( $tlink['string'] );
			}

			//Attach contents of sub-output buffer, to main output buffer.
			$out .= $tout;
			//Close reference.
			$out .= "</ref>";

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
			//FIXME: Missing tag_type index
			if( !isset( $mArray['tag_type'] ) ) {
				sleep( 1 );
			}
			//FIXME: Missing parameters index
			if( ( $mArray['tag_type'] == "template" || $mArray['tag_type'] == "template-swallow" ) &&
			    !isset( $mArray['tag_template']['parameters'] ) ) {
				sleep( 1 );
			}
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
						str_replace( $mArray['old_archive'], $mArray['archive_url'], $out );
				} else $out = str_replace( $mArray['original_url'], $mArray['archive_url'], $out );
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
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array Merged data
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
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return Replacement string
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
	 * Determine if the bot was likely reverted
	 *
	 * @param array $newlink The new link to look at
	 * @param array $lastRevLinks The collection of link data from the previous revision to compare with.
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array Details about every link on the page
	 * @return bool|int If the edit was likely the bot being reverted, it will return the first bot revid it occured on.
	 */
	public function isEditReversed( $newlink, $lastRevLinkss ) {
		foreach( $lastRevLinkss as $revisionID => $lastRevLinks ) {
			$lastRevLinks = unserialize( API::readFile( $lastRevLinks ) );
			if( $newlink['link_type'] == "reference" ) {
				foreach( $newlink['reference'] as $tid => $link ) {
					if( !is_numeric( $tid ) ) continue;
					if( !isset( $link['newdata'] ) ) continue;

					$breakout = false;
					foreach( $lastRevLinks as $revLink ) {
						if( !is_array( $revLink ) ) continue;
						if( $revLink['link_type'] == "reference" ) {
							foreach( $revLink['reference'] as $ttid => $oldLink ) {
								if( !is_numeric( $ttid ) ) continue;
								if( isset( $oldLink['ignore'] ) ) continue;

								if( $oldLink['url'] == $link['url'] ) {
									$breakout = true;
									break;
								}
							}
						} else {
							if( isset( $revLink[$revLink['link_type']]['ignore'] ) ) continue;
							if( $revLink[$revLink['link_type']]['url'] == $link['url'] ) {
								$oldLink = $revLink[$revLink['link_type']];
								break;
							}
						}
						if( $breakout === true ) break;
					}

					if( is_array( $oldLink ) ) {
						if( API::isReverted( $oldLink, $link ) ) {
							return $revisionID;
						} else continue;
					} else continue;
				}
			} else {
				$link = $newlink[$newlink['link_type']];

				$breakout = false;
				foreach( $lastRevLinks as $revLink ) {
					if( !is_array( $revLink ) ) continue;
					if( $revLink['link_type'] == "reference" ) {
						foreach( $revLink['reference'] as $ttid => $oldLink ) {
							if( !is_numeric( $ttid ) ) continue;
							if( isset( $oldLink['ignore'] ) ) continue;

							if( $oldLink['url'] == $link['url'] ) {
								$breakout = true;
								break;
							}
						}
					} else {
						if( isset( $revLink[$revLink['link_type']]['ignore'] ) ) continue;
						if( $revLink[$revLink['link_type']]['url'] == $link['url'] ) {
							$oldLink = $revLink[$revLink['link_type']];
							break;
						}
					}
					if( $breakout === true ) break;
				}

				if( is_array( $oldLink ) ) {
					if( API::isReverted( $oldLink, $link ) ) {
						return $revisionID;
					} else continue;
				} else continue;
			}
		}

		return false;
	}

	/**
	 * Determine if the given link is likely a false positive
	 *
	 * @param string|int $id array index ID
	 * @param array $link Array of link information with details
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return array Details about every link on the page
	 * @return bool If the link is likely a false positive
	 */
	public function isLikelyFalsePositive( $id, $link, &$makeModification = true ) {
		if( is_null( $makeModification ) ) $makeModification = true;
		if( $this->commObject->db->dbValues[$id]['live_state'] == 0 ) {
			if( $link['has_archive'] === true ) return false;
			if( $link['tagged_dead'] === true ) {
				if( $link['tag_type'] == "parameter" ) {
					$makeModification = false;

					return true;
				}

				return false;
			}

			$sql =
				"SELECT * FROM externallinks_fpreports WHERE `report_status` = 2 AND `report_url_id` = {$this->commObject->db->dbValues[$id]['url_id']};";
			if( $res = $this->dbObject->queryDB( $sql ) ) {
				if( mysqli_num_rows( $res ) > 0 ) {
					mysqli_free_result( $res );

					return false;
				}
			}

			$makeModification = false;

			return true;
		} else {
			if( $link['tagged_dead'] === true ) {
				if( $link['tag_type'] == "parameter" ) $makeModification = false;

				return false;
			}
		}

		return false;
	}

	/**
	 * Return whether or not to skip editing the main article.
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return bool True to skip
	 */
	protected function leaveTalkOnly() {
		return preg_match( $this->fetchTemplateRegex( $this->commObject->config['talk_only_tags'] ),
		                   $this->commObject->content,
		                   $garbage
		);
	}

	/**
	 * Return whether or not to leave a talk page message.
	 *
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return bool
	 */
	protected function leaveTalkMessage() {
		return !preg_match( $this->fetchTemplateRegex( $this->commObject->config['no_talk_tags'] ),
		                    $this->commObject->content,
		                    $garbage
		);
	}

	/**
	 * Destroys the class
	 *
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
	 * @return void
	 */
	public function __destruct() {
		$this->deadCheck = null;
		$this->commObject = null;
	}
}
