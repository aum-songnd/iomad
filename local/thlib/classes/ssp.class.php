<?php

/*
 * Helper functions for building a DataTables server-side processing SQL query
 *
 * The static functions in this class are just helper functions to help build
 * the SQL used in the DataTables demo server-side processing scripts. These
 * functions obviously do not represent all that can be done with server-side
 * processing, they are intentionally simple to show how it works. More complex
 * server-side processing operations will likely require a custom script.
 *
 * See http://datatables.net/usage/server-side for full details on the server-
 * side processing requirements of DataTables.
 *
 * @license MIT - http://datatables.net/license_mit
 */

class SSP {
	/**
	 * Create the data output array for the DataTables rows
	 *
	 *  @param  array $columns Column information array
	 *  @param  array $data    Data from the SQL get
	 *  @return array          Formatted data in a row based format
	 */
	static function data_output($columns, $data) {
		$out = array();

		for ($i = 0, $ien = count($data); $i < $ien; $i++) {
			$row = array();

			for ($j = 0, $jen = count($columns); $j < $jen; $j++) {
				$column = $columns[$j];

				// Is there a formatter?
				if (isset($column['formatter'])) {
					if (empty($column['db'])) {
						$row[$column['dt']] = $column['formatter']($data[$i]);
					} else {
						$row[$column['dt']] = $column['formatter']($data[$i][$column['db']], $data[$i]);
					}
				} else {
					if (!empty($column['db'])) {
						$row[$column['dt']] = $data[$i][$columns[$j]['db']];
					} else {
						$row[$column['dt']] = "";
					}
				}
			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Paging
	 *
	 * Construct the LIMIT clause for server-side processing SQL query
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array $columns Column information array
	 *  @return string SQL limit clause
	 */
	static function limit($request, $columns) {
		$limit = '';

		if (isset($request['start']) && $request['length'] != -1) {
			$limit = "LIMIT " . intval($request['start']) . ", " . intval($request['length']);
		}

		return $limit;
	}

	/**
	 * Ordering
	 *
	 * Construct the ORDER BY clause for server-side processing SQL query
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array $columns Column information array
	 *  @return string SQL order by clause
	 */
	static function order($request, $columns) {
		$order = '';

		if (isset($request['order']) && count($request['order'])) {
			$orderBy = array();
			$dtColumns = self::pluck($columns, 'dt');

			for ($i = 0, $ien = count($request['order']); $i < $ien; $i++) {
				// Convert the column index into the column data property
				$columnIdx = intval($request['order'][$i]['column']);
				$requestColumn = $request['columns'][$columnIdx];

				$columnIdx = array_search($requestColumn['data'], $dtColumns);
				$column = $columns[$columnIdx];

				if ($requestColumn['orderable'] == 'true') {
					$dir = $request['order'][$i]['dir'] === 'asc' ?
					'ASC' :
					'DESC';

					$orderBy[] = '`' . $column['db'] . '` ' . $dir;
				}
			}

			if (count($orderBy)) {
				$order = 'ORDER BY ' . implode(', ', $orderBy);
			}
		}

		return $order;
	}

	/**
	 * Searching / Filtering
	 *
	 * Construct the WHERE clause for server-side processing SQL query.
	 *
	 * NOTE this does not match the built-in DataTables filtering which does it
	 * word by word on any field. It's possible to do here performance on large
	 * databases would be very poor
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array $columns Column information array
	 *  @param  array $bindings Array of values for PDO bindings, used in the
	 *    sql_exec() function
	 *  @return string SQL where clause
	 */
	static function filter($request, $columns) {
		$globalSearch = array();
		$columnSearch = array();
		$dtColumns = self::pluck($columns, 'dt');

		if (isset($request['search']) && $request['search']['value'] != '') {
			$str = $request['search']['value'];

			for ($i = 0, $ien = count($request['columns']); $i < $ien; $i++) {
				$requestColumn = $request['columns'][$i];
				$columnIdx = array_search($requestColumn['data'], $dtColumns);
				$column = $columns[$columnIdx];

				if ($requestColumn['searchable'] == 'true') {
					if (!empty($column['db'])) {
						if ($column['db'] != 'stt') {
							$globalSearch[] = $column['db'] . " LIKE '%" . $str . "%'";
						}
					}
				}
			}
		}

		// Individual column filtering
		if (isset($request['columns'])) {
			for ($i = 0, $ien = count($request['columns']); $i < $ien; $i++) {
				$requestColumn = $request['columns'][$i];
				$columnIdx = array_search($requestColumn['data'], $dtColumns);
				$column = $columns[$columnIdx];

				$str = $requestColumn['search']['value'];

				if ($requestColumn['searchable'] == 'true' &&
					$str != '') {
					if (!empty($column['db'])) {
						if ($column['db'] != 'stt') {
							$columnSearch[] = $column['db'] . " LIKE '%" . $str . "%'";
						}
					}
				}
			}
		}

		// Combine the filters into a single string
		$where = '';

		if (count($globalSearch)) {
			$where = '(' . implode(' OR ', $globalSearch) . ')';
		}

		if (count($columnSearch)) {
			$where = $where === '' ?
			implode(' AND ', $columnSearch) :
			$where . ' AND ' . implode(' AND ', $columnSearch);
		}

		if ($where !== '') {
			$where = 'WHERE ' . $where;
		}

		return $where;
	}

	/**
	 * Perform the SQL queries needed for an server-side processing requested,
	 * utilising the helper functions of this class, limit(), order() and
	 * filter() among others. The returned array is ready to be encoded as JSON
	 * in response to an SSP request, or can be modified if needed before
	 * sending back to the client.
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array|PDO $conn PDO connection resource or connection parameters array
	 *  @param  string $table SQL table to query
	 *  @param  string $primaryKey Primary key of the table
	 *  @param  array $columns Column information array
	 *  @return array          Server-side processing response array
	 */
	static function simple($request, $table, $primaryKey, $columns) {

		require_once '../../../config.php';

		global $DB;
		$prefix = $DB->get_prefix();

		// Build the SQL query string from the request
		$limit = self::limit($request, $columns);
		$order = self::order($request, $columns);
		$where = self::filter($request, $columns);

		$fields = implode(",", self::pluck($columns, 'db'));
        $fields = str_replace("stt,", "", $fields);
        if ($table == 'th_bulk_send_message') {
        	$context = context_system::instance();
        	$companyid = 0;
			if (class_exists('iomad')) {
				$companyid = iomad::get_my_companyid($context);
			}
			if (!empty($where)) {
				$where = "WHERE cu.companyid = $companyid AND " . $where;
			} else {
				$where = "WHERE cu.companyid = $companyid";
			}

			$data = $DB->get_records_sql("SELECT ROW_NUMBER() OVER() as stt,$fields
		       FROM {$prefix}$table t
				JOIN {company_users} cu ON cu.userid = t.receiverid
		       $where
		       $order
		       $limit");
			$tmp = array();
			foreach ($data as $key => $a) {
				$tmp[] = (array) $a;
			}
			$data = $tmp;
			$resFilterLength = $DB->count_records_sql(
				"SELECT COUNT({$primaryKey})
			       FROM {$prefix}$table t
					JOIN {company_users} cu ON cu.userid = t.receiverid
			       $where"
			);
			$recordsFiltered = $resFilterLength;

			$resTotalLength = $DB->count_records_sql(
				"SELECT COUNT({$primaryKey})
	       		FROM {$prefix}$table t
				JOIN {company_users} cu ON cu.userid = t.receiverid"
			);
			$recordsTotal = $resTotalLength;
        } else {
        	$data = $DB->get_records_sql("SELECT ROW_NUMBER() OVER() as stt,$fields
		       FROM {$prefix}$table
		       $where
		       $order
		       $limit");
			$tmp = array();
			foreach ($data as $key => $a) {
				$tmp[] = (array) $a;
			}
			$data = $tmp;
			$resFilterLength = $DB->count_records_sql(
				"SELECT COUNT({$primaryKey})
			       FROM {$prefix}$table
			       $where"
			);
			$recordsFiltered = $resFilterLength;

			$resTotalLength = $DB->count_records_sql(
				"SELECT COUNT({$primaryKey})
	       		FROM {$prefix}$table"
			);
			$recordsTotal = $resTotalLength;
        }

		return array(
			"draw" => isset($request['draw']) ?
			intval($request['draw']) :
			0,
			"recordsTotal" => intval($recordsTotal),
			"recordsFiltered" => intval($recordsFiltered),
			"data" => self::data_output($columns, $data),
		);
	}

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 * Internal methods
	*/

	/**
	 * Throw a fatal error.
	 *
	 * This writes out an error message in a JSON string which DataTables will
	 * see and show to the user in the browser.
	 *
	 * @param  string $msg Message to send to the client
	 */
	static function fatal($msg) {
		echo json_encode(array(
			"error" => $msg,
		));

		exit(0);
	}

	/**
	 * Pull a particular property from each assoc. array in a numeric array,
	 * returning and array of the property values from each item.
	 *
	 *  @param  array  $a    Array to get data from
	 *  @param  string $prop Property to read
	 *  @return array        Array of property values
	 */
	static function pluck($a, $prop) {
		$out = array();

		for ($i = 0, $len = count($a); $i < $len; $i++) {
			if (empty($a[$i][$prop]) && $a[$i][$prop] !== 0) {
				continue;
			}

			//removing the $out array index confuses the filter method in doing proper binding,
			//adding it ensures that the array data are mapped correctly
			$out[$i] = $a[$i][$prop];
		}

		return $out;
	}

	/**
	 * Return a string from an array or a string
	 *
	 * @param  array|string $a Array to join
	 * @param  string $join Glue for the concatenation
	 * @return string Joined string
	 */
	static function _flatten($a, $join = ' AND ') {
		if (!$a) {
			return '';
		} else if ($a && is_array($a)) {
			return implode($join, $a);
		}
		return $a;
	}
}
