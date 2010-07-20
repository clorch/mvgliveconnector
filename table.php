<?php
header("Content-Type: text/html; charset=utf8");

require_once('MVGLiveConnector.php');

$station = "Hauptbahnhof";
$entries = 20;

$myMVG = new MVGLiveConnector();
$data = $myMVG->getLiveData($station, $entries);


function formatWaitTime($seconds) {
	$hours = floor($seconds / 3600);

	$minutes = floor(($seconds - $hours * 3600) / 60);
	
	$seconds = round($seconds - $minutes * 60 - $hours * 3600);
	$seconds = str_pad($seconds, 2 ,'0', STR_PAD_LEFT);
	$minutes = str_pad($minutes, 2 ,'0', STR_PAD_LEFT);
	$hours = str_pad($hours, 2 ,'0', STR_PAD_LEFT);
	
	return $hours . ':' . $minutes . ':' . $seconds;
}

?>
<!DOCTYPE html>
<html>
	<head>
		<title>MVG Abfahrtsmonitor</title>

		<meta http-equiv="refresh" content="1">
	</head>

	<body>
		<h1>MVG Abfahrtsmonitor</h1>

		<table>
			<tr>
				<th>Haltestelle</th>
				<td><?php echo $station; ?></td>
			</tr>
			<tr>
				<th>Stand</th>
				<td><?php echo date("d.m.Y H:i:s", $data->date); ?></td>
			</tr>
		</table>

		<table>
			<tr>
				<th>Linie</th>
				<th>Ziel</th>
				<th>via</th>
				<th>Abfahrt in</th>
			</tr>

	<?php
		foreach ($data->abfahrten as $zug) {
			echo '<tr>';
			echo '<td><img src="http://www.mvg-live.de/MvgLive/images/size30/linie/' . $zug->imageLine . '"></td>';
			echo '<td>' . $zug->verkehrsmittel->endstation . '</td>';
			if ($zug->verkehrsmittel->endstation <> $zug->via) {
				echo '<td>' . $zug->via . '</td>';
			} else {
				echo '<td> </td>';
			}
			echo '<td>' . formatWaitTime($zug->date - $data->date) . '</td>';
			echo '</tr>';
		}
	?>
		</table>
	</body>
</html>

