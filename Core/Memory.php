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
 * Memory object
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */

/**
 * Memory class
 * A memory handler for storing and reading large variables and sharing them with other processes.
 * @author Maximilian Doerr (Cyberpower678)
 * @license https://www.gnu.org/licenses/gpl.txt
 * @copyright Copyright (c) 2015-2018, Maximilian Doerr
 */
class Memory {
	protected static $memoryStore;
	protected $filePath;
	protected $fileSize;
	protected $readSharable;
	protected $isShared;
	protected $isWritable;
	protected $fileContents;
	protected $resourceHandle;

	public function __construct( $value, $identifier = false, $preserve = false, $shared = false ) {
		$this->isShared = (bool) $shared;

		if( !is_resource( $value ) ) for( $i = 0; $i < 20; $i++ ) {
			if( $this->open( $identifier, (bool) $preserve ) ) break;
		} else {
			echo "WARNING: Resources cannot be stored.\n";
		}

		if( !$this->filePath ) {
			echo "The values being assigned to this object cannot be saved into a file.  They will be retained in memory.\n";

			$old = ini_set( 'memory_limit', '1G' );
			if( $old != '1G' ) echo "Memory limit has been raised to 1 GB\n";

			if( $shared ) {
				echo "ERROR: This is a designated shared object, but it cannot be shared\n";
			}

			$this->fileContents = $value;
			$this->fileSize = strlen( serialize( $value ) );
			$this->readSharable = false;
			$this->isShared = false;
			$this->isWritable = true;
			unset( $value );
		} else {
			if( $this->isWritable ) {
				$this->set( $value );
			} elseif( $this->isShared ) {
				echo "A shared file is being accessed, provided value will be discarded.\n";
			} else {
				echo "An unexpected initialization error occurred.  The value will be discarded.\n";
			}
			unset( $value );
		}

		self::$memoryStore[] = $this;
	}

	protected function open( $identifier, $preserve ) {
		if( $identifier ) {
			$fileName = "$identifier-";
			if( $this->isShared ) $fileName .= "1";
			else $fileName .= "0";
		} else {
			$fileName =
				WIKIPEDIA . '-' . USERNAME . '-' . UNIQUEID . '-' . md5( microtime( true ) ) . '-' . microtime( true );
		}

		return $this->openHandle( IAPROGRESS . $fileName, $preserve );
	}

	protected function openHandle( $fullPath, $preserve ) {
		if( IAVERBOSE ) echo "Attempting to open '$fullPath'\n";

		$this->resourceHandle = @fopen( $fullPath, "c+" );

		if( !is_resource( $this->resourceHandle ) ) {
			echo "Can't open file handle for '$fullPath'\n";
		} else {
			if( !flock( $this->resourceHandle, LOCK_EX | LOCK_NB, $isAlreadyLocked ) ) {
				if( $isAlreadyLocked ) {
					if( $this->isShared ) {
						if( !flock( $this->resourceHandle, LOCK_SH | LOCK_NB, $noShare ) ) {
							echo "Unable to access '$fullPath' for reading\n";
							@fclose( $this->resourceHandle );

							return false;
						} else {
							$this->isWritable = false;
							$this->fileSize = filesize( $fullPath );
							$this->fileContents = null;
							$this->readSharable = true;
							$this->isShared = true;
							$this->filePath = $fullPath;
							if( IAVERBOSE ) echo "Opened '$fullPath'\n";

							return true;
						}
					} else {
						echo "Unable to access '$fullPath' for writing because the file is owned by another process and isn't shared\n";
						@fclose( $this->resourceHandle );

						return false;
					}
				} else {
					echo "Unable to access '$fullPath' for writing because an unknown error occurred.\n";
					@fclose( $this->resourceHandle );

					return false;
				}
			} else {
				if( !$preserve ) {
					ftruncate( $this->resourceHandle, 0 );
				} else {
					fseek( $this->resourceHandle, 0, SEEK_END );
				}
				$this->isWritable = true;
				$this->fileContents = null;
				$this->fileSize = filesize( $fullPath );
				$this->readSharable = true;
				$this->isShared = false;
				$this->filePath = $fullPath;

				if( IAVERBOSE ) echo "Opened '$fullPath'\n";

				return true;
			}
		}
	}

