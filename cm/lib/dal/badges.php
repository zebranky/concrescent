<?php

require_once dirname(__FILE__).'/dal.php';
require_once dirname(__FILE__).'/attendees.php';
require_once dirname(__FILE__).'/booths.php';
require_once dirname(__FILE__).'/eventlets.php';
require_once dirname(__FILE__).'/guests.php';
require_once dirname(__FILE__).'/staffers.php';

require_once dirname(__FILE__).'/../schema/badges.php';
require_once dirname(__FILE__).'/../schema/attendees.php';
require_once dirname(__FILE__).'/../schema/booths.php';
require_once dirname(__FILE__).'/../schema/eventlets.php';
require_once dirname(__FILE__).'/../schema/guests.php';
require_once dirname(__FILE__).'/../schema/staffers.php';

require_once dirname(__FILE__).'/../base/sql.php';
require_once dirname(__FILE__).'/../base/util.php';
require_once dirname(__FILE__).'/../cmbase/res.php';
require_once dirname(__FILE__).'/../cmbase/util.php';

function decode_badge_artwork($result) {
	$id = (int)$result['id'];
	$filename = unpurify_string($result['filename']);
	$vertical = !!$result['vertical'];
	return array(
		'id' => $id,
		'filename' => $filename,
		'vertical' => $vertical,
	);
}

function encode_badge_artwork($result) {
	$set = array();
	if (isset($result['filename'])) $set[] = '`filename` = ' . q_string ($result['filename']);
	if (isset($result['vertical'])) $set[] = '`vertical` = ' . q_boolean($result['vertical']);
	return implode(', ', $set);
}

function decode_badge_artwork_field($result) {
	$badge_artwork_id = (int)$result['badge_artwork_id'];
	$field_type = str_replace('-', '_', unpurify_string($result['field_type']));
	$top = (int)$result['top'] / 1000.0;
	$left = (int)$result['left'] / 1000.0;
	$right = (int)$result['right'] / 1000.0;
	$bottom = (int)$result['bottom'] / 1000.0;
	$font_size = (int)$result['font_size'];
	$font_family = unpurify_string($result['font_family']);
	$font_weight_bold = !!$result['font_weight_bold'];
	$font_style_italic = !!$result['font_style_italic'];
	$color = unpurify_string($result['color']);
	$background = unpurify_string($result['background']);
	$color_minors = unpurify_string($result['color_minors']);
	$background_minors = unpurify_string($result['background_minors']);
	return array(
		'badge_artwork_id' => $badge_artwork_id,
		'field_type' => $field_type,
		'top' => $top,
		'left' => $left,
		'right' => $right,
		'bottom' => $bottom,
		'font_size' => $font_size,
		'font_family' => $font_family,
		'font_weight_bold' => $font_weight_bold,
		'font_style_italic' => $font_style_italic,
		'color' => $color,
		'background' => $background,
		'color_minors' => $color_minors,
		'background_minors' => $background_minors,
	);
}

