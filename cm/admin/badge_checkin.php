<?php

require_once dirname(__FILE__).'/admin.php';
require_once dirname(__FILE__).'/../lib/cmbase/util.php';
require_once dirname(__FILE__).'/../lib/dal/badges.php';
require_once dirname(__FILE__).'/../lib/dal/lists.php';
require_once dirname(__FILE__).'/../lib/ui/lists.php';

function is_badge_pulled($holder, $connection) {
	return (
		(isset($holder['application_status']) && ($holder['application_status'] == 'Pulled')) ||
		(isset($holder[    'payment_status']) && ($holder[    'payment_status'] == 'Pulled')) ||
		(isset($holder[   'payment_status']) && ($holder[   'payment_status'] == 'Pulled')) ||
		attendee_is_blacklisted($holder, $connection)
	);
}

function render_badge_artwork($t, $id, $badge_id, $connection) {
	$artwork = get_badge_artwork_for_badge_id($badge_id, $connection);
	if ($artwork) {
		$html = '';
		foreach ($artwork as $a) {
			$html .= '<div class="artwork">';
			$html .= '<a href="badge_print.php?t='.htmlspecialchars($t).'&id='.(int)$id.'&ba='.(int)$a['id'].'"';
			$html .= ' target="_blank">';
			$html .= '<img src="badge_checkin.php?img&ba='.(int)$a['id'].'"';
			if ($a['vertical']) {
				$html .= ' class="vertical"';
			} else {
				$html .= ' class="horizontal"';
			}
			$html .= ' title="'.htmlspecialchars($a['filename']).'">';
			$html .= '</a>';
			$html .= '</div>';
		}
		return $html;
	} else {
		return '<div class="no-artwork">No artwork.</div>';
	}
}

$conn = get_db_connection();

if (isset($_POST['img']) || isset($_GET['img'])) {
	if (isset($_POST['ba'])) {
		$badge_artwork_id = $_POST['ba'];
	} else if (isset($_GET['ba'])) {
		$badge_artwork_id = $_GET['ba'];
	} else {
		header('Location: badge_checkin.php');
		exit(0);
	}
	$badge_artwork = get_badge_artwork($badge_artwork_id, $conn);
	if (!$badge_artwork) {
		header('Location: badge_checkin.php');
		exit(0);
	}
	if (!echo_badge_artwork($badge_artwork['filename'])) {
		header('Content-Type: image/png');
		$image = imagecreate(300, 200);
		$bg = imagecolorallocate($image, 255, 255, 255);
		imagefilledrectangle($image, 0, 0, 300, 200, $bg);
		imagepng($image);
		imagedestroy($image);
	}
	exit(0);
}
// jqGrid listing
if (isset($_POST['_search'])) {

    $searchString = isset($_POST['search']) ? $_POST['search'] : '';

    List($results, $totalRows) = list_badge_holders(
        'a', null, null, null,
        ($_POST['rows'] * ($_POST['page'] - 1)) + 1, $_POST['rows'],
        $conn,
        $searchString
    );
    $response = array(
        'total' => (int)($totalRows / $_POST['rows'] + ($totalRows % $_POST['rows'] > 0 ? 1 : 0)), //Total pages
        'page' => $_POST['page'],
        'records' => $totalRows, //Total records
        'rows' => $results,
    );
    echo json_encode($response);

    return;
}