	public function set( $value ) {
		if( $this->isWritable ) {
			$this->fileContents = null;
			if( $this->readSharable ) {
				if( IAVERBOSE ) echo "Writing to '{$this->filePath}\n";
				ftruncate( $this->resourceHandle, 0 );
				if( fwrite( $this->resourceHandle, serialize( $value ) ) ) {
					$this->fileSize = filesize( $this->filePath );
					if( IAVERBOSE ) echo "Successful write to '{$this->filePath}\n";

					return true;
				} else {
					echo "ERROR: Failed to write to '{$this->filePath}\n";
				}
			} else {
				$this->fileContents = $value;

				return true;
			}
		} else return false;
	}

	public static function destroyStore() {
		if( is_array( self::$memoryStore ) ) foreach( self::$memoryStore as $store ) {
			$store->__destruct();
		}

		self::$memoryStore = [];
	}

	public static function clean() {
		echo "Cleaning up unused memory files...\n";

		$dh = @opendir( IAPROGRESS );

		if( !$dh ) {
			echo "Unable to open '" . IAPROGRESS . "' for reading.  The path may not exist.\n";

			return false;
		}

		while( ( $file = readdir( $dh ) ) !== false ) {
			if( $file == "." || $file == ".." ) continue;

			if( is_dir( IAPROGRESS . $file ) ) continue;

			$fh = @fopen( IAPROGRESS . $file, 'r+' );
			if( !$fh ) {
				if( IAVERBOSE ) echo "Unable to open '" . IAPROGRESS . $file . "'. It may have already been deleted.\n";
				continue;
			}

			if( !flock( $fh, LOCK_EX | LOCK_NB, $locked ) ) {
				if( $locked ) {
					if( IAVERBOSE ) echo "'" . IAPROGRESS . $file . "' is in use\n";
					@fclose( $fh );
					continue;
				} else {
					echo "Lock test error occurred for '" . IAPROGRESS . $file . "'\n";
					@fclose( $fh );
					continue;
				}
			} else {
				flock( $fh, LOCK_UN );
				@fclose( $fh );
				@unlink( IAPROGRESS . $file );
				if( IAVERBOSE ) echo "Deleted '" . IAPROGRESS . "$file'\n";
			}
		}
	}

	public function get( $noCache = false ) {
		if( is_null( $this->fileContents ) && $this->readSharable ) {
			fseek( $this->resourceHandle, 0 );
			while( !feof( $this->resourceHandle ) ) {
				if( ( $tmp = fgets( $this->resourceHandle ) ) === false ) {
					echo "READ ERROR: Cannot get stored value\n";
					$this->fileContents = null;
					@fclose( $this->resourceHandle );
					$this->readSharable = false;
					$this->isShared = false;
					unlink( $this->filePath );
					$this->filePath = null;
					break;
				} else {
					$this->fileContents .= $tmp;
				}
			}

			if( !$noCache ) $this->fileContents = unserialize( $this->fileContents );
			else {
				$tmp = unserialize( $this->fileContents );
				$this->fileContents = null;

				return $tmp;
			}
		}

		return $this->fileContents;
	}

	public function __destruct() {
		if( is_resource( $this->resourceHandle ) ) {
			if( IAVERBOSE ) echo "Closing '{$this->filePath}\n";
			flock( $this->resourceHandle, LOCK_UN );
			@fclose( $this->resourceHandle );
		}
		if( IAVERBOSE ) echo "Deleting '{$this->filePath}\n";
		if( file_exists( $this->filePath ) ) unlink( $this->filePath );
		if( IAVERBOSE ) echo "Purging memory\n";
		unset( $this->fileContents );
	}
}