function encode_badge_artwork_field($result) {
	$set = array();
	if (isset($result['badge_artwork_id' ])) $set[] = '`badge_artwork_id` = '  . q_int           ($result['badge_artwork_id' ]);
	if (isset($result['top'              ])) $set[] = '`top` = '               . q_int_or_null   ($result['top'       ] * 1000);
	if (isset($result['left'             ])) $set[] = '`left` = '              . q_int_or_null   ($result['left'      ] * 1000);
	if (isset($result['right'            ])) $set[] = '`right` = '             . q_int_or_null   ($result['right'     ] * 1000);
	if (isset($result['bottom'           ])) $set[] = '`bottom` = '            . q_int_or_null   ($result['bottom'    ] * 1000);
	if (isset($result['font_size'        ])) $set[] = '`font_size` = '         . q_int_or_null   ($result['font_size'        ]);
	if (isset($result['font_family'      ])) $set[] = '`font_family` = '       . q_string_or_null($result['font_family'      ]);
	if (isset($result['font_weight_bold' ])) $set[] = '`font_weight_bold` = '  . q_boolean       ($result['font_weight_bold' ]);
	if (isset($result['font_style_italic'])) $set[] = '`font_style_italic` = ' . q_boolean       ($result['font_style_italic']);
	if (isset($result['color'            ])) $set[] = '`color` = '             . q_string_or_null($result['color'            ]);
	if (isset($result['background'       ])) $set[] = '`background` = '        . q_string_or_null($result['background'       ]);
	if (isset($result['color_minors'     ])) $set[] = '`color_minors` = '      . q_string_or_null($result['color_minors'     ]);
	if (isset($result['background_minors'])) $set[] = '`background_minors` = ' . q_string_or_null($result['background_minors']);
	if (isset($result['field_type'])) {
		$field_type = str_replace('_', '-', $result['field_type']);
		$set[] = '`field_type` = ' . q_string($field_type);
	}
	return implode(', ', $set);
}

function decode_badge_artwork_map($result) {
	$badge_id_string = unpurify_string($result['badge_id_string']);
	$badge_artwork_id = (int)$result['badge_artwork_id'];
	return array(
		'badge_id_string' => $badge_id_string,
		'badge_artwork_id' => $badge_artwork_id,
	);
}

function encode_badge_artwork_map($result) {
	$set = array();
	if (isset($result['badge_id_string' ])) $set[] = '`badge_id_string` = '  . q_string($result['badge_id_string' ]);
	if (isset($result['badge_artwork_id'])) $set[] = '`badge_artwork_id` = ' . q_int   ($result['badge_artwork_id']);
	return implode(', ', $set);
}

function get_badge_artwork_names($connection) {
	db_require_table('badge_artwork', $connection);
	$names = array();
	$results = mysql_query('SELECT * FROM '.db_table_name('badge_artwork').' ORDER BY `filename`', $connection);
	while ($result = mysql_fetch_assoc($results)) {
		$id = (int)$result['id'];
		$filename = unpurify_string($result['filename']);
		$names[$id] = $filename;
	}
	return $names;
}

function upload_badge_artwork($file, $connection) {
	db_require_table('badge_artwork', $connection);
	if (!$file['error']) {
		$filename = preg_replace('/[^A-Za-z0-9_.-]/', '', $file['name']);
		$path = config_file_path('badges/' . $filename);
		if (move_uploaded_file($file['tmp_name'], $path)) {
			$image_size = getimagesize($path);
			$vertical = ($image_size && ($image_size[1] > $image_size[0]));
			$set = encode_badge_artwork(array(
				'filename' => $filename,
				'vertical' => $vertical,
			));
			mysql_query('INSERT INTO '.db_table_name('badge_artwork').' SET '.$set.' ON DUPLICATE KEY UPDATE '.$set, $connection);
			return 'Image upload succeeded.';
		} else {
			return 'Error uploading image. Perhaps permissions-related?';
		}
	} else {
		return 'Error uploading image. Please try again with a different image.';
	}
}

function get_badge_artwork($id, $connection) {
	db_require_table('badge_artwork', $connection);
	$results = mysql_query('SELECT * FROM '.db_table_name('badge_artwork').' WHERE `id` = '.(int)$id, $connection);
	if ($result = mysql_fetch_assoc($results)) {
		$result = decode_badge_artwork($result);
		return $result;
	}
	return null;
}

function echo_badge_artwork($filename) {
	$path = config_file_path('badges/' . $filename);
	if ($path) {
		$image_type = exif_imagetype($path);
		if ($image_type) {
			$mime_type = image_type_to_mime_type($image_type);
			if ($mime_type) {
				header('Content-Type: ' . $mime_type);
				readfile($path);
				return true;
			}
		}
	}
	return false;
}