//Legacy listing
if (isset($_POST['start_id'])) {
	header('Content-type: text/plain');
	$holders = array();
	$start_id = (int)$_POST['start_id'];
	$end_id = $start_id;
	$batch_size = 100;
	
	List($results, $totalRows) = list_badge_holders(
		'a', null, null, null,
		($start_id ? $start_id : 1), $batch_size,
		$conn
	);
	foreach ($results as $result) {
		$html_content = render_list_row(
			array(
				$result['id_string'],
				$result['real_name'],
				$result['fandom_name'],
				$result['badge_name'],
				array('html' => email_link($result['email_address'])),
				(isset($result['application_status_html']) ? array('html' => $result['application_status_html']) : ''),
				(isset($result['payment_status_html']) ? array('html' => $result['payment_status_html']) :
				(isset($result['payment_status_html']) ? array('html' => $result['payment_status_html']) : '')),
			),
			array(
				'ea-t' => $result['t'],
				'ea-id' => $result['id'],
			),
			/*  selectable = */ true,
			/*  switchable = */ false,
			/*      active = */ false,
			/*  deleteable = */ false,
			/* reorderable = */ false,
			/*        edit = */ false,
			/*      review = */ false
		);
		$holders[] = array(
			'id' => $result['id'],
			'search_content' => $result['search_content'],
			'html_content' => $html_content,
		);
		if ($result['t'] == 'a') {
			$end_id = $result['id'];
		}
	}
	
	$response = array(
		'start_id' => $start_id,
		'end_id' => $end_id,
		'next_start_id' => $end_id + 1,
		'batch_size' => $batch_size,
		'entities' => $holders,
	);
	echo json_encode($response);
	exit(0);
}

if (isset($_POST['action'])) {
	header('Content-type: text/plain');
	switch ($_POST['action']) {
		case 'startCheckIn':
			$t = $_POST['t'];
			$id = (int)$_POST['id'];
			$holder = get_badge_holder($t, $id, $conn);
			$force_checkin = !!(int)$_POST['force_checkin'];
			if (!$holder) {
				$response = array('next_state' => 'checkin-error', 'finished' => true);
			} else if (is_badge_pulled($holder, $conn)) {
				$response = array('next_state' => 'badge-holder-blacklisted', 'finished' => true);
			} else if ($holder['checkin_count'] > 0 && !$force_checkin) {
				$response = array('next_state' => 'already-checked-in');
			} else if (isset($holder['application_status'])) {
				if ($holder['application_status'] != 'Accepted') {
					$response = array('next_state' => 'application-denied', 'finished' => true);
				} else if (
					(isset($holder['payment_status']) && ($holder['payment_status'] != 'Completed')) ||
					(isset($holder['payment_status']) && ($holder['payment_status'] != 'Completed'))
				) {
					$response = array('next_state' => 'application-unpaid', 'finished' => true);
				} else {
					$response = array('next_state' => 'verify-info', 'form_values' => $holder);
				}
			} else {
				if (isset($holder['payment_status']) && ($holder['payment_status'] != 'Completed')) {
					$response = array('next_state' => 'payment-incomplete', 'form_values' => $holder);
				} else {
					$response = array('next_state' => 'verify-info', 'form_values' => $holder);
				}
			}
			break;
		case 'paymentCollected':
			$t = $_POST['t'];
			$id = (int)$_POST['id'];
			$badge_id = (int)$_POST['badge_id'];
			set_attendee_payment_completed($id, $badge_id, $conn);
			$holder = get_badge_holder($t, $id, $conn);
			if (!$holder || ($holder['payment_status'] != 'Completed')) {
				$response = array('next_state' => 'checkin-error', 'finished' => true);
			} else {
				$response = array('next_state' => 'verify-info', 'form_values' => $holder);
			}
			break;
		case 'infoVerified':
			$t = $_POST['t'];
			$id = (int)$_POST['id'];
			$changed = !!(int)$_POST['changed'];
			$force_print = !!(int)$_POST['force_print'];
			if ($changed) {
				set_badge_holder_info($t, $id, $_POST, $conn);
				reset_print_count($t, $id, $conn);
			}
			increment_checkin_count($t, $id, $conn);
			$holder = get_badge_holder($t, $id, $conn);
			if (!$holder || !$holder['checkin_count']) {
				$response = array('next_state' => 'checkin-error', 'finished' => true);
			} else if (is_badge_pulled($holder, $conn)) {
				$response = array('next_state' => 'badge-holder-blacklisted', 'finished' => true);
			} else if ($holder['print_count'] > 0 && !$force_print) {
				$response = array('next_state' => 'badge-already-printed', 'badge_info' => $holder);
			} else {
				$artwork = render_badge_artwork($t, $id, $holder['badge_id_string'], $conn);
				$response = array('next_state' => 'badge-printing', 'artwork_html' => $artwork);
			}
			break;
		case 'newAttendeeCheckIn':
			$t = 'a';
			$id = create_new_attendee($_POST, $conn);
			$badge_id = (int)$_POST['badge_id'];
			set_attendee_payment_completed($id, $badge_id, $conn);
			increment_checkin_count($t, $id, $conn);
			$holder = get_badge_holder($t, $id, $conn);
			if (!$holder || ($holder['payment_status'] != 'Completed') || !$holder['checkin_count']) {
				$response = array('next_state' => 'checkin-error', 'finished' => true);
			} else if (is_badge_pulled($holder, $conn)) {
				$response = array('next_state' => 'badge-holder-blacklisted', 'finished' => true);
			} else {
				$artwork = render_badge_artwork($t, $id, $holder['badge_id_string'], $conn);
				$response = array('next_state' => 'badge-printing', 'artwork_html' => $artwork);
			}
			break;
		default:
			$response = array('next_state' => 'checkin-error', 'finished' => true);
			break;
	}
	echo json_encode($response);
	exit(0);
}

