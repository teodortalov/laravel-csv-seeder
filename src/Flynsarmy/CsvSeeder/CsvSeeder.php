<?php namespace Flynsarmy\CsvSeeder;

use Log;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Taken from http://laravelsnippets.com/snippets/seeding-database-with-csv-files-cleanly
 * and modified to include insert chunking
 */
class CsvSeeder extends Seeder
{

	/**
	 * DB table name
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * CSV filename
	 *
	 * @var string
	 */
	protected $filename;

	/**
	 * DB field that to be hashed, most likely a password field. 
	 * If your password has a different name, please overload this 
	 * variable from our seeder class.
	 * 
	 * @var string
	 */
	
	protected $hashable = 'password';
	/**
	 * An SQL INSERT query will execute every time this number of rows
	 * are read from the CSV. Without this, large INSERTS will silently
	 * fail.
	 *
	 * @var integer
	 */
	protected $insert_chunk_size = 50;

	/**
	 * CSV delimiter (defaults to ,)
	 *
	 * @var string
	 */
	protected $csv_delimiter = ',';



	/**
	 * Run DB seed
	 */
	public function run()
	{
		$this->seedFromCSV($this->filename, $this->csv_delimiter);
	}

	/**
	 * Strip UTF-8 BOM characters from the start of a string
	 *
	 * @param  string $text
	 *
	 * @return string       String with BOM stripped
	 */
	private function strip_utf8_bom( $text )
	{
		$bom = pack('H*','EFBBBF');
		$text = preg_replace("/^$bom/", '', $text);
		return $text;
	}

	/**
	 * Collect data from a given CSV file and return as array
	 *
	 * @param $filename
	 * @param string $deliminator
	 * @return array|bool
	 */
	private function seedFromCSV($filename, $deliminator = ",")
	{
		if( !file_exists($filename) || !is_readable($filename) )
			return FALSE;

		$header = NULL;
		$row_count = 0;
		$data = array();

		if ( ($handle = fopen($filename, 'r')) !== FALSE )
		{
			while ( ($row = fgetcsv($handle, 0, $deliminator)) !== FALSE )
			{
				if ( !$header )
				{
					$header = $row;
					$header[0] = $this->strip_utf8_bom($header[0]);
				}
				else
				{
				  
				  //Hash hashable field if it exists
				  $row = array_combine($header, $row);
				  if(isset($row[$this->hashable])){
				    $row[$this->hashable] =  Hash::make($row[$this->hashable]);
				  }
					$data[] = $row;

					// Chunk size reached, insert
					if ( ++$row_count == $this->insert_chunk_size )
					{
						$this->run_insert($data);
						$data = array();
						$row_count = 0;
					}
				}
			}

			// Insert any leftover rows
			if ( $row_count )
				$this->run_insert($data);

			fclose($handle);
		}

		return $data;
	}

	private function run_insert( array $seedData )
	{
		try {
			DB::table($this->table)->insert($seedData);
		} catch (\Exception $e) {
			Log::error("CSV insert failed: " . $e->getMessage() . " - CSV " . $this->filename);
		}

	}

}