function badge_artwork_aspect_ratio($filename) {
	$path = config_file_path('badges/' . $filename);
	if ($path) {
		$image_size = getimagesize($path);
		if ($image_size) {
			return $image_size[1] * 100 / $image_size[0];
		}
	}
	return 66;
}

function delete_badge_artwork($id, $connection) {
	db_require_table('badge_artwork', $connection);
	db_require_table('badge_artwork_field', $connection);
	db_require_table('badge_artwork_map', $connection);
	$results = mysql_query('SELECT `filename` FROM '.db_table_name('badge_artwork').' WHERE `id` = '.(int)$id, $connection);
	while ($result = mysql_fetch_assoc($results)) {
		$filename = unpurify_string($result['filename']);
		$path = config_file_path('badges/' . $filename);
		unlink($path);
	}
	mysql_query('DELETE FROM '.db_table_name('badge_artwork').' WHERE `id` = '.(int)$id, $connection);
	mysql_query('DELETE FROM '.db_table_name('badge_artwork_field').' WHERE `badge_artwork_id` = '.(int)$id, $connection);
	mysql_query('DELETE FROM '.db_table_name('badge_artwork_map').' WHERE `badge_artwork_id` = '.(int)$id, $connection);
}

function get_badge_artwork_fields($id, $connection) {
	db_require_table('badge_artwork_field', $connection);
	$fields = array();
	$results = mysql_query('SELECT * FROM '.db_table_name('badge_artwork_field').' WHERE `badge_artwork_id` = '.(int)$id, $connection);
	while ($result = mysql_fetch_assoc($results)) {
		$result = decode_badge_artwork_field($result);
		$fields[] = $result;
	}
	return $fields;
}

function set_badge_artwork_fields($id, $fields, $connection) {
	db_require_table('badge_artwork_field', $connection);
	mysql_query('DELETE FROM '.db_table_name('badge_artwork_field').' WHERE `badge_artwork_id` = '.(int)$id, $connection);
	foreach ($fields as $field) {
		$field['badge_artwork_id'] = $id;
		$set = encode_badge_artwork_field($field);
		mysql_query('INSERT INTO '.db_table_name('badge_artwork_field').' SET '.$set, $connection);
	}
}

function get_all_badge_types($connection) {
	db_require_table('attendee_badges', $connection);
	db_require_table('booth_badges', $connection);
	db_require_table('eventlet_badges', $connection);
	db_require_table('guest_badges', $connection);
	db_require_table('staffer_badges', $connection);
	$badges = array();
	$results = mysql_query('SELECT * FROM '.db_table_name('attendee_badges').' ORDER BY `order`', $connection);
	while ($result = mysql_fetch_assoc($results)) {
		$result = decode_attendee_badge($result);
		$result['t'] = 'a';
		$badges[] = $result;
	}
	$results = mysql_query('SELECT * FROM '.db_table_name('booth_badges').' ORDER BY `order`', $connection);
	while ($result = mysql_fetch_assoc($results)) {
		$result = decode_booth_badge($result);
		$result['t'] = 'b';
		$badges[] = $result;
	}
	$results = mysql_query('SELECT * FROM '.db_table_name('eventlet_badges').' ORDER BY `order`', $connection);
	while ($result = mysql_fetch_assoc($results)) {
		$result = decode_eventlet_badge($result);
		$result['t'] = 'e';
		$badges[] = $result;
	}
	$results = mysql_query('SELECT * FROM '.db_table_name('guest_badges').' ORDER BY `order`', $connection);
	while ($result = mysql_fetch_assoc($results)) {
		$result = decode_guest_badge($result);
		$result['t'] = 'g';
		$badges[] = $result;
	}
	$results = mysql_query('SELECT * FROM '.db_table_name('staffer_badges').' ORDER BY `order`', $connection);
	while ($result = mysql_fetch_assoc($results)) {
		$result = decode_staffer_badge($result);
		$result['t'] = 's';
		$badges[] = $result;
	}
	return $badges;
}

