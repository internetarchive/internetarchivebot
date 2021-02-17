<?php
/*
	Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	IABot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with IABot.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
*/
/**
 * @file
 * CiteMap object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
 */

/**
 * CiteMap class
 * Allows for parsing and mapping Cite Template parameters
 *
 * @abstract
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2021, Maximilian Doerr, Internet Archive
 */
class CiteMap {

	protected static $globalObject;

	protected static $globalTitle;

	protected static $globalTemplate;

	protected static $lastUpdate;

	protected static $lastSourceUpdate;

	protected static $sources;

	protected static $templateList;

	protected static $wiki;

	protected static $mapObjects;

	protected static $archiveObjects;

	protected static $deadObject;

	protected static $redirectTargetObjects;

	protected static $generatorObject = false;

	protected static $requireUpdate = false;

	protected static $services = [
		'@wayback'           => [
			'archive_url' => [
				'value'    => 'https://web.archive.org/web/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@europarchive'      => [
			'archive_url' => [
				'value'    => 'http://collection.europarchive.org/nli/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@archiveis'         => [
			'archive_url' => [
				'value'    => 'https://archive.is/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@memento'           => [
			'archive_url' => [
				'value'    => 'https://timetravel.mementoweb.org/memento/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@webcite'           => [
			'archive_url' => [
				'value'    => 'https://www.webcitation.org/{microepochbase62}?url={url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@archiveit'         => [
			'archive_url' => [
				'value'    => 'https://wayback.archive-it.org/{archivetimestamp:%Y%m%d%H%M%S}{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@arquivo'           => [
			'archive_url' => [
				'value'    => 'http://arquivo.pt/wayback/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@loc'               => [
			'archive_url' => [
				'value'    => 'http://webarchive.loc.gov/all/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@warbharvest'       => [
			'archive_url' => [
				'value'    => 'https://www.webharvest.gov/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@bibalex'           => [
			'archive_url' => [
				'value'    => 'http://web.archive.bibalex.org/web/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@collectionscanada' => [
			'archive_url' => [
				'value'    => 'https://www.collectionscanada.gc.ca/webarchives/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@veebiarhiiv'       => [
			'archive_url' => [
				'value'    => 'http://veebiarhiiv.digar.ee/a/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@vefsafn'           => [
			'archive_url' => [
				'value'    => 'http://wayback.vefsafn.is/wayback/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@proni'             => [
			'archive_url' => [
				'value'    => 'http://webarchive.proni.gov.uk/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@spletni'           => [
			'archive_url' => [
				'value'    => 'http://nukrobi2.nuk.uni-lj.si:8080/wayback/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@stanford'          => [
			'archive_url' => [
				'value'    => 'https://swap.stanford.edu/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@nationalarchives'  => [
			'archive_url' => [
				'value'    => 'http://webarchive.nationalarchives.gov.uk/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@parliamentuk'      => [
			'archive_url' => [
				'value'    => 'http://webarchive.parliament.uk/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@was'               => [
			'archive_url' => [
				'value'    => 'http://eresources.nlb.gov.sg/webarchives/wayback/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@permacc'           => [
			'archive_url' => [
				'value'    => 'https://perma-archives.org/warc/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@ukwebarchive'      => [
			'archive_url' => [
				'value'    => 'https://www.webarchive.org.uk/wayback/archive/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@wikiwix'           => [
			'archive_url' => [
				'value'    => 'http://archive.wikiwix.com/cache/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		],
		'@catalonianarchive' => [
			'archive_url' => [
				'value'    => 'http://padi.cat:8080/wayback/{archivetimestamp:%Y%m%d%H%M%S}/{url}',
				'requires' => [ 'archive_date', 'url' ]
			]
		]
	];

	protected static $serviceMap = [
		'url'          => '{url}',
		'title'        => '{title}',
		'titlelink'    => '{titlelink}',
		'access_date'  => '{accesstimestamp:{format}}',
		'archive_url'  => '{archiveurl}',
		'archive_date' => '{archivetimestamp:{format}}',
		'deadvalues'   => '{deadvalues:{valueyes}:{valueno}:{valueusurp}:{defaultvalue}}',
		//'paywall' => '{paywall:{valueyes}:{valueno}}',
		'paywall'      => '{paywall:{valuesub}:{valuereg}:{valuelim}:{valuefree}}',
		'doi'          => '{doi}',
		'isbn'         => '{isbn}',
		'page'         => '{page}',
		'permadead'    => '{permadead:{valueyes}:{valueno}}',
		'linkstring'   => '{linkstring}',
		'remainder'    => '{remainder}'
	];

	protected $formalName = false;

	protected $informalName = false;

	protected $map = false;

	protected $templateData = false;

	protected $string = false;

	protected $luaLocation = false;

	protected $redirected = false;

	protected $disabled = false;

	protected $disabledByUser = false;

	protected $assertRequirements = [];

	protected $useTemplateData = false;

	protected $classification = false;

	public function __construct( $name, $mapData = false, $classification = 'cite' ) {
		$this->formalName = API::getTemplateNamespaceName() . ":" . $name;
		$this->informalName = $name;
		$this->classification = $classification;
		if( $mapData ) $this->loadMap( $mapData );
	}

	public function loadMap( $mapData ) {
		if( empty( $mapData['mapString'] ) || !$this->loadMapString( $mapData['mapString'] ) ) {
			if( isset( $mapData['redirect'] ) ) $this->redirected = $mapData['redirect'];
			if( isset( $mapData['template_params'] ) ) $this->templateData = $mapData['template_params'];
			if( isset( $mapData['template_map'] ) ) $this->map = $mapData['template_map'];
			if( isset( $mapData['services'] ) ) $this->map = $mapData;

			if( self::$globalObject instanceof CiteMap ) {
				if( $this->classification == 'cite' ) self::$globalObject->registerParameters( $this->map['params'] );
			}

			if( $this->classification == 'cite' ) self::$requireUpdate = true;
		}
	}

	public function loadMapString( $string ) {
		$oldString = $this->string;
		$oldMap = $this->map;

		if( is_null( self::$mapObjects ) ) self::$mapObjects = [];

		if( empty( $string ) ) {
			$this->string = false;
			$this->clearMap();
			$this->buildMap();

			self::$requireUpdate = true;

			return true;
		} else {
			$this->string = $string;
			$this->clearMap();
			$this->buildMap();

			if( !$this->validateMap() ) {
				$this->string = $oldString;
				$this->map = $oldMap;

				return false;
			}

			return true;
		}
	}

	public function clearMap( $eraseAll = false ) {
		$this->map = false;
		$this->redirected = false;
		$this->disabled = false;
		$this->disabledByUser = false;
		$this->templateData = false;
		if( $eraseAll ) {
			$this->luaLocation = false;
			$this->string = false;
			self::$requireUpdate = true;
		}
	}

	protected function buildMap( $mapString = "" ) {
		if( !$this->useTemplateData && in_array( $this, self::$mapObjects ) ) $this->useTemplateData = true;

		$data = API::getTemplateData( $this->informalName );

		if( $data === false && $this->useTemplateData ) {
			$this->disabled = true;

			return false;
		}

		if( isset( $data['params'] ) ) $params = $data['params'];
		else $params = [];
		if( isset( $data['maps']['citoid'] ) ) $citoid = $data['maps']['citoid'];
		else $citoid = [];

		$this->loadTemplateData( $params, $citoid );

		if( empty( $mapString ) ) $mapString = $this->string;
		if( empty( $mapString ) && $this->isInvokingModule() ) $mapString = self::getGlobalString();
		if( empty( $mapString ) ) return $this->applyFromGlobal();
		$mapStrings = $this->breakdownMapString( $mapString );
		foreach( $mapStrings as $mapString ) {
			$tmp = $this->implementMapString( $mapString );
			if( $tmp === false ) return false;
		}

		if( empty( $this->map['services'] ) ) $this->disabled = true;
	}

	public function loadTemplateData( $params, $citoid ) {
		$toCheck = [ 'url', 'accessDate', 'archiveLocation', 'archiveDate', 'title', 'DOI', 'ISBN', 'pages' ];
		if( !empty( $params ) ) {
			foreach( $params as $param => $paramData ) {
				$toBind = [];
				$toBind[] = $param;
				if( isset( $paramData['aliases'] ) ) foreach( $paramData['aliases'] as $paramAlias ) {
					$toBind[] = $paramAlias;
				}
				$this->registerParameters( $toBind );
				unset( $citoidCheck );
				$shouldWeBind = isset( $paramData['required'] ) && $paramData['required'] === true;
				if( !empty( $citoid ) ) foreach( $toCheck as $check ) {
					if( isset( $citoid[$check] ) && in_array( $citoid[$check], $toBind ) ) {
						$shouldWeBind = true;
						$citoidCheck = $check;
						break;
					}
				}
				if( $shouldWeBind ) {
					if( isset( $citoidCheck ) ) {
						switch( $citoidCheck ) {
							case "url":
								$mapType = "url";
								$customValues = false;
								break;
							case "accessDate":
								$mapType = "access_date";
								$customValues['format'] = 'automatic';
								break;
							case "archiveLocation":
								$mapType = "archive_url";
								$customValues = false;
								break;
							case "archiveDate":
								$mapType = "archive_date";
								$customValues['format'] = 'automatic';
								break;
							case "title":
								$mapType = "title";
								$customValues = false;
								break;
							case "DOI":
								$mapType = "doi";
								$customValues = false;
								break;
							case "pages":
								$mapType = "page";
								$customValues = false;
								break;
							case "ISBN":
								$mapType = "isbn";
								$customValues = false;
								break;
						}
					} else {
						$mapType = "other";
						$customValues = false;
					}
					$this->bindToParams( $mapType, $toBind, '@default', $customValues, true );
				}
			}
		}
	}

	public function registerParameters( $params ) {
		if( $this !== self::$globalObject ) {
			//We will want to register these globally too.
			if( !is_null( self::$globalObject ) &&
			    $this->classification == 'cite' ) self::$globalObject->registerParameters( $params );
		}
		if( empty( $params ) ) return false;
		if( is_null( $this->map['params'] ) ) $this->map['params'] = [];
		foreach( $params as $param ) if( !in_array( $param, $this->map['params'] ) ) {
			$this->map['params'][] = $param;
			if( $this === self::$globalObject ) self::$requireUpdate = true;
		}

		return true;
	}

	public function bindToParams( $type, $params, $service = '__NONE__', $customValues = false, $flagOther = false ) {

		if( $this->classification == 'cite' && $this !== self::$globalObject ) {
			//We will want to bind these globally too.
			$flagOtherGlobal = ( $flagOther === 2 );
			if( $flagOtherGlobal ) {
				usleep( 1 );
			}
			if( !is_null( self::$globalObject ) &&
			    $this->classification == 'cite' ) self::$globalObject->bindToParams( $type, $params, $service,
			                                                                         $customValues, $flagOtherGlobal
			);
		}
		$flagOther = ( $flagOther || $flagOther == 2 );
		if( isset( self::$serviceMap[$type] ) ) {
			$valueString = self::$serviceMap[$type];
			if( is_array( $customValues ) ) {
				//For paywall backwards compatibility
				if( $type == 'paywall' && isset( $customValues['valueyes'] ) ) $valueString =
					'{paywall:{valueyes}:{valueno}}';
				if( $type == 'deadvalues' ) {
					if( !isset( $customValues['valueusurp'] ) ) $customValues['valueusurp'] = '';
					if( !isset( $customValues['defaultvalue'] ) ||
					    !in_array( $customValues['defaultvalue'], [ 'yes', 'no', 'usurp' ] ) )
						$customValues['defaultvalue'] = 'yes';
				}
				if( strpos( $valueString, 'timestamp' ) ) {
					$customValues['type'] = 'timestamp';
				}
				foreach( $customValues as $key => $value ) {
					$valueString = str_replace( "{{$key}}", $value, $valueString );
				}
			} elseif( is_string( $customValues ) ) $valueString = $customValues;
		} elseif( $type == 'other' ) {
			if( is_string( $customValues ) ) {
				$valueString = $customValues;
				switch( $customValues ) {
					case "{epochbase62}":
					case "{epoch}":
					case "{microepochbase62}":
					case "{microepoch}":
						$type = 'archive_date';
						$customValues = [ 'type' => trim( $customValues, '{} ' ) ];
						break;
				}
			} else $valueString = '&mdash;';
		} else return false;
		if( $service == '__NONE__' ) $tService = '@default';
		else $tService = $service;
		if( isset( $this->map['services'][$tService][$type] ) ) {
			foreach( $this->map['services'][$tService][$type] as $serviceID => $serviceValue ) {
				if( is_array( $serviceValue ) ) {
					if( !is_array( $customValues ) ) continue;
					foreach( $customValues as $key => $customValue ) {
						if( !isset( $serviceValue[$key] ) || $serviceValue[$key] != $customValue ) continue 2;
					}
					foreach( $serviceValue as $key => $value ) {
						if( $key == 'index' ) continue;
						if( !isset( $customValues[$key] ) || $customValues[$key] != $value ) continue 2;
					}
					$dataID = $serviceValue['index'];
					break;
				} else {
					if( is_array( $customValues ) ) continue;
					$tmpID = $serviceValue;
					if( is_string( $customValues ) ) {
						if( $customValues != $this->map['data'][$tmpID]['valueString'] ) continue;
					} elseif( $customValues === false ) {
						if( $this->map['data'][$tmpID]['valueString'] != '&mdash;' &&
						    $this->map['data'][$tmpID]['valueString'] != self::$serviceMap[$type] ) continue;
					}
					$dataID = $serviceValue;
					break;
				}
			}
		}
		if( !isset( $dataID ) ) {
			if( !empty( $this->map['data'] ) ) $dataID = max( array_keys( $this->map['data'] ) ) + 1;
			else $dataID = 0;
			unset( $serviceID );
		}
		if( strpos( $valueString, 'timestamp' ) !== false ) {
			$customValues['type'] = 'timestamp';
		}
		if( !empty( $this->map['params'] ) ) foreach( $params as $param ) {
			$index = array_search( $param, $this->map['params'] );
			if( $index === false ) continue;
			if( !isset( $this->map['data'][$dataID] ) ) $this->map['data'][$dataID]['mapto'] = [];
			if( !@in_array( $index, $this->map['data'][$dataID]['mapto'] ) ) {
				if( $this->classification == 'cite' && $this === self::$globalObject ) self::$requireUpdate = true;
				$this->map['data'][$dataID]['mapto'][] =
					$index;
			}
		}
		if( isset( $this->map['data'][$dataID] ) ) {
			$this->map['data'][$dataID]['valueString'] = $valueString;
			if( $service == '__NONE__' ) $this->map['data'][$dataID]['universal'] = true;
			else $this->map['data'][$dataID]['serviceidentifier'] = $service;

			if( $service != '__NONE__' ) {
				if( !isset( $this->map['services'][$service] ) ) {
					$this->map['services'][$service] = [];

					foreach( $this->map['services'] as $tService => $tMaps ) {
						if( $tService === $service ) continue;
						foreach( $tMaps as $tType => $tData ) {
							foreach( $tData as $ttData ) {
								if( is_array( $ttData ) ) {
									$tid = $ttData['index'];
								} else {
									$tid = $ttData;
								}
								if( isset( $this->map['data'][$tid]['universal'] ) &&
								    !in_array( $ttData, $this->map['services'][$service][$tType] ) )
									$this->map['services'][$service][$tType][] = $ttData;
							}
						}
					}
				}
				if( is_array( $customValues ) ) {
					if( !isset( $serviceID ) ) $this->map['services'][$service][$type][] =
						array_merge( [ 'index' => $dataID ], $customValues );
				} else {
					if( !isset( $serviceID ) ) $this->map['services'][$service][$type][] = $dataID;
					if( $type == 'other' && !in_array( $dataID, $this->map['services'][$service]['other'] ) )
						$this->map['services'][$service]['other'][] = $dataID;
				}
			} else {
				if( !isset( $this->map['services']['@default'] ) ) $this->map['services']['@default'] = [];
				foreach( $this->map['services'] as $tService => $garbage ) {
					if( is_array( $customValues ) ) {
						if( !isset( $serviceID ) ) $this->map['services'][$tService][$type][] =
							array_merge( [ 'index' => $dataID ], $customValues );
					} else {
						if( !isset( $serviceID ) ) $this->map['services'][$tService][$type][] = $dataID;
						if( $type == 'other' && !in_array( $dataID, $this->map['services'][$tService]['other'] ) )
							$this->map['services'][$tService]['other'][] = $dataID;
					}
				}
			}

			if( $type == 'other' && $flagOther ) {
				$this->map['data'][$dataID]['required'] = true;
			}
		}

		return true;
	}

	public function isInvokingModule() {
		$text = $this->getTemplateSource();

		if( self::$globalObject->getLuaLocation() !== false && strpos( $text, '#invoke' ) !== false ) {
			return true;
		} else return false;
	}

	protected function getTemplateSource() {
		if( isset( self::$lastSourceUpdate[$this->formalName] ) ) {
			if( time() - self::$lastSourceUpdate[$this->formalName] < 900 ) return self::$sources[$this->formalName];
		}

		$source = API::getPageText( $this->formalName );
		self::$sources[$this->formalName] = $source;
		self::$lastSourceUpdate[$this->formalName] = time();
		return $source;
	}

	public static function getGlobalString() {
		return self::$globalObject->getString();
	}

	protected function applyFromGlobal() {
		$map = self::$globalObject->getMap();
		if( !empty( $map['services'] ) ) foreach( $map['services'] as $service => $serviceTypes ) {
			foreach( $serviceTypes as $type => $serviceMaps ) {
				foreach( $serviceMaps as $serviceMap ) {
					$params = [];
					if( is_array( $serviceMap ) ) {
						$dataID       = $serviceMap['index'];
						$customValues = $serviceMap;
						unset( $customValues['index'] );
					} else {
						$dataID       = $serviceMap;
						$customValues = false;
					}
					foreach( $map['data'][$dataID]['mapto'] as $paramID ) {
						$params[] = $map['params'][$paramID];
					}

					$shouldBind = ( $type != 'other' );
					$shouldBind |= ( $type == 'other' && isset( $map['data'][$dataID]['required'] ) );
					if( $shouldBind ) $this->bindToParams( $type, $params, $service, $customValues );
				}
			}
		}
	}

	protected function breakdownMapString( $mapString ) {
		$mapString = str_replace( '\\+', '__PLUSESCAPED__', $mapString );
		$mapStrings = explode( '++', $mapString );
		$mapStrings = array_map( function( $string ) {
			return str_replace( '__PLUSESCAPED__', '+', $string );
		}, $mapStrings
		);

		return $mapStrings;
	}

	protected function implementMapString( $string ) {
		if( preg_match( '/NULL/i', $string, $garbage ) ) {
			return $this->disableMap();
		}

		if( preg_match( '/#REDIRECT\[\[(.*?)\]\]/i', $string, $redirectTo ) ) {
			return $this->setRedirect( $redirectTo[1] );
		}

		if( preg_match( '/#CS\[\[(.*?)\]\]/i', $string, $CSLocation ) ) {
			$this->setLuaConfiguration( API::getModuleNamespaceName() . ":" . $CSLocation[1] );
			$module = $this->getModuleSource();
			$config = $this->getLuaConfiguration();
			$config = self::parseCSConfig( $config );
			$mapValues = self::getMapValues( $config, $module );
			$mapString = self::buildMasterMapString( $mapValues );

			return $this->implementMapString( $mapString );
		}

		if( preg_match_all( '/\{(\@(?:\{.*?\}|.)*?)\}/i', $string, $identifiers ) ) {
			$string = preg_replace( '/\{(\@(?:\{.*?\}|.)*?)\}/i', "", $string );
		}
		$data = explode( "|", $string );
		array_map( 'trim', $data );
		$idCounter = 0;
		foreach( $data as $id => $set ) {
			if( empty( $set ) ) {
				if( isset( $identifiers[1][$idCounter] ) ) {
					$identifier = array_map( 'trim', explode( "|", $identifiers[1][$idCounter] ) );
					$serviceIdentifier = '__NONE__';
					foreach( $identifier as $subId => $subset ) {
						if( $subId == 0 ) {
							$serviceIdentifier = $subset;
						} else {
							if( strpos( $subset, "=" ) !== false ) {
								$tmp = array_map( "trim", explode( "=", $subset, 2 ) );
								$toBind[] = $tmp[0];
								$this->registerParameters( $toBind );
								if( $tmpValues = self::seekServiceType( $tmp[1] ) ) {
									$type = $tmpValues['type'];
									if( isset( $tmpValues['customValues'] ) ) {
										$customValues = $tmpValues['customValues'];
									} else {
										$customValues = false;
									}
								} else {
									$type = 'other';
									$customValues = $tmp[1];
								}
								$this->bindToParams( $type, $toBind, $serviceIdentifier, $customValues, 2 );
								$toBind = [];
							} else {
								$toBind[] = $subset;
							}
						}
					}
					$idCounter++;
					continue;
				}
			} elseif( strpos( $set, "=" ) !== false ) {
				$tmp = array_map( "trim", explode( "=", $set, 2 ) );
				$toBind[] = $tmp[0];
				$this->registerParameters( $toBind );
				if( $tmpValues = self::seekServiceType( $tmp[1] ) ) {
					$type = $tmpValues['type'];
					if( isset( $tmpValues['customValues'] ) ) {
						$customValues = $tmpValues['customValues'];
					} else {
						$customValues = false;
					}
				} else {
					$type = 'other';
					$customValues = $tmp[1];
				}
				$this->bindToParams( $type, $toBind, '__NONE__', $customValues );
				$toBind = [];
			} else {
				$toBind[] = $set;
			}
		}

		return true;
	}

	protected function disableMap() {
		$this->disabled = true;
		$this->disabledByUser = true;

		return true;
	}

	protected function setRedirect( $location ) {
		if( empty( $location ) ) {
			$this->redirected = false;

			return true;
		}

		$tmp = DB::getConfiguration( $location, 'citation-rules', $this->informalName );

		if( !empty( $tmp ) ) {
			$this->redirected = $location;
			if( !( $tmp instanceof CiteMap ) ) self::$redirectTargetObjects[$this->redirected][$this->informalName] =
				self::convertToObject( $this->informalName, $tmp, 'cite' );
			else self::$redirectTargetObjects[$this->redirected][$this->informalName] = $tmp;

			return true;
		} else {
			$tmp = DB::getConfiguration( "global", "citation-rules", $this->informalName );
			if( empty( $tmp[$location] ) ) return false;
			$this->redirected = $location;
			self::$redirectTargetObjects[$this->redirected][$this->informalName] =
				self::convertToObject( $this->informalName, $tmp[$location], 'cite' );
		}

		return false;
	}

	protected static function convertToObject( $name, $mapData, $classification ) {
		return new CiteMap( $name, $mapData, $classification );
	}

	protected function setLuaConfiguration( $page ) {
		if( empty( $page ) ) {
			$this->luaLocation = false;

			return true;
		} else {
			$this->luaLocation = $page;
			if( $this->getLuaConfiguration() && $this->getModuleSource() ) return true;
			else {
				$this->luaLocation = false;

				return false;
			}
		}
	}

	public function getLuaConfiguration() {
		if( $this->luaLocation === false ) return false;

		if( isset( self::$lastSourceUpdate[$this->luaLocation] ) ) {
			if( time() - self::$lastSourceUpdate[$this->luaLocation] < 900 ) return self::$sources[$this->luaLocation];
		}

		$source = API::getPageText( $this->luaLocation );
		self::$sources[$this->luaLocation] = $source;
		self::$lastSourceUpdate[$this->luaLocation] = time();
		return $source;
	}

	protected function getModuleSource() {
		if( $this->luaLocation === false ) return false;

		$location = substr( $this->luaLocation, 0, strrpos( $this->luaLocation, '/' ) );

		if( isset( self::$lastSourceUpdate[$location] ) ) {
			if( time() - self::$lastSourceUpdate[$location] < 900 ) return self::$sources[$location];
		}

		$source = API::getPageText( $location );
		self::$sources[$location] = $source;
		self::$lastSourceUpdate[$location] = time();
		return $source;
	}

	/**
	 * Reads the content of the configuration values for the CS Lua Module and returns an associative array of defined
	 * values.
	 *
	 * @access public
	 *
	 * @param $string The content of the CS configuration module
	 *
	 * @return array|bool False on failure
	 */
	protected static function parseCSConfig( $string ) {
		$commentRegex = '/\-\-(?:\[\[(?:.|\n)*?\]\]|.*$)/m';
		$parseRegex =
			'/(?:local\s+|citation_config\.)([^\s=]*)\s*\=\s*(?:(\{(?:"(?:\\\\"|[^"])*"|\'(?:\\\\\'|[^\'])*\'|[^{}\'"]*|(?2))*?\}))/i';
		$old = ini_set( 'pcre.jit', false );
		$returnArray = [];
		// Filter out the comments before parsing the text.
		$string = preg_replace( $commentRegex, '', $string );
		if( preg_match_all( $parseRegex, $string, $matches ) ) foreach( $matches[0] as $tid => $match ) {
			$parameter = $matches[1][$tid];
			$content = trim( $matches[2][$tid] );
			$returnArray[$parameter] = self::parseLuaObject( $content );
		} else {
			ini_set( 'pcre.jit', $old );

			return false;
		}
		ini_set( 'pcre.jit', $old );

		return $returnArray;
	}

	protected static function parseLuaObject( $string ) {
		$parseRegex =
			'/\s*(?:\[(\'(?:\\\\\'|[^\'])*\'|"(?:\\\\"|[^"])*")\]\s*=|([^\s\'"]*)\s*=)?\s*(\'(?:\\\\\'|[^\'])*\'|"(?:\\\\"|[^"])*"|(\{(?:"(?:\\\\"|[^"])*?"|\'(?:\\\\\'|[^\'])*?\'|[^{}\'"]*|(?4))*?\})|true|false|null|nil|[0-9a-fx]*(?:\.[0-9a-fx]*)?)\s*[,;]?/i';
		$string = trim( $string );
		if( substr( $string, 0, 1 ) == "{" ) {
			$returnArray = [];
			$string = substr( $string, 1, strlen( $string ) - 2 );
			$old = ini_set( 'pcre.jit', false );
			if( preg_match_all( $parseRegex, $string, $matches ) ) foreach( $matches[0] as $tid => $fullMatch ) {
				if( empty( trim( $matches[1][$tid] ) ) && empty( trim( $matches[2][$tid] ) ) &&
				    empty( trim( $matches[3][$tid] ) ) ) continue;
				if( empty( trim( $matches[1][$tid] ) ) && empty( trim( $matches[2][$tid] ) ) ) $returnArray[] =
					self::parseLuaObject( $matches[3][$tid] );
				elseif( !empty( trim( $matches[1][$tid] ) ) ) $returnArray[self::parseLuaObject( $matches[1][$tid] )] =
					self::parseLuaObject( $matches[3][$tid] );
				else $returnArray[trim( $matches[2][$tid] )] = self::parseLuaObject( $matches[3][$tid] );
			}
			ini_set( 'pcre.jit', $old );

			return $returnArray;
		} elseif( strtolower( $string ) == "false" ) return false;
		elseif( strtolower( $string ) == "true" ) return true;
		elseif( is_numeric( $string ) ) return (float) $string;
		elseif( substr( $string, 0, 1 ) == "'" || substr( $string, 0, 1 ) == "\"" ) {
			$string = substr( $string, 1, strlen( $string ) - 2 );
			$string = str_replace( '\\\'', '\'', $string );
			$string = str_replace( '\\"', '"', $string );
			$string = str_replace( '\\\\', '\\', $string );

			return $string;
		} else return null;
	}

	protected static function getMapValues( $configArray, $moduleCode ) {
		$returnArray = [];
		if( !isset( $configArray['keywords']['yes_true_y'] ) ) $configArray['keywords']['yes_true_y'] =
			[ 'yes', 'true', 'y' ];
		$old = ini_set( 'pcre.jit', false );
		$returnArray['title'] = self::getTitleValues( $configArray );
		$returnArray['titlelink'] = self::getTitleLinkValues( $configArray );
		$returnArray['isbn'] = self::getISBNValues( $configArray );
		$returnArray['doi'] = self::getDOIValues( $configArray );
		$returnArray['page'] = self::getPageValues( $configArray );
		$returnArray['url'] = self::getURLValues( $configArray );
		$returnArray['accesstimestamp'] = self::getAccessDateValues( $configArray );
		$returnArray['archivetimestamp'] = self::getArchiveDateValues( $configArray );
		$returnArray['archiveurl'] = self::getArchiveURLValues( $configArray );
		$returnArray['paywall'] = self::getPaywallValues( $configArray, $moduleCode );
		$returnArray['deadvalues'] = self::getDeadValues( $configArray, $moduleCode );
		ini_set( 'pcre.jit', $old );

		return $returnArray;
	}

	protected static function getTitleValues( $configArray ) {
		$returnArray = [];
		if( !empty( $configArray['aliases']['Title'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['Title'], $returnArray );
		}
		if( !empty( $configArray['aliases']['TransTitle'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['TransTitle'], $returnArray );
		}
		if( !empty( $configArray['aliases']['BookTitle'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['BookTitle'], $returnArray );
		}
		if( !empty( $configArray['aliases']['ScriptTitle'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['ScriptTitle'], $returnArray );
		}

		return $returnArray;
	}

	protected static function addToArray( $value, $returnArray ) {
		if( !is_array( $returnArray ) ) {
			if( !is_null( $returnArray ) ) return false;
			else $returnArray = [];
		}
		if( !is_array( $value ) ) $returnArray[] = self::escapeMapValue( $value );
		else {
			foreach( $value as $tid=>$tmp ) $value[$tid] = self::escapeMapValue( $tmp );
			$returnArray = array_merge( $returnArray, $value );
		}

		return $returnArray;
	}

	protected static function getTitleLinkValues( $configArray ) {
		$returnArray = [];
		if( !empty( $configArray['aliases']['TitleLink'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['TitleLink'], $returnArray );
		}

		return $returnArray;
	}

	protected static function getISBNValues( $configArray ) {
		$returnArray = [];
		if( !empty( $configArray['id_handlers']['ISBN']['parameters'] ) ) {
			$returnArray = self::addToArray( $configArray['id_handlers']['ISBN']['parameters'], $returnArray );
		}

		return $returnArray;
	}

	protected static function getDOIValues( $configArray ) {
		$returnArray = [];
		if( !empty( $configArray['id_handlers']['DOI']['parameters'] ) ) {
			$returnArray = self::addToArray( $configArray['id_handlers']['DOI']['parameters'], $returnArray );
		}

		return $returnArray;
	}

	protected static function getPageValues( $configArray ) {
		$returnArray = [];
		if( !empty( $configArray['aliases']['Page'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['Page'], $returnArray );
		}
		if( !empty( $configArray['aliases']['Pages'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['Pages'], $returnArray );
		}
		if( !empty( $configArray['aliases']['At'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['At'], $returnArray );
		}
		if( !empty( $configArray['aliases']['Position'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['Position'], $returnArray );
		}

		return $returnArray;
	}

	protected static function getURLValues( $configArray ) {
		$returnArray = [];
		if( !empty( $configArray['aliases']['URL'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['URL'], $returnArray );
		}
		if( !empty( $configArray['aliases']['ChapterURL'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['ChapterURL'], $returnArray );
		}
		if( !empty( $configArray['aliases']['ConferenceURL'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['ConferenceURL'], $returnArray );
		}
		if( !empty( $configArray['aliases']['LayURL'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['LayURL'], $returnArray );
		}

		return $returnArray;
	}

	protected static function getAccessDateValues( $configArray ) {
		$returnArray = [];
		if( !empty( $configArray['aliases']['AccessDate'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['AccessDate'], $returnArray );
		}

		return $returnArray;
	}

	protected static function getArchiveDateValues( $configArray ) {
		$returnArray = [];
		if( !empty( $configArray['aliases']['ArchiveDate'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['ArchiveDate'], $returnArray );
		}

		return $returnArray;
	}

	protected static function getArchiveURLValues( $configArray ) {
		$returnArray = [];
		if( !empty( $configArray['aliases']['ArchiveURL'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['ArchiveURL'], $returnArray );
		}

		return $returnArray;
	}

	protected static function getPaywallValues( $configArray, $moduleCode ) {
		$returnArray = [];
		$tmp = [];
		if( !empty( $configArray['aliases']['UrlAccess'] ) ) {
			$tmp = self::addToArray( $configArray['aliases']['UrlAccess'], $tmp );
		}
		if( !empty( $configArray['aliases']['ChapterUrlAccess'] ) ) {
			$tmp = self::addToArray( $configArray['aliases']['ChapterUrlAccess'], $tmp );
		}
		if( isset( $configArray['keywords']['free'] ) && isset( $configArray['keywords']['limited'] ) &&
		    isset( $configArray['keywords']['registration'] ) && isset( $configArray['keywords']['subscription'] ) ) {
			$params['free'] = self::addToArray( $configArray['keywords']['free'], [] );
			$params['limited'] = self::addToArray( $configArray['keywords']['limited'], [] );
			$params['registration'] = self::addToArray( $configArray['keywords']['registration'], [] );
			$params['subscription'] = self::addToArray( $configArray['keywords']['subscription'], [] );
			$tmp['__PARAMS__'] = $params;
		} else $tmp = [];
		if( !empty( $tmp ) ) {
			$returnArray[] = $tmp;
			$tmp = [];
		}
		if( !empty( $configArray['aliases']['RegistrationRequired'] ) ) {
			$tmp = self::addToArray( $configArray['aliases']['RegistrationRequired'], $tmp );
			$tmp['__PARAMS__']['registration'] = self::addToArray( $configArray['keywords']['yes_true_y'], [] );
			$returnArray[] = $tmp;
			$tmp = [];
		}
		if( !empty( $configArray['aliases']['SubscriptionRequired'] ) ) {
			$tmp = self::addToArray( $configArray['aliases']['SubscriptionRequired'], $tmp );
			$tmp['__PARAMS__']['subscription'] = self::addToArray( $configArray['keywords']['yes_true_y'], [] );
			$returnArray[] = $tmp;
			$tmp = [];
		}

		return $returnArray;
	}

	protected static function escapeMapValue( $value ) {
		return str_replace( ':', '\\:', $value );
	}

	protected static function getDeadValues( $configArray, $moduleCode ) {
		$returnArray = [];
		$codeRegex =
			'/if\s+(?:(.*?)\s*==\s*(?:UrlStatus|DeadURL)|in_array\s*\((?:UrlStatus|DeadURL),\s*(.*?)\s*\))\s*then\s+local\s+arch_text\s+=\s+cfg.messages\[\'archived\'\];(?:(?:\n|.)*?if\s+(?:(.*?)\s*==\s*(?:UrlStatus|DeadURL)|in_array\s*\((?:UrlStatus|DeadURL),\s*(.*?)\s*\))\s*then\s+Archived = sepc \.\.)?/im';
		$secondaryCodeRegex =
			'/if\s+is_set\s*\(\s*(?:DeadURL|UrlStatus)\s*\)\s+then\s+(?:DeadURL|UrlStatus)\s+=\s+DeadURL:lower\s*\(\)\s+~=\s+(["\'].*?["\'])/im';
		if( !empty( $configArray['aliases']['UrlStatus'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['UrlStatus'], $returnArray );
		}
		if( !empty( $configArray['aliases']['DeadURL'] ) ) {
			$returnArray = self::addToArray( $configArray['aliases']['DeadURL'], $returnArray );
		}
		if( !empty( $returnArray ) ) {
			$params = [];
			if( isset( $configArray['keywords']['live'] ) && isset( $configArray['keywords']['dead'] ) ) {
				$params['live'] = self::addToArray( $configArray['keywords']['live'], [] );
				$params['dead'] = self::addToArray( $configArray['keywords']['dead'], [] );
				if( !isset( $params['unknown'] ) ) $params['unknown'] = [];
				if( isset( $configArray['keywords']['bot: unknown'] ) ) $params['unknown'] =
					self::addToArray( $configArray['keywords']['bot: unknown'], $params['unknown'] );
				if( isset( $configArray['keywords']['unfit'] ) ) $params['unknown'] =
					self::addToArray( $configArray['keywords']['unfit'], $params['unknown'] );
				if( isset( $configArray['keywords']['usurped'] ) ) $params['unknown'] =
					self::addToArray( $configArray['keywords']['usurped'], $params['unknown'] );
				if( empty( $params['unknown'] ) ) $params['unknown'] = $params['dead'];
			} else {
				if( isset( $configArray['keywords']['url-status'] ) ) {
					$tmp = $configArray['keywords']['url-status'];
				} elseif( isset( $configArray['keywords']['deadurl'] ) ) $tmp = $configArray['keywords']['deadurl'];
				elseif( isset( $configArray['keywords']['affirmative'] ) ) $tmp =
					$configArray['keywords']['affirmative'];
				else $tmp = $configArray['keywords']['yes_true_y'];
				if( !preg_match( $codeRegex, $moduleCode, $match ) &&
				    !preg_match( $secondaryCodeRegex, $moduleCode, $match ) ) {
					return false;
				} else {
					if( !empty( $match[1] ) ) {
						$params['live'] = [ self::parseLuaObject( $match[1] ) ];
					} elseif( !empty( $match[2] ) ) {
						$params['live'] = self::parseLuaObject( $match[2] );
					} else {
						$params['live'] = [ '???' ];
					}
					if( !empty( $match[3] ) ) {
						$params['unknown'] = [ self::parseLuaObject( $match[3] ) ];
					} elseif( !empty( $match[4] ) ) {
						$params['unknown'] = self::parseLuaObject( $match[4] );
					}
					foreach( $params['live'] as $param ) {
						$tid = array_search( $param, $tmp );
						if( $tid !== false ) unset( $tmp[$tid] );
					}
					if( isset( $params['unknown'] ) ) foreach( $params['unknown'] as $param ) {
						$tid = array_search( $param, $tmp );
						if( $tid !== false ) unset( $tmp[$tid] );
					}
					$params['dead'] = self::addToArray( $tmp, [] );
					//if( !isset( $params['unknown'] ) ) $params['unknown'] = $params['dead'];
				}
			}
			$returnArray['__PARAMS__'] = $params;
			if( isset( $configArray['defaults']['UrlStatus'] ) || isset( $configArray['defaults']['DeadURL'] ) ) {
				if( isset( $configArray['defaults']['UrlStatus'] ) ) $use = "UrlStatus";
				else $use = "DeadURL";
				foreach( $params as $type => $subset ) {
					if( in_array( $configArray['defaults'][$use], $subset ) ) {
						$returnArray['__DEFAULT__'] = $type;
						break;
					}
				}
			} else $returnArray['__DEFAULT__'] = 'dead';

			return $returnArray;
		} else return false;
	}

	protected static function buildMasterMapString( $mapValues ) {
		$mapArray = [];
		foreach( $mapValues as $type => $values ) {
			if( $values === false ) continue;
			if( strpos( $type, 'timestamp' ) ) {
				$keyword = "{{$type}:automatic}";
			} elseif( $type == 'deadvalues' ) {
				$keyword = "{deadvalues:";
				$keyword .= @implode( ';;', $values['__PARAMS__']['dead'] ) . ':';
				$keyword .= @implode( ';;', $values['__PARAMS__']['live'] ) . ':';
				$keyword .= @implode( ';;', $values['__PARAMS__']['unknown'] ) . ':';
				unset( $values['__PARAMS__'] );
				switch( $values['__DEFAULT__'] ) {
					case 'dead':
						$keyword .= 'yes}';
						break;
					case 'live':
						$keyword .= 'no}';
						break;
					default:
						$keyword .= 'unknown}';
						break;
				}
			} elseif( $type == 'paywall' ) {
				foreach( $values as $realValues ) {
					$keyword = '{paywall:';
					if( isset( $realValues['__PARAMS__']['subscription'] ) ) $keyword .= implode( ';;',
					                                                                              $realValues['__PARAMS__']['subscription']
					);
					$keyword .= ':';
					if( isset( $realValues['__PARAMS__']['registration'] ) ) $keyword .= implode( ';;',
					                                                                              $realValues['__PARAMS__']['registration']
					);
					$keyword .= ':';
					if( isset( $realValues['__PARAMS__']['limited'] ) ) $keyword .= implode( ';;',
					                                                                         $realValues['__PARAMS__']['limited']
					);
					$keyword .= ':';
					if( isset( $realValues['__PARAMS__']['free'] ) ) $keyword .= implode( ';;',
					                                                                      $realValues['__PARAMS__']['free']
					);
					unset( $realValues['__PARAMS__'] );
					$keyword .= '}';
					$mapArray[] = implode( '|', $realValues ) . "=$keyword";
				}
				continue;
			} else $keyword = "{{$type}}";
			$mapArray[] = implode( '|', $values ) . "=$keyword";
		}

		return implode( '|', $mapArray );
	}

	protected static function seekServiceType( $valueString ) {
		$returnArray = [];
		$data = trim( $valueString, " {}\t\n\r\0\x0B" );
		$data = str_replace( '\\:', "ESCAPEDCOLON", $data );
		$data = explode( ":", $data );
		$data = str_replace( 'ESCAPEDCOLON', '\\:', $data );
		foreach( self::$serviceMap as $type => $string ) {
			if( $type == 'paywall' && count( $data ) === 3 ) {
				$string = '{paywall:{valueyes}:{valueno}}';
			}
			$testData = trim( $string, " {}\t\n\r\0\x0B" );
			$testData = str_replace( '\:', "ESCAPEDCOLON", $testData );
			$testData = explode( ":", $testData );
			$testData = str_replace( 'ESCAPEDCOLON', ':', $testData );
			if( $data[0] == $testData[0] ) {
				$returnArray['type'] = $type;
				unset( $testData[0] );
				foreach( $testData as $tid => $placeholder ) {
					$placeholder = trim( $placeholder, " {}\t\n\r\0\x0B" );
					$returnArray['customValues'][$placeholder] = $data[$tid];
				}

				return $returnArray;
			}
		}

		return false;
	}

	public function validateMap() {
		foreach( $this->assertRequirements as $service => $subset ) {
			foreach( $subset as $type ) {
				if( $service == '__NONE__' ) foreach( $this->map['services'] as $tService => $tSubset ) {
					if( !isset( $tSubset[$type] ) ) {
						if( !isset( self::$services[$tService][$type] ) ) return false;

						$tmp = self::$services[$tService][$type];
						foreach( $tmp['requires'] as $requiredType ) {
							if( !isset( $tSubset[$requiredType] ) ) return false;
						}
					}
				} else {
					if( !isset( $this->map['services'][$service][$type] ) ) {
						if( !isset( self::$services[$service][$type] ) ) return false;

						$tmp = self::$services[$service][$type];
						foreach( $tmp['requires'] as $requiredType ) {
							if( !isset( $tSubset[$requiredType] ) ) return false;
						}
					}
				}
			}
		}

		return true;
	}

	public static function loadGenerator( DataGenerator $generator ) {
		self::$generatorObject = $generator;
	}

	public static function setGlobalString( $string ) {
		return self::$globalObject->loadMapString( $string );
	}

	public static function importCitoid() {
		$citoidData = API::retrieveCitoidDefinitions();
		if( isset( $citoidData['unique_templates'] ) ) {
			foreach( $citoidData['unique_templates'] as $template ) {
				if( isset( $citoidData['template_data'][$template]['maps']['citoid']['url'] ) ) {
					CiteMap::registerTemplate( "{{{$template}}}" );
				}
			}
		}
		if( isset( $citoidData['mapped_templates']['webpage'] ) ) CiteMap::setDefaultTemplate( $citoidData['mapped_templates']['webpage']
		);

		foreach( self::$templateList as $template ) {
			$template = trim( $template, '{}' );
			$template = API::getTemplateNamespaceName() . ":$template";
			$templateLookup[] = $template;
		}
		$templatesExist = API::pagesExist( $templateLookup );

		foreach( $templatesExist as $template=>$exists ) {
			if( !$exists ) {
				self::unregisterMapObject( $template );
				continue;
			}
			$template = explode( ':', $template, 2 )[1];
			if( !isset( $citoidData['template_data'][$template] ) ) $citoidData['template_data'][$template] =
				API::getTemplateData( $template );
			if( $citoidData['template_data'][$template] !== false ) {
				self::registerMapObject( $template );
			} elseif( $citoidData['template_data'][$template] === false ) {
				self::unregisterMapObject( $template );
				continue;
			}
			if( isset( $citoidData['template_data'][$template]['params'] ) ) $params =
				$citoidData['template_data'][$template]['params'];
			else $params = [];
			if( isset( $citoidData['template_data'][$template]['maps']['citoid'] ) ) $citoid =
				$citoidData['template_data'][$template]['maps']['citoid'];
			else $citoid = [];
			self::$mapObjects[$template]->loadTemplateData( $params, $citoid );
		}
	}

	public static function getMaps( $wiki, $force = false, $type = 'cite' ) {
		if( $force || $wiki != self::$wiki || empty( ( $type == 'cite' ? self::$mapObjects :
				( $type == 'archive' ? self::$archiveObjects : self::$deadObject ) )
			) ) {
			if( self::$wiki != $wiki ) {
				self::$archiveObjects = null;
				self::$globalObject = null;
				self::$mapObjects = null;
				self::$deadObject = null;
				self::$templateList = null;
				self::$lastUpdate = null;
			}
			self::$wiki = $wiki;
			if( $type == 'cite' ) {
				$tmp = DB::getConfiguration( $wiki, "citation-rules" );
				if( empty( $tmp ) ) {
					$tmp = DB::getConfiguration( "global", "citation-rules" );
					if( !empty( $tmp ) ) $tmp = CiteMap::convertToObjects( $tmp );
					foreach( $tmp as $key => $object ) {
						DB::setConfiguration( $wiki, "citation-rules", $key, $object );
					}
				}
				self::$templateList = DB::getConfiguration( 'global', 'citation-rules', 'template-list' );
				@self::$globalObject = $tmp['__GLOBAL__'];
				if( !( self::$globalObject instanceof CiteMap ) ) self::$globalObject = new CiteMap( '__GLOBAL__' );
				@self::$globalTemplate = $tmp['__TEMPLATE__'];
				@self::$globalTitle = $tmp['__TITLE__'];
				@self::$lastUpdate = $tmp['__UPDATED__'];
				unset( $tmp['__TEMPLATE__'], $tmp['__TITLE__'], $tmp['__GLOBAL__'], $tmp['__UPDATED__'] );
				@self::$mapObjects = $tmp;
				ksort( self::$mapObjects );

				if( $force || self::$requireUpdate ) self::updateMaps();
			} elseif( $type == 'archive' ) {
				$tmp = DB::getConfiguration( 'global', "archive-templates" );

				foreach( $tmp as $name => $object ) {
					if( !( $object['archivetemplatedefinitions'] instanceof CiteMap ) ) {
						$tmp[$name]['archivetemplatedefinitions'] =
							self::convertToObject( $name, $object['archivetemplatedefinitions'], 'archive' );
						DB::setConfiguration( 'global', "archive-templates", $name, $tmp[$name] );
					}
				}

				self::$archiveObjects = $tmp;

				ksort( self::$archiveObjects );
			} elseif( $type = 'dead' ) {
				$tmp = DB::getConfiguration( $wiki, "wikiconfig", 'deadlink_tags_data' );
				if( !( $tmp instanceof CiteMap ) ) {
					$tmp = self::convertToObject( 'NONAME', $tmp, 'dead' );
					DB::setConfiguration( $wiki, "wikiconfig", 'deadlink_tags_data', $tmp );
				}

				self::$deadObject = $tmp;
			}
		}
		if( $type == 'cite' ) return self::$mapObjects;
		elseif( $type == 'archive' ) return self::$archiveObjects;
		elseif( $type == 'dead' ) return self::$deadObject;
		else return false;
	}

	protected static function convertToObjects( $data, $classification = 'cite' ) {
		$returnArray = [];
		foreach( $data as $key => $content ) {
			if( $key == 'template-list' ) {
				continue;
			} elseif( isset( $content['default-mapString'] ) ) {
				if( self::$wiki == $key ) {
					$returnArray['__GLOBAL__'] = new CiteMap( '__GLOBAL__' );
					$returnArray['__GLOBAL__']->loadMapString( $content['default-mapString'] );
					$returnArray['__TEMPLATE__'] = $content['default-template'];
					$returnArray['__TITLE__'] = $content['default-title'];
					$returnArray['__UPDATED__'] = 0;
				}
				continue;
			}
			if( isset( $content[self::$wiki] ) ) {
				$returnArray[$key] = self::convertToObject( $key, $content[self::$wiki], $classification );
			}
		}
		ksort( $returnArray );

		return $returnArray;
	}

	public static function updateMaps() {
		if( !self::$requireUpdate && time() - self::$lastUpdate < 900 ) return true;

		$noClear = false;

		self::updateDefaultObject();
		do {
			self::$requireUpdate = false;

			$templateLookup = [];
			foreach( self::$templateList as $template ) {
				$template         = trim( $template, '{}' );
				$template         = API::getTemplateNamespaceName() . ":$template";
				$templateLookup[] = $template;
			}
			$templatesExist = API::pagesExist( $templateLookup );

			foreach( $templatesExist as $template => $exists ) {
				$template = explode( ':', $template, 2 )[1];
				if( $exists ) self::registerMapObject( $template );
			}

			foreach( self::$mapObjects as $object ) {
				if( is_null( $object ) ) continue;
				$object->update( $noClear );
			}
			$noClear = true;
		} while( self::$requireUpdate );

		self::$lastUpdate = time();

		return self::saveMaps();
	}

	public static function saveMaps() {
		$return = true;
		if( !empty( self::$mapObjects ) ) {
			if( !is_null( self::$mapObjects ) ) foreach( self::$mapObjects as $name => $object ) {
				$return = $return && DB::setConfiguration( self::$wiki, "citation-rules", $name, $object );
			}
			if( !is_null( self::$globalObject ) ) $return =
				$return && DB::setConfiguration( self::$wiki, "citation-rules", '__GLOBAL__', self::$globalObject );
			if( !is_null( self::$globalObject ) ) $return =
				$return && DB::setConfiguration( self::$wiki, "citation-rules", '__UPDATED__', self::$lastUpdate );
			if( !is_null( self::$globalTemplate ) ) $return =
				$return && DB::setConfiguration( self::$wiki, "citation-rules", '__TEMPLATE__', self::$globalTemplate );
			if( !is_null( self::$globalTitle ) ) $return =
				$return && DB::setConfiguration( self::$wiki, "citation-rules", '__TITLE__', self::$globalTitle );
			if( !is_null( self::$templateList ) ) $return =
				$return && DB::setConfiguration( 'global', "citation-rules", 'template-list', self::$templateList );
		}

		if( !is_null( self::$archiveObjects ) ) foreach( self::$archiveObjects as $name => $object ) {
			$return = $return && DB::setConfiguration( 'global', "archive-templates", $name, $object );
		}
		if( !is_null( self::$deadObject ) ) $return =
			$return && DB::setConfiguration( self::$wiki, "wikiconfig", 'deadlink_tags_data', self::$deadObject );

		return $return;
	}

	public static function registerTemplate( $templateName ) {
		if( !in_array( $templateName, self::$templateList ) ) {
			self::$templateList[] = $templateName;
			sort( self::$templateList );
			self::$requireUpdate = true;

			return true;
		}

		return false;
	}

	public static function setDefaultTemplate( $templateName ) {
		if( in_array( $templateName, self::$templateList ) ) {
			self::$globalTemplate = trim( $templateName, '{}' );
			self::saveMaps();

			return true;
		} elseif( in_array( "{{{$templateName}}}", self::$templateList ) ) {
			self::$globalTemplate = $templateName;
			self::saveMaps();

			return true;
		}

		return false;
	}

	public static function getKnownTemplates() {
		//TODO: Remove me in future versions
		$forceSave = false;
		foreach( self::$templateList as $tid => $template ) {
			if( strpos( $template, '{{' ) !== 0 ) {
				$toRegister[] = "{{{$template}}}";
				unset(self::$templateList[$tid]);
				$forceSave = true;
			}
		}
		if( !empty( $toRegister ) ) foreach( $toRegister as $template ) {
			self::registerTemplate( $template );
		}

		if( $forceSave ) self::saveMaps();

		return self::$templateList;
	}

	public static function registerMapObject( $name ) {
		if( isset( self::$mapObjects[$name] ) ) return false;
		self::registerTemplate( "{{{$name}}}" );
		self::$mapObjects[$name] = new CiteMap( $name );
		ksort( self::$mapObjects );

		return true;
	}

	public static function unregisterMapObject( $name ) {
		if( isset( self::$mapObjects[$name] ) ) self::$mapObjects[$name] = null;
	}

	public static function unregisterArchiveObject( $name ) {
		self::$archiveObjects[$name] = null;
	}

	public static function registerArchiveObject( $name ) {
		if( isset( self::$archiveObjects[$name] ) ) return false;
		self::$archiveObjects[$name] = [
			'templatebehavior'           => "append",
			'archivetemplatedefinitions' => new CiteMap( $name, false, 'archive' )
		];
		ksort( self::$archiveObjects );

		return true;
	}

	public static function setArchiveBehavior( $name, $behavior = 'append' ) {
		if( !in_array( $behavior, [ 'append', 'swallow' ] ) ) return false;
		if( !isset( self::$archiveObjects[$name] ) ) return false;

		self::$archiveObjects[$name]['templatebehavior'] = $behavior;

		return true;
	}

	public static function getDefaultTitle() {
		return self::$globalTitle;
	}

	public static function setDefaultTitle( $title ) {
		if( !is_string( $title ) ) return false;
		self::$globalTitle = $title;

		return self::saveMaps();
	}

	public static function getDefaultTemplate() {
		return self::$globalTemplate;
	}

	public static function updateDefaultObject() {
		return self::$globalObject->update();
	}

	public static function setDefaultMap( $mapString ) {
		return self::$globalObject->loadMapString( $mapString );
	}

	public static function findMapObject( $templateName ) {
		$templateName = trim( $templateName, "{}" );

		$templateList = CiteMap::getKnownTemplates();

		if( !in_array( $templateName, $templateList ) ) {
			$templateName = API::getRedirectRoot( API::getTemplateNamespaceName() . ":$templateName" );
			$templateName = substr( $templateName, strlen( API::getTemplateNamespaceName() ) + 1 );
		}

		if( isset( self::$mapObjects[$templateName] ) ) {
			$templateObject = self::$mapObjects[$templateName];
		} else {
			// Sanity check in case somehow a template slipped detection.
			echo "Uh-oh '$templateName' isn't registered in the definitions\n";
			self::registerMapObject( $templateName );
			$templateObject = self::findMapObject( $templateName );
			$templateObject->update();
			echo "Registered '$templateName' and loaded defaults\n";
		}

		return $templateObject;
	}

	public function getValue( $serviceType, $templateParams, $resolveInternal = true ) {
		if( $param = $this->getParameter( $serviceType, $templateParams, false, $customValues ) ) {
			if( !is_array( $param ) ) {
				if( !$resolveInternal ) return $templateParams[$param];
				else {
					if( strpos( $serviceType, 'timestamp' ) !== false ) {
						return strtotime( $templateParams[$param] );
					}
					if( $serviceType == 'deadvalues' ) {

						$yes = explode( ';;', $customValues['valueyes'] );
						$no = explode( ';;', $customValues['valueyes'] );
						if( !empty( $customValues['valueusurp'] ) ) $usurp =
							explode( ';;', $customValues['valueusurp'] );
						else $usurp = $yes;

						if( in_array( $templateParams[$param], $yes ) ) return true;
						if( in_array( $templateParams[$param], $no ) ) return false;
						if( in_array( $templateParams[$param], $usurp ) ) return 'usurp';

						return null;
					}
					if( $serviceType == 'paywall' ) {
						if( !empty( $customValues['valueyes'] ) ) {
							$yes = explode( ';;', $customValues['valueyes'] );
							$no = explode( ';;', $customValues['valueno'] );
						} else {
							$sub = explode( ';;', $customValues['valuesub'] );
							$reg = explode( ';;', $customValues['valuereg'] );
							$lim = explode( ';;', $customValues['valuelim'] );
							$free = explode( ';;', $customValues['valuefree'] );
						}

						if( in_array( $templateParams[$param], $yes ) ) return true;
						if( in_array( $templateParams[$param], $no ) ) return false;
						if( in_array( $templateParams[$param], $sub ) ) return 'sub';
						if( in_array( $templateParams[$param], $reg ) ) return 'reg';
						if( in_array( $templateParams[$param], $lim ) ) return 'lim';
						if( in_array( $templateParams[$param], $free ) ) return 'free';

						return null;
					}
					if( $serviceType == 'permadead' ) {
						$yes = explode( ';;', $customValues['valueyes'] );
						$no = explode( ';;', $customValues['valueno'] );

						if( in_array( $templateParams[$param], $yes ) ) return true;
						if( in_array( $templateParams[$param], $no ) ) return false;

						return null;
					}
				}
			} else {
				$value = $param['value'];
				$params = $param['using_params'];
				foreach( $params as $param ) {
					return $this->fillVariables( $params, $value );
				}
			}
		} else return false;
	}

	public function getParameter( $serviceType, $templateParams, $returnDefault = false, &$serviceValues = [],
	                              $useService = false
	) {
		$default = false;

		if( $this->redirected ) {
			$this->loadRedirectTarget();
			$activeObject = self::$redirectTargetObjects[$this->redirected][$this->informalName];

		} else {
			$activeObject = $this;
		}
		$map = $activeObject->getMap();

		$defaultService = $map['services']['@default'];
		$services       = $map['services'];
		unset( $services['@default'] );
		$reArrangedServices             = $services;
		$reArrangedServices['@default'] = $defaultService;
		unset( $services, $defaultService );
		foreach( $reArrangedServices as $tService => $subset ) {
			if( $useService && !in_array( $tService, [ '@default', $useService ] ) ) continue;
			if( isset( $subset[$serviceType] ) ) {
				foreach( $subset[$serviceType] as $junk => $serviceSub ) {
					if( is_array( $serviceSub ) ) {
						$dataID        = $serviceSub['index'];
						$serviceValues = $serviceSub;
						unset( $serviceValues['index'] );
					} else {
						$dataID        = $serviceSub;
						$serviceValues = false;
					}
					foreach( $map['data'][$dataID]['mapto'] as $paramID ) {
						if( $default === false && !$useService && $tService == '@default' ) {
							$default =
								$map['params'][$paramID];
						}
						if( $default === false && $useService == $tService ) $default = $map['params'][$paramID];
						if( isset( $templateParams[$map['params'][$paramID]] ) ) {
							return $map['params'][$paramID];
						}
					}
				}
			} elseif( isset( self::$services[$tService][$serviceType] ) ) {
				$params = [];
				$value = self::$services[$tService][$serviceType]['value'];
				foreach( self::$services[$tService][$serviceType]['requires'] as $requiredType ) {
					$tParam = $this->getParameter( $requiredType, $templateParams, $returnDefault, $junk, $tService );
					if( $tParam === false ) return false;
					$params[$requiredType] = $tParam;
				}

				return [ 'value' => $value, 'using_params' => $params ];
			}
		}

		if( $returnDefault ) return $default;
		else return false;
	}

	protected function loadRedirectTarget() {
		if( $this->redirected && !isset( self::$redirectTargetObjects[$this->redirected][$this->informalName] ) ) {
			self::$redirectTargetObjects[$this->redirected][$this->informalName] =
				DB::getConfiguration( $this->redirected, 'citation-rules', $this->informalName );
			if( is_null( self::$redirectTargetObjects[$this->redirected][$this->informalName] ) ) return false;
			if( !( self::$redirectTargetObjects[$this->redirected][$this->informalName] instanceof CiteMap ) ) {
				self::$redirectTargetObjects[$this->redirected][$this->informalName] =
					self::convertToObject( $this->informalName,
					                       self::$redirectTargetObjects[$this->redirected][$this->informalName], 'cite'
					);
			}
		}

		return true;
	}

	public function getMap() {
		return $this->map;
	}

	public function fillVariables( $linkDetails, $string ) {
		switch( $string ) {
			case "{epochbase62}":
			case "{epoch}":
			case "{microepochbase62}":
			case "{microepoch}":
				$type = 'archive_date';
				$customValues = [ 'type' => trim( $string, '{} ' ) ];
				break;
			default:
				$type = self::seekServiceType( $string );
				if( $type !== false ) {
					$customValues = $type['customValues'];
					$type = $type['type'];
				} elseif( strpos( $string, '{timestamp:' ) === 0 ) {
					if( preg_match( '/\{.*?\}/', $string, $variable ) && $variable[0] !== $string ) {
						$string = str_replace( $variable[0], $this->fillVariables( $linkDetails, $variable[0] ) );
					} else {
						$type = 'timestamp';
						$customValues['format'] = substr( $string, 11, strlen( $string ) - 12 );
					}
				}
				break;
		}

		if( $type == false ) {
			while( preg_match( '/\{.*?\}/', $string, $variable ) ) {
				$string = str_replace( $variable[0], $this->fillVariables( $linkDetails, $variable[0] ) );
			}

			return $string;
		} else {
			if( $customValues === false ) {
				if( isset( $linkDetails[$type] ) ) return $linkDetails[$type];
				else return $string;
			} else {
				if( strpos( $string, 'timestamp' ) !== false ) {
					$format = $customValues['format'];
					if( $format == 'automatic' ) $format = self::$generatorObject->retrieveDateFormat( true );
					switch( $type ) {
						case 'timestamp':
							$timestamp = time();
							break;
						case 'archive_date':
							if( !isset( $linkDetails['archive_time'] ) ) return $string;
							else $timestamp = $linkDetails['archive_time'];
							break;
						case 'access_date':
							if( !isset( $linkDetails['access_time'] ) ) return $string;
							else $timestamp = $linkDetails['access_time'];
							break;
					}
					$value = DataGenerator::strftime( $format, $timestamp );

					return $value;
				}
				if( strpos( $string, 'epoch' ) !== false ) {
					if( strpos( $string, 'micro' ) !== false ) {
						if( !isset( $linkDetails['mirco_archive_time'] ) ) return $string;
						$value = $linkDetails['mirco_archive_time'];
					} else {
						$value = $linkDetails['archive_time'];
					}
					if( strpos( $string, 'base62' ) !== false ) {
						$value = API::toBase( $value, 62 );
					}

					return $value;
				}
				if( $type == 'deadvalues' ) {
					if( ( $linkDetails['tagged_dead'] === true || $linkDetails['is_dead'] === true ) ) {
						$useValue = "yes";
					} elseif( ( $linkDetails['has_archive'] === true && $linkDetails['archive_type'] == "invalid" ) ||
					          $linkDetails['link_type'] == "stray" ) {
						$useValue = "usurp";
					} else {
						$useValue = "no";
					}

					$yes = explode( ';;', $customValues['valueyes'] );
					$no = explode( ';;', $customValues['valueyes'] );
					if( !empty( $customValues['valueusurp'] ) ) $usurp = explode( ';;', $customValues['valueusurp'] );
					else $usurp = $yes;
					if( !empty( $customValues['defaultvalue'] ) ) $default = $customValues['defaultvalue'];
					else $default = 'yes';

					if( empty( $useValue ) ) $useValue = $default;

					switch( $useValue ) {
						case 'yes':
							return $yes[0];
						case 'no':
							return $no[0];
						case 'usurp':
							return $usurp[0];
						default:
							return "";
					}
				}
				if( $type == 'paywall' ) {
					if( !empty( $customValues['valueyes'] ) ) {
						$yes = explode( ';;', $customValues['valueyes'] );
						$no = explode( ';;', $customValues['valueno'] );
					} else {
						$sub = explode( ';;', $customValues['valuesub'] );
						$reg = explode( ';;', $customValues['valuereg'] );
						$lim = explode( ';;', $customValues['valuelim'] );
						$free = explode( ';;', $customValues['valuefree'] );
					}

					if( $linkDetails['tarbwall'] === true ) {
						if( isset( $yes ) ) {
							return $yes[0];
						} else {
							return $reg[0];
						}
					}

					if( isset( $no ) ) {
						return $no[0];
					} else {
						return $free[0];
					}
				}
				if( $type == 'permadead' ) {
					$yes = explode( ';;', $customValues['valueyes'] );
					$no = explode( ';;', $customValues['valueno'] );

					if( $linkDetails['permanent_dead'] ) return $yes[0];
					else return $no[0];
				}
			}
		}
	}

	public function setValue( $serviceType, $linkDetails, &$templateParams, $value = false, $service = false ) {
		if( $value ) {
			$templateParams[$this->getParameter( $serviceType, $templateParams, true, $junk, $service )] =
				$this->fillVariables( $linkDetails, $value );

			return true;
		}

		$toCheck = false;

		if( !empty( $linkDetails['newdata']['archive_host'] ) ) $toCheck = $linkDetails['newdata']['archive_host'];
		elseif( !empty( $linkDetails['archive_host'] ) ) $toCheck = $linkDetails['archive_host'];

		if( $toCheck && isset( self::$services[$toCheck][$serviceType] ) ) {
			$templateParamsB = $templateParams;
			if( $this->resolveServiceValues( $linkDetails, "@{$linkDetails['archive_host']}", $serviceType,
			                                 $templateParams
			) ) {
				return true;
			}
			$templateParams = $templateParamsB;
		}

		$param = $this->getParameter( $serviceType, $templateParams, true, $serviceValues, $service );

		if( !$param ) return false;

		$templateParams[$param] = $this->fillVariables( $linkDetails, $serviceValues['string'] );

		return true;

	}

	public function resolveServiceValues( $linksDetails, $service, $serviceType, &$templateParams = [] ) {
		if( isset( self::$services[$service][$serviceType] ) ) {
			$value = self::$services[$service][$serviceType]['value'];
			$requires = self::$services[$service][$serviceType]['requires'];

			preg_match_all( '/\{.*?\}/', $value, $variables );

			$returnArray = [];

			if( $this->redirected ) {
				$this->loadRedirectTarget();
				$activeObject = self::$redirectTargetObjects[$this->redirected][$this->informalName];

			} else {
				$activeObject = $this;
			}
			$map = $activeObject->getMap();

			foreach( $requires as $requiredType ) {
				if( isset( $map['services'][$service][$serviceType] ) ) {
					if( !is_array( $map['services'][$service][$serviceType] ) ) $dataID =
						$map['services'][$service][$serviceType];
					else {
						$dataID = $map['services'][$service][$serviceType]['index'];
						$serviceValues = $map['services'][$service][$serviceType]['values'];
					}
				} elseif( isset( $map['services']['@default'][$serviceType] ) ) {
					if( !is_array( $map['services']['@default'][$serviceType] ) ) $dataID =
						$map['services'][$service][$serviceType];
					else {
						$dataID = $map['services'][$service]['@default']['index'];
						$serviceValues = $map['services'][$service][$serviceType]['values'];
					}
				} else return false;

				if( !isset( $serviceValues ) ) $serviceValues = [];

				$dataset = $map['data'][$dataID];

				$templateParams[$this->getParameter( $requiredType, $templateParams, true, $serviceValues, $service )] =
					$this->fillVariables( $linksDetails, $dataset['string'] );
				$returnArray[$requiredType] = $this->fillVariables( $linksDetails, $dataset['string'] );
			}

			return $returnArray;
		} else {
			return false;
		}
	}

	public function getLuaLocation() {
		return $this->luaLocation;
	}

	public function isUsingGlobal() {
		return empty( $this->string );
	}

	public function renderMap() {
		$returnArray = [];

		if( $this->redirected ) {
			$this->loadRedirectTarget();
			$activeObject = self::$redirectTargetObjects[$this->redirected][$this->informalName];

		} else {
			$activeObject = $this;
		}
		$map = $activeObject->getMap();

		if( !is_array( $map ) ) return false;
		if( isset( $map['services'] ) ) foreach( $map['services'] as $servicename => $service ) {
			$string = "{{{$this->informalName}|";
			$tout = [];
			foreach( $map['data'] as $id => $subData ) {
				$tString = "";
				if( isset( $subData['serviceidentifier'] ) &&
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
					$tString .= $map['params'][$paramIndex] . "=";
				}
				if( $counter > 1 ) {
					$tString .= "]";
				}
				$tString .= $subData['valueString'];
				$tout[] = $tString;
			}
			$string .= implode( "|", $tout );
			$string .= "}}";
			$returnArray[$servicename] = $string;
		}
		if( empty( $returnArray ) ) $returnArray['@default'] = "{{{$this->formalName}}}";

		return $returnArray;
	}

	public function isDisabled() {
		if( $this->map === false ) return true;
		if( empty( $this->map['services'] ) ) return true;

		return $this->disabled;
	}

	public function isDisabledByUser() {
		return $this->disabledByUser;
	}

	public function clearAssertions() {
		$this->assertRequirements = [];

		return true;
	}

	public function addAssertion( $serviceType, $service = '__NONE__' ) {
		if( !in_array( $serviceType, $this->assertRequirements[$service] ) ) {
			$this->assertRequirements[$service][] = $serviceType;

			return true;
		} else return false;
	}

	public function getString() {
		if( !$this->string ) return "";

		return $this->string;
	}

	public function update( $noClear = false ) {
		if( !$noClear ) $this->clearMap();

		$this->buildMap();

		$this->isRedirectOnWiki();

		if( $this->disabled && !$this->disabledByUser && $this->informalName != '__GLOBAL__' ) self::unregisterMapObject( $this->informalName );

		return true;
	}

	public function isRedirectOnWiki( $target = false ) {

		$redirects = false;

		if( !$target ) $page = $this->formalName;
		else $page = $target;

		while( ( $destination = API::getRedirectRoot( $page ) ) != $page ) {
			if( $target === false ) $this->disabled = true;

			$nextDestinationT = explode( ':', $destination );
			if( count( $nextDestinationT ) === 1 ) $nextDestinationT = $nextDestinationT[0];
			else $nextDestinationT = $nextDestinationT[1];
			self::registerTemplate( "{{{$nextDestinationT}}}" );

			$redirects = $page = $destination;
		}

		return $redirects;
	}

	protected function enableMap() {
		$this->disabled = false;
		$this->disabledByUser = false;

		return true;
	}

}