/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - */

$badge_info = get_valid_attendee_badges($conn);

render_admin_head('Badge Check-In');

echo '<link rel="stylesheet" href="' . htmlspecialchars(resource_file_url('cmbacheckin.css')) . '">';
echo '<script type="text/javascript" src="' . htmlspecialchars(resource_file_url('cmbacheckin.js')) . '"></script>';
echo '<script type="text/javascript" src="' . htmlspecialchars(resource_file_url('cmlists.js')) . '"></script>';

if(isset($_GET['legacyloader']) && $_GET['legacyloader'] == '1')
{
?><script type="text/javascript">listPage({
	ajaxUrl: 'badge_checkin.php',
	entityType: 'badge holders',
	progressive: true,
	searchable: true,
	startId: 0,
	maxResults: 5,
	listItemInit: function(item) {
		item.find('.select-button').text('Start Check-In');
	},
	selectable: true,
	selectAction: function(item) {
		var t = item.find('.ea-t').val();
		var id = item.find('.ea-id').val();
		startCheckIn(t, id);
	},
	pageInit: function() {
		$('.add-button').text('New Attendee');
		$('.add-button').click(startNewAttendee);
	},
});</script><?php
    render_admin_body('Badge Check-In');

    echo '<div class="card" >';
    render_list_search('name, badge type, contact info, or transaction ID', 'card-content-only');
    echo '</div>';

    echo '<div class="card entity-list-card">';
    render_list_table(array(
        'ID', 'Real Name', 'Fandom Name',
        'Badge Type', 'Email Address',
        'Application Status', 'Payment Status'
    ), null, true, $conn, true, "badge_checkin_table" );

    echo '</div>';

} else {
    //Use jQGrid instead
    echo '<link rel="stylesheet" href="' . htmlspecialchars(resource_file_url('jquery-ui/jquery-ui.css')) . '"></script>';
    echo '<link rel="stylesheet" href="' . htmlspecialchars(resource_file_url('ui.jqgrid.css')) . '"></script>';
    echo '<script type="text/javascript" src="' . htmlspecialchars(resource_file_url('grid.locale-en.js')) . '"></script>';
    echo '<script type="text/javascript" src="' . htmlspecialchars(resource_file_url('jquery.jqGrid.min.js')) . '"></script>';
    echo '<script type="text/javascript" src="' . htmlspecialchars(resource_file_url('jqgridAssist.js')) . '"></script>';
    ?>
<script type="text/javascript">
    gridParams['#badge_checkin_table'] = {
        url: 'badge_checkin.php',
        datatype: 'json',
        postData: {
            search : function() {return $("#searchText").val();},
        },
        pager: '#badge_checkin_pager',
        toppager: false,
        //shringToFit: true,
        autowidth: true,
        colNames: ["ID","Real Name","Fandom Name","Badge Type","Email Address","Application Status","Payment Status","Actions"],
        colModel: [
            {name: "id_string", width: 40 },
            {name: "real_name", width: 130},
            {name: "fandom_name", width: 120},
            {name: "badge_name", width: 80},
            {name: "email_address", width: 130},
            {name: "application_status", width: 80},
            {name: "payment_status", width: 70},
            {name: "", width: 90, formatter: jqFormat_Actions},

        ]

    }
    gridParams['#badge_checkin_pager'] = {
        parameters: {
            refresh: true,
            add: true,
            edit: false,
            del: false,
            search: false,
            view: false
        },
    }

    //$("div.card.entity-list-card > table")
$(document).ready(function () {
    //Things to do upon opening the page
    //Init the grid
    refresh_grid();
    //$("#gview_badge_checkin_table > div.ui-jqgrid-hdiv.ui-state-default > div > table").addClass("entity-list");
});

    function refresh_grid()
    {
        loadGrid("#badge_checkin_table", "","");
    }

    function jqFormat_Actions(cellvalue, options, rowObject)
    {
        return '<button onclick="startCheckIn(\'' + rowObject["t"] + '\',\'' + rowObject["id"] + '\')">Start Check-In</button>' ;
    }

    //Helper function
    var timer = null;
    function debounce(fn, delay) {

        return function () {
            var context = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    function triggerSearch(immediate)
    {
        if(immediate)
        {
            $("#badge_checkin_table").jqGrid().trigger("reloadGrid");

        } else {
            debounce(function(event){triggerSearch(true);},500)();
        }
    }
</script>
<?php

    render_admin_body('Badge Check-In');
?>

    <div class="card">
        <div class="card-content-only">
            <div class="search-input">
                <div class="search-input-group">
                    <label>Search by name, badge type, contact info, or transaction ID:</label>
                    <input type="text" class="search-filter" id="searchText" onkeyup='triggerSearch(false);'>
                </div>
            </div>
            <div class="search-options">
                <div class="search-input-group">
                    <button class="search-go" onclick='triggerSearch(true);'>Go</button>
                </div>
            </div>
        </div>
    </div>
<?php

    echo '<div class="card entity-list-card ">';
    render_list_table(array(
        'ID', 'Real Name', 'Fandom Name',
        'Badge Type', 'Email Address',
        'Application Status', 'Payment Status'
    ), null, true, $conn, true, "badge_checkin_table" );

    //echo '<div class="card" >';
    echo '<div class="card entity-list" id="badge_checkin_pager">';
    //render_list_search('name, badge type, contact info, or transaction ID', 'card-content-only');
    echo '</div>';

    echo '</div>';

}

?>
    <div class="card checkin-state checkin-error hidden">
<div class="card-content spaced">
<p><b>Cannot check in this person: An unexpected error occurred.</b></p>
<p>Please contact an executive staff member.</p>
</div>
<div class="card-buttons">
<button onclick="cancelCheckIn();">Cancel Check-In</button>
</div>
</div>

<div class="card checkin-state badge-holder-blacklisted hidden">
<div class="card-content spaced">
<p><b>Cannot check in this person: This person has been blacklisted.</b></p>
<p class="blacklist-error">Please contact con ops immediately.</p>
</div>
<div class="card-buttons">
<button onclick="cancelCheckIn();">Cancel Check-In</button>
</div>
</div>

<div class="card checkin-state already-checked-in hidden">
<div class="card-content spaced">
<p><b>This person has already been checked in.</b></p>
<p>Please continue only if reprinting a badge. If not reprinting a badge, please contact an executive staff member.</p>
</div>
<div class="card-buttons">
<button onclick="cancelCheckIn();">Cancel Check-In</button>
<button onclick="checkInAgain();" class="action-button">Continue Check-In</button>
</div>
</div>

<div class="card checkin-state application-denied hidden">
<div class="card-content spaced">
<p><b>Cannot check in this person: This person's application was not accepted.</b></p>
<p>Please contact the appropriate executive staff member if you believe this is an error.</p>
</div>
<div class="card-buttons">
<button onclick="cancelCheckIn();">Cancel Check-In</button>
</div>
</div>

<div class="card checkin-state application-unpaid hidden">
<div class="card-content spaced">
<p><b>Cannot check in this person: This person's application has not been completed and/or paid for.</b></p>
<p>Please contact the appropriate executive staff member.</p>
</div>
<div class="card-buttons">
<button onclick="cancelCheckIn();">Cancel Check-In</button>
</div>
</div>

<div class="card checkin-state payment-incomplete hidden">
<div class="card-content spaced">
<p><b>This person's badge has not been paid for.</b></p>
<p>Please select a badge type and collect the required payment amount.</p>
<?php
echo '<p><select name="badge_id" id="badge_id" class="badge-id">';
foreach ($badge_info as $badge_id => $badge) {
	echo '<option value="'.$badge_id.'">';
	echo htmlspecialchars($badge['name']);
	echo ' - Payment Amount: ';
	echo htmlspecialchars($badge['price_string']);
	echo '</option>';
}
echo '</select></p>';
?>
</div>
<div class="card-buttons">
<button onclick="cancelCheckIn();">Cancel Check-In</button>
<button onclick="paymentCollected();" class="action-button">Continue Check-In</button>
</div>
</div>

<div class="card checkin-state verify-info hidden">
<div class="card-content spaced">
<p>Please verify this person's badge information and make any necessary changes.</p>
<table border="0" cellpadding="0" cellspacing="0" class="form">
	<tr>
		<th><label for="first_name">First Name:</label></th>
		<td>
			<input type="hidden" name="first_name_o" id="first_name_o" class="first-name-o">
			<input type="text" name="first_name" id="first_name" class="first-name">
		</td>
	</tr>
	<tr>
		<th><label for="last_name">Last Name:</label></th>
		<td>
			<input type="hidden" name="last_name_o" id="last_name_o" class="last-name-o">
			<input type="text" name="last_name" id="last_name" class="last-name">
		</td>
	</tr>
	<tr>
		<th><label for="fandom_name">Fandom Name:</label></th>
		<td>
			<input type="hidden" name="fandom_name_o" id="fandom_name_o" class="fandom-name-o">
			<input type="text" name="fandom_name" id="fandom_name" class="fandom-name">
		</td>
	</tr>
	<tr class="tr-name-on-badge">
		<th><label for="name_on_badge">Name on Badge:</label></th>
		<td>
			<input type="hidden" name="name_on_badge_o" id="name_on_badge_o" class="name-on-badge-o">
			<select name="name_on_badge" id="name_on_badge" class="name-on-badge">
				<option value="FandomReal">Fandom Name Large, Real Name Small</option>
				<option value="RealFandom">Real Name Large, Fandom Name Small</option>
				<option value="FandomOnly">Fandom Name Only</option>
				<option value="RealOnly">Real Name Only</option>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="date_of_birth">Date of Birth:</label></th>
		<td>
			<input type="hidden" name="date_of_birth_o" id="date_of_birth_o" class="date-of-birth-o">
			<input type="date" name="date_of_birth" id="date_of_birth" class="date-of-birth">
			if (!ua('Chrome'))  (YYYY-MM-DD)
		</td>
	</tr>
</table>
</div>
<div class="card-buttons">
<button onclick="cancelCheckIn();">Cancel Check-In</button>
<button onclick="infoVerified();" class="action-button">Continue Check-In</button>
</div>
</div>

<div class="card checkin-state new-attendee hidden">
<div class="card-content spaced">
<p>Please enter this person's badge and contact information and collect the required payment amount.</p>
<table border="0" cellpadding="0" cellspacing="0" class="form">
	<tr>
		<th><label for="first_name_n">First Name:</label></th>
		<td><input type="text" name="first_name_n" id="first_name_n" class="first-name-n"></td>
	</tr>
	<tr>
		<th><label for="last_name_n">Last Name:</label></th>
		<td><input type="text" name="last_name_n" id="last_name_n" class="last-name-n"></td>
	</tr>
	<tr>
		<th><label for="fandom_name_n">Fandom Name:</label></th>
		<td><input type="text" name="fandom_name_n" id="fandom_name_n" class="fandom-name-n"></td>
	</tr>
	<tr class="tr-name-on-badge-n">
		<th><label for="name_on_badge_n">Name on Badge:</label></th>
		<td>
			<select name="name_on_badge_n" id="name_on_badge_n" class="name-on-badge-n">
				<option value="FandomReal">Fandom Name Large, Real Name Small</option>
				<option value="RealFandom">Real Name Large, Fandom Name Small</option>
				<option value="FandomOnly">Fandom Name Only</option>
				<option value="RealOnly">Real Name Only</option>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="date_of_birth_n">Date of Birth:</label></th>
		<td>
			<input type="date" name="date_of_birth_n" id="date_of_birth_n" class="date-of-birth-n">
			if (!ua('Chrome'))  (YYYY-MM-DD)
		</td>
	</tr>
	<tr>
		<th><label for="badge_id_n">Badge Type:</label></th>
		<td>
			<select name="badge_id_n" id="badge_id_n" class="badge-id-n">
				foreach ($badge_info as $badge_id => $badge) {
					<option value="'.$badge_id.'">
					echo htmlspecialchars($badge['name']);
					 - Payment Amount: 
					echo htmlspecialchars($badge['price_string']);
					</option>
				}
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="email_address_n">Email Address:</label></th>
		<td><input type="email" name="email_address_n" id="email_address_n" class="email-address-n"></td>
	</tr>
	<tr>
		<th><label for="phone_number_n">Phone Number:</label></th>
		<td><input type="text" name="phone_number_n" id="phone_number_n" class="phone-number-n"></td>
	</tr>
</table>
</div>
<div class="card-buttons">
<button onclick="cancelCheckIn();">Cancel Check-In</button>
<button onclick="newAttendeeCheckIn();" class="action-button">Continue Check-In</button>
</div>
</div>

<div class="card checkin-state badge-already-printed hidden">
<div class="card-content spaced">
<p>This person's badge has been pre-printed. Please look for:</p>
<table border="0" cellpadding="0" cellspacing="0" class="form">
	<tr>
		<th>Badge Type:</th>
		<td class="badge-preprinted-type"></td>
	</tr>
	<tr>
		<th>Badge ID:</th>
		<td class="badge-preprinted-id"></td>
	</tr>
	<tr>
		<th>Name on Badge:</th>
		<td class="badge-preprinted-name"></td>
	</tr>
</table>
<p>You may also print the badge again if necessary.</p>
</div>
<div class="card-buttons">
<button onclick="cancelCheckIn();" class="action-button">Finish Check-In</button>
<button onclick="printAgain();">Print Again</button>
</div>
</div>

<div class="card checkin-state badge-printing hidden">
<div class="card-content spaced">
<p><b>Click a badge design</b> to print the badge!</p>
<div class="badge-printing-artwork"></div>
</div>
<div class="card-buttons">
<button onclick="cancelCheckIn();" class="action-button">Finish Check-In</button>
</div>
</div>
<?php
render_admin_dialogs();
render_admin_tail();