function get_badge_artwork_map($id, $connection) {
	db_require_table('badge_artwork_map', $connection);
	$badge_ids = array();
	$results = mysql_query('SELECT * FROM '.db_table_name('badge_artwork_map').' WHERE `badge_artwork_id` = '.(int)$id, $connection);
	while ($result = mysql_fetch_assoc($results)) {
		$badge_id = unpurify_string($result['badge_id_string']);
		$badge_ids[] = $badge_id;
	}
	return $badge_ids;
}

function set_badge_artwork_map($id, $badge_ids, $connection) {
	db_require_table('badge_artwork_map', $connection);
	mysql_query('DELETE FROM '.db_table_name('badge_artwork_map').' WHERE `badge_artwork_id` = '.(int)$id, $connection);
	foreach ($badge_ids as $badge_id) {
		$set = encode_badge_artwork_map(array(
			'badge_id_string' => $badge_id,
			'badge_artwork_id' => $id,
		));
		$q = 'INSERT INTO '.db_table_name('badge_artwork_map').' SET '.$set;
		mysql_query($q, $connection);
	}
}

function cmp_badge_artwork_filename($a, $b) {
	return strnatcasecmp($a['filename'], $b['filename']);
}

function get_badge_artwork_for_badge_id($badge_id, $connection) {
	db_require_table('badge_artwork_map', $connection);
	db_require_table('badge_artwork', $connection);
	$artwork = array();
	$results = mysql_query('SELECT * FROM '.db_table_name('badge_artwork_map').' WHERE `badge_id_string` = '.q_string($badge_id), $connection);
	while ($result = mysql_fetch_assoc($results)) {
		$result = decode_badge_artwork_map($result);
		$innerresults = mysql_query('SELECT * FROM '.db_table_name('badge_artwork').' WHERE `id` = '.(int)$result['badge_artwork_id'], $connection);
		while ($innerresult = mysql_fetch_assoc($innerresults)) {
			$innerresult = decode_badge_artwork($innerresult);
			$artwork[] = $innerresult;
		}
	}
	usort($artwork, 'cmp_badge_artwork_filename');
	return $artwork;
}

function get_all_badge_artwork($connection) {
	db_require_table('badge_artwork', $connection);
	$artwork = array();
	$results = mysql_query('SELECT * FROM '.db_table_name('badge_artwork').' ORDER BY `filename`', $connection);
	while ($result = mysql_fetch_assoc($results)) {
		$result = decode_badge_artwork($result);
		$artwork[] = $result;
	}
	usort($artwork, 'cmp_badge_artwork_filename');
	return $artwork;
}

