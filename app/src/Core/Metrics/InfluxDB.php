<?php

/*
	Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	InternetArchiveBot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with InternetArchiveBot.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
*/

class InfluxDB implements MetricsDriver {

	protected bool $usingDummy = false;

	protected int $lastFlush;

	protected int $cachedPoints;

	protected array $dataPoints;

	protected bool $usingLegacy;

	protected object $client;

	protected object $writer;

	protected array $configuration;

	protected MetricsDriver $dummy;

	protected bool $allowExceptions;

	protected int $dataPointLimit;

	protected int $flushPeriod;

	public function __construct( $config = [] ) {
		$this->configuration = $config;

		if( class_exists( "Dummy" ) ) {
			$this->dummy = new Dummy();
		}

		if( !empty( $config['legacy'] ) && $config['legacy'] === true ) $this->usingLegacy = true;
		else $this->usingLegacy = false;
	}

	public function initialize( $exceptOnFail = false ): bool {
		$this->allowExceptions = $exceptOnFail;

		try {
			if( $this->usingLegacy ) {
				$this->client = new \InfluxDB\Client( $this->configuration['host'],
					$this->configuration['port'],
					$this->configuration['user'],
					$this->configuration['password'],
					isset( $this->configuration['ssl'] ) &&
					is_bool( $this->configuration['ssl']
					) &&
					$this->configuration['ssl'],
					!( isset( $this->configuration['verify_ssl'] ) &&
					   is_bool( $this->configuration['verify_ssl']
					   ) ) ||
					$this->configuration['verify_ssl'], 5, 10

				);

				$this->writer = $this->client->selectDB( $this->configuration['db'] );
			} else {
				$params = [];

				if( isset( $this->configuration['ssl'] ) && $this->configuration['ssl'] === true ) $params['url'] =
					"https://";
				else $params['url'] = "http://";

				$params['url'] .= $this->configuration['host'];
				$params['url'] .= ":{$this->configuration['port']}";

				if( !empty( $this->configuration['token'] ) ) $params['token'] = $this->configuration['token'];

				if( !empty( $this->configuration['bucket'] ) ) $params['bucket'] =
					(string) $this->configuration['bucket'];
				elseif( !empty( $this->configuration['db'] ) ) $params['bucket'] = (string) $this->configuration['db'];

				if( isset( $this->configuration['org'] ) ) $params['org'] = (string) $this->configuration['org'];
				else $params['org'] = '';

				$params['precision'] = \InfluxDB2\Model\WritePrecision::US;

				if( !empty( $this->configuration['verify_ssl'] ) && is_bool( $this->configuration['verify_ssl'] ) )
					$params['verify_ssl'] = $this->configuration['verify_ssl'];

				$params['timeout'] = 5;

				$this->client = new \InfluxDB2\Client( $params );

				$this->writer = $this->client->createWriteApi();
			}
		} catch( Exception $e ) {
			if( !$exceptOnFail ) {
				if( $this->dummy instanceof MetricsDriver ) {
					$this->usingDummy = true;

					return false;
				} else throw $e;
			} else throw $e;
		}

		$this->lastFlush = time();
		$this->cachedPoints = 0;
		$this->dataPoints = [];

		$this->usingDummy = false;

		$this->setFlushInterval();

		return true;
	}

	public function createEntry( float $microtime, $attributesArray ): bool {
		if( $this->usingDummy ) return $this->dummy->createEntry( $microtime, $attributesArray );

		try {
			if( $this->usingLegacy ) {
				$point = new \InfluxDB\Point(
					$attributesArray['name'],
					null,
					$attributesArray['group_fields'],
					$attributesArray['aggregation_fields'],
					(int) ( $microtime * 1000000 )
				);
			} else {
				$point = \InfluxDB2\Point::measurement( $attributesArray['name'] )->time( (int) ( $microtime * 1000000 ),
				                                                                          \InfluxDB2\Model\WritePrecision::US
				);

				foreach( $attributesArray['group_fields'] as $name => $value ) {
					$point = $point->addTag( $name, (string) $value );
				}

				foreach( $attributesArray['aggregation_fields'] as $name => $value ) {
					$point = $point->addField( $name, $value );
				}
			}
			$this->dataPoints[] = $point;
			$this->cachedPoints++;

		} catch( Exception $e ) {
			if( !$this->allowExceptions ) {
				if( $this->dummy instanceof MetricsDriver ) {
					return false;
				} else throw $e;
			} else throw $e;
		}

		if( $this->readyToFlush() ) $this->flushEntries();

		return true;
	}

	public function setFlushInterval( int $seconds = 300, int $entryLimit = 1000 ) {
		$this->dataPointLimit = $entryLimit;
		$this->flushPeriod = $seconds;
	}

	public function flushEntries(): bool {
		if( $this->usingDummy ) return $this->dummy->flushEntries();

		try {
			if( $this->usingLegacy ) {
				$this->writer->writePoints( $this->dataPoints, InfluxDB\Database::PRECISION_MICROSECONDS );
			} else {
				$this->writer->write( $this->dataPoints );
			}

			$this->dataPoints = [];
			$this->cachedPoints = 0;
			$this->lastFlush = time();

		} catch( Exception $e ) {
			if( !$this->allowExceptions ) {
				if( $this->dummy instanceof MetricsDriver ) {
					return false;
				} else throw $e;
			} else throw $e;
		}

		return true;
	}

	public function purgeEntries(): bool {
		$this->cachedPoints = 0;
		$this->dataPoints = [];

		return true;
	}

	public function readyToFlush(): bool {
		if( $this->dataPointLimit > 0 && $this->cachedPoints >= $this->dataPointLimit ) return true;
		if( $this->flushPeriod > 0 && time() >= $this->lastFlush + $this->flushPeriod ) return true;

		return false;
	}

	public function getLatestPoints( $metricName ) {
		try {
			if( $this->usingLegacy ) {
				$queryString = "select * from $metricName WHERE time > NOW() - 30m;";
				$result = $this->writer->query( $queryString );
				$points = $result->getPoints();

				return $points;
			} else {
				$queryHandler = $this->client->createQueryApi();

				$queryString = "from(bucket: params.bucketParam) |> range(start: duration(v: params.startParam))";
				$query = new InfluxDB2\Model\Query();
				$query->setQuery( $queryString );
				$query->setParams( [ "bucketParam" => $this->configuration['bucket'], "startParam" => "-30m" ] );

				$tables = $queryHandler->query( $query );

				foreach( $tables as $table ) {
					return $table->records;
				}
			}
		} catch( Exception $e ) {
			if( !$this->allowExceptions ) {
				if( $this->dummy instanceof MetricsDriver ) {
					return false;
				} else throw $e;
			} else throw $e;
		}

		return [];
	}
}