function list_badge_holders($t, $badge_id_string, $application_status, $payment_status, $start_id, $batch_size, $connection, $searchString = null) {
	// "If you have a procedure with ten parameters, you probably missed some."
	// This is very ugly and could benefit from refactoring of the individual DALs.
    //Tsaukpaetra: Challenge Accepted!
	$badge_holders = array();
    $totalRows = 0;

    //Lets declare a small badge-type map
    $badgedata = array(
        's' => array(
            'requiredtables' => array('staffers','staffer_badges'),
            'table' => 'staffers',
            'badgenamefunc' => 'get_staffer_badge_names',
            'infofunc' => null,
            'decodefunc' => 'decode_staffer',
            'searchcolumns' => array('')
        ),
        'g' => array(
            'requiredtables' => array('guest_supporters','guests','guest_badges'),
            'table' => 'guest_supporters',
            'badgenamefunc' => 'get_guest_badge_names',
            'infofunc' => 'get_guest_info',
            'decodefunc' => 'decode_guest_supporter',
            'searchcolumns' => array()
        ),
        'e' => array(
            'requiredtables' => array('eventlet_staffers','eventlets','eventlet_badges'),
            'table' => 'eventlet_staffers',
            'badgenamefunc' => 'get_eventlet_badge_names',
            'infofunc' => 'get_eventlet_info',
            'decodefunc' => 'decode_eventlet_staffer',
            'searchcolumns' => array()
        ),
        'b' => array(
            'requiredtables' => array('booth_staffers','booths','booth_badges'),
            'table' => 'booth_staffers',
            'badgenamefunc' => 'get_booth_badge_names',
            'infofunc' => 'get_booth_info',
            'decodefunc' => 'decode_booth_staffer',
            'searchcolumns' => array()
        ),
        'a' => array(
            'requiredtables' => array('attendees','attendee_badges'),
            'table' => 'attendees',
            'badgenamefunc' => 'get_attendee_badge_names',
            'infofunc' => null,
            'decodefunc' => 'decode_attendee',
            'searchcolumns' => array(
                'first_name',
                'last_name',
                'fandom_name',
                'email_address',
                'phone_number',
                'ice_name',
                'ice_email_address',
                'ice_phone_number',
                'payment_lookup_key'
            )
        ),
    );

    //Good, now lets do a little processing!

    //Check that type is not null/false
    if(!$t)
    {
        //Loop through all the known types
        foreach($badgedata as $t2 => $value)
        {
            List($bhadd, $totadd) = list_badge_holders($t2, $badge_id_string, $application_status, $payment_status, $start_id, $batch_size, $connection, $searchString);
            $badge_holders = array_merge((array)$bhadd,$badge_holders);
            $totalRows += $totadd;
        }
        return $badge_holders;
    }

    //Quickfail: Was a valid badge type given?
    if(!isset($badgedata[$t]))
        return null;

    //Normal processing. Lets get the badge info we need
    $binfo = $badgedata[$t];

    //First, require the tables
    foreach($binfo['requiredtables'] as $tablename )
        db_require_table($tablename, $connection);

    //Next, get the badge names
    $badge_names = call_user_func($binfo['badgenamefunc'], $connection);

    //If we have an infofunc, replace the $badge_names with that info
    if($binfo['infofunc'])
        $badge_names = call_user_func($binfo['infofunc'],$connection, $badge_names);

    //Start building our queries (one for the total count if limited, and the normal one)
    $qry = 'SELECT * FROM '.db_table_name($binfo['table']).' ';
    $qrycount = 'SELECT COUNT(*) as Total FROM '.db_table_name($binfo['table']) .' ';

    //Build up the Where clause
    $where = array();
    //Add Badge_ID if set
    if($badge_id_string)
        $where[] = 'badge_id = ' . preg_replace("/[^0-9,.]/", "", $badge_id_string);
    //Add Application Status if set
    if($application_status)
        $where[] = 'application_status = \'' . mysql_real_escape_string($application_status) . '\'';
    //Add payment_status if set
    if($payment_status)
        $where[] = 'payment_status = \'' . mysql_real_escape_string($application_status) . '\'';


    //Add searchable columns if we're searching
    if (isset($searchString))
    {
        $s = '(';
        foreach($binfo["searchcolumns"] as $idx => $searchcol)
        {
            if($idx>0)
                $s .= 'or ';
            $s .= $searchcol . ' like \'%' . mysql_real_escape_string($searchString) . '%\' ';
        }
        $where[] = $s . ')';

    }

    //Now compile the Where clause
    $whereclause = count($where) == 0 ? ' ' : 'WHERE 1=1 AND ' . implode(' AND ',$where) . ' ' ;

    //Add the Order By clause
    //TODO: Allow this to be dynamic somehow?
    $orderclause = 'ORDER BY `id` ASC ';

    //Add Limits if asked for
    $limits = ($batch_size ? ' LIMIT '.$batch_size .' ': ' ')
        . ($start_id ? ' OFFSET '.($start_id - 1) . ' ' : ' ');

    //Finalize the queries
    $qry .= $whereclause . $orderclause . $limits;
    $qrycount .= $whereclause;

    //Execute the queries
    $results = mysql_query($qry, $connection);

    //Add the results together
    while ($result = mysql_fetch_assoc($results)) {
        $result = call_user_func($binfo['decodefunc'],$result, $badge_names);
        if (!$badge_id_string || $result['badge_id_string'] == $badge_id_string) {
                    $result['t'] = $t;
                    $badge_holders[] = $result;
        }
    }

    //Retrieve the count if asked for
    $totalRows = strlen($limits) > 3 ? mysql_fetch_assoc(mysql_query($qrycount, $connection))['Total'] : '-1';


/* Legacy code
	if (!$t || $t == 's') {
		db_require_table('staffers', $connection);
		db_require_table('staffer_badges', $connection);
		$badge_names = get_staffer_badge_names($connection);
		$results = mysql_query('SELECT * FROM '.db_table_name('staffers').' ORDER BY `id`', $connection);
		while ($result = mysql_fetch_assoc($results)) {
			$result = decode_staffer($result, $badge_names);
			if (!$badge_id_string || $result['badge_id_string'] == $badge_id_string) {
				if (!$application_status || $result['application_status'] == $application_status) {
					if (!$payment_status || $result['payment_status'] == $payment_status) {
						$result['t'] = 's';
						$badge_holders[] = $result;
					}
				}
			}
		}
	}
	if (!$t || $t == 'g') {
		db_require_table('guest_supporters', $connection);
		db_require_table('guests', $connection);
		db_require_table('guest_badges', $connection);
		$badge_names = get_guest_badge_names($connection);
		$guest_info = get_guest_info($connection, $badge_names);
		$results = mysql_query('SELECT * FROM '.db_table_name('guest_supporters').' ORDER BY `id`', $connection);
		while ($result = mysql_fetch_assoc($results)) {
			$result = decode_guest_supporter($result, $guest_info);
			if (!$badge_id_string || $result['badge_id_string'] == $badge_id_string) {
				if (!$application_status || $result['application_status'] == $application_status) {
					if (!$payment_status || $result['payment_status'] == $payment_status) {
						$result['t'] = 'g';
						$badge_holders[] = $result;
					}
				}
			}
		}
	}
	if (!$t || $t == 'e') {
		db_require_table('eventlet_staffers', $connection);
		db_require_table('eventlets', $connection);
		db_require_table('eventlet_badges', $connection);
		$badge_names = get_eventlet_badge_names($connection);
		$eventlet_info = get_eventlet_info($connection, $badge_names);
		$results = mysql_query('SELECT * FROM '.db_table_name('eventlet_staffers').' ORDER BY `id`', $connection);
		while ($result = mysql_fetch_assoc($results)) {
			$result = decode_eventlet_staffer($result, $eventlet_info);
			if (!$badge_id_string || $result['badge_id_string'] == $badge_id_string) {
				if (!$application_status || $result['application_status'] == $application_status) {
					if (!$payment_status || $result['payment_status'] == $payment_status) {
						$result['t'] = 'e';
						$badge_holders[] = $result;
					}
				}
			}
		}
	}
	if (!$t || $t == 'b') {
		db_require_table('booth_staffers', $connection);
		db_require_table('booths', $connection);
		db_require_table('booth_badges', $connection);
		$badge_names = get_booth_badge_names($connection);
		$booth_info = get_booth_info($connection, $badge_names);
		$results = mysql_query('SELECT * FROM '.db_table_name('booth_staffers').' ORDER BY `id`', $connection);
		while ($result = mysql_fetch_assoc($results)) {
			$result = decode_booth_staffer($result, $booth_info);
			if (!$badge_id_string || $result['badge_id_string'] == $badge_id_string) {
				if (!$application_status || $result['application_status'] == $application_status) {
					if (!$payment_status || $result['payment_status'] == $payment_status) {
						$result['t'] = 'b';
						$badge_holders[] = $result;
					}
				}
			}
		}
	}
	if (!$t || $t == 'a') {
		db_require_table('attendees', $connection);
		db_require_table('attendee_badges', $connection);
		$badge_names = get_attendee_badge_names($connection);
		$q = 'SELECT * FROM '.db_table_name('attendees');
		if ($start_id) $q .= ' WHERE `id` >= '.(int)$start_id;
		$q .= ' ORDER BY `id`';
		if ($batch_size) $q .= ' LIMIT '.(int)$batch_size;
		$results = mysql_query($q, $connection);
		while ($result = mysql_fetch_assoc($results)) {
			$result = decode_attendee($result, $badge_names);
			if (!$badge_id_string || $result['badge_id_string'] == $badge_id_string) {
				if (!$payment_status || $result['payment_status'] == $payment_status) {
					$result['t'] = 'a';
					$badge_holders[] = $result;
				}
			}
		}
	}
*/
	return array($badge_holders,$totalRows);
}

function get_badge_holder($t, $id, $connection) {
	// This is fairly ugly and could benefit from refactoring of the individual DALs.
	switch ($t) {
		case 'a':
			db_require_table('attendees', $connection);
			db_require_table('attendee_badges', $connection);
			$results = mysql_query('SELECT * FROM '.db_table_name('attendees').' WHERE `id` = '.(int)$id, $connection);
			if ($result = mysql_fetch_assoc($results)) return decode_attendee($result, get_attendee_badge_names($connection));
			break;
		case 'b':
			db_require_table('booth_staffers', $connection);
			db_require_table('booths', $connection);
			db_require_table('booth_badges', $connection);
			$results = mysql_query('SELECT * FROM '.db_table_name('booth_staffers').' WHERE `id` = '.(int)$id, $connection);
			if ($result = mysql_fetch_assoc($results)) return decode_booth_staffer($result, get_booth_info($connection, get_booth_badge_names($connection)));
			break;
		case 'e':
			db_require_table('eventlet_staffers', $connection);
			db_require_table('eventlets', $connection);
			db_require_table('eventlet_badges', $connection);
			$results = mysql_query('SELECT * FROM '.db_table_name('eventlet_staffers').' WHERE `id` = '.(int)$id, $connection);
			if ($result = mysql_fetch_assoc($results)) return decode_eventlet_staffer($result, get_eventlet_info($connection, get_eventlet_badge_names($connection)));
			break;
		case 'g':
			db_require_table('guest_supporters', $connection);
			db_require_table('guests', $connection);
			db_require_table('guest_badges', $connection);
			$results = mysql_query('SELECT * FROM '.db_table_name('guest_supporters').' WHERE `id` = '.(int)$id, $connection);
			if ($result = mysql_fetch_assoc($results)) return decode_guest_supporter($result, get_guest_info($connection, get_guest_badge_names($connection)));
			break;
		case 's':
			db_require_table('staffers', $connection);
			db_require_table('staffer_badges', $connection);
			$results = mysql_query('SELECT * FROM '.db_table_name('staffers').' WHERE `id` = '.(int)$id, $connection);
			if ($result = mysql_fetch_assoc($results)) return decode_staffer($result, get_staffer_badge_names($connection));
			break;
	}
	return null;
}

function set_attendee_payment_completed($id, $badge_id, $connection) {
	db_require_table('attendees', $connection);
	db_require_table('attendee_badges', $connection);
	$results = mysql_query('SELECT * FROM '.db_table_name('attendee_badges').' WHERE `id` = '.(int)$badge_id, $connection);
	$result = decode_attendee_badge(mysql_fetch_assoc($results));
	$set = encode_attendee(array(
		'badge_id' => $badge_id,
		'payment_status' => 'Completed',
		'payment_type' => 'Live',
		'payment_txn_id' => uniqid(),
		'payment_original_price' => $result['price'],
		'payment_final_price' => $result['price'],
		'payment_total_price' => $result['price'],
		'payment_details' => 'Paid in-person through badge checkin.',
		'payment_date' => 'NOW()',
	));
	mysql_query('UPDATE '.db_table_name('attendees').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
}

function set_badge_holder_info($t, $id, $result, $connection) {
	// This is fairly ugly and could benefit from refactoring of the individual DALs.
	switch ($t) {
		case 'a':
			db_require_table('attendees', $connection);
			$set = encode_attendee($result);
			mysql_query('UPDATE '.db_table_name('attendees').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'b':
			db_require_table('booth_staffers', $connection);
			$set = encode_booth_staffer($result);
			mysql_query('UPDATE '.db_table_name('booth_staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'e':
			db_require_table('eventlet_staffers', $connection);
			$set = encode_eventlet_staffer($result);
			mysql_query('UPDATE '.db_table_name('eventlet_staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'g':
			db_require_table('guest_supporters', $connection);
			$set = encode_guest_supporter($result);
			mysql_query('UPDATE '.db_table_name('guest_supporters').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 's':
			db_require_table('staffers', $connection);
			$set = encode_staffer($result);
			mysql_query('UPDATE '.db_table_name('staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
	}
}

function create_new_attendee($result, $connection) {
	db_require_table('attendees', $connection);
	$set = encode_attendee($result);
	mysql_query('INSERT INTO '.db_table_name('attendees').' SET '.$set.', `date_created` = NOW()', $connection);
	return (int)mysql_insert_id();
}

function reset_print_count($t, $id, $connection) {
	$set = '`print_count` = 0, `print_time` = NULL';
	switch ($t) {
		case 'a':
			db_require_table('attendees', $connection);
			mysql_query('UPDATE '.db_table_name('attendees').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'b':
			db_require_table('booth_staffers', $connection);
			mysql_query('UPDATE '.db_table_name('booth_staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'e':
			db_require_table('eventlet_staffers', $connection);
			mysql_query('UPDATE '.db_table_name('eventlet_staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'g':
			db_require_table('guest_supporters', $connection);
			mysql_query('UPDATE '.db_table_name('guest_supporters').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 's':
			db_require_table('staffers', $connection);
			mysql_query('UPDATE '.db_table_name('staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
	}
}

function increment_checkin_count($t, $id, $connection) {
	$set = '`checkin_count` = IFNULL(`checkin_count`, 0) + 1, `checkin_time` = NOW()';
	switch ($t) {
		case 'a':
			db_require_table('attendees', $connection);
			mysql_query('UPDATE '.db_table_name('attendees').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'b':
			db_require_table('booth_staffers', $connection);
			mysql_query('UPDATE '.db_table_name('booth_staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'e':
			db_require_table('eventlet_staffers', $connection);
			mysql_query('UPDATE '.db_table_name('eventlet_staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'g':
			db_require_table('guest_supporters', $connection);
			mysql_query('UPDATE '.db_table_name('guest_supporters').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 's':
			db_require_table('staffers', $connection);
			mysql_query('UPDATE '.db_table_name('staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
	}
}

function increment_print_count($t, $id, $connection) {
	$set = '`print_count` = IFNULL(`print_count`, 0) + 1, `print_time` = NOW()';
	switch ($t) {
		case 'a':
			db_require_table('attendees', $connection);
			mysql_query('UPDATE '.db_table_name('attendees').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'b':
			db_require_table('booth_staffers', $connection);
			mysql_query('UPDATE '.db_table_name('booth_staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'e':
			db_require_table('eventlet_staffers', $connection);
			mysql_query('UPDATE '.db_table_name('eventlet_staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 'g':
			db_require_table('guest_supporters', $connection);
			mysql_query('UPDATE '.db_table_name('guest_supporters').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
		case 's':
			db_require_table('staffers', $connection);
			mysql_query('UPDATE '.db_table_name('staffers').' SET '.$set.' WHERE `id` = '.(int)$id, $connection);
			break;
	}
}